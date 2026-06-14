<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Services\FCMService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class MedicalRecordController extends Controller
{
    protected FCMService $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    private function normalizeMoneyInput(Request $request, string $key, float $fallback = 0): float
    {
        $value = $request->input($key);

        if ($value === null || $value === '') {
            return $fallback;
        }

        return max(0, (float) $value);
    }

    /**
     * List all medical records
     */
    public function index(Request $request)
    {
        $query = $this->filteredRecordsQuery($request);
        $records = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('admin.medical-records.index', compact('records'));
    }

    private function filteredRecordsQuery(Request $request)
    {
        $query = MedicalRecord::with(['pet', 'doctor', 'booking.user']);

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->input('doctor_id'));
        }

        if ($request->filled('payment_status')) {
            $query->where('extra_payment_status', $request->input('payment_status'));
        }

        return $query;
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
        $cost = $this->normalizeMoneyInput($request, 'cost');
        if ($cost == 0.0) {
            $cost = $booking->total_amount ?? 0;
        }

        $treatmentCost = $this->normalizeMoneyInput($request, 'treatment_cost');
        $medicineCost = $this->normalizeMoneyInput($request, 'medicine_cost');

        $record = new MedicalRecord([
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
            'treatment_cost' => $treatmentCost,
            'medicine_cost' => $medicineCost,
        ]);
        $record->recalculatePaymentState((float) ($booking->paid_amount ?? 0));
        $record->save();

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
                $nextVisitDate,
                $booking->pet_id,
                $record->id
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

        $record->fill($request->only([
            'diagnosis',
            'treatment',
            'medicine',
            'notes',
            'next_visit_date',
            'next_visit_time',
        ]));
        $record->loadMissing('booking');
        $record->cost = $this->normalizeMoneyInput($request, 'cost', (float) ($record->booking?->total_amount ?? 0));
        $record->treatment_cost = $this->normalizeMoneyInput($request, 'treatment_cost');
        $record->medicine_cost = $this->normalizeMoneyInput($request, 'medicine_cost');
        $record->recalculatePaymentState((float) ($record->booking?->paid_amount ?? 0));
        $record->save();

        return redirect()->route('admin.medical-records.index')->with('success', 'Medical record updated successfully');
    }

    public function exportPdf(Request $request)
    {
        $records = $this->filteredRecordsQuery($request)->latest()->get();
        $html = view('admin.exports.medical_records_pdf', compact('records'))->render();
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="medical-records-' . now()->format('Y-m-d') . '.pdf"');
    }

    public function exportCsv(Request $request)
    {
        $records = $this->filteredRecordsQuery($request)->latest()->get();
        $headers = [
            'Booking ID',
            'Owner',
            'Pet',
            'Doctor',
            'Date',
            'Diagnosis',
            'Treatment',
            'Medicine',
            'Consultation Cost',
            'Treatment Cost',
            'Medicine Cost',
            'Total Cost',
            'Payment Status',
            'Created At',
        ];

        $callback = function () use ($records, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->booking_id,
                    $record->booking?->user?->name,
                    $record->pet?->name,
                    $record->doctor?->name,
                    optional($record->created_at)->format('Y-m-d'),
                    $record->diagnosis,
                    $record->treatment,
                    $record->medicine,
                    $record->cost,
                    $record->treatment_cost,
                    $record->medicine_cost,
                    $record->total_medical_cost,
                    $record->extra_payment_status,
                    optional($record->created_at)->toDateTimeString(),
                ]);
            }
            fclose($handle);
        };

        return Response::streamDownload($callback, 'medical-records-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
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
