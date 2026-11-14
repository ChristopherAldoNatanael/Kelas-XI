<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Services\PaymentGatewayException;
use App\Services\PaymentRoutingException;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MedicalRecordController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments
    ) {}

    private function serializeRecord(MedicalRecord $record, bool $includeFullRecord = true): array
    {
        // `attachments` ikut dimuat supaya lampiran tidak memicu N+1 query.
        // `booking.clinic` ikut dimuat: satu pasien bisa berobat di beberapa
        // klinik, jadi identitas klinik harus datang dari rekam medis/booking
        // yang bersangkutan — bukan dari klinik yang terakhir dipakai.
        $record->loadMissing(['booking.clinic', 'pet', 'doctor', 'attachments']);
        $canViewFullRecord = $record->can_view_full_record;
        $clinic = $record->booking?->clinic;

        return [
            'id' => $record->id,
            'booking_id' => $record->booking_id,
            'pet_id' => $record->pet_id,
            'doctor_id' => $record->doctor_id,
            'record_id' => $record->id,
            // Klinik tempat rekam medis ini dibuat. Null bila booking lama
            // tidak punya relasi klinik — UI wajib punya tampilan cadangan.
            'clinic' => $clinic ? [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'slug' => $clinic->slug,
                'address' => $clinic->address,
                'phone' => $clinic->phone,
                'logo_url' => $clinic->logo_url ?? null,
                'primary_color' => $clinic->primary_color ?? null,
            ] : null,
            'diagnosis' => $includeFullRecord && $canViewFullRecord ? $record->diagnosis : null,
            // SOAP — di-gate dengan aturan yang sama seperti diagnosis:
            // rekam medis terkunci tidak boleh membocorkan isi pemeriksaan.
            'subjective' => $includeFullRecord && $canViewFullRecord ? $record->subjective : null,
            'objective' => $includeFullRecord && $canViewFullRecord ? $record->objective : null,
            'assessment' => $includeFullRecord && $canViewFullRecord ? $record->assessment : null,
            'plan' => $includeFullRecord && $canViewFullRecord ? $record->plan : null,
            // Lampiran tanpa URL valid (berkas hilang) dibuang, bukan dikirim
            // sebagai entri kosong yang membuat UI menampilkan gambar rusak.
            'attachments' => ($includeFullRecord && $canViewFullRecord)
                ? $record->attachments
                    ->map(fn ($attachment) => [
                        'id' => $attachment->id,
                        'url' => $attachment->url,
                        'caption' => $attachment->caption,
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'is_image' => $attachment->isImage(),
                    ])
                    ->filter(fn ($item) => !empty($item['url']))
                    ->values()
                    ->all()
                : [],
            'treatment' => $includeFullRecord && $canViewFullRecord ? $record->treatment : null,
            'medicine' => $includeFullRecord && $canViewFullRecord ? $record->medicine : null,
            'notes' => $includeFullRecord && $canViewFullRecord ? $record->notes : null,
            'next_visit_date' => $includeFullRecord && $canViewFullRecord ? $record->next_visit_date : null,
            'cost' => (float) $record->cost,
            'treatment_cost' => (float) $record->treatment_cost,
            'medicine_cost' => (float) $record->medicine_cost,
            'total_medical_cost' => (float) $record->total_medical_cost,
            'extra_payment_amount' => (float) $record->extra_payment_amount,
            'extra_payment_paid_amount' => (float) $record->extra_payment_paid_amount,
            'extra_payment_status' => $record->extra_payment_status,
            'extra_payment_order_id' => $record->extra_payment_order_id,
            'extra_payment_date' => $record->extra_payment_date?->toDateTimeString(),
            'can_view_full_record' => $canViewFullRecord,
            'booking' => $record->booking,
            'pet' => $record->pet,
            'doctor' => $record->doctor,
            'created_at' => $record->created_at?->toDateTimeString(),
        ];
    }
    /**
     * Get all medical records for authenticated user's pets
     */
    public function index(Request $request)
    {
        try {
            $petIds = $request->user()->pets()->pluck('id');

            // ✅ FIXED: Booking already has $with = ['pet', 'doctor'], so just load booking
            $records = MedicalRecord::whereIn('pet_id', $petIds)
                ->with(['booking', 'pet', 'doctor'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => collect($records->items())->map(fn ($record) => $this->serializeRecord($record))->values(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MedicalRecord index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load medical records.',
            ], 500);
        }
    }

    /**
     * Get medical records for a specific pet
     */
    public function getByPet(Request $request, $petId)
    {
        try {
            $pet = $request->user()->pets()->find($petId);
            if (!$pet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pet not found',
                ], 404);
            }

            // ✅ FIXED: Booking already has $with = ['pet', 'doctor']
            $records = MedicalRecord::where('pet_id', $petId)
                ->with(['booking', 'pet', 'doctor'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => collect($records->items())->map(fn ($record) => $this->serializeRecord($record))->values(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MedicalRecord getByPet error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load records for this pet.',
            ], 500);
        }
    }

    /**
     * Get single medical record details
     */
    public function show(Request $request, $id)
    {
        try {
            $petIds = $request->user()->pets()->pluck('id');

            // ✅ FIXED: Don't load nested relationships manually since Booking already has $with
            $record = MedicalRecord::whereIn('pet_id', $petIds)
                ->with(['booking', 'pet', 'doctor'])
                ->find($id);

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Medical record not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->serializeRecord($record),
            ]);
        } catch (\Exception $e) {
            Log::error('MedicalRecord show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load medical record details.',
            ], 500);
        }
    }

    public function getByBooking(Request $request, int $id)
    {
        try {
            $booking = $request->user()->bookings()->find($id);
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found',
                ], 404);
            }

            $record = MedicalRecord::where('booking_id', $booking->id)
                ->with(['booking', 'pet', 'doctor'])
                ->first();

            if (!$record) {
                return response()->json([
                    'success' => true,
                    'message' => 'Medical record is not available yet',
                    'data' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $this->serializeRecord($record),
            ]);
        } catch (\Throwable $e) {
            Log::error('MedicalRecord getByBooking error', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load booking medical record.',
            ], 500);
        }
    }

    public function createExtraPayment(Request $request, int $id)
    {
        try {
            $record = MedicalRecord::with(['booking.user', 'pet'])
                ->whereHas('booking', fn ($query) => $query->where('user_id', $request->user()->id))
                ->find($id);

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Medical record not found',
                ], 404);
            }

            if ($record->extra_payment_status === 'paid' || $record->extra_payment_status === 'not_required') {
                return response()->json([
                    'success' => false,
                    'message' => 'No extra medical payment is required',
                ], 400);
            }

            $remainingAmount = max(0, (float) $record->extra_payment_amount - (float) $record->extra_payment_paid_amount);
            if ($remainingAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No remaining extra medical cost to pay',
                ], 400);
            }

            // Akun merchant, nominal, dan order id diputuskan server. Endpoint
            // ini tidak lagi memakai Server Key pusat secara langsung.
            $payment = $this->payments->createMedicalRecordPayment($record);
            $payment->loadMissing('credentialVersion');

            Log::info('Medical record payment created with routed account', [
                'medical_record_id' => $record->id,
                'payment_id' => $payment->id,
                'routing_source' => $payment->routing_source,
                'environment' => $payment->environment,
                'amount' => $payment->gross_amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Extra medical payment token created successfully',
                'data' => [
                    'token' => $payment->snap_token,
                    'redirect_url' => $payment->redirect_url,
                    'transaction_id' => $payment->provider_transaction_id,
                    'order_id' => $payment->provider_order_id,
                    'payment_id' => $payment->id,
                    'gross_amount' => $payment->gross_amount,
                    'client_key' => $payment->credentialVersion?->client_key
                        ?: config("services.midtrans.central.{$payment->environment}.client_key"),
                    'environment' => $payment->environment,
                    'routing_source' => $payment->routing_source,
                    'medical_record' => $this->serializeRecord($record->fresh()),
                ],
            ]);
        } catch (PaymentRoutingException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'online_payment_unavailable',
                'reason' => $e->reason,
            ], $e->httpStatus);
        } catch (PaymentGatewayException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create extra medical payment token: ' . $e->getMessage(),
            ], 502);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('MedicalRecord createExtraPayment error', [
                'medical_record_id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to prepare extra medical payment.',
            ], 500);
        }
    }

    public function paymentStatus(Request $request, int $id)
    {
        $record = MedicalRecord::whereHas('booking', fn ($query) => $query->where('user_id', $request->user()->id))
            ->find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Medical record not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $record->id,
                'extra_payment_status' => $record->extra_payment_status,
                'extra_payment_amount' => (float) $record->extra_payment_amount,
                'extra_payment_paid_amount' => (float) $record->extra_payment_paid_amount,
                'extra_payment_order_id' => $record->extra_payment_order_id,
                'extra_payment_date' => $record->extra_payment_date?->toDateTimeString(),
                'can_view_full_record' => $record->can_view_full_record,
            ],
        ]);
    }
}
