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
    /**
     * List all doctors
     */
    public function index()
    {
        $doctors = Doctor::orderBy('name')->paginate(20);

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
        ]);

        $data = $request->except('photo');

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

        $doctor = Doctor::create($data);

        AuditLog::log('doctor.create', "Created doctor {$doctor->name}", $doctor);

        return redirect()->route('admin.doctors.index')->with('success', 'Doctor created successfully');
    }

    /**
     * Show doctor details
     */
    public function show($id)
    {
        $doctor = Doctor::with(['bookings' => function ($query) {
            $query->orderBy('booking_date', 'desc')->limit(10);
        }])->findOrFail($id);

        return view('admin.doctors.show', compact('doctor'));
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $doctor = Doctor::findOrFail($id);

        return view('admin.doctors.edit', compact('doctor'));
    }

    /**
     * Update doctor
     */
    public function update(Request $request, $id)
    {
        $doctor = Doctor::findOrFail($id);

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

        $data = $request->except('photo');

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

        AuditLog::log('doctor.update', "Updated doctor {$doctor->name}", $doctor);

        return redirect()->route('admin.doctors.index')->with('success', 'Doctor updated successfully');
    }

    /**
     * Delete doctor
     */
    public function destroy($id)
    {
        $doctor = Doctor::findOrFail($id);

        // Delete photo if exists
        if ($doctor->photo) {
            Storage::disk('public')->delete($doctor->photo);
        }

        AuditLog::log('doctor.delete', "Deleted doctor {$doctor->name}", $doctor);

        $doctor->delete();

        return redirect()->route('admin.doctors.index')->with('success', 'Doctor deleted successfully');
    }
}
