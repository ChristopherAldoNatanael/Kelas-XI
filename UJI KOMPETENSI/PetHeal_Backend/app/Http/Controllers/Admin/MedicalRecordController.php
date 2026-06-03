<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Services\FCMService;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    protected FCMService $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * List all medical records
     */
    public function index()
    {
        $records = MedicalRecord::with(['pet', 'doctor', 'booking'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.medical-records.index', compact('records'));
    }

    /**
     * Show create form
     */
    public function create($bookingId)
    {
        $booking = Booking::with(['pet', 'doctor', 'user'])->findOrFail($bookingId);

        return view('admin.medical-records.create', compact('booking'));
    }

    /**
     * Store medical record
     */
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'diagnosis' => 'required|string',
            'treatment' => 'required|string',
            'medicine' => 'nullable|string',
            'notes' => 'nullable|string',
            'next_visit_date' => 'nullable|date|after:today',
            'next_visit_time' => 'nullable|date_format:H:i',
            'cost' => 'nullable|numeric|min:0',
            'treatment_cost' => 'nullable|numeric|min:0',
            'medicine_cost' => 'nullable|numeric|min:0',
        ]);

        $booking = Booking::with(['pet', 'doctor'])->findOrFail($request->input('booking_id'));

        // Auto-fill cost from booking's total_amount if not provided
        $cost = $request->input('cost');
        if ($cost === null || $cost === '' || $cost == 0) {
            $cost = $booking->total_amount ?? 0;
        }

        $record = MedicalRecord::create([
            'booking_id' => $booking->id,
            'pet_id' => $booking->pet_id,
            'doctor_id' => $booking->doctor_id,
            'diagnosis' => $request->input('diagnosis'),
            'treatment' => $request->input('treatment'),
            'medicine' => $request->input('medicine'),
            'notes' => $request->input('notes'),
            'next_visit_date' => $request->input('next_visit_date'),
            'next_visit_time' => $request->input('next_visit_time'),
            'cost' => $cost,
            'treatment_cost' => $request->input('treatment_cost', 0),
            'medicine_cost' => $request->input('medicine_cost', 0),
        ]);

        // Mark booking as completed
        $booking->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Send notification if next visit is scheduled
        $nextVisitDate = $request->input('next_visit_date');
        if ($nextVisitDate) {
            $this->fcmService->sendVaccinationReminder(
                $booking->user_id,
                $booking->pet->name,
                $nextVisitDate
            );
        }

        return redirect()->route('admin.bookings.index')->with('success', 'Medical record created successfully');
    }

    /**
     * Show medical record details
     */
    public function show($id)
    {
        $record = MedicalRecord::with(['pet', 'doctor', 'booking', 'booking.user'])->findOrFail($id);

        return view('admin.medical-records.show', compact('record'));
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $record = MedicalRecord::with(['pet', 'doctor', 'booking'])->findOrFail($id);

        return view('admin.medical-records.edit', compact('record'));
    }

    /**
     * Update medical record
     */
    public function update(Request $request, $id)
    {
        $record = MedicalRecord::findOrFail($id);

        $request->validate([
            'diagnosis' => 'required|string',
            'treatment' => 'required|string',
            'medicine' => 'nullable|string',
            'notes' => 'nullable|string',
            'next_visit_date' => 'nullable|date',
            'next_visit_time' => 'nullable|date_format:H:i',
            'cost' => 'nullable|numeric|min:0',
            'treatment_cost' => 'nullable|numeric|min:0',
            'medicine_cost' => 'nullable|numeric|min:0',
        ]);

        $record->update($request->only([
            'diagnosis',
            'treatment',
            'medicine',
            'notes',
            'next_visit_date',
            'next_visit_time',
            'cost',
            'treatment_cost',
            'medicine_cost',
        ]));

        return redirect()->route('admin.medical-records.index')->with('success', 'Medical record updated successfully');
    }

    /**
     * Delete medical record
     */
    public function destroy($id)
    {
        $record = MedicalRecord::findOrFail($id);
        $record->delete();

        return redirect()->route('admin.medical-records.index')->with('success', 'Medical record deleted successfully');
    }
}
