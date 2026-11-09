<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\ClinicDeletionService;
use App\Services\ImageService;

class ClinicController extends Controller
{
    /**
     * Aturan validasi lokasi & operasional (dipakai create/update/profil).
     *
     * Longitude wajib bila latitude diisi, agar tidak ada klinik dengan
     * koordinat setengah yang membuat perhitungan jarak salah.
     */
    private function locationRules(): array
    {
        return [
            // Larangan "setengah koordinat" ditegakkan setelah validasi (lihat
            // guardCoordinates) supaya pesannya bahasa manusia, bukan atribut
            // mentah seperti "longitude field is required".
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i|after:opening_time',
            'operating_days' => 'nullable|array',
            'operating_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'website' => 'nullable|url|max:255',
        ];
    }

    /**
     * Tolak koordinat setengah (latitude saja / longitude saja) dengan pesan
     * yang bisa ditindaklanjuti admin. Klinik dengan koordinat setengah akan
     * membuat semua perhitungan jarak salah.
     */
    private function guardCoordinates(Request $request): void
    {
        $hasLat = $request->filled('latitude');
        $hasLng = $request->filled('longitude');
        if ($hasLat === $hasLng) {
            return; // lengkap keduanya, atau kosong keduanya
        }
        $missing = $hasLat ? 'longitude' : 'latitude';
        throw ValidationException::withMessages([
            $missing => $hasLat
                ? 'Longitude belum diisi. Isi keduanya, atau kosongkan keduanya.'
                : 'Latitude belum diisi. Isi keduanya, atau kosongkan keduanya.',
        ]);
    }

    /**
     * Ambil field jam operasional dari request.
     * `operating_days` kosong = buka setiap hari (perilaku Clinic::isOpenNow).
     */
    private function operatingData(Request $request): array
    {
        $days = collect($request->input('operating_days', []))
            ->filter(fn ($d) => is_string($d))
            ->map(fn ($d) => strtolower($d))
            ->unique()
            ->values()
            ->all();

        return [
            'opening_time' => $request->filled('opening_time') ? $request->input('opening_time') : null,
            'closing_time' => $request->filled('closing_time') ? $request->input('closing_time') : null,
            'operating_days' => $days === [] ? null : $days,
            'website' => $request->filled('website') ? $request->input('website') : null,
        ];
    }

    public function index()
    {
        $clinics = Clinic::withCount([
            'doctors',
            'services',
            'bookings',
            'users',
            'medicalRecords',
            'notifications',
            'joinRequests',
            'scheduleExceptions',
        ])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.clinics.index', compact('clinics'));
    }

    public function create()
    {
        return view('admin.clinics.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:clinics,slug|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ] + $this->locationRules());
        $this->guardCoordinates($request);

        $data = collect($validated)->only([
            'name', 'slug', 'address', 'phone', 'email', 'primary_color', 'description',
        ])->toArray();
        // MULTI-DISCOVERY: koordinat & jam operasional ikut disimpan agar
        // klinik bisa diurutkan berdasarkan jarak di aplikasi.
        $data = array_merge($data, $this->operatingData($request));
        // Filled() dengan null dipakai agar koordinat bisa dikosongkan lagi.
        $data['latitude'] = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $request->input('longitude') : null;
        // PHASE 5 (SA-05): absent checkbox = false. Previously defaulted to
        // true, so unchecking "Aktif" could never deactivate via this form.
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('logo')) {
            $fileName = time() . '_' . uniqid() . '.jpg';
            $data['logo_path'] = ImageService::process(
                $request->file('logo'),
                'clinics/' . $fileName,
                400,
                85
            );
        }

        $clinic = Clinic::create($data);

        // Auto-create default admin for the new clinic.
        // PHASE 5 (SA-04): random initial password instead of predictable
        // 'admin123' (slug is public), and guard the email collision that
        // previously ended in a 500 on users.email unique.
        $defaultEmail = 'admin@' . $clinic->slug . '.com';
        if (!User::where('email', $defaultEmail)->exists()) {
            $defaultPassword = Str::random(12);
            User::create([
                'name' => 'Admin ' . $clinic->name,
                'email' => $defaultEmail,
                'password' => Hash::make($defaultPassword),
                'role' => 'clinic_admin',
                'clinic_id' => $clinic->id,
                'phone' => $clinic->phone,
            ]);

            return redirect()->route('admin.clinics.index')
                ->with('success', __('clinics.created_success'))
                ->with('new_clinic_credentials', [
                    'email' => $defaultEmail,
                    'password' => $defaultPassword,
                    'clinic' => $clinic->name,
                ]);
        }

        return redirect()->route('admin.clinics.index')
            ->with('success', __('clinics.created_success'))
            ->with('error', __('clinics.admin_email_taken', ['email' => $defaultEmail]));
    }

    public function edit(Clinic $clinic)
    {
        return view('admin.clinics.edit', compact('clinic'));
    }

    public function update(Request $request, Clinic $clinic)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:clinics,slug,' . $clinic->id . '|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ] + $this->locationRules());
        $this->guardCoordinates($request);

        $data = collect($validated)->only([
            'name', 'slug', 'address', 'phone', 'email', 'primary_color', 'description',
        ])->toArray();
        $data = array_merge($data, $this->operatingData($request));
        $data['latitude'] = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $request->input('longitude') : null;
        // PHASE 5 (SA-05): see store().
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($clinic->logo_path && !filter_var($clinic->logo_path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($clinic->logo_path);
            }
            $fileName = time() . '_' . uniqid() . '.jpg';
            $data['logo_path'] = ImageService::process(
                $request->file('logo'),
                'clinics/' . $fileName,
                400,
                85
            );
        }

        $clinic->update($data);

        return redirect()->route('admin.clinics.index')->with('success', __('clinics.updated_success'));
    }

    /**
     * Hapus klinik permanen beserta seluruh datanya (super_admin only).
     *
     * Konfirmasi memakai slug klinik — bukan sekadar tombol OK — karena
     * tindakan ini menghapus rekam medis, pembayaran, dan akun pasien.
     */
    public function destroy(Request $request, Clinic $clinic, ClinicDeletionService $deletion)
    {
        $request->validate([
            'confirm_slug' => ['required', 'string'],
        ]);

        if ($request->input('confirm_slug') !== $clinic->slug) {
            return redirect()->route('admin.clinics.index')
                ->with('error', __('clinics.delete_slug_mismatch', ['slug' => $clinic->slug]));
        }

        $clinicId = (int) $clinic->id;
        $clinicName = $clinic->name;

        $summary = $deletion->delete($clinic);

        // Jangan biarkan sesi menunjuk klinik yang sudah tidak ada.
        if ((int) session('current_clinic_id') === $clinicId) {
            session()->forget('current_clinic_id');
        }

        return redirect()->route('admin.clinics.index')
            ->with('success', __('clinics.deleted_success', [
                'name' => $clinicName,
                'users' => $summary['users'],
                'doctors' => $summary['doctors'],
                'bookings' => $summary['bookings'],
                'records' => $summary['medical_records'],
            ]));
    }

    public function toggleActive(Clinic $clinic)
    {
        $clinic->update(['is_active' => !$clinic->is_active]);
        $status = $clinic->is_active ? __('clinics.activated') : __('clinics.deactivated');
        return redirect()->back()->with('success', __('clinics.toggle_success', ['name' => $clinic->name, 'status' => $status]));
    }

    /**
     * Update the current user's own clinic (limited fields).
     *
     * Deliberately excludes `slug` (used by the Android app as the tenant
     * identity via X-Clinic-Slug — changing it orphans mobile sessions)
     * and `is_active` (deactivating locks out the whole clinic including
     * the editor). Both stay super_admin-only via update().
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'super_admin') {
            $clinic = currentClinic();

            if (!$clinic) {
                return redirect()->route('admin.clinics.index')
                    ->with('warning', __('clinics.select_clinic_first'));
            }
        } else {
            // Tenant roles may only touch their own clinic — resolved from
            // the authenticated user, never from URL/input parameters.
            $clinic = Clinic::findOrFail(requireTenantClinicId($user));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string',
        ] + $this->locationRules());
        $this->guardCoordinates($request);

        $data = collect($validated)->only(['name', 'address', 'phone', 'email', 'primary_color', 'description'])->toArray();
        // Profil klinik (clinic_admin) juga wajib bisa memperbaiki lokasi &
        // jam operasional — tanpa slug/status, sesuai kewenangannya.
        $data = array_merge($data, $this->operatingData($request));
        $data['latitude'] = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $data['longitude'] = $request->filled('longitude') ? (float) $request->input('longitude') : null;

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($clinic->logo_path && !filter_var($clinic->logo_path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($clinic->logo_path);
            }
            $fileName = time() . '_' . uniqid() . '.jpg';
            $data['logo_path'] = ImageService::process(
                $request->file('logo'),
                'clinics/' . $fileName,
                400,
                85
            );
        }

        $clinic->update($data);

        return redirect()
            ->route('admin.settings', ['tab' => 'klinik'])
            ->with('success', __('clinics.updated_success'));
    }
}