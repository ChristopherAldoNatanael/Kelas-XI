<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Medicine;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAttachment;
use App\Services\FCMService;
use App\Services\MedicalRecordAttachmentService;
use App\Services\PrescriptionService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

class MedicalRecordController extends Controller
{
    protected FCMService $fcmService;
    protected MedicalRecordAttachmentService $attachments;
    protected PrescriptionService $prescriptions;

    public function __construct(
        FCMService $fcmService,
        MedicalRecordAttachmentService $attachments,
        PrescriptionService $prescriptions
    ) {
        $this->fcmService = $fcmService;
        $this->attachments = $attachments;
        $this->prescriptions = $prescriptions;
    }

    /**
     * Drop the cached admin aggregates for a clinic.
     *
     * A new/edited/deleted record changes revenue, so the 5-minute dashboard
     * cache must not outlive the write. Same keys as
     * PaymentStatusService::clearPaymentCaches().
     */
    private function forgetDashboardCaches(?int $clinicId): void
    {
        $suffix = $clinicId ?: 'all';
        Cache::forget('payment_stats_c' . $suffix);
        Cache::forget('dashboard_stats_v3_c' . $suffix . '_all');
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

        // Summaries describe the whole filtered result set, not just the
        // current page (the old implementation summed getCollection()).
        $totalRecords = (clone $query)->count();
        $extraPendingCount = (clone $query)
            ->whereIn('extra_payment_status', ['unpaid', 'pending', 'partial'])
            ->count();
        $totalMedicalRevenue = (clone $query)
            ->selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
            ->value('total') ?? 0;

        $records = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('admin.medical-records.index', compact(
            'records',
            'totalRecords',
            'extraPendingCount',
            'totalMedicalRevenue'
        ));
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

        return view('admin.medical-records.create', [
            'booking' => $booking,
            // Obat aktif klinik booking — server memvalidasi hal yang sama.
            // Obat kedaluwarsa sengaja tidak ditampilkan: resep obat lewat
            // tanggal adalah kesalahan klinis, bukan sekadar data usang.
            'medicines' => Medicine::where('clinic_id', $booking->clinic_id)
                ->dispensable()
                ->fefo()
                ->get(),
        ]);
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
            // SOAP: opsional agar rekam medis lama tetap bisa disimpan, tetapi
            // bila diisi disimpan apa adanya. Dikirim sebagai `soap.*` (satu
            // grup field) supaya form tidak perlu empat nama terpisah.
            'soap' => 'nullable|array',
            'soap.subjective' => 'nullable|string|max:5000',
            'soap.objective' => 'nullable|string|max:5000',
            'soap.assessment' => 'nullable|string|max:5000',
            'soap.plan' => 'nullable|string|max:5000',
            'medicine' => 'nullable|string',
            'notes' => 'nullable|string',
            // Lampiran hasil pemeriksaan. Validasi sejak awal — bukan hanya
            // mengandalkan ekstensi nama file.
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:' . implode(',', MedicalRecordAttachmentService::ALLOWED_MIMES)
                . '|max:' . MedicalRecordAttachmentService::MAX_KB,
            // Resep terstruktur. Hanya id obat milik klinik ini yang lolos,
            // sehingga tidak mungkin memotong stok klinik lain.
            'prescription' => 'nullable|array|max:20',
            'prescription.*.medicine_id' => [
                'nullable',
                'integer',
                $clinicId
                    ? \Illuminate\Validation\Rule::exists('medicines', 'id')->where('clinic_id', $clinicId)
                    : 'integer',
            ],
            'prescription.*.quantity' => 'nullable|numeric|min:0',
            'prescription.*.dose' => 'nullable|string|max:100',
            'prescription.*.unit_price' => 'nullable|numeric|min:0',
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
                ->with('error', __('admin.medical_record_cancelled_booking'));
        }

        if (MedicalRecord::where('booking_id', $booking->id)->exists()) {
            return redirect()->back()->withInput()
                ->with('error', __('admin.medical_record_exists'));
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
            'subjective' => $request->input('soap.subjective'),
            'objective' => $request->input('soap.objective'),
            'assessment' => $request->input('soap.assessment'),
            'plan' => $request->input('soap.plan'),
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
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($record, $booking, $request) {
                // Rekam medis WAJIB punya id sebelum item resep ditulis:
                // `prescription_items.medical_record_id` NOT NULL, sehingga
                // syncForRecord() pada record yang belum disimpan dulu selalu
                // gagal (500) begitu admin memilih obat di form create.
                $record->save();

                // Resep setelah record tersimpan: bila stok tidak cukup,
                // transaksi dibatalkan (RuntimeException) dan tidak ada data
                // setengah jadi.
                $prescriptionTotal = $this->prescriptions->syncForRecord(
                    $record,
                    (array) $request->input('prescription', [])
                );

                // Biaya obat otomatis dari resep; input manual tetap dipakai
                // bila resep kosong (klinik belum memakai master obat).
                if ($prescriptionTotal > 0) {
                    $record->medicine_cost = $prescriptionTotal;
                }

                $record->recalculatePaymentState((float) ($booking->paid_amount ?? 0));
                $record->save();

                // Lampiran ikut transaksi yang sama: bila penyimpanan berkas
                // gagal, rekam medis juga batal sehingga tidak ada record
                // tanpa bukti.
                $this->attachments->storeMany(
                    $record,
                    $request->file('attachments', []),
                    auth()->id()
                );

                // Mark booking as completed
                $booking->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            // Pesan stok kurang sudah dibuat manusiawi oleh StockService.
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $this->forgetDashboardCaches($record->clinic_id ?: currentClinicId());

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

        return redirect()->route('admin.bookings.index')->with('success', __('admin.medical_record_created'));
    }

    /**
     * Show medical record details — clinic scoped
     */
    public function show($id)
    {
        $clinicId = currentClinicId();
        $query = MedicalRecord::with([
            'pet', 'doctor', 'booking', 'booking.user',
            // Resep & lampiran dirender di halaman detail; tanpa eager load
            // ini setiap baris memicu query sendiri.
            'prescriptionItems', 'attachments',
        ])->where('id', $id);
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

        return view('admin.medical-records.edit', [
            'record' => $record,
            'medicines' => Medicine::where('clinic_id', $record->clinic_id)
                ->dispensable()
                ->fefo()
                ->get(),
        ]);
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
            'soap' => 'nullable|array',
            'soap.subjective' => 'nullable|string|max:5000',
            'soap.objective' => 'nullable|string|max:5000',
            'soap.assessment' => 'nullable|string|max:5000',
            'soap.plan' => 'nullable|string|max:5000',
            'medicine' => 'nullable|string',
            'notes' => 'nullable|string',
            // "Harus tanggal ke depan" hanya berlaku saat tanggal benar-benar
            // diubah. Rekam medis lama dengan jadwal kontrol yang sudah lewat
            // tetap bisa diperbaiki bagian lain tanpa dipaksa mengganti tanggal.
            'next_visit_date' => [
                'nullable',
                'date',
                \Illuminate\Validation\Rule::when(
                    $request->filled('next_visit_date')
                        && $request->input('next_visit_date') !== $record->next_visit_date?->toDateString(),
                    ['after:today']
                ),
            ],
            'next_visit_time' => 'nullable|date_format:H:i',
            'cost' => 'nullable|numeric|min:0',
            'treatment_cost' => 'nullable|numeric|min:0',
            'medicine_cost' => 'nullable|numeric|min:0',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:' . implode(',', MedicalRecordAttachmentService::ALLOWED_MIMES)
                . '|max:' . MedicalRecordAttachmentService::MAX_KB,
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'integer',
        ]);

        $record->fill($request->only([
            'diagnosis',
            'treatment',
            'medicine',
            'notes',
            'next_visit_date',
            'next_visit_time',
        ]));

        // SOAP tidak lewat fill() karena input bernama `soap.*`.
        $record->subjective = $request->input('soap.subjective');
        $record->objective = $request->input('soap.objective');
        $record->assessment = $request->input('soap.assessment');
        $record->plan = $request->input('soap.plan');

        $record->loadMissing('booking');
        $record->cost = $this->normalizeMoneyInput($request, 'cost', (float) ($record->booking?->total_amount ?? 0));
        $record->treatment_cost = $this->normalizeMoneyInput($request, 'treatment_cost');

        // Resep dikoreksi: stok lama dikembalikan lalu dipotong ulang sesuai
        // qty baru, sehingga tidak ada pemotongan berlapis.
        $manualMedicineCost = $this->normalizeMoneyInput($request, 'medicine_cost');
        try {
            $prescriptionTotal = $this->prescriptions->syncForRecord(
                $record,
                (array) $request->input('prescription', [])
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $record->medicine_cost = $prescriptionTotal > 0 ? $prescriptionTotal : $manualMedicineCost;
        $record->recalculatePaymentState((float) ($record->booking?->paid_amount ?? 0));
        $record->save();

        // Lampiran: hapus yang dicentang dulu, lalu simpan yang baru.
        // Lewat service supaya berkas di disk ikut terhapus.
        $removeIds = array_filter((array) $request->input('remove_attachments', []));
        if ($removeIds) {
            $toRemove = $record->attachments()->whereIn('id', $removeIds)->get();
            foreach ($toRemove as $attachment) {
                $this->attachments->delete($attachment);
            }
        }

        $this->attachments->storeMany($record, $request->file('attachments', []), auth()->id());

        $this->forgetDashboardCaches($record->clinic_id ?: $clinicId);

        return redirect()->route('admin.medical-records.index')->with('success', __('admin.medical_record_updated'));
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
        // Keep the export streamable: lazy() fetches rows in chunks instead of
        // loading the whole filtered table into memory before writing.
        $query = $this->filteredRecordsQuery($request)->latest();
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

        $callback = function () use ($query, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($query->lazy(500) as $record) {
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
        $cacheClinicId = $record->clinic_id ?: $clinicId;

        // Stok yang terlanjur dipotong resep dikembalikan sebelum record
        // hilang — kalau tidak, obat "hilang" tanpa penjelasan.
        $this->prescriptions->restoreOnDelete($record);

        $record->delete();
        $this->forgetDashboardCaches($cacheClinicId);

        return redirect()->route('admin.medical-records.index')->with('success', __('admin.medical_record_deleted'));
    }
}
