<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use App\Models\ProductVariant;
use App\Services\PaymentService;
use App\Services\ProductOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Pesanan Pet Shop milik pengguna yang sedang login.
 *
 * Backend tidak pernah menerima harga/total dari client; semua dihitung dari
 * database di ProductOrderService.
 */
class PetShopOrderController extends Controller
{
    public function __construct(
        private readonly ProductOrderService $orders,
        private readonly PaymentService $payments
    ) {}

    public function index(Request $request)
    {
        $orders = ProductOrder::with(['clinic:id,name,slug,address,phone,logo_path', 'items'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => collect($orders->items())->map(fn (ProductOrder $order) => $this->orderPayload($order))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $maxQty = (int) config('petshop.max_quantity_per_item', 20);

        $validated = $request->validate([
            'items' => 'required|array|min:1|max:' . (int) config('petshop.max_items_per_order', 20),
            'items.*.variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1|max:' . $maxQty,
            'payment_method' => 'required|in:online,pay_at_clinic',
            'notes' => 'nullable|string|max:500',
            'customer_phone' => 'nullable|string|max:32|regex:/^[0-9+\-\s()]+$/',
        ]);

        $user = $request->user();

        // Validasi ketersediaan pembayaran online SEBELUM membuat pesanan
        // agar tidak ada pesanan yang dibuat lalu langsung dibatalkan.
        // Klinik tanpa Midtrans sendiri tetap bisa bayar online via akun pusat
        // (central fallback sandbox selalu aktif bila MIDTRANS_SERVER_KEY terisi).
        if ($validated['payment_method'] === ProductOrder::METHOD_ONLINE) {
            // Pre-check ketersediaan online payment sebelum membuat pesanan.
            // Satu query dengan eager load — tidak pakai lockForUpdate di sini.
            $firstVariantId = (int) ($validated['items'][0]['variant_id'] ?? 0);
            $preClinic = \App\Models\ProductVariant::with('product.clinic')
                ->where('id', $firstVariantId)
                ->first()
                ?->product
                ?->clinic;

            if ($preClinic) {
                $availability = $this->payments->onlineAvailability($preClinic);
                if (!$availability['available']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pembayaran online belum tersedia untuk klinik ini. Silakan pilih bayar di klinik.',
                        'code' => 'online_payment_unavailable',
                        'reason' => $availability['reason'],
                    ], 422);
                }
            }
        }

        try {
            $order = $this->orders->placeOrder(
                $user,
                $validated['items'],
                $validated['payment_method'],
                $validated['notes'] ?? null,
                $validated['customer_phone'] ?? null
            );
        } catch (InsufficientStockException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'insufficient_stock',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat.',
            'data' => $this->orderPayload($order->fresh(['items', 'clinic', 'statusHistories'])),
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        $order = ProductOrder::with(['clinic:id,name,slug,address,phone,email,logo_path', 'items', 'statusHistories'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->orderPayload($order, detailed: true)]);
    }

    public function cancel(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:200',
        ]);

        $order = ProductOrder::where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        $order = $this->orders->cancelByUser($order, $request->user(), $validated['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan dibatalkan.',
            'data' => $this->orderPayload($order->load(['items', 'clinic', 'statusHistories']), detailed: true),
        ]);
    }

    /**
     * Buat pembayaran online. Nominal SELALU dari order di database.
     */
    public function pay(Request $request, int $id)
    {
        $order = ProductOrder::with(['clinic', 'items'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        if ($order->payment_method === ProductOrder::METHOD_PAY_AT_CLINIC) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan ini dibayar langsung di klinik.',
                'code' => 'pay_at_clinic',
            ], 422);
        }

        try {
            $payment = $this->payments->createPetShopPayment($order);
        } catch (\App\Services\PaymentRoutingException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'online_payment_unavailable',
                'reason' => $e->reason,
            ], 422);
        } catch (\App\Services\PaymentGatewayException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Pet shop payment creation failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak dapat dibuat saat ini. Coba lagi nanti.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran dibuat.',
            'data' => [
                'payment_code' => $payment->payment_code,
                'provider_order_id' => $payment->provider_order_id,
                'status' => $payment->status,
                'gross_amount' => (int) $payment->gross_amount,
                'snap_token' => $payment->snap_token,
                'redirect_url' => $payment->redirect_url,
                'environment' => $payment->environment,
                'routing_source' => $payment->routing_source,
                'expires_at' => $payment->expires_at?->toIso8601String(),
            ],
        ]);
    }

    public function paymentStatus(Request $request, int $id)
    {
        $order = ProductOrder::with('payments')
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        $payment = $order->payments->sortByDesc('id')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'order_status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'payment' => $payment ? [
                    'payment_code' => $payment->payment_code,
                    'status' => $payment->status,
                    'gross_amount' => (int) $payment->gross_amount,
                    'refunded_amount' => (int) $payment->refunded_amount,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    private function orderPayload(ProductOrder $order, bool $detailed = false): array
    {
        $payload = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'subtotal' => (int) $order->subtotal,
            'discount_total' => (int) $order->discount_total,
            'total_amount' => (int) $order->total_amount,
            'currency' => $order->currency,
            'clinic' => $order->clinic ? [
                'id' => $order->clinic->id,
                'name' => $order->clinic->name,
                'slug' => $order->clinic->slug,
                'address' => $order->clinic->address,
                'phone' => $order->clinic->phone,
                'logo_url' => $order->clinic->logo_url,
            ] : null,
            'pickup' => [
                'clinic_name' => $order->pickup_clinic_name,
                'address' => $order->pickup_clinic_address,
                'phone' => $order->pickup_clinic_phone,
            ],
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'notes' => $order->notes,
            'reservation_expires_at' => $order->reservation_expires_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->clinic_product_id,
                'variant_id' => $item->product_variant_id,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'sku' => $item->sku,
                'image_url' => $item->image_path ? asset('storage/' . preg_replace('#^(storage|public)/#', '', $item->image_path)) : null,
                'unit_price' => (int) $item->unit_price,
                'discount_price' => $item->discount_price !== null ? (int) $item->discount_price : null,
                'quantity' => (int) $item->quantity,
                'subtotal' => (int) $item->subtotal,
            ])->values(),
            'can_cancel' => in_array($order->status, [
                ProductOrder::STATUS_PENDING_PAYMENT,
                ProductOrder::STATUS_AWAITING_CONFIRMATION,
            ], true) && !$order->isPaid(),
        ];

        if ($detailed) {
            $payload['timeline'] = $order->statusHistories->map(fn ($history) => [
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'note' => $history->note,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values();
        }

        return $payload;
    }
}
