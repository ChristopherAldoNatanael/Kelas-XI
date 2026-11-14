@extends('layouts.admin')

@section('title', __('petshop.sales_title') . ' — ' . (currentClinic()?->name ?? 'PetHeal') . ' Admin')
@section('header', __('petshop.sales_title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('petshop.sales_title')"
        :eyebrow="__('menu.pet_shop')"
        icon="monitoring"
        :description="__('petshop.sales_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.pet-shop.sales.export', request()->query()) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">download</span>
                {{ __('petshop.export_csv') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-admin.stat-card :label="__('petshop.revenue')" :value="'Rp ' . number_format($summary['revenue'], 0, ',', '.')" icon="payments" tone="primary" />
        <x-admin.stat-card :label="__('petshop.nav_orders')" :value="$summary['orders']" icon="receipt_long" tone="blue" />
        <x-admin.stat-card :label="__('petshop.total_items_sold')" :value="number_format($summary['items'], 0, ',', '.')" icon="inventory_2" tone="violet" />
    </div>

    <x-admin.panel flush>
        <form method="GET" class="grid gap-2 border-b border-slate-100 px-5 py-4 sm:grid-cols-2 lg:grid-cols-6 dark:border-slate-800">
            <div class="relative lg:col-span-2">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[18px] text-slate-400" aria-hidden="true">search</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('petshop.order_number') }}" class="admin-input pl-10">
            </div>
            <input type="date" name="from" value="{{ request('from') }}" class="admin-input" aria-label="{{ __('petshop.date_from') }}">
            <input type="date" name="to" value="{{ request('to') }}" class="admin-input" aria-label="{{ __('petshop.date_to') }}">
            <select name="payment_method" class="admin-input">
                <option value="">{{ __('petshop.payment_method_label') }}</option>
                @foreach($paymentMethods as $method)
                    <option value="{{ $method }}" @selected(request('payment_method') === $method)>{{ __('petshop.payment_method.' . $method) }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-2">
                <button type="submit" class="admin-btn-secondary">{{ __('petshop.search') }}</button>
                @if(request()->hasAny(['search','from','to','payment_method','payment_status']))
                    <a href="{{ route('admin.pet-shop.sales.index') }}" class="admin-btn-ghost">{{ __('petshop.reset') }}</a>
                @endif
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('petshop.order_number') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.customer') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.items') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.payment_method_label') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.total') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.completed_at') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($orders as $order)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3 font-mono text-xs font-semibold text-slate-700 dark:text-slate-300">{{ $order->order_number }}</td>
                            <td class="px-5 py-3 text-sm text-slate-700 dark:text-slate-300">{{ $order->customer_name }}</td>
                            <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $order->items->sum('quantity') }} item</td>
                            <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">{{ __('petshop.payment_method.' . $order->payment_method) }}</td>
                            <td class="px-5 py-3 text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $order->completed_at?->format('d M Y H:i') }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.pet-shop.orders.show', $order) }}" class="admin-btn-ghost !p-2">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="monitoring" :title="__('petshop.no_sales')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="border-t border-slate-100 px-5 py-3.5 dark:border-slate-800">{{ $orders->links() }}</div>
        @endif
    </x-admin.panel>
</div>
@endsection
