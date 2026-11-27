<?php

namespace App\Http\Controllers\Doctor;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Services\FCMService;
use App\Services\MedicalRecordAttachmentService;
use App\Services\MedicalRecordRevisionService;
use App\Services\PrescriptionService;
use App\Services\QueueService;
use App\Services\MedicalRecordLifecycleService;
use App\Services\RecordLinkService;
use App\Services\VitalMeasurementService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Concerns\ManagesEmergencyRecord;
use Illuminate\Validation\Rule;
use Dompdf\Dompdf;

/**
 * Rekam medis yang dibuat dokter untuk booking miliknya sendiri.
 *
 * Aturan klinis tetap sama dengan panel admin: satu booking satu rekam medis,
 * booking batal tidak boleh dihidupkan kembali, dan penyimpanan rekam medis
 * menuntaskan booking dalam satu transaksi. Setiap perubahan isi meninggalkan
 * baris revisi, sehingga riwayat klinis tidak bisa hilang diam-diam.
 */
class DoctorMedicalRecordController extends DoctorPortalController
{
    use ManagesEmergencyRecord;

    public function __construct(
        private readonly FCMService $fcmService,
        private readonly QueueService $queueService,
        private readonly RecordLinkService $recordLinks,
        private readonly MedicalRecordLifecycleService $lifecycle,
        private readonly VitalMeasurementService $vitals
    ) {
    }

    /**
     * Drop the cached admin aggregates for a clinic — a record written from the
     * doctor portal changes admin revenue exactly like one written from the
     * admin console. Same keys as PaymentStatusService::clearPaymentCaches().
     */
    private function forgetDashboardCaches(?int $clinicId): void
    {
        $suffix = $clinicId ?: 'all';
        Cache::forget('payment_stats_c' . $suffix);
        Cache::forget('dashboard_stats_v4_c' . $suffix . '_all');
    }

    public function index(Request $request)
    {
        $doctor = $this->doctor();

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'q' => 'nullable|string|max:100',
        ]);

        $search = trim((string) ($validated['q'] ?? ''));

        $records = MedicalRecord::with(['pet', 'booking.user'])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->when(!empty($validated['from']), fn ($q) => $q->whereDate('created_at', '>=', $validated['from']))
            ->when(!empty($validated['to']), fn ($q) => $q->whereDate('created_at', '<=', $validated['to']))
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('pet', fn ($pet) => $pet->where('name', 'like', '%' . $search . '%'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $total = MedicalRecord::where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->count();

        return view('doctor.medical-records.index', compact('doctor', 'records', 'total'));
    }

    public function create($bookingId)
    {
        $doctor = $this->doctor();

        $booking = Booking::with(['pet', 'user', 'doctor', 'medicalRecord'])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->findOrFail($bookingId);

        if ($booking->status === 'cancelled') {
            return redirect()->route('doctor.bookings.show', $booking->id)
                ->with('error', __('doctor.cancelled_booking_no_record'));
        }

        if ($booking->medicalRecord) {
            return redirect()->route('doctor.medical-records.edit', $booking->medicalRecord->id)
                ->with('warning', 'Booking ini sudah memiliki rekam medis. Silakan perbarui rekam medis yang ada.');
        }

        return view('doctor.medical-records.form', [
            'doctor' => $doctor,
            'booking' => $booking,
            'record' => null,
            // Obat aktif klinik dokter ini — sama dengan yang divalidasi server.
            // Kedaluwarsa dikecualikan: dokter tidak boleh memilih obat lewat tanggal.
            'medicines' => Medicine::where('clinic_id', $doctor->clinic_id)
                ->dispensable()
                ->fefo()
                ->get(),
            'allergies' => $booking->pet?->allergies()->where('is_active', true)->get() ?? collect(),
        ]);
    }

    public function store(Request $request)
    {
        $doctor = $this->doctor();

        $request->validate([
            'booking_id' => [
                'required',
                Rule::exists('bookings', 'id')
                    ->where('doctor_id', $doctor->id)
                    ->where('clinic_id', $doctor->clinic_id),
            ],
            'diagnosis' => 'required|string|max:20000',
            'treatment' => 'required|string|max:20000',
            // SOAP — aturan identik dengan panel admin supaya dokter dan admin
            // tidak bisa menyimpan bentuk data yang berbeda.
            'soap' => 'nullable|array',
            'soap.subjective' => 'nullable|string|max:5000',
            'soap.objective' => 'nullable|string|max:5000',
            'soap.assessment' => 'nullable|string|max:5000',
            'soap.plan' => 'nullable|string|max:5000',
            'medicine' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:' . implode(',', MedicalRecordAttachmentService::ALLOWED_MIMES)
                . '|max:' . MedicalRecordAttachmentService::MAX_KB,
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
            // Resep terstruktur — hanya obat milik klinik dokter ini. Harga
            // selalu dari master obat di server, bukan dari payload.
            'prescription' => 'nullable|array|max:20',
            'prescription.*.medicine_id' => [
                'nullable',
                'integer',
                Rule::exists('medicines', 'id')->where('clinic_id', $doctor->clinic_id),
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
            'next_visit_date' => 'nullable|date|after:today',
            'next_visit_time' => 'nullable|date_format:H:i',
            'cost' => 'nullable|numeric|min:0',
            'treatment_cost' => 'nullable|numeric|min:0',
            'medicine_cost' => 'nullable|numeric|min:0',
        ]);

        $booking = Booking::with(['pet'])
            ->where('id', $request->input('booking_id'))
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->firstOrFail();

        if ($booking->status === 'cancelled') {
            return redirect()->back()->withInput()
                ->with('error', __('doctor.cancelled_booking_no_record'));
        }

        if (MedicalRecord::where('booking_id', $booking->id)->exists()) {
            return redirect()->route('doctor.bookings.show', $booking->id)
                ->with('warning', 'Booking ini sudah memiliki rekam medis.');
        }

        $record = new MedicalRecord([
            'pet_id' => $booking->pet_id,
            'doctor_id' => $booking->doctor_id,
            'clinic_id' => $booking->clinic_id,
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
            'status' => MedicalRecord::STATUS_FINAL,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),
            'outcome' => $request->input('outcome'),
            'cause_of_death' => $request->input('cause_of_death'),
            'euthanasia_reason' => $request->input('euthanasia_reason'),
            'disposal_method' => $request->input('disposal_method'),
        ]);

        try {
            DB::transaction(function () use ($record, $booking, $request) {
                // Simpan dulu supaya `$record->id` ada: item resep memakai
                // foreign key NOT NULL ke medical_records. Tanpa ini, dokter
                // yang mengisi resep saat membuat rekam medis baru kena 500.
                $record->save();

                // Relasi booking hanya ditulis oleh RecordLinkService.
                $this->recordLinks->linkBooking($record, $booking);

                $this->persistVitals($record, $request);

                // Resep diproses berikutnya: stok kurang membatalkan seluruh
                // transaksi, jadi tidak ada rekam medis setengah jadi.
                $prescriptionTotal = app(PrescriptionService::class)->syncForRecord(
                    $record,
                    (array) $request->input('prescription', [])
                );

                if ($prescriptionTotal > 0) {
                    $record->medicine_cost = $prescriptionTotal;
                }

                $record->recalculatePaymentState((float) ($booking->paid_amount ?? 0));
                $record->save();

                // Lampiran disimpan sebelum booking ditandai selesai, supaya
                // kegagalan upload membatalkan semuanya.
                app(MedicalRecordAttachmentService::class)->storeMany(
                    $record,
                    $request->file('attachments', []),
                    auth()->id()
                );

                $booking->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return redirect()->back()->withInput()
                    ->with('warning', 'Booking ini sudah memiliki rekam medis.');
            }

            throw $e;
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $revisions = app(MedicalRecordRevisionService::class);
        $revisions->record($record, 'create', [], $revisions->snapshot($record), 'Rekam medis dibuat oleh dokter');
        $this->lifecycle->finalize($record, auth()->user());

        $this->forgetDashboardCaches($record->clinic_id ?: $doctor->clinic_id);

        // Sinkronkan antrian: pemeriksaan selesai saat rekam medis tersimpan.
        // Kegagalan antrian tidak boleh membatalkan rekam medis yang sudah
        // sah — dicatat, bukan dilempar ke dokter.
        try {
            $freshBooking = $booking->fresh();
            if ($freshBooking && $freshBooking->queue_number
                && in_array($freshBooking->queue_status, QueueService::ACTIVE_STATUSES, true)) {
                $this->queueService->transition($freshBooking, QueueService::STATUS_DONE);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Queue completion sync failed', [
                'booking_id' => $booking->id,
                'message' => $e->getMessage(),
            ]);
        }

        // Pengingat kontrol dikirim terjadwal oleh `reminders:send` (H-3/H-0),
        // bukan langsung saat menyimpan — pesan yang datang terlalu dini
        // membuat pemilik mengira jadwalnya hari ini.
        AuditLog::log('doctor.medical_record.create', "Dokter membuat rekam medis #{$record->id} untuk {$booking->pet->name}.", $record);

        return redirect()->route('doctor.medical-records.show', $record->id)
            ->with('success', __('doctor.record_saved'));
    }

    public function show($id)
    {
        $doctor = $this->doctor();

        $record = MedicalRecord::with([
            'pet', 'pet.allergies', 'booking.user', 'doctor', 'vitals',
            'prescriptionItems', 'attachments', 'clinic', 'revisions.changedBy',
            'encounter', 'treatmentConsents',
        ])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->findOrFail($id);

        return view('doctor.medical-records.show', compact('doctor', 'record'));
    }

    protected function resolveEmergencyRecord(int $id): MedicalRecord
    {
        $doctor = $this->doctor();

        return MedicalRecord::with(['encounter', 'booking', 'pet', 'doctor', 'clinic', 'treatmentConsents'])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->findOrFail($id);
    }

    public function edit($id)
    {
        $doctor = $this->doctor();

        $record = MedicalRecord::with([
            'pet', 'pet.allergies', 'booking.user', 'doctor',
            'prescriptionItems', 'attachments', 'vitals',
        ])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->findOrFail($id);

        return view('doctor.medical-records.form', [
            'doctor' => $doctor,
            'booking' => $record->booking,
            'record' => $record,
            'medicines' => Medicine::where('clinic_id', $doctor->clinic_id)
                ->dispensable()
                ->fefo()
                ->get(),
            'allergies' => $record->pet?->allergies()->where('is_active', true)->get() ?? collect(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $doctor = $this->doctor();

        $record = MedicalRecord::with('booking')
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->findOrFail($id);

        if ($record->isVoided()) {
            return redirect()->route('doctor.medical-records.show', $record->id)
                ->with('error', 'Rekam medis yang sudah dibatalkan tidak dapat diubah.');
        }

        $request->validate([
            'diagnosis' => 'required|string|max:20000',
            'treatment' => 'required|string|max:20000',
            'medicine' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
            // Sama seperti panel admin: "tanggal ke depan" hanya divalidasi
            // bila tanggalnya diubah, agar rekam medis lama tetap bisa diedit.
            'next_visit_date' => [
                'nullable',
                'date',
                Rule::when(
                    $request->filled('next_visit_date')
                        && $request->input('next_visit_date') !== $record->next_visit_date?->toDateString(),
                    ['after:today']
                ),
            ],
            'next_visit_time' => 'nullable|date_format:H:i',
            'cost' => 'nullable|numeric|min:0',
            'treatment_cost' => 'nullable|numeric|min:0',
            'medicine_cost' => 'nullable|numeric|min:0',
            'soap' => 'nullable|array',
            'soap.subjective' => 'nullable|string|max:5000',
            'soap.objective' => 'nullable|string|max:5000',
            'soap.assessment' => 'nullable|string|max:5000',
            'soap.plan' => 'nullable|string|max:5000',
            'outcome' => 'nullable|in:sembuh,membaik,dirujuk,mati,euthanasia,berlanjut',
            'cause_of_death' => 'nullable|string|max:255',
            'euthanasia_reason' => 'nullable|string|max:255',
            'disposal_method' => 'nullable|in:pemakaman,kremasi,lainnya',
            'change_reason' => 'nullable|string|max:255',
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
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:' . implode(',', MedicalRecordAttachmentService::ALLOWED_MIMES)
                . '|max:' . MedicalRecordAttachmentService::MAX_KB,
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'integer',
            'prescription' => 'nullable|array|max:20',
            'prescription.*.medicine_id' => [
                'nullable',
                'integer',
                Rule::exists('medicines', 'id')->where('clinic_id', $doctor->clinic_id),
            ],
            'prescription.*.quantity' => 'nullable|numeric|min:0',
            'prescription.*.dose' => 'nullable|string|max:100',
            'prescription.*.route' => 'nullable|string|max:50',
            'prescription.*.frequency' => 'nullable|string|max:50',
            'prescription.*.duration_days' => 'nullable|integer|min:0|max:365',
            'prescription.*.instruction' => 'nullable|string|max:255',
        ]);

        $revisions = app(MedicalRecordRevisionService::class);
        $before = $revisions->snapshot($record);

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

                $record->cost = $this->resolveConsultationCost($request, $record->booking);
                $record->treatment_cost = $this->normalizeMoneyInput($request, 'treatment_cost');
                $record->updated_by = auth()->id();

                // Koreksi resep: stok lama dikembalikan lalu dipotong ulang.
                $manualMedicineCost = $this->normalizeMoneyInput($request, 'medicine_cost');
                $prescriptionTotal = app(PrescriptionService::class)->syncForRecord(
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

                // Hapus lampiran yang dicentang dokter. Lewat service agar
                // riwayat penghapusan tetap tercatat (soft delete).
                $attachmentService = app(MedicalRecordAttachmentService::class);
                $removeIds = array_filter((array) $request->input('remove_attachments', []));
                if ($removeIds) {
                    $toRemove = $record->attachments()->whereIn('id', $removeIds)->get();
                    foreach ($toRemove as $attachment) {
                        $attachmentService->delete($attachment);
                    }
                }

                $attachmentService->storeMany($record, $request->file('attachments', []), auth()->id());
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $revisions->record(
            $record,
            'update',
            $before,
            $revisions->snapshot($record->fresh()),
            $request->input('change_reason')
        );

        $this->lifecycle->finalize($record, auth()->user());

        $this->forgetDashboardCaches($record->clinic_id ?: $doctor->clinic_id);

        AuditLog::log('doctor.medical_record.update', "Dokter memperbarui rekam medis #{$record->id}.", $record);

        return redirect()->route('doctor.medical-records.show', $record->id)
            ->with('success', __('doctor.record_updated'));
    }

    /**
     * Resume medis resmi yang bisa diserahkan ke pemilik.
     */
    public function resumePdf($id)
    {
        $doctor = $this->doctor();

        $record = MedicalRecord::with([
            'pet', 'pet.allergies', 'doctor', 'clinic', 'booking.user',
            'prescriptionItems', 'vitals', 'attachments',
        ])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->findOrFail($id);

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

        AuditLog::log('medical_record.resume_printed', "Dokter mencetak resume medis #{$record->id}.", $record);

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="resume-medis-' . $record->id . '.pdf"');
    }

    /**
     * Biaya konsultasi: angka 0 adalah nilai sah; fallback hanya saat field
     * tidak dikirim.
     */
    private function resolveConsultationCost(Request $request, Booking $booking): float
    {
        $value = $request->input('cost');

        if ($value === null || $value === '') {
            return (float) ($booking->total_amount ?? 0);
        }

        return max(0, (float) $value);
    }

    private function normalizeMoneyInput(Request $request, string $key, float $fallback = 0): float
    {
        $value = $request->input($key);

        if ($value === null || $value === '') {
            return $fallback;
        }

        return max(0, (float) $value);
    }

    private function persistVitals(MedicalRecord $record, Request $request): void
    {
        $input = (array) $request->input('vitals', []);
        $hasValue = collect($input)->contains(fn ($value) => $value !== null && $value !== '');

        if (!$hasValue) {
            return;
        }

        $this->vitals->recordForRecord($record, $input, auth()->user());
    }
}
