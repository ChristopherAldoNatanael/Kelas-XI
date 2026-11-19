<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Services\ServiceRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Review permintaan layanan oleh admin klinik.
 *
 * Semua query dibatasi `clinic_id` sesi. Admin tidak dapat melihat atau
 * menawarkan layanan untuk klinik lain, dan harga penawaran hanya ditentukan
 * di sini (bukan oleh dokter).
 */
class ServiceRequestController extends Controller
{
    public function __construct(private readonly ServiceRequestService $service)
    {
    }

    public function index(Request $request)
    {
        $clinicId = currentClinicId();

        if (!$clinicId) {
            return view('admin.service-requests.select_clinic', [
                'clinics' => Clinic::where('is_active', true)->orderBy('name')->get(),
            ]);
        }

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(ServiceRequest::STATUSES)],
            'q' => 'nullable|string|max:100',
        ]);

        $status = $validated['status'] ?? null;
        $search = trim((string) ($validated['q'] ?? ''));

        $requests = ServiceRequest::with(['pet', 'user', 'doctor', 'clinic'])
            ->where('clinic_id', $clinicId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', '%' . $search . '%')
                        ->orWhere('complaint', 'like', '%' . $search . '%')
                        ->orWhereHas('pet', fn ($pet) => $pet->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('user', fn ($owner) => $owner->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->orderByRaw("FIELD(status, 'submitted', 'under_review', 'waiting_doctor_review', 'waiting_offer', 'offered', 'accepted', 'converted_to_booking', 'expired', 'rejected', 'cancelled')")
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $counts = ServiceRequest::where('clinic_id', $clinicId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.service-requests.index', compact('requests', 'status', 'search', 'counts'));
    }

    public function show($id)
    {
        $request = $this->findScoped((int) $id);
        $request->load(['pet', 'user', 'doctor', 'offerDoctor', 'offerService', 'clinic', 'medicalRecord', 'booking']);

        $doctors = Doctor::where('clinic_id', $request->clinic_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $services = Service::where('clinic_id', $request->clinic_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.service-requests.show', compact('request', 'doctors', 'services'));
    }

    public function update(Request $request, $id)
    {
        $serviceRequest = $this->findScoped((int) $id);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['review', 'request_info', 'assign_doctor', 'offer', 'reject', 'cancel'])],
            'admin_notes' => 'nullable|string|max:2000',
            'doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')->where('clinic_id', $serviceRequest->clinic_id)],
            'offer_service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('clinic_id', $serviceRequest->clinic_id)],
            'offer_service_name' => 'nullable|string|max:150',
            'offer_description' => 'nullable|string|max:2000',
            'offer_price' => 'nullable|numeric|min:0|max:1000000000',
            'offer_price_type' => 'nullable|in:final,estimate',
            'offer_duration_minutes' => 'nullable|integer|min:0|max:1440',
            'offer_doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')->where('clinic_id', $serviceRequest->clinic_id)],
            'offer_schedule_date' => 'nullable|date|after_or_equal:today',
            'offer_schedule_time' => 'nullable|date_format:H:i',
            'offer_notes' => 'nullable|string|max:2000',
            'offer_valid_until' => 'nullable|date|after:now',
        ], [
            'offer_price.numeric' => 'Harga penawaran harus berupa angka.',
            'offer_schedule_date.after_or_equal' => 'Jadwal penawaran tidak boleh di masa lalu.',
        ]);

        try {
            switch ($validated['action']) {
                case 'review':
                    $this->moveTo($serviceRequest, ServiceRequest::STATUS_UNDER_REVIEW, [], 'under_review');
                    $message = 'Permintaan ditandai sedang ditinjau.';
                    break;

                case 'request_info':
                    $this->moveTo($serviceRequest, ServiceRequest::STATUS_UNDER_REVIEW, [
                        'admin_notes' => $validated['admin_notes'] ?? null,
                    ], 'info_required', forceNotify: true);
                    $message = 'Permintaan informasi tambahan dikirim ke pemilik.';
                    break;

                case 'assign_doctor':
                    if (empty($validated['doctor_id'])) {
                        throw new RuntimeException('Pilih dokter yang akan mengevaluasi permintaan ini.');
                    }

                    // `moveTo` sudah melewati `under_review` bila perlu, jadi
                    // cukup satu pemanggilan (sebelumnya ada dua, dan yang
                    // pertama tidak melakukan apa pun selain UPDATE).
                    $this->moveTo($serviceRequest, ServiceRequest::STATUS_WAITING_DOCTOR, [
                        'doctor_id' => $validated['doctor_id'],
                        'admin_notes' => $validated['admin_notes'] ?? $serviceRequest->admin_notes,
                    ]);
                    $message = 'Dokter ditugaskan untuk evaluasi medis.';
                    break;

                case 'offer':
                    $offerService = null;
                    if (!empty($validated['offer_service_id'])) {
                        $offerService = Service::where('id', $validated['offer_service_id'])
                            ->where('clinic_id', $serviceRequest->clinic_id)
                            ->first();
                    }

                    $name = trim((string) ($validated['offer_service_name'] ?? ''))
                        ?: $offerService?->name;

                    if (!$name) {
                        throw new RuntimeException('Nama layanan penawaran wajib diisi.');
                    }

                    if (empty($validated['offer_doctor_id']) || empty($validated['offer_schedule_date']) || empty($validated['offer_schedule_time'])) {
                        throw new RuntimeException('Dokter, tanggal, dan jam pada penawaran wajib diisi agar pemilik dapat menyetujuinya.');
                    }

                    $this->service->offer($serviceRequest, [
                        'offer_service_id' => $offerService?->id,
                        'offer_service_name' => $name,
                        'offer_description' => $validated['offer_description'] ?? $offerService?->description,
                        'offer_price' => $validated['offer_price'] ?? null,
                        'offer_price_type' => $validated['offer_price_type'] ?? 'final',
                        'offer_duration_minutes' => $validated['offer_duration_minutes'] ?? null,
                        'offer_doctor_id' => $validated['offer_doctor_id'],
                        'offer_schedule_date' => $validated['offer_schedule_date'],
                        'offer_schedule_time' => $validated['offer_schedule_time'],
                        'offer_notes' => $validated['offer_notes'] ?? null,
                        'offer_valid_until' => $validated['offer_valid_until'] ?? null,
                    ]);
                    $message = 'Penawaran dikirim ke pemilik hewan.';
                    break;

                case 'reject':
                    if (empty(trim((string) ($validated['admin_notes'] ?? '')))) {
                        throw new RuntimeException('Alasan penolakan wajib diisi.');
                    }

                    $this->moveTo($serviceRequest, ServiceRequest::STATUS_REJECTED, [
                        'admin_notes' => $validated['admin_notes'],
                    ], 'rejected');
                    $message = 'Permintaan ditolak dengan alasan yang dikirim ke pemilik.';
                    break;

                default: // cancel
                    $this->moveTo($serviceRequest, ServiceRequest::STATUS_CANCELLED, [
                        'admin_notes' => $validated['admin_notes'] ?? null,
                    ]);
                    $message = 'Permintaan dibatalkan.';
                    break;
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        \App\Models\AuditLog::log(
            'service_request.' . $validated['action'],
            "Admin memproses permintaan {$serviceRequest->code} ({$validated['action']}).",
            $serviceRequest
        );

        return redirect()->route('admin.service-requests.show', $serviceRequest->id)->with('success', $message);
    }

    /**
     * Pindahkan status sambil melewati status antara bila perlu.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function moveTo(
        ServiceRequest $serviceRequest,
        string $target,
        array $attributes = [],
        ?string $notifyEvent = null,
        bool $forceNotify = false
    ): void {
        $serviceRequest->refresh();

        if ($serviceRequest->status === $target) {
            // Status sama: `advance()` sendiri yang menyimpan atribut dan
            // mengirim notifikasi bila diminta, jadi tidak perlu jalur kedua
            // yang menyalin logika itu.
            $this->service->advance($serviceRequest, $target, $attributes, $notifyEvent, $forceNotify);
            return;
        }

        // Jalur aman untuk status yang harus lewat review dulu.
        if (!in_array($target, ServiceRequest::TRANSITIONS[$serviceRequest->status] ?? [], true)) {
            $this->service->advance($serviceRequest, ServiceRequest::STATUS_UNDER_REVIEW, []);
        }

        $this->service->advance($serviceRequest, $target, $attributes, $notifyEvent, $forceNotify);
    }

    private function findScoped(int $id): ServiceRequest
    {
        $clinicId = currentClinicId();

        abort_unless($clinicId, 403, 'Pilih klinik terlebih dahulu.');

        return ServiceRequest::where('clinic_id', $clinicId)->findOrFail($id);
    }
}
