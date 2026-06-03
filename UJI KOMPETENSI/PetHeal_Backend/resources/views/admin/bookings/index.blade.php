@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $bookings */
@endphp
@extends('layouts.admin')

@section('title', 'Bookings - PetHeal Admin')
@section('header', 'Bookings Management')

@section('content')
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <!-- Filters -->
    <div class="p-6 border-b border-slate-100 dark:border-slate-800">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Status</label>
                <select name="status" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm dark:text-slate-200">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Date</label>
                <input type="date" name="date" value="{{ request('date') }}"
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm dark:text-slate-200">
            </div>

            <button type="submit" class="bg-primary hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">filter_list</span>Filter
            </button>

            @if(request()->hasAny(['status', 'date']))
                <a href="{{ route('admin.bookings.index') }}" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-1">
                    <span class="material-symbols-outlined text-[18px]">close</span>Clear
                </a>
            @endif

            <a href="{{ route('admin.bookings.export') }}" target="_blank" class="bg-primary hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">print</span>Export PDF
            </a>
        </form>
    </div>

    <!-- Bookings Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50/50 dark:bg-slate-800/30">
                <tr>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">ID</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">Pet</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">Owner</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">Doctor</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">Date & Time</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">Payment</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">Status</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800/50">
                @forelse($bookings as $booking)
                    <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/20 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">#{{ $booking->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                @if($booking->pet->photo)
                                    <img class="h-8 w-8 rounded-full object-cover"
                                         src="{{ asset('storage/' . $booking->pet->photo) }}"
                                         alt="{{ $booking->pet->name }}">
                                @else
                                    <div class="h-8 w-8 rounded-full bg-emerald-100 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-emerald-600 text-[16px]">pets</span>
                                    </div>
                                @endif
                                <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->pet->name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ $booking->user->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                @if($booking->doctor->photo)
                                    <img class="h-8 w-8 rounded-full object-cover"
                                         src="{{ asset('storage/' . $booking->doctor->photo) }}"
                                         alt="{{ $booking->doctor->name }}">
                                @else
                                    <div class="h-8 w-8 rounded-full bg-purple-100 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-purple-600 text-[16px]">stethoscope</span>
                                    </div>
                                @endif
                                <span class="text-sm text-slate-500 dark:text-slate-400">{{ $booking->doctor->name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                            {{ $booking->service?->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                            @if($booking->service?->price)
                                Rp {{ number_format($booking->service->price, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ \Carbon\Carbon::parse($booking->booking_date)->format('M d, Y') }}<br>
                            <span class="text-xs dark:text-slate-500">{{ \Carbon\Carbon::parse($booking->booking_time)->format('H:i') }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($booking->payment_type)
                                @php
                                    $paymentTypeColors = [
                                        'full' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                        'dp' => 'bg-amber-100 text-amber-700 border-amber-200',
                                    ];
                                    $paymentStatusColors = [
                                        'paid' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                        'unpaid' => 'bg-red-100 text-red-700 border-red-200',
                                        'partial' => 'bg-amber-100 text-amber-700 border-amber-200',
                                    ];
                                @endphp
                                <div class="text-xs">
                                    <span class="px-2 py-0.5 text-[9px] font-bold rounded border {{ $paymentTypeColors[$booking->payment_type] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                        {{ $booking->payment_type == 'dp' ? 'Down Payment' : 'Full Payment' }}
                                    </span>
                                </div>
                                @if($booking->total_amount > 0)
                                    <div class="text-xs mt-1 text-slate-500">
                                        Rp {{ number_format($booking->total_amount, 0, ',', '.') }}
                                        @if($booking->paid_amount > 0)
                                            <span class="text-emerald-600">({{ number_format($booking->paid_amount, 0, ',', '.') }})</span>
                                        @endif
                                    </div>
                                @endif
                            @else
                                <span class="text-xs text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                    'confirmed' => 'bg-blue-100 text-blue-700 border-blue-200',
                                    'completed' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                    'cancelled' => 'bg-red-100 text-red-700 border-red-200',
                                ];
                            @endphp
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border {{ $statusColors[$booking->status] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                {{ ucfirst($booking->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                    class="text-slate-400 hover:text-primary transition-colors" title="View">
                                    <span class="material-symbols-outlined text-[20px]">visibility</span>
                                </a>

                                @if($booking->status == 'pending')
                                    <form method="POST" action="{{ route('admin.bookings.confirm', $booking->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-slate-400 hover:text-primary transition-colors" title="Confirm">
                                            <span class="material-symbols-outlined text-[20px]">check_circle</span>
                                        </button>
                                    </form>
                                @endif

                                @if(in_array($booking->status, ['pending', 'confirmed']))
                                    <form method="POST" action="{{ route('admin.bookings.complete', $booking->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-slate-400 hover:text-primary transition-colors" title="Complete">
                                            <span class="material-symbols-outlined text-[20px]">task_alt</span>
                                        </button>
                                    </form>

                                    <button onclick="showCancelModal({{ $booking->id }})"
                                        class="text-slate-400 hover:text-red-500 transition-colors" title="Cancel">
                                        <span class="material-symbols-outlined text-[20px]">cancel</span>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="flex flex-col items-center justify-center py-12 text-slate-400 dark:text-slate-500">
                                <span class="material-symbols-outlined text-[48px] mb-3">calendar_today</span>
                                <p class="text-sm text-slate-500 dark:text-slate-400">No bookings found</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800">
        {{ $bookings->links() }}
    </div>
</div>

<!-- Cancel Modal -->
<div id="cancelModal" style="display:none" class="fixed inset-0 bg-black/30 backdrop-blur-sm overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 w-full max-w-md">
        <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur rounded-2xl shadow-xl border border-slate-200/50 dark:border-slate-700 overflow-hidden">
            <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-red-500 text-[24px]">cancel</span>
                <h3 class="text-md font-bold text-slate-900 dark:text-white">Cancel Booking</h3>
            </div>
            <form id="cancelForm" method="POST" class="p-6">
                @csrf
                <input type="hidden" name="_method" value="POST">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Cancellation Reason <span class="text-red-500">*</span></label>
                    <textarea name="reason" required rows="3"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm dark:text-slate-200"
                        placeholder="Enter reason for cancellation..."></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="hideCancelModal()"
                        class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 px-4 py-2 rounded-xl text-sm font-semibold">
                        Close
                    </button>
                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-semibold">
                        Confirm Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function showCancelModal(bookingId) {
        document.getElementById('cancelForm').action = '/admin/bookings/' + bookingId + '/cancel';
        document.getElementById('cancelModal').style.display = 'block';
    }

    function hideCancelModal() {
        document.getElementById('cancelModal').style.display = 'none';
    }

    document.getElementById('cancelModal').addEventListener('click', function(e) {
        if (e.target === this) hideCancelModal();
    });
</script>
@endsection
