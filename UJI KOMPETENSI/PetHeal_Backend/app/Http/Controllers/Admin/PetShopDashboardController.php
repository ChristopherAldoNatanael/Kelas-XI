<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClinicProduct;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class PetShopDashboardController extends Controller
{
    public function index()
    {
        $clinicId = currentClinicId();
        $scoped = fn ($query) => $query->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId));

        $productQuery = $scoped(ClinicProduct::query());
        $orderQuery = $scoped(ProductOrder::query());

        $totalProducts = (clone $productQuery)->count();
        $activeProducts = (clone $productQuery)->where('is_active', true)->count();

        $totalVariants = ProductVariant::query()
            ->when($clinicId, fn ($q) => $q->whereHas('product', fn ($p) => $p->where('clinic_id', $clinicId)))
            ->count();

        $totalStock = (int) ProductVariant::query()
            ->when($clinicId, fn ($q) => $q->whereHas('product', fn ($p) => $p->where('clinic_id', $clinicId)))
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(stock_on_hand - stock_reserved), 0) as total')
            ->value('total');

        $statusCounts = (clone $orderQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $salesTotal = (int) (clone $orderQuery)
            ->where('status', ProductOrder::STATUS_COMPLETED)
            ->sum('total_amount');

        $successfulTransactions = (clone $orderQuery)
            ->whereIn('payment_status', [ProductOrder::PAYMENT_PAID, ProductOrder::PAYMENT_PARTIALLY_REFUNDED])
            ->count();

        // Grafik 7 hari terakhir: hanya pesanan selesai (pendapatan nyata).
        $chartRaw = (clone $orderQuery)
            ->where('status', ProductOrder::STATUS_COMPLETED)
            ->where('completed_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(completed_at) as day, SUM(total_amount) as total, COUNT(*) as orders')
            ->groupBy('day')
            ->pluck('total', 'day');

        $chart = collect(range(6, 0))->map(function (int $daysAgo) use ($chartRaw) {
            $date = now()->subDays($daysAgo)->toDateString();

            return [
                'date' => $date,
                'label' => now()->subDays($daysAgo)->translatedFormat('D'),
                'total' => (int) ($chartRaw[$date] ?? 0),
            ];
        });

        $topProducts = ProductOrderItem::query()
            ->select('product_name', 'variant_name', DB::raw('SUM(quantity) as sold'), DB::raw('SUM(subtotal) as revenue'))
            ->whereHas('order', function ($query) use ($clinicId) {
                $query->where('status', ProductOrder::STATUS_COMPLETED)
                    ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId));
            })
            ->groupBy('product_name', 'variant_name')
            ->orderByDesc('sold')
            ->limit(5)
            ->get();

        $lowStock = ProductVariant::with('product')
            ->when($clinicId, fn ($q) => $q->whereHas('product', fn ($p) => $p->where('clinic_id', $clinicId)))
            ->where('is_active', true)
            ->whereColumn('stock_on_hand', '<=', 'low_stock_threshold')
            ->orderByRaw('(stock_on_hand - stock_reserved) asc')
            ->limit(5)
            ->get();

        return view('admin.pet-shop.dashboard', [
            'totalProducts' => $totalProducts,
            'activeProducts' => $activeProducts,
            'totalVariants' => $totalVariants,
            'totalStock' => $totalStock,
            'statusCounts' => $statusCounts,
            'salesTotal' => $salesTotal,
            'successfulTransactions' => $successfulTransactions,
            'chart' => $chart,
            'topProducts' => $topProducts,
            'lowStock' => $lowStock,
        ]);
    }
}
