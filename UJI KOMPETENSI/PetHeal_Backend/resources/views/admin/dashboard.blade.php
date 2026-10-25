@extends('layouts.admin')

@section('title', __('dashboard.executive_dashboard') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', $clinicName ?? __('dashboard.executive_dashboard'))

@section('content')
<div class="space-y-6">

    {{-- Header halaman --}}
    <x-admin.page-header
        :title="__('dashboard.executive_dashboard')"
        :eyebrow="__('admin.eyebrow_operations')"
        icon="monitoring"
        :description="__('dashboard.dashboard_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.bookings.schedule') }}"
               class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">view_timeline</span>
                {{ __('dashboard.board_schedule') }}
            </a>
            <a href="{{ route('admin.bookings.index') }}"
               class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">calendar_month</span>
                {{ __('dashboard.bookings') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Filter rentang tanggal --}}
    <form method="GET" action="{{ route('admin.dashboard') }}"
          class="rounded-2xl border border-slate-200/70 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[150px]">
                    <label for="dashboard-start" class="admin-label !mb-1 text-[10px] uppercase tracking-[0.16em] text-slate-400">{{ __('common.from') }}</label>
                    <input id="dashboard-start" type="date" name="start_date" value="{{ request('start_date') }}" class="admin-input">
                </div>
                <div class="min-w-[150px]">
                    <label for="dashboard-end" class="admin-label !mb-1 text-[10px] uppercase tracking-[0.16em] text-slate-400">{{ __('common.to') }}</label>
                    <input id="dashboard-end" type="date" name="end_date" value="{{ request('end_date') }}" class="admin-input">
                </div>
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
                    {{ __('common.apply') }}
                </button>
                @if(request('start_date') || request('end_date'))
                    <a href="{{ route('admin.dashboard') }}" class="admin-btn-ghost">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">restart_alt</span>
                        {{ __('common.show_all') }}
                    </a>
                @endif
            </div>

            <p class="text-xs text-slate-400">
                @if(request('start_date') && request('end_date'))
                    {{ \Carbon\Carbon::parse(request('start_date'))->locale(app()->getLocale())->translatedFormat('d M Y') }}
                    &ndash;
                    {{ \Carbon\Carbon::parse(request('end_date'))->locale(app()->getLocale())->translatedFormat('d M Y') }}
                @else
                    {{ __('common.all_time') }} &mdash; {{ __('common.leave_empty_all') }}
                @endif
            </p>
        </div>
    </form>

    {{-- Ringkasan cepat --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card
            :label="__('dashboard.todays_visits')"
            :value="$todayBookings"
            icon="event_available"
            tone="primary" />

        <x-admin.stat-card
            :label="__('dashboard.waitlist')"
            :value="$pendingBookings"
            icon="pending_actions"
            tone="amber" />

        <x-admin.stat-card
            :label="__('dashboard.total_pets')"
            :value="number_format($totalPatients)"
            icon="pets"
            tone="sky" />

        <x-admin.stat-card
            :label="__('dashboard.active_staff')"
            :value="$totalDoctors"
            icon="stethoscope"
            tone="violet" />
    </div>

    {{-- Pendapatan --}}
    @php
        $growthBadge = function (?float $value) {
            if ($value === null) {
                return ['label' => __('dashboard.no_baseline'), 'class' => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'];
            }
            $sign = $value > 0 ? '+' : '';

            return [
                'label' => $sign . number_format($value, 1, ',', '.') . '%',
                'class' => $value >= 0
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                    : 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
            ];
        };
        $mtdBadge = $growthBadge($monthlyRevenueGrowth);
        $dailyBadge = $growthBadge($dailyRevenueGrowth);
        $revenueData = is_array($monthlyRevenueData['data'] ?? null) ? $monthlyRevenueData['data'] : [0];
        $maxRevenue = max($revenueData) ?: 1;
        $dailyData = is_array($dailyRevenueData['data'] ?? null) ? $dailyRevenueData['data'] : [0];
        $maxDaily = max($dailyData) ?: 1;
    @endphp

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ __('dashboard.revenue_mtd') }}</p>
                    <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight text-slate-900 dark:text-white">
                        Rp {{ number_format($monthlyRevenue, 0, ',', '.') }}
                    </p>
                </div>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $mtdBadge['class'] }}">
                    {{ $mtdBadge['label'] }}
                </span>
            </div>
            <div class="mt-4 flex h-8 items-end gap-1" aria-hidden="true">
                @foreach($revenueData as $revenue)
                    <div class="flex-1 rounded-t bg-emerald-500/25" style="height: {{ max(4, ($revenue / $maxRevenue) * 100) }}%"></div>
                @endforeach
            </div>
            <p class="mt-3 text-[11px] text-slate-400">{{ __('dashboard.same_day_comparison') }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ __('dashboard.revenue_daily') }}</p>
                    <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight text-slate-900 dark:text-white">
                        Rp {{ number_format($todayRevenue, 0, ',', '.') }}
                    </p>
                </div>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $dailyBadge['class'] }}">
                    {{ $dailyBadge['label'] }}
                </span>
            </div>
            <div class="mt-4 flex h-8 items-end gap-1" aria-hidden="true">
                @foreach($dailyData as $revenue)
                    <div class="flex-1 rounded-t bg-blue-500/25" style="height: {{ max(4, ($revenue / $maxDaily) * 100) }}%"></div>
                @endforeach
            </div>
            <p class="mt-3 text-[11px] text-slate-400">{{ __('dashboard.last_7_days') }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ __('dashboard.collection_rate') }}</p>
                    <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight text-slate-900 dark:text-white">{{ $collectionRate }}%</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    Rp {{ number_format($totalCollected, 0, ',', '.') }}
                </span>
            </div>
            <div class="mt-4">
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                     role="progressbar" aria-valuenow="{{ $collectionRate }}" aria-valuemin="0" aria-valuemax="100"
                     aria-label="{{ __('dashboard.collection_rate') }}">
                    <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: {{ $collectionRate }}%"></div>
                </div>
                <div class="mt-2 flex justify-between text-[11px] text-slate-400">
                    <span>{{ __('dashboard.collected') }}</span>
                    <span class="tabular-nums">{{ __('dashboard.billed') }} Rp {{ number_format($totalBilled, 0, ',', '.') }}</span>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-slate-400">
                {{ __('dashboard.outstanding_amount') }} Rp {{ number_format($totalOutstanding, 0, ',', '.') }}
            </p>
        </div>
    </div>

    {{-- Grafik --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-admin.panel :title="__('dashboard.payment_status_distribution')" :subtitle="__('dashboard.payment_status_desc')">
            <div class="h-64">
                <canvas id="paymentStatusChart"></canvas>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('dashboard.top_doctors')" :subtitle="__('dashboard.top_doctors_desc')">
            <div class="h-64">
                <canvas id="topDoctorsChart"></canvas>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('dashboard.monthly_booking_trends')" :subtitle="__('dashboard.monthly_booking_desc')">
            <div class="h-64">
                <canvas id="monthlyBookingChart"></canvas>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('dashboard.monthly_revenue_trend')" :subtitle="__('dashboard.monthly_revenue_desc')">
            <div class="h-64">
                <canvas id="monthlyRevenueChart"></canvas>
            </div>
        </x-admin.panel>
    </div>

    {{-- Status pembayaran --}}
    <x-admin.panel :title="__('dashboard.payment_status_overview')" :subtitle="__('dashboard.payment_midtrans_desc')">
        <x-slot:actions>
            <x-admin.badge :label="__('dashboard.midtrans_transactions') . ' · ' . $midtransTransactions" tone="blue" />
        </x-slot:actions>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach([
                ['label' => __('dashboard.unpaid'), 'value' => $unpaidBookings, 'tone' => 'slate', 'hint' => __('dashboard.awaiting_payment')],
                ['label' => __('dashboard.pending'), 'value' => $pendingPayment, 'tone' => 'amber', 'hint' => __('dashboard.processing_payment')],
                ['label' => __('dashboard.dp_pending'), 'value' => $dpPendingBookings, 'tone' => 'orange', 'hint' => __('dashboard.awaiting_dp')],
                ['label' => __('dashboard.dp_paid'), 'value' => $dpPaidBookings, 'tone' => 'blue', 'hint' => __('dashboard.awaiting_remaining')],
                ['label' => __('dashboard.paid'), 'value' => $paidInFull, 'tone' => 'primary', 'hint' => __('dashboard.fully_settled')],
                ['label' => __('dashboard.failed'), 'value' => $failedPayments, 'tone' => 'rose', 'hint' => __('dashboard.payment_failed')],
            ] as $tile)
                <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full {{ [
                            'slate' => 'bg-slate-400',
                            'amber' => 'bg-amber-500',
                            'orange' => 'bg-orange-500',
                            'blue' => 'bg-blue-500',
                            'primary' => 'bg-emerald-500',
                            'rose' => 'bg-rose-500',
                        ][$tile['tone']] }}" aria-hidden="true"></span>
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">{{ $tile['label'] }}</p>
                    </div>
                    <p class="mt-1.5 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $tile['value'] }}</p>
                    <p class="mt-1 text-[11px] text-slate-400">{{ $tile['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-emerald-200/60 bg-emerald-50/60 p-4 dark:border-emerald-900/50 dark:bg-emerald-900/15">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-emerald-700 dark:text-emerald-400">{{ __('dashboard.total_collected') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">
                    Rp {{ number_format($totalCollected, 0, ',', '.') }}
                </p>
            </div>
            <div class="rounded-xl border border-orange-200/60 bg-orange-50/60 p-4 dark:border-orange-900/50 dark:bg-orange-900/15">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-orange-700 dark:text-orange-400">{{ __('dashboard.total_outstanding') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-orange-700 dark:text-orange-300">
                    Rp {{ number_format($totalOutstanding, 0, ',', '.') }}
                </p>
            </div>
        </div>
    </x-admin.panel>

    {{-- Pembayaran terbaru & saldo tertunggak --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-admin.panel :title="__('dashboard.recent_payments')" :subtitle="__('dashboard.recent_payments_desc')" flush>
            <x-slot:actions>
                <a href="{{ route('admin.payments.index') }}"
                   class="text-xs font-semibold text-primary transition hover:opacity-80">
                    {{ __('dashboard.view_all_payments') }}
                </a>
            </x-slot:actions>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('admin.col_customer') }}</th>
                            <th class="px-5 py-3">{{ __('crud.total_amount') }}</th>
                            <th class="px-5 py-3">{{ __('common.status') }}</th>
                            <th class="px-5 py-3 text-center">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @forelse($recentPayments as $booking)
                            <tr class="admin-table-row">
                                <td class="px-5 py-3">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $booking->user->name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $booking->pet->name }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                                        Rp {{ number_format($booking->paid_amount, 0, ',', '.') }}
                                    </p>
                                    <p class="text-[11px] tabular-nums text-slate-400">
                                        {{ __('common.of') }} Rp {{ number_format($booking->total_amount, 0, ',', '.') }}
                                    </p>
                                </td>
                                <td class="px-5 py-3">
                                    <x-admin.badge
                                        :label="\App\Support\AdminStatus::paymentLabel($booking->payment_status)"
                                        :tone="\App\Support\AdminStatus::paymentTone($booking->payment_status)" />
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                       class="admin-icon-btn"
                                       title="{{ __('admin.view_detail') }}"
                                       aria-label="{{ __('admin.view_detail') }} #{{ $booking->id }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-admin.empty-state
                                        :title="__('dashboard.no_recent_payments')"
                                        icon="receipt_long"
                                        tone="slate" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('dashboard.outstanding_balance')" :subtitle="__('dashboard.outstanding_desc')" flush>
            <x-slot:actions>
                <x-admin.badge :label="__('dashboard.pending_count') . ' · ' . $outstandingCount" tone="orange" />
            </x-slot:actions>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('admin.col_customer') }}</th>
                            <th class="px-5 py-3">{{ __('admin.col_remaining') }}</th>
                            <th class="px-5 py-3">{{ __('crud.payment_type') }}</th>
                            <th class="px-5 py-3 text-center">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @forelse($outstandingBookings as $booking)
                            <tr class="admin-table-row">
                                <td class="px-5 py-3">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $booking->user->name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $booking->pet->name }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-sm font-bold tabular-nums text-orange-600 dark:text-orange-400">
                                        Rp {{ number_format($booking->remaining_amount, 0, ',', '.') }}
                                    </p>
                                </td>
                                <td class="px-5 py-3">
                                    <x-admin.badge
                                        :label="$booking->payment_type === 'dp' ? __('admin.type_dp') : __('admin.type_full')"
                                        tone="violet" />
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                       class="admin-icon-btn"
                                       title="{{ __('admin.view_detail') }}"
                                       aria-label="{{ __('admin.view_detail') }} #{{ $booking->id }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">send</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-admin.empty-state
                                        :title="__('dashboard.no_outstanding')"
                                        icon="task_alt"
                                        tone="primary" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
    </div>

    {{-- Log pasien & pusat aksi --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-admin.panel :title="__('dashboard.patient_logs')" :subtitle="__('dashboard.patient_logs_desc')" flush class="lg:col-span-2">
            <x-slot:actions>
                <a href="{{ route('admin.bookings.index') }}"
                   class="text-xs font-semibold text-primary transition hover:opacity-80">
                    {{ __('common.view_all') }}
                </a>
            </x-slot:actions>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('dashboard.patient_profile') }}</th>
                            <th class="px-5 py-3">{{ __('dashboard.clinician') }}</th>
                            <th class="px-5 py-3">{{ __('common.status') }}</th>
                            <th class="px-5 py-3 text-center">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @forelse($recentBookings as $booking)
                            <tr class="admin-table-row">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-100 bg-emerald-50 text-[10px] font-bold text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/30 dark:text-emerald-400">
                                            {{ \Illuminate\Support\Str::substr($booking->pet->name, 0, 1) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $booking->pet->name }}</p>
                                            <p class="text-[11px] text-slate-400">{{ $booking->user->name }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    @if($booking->doctor->photo_url)
                                        <img class="h-8 w-8 rounded-full object-cover" src="{{ $booking->doctor->photo_url }}" alt="{{ $booking->doctor->name }}">
                                    @else
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full border border-blue-100 bg-blue-50 text-[10px] font-bold text-blue-700 dark:border-blue-900/60 dark:bg-blue-900/30 dark:text-blue-400">
                                            {{ \Illuminate\Support\Str::substr($booking->doctor->name, 0, 1) }}
                                        </span>
                                    @endif
                                    <span class="ml-2 text-sm text-slate-700 dark:text-slate-200">{{ $booking->doctor->name }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <x-admin.badge
                                        :label="\App\Support\AdminStatus::bookingLabel($booking->status)"
                                        :tone="\App\Support\AdminStatus::bookingTone($booking->status)" />
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                       class="admin-icon-btn"
                                       title="{{ __('admin.view_detail') }}"
                                       aria-label="{{ __('admin.view_detail') }} #{{ $booking->id }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-admin.empty-state
                                        :title="__('dashboard.no_recent_bookings')"
                                        icon="event_note"
                                        tone="slate" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('dashboard.action_center')" :subtitle="__('dashboard.action_center_desc')">
            <div class="space-y-2">
                <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}"
                   class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/70 px-4 py-3 transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-900/25 dark:text-amber-400">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">pending_actions</span>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-xs font-semibold text-slate-700 dark:text-slate-200">{{ __('dashboard.pending_confirmations') }}</span>
                            <span class="block text-[11px] text-slate-400">{{ __('dashboard.pending_confirmations_desc') }}</span>
                        </span>
                    </span>
                    <span class="text-lg font-bold tabular-nums text-slate-900 dark:text-white">{{ $pendingBookings }}</span>
                </a>

                <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}"
                   class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/70 px-4 py-3 transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-900/25 dark:text-blue-400">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">schedule</span>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-xs font-semibold text-slate-700 dark:text-slate-200">{{ __('dashboard.payments_processing') }}</span>
                            <span class="block text-[11px] text-slate-400">{{ __('dashboard.payments_processing_desc') }}</span>
                        </span>
                    </span>
                    <span class="text-lg font-bold tabular-nums text-slate-900 dark:text-white">{{ $pendingPayment }}</span>
                </a>

                <a href="{{ route('admin.payments.index', ['status' => 'failed']) }}"
                   class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/70 px-4 py-3 transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-900/25 dark:text-rose-400">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">error</span>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-xs font-semibold text-slate-700 dark:text-slate-200">{{ __('dashboard.failed_payments') }}</span>
                            <span class="block text-[11px] text-slate-400">{{ __('dashboard.failed_payments_desc') }}</span>
                        </span>
                    </span>
                    <span class="text-lg font-bold tabular-nums text-slate-900 dark:text-white">{{ $failedPayments }}</span>
                </a>
            </div>

            <div class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">{{ __('dashboard.outstanding_balance') }}</p>
                        <p class="mt-1 text-sm font-bold tabular-nums text-slate-900 dark:text-white">
                            Rp {{ number_format($totalOutstanding, 0, ',', '.') }}
                        </p>
                    </div>
                    <a href="{{ route('admin.payments.index') }}"
                       class="admin-btn-ghost !px-3 !py-2 !text-[11px]">
                        {{ __('admin.view_detail') }}
                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">arrow_forward</span>
                    </a>
                </div>
            </div>
        </x-admin.panel>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        if (!window.Chart) {
            console.warn('Chart.js bundle is missing; dashboard charts were skipped.');
            return;
        }

        const root = document.documentElement;
        const isDark = root.classList.contains('dark');
        const primary = (getComputedStyle(root).getPropertyValue('--color-primary') || '#10B981').trim();
        const gridColor = isDark ? 'rgba(148, 163, 184, 0.16)' : 'rgba(0, 0, 0, 0.05)';
        const textColor = isDark ? '#94A3B8' : '#64748B';

        function withAlpha(hex, alpha) {
            const clean = hex.replace('#', '');
            if (clean.length !== 6) return hex;
            const value = parseInt(clean, 16);
            return 'rgba(' + ((value >> 16) & 255) + ', ' + ((value >> 8) & 255) + ', ' + (value & 255) + ', ' + alpha + ')';
        }

        Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
        Chart.defaults.color = textColor;

        // Keep chart chrome in sync when the shell toggles dark mode. Chart.js
        // does not observe CSS variables, so grid/text colors need a nudge.
        function syncChartTheme(dark) {
            const grid = dark ? 'rgba(148, 163, 184, 0.16)' : 'rgba(0, 0, 0, 0.05)';
            const text = dark ? '#94A3B8' : '#64748B';
            Chart.defaults.color = text;

            document.querySelectorAll('canvas').forEach(function (canvas) {
                const chart = Chart.getChart(canvas);
                if (!chart) return;

                Object.values(chart.options.scales || {}).forEach(function (scale) {
                    if (scale.grid) scale.grid.color = grid;
                });
                if (chart.options.plugins && chart.options.plugins.legend && chart.options.plugins.legend.labels) {
                    chart.options.plugins.legend.labels.color = text;
                }
                if (chart.config.type === 'line') {
                    chart.data.datasets.forEach(function (dataset) {
                        dataset.pointBorderColor = dark ? '#0F172A' : '#fff';
                    });
                }
                if (chart.config.type === 'doughnut') {
                    chart.data.datasets.forEach(function (dataset) {
                        dataset.borderColor = dark ? '#0F172A' : '#fff';
                    });
                }
                chart.update('none');
            });
        }

        window.addEventListener('petheal:themechange', function (event) {
            syncChartTheme(Boolean(event.detail && event.detail.isDark));
        });

        const monthlyBookingEl = document.getElementById('monthlyBookingChart');
        if (monthlyBookingEl) {
            new Chart(monthlyBookingEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: @json($monthlyBookingData['labels']),
                    datasets: [{
                        label: @json(__('dashboard.bookings')),
                        data: @json($monthlyBookingData['data']),
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: '#3B82F6',
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        const monthlyRevenueEl = document.getElementById('monthlyRevenueChart');
        if (monthlyRevenueEl) {
            new Chart(monthlyRevenueEl.getContext('2d'), {
                type: 'line',
                data: {
                    labels: @json($monthlyRevenueData['labels']),
                    datasets: [{
                        label: @json(__('dashboard.revenue')),
                        data: @json($monthlyRevenueData['data']),
                        backgroundColor: withAlpha(primary, 0.12),
                        borderColor: primary,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: primary,
                        pointBorderColor: isDark ? '#0F172A' : '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: gridColor },
                            ticks: {
                                callback: function (value) {
                                    return 'Rp ' + (value / 1000000).toFixed(1) + ' ' + @json(__('dashboard.million_short'));
                                }
                            }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        const paymentStatusEl = document.getElementById('paymentStatusChart');
        if (paymentStatusEl) {
            new Chart(paymentStatusEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: [
                        @json(__('dashboard.unpaid')),
                        @json(__('dashboard.pending')),
                        @json(__('dashboard.dp_pending')),
                        @json(__('dashboard.dp_paid')),
                        @json(__('dashboard.paid')),
                        @json(__('dashboard.failed'))
                    ],
                    datasets: [{
                        data: [
                            {{ $unpaidBookings }},
                            {{ $pendingPayment }},
                            {{ $dpPendingBookings }},
                            {{ $dpPaidBookings }},
                            {{ $paidInFull }},
                            {{ $failedPayments }}
                        ],
                        backgroundColor: ['#9CA3AF', '#F59E0B', '#F97316', '#3B82F6', '#10B981', '#EF4444'],
                        borderColor: isDark ? '#0F172A' : '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 8,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 16,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: { size: 11 }
                            }
                        }
                    },
                    cutout: '65%',
                }
            });
        }

        const topDoctorsEl = document.getElementById('topDoctorsChart');
        if (topDoctorsEl) {
            new Chart(topDoctorsEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: @json($popularDoctors['labels']),
                    datasets: [{
                        label: @json(__('dashboard.bookings')),
                        data: @json($popularDoctors['data']),
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(139, 92, 246, 0.8)',
                            'rgba(236, 72, 153, 0.8)',
                        ],
                        borderColor: ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899'],
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor } },
                        y: { grid: { display: false } }
                    }
                }
            });
        }
    });
</script>
@endsection
