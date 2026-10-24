@php
    $paymentMethods = $paymentMethods ?? collect();
    $exportQuery = request()->only(['status', 'type', 'method', 'search', 'sort', 'direction', 'from', 'to']);
@endphp
@extends('layouts.admin')

@section('title', __('admin.payments_title') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.payments_title'))

@section('content')
<div class="space-y-6">

    {{-- Header halaman --}}
    <x-admin.page-header
        :title="__('admin.payments_title')"
        :eyebrow="__('admin.eyebrow_finance')"
        icon="payments"
        :description="__('admin.payments_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.bookings.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">calendar_month</span>
                {{ __('menu.appointments') }}
            </a>
            <a href="{{ route('admin.payments.export', $exportQuery) }}" target="_blank" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">print</span>
                {{ __('admin.export_pdf') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Kartu ringkasan --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card
            :label="__('admin.stat_total_transactions')"
            :value="number_format($stats['total_transactions'])"
            icon="receipt_long"
            tone="blue" />

        <x-admin.stat-card
            :label="__('admin.stat_total_collected')"
            :value="'Rp ' . number_format($stats['total_collected'], 0, ',', '.')"
            icon="trending_up"
            tone="primary" />

        <x-admin.stat-card
            :label="__('admin.stat_outstanding')"
            :value="'Rp ' . number_format($stats['total_outstanding'], 0, ',', '.')"
            icon="pending_actions"
            tone="amber" />

        <x-admin.stat-card
            :label="__('admin.stat_fully_paid')"
            :value="number_format($stats['paid_count'])"
            icon="check_circle"
            tone="violet" />
    </div>

    {{-- Filter --}}
    <x-admin.panel :title="__('admin.filter')" :subtitle="__('admin.export_hint')">
        <form method="GET" action="{{ route('admin.payments.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="payment-search" class="admin-label">{{ __('admin.search') }}</label>
                <input id="payment-search" type="text" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('admin.payment_search_placeholder') }}" class="admin-input">
            </div>

            <div>
                <label for="payment-status" class="admin-label">{{ __('common.status') }}</label>
                <select id="payment-status" name="status" class="admin-input">
                    <option value="">{{ __('admin.all_status') }}</option>
                    <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>{{ __('common.unpaid') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('common.pending') }}</option>
                    <option value="dp_pending" {{ request('status') === 'dp_pending' ? 'selected' : '' }}>{{ __('common.dp_pending') }}</option>
                    <option value="dp_paid" {{ request('status') === 'dp_paid' ? 'selected' : '' }}>{{ __('common.dp_paid') }}</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>{{ __('common.paid') }}</option>
                    <option value="partial" {{ request('status') === 'partial' ? 'selected' : '' }}>{{ __('common.partial') }}</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>{{ __('common.failed') }}</option>
                </select>
            </div>

            <div>
                <label for="payment-type" class="admin-label">{{ __('common.type') }}</label>
                <select id="payment-type" name="type" class="admin-input">
                    <option value="">{{ __('admin.all_types') }}</option>
                    <option value="dp" {{ request('type') === 'dp' ? 'selected' : '' }}>{{ __('admin.type_down_payment') }}</option>
                    <option value="full" {{ request('type') === 'full' ? 'selected' : '' }}>{{ __('admin.type_full_payment') }}</option>
                </select>
            </div>

            <div>
                <label for="payment-method" class="admin-label">{{ __('common.method') }}</label>
                <select id="payment-method" name="method" class="admin-input">
                    <option value="">{{ __('admin.all_methods') }}</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method }}" @selected(request('method') === $method)>{{ $method }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4">
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
                    {{ __('admin.apply_filter') }}
                </button>
                <a href="{{ route('admin.payments.index') }}" class="admin-btn-ghost">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">restart_alt</span>
                    {{ __('admin.reset_filter') }}
                </a>
            </div>
        </form>
    </x-admin.panel>

    {{-- Tabel transaksi --}}
    <x-admin.panel :title="__('admin.invoice_transactions')" :subtitle="__('admin.invoice_count', ['count' => $payments->total()])" flush>
        <x-slot:actions>
            <a href="{{ route('admin.payments.export', $exportQuery) }}" target="_blank" class="text-xs font-semibold text-primary transition hover:opacity-80">
                {{ __('admin.export_pdf') }}
            </a>
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('admin.col_invoice') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_customer') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_total_amount') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_paid') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_remaining') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_type') }}</th>
                        <th class="px-5 py-3">{{ __('common.status') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($payments as $payment)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3">
                                <p class="font-mono text-sm font-semibold text-slate-700 dark:text-slate-300">
                                    INV-{{ str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT) }}
                                </p>
                                <p class="mt-0.5 text-[10px] text-slate-400">#{{ $payment->id }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $payment->user->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $payment->pet->name }}</p>
                            </td>
                            <td class="px-5 py-3 text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                                Rp {{ number_format($payment->total_amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-sm font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                                Rp {{ number_format($payment->paid_amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3">
                                @if($payment->remaining_amount > 0)
                                    <p class="text-sm font-bold tabular-nums text-orange-600 dark:text-orange-400">
                                        Rp {{ number_format($payment->remaining_amount, 0, ',', '.') }}
                                    </p>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="\App\Support\AdminStatus::paymentTypeLabel($payment->payment_type)"
                                    :tone="\App\Support\AdminStatus::paymentTypeTone($payment->payment_type)" />
                                <p class="mt-1 text-[10px] text-slate-400">
                                    {{ $payment->payment_method ?: __('admin.not_selected') }}
                                </p>
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="\App\Support\AdminStatus::paymentLabel($payment->payment_status)"
                                    :tone="\App\Support\AdminStatus::paymentTone($payment->payment_status)"
                                    dot />
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('admin.bookings.show', $payment->id) }}#pembayaran"
                                       class="admin-icon-btn hover:!text-primary"
                                       title="{{ __('admin.open_booking_payment') }}"
                                       aria-label="{{ __('admin.open_booking_payment') }} INV-{{ $payment->id }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
                                    </a>

                                    <a href="{{ route('admin.payments.receipt', $payment->id) }}"
                                       class="admin-icon-btn hover:!text-emerald-600"
                                       title="{{ __('admin.download_invoice') }}"
                                       aria-label="{{ __('admin.download_invoice') }} INV-{{ $payment->id }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">download</span>
                                    </a>

                                    @if($payment->payment_status !== 'paid' && $payment->remaining_amount > 0)
                                        <form method="POST" action="{{ route('admin.payments.send-reminder', $payment->id) }}" class="inline"
                                              onsubmit="return confirm('{{ __('admin.remind_confirm') }}')">
                                            @csrf
                                            <button type="submit"
                                                    class="admin-icon-btn hover:!text-blue-600"
                                                    title="{{ __('admin.remind_confirm') }}"
                                                    aria-label="{{ __('admin.remind_confirm') }} INV-{{ $payment->id }}">
                                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">send</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state
                                    :title="__('admin.no_payments_title')"
                                    :description="__('admin.no_payments_desc')"
                                    icon="receipt_long"
                                    tone="slate" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($payments->hasPages())
            <x-slot:footer>
                {{ $payments->links() }}
            </x-slot:footer>
        @endif
    </x-admin.panel>
</div>
@endsection
