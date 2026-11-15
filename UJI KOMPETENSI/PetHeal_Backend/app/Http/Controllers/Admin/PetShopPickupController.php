<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesPetShopScope;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ProductOrder;
use App\Models\ProductPickupHandover;
use App\Models\ProductPickupPass;
use App\Services\PaymentService;
use App\Services\PickupPassService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Verifikasi pengambilan produk (Click & Collect) di VCMS.
 *
 * Alur: pindai QR / masukkan kode manual / cari nomor pesanan → periksa
 * klinik, status pesanan, dan pembayaran → catat pembayaran bila perlu →
 * konfirmasi serah terima. Semua pemeriksaan diulang di backend.
 */
class PetShopPickupController extends Controller
{
    use ResolvesPetShopScope;

    public function __construct(
        private readonly PickupPassService $passes,
        private readonly PaymentService $payments
    ) {}

    public function index(Request $request)
    {
        $clinicId = $this->requireWritableClinic();

        $verified = null;
        $verificationError = null;
        $code = trim((string) $request->input('code', ''));

        // Cara verifikasi: 'qr' bila kode berasal dari pemindaian kamera,
        // 'manual' bila diketik petugas. Disimpan supaya jejak serah terima
        // di Histori truthfully membedakan keduanya.
        $via = $request->input('via') === 'qr' ? 'qr' : 'manual';

        if ($code !== '') {
            try {
                $verified = $this->passes->verify($code, $clinicId);
            } catch (ValidationException $e) {
                $verificationError = $e->validator->errors()->first('code');
            }
        }

        $readyOrders = ProductOrder::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('status', [ProductOrder::STATUS_PREPARING, ProductOrder::STATUS_READY_FOR_PICKUP])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($inner) use ($search) {
                    $inner->where('order_number', 'like', '%' . $search . '%')
                        ->orWhere('customer_name', 'like', '%' . $search . '%')
                        ->orWhere('customer_phone', 'like', '%' . $search . '%');
                });
            })
            ->with(['items', 'pickupPass'])
            ->orderBy('ready_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $handovers = ProductPickupHandover::query()
            ->where('clinic_id', $clinicId)
            ->with(['order:id,order_number,customer_name,total_amount,payment_method', 'staff:id,name'])
            ->orderByDesc('handed_over_at')
            ->limit(20)
            ->get();

        return view('admin.pet-shop.pickups.index', [
            'code' => $code,
            'via' => $via,
            'verified' => $verified,
            'verificationError' => $verificationError,
            'readyOrders' => $readyOrders,
            'handovers' => $handovers,
        ]);
    }

    /**
     * Konfirmasi serah terima. Wajib sudah lolos verifikasi kode (atau
     * verifikasi lewat nomor pesanan yang dipilih petugas).
     */
    public function confirm(Request $request, ProductOrder $order)
    {
        $clinicId = $this->requireWritableClinic();

        abort_unless((int) $order->clinic_id === (int) $clinicId, 404);

        $validated = $request->validate([
            'code' => 'nullable|string|max:120',
            'method' => 'required|in:qr,manual,order_number',
        ]);

        $code = trim((string) ($validated['code'] ?? ''));
        $method = $validated['method'];

        try {
            $pass = null;

            if ($code !== '') {
                $result = $this->passes->verify($code, $clinicId);

                // Kode harus milik pesanan yang sedang dikonfirmasi; jangan
                // pernah menyelesaikan pesanan lain yang kebetulan kodenya valid.
                if ((int) $result['order']->id !== (int) $order->id) {
                    throw ValidationException::withMessages([
                        'code' => 'Kode pengambilan ini milik pesanan lain.',
                    ]);
                }

                $order = $result['order'];
                $pass = $result['pass'];
                $method = $method === 'order_number' ? 'manual' : $method;
            } elseif ($method !== 'order_number') {
                throw ValidationException::withMessages([
                    'code' => 'Pindai QR atau masukkan kode pengambilan terlebih dahulu.',
                ]);
            }

            if ((int) $order->clinic_id !== (int) $clinicId) {
                abort(404);
            }

            $result = $this->passes->confirmHandover($order, $request->user(), $method, $pass);

            if ($result['already_completed']) {
                return redirect()
                    ->route('admin.pet-shop.pickups.index')
                    ->with('info', __('petshop.pickup_already_completed'));
            }
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.pet-shop.pickups.index')
                ->withErrors($e->errors())
                ->withInput();
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.pet-shop.pickups.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.pet-shop.pickups.index')
            ->with('success', __('petshop.pickup_completed', ['order' => $order->order_number]));
    }

    /**
     * Catat pembayaran langsung di klinik (offline).
     */
    public function recordPayment(Request $request, ProductOrder $order)
    {
        $clinicId = $this->requireWritableClinic();

        abort_unless((int) $order->clinic_id === (int) $clinicId, 404);

        $validated = $request->validate([
            'amount_received' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,debit_card,credit_card,bank_transfer,qris',
            'payment_reference' => 'nullable|string|max:100',
            'paid_at' => 'nullable|date',
        ]);

        try {
            $this->payments->recordOfflinePayment(
                $order,
                (int) $validated['amount_received'],
                $validated['payment_method'],
                $validated['payment_reference'] ?? null,
                $request->user(),
                $validated['paid_at'] ?? null
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('petshop.offline_payment_recorded'));
    }

    /**
     * Riwayat pengambilan lengkap.
     */
    public function history()
    {
        $clinicId = $this->requireWritableClinic();

        $handovers = ProductPickupHandover::query()
            ->where('clinic_id', $clinicId)
            ->with([
                'order:id,order_number,customer_name,customer_phone,total_amount,payment_method,payment_status',
                'staff:id,name',
            ])
            ->orderByDesc('handed_over_at')
            ->paginate(20);

        return view('admin.pet-shop.pickups.history', compact('handovers'));
    }
}
