@extends('layouts.admin')

@section('title', __('petshop.dashboard_title') . ' — ' . ($isSuperAdmin && !currentClinicId() ? __('menu.super_admin') : __('menu.pet_shop')) . ' Admin')
@section('header', __('petshop.dashboard_title'))

@section('content')
@php
    $clinicId = currentClinicId();
    $maxTotal = max(1, $chart->max('total'));
    $statusTile = [
        'pending_payment' => ['tone' => 'amber', 'icon' => 'schedule'],
        'awaiting_confirmation' => ['tone' => 'orange', 'icon' => 'pending_actions'],
        'accepted' => ['tone' => 'blue', 'icon' => 'task_alt'],
        'preparing' => ['tone' => 'violet', 'icon' => 'inventory_2'],
        'ready_for_pickup' => ['tone' => 'sky', 'icon' => 'local_mall'],
        'completed' => ['tone' => 'primary', 'icon' => 'check_circle'],
        'cancelled' => ['tone' => 'slate', 'icon' => 'cancel'],
        'rejected' => ['tone' => 'rose', 'icon' => 'block'],
        'expired' => ['tone' => 'slate', 'icon' => 'timer_off'],
    ];
    $openOrders = (int) $statusCounts->only(['pending_payment','awaiting_confirmation','accepted','preparing','ready_for_pickup'])->sum();
@endphp

<div class="space-y-6">
    <x-admin.page-header
        :title="__('petshop.dashboard_title')"
        :eyebrow="__('menu.pet_shop')"
        icon="shopping_bag"
        :description="__('petshop.dashboard_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.pet-shop.products.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">inventory_2</span>
                {{ __('petshop.nav_products') }}
            </a>
            <a href="{{ route('admin.pet-shop.orders.index') }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">receipt_long</span>
                {{ __('petshop.nav_orders') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <div id="flash-msg" class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($isSuperAdmin && !$clinicId)
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3.5 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">info</span>
            <span>{{ __('petshop.all_clinics_note') }}</span>
        </div>
    @endif

    {{-- Statistik utama --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
        <x-admin.stat-card :label="__('petshop.stat_products')" :value="$totalProducts" icon="inventory_2" tone="primary" />
        <x-admin.stat-card :label="__('petshop.stat_active_products')" :value="$activeProducts" icon="check_circle" tone="blue" />
        <x-admin.stat-card :label="__('petshop.stat_variants')" :value="$totalVariants" icon="style" tone="violet" />
        <x-admin.stat-card :label="__('petshop.stat_stock')" :value="number_format($totalStock, 0, ',', '.')" icon="warehouse" tone="sky" />
        <x-admin.stat-card :label="__('petshop.stat_sales')" :value="'Rp ' . number_format($salesTotal, 0, ',', '.')" icon="payments" tone="primary" />
        <x-admin.stat-card :label="__('petshop.stat_transactions')" :value="$successfulTransactions" icon="verified" tone="amber" />
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        {{-- Grafik penjualan --}}
        <x-admin.panel :title="__('petshop.sales_chart_7d')" class="xl:col-span-2">
            <div class="flex h-56 items-end gap-2 sm:gap-4" role="img" aria-label="{{ __('petshop.sales_chart_7d') }}">
                @foreach($chart as $day)
                    @php($height = $day['total'] > 0 ? max(6, (int) round($day['total'] / $maxTotal * 100)) : 2)
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <span class="text-[10px] font-semibold tabular-nums text-slate-400">
                            {{ $day['total'] > 0 ? 'Rp ' . number_format($day['total'], 0, ',', '.') : '—' }}
                        </span>
                        <div class="flex w-full flex-1 items-end">
                            <div class="w-full rounded-t-lg {{ $day['total'] > 0 ? 'bg-emerald-500/80' : 'bg-slate-200 dark:bg-slate-700' }}"
                                 style="height: {{ $height }}%"
                                 title="{{ $day['date'] }} — Rp {{ number_format($day['total'], 0, ',', '.') }}"></div>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
            @if($chart->sum('total') === 0)
                <p class="mt-4 text-center text-xs text-slate-400">{{ __('petshop.sales_chart_empty') }}</p>
            @endif
        </x-admin.panel>

        {{-- Pesanan per status --}}
        <x-admin.panel :title="__('petshop.orders_by_status')" :subtitle="$openOrders . ' ' . __('petshop.open_orders')">
            <div class="space-y-2">
                @foreach($statusTile as $status => $meta)
                    <a href="{{ route('admin.pet-shop.orders.index', ['status' => $status]) }}"
                       class="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2.5 text-sm transition-colors hover:border-slate-200 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <span class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                            <span class="material-symbols-outlined text-[17px] text-slate-400" aria-hidden="true">{{ $meta['icon'] }}</span>
                            {{ __('petshop.status.' . $status) }}
                        </span>
                        <x-admin.badge :label="(string) ($statusCounts[$status] ?? 0)" :tone="$meta['tone']" />
                    </a>
                @endforeach
            </div>
        </x-admin.panel>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <x-admin.panel :title="__('petshop.top_products')" :subtitle="__('petshop.sales_desc')">
            @forelse($topProducts as $index => $product)
                <div class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-0 dark:border-slate-800">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                        {{ $index + 1 }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $product->product_name }}</p>
                        <p class="truncate text-[11px] text-slate-400">{{ $product->variant_name }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">{{ (int) $product->sold }} {{ __('petshop.sold') }}</p>
                        <p class="text-[11px] tabular-nums text-slate-400">Rp {{ number_format((int) $product->revenue, 0, ',', '.') }}</p>
                    </div>
                </div>
            @empty
                <x-admin.empty-state icon="shopping_bag" :title="__('petshop.top_products_empty')" />
            @endforelse
        </x-admin.panel>

        <x-admin.panel :title="__('petshop.low_stock')" :subtitle="__('petshop.dashboard_desc')">
            @forelse($lowStock as $variant)
                <div class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-0 dark:border-slate-800">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                        <span class="material-symbols-outlined text-[17px]" aria-hidden="true">warning</span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $variant->product?->name ?? '—' }}</p>
                        <p class="truncate text-[11px] text-slate-400">{{ $variant->name }} · {{ $variant->sku }}</p>
                    </div>
                    <x-admin.badge :label="$variant->availableStock() . ' ' . __('petshop.available_stock')" tone="amber" />
                </div>
            @empty
                <x-admin.empty-state icon="check_circle" :title="__('petshop.low_stock_empty')" />
            @endforelse
        </x-admin.panel>
    </div>
</div>
@endsection
