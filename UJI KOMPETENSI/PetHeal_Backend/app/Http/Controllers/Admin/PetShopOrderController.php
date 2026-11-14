<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesPetShopScope;
use App\Http\Controllers\Controller;
use App\Models\PaymentRefund;
use App\Models\ProductOrder;
use App\Services\PaymentService;
use App\Services\ProductOrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PetShopOrderController extends Controller
{
    use ResolvesPetShopScope;

    public function __construct(
        private readonly ProductOrderService $orders,
        private readonly PaymentService $payments
    ) {}

    public function index(Request $request)
    {
        $clinicId = $this->scopeClinicId();

        $orders = ProductOrder::with(['items', 'user:id,name'])
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->input('payment_status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim((string) $request->input('search'));
                $q->where(function ($inner) use ($search) {
                    $inner->where('order_number', 'like', '%' . $search . '%')
                        ->orWhere('customer_name', 'like', '%' . $search . '%')
                        ->orWhere('customer_phone', 'like', '%' . $search . '%');
                });
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.pet-shop.orders.index', [
            'orders' => $orders,
            'statuses' => ProductOrder::TRANSITIONS,
            'statusCounts' => ProductOrder::query()
                ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function show(ProductOrder $order)
    {
        $this->authorizeOrder($order);

        $order->load([
            'items',
            'statusHistories',
            'user:id,name,email',
            'clinic:id,name,address,phone',
            'payments.refunds',
        ]);

        return view('admin.pet-shop.orders.show', compact('order'));
    }

    public function transition(Request $request, ProductOrder $order)
    {
        $this->authorizeOrder($order);

        $validated = $request->validate([
            'status' => 'required|in:accepted,preparing,ready_for_pickup,completed,rejected',
            'note' => 'nullable|string|max:200',
            'reason' => 'nullable|string|max:200|required_if:status,rejected',
        ]);

        try {
            $updated = $this->orders->transition(
                $order,
                $validated['status'],
                'clinic_admin',
                $request->user(),
                $validated['note'] ?? null,
                $validated['reason'] ?? null
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        // Pembayaran tunai dicatat saat pesanan diselesaikan.
        if ($updated->status === ProductOrder::STATUS_COMPLETED && $updated->isPayAtClinic()) {
            $this->payments->recordCashPayment($updated, $request->user());
        }

        // Penolakan pesanan yang sudah dibayar online memulai refund yang sah.
        if ($updated->status === ProductOrder::STATUS_REJECTED
            && $updated->payment_status === ProductOrder::PAYMENT_REFUND_PENDING) {
            $payment = $updated->latestPayment();

            if ($payment) {
                $result = $this->payments->requestRefund(
                    $payment,
                    (int) $payment->gross_amount - (int) $payment->refunded_amount,
                    'Pesanan ditolak klinik: ' . ($validated['reason'] ?? '-'),
                    $request->user()
                );

                return back()->with(
                    $result['success'] ? 'success' : 'error',
                    $result['success']
                        ? __('petshop.order_rejected_refund_started')
                        : ($result['message'] ?? __('petshop.order_rejected_refund_manual'))
                );
            }
        }

        return back()->with('success', __('petshop.order_status_updated'));
    }

    public function confirmRefund(Request $request, PaymentRefund $refund)
    {
        $refund->loadMissing('payment.payable');
        $payment = $refund->payment;

        abort_unless($payment !== null, 404);

        $clinicId = $this->scopeClinicId();
        if ($clinicId === null) {
            abort_unless(isSuperAdmin(), 404);
        } else {
            abort_unless((int) $payment->clinic_id === (int) $clinicId, 404);
        }

        $validated = $request->validate([
            'result' => 'required|in:succeeded,failed',
            'note' => 'nullable|string|max:255',
        ]);

        $this->payments->confirmRefund(
            $refund,
            $validated['result'] === 'succeeded',
            $validated['note'] ?? null,
            $request->user()
        );

        return back()->with('success', __('petshop.refund_updated'));
    }
}
