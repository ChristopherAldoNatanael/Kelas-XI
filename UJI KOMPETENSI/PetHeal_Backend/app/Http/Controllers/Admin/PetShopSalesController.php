<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesPetShopScope;
use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Riwayat penjualan & laporan Pet Shop.
 *
 * Laporan hanya berisi pesanan klinik yang sedang dikelola. Penjualan produk
 * tidak pernah dicampur dengan pendapatan konsultasi.
 */
class PetShopSalesController extends Controller
{
    use ResolvesPetShopScope;

    public function index(Request $request)
    {
        $query = $this->salesQuery($request);
        $orders = $query->paginate(20)->withQueryString();

        $summaryQuery = $this->salesQuery($request)->reorder();

        $summary = [
            'orders' => (clone $summaryQuery)->count(),
            'revenue' => (int) (clone $summaryQuery)->sum('total_amount'),
            'items' => (int) ProductOrderItem::whereIn('product_order_id', (clone $summaryQuery)->select('id'))->sum('quantity'),
        ];

        return view('admin.pet-shop.sales.index', [
            'orders' => $orders,
            'summary' => $summary,
            'paymentMethods' => ProductOrder::query()
                ->when($this->scopeClinicId(), fn ($q, $clinicId) => $q->where('clinic_id', $clinicId))
                ->whereNotNull('payment_method')
                ->distinct()
                ->pluck('payment_method'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $orders = $this->salesQuery($request)->with('items')->get();
        $filename = 'pet-shop-sales-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Nomor Pesanan',
                'Tanggal',
                'Klinik',
                'Pelanggan',
                'Metode Pembayaran',
                'Status Pembayaran',
                'Produk',
                'Varian',
                'SKU',
                'Qty',
                'Harga Satuan',
                'Subtotal',
                'Total Pesanan',
            ]);

            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    fputcsv($handle, [
                        $order->order_number,
                        $order->completed_at?->format('Y-m-d H:i') ?? $order->created_at?->format('Y-m-d H:i'),
                        $order->pickup_clinic_name,
                        $order->customer_name,
                        $order->payment_method,
                        $order->payment_status,
                        $item->product_name,
                        $item->variant_name,
                        $item->sku,
                        $item->quantity,
                        $item->discount_price ?? $item->unit_price,
                        $item->subtotal,
                        $order->total_amount,
                    ]);
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function salesQuery(Request $request)
    {
        $clinicId = $this->scopeClinicId();

        return ProductOrder::query()
            ->with(['items', 'clinic:id,name'])
            ->where('status', ProductOrder::STATUS_COMPLETED)
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('completed_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('completed_at', '<=', $request->input('to')))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->input('payment_method')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->input('payment_status')))
            ->when($request->filled('search'), fn ($q) => $q->where('order_number', 'like', '%' . trim((string) $request->input('search')) . '%'))
            ->orderByDesc('completed_at');
    }
}
