<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Services\MidtransService;
use App\Services\PaymentStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MedicalRecordController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans
    ) {}

    private function serializeRecord(MedicalRecord $record, bool $includeFullRecord = true): array
    {
        $record->loadMissing(['booking', 'pet', 'doctor']);
        $canViewFullRecord = $record->can_view_full_record;

        return [
            'id' => $record->id,
            'booking_id' => $record->booking_id,
            'pet_id' => $record->pet_id,
            'doctor_id' => $record->doctor_id,
            'diagnosis' => $includeFullRecord && $canViewFullRecord ? $record->diagnosis : null,
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

            if (!$this->midtrans->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Midtrans configuration is incomplete. Please contact support.',
                ], 500);
            }

            // PHASE 3 (B17): millis instead of time() — same-second double
            // taps collided on unique extra_payment_order_id (500).
            $orderId = 'MEDREC-' . $record->id . '-' . (int) (microtime(true) * 1000);
            $user = $request->user();
            $petName = $record->pet?->name ?: 'Pet';
            $snapPayload = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int) round($remainingAmount),
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
                'item_details' => [
                    [
                        'id' => "MEDREC-{$record->id}",
                        'price' => (int) round($remainingAmount),
                        'quantity' => 1,
                        'name' => "Extra Medical Cost - {$petName}",
                    ],
                ],
                'callbacks' => [
                    'finish' => 'https://app.sandbox.midtrans.com/payment/finish',
                ],
                'enabled_payments' => [
                    'credit_card',
                    'gopay',
                    'shopeepay',
                    'bca_va',
                    'bni_va',
                    'bri_va',
                    'mandiri_va',
                    'permata_va',
                    'cimb_va',
                    'qris',
                ],
                'credit_card' => [
                    'secure' => true,
                ],
            ];

            $result = $this->midtrans->createSnapToken($snapPayload);

            if (!$result['success']) {
                Log::error('Midtrans Snap API error for medical extra payment', [
                    'medical_record_id' => $record->id,
                    'status' => $result['status'] ?? 'unknown',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create extra medical payment token',
                ], $result['status'] ?? 502);
            }

            $data = $result['data'];

            $record->update([
                'extra_payment_status' => 'pending',
                'extra_payment_order_id' => $orderId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Extra medical payment token created successfully',
                'data' => [
                    'token' => $data['token'],
                    'redirect_url' => $data['redirect_url'],
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'order_id' => $orderId,
                    'medical_record' => $this->serializeRecord($record->fresh()),
                ],
            ]);
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
