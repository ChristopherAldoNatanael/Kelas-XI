<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\DoctorInviteMail;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DoctorController extends Controller
{
    private function refreshDoctorApiCache(): void
    {
        Cache::forever('active_doctors_version', now()->timestamp);
    }

    /**
     * List all doctors — clinic scoped
     */
    public function index()
    {
        $clinicId = currentClinicId();

        $doctors = Doctor::query()
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->with('user:id,name,email')
            ->withCount([
                'reviews',
                'bookings',
                'bookings as completed_bookings_count' => function ($query) {
                    $query->where('status', 'completed');
                },
            ])
            ->withAvg('reviews', 'rating')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.doctors.index', compact('doctors'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('admin.doctors.create');
    }

    /**
     * Store new doctor
     */
    public function store(Request $request)
    {
        // Normalize available_days to lowercase before validation
        if ($request->has('available_days')) {
            $request->merge([
                'available_days' => array_map('strtolower', $request->available_days)
            ]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'specialization' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'available_days' => 'required|array',
            'available_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_active' => 'sometimes|boolean',
        ], [
            // Pesan bahasa sendiri: field ini dirender sebagai pemilih hari
            // (bukan select multiple), jadi error bawaan "required" kurang
            // jelas-ui dan tidak menyebut klinik.
            'available_days.required' => __('admin.day_picker_required'),
        ]);

        // PHASE 3 (F-06 orphan guard): super_admin in overview mode has no
        // target clinic — refuse instead of creating clinic-less doctors.
        $contextClinicId = currentClinicId();
        if (!$contextClinicId && isSuperAdmin()) {
            return redirect()->route('admin.doctors.index')
                ->with('error', __('admin.select_clinic_doctor'));
        }

        $data = $request->only([
            'name', 'specialization', 'phone', 'email',
            'available_days', 'start_time', 'end_time', 'is_active',
        ]);

        if ($request->hasFile('photo')) {
            try {
                $file = $request->file('photo');

                // Log file details for debugging
                Log::info('Doctor photo upload attempt:', [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);

                // ✅ OPTIMIZED: Compress and resize to max 800px width, 80% quality
                $fileName = time() . '_' . uniqid() . '.jpg';
                $path = \App\Services\ImageService::process($file, 'doctors/' . $fileName, 800, 80);

                // Verify file was stored
                if ($path && Storage::disk('public')->exists($path)) {
                    $data['photo'] = $path;
                    Log::info('Doctor photo stored successfully (compressed): ' . $path);
                } else {
                    Log::error('Failed to store doctor photo');
                }
            } catch (\Exception $e) {
                Log::error('Doctor photo upload error: ' . $e->getMessage());
            }
        }

        // Set clinic_id from current context
        $data['clinic_id'] = currentClinicId();

        $doctor = Doctor::create($data);
        $this->refreshDoctorApiCache();

        AuditLog::log('doctor.create', "Created doctor {$doctor->name}", $doctor);

        return redirect()->route('admin.doctors.index')->with('success', __('admin.doctor_created'));
    }

    /**
     * Show doctor details — clinic scoped
     */
    public function show($id)
    {
        $clinicId = currentClinicId();

        $doctor = Doctor::query()
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->withCount([
                'reviews',
                'bookings',
                'bookings as completed_bookings_count' => function ($query) {
                    $query->where('status', 'completed');
                },
                'bookings as pending_bookings_count' => function ($query) {
                    $query->where('status', 'pending');
                },
            ])
            ->withAvg('reviews', 'rating')
            ->with([
                'user:id,name,email,created_at',
                'bookings' => function ($query) {
                    $query->with(['pet', 'user'])->orderBy('booking_date', 'desc')->limit(10);
                },
                'reviews' => function ($query) {
                    $query->with(['user:id,name', 'booking:id,booking_date,booking_time,pet_id', 'booking.pet:id,name'])
                        ->latest()
                        ->limit(6);
                },
            ])
            ->findOrFail($id);

        return view('admin.doctors.show', compact('doctor'));
    }

    /**
     * Kirim tautan aktivasi akun portal dokter ke email profil dokter.
     *
     * Tidak ada akun yang dibuat di sini. Tautan membawa token acak (hash-nya
     * tersimpan di `doctors.invite_token`), berlaku 60 menit, dan hangus setelah
     * dipakai. Dokter hanya perlu membuat kata sandi — tanpa kode OTP.
     */
    public function invite(Request $request, $id)
    {
        $clinicId = currentClinicId();

        $doctor = Doctor::query()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->findOrFail($id);

        if ($doctor->user_id) {
            return back()->with('warning', 'Dokter ini sudah memiliki akun portal aktif.');
        }

        $email = strtolower(trim((string) $doctor->email));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', 'Isi alamat email dokter yang valid terlebih dahulu sebelum mengirim undangan.');
        }

        if (User::where('email', $email)->exists()) {
            return back()->with('error', 'Email dokter ini sudah dipakai akun lain. Gunakan alamat email yang berbeda.');
        }

        $clinic = $doctor->clinic;
        $ttlMinutes = 60;

        // Token asli hanya dikirim lewat email; database menyimpan hash-nya.
        // Undangan baru otomatis membatalkan tautan undangan sebelumnya.
        $token = Str::random(64);
        $doctor->forceFill([
            'invite_token' => hash('sha256', $token),
            'invite_token_expires_at' => now()->addMinutes($ttlMinutes),
        ])->save();

        $activationUrl = route('doctor.activate.token', ['token' => $token]);

        try {
            Mail::to($email)->send(new DoctorInviteMail(
                $activationUrl,
                $clinic?->name ?? 'PetHeal',
                $doctor->name,
                $ttlMinutes,
            ));
            $sent = true;
        } catch (\Throwable $e) {
            Log::warning('Email undangan dokter gagal dikirim', [
                'doctor_id' => $doctor->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            $sent = false;
        }

        AuditLog::log(
            'doctor.account.invite',
            $sent
                ? "Undangan akun portal dikirim ke {$doctor->name} ({$email})."
                : "Undangan akun portal untuk {$doctor->name} gagal dikirim lewat email.",
            $doctor
        );

        return back()->with(
            $sent ? 'success' : 'warning',
            $sent
                ? "Undangan dikirim ke {$email}. Minta dokter membuka email \"Aktivasi akun dokter\" dan mengeklik tombol Buat Kata Sandi Portal Dokter. Tautan berlaku {$ttlMinutes} menit dan hanya sekali pakai."
                : 'Tautan undangan sudah dibuat, tetapi email gagal dikirim. Coba kirim ulang beberapa saat lagi.'
        );
    }

    /**
     * Cabut akses portal dokter tanpa menghapus profil praktik/riwayat medis.
     * Baris `doctors` tetap ada (jadwal & rekam medis aman), hanya akun login
     * yang dihapus sehingga email bisa diundang ulang jika diperlukan.
     */
    public function revokeAccount($id)
    {
        $clinicId = currentClinicId();

        $doctor = Doctor::query()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->with('user')
            ->findOrFail($id);

        if (!$doctor->user) {
            return back()->with('warning', 'Dokter ini belum memiliki akun portal.');
        }

        $user = $doctor->user;

        if ($user->pets()->exists() || $user->bookings()->exists()) {
            return back()->with('error', 'Akun ini juga memiliki data pemilik hewan sehingga tidak bisa dicabut dari sini.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($doctor, $user) {
            $user->tokens()->delete();
            $doctor->update([
                'user_id' => null,
                'invite_token' => null,
                'invite_token_expires_at' => null,
            ]);
            $user->delete();
        });

        AuditLog::log('doctor.account.revoke', "Akses portal dokter {$doctor->name} dicabut.", $doctor);

        return back()->with('success', 'Akses portal dokter dicabut. Profil praktik dan rekam medis tetap tersimpan.');
    }

    /**
     * Show edit form — clinic scoped
     */
    public function edit($id)
    {
        $clinicId = currentClinicId();
        $query = Doctor::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $doctor = $query->firstOrFail();

        return view('admin.doctors.edit', compact('doctor'));
    }

    /**
     * Update doctor — clinic scoped
     */
    public function update(Request $request, $id)
    {
        $clinicId = currentClinicId();
        $query = Doctor::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $doctor = $query->firstOrFail();

        // Normalize available_days to lowercase before validation
        if ($request->has('available_days')) {
            $request->merge([
                'available_days' => array_map('strtolower', $request->available_days)
            ]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'specialization' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'available_days' => 'required|array',
            'available_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_active' => 'boolean',
        ], [
            'available_days.required' => __('admin.day_picker_required'),
        ]);

        // PHASE 3 (F-01): whitelist update fields. Previously
        // `$request->except('photo')` let `clinic_id` through (fillable but
        // unvalidated), so a clinic admin could move a doctor to another
        // tenant. `clinic_id` is intentionally NOT updatable here.
        $data = $request->only([
            'name', 'specialization', 'phone', 'email',
            'available_days', 'start_time', 'end_time', 'is_active',
        ]);

        if ($request->hasFile('photo')) {
            try {
                $file = $request->file('photo');

                // Log file details
                Log::info('Doctor photo update attempt:', [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);

                // Delete old photo if exists
                if ($doctor->photo) {
                    Storage::disk('public')->delete($doctor->photo);
                }

                // ✅ OPTIMIZED: Compress and resize to max 800px width, 80% quality
                $fileName = time() . '_' . uniqid() . '.jpg';
                $path = \App\Services\ImageService::process($file, 'doctors/' . $fileName, 800, 80);

                if ($path && Storage::disk('public')->exists($path)) {
                    $data['photo'] = $path;
                    Log::info('Doctor photo updated successfully (compressed): ' . $path);
                } else {
                    Log::error('Failed to update doctor photo');
                }
            } catch (\Exception $e) {
                Log::error('Doctor photo update error: ' . $e->getMessage());
            }
        }

        $previousEmail = strtolower(trim((string) $doctor->email));

        $doctor->update($data);

        // Email berubah sebelum akun aktif → tautan undangan lama tidak boleh
        // lagi berlaku, karena tautan itu dikirim ke alamat lama.
        $newEmail = strtolower(trim((string) $doctor->email));
        if ($newEmail !== $previousEmail && !$doctor->user_id) {
            $doctor->forceFill([
                'invite_token' => null,
                'invite_token_expires_at' => null,
            ])->save();
        }

        $this->refreshDoctorApiCache();

        AuditLog::log('doctor.update', "Updated doctor {$doctor->name}", $doctor);

        return redirect()->route('admin.doctors.index')->with('success', __('admin.doctor_updated'));
    }

    /**
     * Delete doctor — clinic scoped
     */
    public function destroy($id)
    {
        $clinicId = currentClinicId();
        $query = Doctor::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $doctor = $query->firstOrFail();

        // Profil dokter yang masih punya akun portal harus dicabut dulu supaya
        // tidak meninggalkan user role=doctor tanpa profil praktik.
        if ($doctor->user_id) {
            return back()->with('error', 'Cabut akses portal dokter terlebih dahulu sebelum menghapus profil dokter.');
        }

        // Delete photo if exists
        if ($doctor->photo) {
            Storage::disk('public')->delete($doctor->photo);
        }

        AuditLog::log('doctor.delete', "Deleted doctor {$doctor->name}", $doctor);

        $doctor->delete();
        $this->refreshDoctorApiCache();

        return redirect()->route('admin.doctors.index')->with('success', __('admin.doctor_deleted'));
    }
}
