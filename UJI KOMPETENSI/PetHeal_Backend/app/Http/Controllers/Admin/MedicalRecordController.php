<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAttachment;
use App\Models\Medicine;
use App\Models\Payment;
use App\Services\FCMService;
use App\Services\MedicalRecordAttachmentService;
use App\Services\MedicalRecordRevisionService;
use App\Services\PrescriptionService;
use App\Services\MedicalRecordLifecycleService;
use App\Services\RecordLinkService;
use App\Services\VitalMeasurementService;
use Dompdf\Dompdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Concerns\ManagesEmergencyRecord;

class MedicalRecordController extends Controller
{
    use ManagesEmergencyRecord;

    protected FCMService $fcmService;
    protected MedicalRecordAttachmentService $attachments;
    protected PrescriptionService $prescriptions;
    protected MedicalRecordRevisionService $revisions;
    protected RecordLinkService $recordLinks;
    protected MedicalRecordLifecycleService $lifecycle;
    protected VitalMeasurementService $vitals;

    public function __construct(
        FCMService $fcmService,
        MedicalRecordAttachmentService $attachments,
        PrescriptionService $prescriptions,
        MedicalRecordRevisionService $revisions,
        RecordLinkService $recordLinks,
        MedicalRecordLifecycleService $lifecycle,
        VitalMeasurementService $vitals
    ) {
        $this->fcmService = $fcmService;
        $this->attachments = $attachments;
        $this->prescriptions = $prescriptions;
        $this->revisions = $revisions;
        $this->recordLinks = $recordLinks;
        $this->lifecycle = $lifecycle;
        $this->vitals = $vitals;
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
        Cache::forget('dashboard_stats_v4_c' . $suffix . '_all');
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
     * Biaya konsultasi: angka 0 yang diketik admin adalah nilai sah (mis.
     * konsultasi gratis). Fallback ke total booking hanya terjadi bila field
     * memang tidak dikirim sama sekali.
     */
    private function resolveConsultationCost(Request $request, Booking $booking): float
    {
        $value = $request->input('cost');

        if ($value === null || $value === '') {
            return (float) ($booking->total_amount ?? 0);
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
            'status' => 'nullable|in:final,void,archived',
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

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
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
        $query = Booking::with(['pet', 'doctor', 'user', 'medicalRecord'])->where('id', $bookingId);
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
            'allergies' => $booking->pet?->allergies()->where('is_active', true)->get() ?? collect(),
        ]);
    }

    /**
     * Aturan validasi isian klinis yang dipakai create & update.
     */
    private function validateClinicalPayload(Request $request, ?int $clinicId): array
    {
        $request->validate([
            'booking_id' => $this->bookingRule($clinicId), // diabaikan pada update
            'diagnosis' => 'required|string|max:20000',
            'treatment' => 'required|string|max:20000',
            // SOAP: opsional agar rekam medis lama tetap bisa disimpan, tetapi
            // bila diisi disimpan apa adanya. Dikirim sebagai `soap.*` (satu
            // grup field) supaya form tidak perlu empat nama terpisah.
            'soap' => 'nullable|array',
            'soap.subjective' => 'nullable|string|max:5000',
            'soap.objective' => 'nullable|string|max:5000',
            'soap.assessment' => 'nullable|string|max:5000',
            'soap.plan' => 'nullable|string|max:5000',
            'medicine' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
            // Lampiran hasil pemeriksaan. Validasi sejak awal — bukan hanya
            // mengandalkan ekstensi nama file.
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:' . implode(',', MedicalRecordAttachmentService::ALLOWED_MIMES)
                . '|max:' . MedicalRecordAttachmentService::MAX_KB,
            // Tanda vital tidak wajib; diisi bila pemeriksaan memang mencatatnya.
            'vitals' => 'nullable|array',
            'vitals.temperature_c' => 'nullable|numeric|between:20,50',
            'vitals.heart_rate' => 'nullable|integer|between:1,400',
            'vitals.respiratory_rate' => 'nullable|integer|between:1,200',
            'vitals.crt_seconds' => 'nullable|numeric|between:0,10',
            'vitals.mucosa' => 'nullable|string|max:50',
            'vitals.hydration' => 'nullable|string|max:30',
            'vitals.bcs' => 'nullable|integer|between:1,9',
            'vitals.pain_score' => 'nullable|integer|between:0,4',
            'vitals.weight_kg' => 'nullable|numeric|between:0.1,500',
            'vitals.notes' => 'nullable|string|max:500',
            // Resep terstruktur. Hanya id obat milik klinik ini yang lolos,
            // sehingga tidak mungkin memotong stok klinik lain. Harga TIDAK
            // diterima dari form: selalu diambil dari master obat di server.
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
            'prescription.*.route' => 'nullable|string|max:50',
            'prescription.*.frequency' => 'nullable|string|max:50',
            'prescription.*.duration_days' => 'nullable|integer|min:0|max:365',
            'prescription.*.instruction' => 'nullable|string|max:255',
            'outcome' => 'nullable|in:sembuh,membaik,dirujuk,mati,euthanasia,berlanjut',
            'cause_of_death' => 'nullable|string|max:255',
            'euthanasia_reason' => 'nullable|string|max:255',
            'disposal_method' => 'nullable|in:pemakaman,kremasi,lainnya',
            'next_visit_date' => 'nullable|date',
            'next_visit_time' => 'nullable|date_format:H:i',
            'cost' => 'nullable|numeric|min:0',
            'treatment_cost' => 'nullable|numeric|min:0',
            'medicine_cost' => 'nullable|numeric|min:0',
        ]);

        return $request->only([
            'booking_id', 'diagnosis', 'treatment', 'medicine', 'notes',
            'next_visit_date', 'next_visit_time', 'cost', 'treatment_cost', 'medicine_cost',
            'outcome', 'cause_of_death', 'euthanasia_reason', 'disposal_method',
        ]);
    }

    private function bookingRule(?int $clinicId)
    {
        $rule = \Illuminate\Validation\Rule::exists('bookings', 'id');

        if ($clinicId) {
            $rule->where('clinic_id', $clinicId);
        }

        return $rule;
    }

    /**
     * Store medical record
     */
    public function store(Request $request)
    {
        $clinicId = currentClinicId();
        $validated = $this->validateClinicalPayload($request, $clinicId);

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

        $adminCannotFinalize = config('medical_records.lifecycle_enforcement_enabled', false)
            && auth()->user()?->role !== 'doctor';

        $record = new MedicalRecord([
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
            'cost' => $this->resolveConsultationCost($request, $booking),
            'treatment_cost' => $this->normalizeMoneyInput($request, 'treatment_cost'),
            'medicine_cost' => $this->normalizeMoneyInput($request, 'medicine_cost'),
            'clinic_id' => $booking->clinic_id,
            'status' => $adminCannotFinalize ? MedicalRecord::STATUS_DRAFT : MedicalRecord::STATUS_FINAL,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'finalized_at' => $adminCannotFinalize ? null : now(),
            'finalized_by' => $adminCannotFinalize ? null : auth()->id(),
            'outcome' => $request->input('outcome'),
            'cause_of_death' => $request->input('cause_of_death'),
            'euthanasia_reason' => $request->input('euthanasia_reason'),
            'disposal_method' => $request->input('disposal_method'),
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

                // Relasi booking hanya ditulis oleh RecordLinkService.
                $this->recordLinks->linkBooking($record, $booking);

                $this->persistVitals($record, $request);

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
        } catch (QueryException $e) {
            // Unique index `booking_id` adalah penjaga terakhir saat dua
            // request datang bersamaan; pesan ke pengguna tetap manusiawi.
            if ($this->isDuplicateBookingRecord($e)) {
                return redirect()->back()->withInput()
                    ->with('error', __('admin.medical_record_exists'));
            }

            throw $e;
        } catch (\RuntimeException $e) {
            // Pesan stok kurang sudah dibuat manusiawi oleh StockService.
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $this->revisions->record(
            $record,
            'create',
            [],
            $this->revisions->snapshot($record),
            'Rekam medis dibuat'
        );

        if (! $adminCannotFinalize) {
            $this->lifecycle->finalize($record, auth()->user());
        }

        AuditLog::log(
            'medical_record.created',
            "Membuat rekam medis #{$record->id} untuk {$booking->pet?->name}.",
            $record
        );

        $this->forgetDashboardCaches($record->clinic_id ?: currentClinicId());

        // Pemeriksaan selesai → antrian pasien ikut ditutup. Bila pasien tidak
        // pernah check-in (tidak ada nomor), tidak ada yang perlu diubah.
        try {
            $freshBooking = $booking->fresh();
            if ($freshBooking && $freshBooking->queue_number
                && in_array($freshBooking->queue_status, \App\Services\QueueService::ACTIVE_STATUSES, true)) {
                app(\App\Services\QueueService::class)
                    ->transition($freshBooking, \App\Services\QueueService::STATUS_DONE);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Queue completion sync failed', [
                'booking_id' => $booking->id,
                'message' => $e->getMessage(),
            ]);
        }

        // Jadwal kontrol tidak langsung dikirim di sini: `reminders:send`
        // mengirimkannya H-3 dan H-0 supaya pesan tidak datang terlalu dini.
        return redirect()->route('admin.bookings.index')->with('success', __('admin.medical_record_created'));
    }

    /**
     * Show medical record details — clinic scoped
     */
    public function show($id)
    {
        $clinicId = currentClinicId();
        $query = MedicalRecord::with([
            'pet', 'pet.allergies', 'doctor', 'booking', 'booking.user',
            // Resep & lampiran dirender di halaman detail; tanpa eager load
            // ini setiap baris memicu query sendiri.
            'prescriptionItems', 'attachments', 'vitals', 'revisions.changedBy',
            'encounter', 'treatmentConsents',
        ])->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $record = $query->firstOrFail();

        return view('admin.medical-records.show', compact('record'));
    }

    protected function resolveEmergencyRecord(int $id): MedicalRecord
    {
        $clinicId = currentClinicId();

        return MedicalRecord::with(['encounter', 'booking', 'pet', 'doctor', 'clinic', 'treatmentConsents'])
            ->whereKey($id)
            ->when($clinicId, fn ($query) => $query->where('clinic_id', $clinicId))
            ->firstOrFail();
    }

    /**
     * Show edit form — clinic scoped
     */
    public function edit($id)
    {
        $clinicId = currentClinicId();
        $query = MedicalRecord::with(['pet', 'pet.allergies', 'doctor', 'booking', 'vitals'])->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $record = $query->firstOrFail();

        return view('admin.medical-records.edit', [
            'record' => $record,
            'allergies' => $record->pet?->allergies()->where('is_active', true)->get() ?? collect(),
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
        $query = MedicalRecord::with('booking')->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $record = $query->firstOrFail();

        if ($record->isVoided()) {
            return redirect()->route('admin.medical-records.show', $record->id)
                ->with('error', 'Rekam medis yang sudah dibatalkan tidak dapat diubah.');
        }

        $adminCannotFinalize = config('medical_records.lifecycle_enforcement_enabled', false)
            && auth()->user()?->role !== 'doctor';

        if ($adminCannotFinalize && $record->status === MedicalRecord::STATUS_FINAL) {
            return redirect()->route('admin.medical-records.show', $record->id)
                ->with('error', 'Rekam medis final tidak dapat diubah admin. Ajukan amendment klinis.');
        }

        $this->validateClinicalPayload($request, $clinicId);

        $request->validate([
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
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'integer',
            'change_reason' => 'nullable|string|max:255',
        ]);

        $before = $this->revisions->snapshot($record);

        try {
            DB::transaction(function () use ($record, $request) {
                $record->fill($request->only([
                    'diagnosis',
                    'treatment',
                    'medicine',
                    'notes',
                    'next_visit_date',
                    'next_visit_time',
                    'outcome',
                    'cause_of_death',
                    'euthanasia_reason',
                    'disposal_method',
                ]));

                // SOAP tidak lewat fill() karena input bernama `soap.*`.
                $record->subjective = $request->input('soap.subjective');
                $record->objective = $request->input('soap.objective');
                $record->assessment = $request->input('soap.assessment');
                $record->plan = $request->input('soap.plan');

                $record->loadMissing('booking');
                $record->cost = $this->resolveConsultationCost($request, $record->booking);
                $record->treatment_cost = $this->normalizeMoneyInput($request, 'treatment_cost');
                $record->updated_by = auth()->id();

                // Resep dikoreksi: stok lama dikembalikan lalu dipotong ulang
                // sesuai qty baru, sehingga tidak ada pemotongan berlapis.
                $manualMedicineCost = $this->normalizeMoneyInput($request, 'medicine_cost');
                $prescriptionTotal = $this->prescriptions->syncForRecord(
                    $record,
                    (array) $request->input('prescription', [])
                );

                $record->medicine_cost = $prescriptionTotal > 0 ? $prescriptionTotal : $manualMedicineCost;
                $record->recalculatePaymentState((float) ($record->booking?->paid_amount ?? 0));
                $record->save();

                // Backfill/rekonsiliasi relasi untuk record lama yang belum
                // punya encounter. Untuk record baru ini idempoten.
                if ($record->booking) {
                    $this->recordLinks->linkBooking($record, $record->booking);
                }

                $this->persistVitals($record, $request);

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
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $this->revisions->record(
            $record,
            'update',
            $before,
            $this->revisions->snapshot($record->fresh()),
            $request->input('change_reason')
        );

        if (! $adminCannotFinalize) {
            $this->lifecycle->finalize($record, auth()->user());
        }

        AuditLog::log(
            'medical_record.updated',
            "Memperbarui rekam medis #{$record->id}.",
            $record
        );

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

        $this->auditExport('pdf', $records->count());

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="medical-records-' . now()->format('Y-m-d') . '.pdf"');
    }

    public function exportCsv(Request $request)
    {
        // Keep the export streamable: lazy() fetches rows in chunks instead of
        // loading every row into memory.
        $query = $this->filteredRecordsQuery($request)->latest();
        $headers = [
            'Booking ID',
            'Status',
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

        $rows = 0;
        $callback = function () use ($query, $headers, &$rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($query->lazy(500) as $record) {
                $rows++;
                fputcsv($handle, [
                    $record->booking_id,
                    $record->status,
                    $this->csvSafe($record->booking?->user?->name),
                    $this->csvSafe($record->pet?->name),
                    $this->csvSafe($record->doctor?->name),
                    optional($record->created_at)->format('Y-m-d'),
                    $this->csvSafe($record->diagnosis),
                    $this->csvSafe($record->treatment),
                    $this->csvSafe($record->medicine),
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

        $this->auditExport('csv', null);

        return Response::streamDownload($callback, 'medical-records-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Nilai sel yang dimulai dengan karakter formula dianggap teks oleh Excel.
     * Tanpa ini, diagnosis/terapi yang diawali `=` atau `+` dieksekusi sebagai
     * formula saat file dibuka.
     */
    private function csvSafe($value): string
    {
        $text = (string) ($value ?? '');

        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $text;
        }

        return $text;
    }

    private function auditExport(string $format, ?int $rows): void
    {
        AuditLog::log(
            'medical_record.exported',
            sprintf('Mengekspor rekam medis (%s%s).', strtoupper($format), $rows !== null ? ", {$rows} baris" : ''),
            null
        );
    }

    /**
     * Batalkan rekam medis.
     *
     * Rekaman TIDAK dihapus: statusnya menjadi `void` dan isinya tetap utuh
     * untuk retensi, ekspor, dan pemeriksaan. Stok obat yang pernah dipotong
     * dikembalikan karena obat tidak jadi diserahkan.
     */
    public function destroy(Request $request, $id)
    {
        $clinicId = currentClinicId();
        $query = MedicalRecord::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $record = $query->firstOrFail();

        if ($record->isVoided()) {
            return redirect()->route('admin.medical-records.show', $record->id)
                ->with('warning', 'Rekam medis ini sudah dibatalkan sebelumnya.');
        }

        $request->validate([
            'void_reason' => 'nullable|string|max:255',
        ]);

        $reason = $request->input('void_reason') ?: 'Dibatalkan oleh admin klinik';
        $before = $this->revisions->snapshot($record);

        try {
            DB::transaction(function () use ($record, $reason) {
                // Stok yang terlanjur dipotong resep dikembalikan karena obat
                // tidak jadi diserahkan.
                $this->prescriptions->restoreOnDelete($record);

                // Lampiran tetap disimpan sebagai bagian dari dokumen; yang
                // berubah hanya status rekam medis.
                $record->void($reason, auth()->id());
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Gagal membatalkan rekam medis', [
                'medical_record_id' => $record->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('admin.medical-records.show', $record->id)
                ->with('error', 'Rekam medis gagal dibatalkan: ' . $e->getMessage());
        }

        $this->revisions->record(
            $record,
            'void',
            $before,
            $this->revisions->snapshot($record->fresh()),
            $reason
        );

        AuditLog::create([
            'user_id' => auth()->id(),
            'clinic_id' => $record->clinic_id ?: $clinicId,
            'action' => 'medical_record.voided',
            'model_type' => MedicalRecord::class,
            'model_id' => $record->id,
            'description' => sprintf(
                'Rekam medis #%d untuk booking #%s dibatalkan (biaya %s). Alasan: %s',
                $record->id,
                $record->booking_id ?: '-',
                number_format((float) $record->total_medical_cost, 0, ',', '.'),
                $reason
            ),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);

        $this->forgetDashboardCaches($record->clinic_id ?: $clinicId);

        return redirect()->route('admin.medical-records.index')
            ->with('success', 'Rekam medis dibatalkan. Isinya tetap tersimpan untuk retensi.');
    }

    /**
     * Resume medis resmi satu pasien (kop klinik, identitas legal dokter,
     * cap isi dokumen, dan tautan verifikasi).
     */
    public function resumePdf($id)
    {
        $clinicId = currentClinicId();
        $query = MedicalRecord::with([
            'pet', 'pet.allergies', 'doctor', 'clinic', 'booking.user',
            'prescriptionItems', 'vitals', 'attachments',
        ])->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $record = $query->firstOrFail();

        $verifyUrl = URL::temporarySignedRoute(
            'medical-records.verify',
            now()->addDays(365),
            ['record' => $record->id]
        );

        $html = view('medical-records.resume', [
            'record' => $record,
            'verifyUrl' => $verifyUrl,
            'contentHash' => $record->content_hash ?: $record->computeContentHash(),
        ])->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        AuditLog::log('medical_record.resume_printed', "Mencetak resume medis #{$record->id}.", $record);

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="resume-medis-' . $record->id . '.pdf"');
    }

    /**
     * Halaman verifikasi keaslian dokumen (dibuka lewat URL bertanda tangan).
     * Sengaja tidak menampilkan diagnosis: cukup status dan cap isi dokumen.
     */
    public function verify(Request $request, $id)
    {
        $record = MedicalRecord::with(['pet', 'doctor', 'clinic'])->findOrFail($id);
        $currentHash = $record->computeContentHash();

        return view('medical-records.verify', [
            'record' => $record,
            'isIntact' => $record->content_hash !== null && hash_equals($record->content_hash, $currentHash),
            'currentHash' => $currentHash,
        ]);
    }

    /**
     * Unduh lampiran lewat URL bertanda tangan. Berlaku untuk admin, dokter,
     * dan aplikasi pemilik tanpa membuka folder storage ke publik.
     */
    public function downloadAttachment(Request $request, $record, $attachment)
    {
        $model = MedicalRecordAttachment::where('medical_record_id', $record)->findOrFail($attachment);

        if (!Storage::disk(MedicalRecordAttachment::DISK)->exists($model->path)) {
            abort(404, 'Berkas lampiran tidak ditemukan.');
        }

        AuditLog::log(
            'medical_record.attachment_downloaded',
            "Mengunduh lampiran #{$model->id} dari rekam medis #{$record}.",
            null
        );

        $contents = $this->attachments->read($model);
        $fileName = $model->original_name ?: basename($model->path);

        return response($contents, 200, [
            'Content-Type' => $model->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . addslashes($fileName) . '"',
        ]);
    }

    /**
     * Tambah alergi hewan dari halaman rekam medis.
     *
     * Alergi bukan bagian dari satu kunjungan, melainkan peringatan seumur
     * hidup hewan. Karena itu data disimpan di `pet_allergies` dan langsung
     * dipakai form resep untuk memperingatkan dokter.
     */
    public function storeAllergy(Request $request, $id)
    {
        $clinicId = currentClinicId();
        $record = MedicalRecord::when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->findOrFail($id);

        $validated = $request->validate([
            'allergen' => 'required|string|max:100',
            'reaction' => 'nullable|string|max:255',
            'severity' => 'required|in:ringan,sedang,berat',
        ]);

        $allergy = $record->pet->allergies()->create([
            'allergen' => $validated['allergen'],
            'reaction' => $validated['reaction'] ?? null,
            'severity' => $validated['severity'],
            'is_active' => true,
            'noted_by' => auth()->id(),
            'noted_at' => now(),
        ]);

        AuditLog::log(
            'pet.allergy_recorded',
            sprintf(
                'Mencatat alergi "%s" untuk hewan #%d dari rekam medis #%d.',
                $allergy->allergen,
                $record->pet_id,
                $record->id
            ),
            $record
        );

        return redirect()->route('admin.medical-records.show', $record->id)
            ->with('success', 'Alergi berhasil dicatat dan akan muncul sebagai peringatan pada resep.');
    }

    public function destroyAllergy(Request $request, $id, $allergy)
    {
        $clinicId = currentClinicId();
        $record = MedicalRecord::when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->findOrFail($id);

        $model = $record->pet->allergies()->findOrFail($allergy);
        $model->update(['is_active' => false]);

        AuditLog::log(
            'pet.allergy_deactivated',
            sprintf('Menonaktifkan alergi #%d pada hewan #%d.', $model->id, $record->pet_id),
            $record
        );

        return redirect()->route('admin.medical-records.show', $record->id)
            ->with('success', 'Alergi dinonaktifkan.');
    }

    /**
     * Simpan tanda vital satu kunjungan. Bila semua field kosong, baris lama
     * tidak dibuat/diubah supaya record tanpa pemeriksaan fisik tetap bersih.
     */
    private function persistVitals(MedicalRecord $record, Request $request): void
    {
        $input = (array) $request->input('vitals', []);
        $hasValue = collect($input)->contains(fn ($value) => $value !== null && $value !== '');

        if (!$hasValue) {
            return;
        }

        $this->vitals->recordForRecord($record, $input, auth()->user());
    }

    private function isDuplicateBookingRecord(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062
            || str_contains($e->getMessage(), 'medical_records_booking_id_unique');
    }
}
