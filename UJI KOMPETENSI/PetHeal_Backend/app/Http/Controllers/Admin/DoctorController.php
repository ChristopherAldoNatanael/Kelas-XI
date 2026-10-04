<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        ]);

        // PHASE 3 (F-06 orphan guard): super_admin in overview mode has no
        // target clinic — refuse instead of creating clinic-less doctors.
        $contextClinicId = currentClinicId();
        if (!$contextClinicId && isSuperAdmin()) {
            return redirect()->route('admin.doctors.index')
                ->with('error', 'Pilih klinik terlebih dahulu sebelum menambah dokter.');
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

        return redirect()->route('admin.doctors.index')->with('success', 'Dokter berhasil ditambahkan.');
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

        $doctor->update($data);
        $this->refreshDoctorApiCache();

        AuditLog::log('doctor.update', "Updated doctor {$doctor->name}", $doctor);

        return redirect()->route('admin.doctors.index')->with('success', 'Dokter berhasil diperbarui.');
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

        // Delete photo if exists
        if ($doctor->photo) {
            Storage::disk('public')->delete($doctor->photo);
        }

        AuditLog::log('doctor.delete', "Deleted doctor {$doctor->name}", $doctor);

        $doctor->delete();
        $this->refreshDoctorApiCache();

        return redirect()->route('admin.doctors.index')->with('success', 'Dokter berhasil dihapus.');
    }
}
