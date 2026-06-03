@extends('layouts.admin')

@section('title', 'Payment Detail #' . $booking->id)

@section('header', 'Payment Detail #' . $booking->id)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.payments.index') }}" class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
            <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">arrow_back</span>
        </a>
        <div>
            <p class="text-sm text-slate-500">Midtrans transaction & booking payment info</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        @if($booking->payment_status !== 'paid' && $booking->remaining_amount > 0)
        <form method="POST" action="{{ route('admin.payments.send-reminder', $booking->id) }}">
            @csrf
            <button type="submit" class="px-4 py-2 bg-primary hover:bg-emerald-600 text-white rounded-xl text-sm font-semibold transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-md">send</span>
                Send Reminder
            </button>
        </form>
        @endif
    </div>
</div>

@section('content')
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    {{-- Left Column: Payment Info --}}
    <div class="lg:col-span-2 space-y-8">
        {{-- Payment Overview Card --}}
        <div class="glass-card p-8 rounded-2xl border border-slate-200/50">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary text-2xl">payments</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Payment Information</h3>
                        <p class="text-xs text-slate-400">Midtrans transaction details</p>
                    </div>
                </div>
                @php
                    $statusColors = [
                        'paid' => ['bg-emerald-100 text-emerald-700 border-emerald-200', 'Paid'],
                        'dp_paid' => ['bg-blue-100 text-blue-700 border-blue-200', 'DP Paid'],
                        'pending' => ['bg-yellow-100 text-yellow-700 border-yellow-200', 'Pending'],
                        'partial' => ['bg-orange-100 text-orange-700 border-orange-200', 'Partial'],
                        'failed' => ['bg-red-100 text-red-700 border-red-200', 'Failed'],
                        'unpaid' => ['bg-slate-100 text-slate-700 border-slate-200', 'Unpaid'],
                    ];
                    $style = $statusColors[$booking->payment_status] ?? ['bg-slate-100 text-slate-700 border-slate-200', ucfirst($booking->payment_status)];
                @endphp
                <span class="px-4 py-2 text-sm font-bold rounded-xl border {{ $style[0] }}">{{ $style[1] }}</span>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-5">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Total Amount</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">Rp {{ number_format($booking->total_amount, 0, ',', '.') }}</p>
                </div>
                <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-5">
                    <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-2">Amount Paid</p>
                    <p class="text-2xl font-bold text-emerald-600">Rp {{ number_format($booking->paid_amount, 0, ',', '.') }}</p>
                </div>
                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-xl p-5">
                    <p class="text-[10px] font-bold text-orange-600 uppercase tracking-wider mb-2">Remaining</p>
                    <p class="text-2xl font-bold text-orange-600">Rp {{ number_format($booking->remaining_amount, 0, ',', '.') }}</p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-5">
                    <p class="text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-2">Payment Type</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $booking->payment_type === 'dp' ? 'Down Payment' : 'Full Payment' }}</p>
                </div>
            </div>

            {{-- Payment Progress Bar --}}
            <div class="mt-6">
                <div class="flex justify-between items-center mb-2">
                    <p class="text-xs font-semibold text-slate-500">Payment Progress</p>
                    <p class="text-xs font-bold text-primary">{{ $paymentProgress }}%</p>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-800 h-3 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-primary to-emerald-400 rounded-full transition-all duration-500" style="width: {{ $paymentProgress }}%"></div>
                </div>
            </div>

            {{-- Payment Details --}}
            <div class="mt-6 pt-6 border-t border-slate-100 dark:border-slate-800 grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Payment Method</p>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 mt-1 capitalize">{{ $booking->payment_method ?? 'Not specified' }}</p>
                </div>
                @if($booking->payment_type === 'dp' && $booking->dp_amount > 0)
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">DP Amount Required</p>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 mt-1">Rp {{ number_format($booking->dp_amount, 0, ',', '.') }}</p>
                </div>
                @endif
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Booking Date</p>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 mt-1">{{ $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') : '-' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Booking Status</p>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 mt-1 capitalize">{{ $booking->status }}</p>
                </div>
            </div>
        </div>

        {{-- Admin Action Card --}}
        @if($booking->payment_status !== 'paid')
        <div class="glass-card p-8 rounded-2xl border border-slate-200/50">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                    <span class="material-symbols-outlined text-amber-600">edit_note</span>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Admin Payment Action</h3>
                    <p class="text-xs text-slate-400">Manually confirm or adjust payment</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.payments.confirm', $booking->id) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Payment Amount (Rp)</label>
                        <input type="number" name="amount" value="{{ $booking->remaining_amount }}" min="0" max="{{ $booking->remaining_amount }}"
                               class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Notes (Optional)</label>
                        <input type="text" name="notes" placeholder="Reason for adjustment..."
                               class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all">
                    </div>
                </div>
                <button type="submit" class="w-full py-3 bg-primary hover:bg-emerald-600 text-white rounded-xl text-sm font-semibold transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-md">check_circle</span>
                    Confirm Payment
                </button>
            </form>
        </div>
        @endif
    </div>

    {{-- Right Column: Customer & Booking Info --}}
    <div class="space-y-8">
        {{-- Customer Card --}}
        <div class="glass-card p-6 rounded-2xl border border-slate-200/50">
            <div class="flex items-center gap-3 mb-4">
                <span class="material-symbols-outlined text-slate-400">person</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">Customer Information</h4>
            </div>
            <div class="space-y-3">
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Name</p>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $booking->user->name }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Email</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $booking->user->email }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Phone</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $booking->user->phone ?? '-' }}</p>
                </div>
            </div>
        </div>

        {{-- Pet Card --}}
        <div class="glass-card p-6 rounded-2xl border border-slate-200/50">
            <div class="flex items-center gap-3 mb-4">
                <span class="material-symbols-outlined text-slate-400">pets</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">Pet Information</h4>
            </div>
            <div class="space-y-3">
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Pet Name</p>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $booking->pet->name }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Species</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400 capitalize">{{ $booking->pet->species ?? '-' }}</p>
                </div>
            </div>
        </div>

        {{-- Doctor Card --}}
        <div class="glass-card p-6 rounded-2xl border border-slate-200/50">
            <div class="flex items-center gap-3 mb-4">
                <span class="material-symbols-outlined text-slate-400">medical_services</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">Doctor Information</h4>
            </div>
            <div class="space-y-3">
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Doctor Name</p>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $booking->doctor->name }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Specialization</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $booking->doctor->specialization }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Schedule</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') : '-' }} at {{ $booking->booking_time }}</p>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="glass-card p-6 rounded-2xl border border-slate-200/50">
            <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Quick Links</h4>
            <div class="space-y-2">
                <a href="{{ route('admin.bookings.show', $booking->id) }}" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                    <span class="material-symbols-outlined text-slate-400 text-lg">event_note</span>
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">View Booking Details</span>
                </a>
                @if($booking->medicalRecords && $booking->medicalRecords->count() > 0)
                <a href="{{ route('admin.medical-records.show', $booking->medicalRecords->first()->id) }}" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                    <span class="material-symbols-outlined text-slate-400 text-lg">clinical_notes</span>
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">View Medical Record</span>
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

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