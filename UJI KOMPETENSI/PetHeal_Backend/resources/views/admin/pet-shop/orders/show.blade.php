@extends('layouts.admin')

@section('title', $order->order_number . ' — ' . __('petshop.order_detail'))
@section('header', __('petshop.order_detail'))

@php
    $payment = $order->payments->sortByDesc('id')->first();
    $nextStatuses = \App\Models\ProductOrder::TRANSITIONS[$order->status] ?? [];
    $actionLabels = [
        'accepted' => 'accept_order',
        'preparing' => 'start_preparing',
        'ready_for_pickup' => 'mark_ready',
        'completed' => 'complete_order',
        'rejected' => 'reject_order',
    ];
@endphp

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="$order->order_number"
        :eyebrow="__('petshop.order_detail')"
        icon="receipt_long"
        :description="$order->pickup_clinic_name">
        <x-slot:actions>
            <a href="{{ route('admin.pet-shop.orders.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('petshop.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

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

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <x-admin.panel :title="__('petshop.order_items')" class="lg:col-span-2" flush>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('petshop.product_name') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.sku') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('petshop.price') }}</th>
                            <th class="px-5 py-3 text-center">Qty</th>
                            <th class="px-5 py-3 text-right">{{ __('petshop.subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @foreach($order->items as $item)
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $item->product_name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $item->variant_name }}</p>
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $item->sku }}</td>
                                <td class="px-5 py-3 text-right text-sm tabular-nums text-slate-700 dark:text-slate-300">
                                    Rp {{ number_format($item->discount_price ?? $item->unit_price, 0, ',', '.') }}
                                </td>
                                <td class="px-5 py-3 text-center text-sm tabular-nums text-slate-700 dark:text-slate-300">{{ $item->quantity }}</td>
                                <td class="px-5 py-3 text-right text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                                    Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-100 dark:border-slate-800">
                        <tr>
                            <td colspan="4" class="px-5 py-2 text-right text-xs text-slate-500 dark:text-slate-400">{{ __('petshop.subtotal') }}</td>
                            <td class="px-5 py-2 text-right text-sm tabular-nums text-slate-700 dark:text-slate-300">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td>
                        </tr>
                        @if($order->discount_total > 0)
                            <tr>
                                <td colspan="4" class="px-5 py-2 text-right text-xs text-slate-500 dark:text-slate-400">{{ __('petshop.discount') }}</td>
                                <td class="px-5 py-2 text-right text-sm tabular-nums text-emerald-600 dark:text-emerald-400">− Rp {{ number_format($order->discount_total, 0, ',', '.') }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="4" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('petshop.total') }}</td>
                            <td class="px-5 py-3 text-right text-base font-bold tabular-nums text-slate-900 dark:text-white">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-admin.panel>

        <div class="space-y-4">
            <x-admin.panel :title="__('common.status')">
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('petshop.order_status') }}</span>
                        <x-petshop.status-badge :status="$order->status" />
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('petshop.payment_status_label') }}</span>
                        <x-petshop.status-badge :status="$order->payment_status" type="payment" />
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('petshop.payment_method_label') }}</span>
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ __('petshop.payment_method.' . $order->payment_method) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('petshop.created_at') }}</span>
                        <span class="text-slate-700 dark:text-slate-300">{{ $order->created_at?->format('d M Y H:i') }}</span>
                    </div>
                    @if($order->reservation_expires_at)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('petshop.expires_at') }}</span>
                            <span class="text-slate-700 dark:text-slate-300">{{ $order->reservation_expires_at->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                </div>

                @if(!empty($nextStatuses))
                    <div class="mt-4 space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                        @foreach($nextStatuses as $nextStatus)
                            @continue(!isset($actionLabels[$nextStatus]))
                            @if($nextStatus === 'rejected')
                                <details>
                                    <summary class="admin-btn-danger w-full cursor-pointer list-none text-center">
                                        {{ __('petshop.reject_order') }}
                                    </summary>
                                    <form method="POST" action="{{ route('admin.pet-shop.orders.transition', $order) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <input type="hidden" name="status" value="rejected">
                                        <input name="reason" required maxlength="200" class="admin-input" placeholder="{{ __('petshop.reject_reason') }}">
                                        <button type="submit" class="admin-btn-danger w-full">{{ __('petshop.reject_order') }}</button>
                                    </form>
                                </details>
                            @else
                                <form method="POST" action="{{ route('admin.pet-shop.orders.transition', $order) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $nextStatus }}">
                                    <button type="submit" class="{{ $nextStatus === 'completed' ? 'admin-btn-primary' : 'admin-btn-secondary' }} w-full">
                                        {{ __('petshop.' . $actionLabels[$nextStatus]) }}
                                    </button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                @endif
            </x-admin.panel>

            <x-admin.panel :title="__('petshop.pickup_info')">
                <div class="space-y-1 text-sm">
                    <p class="font-semibold text-slate-800 dark:text-slate-200">{{ $order->pickup_clinic_name }}</p>
                    <p class="text-slate-500 dark:text-slate-400">{{ $order->pickup_clinic_address }}</p>
                    @if($order->pickup_clinic_phone)
                        <p class="text-slate-500 dark:text-slate-400">{{ $order->pickup_clinic_phone }}</p>
                    @endif
                    <p class="pt-3 text-xs text-slate-400">{{ __('petshop.customer') }}: {{ $order->customer_name }}</p>
                    @if($order->customer_phone)
                        <p class="text-xs text-slate-400">{{ $order->customer_phone }}</p>
                    @endif
                    @if($order->notes)
                        <p class="pt-2 text-xs italic text-slate-500 dark:text-slate-400">“{{ $order->notes }}”</p>
                    @endif
                </div>
            </x-admin.panel>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-admin.panel :title="__('petshop.payment_history')">
            @if($payment)
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('petshop.payment_status_label') }}</span>
                        <x-petshop.status-badge :status="$payment->status" type="payment" />
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('petshop.total') }}</span>
                        <span class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">Rp {{ number_format($payment->gross_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('petshop.payment_method_label') }}</span>
                        <span class="text-slate-700 dark:text-slate-300">{{ $payment->payment_method ? __('petshop.payment_method.' . $payment->payment_method) : '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">Provider</span>
                        <span class="font-mono text-xs text-slate-600 dark:text-slate-300">{{ $payment->provider }} · {{ $payment->environment }}</span>
                    </div>
                    @if($payment->merchant_id)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('petshop.merchant_id') }}</span>
                            <span class="font-mono text-xs text-slate-600 dark:text-slate-300">{{ $payment->merchant_id }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('petshop.date') }}</span>
                        <span class="text-xs text-slate-600 dark:text-slate-300">{{ $payment->paid_at?->format('d M Y H:i') ?? $payment->created_at?->format('d M Y H:i') }}</span>
                    </div>

                    @if($payment->refunds->isNotEmpty())
                        <div class="mt-3 space-y-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('petshop.refunds') }}</p>
                            @foreach($payment->refunds as $refund)
                                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $refund->refund_code }}</span>
                                        <x-admin.badge :label="$refund->status" :tone="$refund->status === 'succeeded' ? 'primary' : ($refund->status === 'failed' ? 'rose' : 'amber')" />
                                    </div>
                                    <p class="mt-1 text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-200">Rp {{ number_format($refund->amount, 0, ',', '.') }}</p>
                                    @if($refund->note)
                                        <p class="mt-1 text-[11px] text-slate-400">{{ $refund->note }}</p>
                                    @endif

                                    @if(in_array($refund->status, ['requested','pending'], true))
                                        <form method="POST" action="{{ route('admin.pet-shop.refunds.confirm', $refund) }}" class="mt-2 flex flex-col gap-2 sm:flex-row">
                                            @csrf
                                            <input name="note" class="admin-input flex-1 !py-1.5 !text-xs" placeholder="{{ __('petshop.refund_note') }}">
                                            <select name="result" class="admin-input !py-1.5 !text-xs sm:w-40">
                                                <option value="succeeded">{{ __('petshop.refund_succeeded') }}</option>
                                                <option value="failed">{{ __('petshop.refund_failed') }}</option>
                                            </select>
                                            <button type="submit" class="admin-btn-secondary !px-3 !py-1.5 !text-[11px]">{{ __('petshop.confirm_refund') }}</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <x-admin.empty-state icon="payments" :title="__('petshop.no_payment')" />
            @endif
        </x-admin.panel>

        <x-admin.panel :title="__('petshop.timeline')">
            @forelse($order->statusHistories as $history)
                <div class="relative flex gap-3 border-b border-slate-100 py-3 last:border-0 dark:border-slate-800">
                    <span class="mt-1 flex h-2 w-2 flex-shrink-0 rounded-full bg-emerald-500"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            {{ __('petshop.status.' . $history->to_status) }}
                        </p>
                        <p class="text-[11px] text-slate-400">
                            {{ $history->created_at?->format('d M Y H:i') }}
                            · {{ $history->source }}
                            @if($history->note)
                                · {{ $history->note }}
                            @endif
                        </p>
                    </div>
                </div>
            @empty
                <x-admin.empty-state icon="history" :title="__('petshop.timeline_empty')" />
            @endforelse
        </x-admin.panel>
    </div>
</div>
@endsection
