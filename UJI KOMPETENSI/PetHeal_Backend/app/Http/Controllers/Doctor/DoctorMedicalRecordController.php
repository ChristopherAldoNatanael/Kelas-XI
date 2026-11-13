<?php

namespace App\Http\Controllers\Doctor;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Services\FCMService;
use App\Services\MedicalRecordAttachmentService;
use App\Services\PrescriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Rekam medis yang dibuat dokter untuk booking miliknya sendiri.
 *
 * Aturan klinis tetap sama dengan panel admin: satu booking satu rekam medis,
 * booking batal tidak boleh dihidupkan kembali, dan penyimpanan rekam medis
 * menuntaskan booking dalam satu transaksi.
 */
class DoctorMedicalRecordController extends DoctorPortalController
{
    public function __construct(private readonly FCMService $fcmService)
    {
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
        Cache::forget('dashboard_stats_v3_c' . $suffix . '_all');
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
                ->with('error', 'Booking yang dibatalkan tidak bisa dibuatkan rekam medis.');
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
            'diagnosis' => 'required|string',
            'treatment' => 'required|string',
            // SOAP — aturan identik dengan panel admin supaya dokter dan admin
            // tidak bisa menyimpan bentuk data yang berbeda.
            'soap' => 'nullable|array',
            'soap.subjective' => 'nullable|string|max:5000',
            'soap.objective' => 'nullable|string|max:5000',
            'soap.assessment' => 'nullable|string|max:5000',
            'soap.plan' => 'nullable|string|max:5000',
            'medicine' => 'nullable|string',
            'notes' => 'nullable|string',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:' . implode(',', MedicalRecordAttachmentService::ALLOWED_MIMES)
                . '|max:' . MedicalRecordAttachmentService::MAX_KB,
            // Resep terstruktur — hanya obat milik klinik dokter ini.
            'prescription' => 'nullable|array|max:20',
            'prescription.*.medicine_id' => [
                'nullable',
                'integer',
                Rule::exists('medicines', 'id')->where('clinic_id', $doctor->clinic_id),
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

        $booking = Booking::with(['pet'])
            ->where('id', $request->input('booking_id'))
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->firstOrFail();

        if ($booking->status === 'cancelled') {
            return redirect()->back()->withInput()
                ->with('error', 'Booking yang dibatalkan tidak bisa dibuatkan rekam medis.');
        }

        if (MedicalRecord::where('booking_id', $booking->id)->exists()) {
            return redirect()->route('doctor.bookings.show', $booking->id)
                ->with('warning', 'Booking ini sudah memiliki rekam medis.');
        }

        $cost = $this->normalizeMoneyInput($request, 'cost');
        if ($cost == 0.0) {
            $cost = (float) ($booking->total_amount ?? 0);
        }

        $record = new MedicalRecord([
            'booking_id' => $booking->id,
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
            'cost' => $cost,
            'treatment_cost' => $this->normalizeMoneyInput($request, 'treatment_cost'),
            'medicine_cost' => $this->normalizeMoneyInput($request, 'medicine_cost'),
        ]);

        try {
            DB::transaction(function () use ($record, $booking, $request) {
                // Simpan dulu supaya `$record->id` ada: item resep memakai
                // foreign key NOT NULL ke medical_records. Tanpa ini, dokter
                // yang mengisi resep saat membuat rekam medis baru kena 500.
                $record->save();

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
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $this->forgetDashboardCaches($record->clinic_id ?: $doctor->clinic_id);

        if ($request->filled('next_visit_date')) {
            $this->fcmService->sendVaccinationReminder(
                $booking->user_id,
                $booking->pet->name,
                (string) $request->input('next_visit_date'),
                $booking->pet_id,
                $record->id
            );
        }

        AuditLog::log('doctor.medical_record.create', "Dokter membuat rekam medis #{$record->id} untuk {$booking->pet->name}.", $record);

        return redirect()->route('doctor.medical-records.show', $record->id)
            ->with('success', 'Rekam medis tersimpan dan booking ditandai selesai.');
    }

    public function show($id)
    {
        $doctor = $this->doctor();

        $record = MedicalRecord::with(['pet', 'booking.user', 'doctor'])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->findOrFail($id);

        return view('doctor.medical-records.show', compact('doctor', 'record'));
    }

    public function edit($id)
    {
        $doctor = $this->doctor();

        $record = MedicalRecord::with(['pet', 'booking.user', 'doctor', 'prescriptionItems', 'attachments'])
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
        ]);
    }

    public function update(Request $request, $id)
    {
        $doctor = $this->doctor();

        $record = MedicalRecord::with('booking')
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->findOrFail($id);

        $request->validate([
            'diagnosis' => 'required|string',
            'treatment' => 'required|string',
            'medicine' => 'nullable|string',
            'notes' => 'nullable|string',
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
            'prescription.*.unit_price' => 'nullable|numeric|min:0',
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

        $record->cost = $this->normalizeMoneyInput($request, 'cost', (float) ($record->booking?->total_amount ?? 0));
        $record->treatment_cost = $this->normalizeMoneyInput($request, 'treatment_cost');

        // Koreksi resep: stok lama dikembalikan lalu dipotong ulang.
        $manualMedicineCost = $this->normalizeMoneyInput($request, 'medicine_cost');
        try {
            $prescriptionTotal = app(PrescriptionService::class)->syncForRecord(
                $record,
                (array) $request->input('prescription', [])
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $record->medicine_cost = $prescriptionTotal > 0 ? $prescriptionTotal : $manualMedicineCost;
        $record->recalculatePaymentState((float) ($record->booking?->paid_amount ?? 0));
        $record->save();

        // Hapus lampiran yang dicentang dokter. Lewat service agar berkas di
        // disk ikut terhapus (bukan hanya baris database).
        $attachmentService = app(MedicalRecordAttachmentService::class);
        $removeIds = array_filter((array) $request->input('remove_attachments', []));
        if ($removeIds) {
            $toRemove = $record->attachments()->whereIn('id', $removeIds)->get();
            foreach ($toRemove as $attachment) {
                $attachmentService->delete($attachment);
            }
        }

        $attachmentService->storeMany($record, $request->file('attachments', []), auth()->id());

        $this->forgetDashboardCaches($record->clinic_id ?: $doctor->clinic_id);

        AuditLog::log('doctor.medical_record.update', "Dokter memperbarui rekam medis #{$record->id}.", $record);

        return redirect()->route('doctor.medical-records.show', $record->id)
            ->with('success', 'Rekam medis diperbarui.');
    }

    private function normalizeMoneyInput(Request $request, string $key, float $fallback = 0): float
    {
        $value = $request->input($key);

        if ($value === null || $value === '') {
            return $fallback;
        }

        return max(0, (float) $value);
    }
}
