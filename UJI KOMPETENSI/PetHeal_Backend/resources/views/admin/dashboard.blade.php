@extends('layouts.admin')

@section('title', 'Dashboard — ' . ($clinicName ?? 'VCMS') . ' Admin')
@section('header', ($clinicName ?? 'VCMS') . ' — Executive Dashboard')

@section('content')
<!-- Date Range Filter -->
<div class="glass-card rounded-2xl p-4 border border-slate-200/50 dark:border-slate-700 mb-6">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-slate-400 text-lg">calendar_month</span>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                @if(request('start_date') && request('end_date'))
                    {{ \Carbon\Carbon::parse(request('start_date'))->format('d M Y') }} — {{ \Carbon\Carbon::parse(request('end_date'))->format('d M Y') }}
                @else
                    All Time
                @endif
            </span>
        </div>
        <span class="text-[10px] text-slate-400">(leave empty to show all data)</span>
    </div>
    <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-4 flex-wrap">
        <div>
            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">From</label>
            <input type="date" name="start_date" value="{{ request('start_date') }}" class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm dark:text-slate-200">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">To</label>
            <input type="date" name="end_date" value="{{ request('end_date') }}" class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm dark:text-slate-200">
        </div>
        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-xl text-sm font-semibold mt-5">Apply</button>
        <a href="{{ route('admin.dashboard') }}" class="bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-400 px-4 py-2 rounded-xl text-sm font-semibold mt-5">Show All</a>
    </form>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <div class="glass-card p-6 rounded-2xl flex items-center gap-4">
        <div class="w-10 h-10 bg-emerald-500/5 text-emerald-600 rounded-xl flex items-center justify-center border border-emerald-500/10">
            <span class="material-symbols-outlined text-[20px]">event_available</span>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Today's Visits</p>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $todayBookings }}</h3>
        </div>
    </div>
    <div class="glass-card p-6 rounded-2xl flex items-center gap-4">
        <div class="w-10 h-10 bg-blue-500/5 text-blue-600 rounded-xl flex items-center justify-center border border-blue-500/10">
            <span class="material-symbols-outlined text-[20px]">pending_actions</span>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Waitlist</p>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $pendingBookings }}</h3>
        </div>
    </div>
    <div class="glass-card p-6 rounded-2xl flex items-center gap-4">
        <div class="w-10 h-10 bg-indigo-500/5 text-indigo-600 rounded-xl flex items-center justify-center border border-indigo-500/10">
            <span class="material-symbols-outlined text-[20px]">pets</span>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Pets</p>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ number_format($totalPatients) }}</h3>
        </div>
    </div>
    <div class="glass-card p-6 rounded-2xl flex items-center gap-4">
        <div class="w-10 h-10 bg-slate-500/5 text-slate-600 rounded-xl flex items-center justify-center border border-slate-500/10">
            <span class="material-symbols-outlined text-[20px]">stethoscope</span>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Active Staff</p>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $totalDoctors }}</h3>
        </div>
    </div>
</div>

<!-- Revenue Cards -->
@php
    $growthBadge = function (?float $value) {
        if ($value === null) {
            return ['label' => 'No baseline', 'class' => 'text-slate-500 bg-slate-100 dark:bg-slate-800 dark:text-slate-400'];
        }
        $sign = $value > 0 ? '+' : '';
        return [
            'label' => $sign . number_format($value, 1, ',', '.') . '%',
            'class' => $value >= 0
                ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-400'
                : 'text-red-600 bg-red-50 dark:bg-red-900/30 dark:text-red-400',
        ];
    };
    $mtdBadge = $growthBadge($monthlyRevenueGrowth);
    $dailyBadge = $growthBadge($dailyRevenueGrowth);
@endphp
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
    <div class="glass-card p-6 rounded-2xl border-l-2 border-l-emerald-500">
        <div class="flex justify-between items-start gap-3">
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Revenue (MTD)</p>
                <h4 class="text-xl font-bold text-slate-900 dark:text-white mt-1 tabular-nums">Rp {{ number_format($monthlyRevenue, 0, ',', '.') }}</h4>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap {{ $mtdBadge['class'] }}"
                  title="Compared with the same number of days last month">
                {{ $mtdBadge['label'] }}
            </span>
        </div>
        <div class="w-full h-8 mt-4 flex items-end gap-1" aria-hidden="true">
            @php
                $revenueData = is_array($monthlyRevenueData['data'] ?? null) ? $monthlyRevenueData['data'] : [0];
                $maxRevenue = max($revenueData);
                if ($maxRevenue <= 0) {
                    $maxRevenue = 1;
                }
            @endphp
            @foreach($revenueData as $revenue)
                <div class="flex-1 bg-emerald-500/20 rounded-t" style="height: {{ max(4, ($revenue / $maxRevenue) * 100) }}%"></div>
            @endforeach
        </div>
        <p class="text-[10px] text-slate-400 mt-3">Same-day comparison with last month</p>
    </div>
    <div class="glass-card p-6 rounded-2xl border-l-2 border-l-blue-500">
        <div class="flex justify-between items-start gap-3">
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Revenue (Today)</p>
                <h4 class="text-xl font-bold text-slate-900 dark:text-white mt-1 tabular-nums">Rp {{ number_format($todayRevenue, 0, ',', '.') }}</h4>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap {{ $dailyBadge['class'] }}"
                  title="Compared with yesterday">
                {{ $dailyBadge['label'] }}
            </span>
        </div>
        <div class="w-full h-8 mt-4 flex items-end gap-1" aria-hidden="true">
            @php
                $dailyData = is_array($dailyRevenueData['data'] ?? null) ? $dailyRevenueData['data'] : [0];
                $maxDaily = max($dailyData);
                if ($maxDaily <= 0) {
                    $maxDaily = 1;
                }
            @endphp
            @foreach($dailyData as $revenue)
                <div class="flex-1 bg-blue-500/20 rounded-t" style="height: {{ max(4, ($revenue / $maxDaily) * 100) }}%"></div>
            @endforeach
        </div>
        <p class="text-[10px] text-slate-400 mt-3">Last 7 days · compared with yesterday</p>
    </div>
    <div class="glass-card p-6 rounded-2xl border-l-2 border-l-slate-300">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Collection Rate</p>
                <h4 class="text-xl font-bold text-slate-900 dark:text-white mt-1 tabular-nums">{{ $collectionRate }}%</h4>
            </div>
            <span class="text-[10px] font-bold text-slate-500 bg-slate-100 dark:bg-slate-800 dark:text-slate-400 px-2 py-0.5 rounded-full">
                Rp {{ number_format($totalCollected, 0, ',', '.') }} collected
            </span>
        </div>
        <div class="mt-4">
            <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden"
                 role="progressbar" aria-valuenow="{{ $collectionRate }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="Collection rate">
                <div class="bg-primary h-full rounded-full transition-all duration-500" style="width: {{ $collectionRate }}%"></div>
            </div>
            <div class="flex justify-between text-[10px] text-slate-400 mt-2">
                <span>Collected</span>
                <span class="tabular-nums">of Rp {{ number_format($totalBilled, 0, ',', '.') }} billed</span>
            </div>
        </div>
        <p class="text-[10px] text-slate-400 mt-3">
            Rp {{ number_format($totalOutstanding, 0, ',', '.') }} still outstanding
        </p>
        <p class="text-[10px] text-slate-400 mt-1">
            Rp {{ number_format($totalRevenue, 0, ',', '.') }} all-time revenue
        </p>
    </div>
</div>

<!-- Payment Doughnut & Top Doctors -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="glass-card p-8 rounded-2xl">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Payment Status Distribution</h3>
                <p class="text-xs text-slate-400 mt-0.5">Breakdown of all payment statuses</p>
            </div>
        </div>
        <div class="h-64 relative flex items-center justify-center">
            <canvas id="paymentStatusChart"></canvas>
        </div>
    </div>
    <div class="glass-card p-8 rounded-2xl">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Top Doctors</h3>
                <p class="text-xs text-slate-400 mt-0.5">Highest booking volume</p>
            </div>
            <span class="material-symbols-outlined text-slate-400">monitoring</span>
        </div>
        <div class="h-64 relative">
            <canvas id="topDoctorsChart"></canvas>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="glass-card p-8 rounded-2xl">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Monthly Booking Trends</h3>
                <p class="text-xs text-slate-400 mt-0.5">Last 6 months overview</p>
            </div>
        </div>
        <div class="h-64 relative">
            <canvas id="monthlyBookingChart"></canvas>
        </div>
    </div>
    <div class="glass-card p-8 rounded-2xl">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Monthly Revenue Trend</h3>
                <p class="text-xs text-slate-400 mt-0.5">Revenue growth over time</p>
            </div>
        </div>
        <div class="h-64 relative">
            <canvas id="monthlyRevenueChart"></canvas>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- PAYMENT SECTION - Midtrans Integration -->
<!-- ===================================================== -->
<div class="space-y-8">
    <!-- Payment Status Cards -->
    <div class="glass-card p-8 rounded-2xl border border-slate-200/50">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Payment Status Overview</h3>
                <p class="text-xs text-slate-400 mt-0.5">Midtrans integration & booking payments</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-600 text-lg">payments</span>
                <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">{{ $midtransTransactions }} Midtrans Transactions</span>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <!-- Unpaid -->
            <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4 border border-slate-200/50">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Unpaid</p>
                </div>
                <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $unpaidBookings }}</p>
                <p class="text-[9px] text-slate-400 mt-1">Awaiting payment</p>
            </div>

            <!-- Pending -->
            <div class="bg-yellow-50/50 dark:bg-yellow-900/10 rounded-xl p-4 border border-yellow-200/50">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></div>
                    <p class="text-[10px] font-bold text-yellow-600 uppercase tracking-wider">Pending</p>
                </div>
                <p class="text-2xl font-bold text-yellow-600">{{ $pendingPayment }}</p>
                <p class="text-[9px] text-yellow-600/70 mt-1">Processing payment</p>
            </div>

            <!-- DP Paid -->
            <div class="bg-blue-50/50 dark:bg-blue-900/10 rounded-xl p-4 border border-blue-200/50">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                    <p class="text-[10px] font-bold text-blue-600 uppercase tracking-wider">DP Paid</p>
                </div>
                <p class="text-2xl font-bold text-blue-600">{{ $dpPaidBookings }}</p>
                <p class="text-[9px] text-blue-600/70 mt-1">Awaiting remaining</p>
            </div>

            <!-- Paid in Full -->
            <div class="bg-emerald-50/50 dark:bg-emerald-900/10 rounded-xl p-4 border border-emerald-200/50">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                    <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Paid</p>
                </div>
                <p class="text-2xl font-bold text-emerald-600">{{ $paidInFull }}</p>
                <p class="text-[9px] text-emerald-600/70 mt-1">Fully settled</p>
            </div>

            <!-- Failed -->
            <div class="bg-red-50/50 dark:bg-red-900/10 rounded-xl p-4 border border-red-200/50">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-2 h-2 rounded-full bg-red-500"></div>
                    <p class="text-[10px] font-bold text-red-600 uppercase tracking-wider">Failed</p>
                </div>
                <p class="text-2xl font-bold text-red-600">{{ $failedPayments }}</p>
                <p class="text-[9px] text-red-600/70 mt-1">Payment failed</p>
            </div>
        </div>

        <!-- Total Collected vs Outstanding -->
        <div class="grid grid-cols-2 gap-4 mt-6">
            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-4 border border-emerald-200/50">
                <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Total Collected</p>
                <p class="text-xl font-bold text-emerald-700 dark:text-emerald-400">Rp {{ number_format($totalCollected, 0, ',', '.') }}</p>
            </div>
            <div class="bg-orange-50 dark:bg-orange-900/20 rounded-xl p-4 border border-orange-200/50">
                <p class="text-[10px] font-bold text-orange-600 uppercase tracking-wider mb-1">Total Outstanding</p>
                <p class="text-xl font-bold text-orange-700 dark:text-orange-400">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    <!-- Recent Payments Table -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Payments -->
        <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
            <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Recent Payments</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Latest payment activity</p>
                </div>
                <a href="{{ route('admin.bookings.index') }}" class="text-xs text-primary hover:text-emerald-600 font-medium">View All Bookings</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50/50 dark:bg-slate-800/30 text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                            <th class="px-6 py-3">Customer</th>
                            <th class="px-6 py-3">Amount</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800/50">
                        @forelse($recentPayments as $booking)
                        <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/10 transition-colors">
                            <td class="px-6 py-3">
                                <p class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ $booking->user->name }}</p>
                                <p class="text-[10px] text-slate-400">{{ $booking->pet->name }}</p>
                            </td>
                            <td class="px-6 py-3">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Rp {{ number_format($booking->paid_amount, 0, ',', '.') }}</p>
                                <p class="text-[10px] text-slate-400">of Rp {{ number_format($booking->total_amount, 0, ',', '.') }}</p>
                            </td>
                            <td class="px-6 py-3">
                                @php
                                    $paymentStatusColors = [
                                        'paid' => ['bg-emerald-50 text-emerald-600 border-emerald-100', 'Paid'],
                                        'dp_paid' => ['bg-blue-50 text-blue-600 border-blue-100', 'DP Paid'],
                                        'pending' => ['bg-yellow-50 text-yellow-600 border-yellow-100', 'Pending'],
                                        'partial' => ['bg-orange-50 text-orange-600 border-orange-100', 'Partial'],
                                        'failed' => ['bg-red-50 text-red-600 border-red-100', 'Failed'],
                                    ];
                                    $pStyle = $paymentStatusColors[$booking->payment_status] ?? ['bg-gray-50 text-gray-600 border-gray-100', ucfirst($booking->payment_status)];
                                @endphp
                                <span class="px-2 py-0.5 text-[9px] font-bold rounded border {{ $pStyle[0] }}">{{ $pStyle[1] }}</span>
                            </td>
                            <td class="px-6 py-3 text-center">
                                <a href="{{ route('admin.bookings.show', $booking->id) }}" class="w-7 h-7 rounded-lg text-slate-400 hover:text-primary transition-all inline-flex items-center justify-center">
                                    <span class="material-symbols-outlined text-md">visibility</span>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-slate-400">No recent payments</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Outstanding Balance -->
        <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
            <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Outstanding Balance</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Bookings awaiting remaining payment</p>
                </div>
                <span class="text-[10px] font-bold text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full">{{ $outstandingBookings->count() }} Pending</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50/50 dark:bg-slate-800/30 text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                            <th class="px-6 py-3">Customer</th>
                            <th class="px-6 py-3">Remaining</th>
                            <th class="px-6 py-3">Type</th>
                            <th class="px-6 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800/50">
                        @forelse($outstandingBookings as $booking)
                        <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/10 transition-colors">
                            <td class="px-6 py-3">
                                <p class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ $booking->user->name }}</p>
                                <p class="text-[10px] text-slate-400">{{ $booking->pet->name }}</p>
                            </td>
                            <td class="px-6 py-3">
                                <p class="text-sm font-bold text-orange-600">Rp {{ number_format($booking->remaining_amount, 0, ',', '.') }}</p>
                            </td>
                            <td class="px-6 py-3">
                                <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-purple-50 text-purple-600 border-purple-100 uppercase">{{ $booking->payment_type }}</span>
                            </td>
                            <td class="px-6 py-3 text-center">
                                <a href="{{ route('admin.bookings.show', $booking->id) }}" class="w-7 h-7 rounded-lg text-slate-400 hover:text-primary transition-all inline-flex items-center justify-center">
                                    <span class="material-symbols-outlined text-md">send</span>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-slate-400">No outstanding balance</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Recent Bookings Table -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 glass-card rounded-2xl overflow-hidden border border-slate-200/50">
        <div class="p-8 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Patient Logs</h3>
                <p class="text-xs text-slate-400 mt-0.5">Recent clinical activity</p>
            </div>
            <a href="{{ route('admin.bookings.index') }}" class="text-xs text-primary hover:text-emerald-600 font-medium">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-slate-800/30 text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                        <th class="px-8 py-4">Patient Profile</th>
                        <th class="px-8 py-4">Clinician</th>
                        <th class="px-8 py-4">Status</th>
                        <th class="px-8 py-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800/50">
                    @forelse($recentBookings as $booking)
                    <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/10 transition-colors">
                        <td class="px-8 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-[10px] border border-emerald-100">
                                    {{ substr($booking->pet->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ $booking->pet->name }}</p>
                                    <p class="text-[10px] text-slate-400">{{ $booking->user->name }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-4">
                            <div class="flex items-center gap-3">
                                @if($booking->doctor->photo)
                                    <img class="h-8 w-8 rounded-full object-cover" src="{{ asset('storage/' . $booking->doctor->photo) }}" alt="{{ $booking->doctor->name }}">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-[10px]">
                                        {{ substr($booking->doctor->name, 0, 1) }}
                                    </div>
                                @endif
                                <div>
                                    <p class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ $booking->doctor->name }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-4">
                            @php
                                $statusColors = [
                                    'pending' => ['bg-yellow-50 text-yellow-600 border-yellow-100', 'Pending'],
                                    'confirmed' => ['bg-blue-50 text-blue-600 border-blue-100', 'Scheduled'],
                                    'completed' => ['bg-emerald-50 text-emerald-600 border-emerald-100', 'Completed'],
                                    'cancelled' => ['bg-red-50 text-red-600 border-red-100', 'Cancelled'],
                                ];
                                $statusStyle = $statusColors[$booking->status] ?? ['bg-gray-50 text-gray-600 border-gray-100', ucfirst($booking->status)];
                            @endphp
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border {{ $statusStyle[0] }} uppercase">{{ $statusStyle[1] }}</span>
                        </td>
                        <td class="px-8 py-4 text-center">
                            <a href="{{ route('admin.bookings.show', $booking->id) }}" class="w-8 h-8 rounded-lg text-slate-400 hover:text-primary transition-all inline-flex items-center justify-center">
                                <span class="material-symbols-outlined text-md">visibility</span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-8 py-8 text-center text-slate-400">No recent bookings</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Action Center -->
    <div class="glass-card p-6 sm:p-8 rounded-2xl flex flex-col border border-slate-200/50">
        <div class="flex items-start justify-between gap-3 mb-6">
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Action Center</h3>
                <p class="text-xs text-slate-400 mt-0.5">What needs attention right now</p>
            </div>
            <span class="material-symbols-outlined text-slate-400" aria-hidden="true">bolt</span>
        </div>
        <div class="space-y-2 flex-1">
            <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}"
               class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 dark:border-slate-800 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                <span class="flex items-center gap-3 min-w-0">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">pending_actions</span>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold text-slate-700 dark:text-slate-200">Pending confirmations</span>
                        <span class="block text-[10px] text-slate-400">Bookings waiting for approval</span>
                    </span>
                </span>
                <span class="text-lg font-bold tabular-nums text-slate-900 dark:text-white">{{ $pendingBookings }}</span>
            </a>
            <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}"
               class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 dark:border-slate-800 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                <span class="flex items-center gap-3 min-w-0">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">schedule</span>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold text-slate-700 dark:text-slate-200">Payments processing</span>
                        <span class="block text-[10px] text-slate-400">Waiting for gateway result</span>
                    </span>
                </span>
                <span class="text-lg font-bold tabular-nums text-slate-900 dark:text-white">{{ $pendingPayment }}</span>
            </a>
            <a href="{{ route('admin.payments.index', ['status' => 'failed']) }}"
               class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 dark:border-slate-800 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                <span class="flex items-center gap-3 min-w-0">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">error</span>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold text-slate-700 dark:text-slate-200">Failed payments</span>
                        <span class="block text-[10px] text-slate-400">Needs a follow-up or retry</span>
                    </span>
                </span>
                <span class="text-lg font-bold tabular-nums text-slate-900 dark:text-white">{{ $failedPayments }}</span>
            </a>
        </div>
        <div class="pt-5 mt-5 border-t border-slate-100 dark:border-slate-800">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Outstanding balance</p>
                    <p class="text-sm font-bold text-slate-900 dark:text-white tabular-nums mt-1">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</p>
                </div>
                <a href="{{ route('admin.payments.index') }}"
                   class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-[11px] font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors">
                    Review
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">arrow_forward</span>
                </a>
            </div>
        </div>
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

        // Monthly Booking Chart (Bar)
        const monthlyBookingEl = document.getElementById('monthlyBookingChart');
        if (monthlyBookingEl) {
            new Chart(monthlyBookingEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: @json($monthlyBookingData['labels']),
                    datasets: [{
                        label: 'Bookings',
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

        // Monthly Revenue Chart (Line)
        const monthlyRevenueEl = document.getElementById('monthlyRevenueChart');
        if (monthlyRevenueEl) {
            new Chart(monthlyRevenueEl.getContext('2d'), {
                type: 'line',
                data: {
                    labels: @json($monthlyRevenueData['labels']),
                    datasets: [{
                        label: 'Revenue',
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
                                    return 'Rp ' + (value / 1000000).toFixed(1) + ' jt';
                                }
                            }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // Payment Status Chart (Doughnut)
        const paymentStatusEl = document.getElementById('paymentStatusChart');
        if (paymentStatusEl) {
            new Chart(paymentStatusEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Unpaid', 'Pending', 'DP Paid', 'Paid', 'Failed'],
                    datasets: [{
                        data: [
                            {{ $unpaidBookings }},
                            {{ $pendingPayment }},
                            {{ $dpPaidBookings }},
                            {{ $paidInFull }},
                            {{ $failedPayments }}
                        ],
                        backgroundColor: ['#9CA3AF', '#F59E0B', '#3B82F6', '#10B981', '#EF4444'],
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

        // Top Doctors Chart (Horizontal Bar)
        const topDoctorsEl = document.getElementById('topDoctorsChart');
        if (topDoctorsEl) {
            new Chart(topDoctorsEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: @json($popularDoctors['labels']),
                    datasets: [{
                        label: 'Bookings',
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
