@extends('layouts.admin')

@section('title', __('petshop.orders_title') . ' — ' . (currentClinic()?->name ?? 'PetHeal') . ' Admin')
@section('header', __('petshop.orders_title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('petshop.orders_title')"
        :eyebrow="__('menu.pet_shop')"
        icon="receipt_long"
        :description="__('petshop.orders_desc')" />

    @if(session('success'))
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm font-medium text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <x-admin.panel flush>
        <form method="GET" class="grid gap-2 border-b border-slate-100 px-5 py-4 sm:grid-cols-2 lg:grid-cols-6 dark:border-slate-800">
            <div class="relative lg:col-span-2">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[18px] text-slate-400" aria-hidden="true">search</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('petshop.search_order') }}" class="admin-input pl-10">
            </div>
            <select name="status" class="admin-input">
                <option value="">{{ __('petshop.all_statuses') }}</option>
                @foreach(\App\Models\ProductOrder::TRANSITIONS as $status => $next)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('petshop.status.' . $status) }}</option>
                @endforeach
                @foreach(\App\Models\ProductOrder::FINAL_STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('petshop.status.' . $status) }}</option>
                @endforeach
            </select>
            <select name="payment_status" class="admin-input">
                <option value="">{{ __('petshop.payment_status_label') }}</option>
                @foreach(['unpaid','pending','paid','failed','expired','cancelled','refund_pending','partially_refunded','refunded','chargeback'] as $status)
                    <option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ __('petshop.payment_status.' . $status) }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="admin-input" aria-label="{{ __('petshop.date_from') }}">
            <input type="date" name="to" value="{{ request('to') }}" class="admin-input" aria-label="{{ __('petshop.date_to') }}">
            <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-6">
                <button type="submit" class="admin-btn-secondary">{{ __('petshop.search') }}</button>
                @if(request()->hasAny(['search','status','payment_status','from','to']))
                    <a href="{{ route('admin.pet-shop.orders.index') }}" class="admin-btn-ghost">{{ __('petshop.reset') }}</a>
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
                        <th class="px-5 py-3">{{ __('petshop.total') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.payment_method_label') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.payment_status_label') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.order_status') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.created_at') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($orders as $order)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.pet-shop.orders.show', $order) }}" class="font-mono text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-400">
                                    {{ $order->order_number }}
                                </a>
                                <p class="mt-0.5 text-[11px] text-slate-400">{{ $order->pickup_clinic_name }}</p>
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-700 dark:text-slate-300">
                                {{ $order->customer_name }}
                                @if($order->customer_phone)
                                    <p class="text-[11px] text-slate-400">{{ $order->customer_phone }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">
                                {{ $order->items->sum('quantity') }} item
                            </td>
                            <td class="px-5 py-3 text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">
                                Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('petshop.payment_method.' . $order->payment_method) }}
                            </td>
                            <td class="px-5 py-3"><x-petshop.status-badge :status="$order->payment_status" type="payment" /></td>
                            <td class="px-5 py-3"><x-petshop.status-badge :status="$order->status" /></td>
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                                {{ $order->created_at?->format('d M Y H:i') }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.pet-shop.orders.show', $order) }}" class="admin-btn-ghost !p-2" title="{{ __('petshop.detail') }}">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-admin.empty-state
                                    icon="receipt_long"
                                    :title="request()->hasAny(['search','status','payment_status','from','to']) ? __('petshop.no_orders_filter') : __('petshop.no_orders')" />
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
