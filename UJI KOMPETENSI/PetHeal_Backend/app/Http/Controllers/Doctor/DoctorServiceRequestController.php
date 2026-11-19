<?php

namespace App\Http\Controllers\Doctor;

use App\Models\ServiceRequest;
use App\Services\ServiceRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Evaluasi medis atas permintaan layanan.
 *
 * Dokter hanya melihat permintaan yang ditugaskan kepadanya di klinik yang
 * sama. Dokter TIDAK menentukan harga — itu kewenangan admin.
 */
class DoctorServiceRequestController extends DoctorPortalController
{
    public function __construct(private readonly ServiceRequestService $service)
    {
    }

    public function index(Request $request)
    {
        $doctor = $this->doctor();

        $validated = $request->validate([
            'status' => ['nullable', Rule::in([
                ServiceRequest::STATUS_WAITING_DOCTOR,
                ServiceRequest::STATUS_WAITING_OFFER,
                ServiceRequest::STATUS_OFFERED,
                ServiceRequest::STATUS_CONVERTED,
            ])],
        ]);

        $status = $validated['status'] ?? null;

        $requests = ServiceRequest::with(['pet', 'user', 'clinic'])
            ->where('clinic_id', $doctor->clinic_id)
            ->where('doctor_id', $doctor->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'waiting_doctor_review', 'waiting_offer', 'offered', 'converted_to_booking')")
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $pendingCount = ServiceRequest::where('clinic_id', $doctor->clinic_id)
            ->where('doctor_id', $doctor->id)
            ->where('status', ServiceRequest::STATUS_WAITING_DOCTOR)
            ->count();

        return view('doctor.service-requests.index', compact('doctor', 'requests', 'status', 'pendingCount'));
    }

    public function show($id)
    {
        $doctor = $this->doctor();

        $request = ServiceRequest::with([
            'pet', 'user', 'clinic', 'medicalRecord', 'offerService', 'offerDoctor', 'booking',
        ])
            ->where('clinic_id', $doctor->clinic_id)
            ->where('doctor_id', $doctor->id)
            ->findOrFail($id);

        // Riwayat pemeriksaan hewan ini, agar evaluasi tidak menebak.
        $history = \App\Models\MedicalRecord::with('doctor')
            ->where('pet_id', $request->pet_id)
            ->where('clinic_id', $doctor->clinic_id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('doctor.service-requests.show', compact('doctor', 'request', 'history'));
    }

    public function recommend(Request $request, $id)
    {
        $doctor = $this->doctor();

        $serviceRequest = ServiceRequest::where('clinic_id', $doctor->clinic_id)
            ->where('doctor_id', $doctor->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'doctor_recommendation' => 'required|string|min:10|max:2000',
            'doctor_notes' => 'nullable|string|max:2000',
            'needs_follow_up' => 'nullable|boolean',
        ], [
            'doctor_recommendation.required' => 'Rekomendasi layanan wajib diisi agar admin dapat menyusun penawaran.',
            'doctor_recommendation.min' => 'Rekomendasi terlalu singkat — jelaskan kebutuhan pemeriksaan lanjutan.',
        ]);

        try {
            $this->service->advance(
                $serviceRequest,
                ServiceRequest::STATUS_WAITING_OFFER,
                [
                    'doctor_recommendation' => $validated['doctor_recommendation'],
                    'doctor_notes' => $validated['doctor_notes'] ?? null,
                    'needs_follow_up' => (bool) ($validated['needs_follow_up'] ?? false),
                ],
                'doctor_evaluated'
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        \App\Models\AuditLog::log(
            'service_request.doctor_review',
            "Dokter menyelesaikan evaluasi permintaan {$serviceRequest->code}.",
            $serviceRequest
        );

        return back()->with('success', 'Evaluasi medis tersimpan. Admin akan menyusun penawaran untuk pemilik.');
    }
}
