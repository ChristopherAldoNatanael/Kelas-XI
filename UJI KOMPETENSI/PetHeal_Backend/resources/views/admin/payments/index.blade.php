@extends('layouts.admin')

@section('title', 'Payment Management')

@section('header', 'Payment Management')

@section('content')
{{-- Statistics Cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="glass-card p-5 rounded-xl border border-slate-200/50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                <span class="material-symbols-outlined text-blue-600">receipt_long</span>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Transactions</p>
                <p class="text-xl font-bold text-slate-900 dark:text-white">{{ $stats['total_transactions'] }}</p>
            </div>
        </div>
    </div>

    <div class="glass-card p-5 rounded-xl border border-emerald-200/50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                <span class="material-symbols-outlined text-emerald-600">trending_up</span>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Collected</p>
                <p class="text-xl font-bold text-emerald-600">Rp {{ number_format($stats['total_collected'], 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    <div class="glass-card p-5 rounded-xl border border-orange-200/50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-orange-50 dark:bg-orange-900/30 flex items-center justify-center">
                <span class="material-symbols-outlined text-orange-600">pending_actions</span>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Outstanding</p>
                <p class="text-xl font-bold text-orange-600">Rp {{ number_format($stats['total_outstanding'], 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    <div class="glass-card p-5 rounded-xl border border-purple-200/50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center">
                <span class="material-symbols-outlined text-purple-600">check_circle</span>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Fully Paid</p>
                <p class="text-xl font-bold text-purple-600">{{ $stats['paid_count'] }}</p>
            </div>
        </div>
    </div>
</div>

{{-- Filter Bar --}}
<div class="glass-card p-6 rounded-xl border border-slate-200/50 mb-8">
    <form method="GET" action="{{ route('admin.payments.index') }}" class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Customer, pet, or booking ID"
                   class="w-full px-4 py-2.5 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
        </div>

        <div class="w-40">
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Status</label>
            <select name="status" class="w-full px-4 py-2.5 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                <option value="">All Status</option>
                <option value="unpaid" {{ request('status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="dp_paid" {{ request('status') == 'dp_paid' ? 'selected' : '' }}>DP Paid</option>
                <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>Partial</option>
                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
        </div>

        <div class="w-40">
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Type</label>
            <select name="type" class="w-full px-4 py-2.5 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                <option value="">All Types</option>
                <option value="dp" {{ request('type') == 'dp' ? 'selected' : '' }}>Down Payment</option>
                <option value="full" {{ request('type') == 'full' ? 'selected' : '' }}>Full Payment</option>
            </select>
        </div>

        <div class="w-40">
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Method</label>
            <select name="method" class="w-full px-4 py-2.5 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                <option value="">All Methods</option>
                <option value="midtrans" {{ request('method') == 'midtrans' ? 'selected' : '' }}>Midtrans</option>
                <option value="cash" {{ request('method') == 'cash' ? 'selected' : '' }}>Cash</option>
                <option value="bank_transfer" {{ request('method') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-emerald-600 transition-colors">
                Filter
            </button>
            <a href="{{ route('admin.payments.index') }}" class="px-6 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-lg text-sm font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                Reset
            </a>
        </div>
    </form>
</div>

{{-- Payments Table --}}
<div class="glass-card rounded-xl border border-slate-200/50 overflow-hidden">
    <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div>
            <h3 class="text-md font-bold text-slate-900 dark:text-white">Payment Transactions</h3>
            <p class="text-xs text-slate-400 mt-1">{{ $payments->total() }} total transactions</p>
        </div>
        <a href="{{ route('admin.payments.export') }}" target="_blank" class="px-4 py-2 bg-emerald-700 text-white rounded-lg text-sm font-semibold hover:bg-emerald-800 transition-colors flex items-center gap-1">
            <span class="material-symbols-outlined text-[18px]">print</span>Export PDF
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-slate-800/30 text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <th class="px-6 py-4">Booking ID</th>
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Total Amount</th>
                    <th class="px-6 py-4">Paid</th>
                    <th class="px-6 py-4">Remaining</th>
                    <th class="px-6 py-4">Type</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800/50">
                @forelse($payments as $payment)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/10 transition-colors">
                    <td class="px-6 py-4">
                        <span class="text-sm font-mono font-semibold text-slate-600 dark:text-slate-400">#{{ $payment->id }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <p class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ $payment->user->name }}</p>
                        <p class="text-[10px] text-slate-400">{{ $payment->pet->name }}</p>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Rp {{ number_format($payment->total_amount, 0, ',', '.') }}</p>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm font-semibold text-emerald-600">Rp {{ number_format($payment->paid_amount, 0, ',', '.') }}</p>
                    </td>
                    <td class="px-6 py-4">
                        @if($payment->remaining_amount > 0)
                            <p class="text-sm font-bold text-orange-600">Rp {{ number_format($payment->remaining_amount, 0, ',', '.') }}</p>
                        @else
                            <span class="text-xs text-slate-400">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($payment->payment_type === 'dp')
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-blue-50 text-blue-600 border-blue-100">DP</span>
                        @else
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-emerald-50 text-emerald-600 border-emerald-100">Full</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $statusColors = [
                                'paid' => ['bg-emerald-50 text-emerald-600 border-emerald-100', 'Paid'],
                                'dp_paid' => ['bg-blue-50 text-blue-600 border-blue-100', 'DP Paid'],
                                'pending' => ['bg-yellow-50 text-yellow-600 border-yellow-100', 'Pending'],
                                'partial' => ['bg-orange-50 text-orange-600 border-orange-100', 'Partial'],
                                'failed' => ['bg-red-50 text-red-600 border-red-100', 'Failed'],
                                'unpaid' => ['bg-gray-50 text-gray-600 border-gray-100', 'Unpaid'],
                            ];
                            $style = $statusColors[$payment->payment_status] ?? ['bg-gray-50 text-gray-600 border-gray-100', ucfirst($payment->payment_status)];
                        @endphp
                        <span class="px-2 py-0.5 text-[9px] font-bold rounded border {{ $style[0] }}">{{ $style[1] }}</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-1">
                            <a href="{{ route('admin.payments.show', $payment->id) }}" class="w-8 h-8 rounded-lg text-slate-400 hover:text-primary transition-all inline-flex items-center justify-center" title="View Details">
                                <span class="material-symbols-outlined text-md">visibility</span>
                            </a>
                            @if($payment->payment_status !== 'paid' && $payment->remaining_amount > 0)
                            <form method="POST" action="{{ route('admin.payments.send-reminder', $payment->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="w-8 h-8 rounded-lg text-slate-400 hover:text-blue-600 transition-all inline-flex items-center justify-center" title="Send Reminder" onclick="return confirm('Send payment reminder to customer?')">
                                    <span class="material-symbols-outlined text-md">send</span>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center">
                            <span class="material-symbols-outlined text-slate-300 text-5xl mb-4">receipt_long</span>
                            <p class="text-slate-400 text-sm">No payment transactions found</p>
                            <p class="text-slate-300 text-xs mt-1">Try adjusting your filters</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($payments->hasPages())
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800">
        {{ $payments->links() }}
    </div>
    @endif
</div>

{{-- Success/Error Messages --}}
@if(session('success'))
<div id="successMessage" class="fixed top-6 right-6 z-50 bg-emerald-500 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 animate-slide-in">
    <span class="material-symbols-outlined">check_circle</span>
    <p class="font-semibold text-sm">{{ session('success') }}</p>
</div>
<script>
    setTimeout(() => {
        const msg = document.getElementById('successMessage');
        if (msg) msg.style.display = 'none';
    }, 4000);
</script>
@endif

@if(session('error'))
<div id="errorMessage" class="fixed top-6 right-6 z-50 bg-red-500 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 animate-slide-in">
    <span class="material-symbols-outlined">error</span>
    <p class="font-semibold text-sm">{{ session('error') }}</p>
</div>
<script>
    setTimeout(() => {
        const msg = document.getElementById('errorMessage');
        if (msg) msg.style.display = 'none';
    }, 4000);
</script>
@endif

<style>
    @keyframes slide-in {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    .animate-slide-in {
        animation: slide-in 0.3s ease-out;
    }
</style>
@endsection
