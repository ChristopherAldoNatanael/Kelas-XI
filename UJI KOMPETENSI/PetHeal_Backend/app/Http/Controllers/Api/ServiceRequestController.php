<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\ServiceRequest;
use App\Services\ServiceRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Permintaan layanan dari aplikasi pet owner.
 *
 * Hanya pemilik yang boleh melihat dan merespons permintaannya sendiri.
 * `clinic_id` selalu divalidasi ke klinik aktif; `doctor_id` (bila dikirim)
 * wajib dokter klinik tersebut.
 */
class ServiceRequestController extends Controller
{
    public function __construct(private readonly ServiceRequestService $service)
    {
    }

    public function index(Request $request)
    {
        $requests = $request->user()->serviceRequests()
            ->with(['clinic', 'pet', 'doctor', 'offerDoctor', 'offerService', 'booking'])
            ->orderByDesc('created_at')
            ->paginate(20);

        foreach ($requests->items() as $serviceRequest) {
            $this->service->expireIfNeeded($serviceRequest);
        }

        return response()->json([
            'success' => true,
            'data' => collect($requests->items())
                ->map(fn (ServiceRequest $item) => $this->serialize($item))
                ->all(),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $clinicRule = Rule::exists('clinics', 'id')->where('is_active', true);
        $request->validate([
            'clinic_id' => ['required', $clinicRule],
            'pet_id' => ['required', Rule::exists('pets', 'id')->where('user_id', $user->id)],
            'need_type' => ['required', Rule::in(array_keys(ServiceRequest::NEED_TYPES))],
            'complaint' => 'required|string|min:10|max:2000',
            'preferred_date' => 'nullable|date|after_or_equal:today',
            'preferred_time' => 'nullable|date_format:H:i',
            'medical_record_id' => ['nullable', 'integer'],
            'doctor_id' => ['nullable', 'integer'],
        ], [
            'complaint.min' => 'Jelaskan kebutuhan atau keluhan minimal 10 karakter agar klinik dapat menindaklanjuti.',
            'pet_id.exists' => 'Hewan tidak ditemukan pada akun Anda.',
        ]);

        $clinic = Clinic::where('is_active', true)->findOrFail($request->input('clinic_id'));
        $pet = $user->pets()->findOrFail($request->input('pet_id'));

        // Referensi rekam medis hanya boleh milik hewan ini.
        $medicalRecordId = $request->input('medical_record_id');
        if ($medicalRecordId) {
            $record = MedicalRecord::where('id', $medicalRecordId)
                ->where('pet_id', $pet->id)
                ->where('clinic_id', $clinic->id)
                ->first();

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rekam medis rujukan tidak ditemukan untuk hewan ini di klinik tersebut.',
                ], 422);
            }
        }

        // Dokter rujukan (opsional) harus dokter klinik yang dipilih.
        $doctorId = $request->input('doctor_id');
        if ($doctorId) {
            $doctor = Doctor::where('id', $doctorId)
                ->where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->first();

            if (!$doctor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokter tidak tersedia di klinik yang dipilih.',
                ], 422);
            }
        }

        $serviceRequest = $this->service->submit($user, $pet, [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctorId,
            'medical_record_id' => $medicalRecordId,
            'need_type' => $request->input('need_type'),
            'complaint' => $request->input('complaint'),
            'preferred_date' => $request->input('preferred_date'),
            'preferred_time' => $request->input('preferred_time'),
        ]);

        $serviceRequest->load(['clinic', 'pet', 'doctor', 'offerDoctor', 'offerService']);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan layanan terkirim. Klinik akan meninjaunya.',
            'data' => $this->serialize($serviceRequest),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $serviceRequest = $request->user()->serviceRequests()
            ->with(['clinic', 'pet', 'doctor', 'offerDoctor', 'offerService', 'booking'])
            ->find($id);

        if (!$serviceRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan layanan tidak ditemukan.',
            ], 404);
        }

        $this->service->expireIfNeeded($serviceRequest);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($serviceRequest, true),
        ]);
    }

    public function accept(Request $request, $id)
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $serviceRequest = $request->user()->serviceRequests()->find($id);

        if (!$serviceRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan layanan tidak ditemukan.',
            ], 404);
        }

        try {
            $booking = $this->service->accept($serviceRequest, $request->input('note'));
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $serviceRequest->refresh()->load(['clinic', 'pet', 'doctor', 'offerDoctor', 'offerService', 'booking']);

        return response()->json([
            'success' => true,
            'message' => 'Penawaran disetujui. Booking berhasil dibuat.',
            'data' => $this->serialize($serviceRequest, true),
            'booking' => $booking->load(['clinic', 'doctor', 'service', 'pet']),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $serviceRequest = $request->user()->serviceRequests()->find($id);

        if (!$serviceRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan layanan tidak ditemukan.',
            ], 404);
        }

        try {
            $this->service->reject($serviceRequest, $request->input('note'));
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $serviceRequest->refresh()->load(['clinic', 'pet', 'doctor', 'offerDoctor', 'offerService', 'booking']);

        return response()->json([
            'success' => true,
            'message' => 'Penawaran ditolak.',
            'data' => $this->serialize($serviceRequest, true),
        ]);
    }

    /**
     * Bentuk respons aplikasi. Nama dokter/admin tidak membocorkan data lain;
     * catatan medis hanya disertakan pada detail milik pemilik.
     *
     * @return array<string, mixed>
     */
    private function serialize(ServiceRequest $request, bool $detail = false): array
    {
        $offer = $request->offered_at ? [
            'service_id' => $request->offer_service_id,
            'service_name' => $request->offer_service_name
                ?? $request->offerService?->name,
            'description' => $request->offer_description
                ?? $request->offerService?->description,
            'price' => $request->offer_price !== null ? (float) $request->offer_price : null,
            'price_type' => $request->offer_price_type,
            'duration_minutes' => $request->offer_duration_minutes,
            'doctor_id' => $request->offer_doctor_id,
            'doctor_name' => $request->offerDoctor?->name,
            'schedule_date' => $request->offer_schedule_date,
            'schedule_time' => $request->offer_schedule_time,
            'notes' => $request->offer_notes,
            'valid_until' => $request->offer_valid_until?->toIso8601String(),
            'is_expired' => $request->isOfferExpired(),
        ] : null;

        $payload = [
            'id' => $request->id,
            'code' => $request->code,
            'status' => $request->status,
            'status_label' => ServiceRequest::statusLabel($request->status),
            'need_type' => $request->need_type,
            'need_type_label' => $request->need_type_label,
            'complaint' => $request->complaint,
            'preferred_date' => $request->preferred_date,
            'preferred_time' => $request->preferred_time,
            'created_at' => $request->created_at?->toIso8601String(),
            'clinic' => $request->clinic ? [
                'id' => $request->clinic->id,
                'name' => $request->clinic->name,
                'slug' => $request->clinic->slug,
            ] : null,
            'pet' => $request->pet ? [
                'id' => $request->pet->id,
                'name' => $request->pet->name,
                'species' => $request->pet->species,
            ] : null,
            'doctor' => $request->doctor ? [
                'id' => $request->doctor->id,
                'name' => $request->doctor->name,
            ] : null,
            'medical_record_id' => $request->medical_record_id,
            'booking_id' => $request->booking_id,
            'offer' => $offer,
            'can_respond' => $request->status === ServiceRequest::STATUS_OFFERED && !$request->isOfferExpired(),
        ];

        if ($detail) {
            $payload['doctor_recommendation'] = $request->doctor_recommendation;
            $payload['doctor_notes'] = $request->doctor_notes;
            $payload['admin_notes'] = $request->admin_notes;
            $payload['needs_follow_up'] = (bool) $request->needs_follow_up;
            $payload['owner_response_note'] = $request->owner_response_note;
            $payload['accepted_at'] = $request->accepted_at?->toIso8601String();
            $payload['rejected_at'] = $request->rejected_at?->toIso8601String();
            $payload['booking'] = $request->booking ? [
                'id' => $request->booking->id,
                'status' => $request->booking->status,
                'payment_status' => $request->booking->payment_status,
                'booking_date' => $request->booking->booking_date,
                'booking_time' => $request->booking->booking_time,
                'total_amount' => (float) $request->booking->total_amount,
            ] : null;
        }

        return $payload;
    }
}
