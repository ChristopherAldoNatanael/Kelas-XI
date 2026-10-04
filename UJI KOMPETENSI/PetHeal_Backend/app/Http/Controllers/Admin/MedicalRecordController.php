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
        // PHASE 4: validate list/export filters in one place — malformed
        // dates previously reached whereDate() raw (DB error instead of 422).
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'doctor_id' => 'nullable|integer',
            'payment_status' => 'nullable|in:pending,partial,paid,failed,unpaid,not_required',
        ]);

        $clinicId = currentClinicId();
        $query = MedicalRecord::with(['pet', 'doctor', 'booking.user'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId));

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
     * Show create form — clinic scoped
     */
    public function create($bookingId)
    {
        $clinicId = currentClinicId();
        $query = Booking::with(['pet', 'doctor', 'user'])->where('id', $bookingId);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $booking = $query->firstOrFail();

        return view('admin.medical-records.create', compact('booking'));
    }

    /**
     * Store medical record
     */
    public function store(Request $request)
    {
        $clinicId = currentClinicId();
        $bookingExistsRule = \Illuminate\Validation\Rule::exists('bookings', 'id');
        if ($clinicId) {
            $bookingExistsRule->where('clinic_id', $clinicId);
        }
        $request->validate([
            // PHASE 3 (V-01): scope the existence check to this clinic so a
            // foreign booking id fails validation instead of leaking via 404.
            'booking_id' => ['required', $bookingExistsRule],
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

        $bookingQuery = Booking::with(['pet', 'doctor'])->where('id', $request->input('booking_id'));
        if ($clinicId) {
            $bookingQuery->where('clinic_id', $clinicId);
        }
        $booking = $bookingQuery->firstOrFail();

        // PHASE 3 (B10): one record per booking (hasOne) and never resurrect
        // a cancelled booking as completed.
        if ($booking->status === 'cancelled') {
            return redirect()->back()->withInput()
                ->with('error', 'Tidak dapat membuat rekam medis untuk booking yang dibatalkan.');
        }

        if (MedicalRecord::where('booking_id', $booking->id)->exists()) {
            return redirect()->back()->withInput()
                ->with('error', 'Booking ini sudah memiliki rekam medis.');
        }

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
            'clinic_id' => $booking->clinic_id,
        ]);
        // PHASE 3 (D-08): record + booking completion must be atomic — a
        // crash between the two left orphan records on pending bookings.
        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $booking) {
            $record->recalculatePaymentState((float) ($booking->paid_amount ?? 0));
            $record->save();

            // Mark booking as completed
            $booking->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });

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

        return redirect()->route('admin.bookings.index')->with('success', 'Rekam medis berhasil ditambahkan.');
    }

    /**
     * Show medical record details — clinic scoped
     */
    public function show($id)
    {
        $clinicId = currentClinicId();
        $query = MedicalRecord::with(['pet', 'doctor', 'booking', 'booking.user'])->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $record = $query->firstOrFail();

        return view('admin.medical-records.show', compact('record'));
    }

    /**
     * Show edit form — clinic scoped
     */
    public function edit($id)
    {
        $clinicId = currentClinicId();
        $query = MedicalRecord::with(['pet', 'doctor', 'booking'])->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $record = $query->firstOrFail();

        return view('admin.medical-records.edit', compact('record'));
    }

    /**
     * Update medical record — clinic scoped
     */
    public function update(Request $request, $id)
    {
        $clinicId = currentClinicId();
        $query = MedicalRecord::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $record = $query->firstOrFail();

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

        return redirect()->route('admin.medical-records.index')->with('success', 'Rekam medis berhasil diperbarui.');
    }

    public function exportPdf(Request $request)
    {
        // PHASE 4: cap matches the "Limited to 200 records" subtitle and
        // keeps Dompdf from OOM-ing on unbounded ranges.
        $records = $this->filteredRecordsQuery($request)->latest()->limit(200)->get();
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
     * Delete medical record — clinic scoped
     */
    public function destroy($id)
    {
        $clinicId = currentClinicId();
        $query = MedicalRecord::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $record = $query->firstOrFail();
        $record->delete();

        return redirect()->route('admin.medical-records.index')->with('success', 'Rekam medis berhasil dihapus.');
    }
}
