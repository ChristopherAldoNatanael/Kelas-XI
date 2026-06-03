@extends('layouts.admin')

@section('title', 'Booking Details - PetHeal Admin')
@section('header', 'Booking Details')

@section('content')
<div class="max-w-4xl mx-auto">

    {{-- Flash: success --}}
    @if(session('success'))
    <div id="flash-success" class="mb-4 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-4 rounded-xl shadow-sm">
        <span class="material-symbols-outlined text-emerald-500 text-xl shrink-0">check_circle</span>
        <span class="font-medium text-sm">{{ session('success') }}</span>
        <button onclick="document.getElementById('flash-success').remove()" class="ml-auto text-emerald-400 hover:text-emerald-600">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>
    @endif

    {{-- Flash: error --}}
    @if(session('error'))
    <div id="flash-error" class="mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl shadow-sm">
        <span class="material-symbols-outlined text-red-500 text-xl shrink-0">error</span>
        <span class="font-medium text-sm">{{ session('error') }}</span>
        <button onclick="document.getElementById('flash-error').remove()" class="ml-auto text-red-400 hover:text-red-600">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>
    @endif

    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">

        {{-- ── Header ── --}}
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <div>
                <h2 class="text-md font-bold text-slate-900 dark:text-white">Booking #{{ $booking->id }}</h2>
                <p class="text-xs text-slate-400 mt-0.5">Created on {{ \Carbon\Carbon::parse($booking->created_at)->format('M d, Y H:i') }}</p>
            </div>
            @php
                $statusColors = [
                    'pending'   => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                    'confirmed' => 'bg-blue-100 text-blue-700 border-blue-200',
                    'completed' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                    'cancelled' => 'bg-red-100 text-red-700 border-red-200',
                ];
            @endphp
            <span class="px-2 py-0.5 text-[9px] font-bold rounded border {{ $statusColors[$booking->status] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                {{ ucfirst($booking->status) }}
            </span>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- ── Pet Information ── --}}
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-500">pets</span>
                    Pet Information
                </h3>
                <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                    <div class="flex items-center gap-4 mb-4">
                        @if($booking->pet->photo)
                            <img class="h-16 w-16 rounded-full object-cover ring-2 ring-emerald-200"
                                 src="{{ asset('storage/' . $booking->pet->photo) }}"
                                 alt="{{ $booking->pet->name }}"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="h-16 w-16 rounded-full bg-emerald-100 items-center justify-center" style="display:none">
                                <span class="material-symbols-outlined text-emerald-600 text-3xl">pets</span>
                            </div>
                        @else
                            <div class="h-16 w-16 rounded-full bg-emerald-100 flex items-center justify-center">
                                <span class="material-symbols-outlined text-emerald-600 text-3xl">pets</span>
                            </div>
                        @endif
                        <div>
                            <p class="font-semibold text-slate-800 dark:text-slate-200">{{ $booking->pet->name }}</p>
                            <p class="text-sm text-slate-500">{{ $booking->pet->species }}@if($booking->pet->breed) · {{ $booking->pet->breed }}@endif</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-slate-400">Age:</span>
                            <span class="text-slate-800 dark:text-slate-200 ml-1">{{ $booking->pet->age ? $booking->pet->age . ' yrs' : 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400">Gender:</span>
                            <span class="text-slate-800 dark:text-slate-200 ml-1">{{ $booking->pet->gender ? ucfirst($booking->pet->gender) : 'N/A' }}</span>
                        </div>
                    </div>
                    @if($booking->pet->notes)
                        <div class="mt-3 pt-3 border-t border-slate-200/50">
                            <span class="text-slate-400 text-sm">Notes:</span>
                            <p class="text-slate-700 dark:text-slate-300 text-sm mt-1">{{ $booking->pet->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ── Owner Information ── --}}
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-500">person</span>
                    Owner Information
                </h3>
                <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                    <div class="flex items-center gap-4 mb-4">
                        @if($booking->user->photo)
                            <img class="h-16 w-16 rounded-full object-cover ring-2 ring-blue-200"
                                 src="{{ asset('storage/' . $booking->user->photo) }}"
                                 alt="{{ $booking->user->name }}"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="h-16 w-16 rounded-full bg-blue-100 items-center justify-center" style="display:none">
                                <span class="material-symbols-outlined text-blue-600 text-3xl">person</span>
                            </div>
                        @else
                            <div class="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center">
                                <span class="material-symbols-outlined text-blue-600 text-3xl">person</span>
                            </div>
                        @endif
                        <div>
                            <p class="font-semibold text-slate-800 dark:text-slate-200">{{ $booking->user->name }}</p>
                            <p class="text-sm text-slate-500">{{ $booking->user->email }}</p>
                        </div>
                    </div>
                    <div class="text-sm">
                        <span class="text-slate-400">Phone:</span>
                        <span class="text-slate-800 dark:text-slate-200 ml-1">{{ $booking->user->phone ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>

            {{-- ── Doctor Information ── --}}
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-500">stethoscope</span>
                    Doctor Information
                </h3>
                <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                    <div class="flex items-center gap-4 mb-4">
                        @if($booking->doctor->photo)
                            <img class="h-16 w-16 rounded-full object-cover ring-2 ring-purple-200"
                                 src="{{ asset('storage/' . $booking->doctor->photo) }}"
                                 alt="{{ $booking->doctor->name }}"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="h-16 w-16 rounded-full bg-purple-100 items-center justify-center" style="display:none">
                                <span class="material-symbols-outlined text-purple-600 text-3xl">stethoscope</span>
                            </div>
                        @else
                            <div class="h-16 w-16 rounded-full bg-purple-100 flex items-center justify-center">
                                <span class="material-symbols-outlined text-purple-600 text-3xl">stethoscope</span>
                            </div>
                        @endif
                        <div>
                            <p class="font-semibold text-slate-800 dark:text-slate-200">{{ $booking->doctor->name }}</p>
                            <p class="text-sm text-slate-500">{{ $booking->doctor->specialization }}</p>
                        </div>
                    </div>
                    <div class="text-sm space-y-1">
                        <div>
                            <span class="text-slate-400">Phone:</span>
                            <span class="text-slate-800 dark:text-slate-200 ml-1">{{ $booking->doctor->phone ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400">Email:</span>
                            <span class="text-slate-800 dark:text-slate-200 ml-1">{{ $booking->doctor->email ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Appointment Details ── --}}
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-500">calendar_today</span>
                    Appointment Details
                </h3>
                <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Service:</span>
                            <span class="text-slate-800 dark:text-slate-200 font-medium">
                                {{ $booking->service?->name ?? 'N/A' }}
                            </span>
                        </div>
                        @if($booking->service?->price)
                            <div class="flex justify-between">
                                <span class="text-slate-400">Service Price:</span>
                                <span class="text-slate-800 dark:text-slate-200 font-medium">
                                    Rp {{ number_format($booking->service->price, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-slate-400">Date:</span>
                            <span class="text-slate-800 dark:text-slate-200 font-medium">
                                {{ \Carbon\Carbon::parse($booking->booking_date)->format('l, F j, Y') }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Time:</span>
                            <span class="text-slate-800 dark:text-slate-200 font-medium">{{ $booking->booking_time }}</span>
                        </div>
                        @if($booking->confirmed_at)
                            <div class="flex justify-between">
                                <span class="text-slate-400">Confirmed At:</span>
                                <span class="text-slate-700 dark:text-slate-300">
                                    {{ \Carbon\Carbon::parse($booking->confirmed_at)->format('M d, Y H:i') }}
                                </span>
                            </div>
                        @endif
                        @if($booking->completed_at)
                            <div class="flex justify-between">
                                <span class="text-slate-400">Completed At:</span>
                                <span class="text-slate-700 dark:text-slate-300">
                                    {{ \Carbon\Carbon::parse($booking->completed_at)->format('M d, Y H:i') }}
                                </span>
                            </div>
                        @endif
                        @if($booking->cancellation_reason)
                            <div class="pt-3 border-t border-slate-200/50">
                                <span class="text-slate-400">Cancellation Reason:</span>
                                <p class="text-red-600 mt-1">{{ $booking->cancellation_reason }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Additional Notes ── --}}
        @if($booking->notes)
            <div class="px-6 pb-6">
                <h3 class="text-md font-bold text-slate-900 dark:text-white mb-2">Additional Notes</h3>
                <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                    <p class="text-sm text-slate-700 dark:text-slate-300">{{ $booking->notes }}</p>
                </div>
            </div>
        @endif

        {{-- ── Payment Information ── --}}
        @if($booking->payment_type || $booking->total_amount > 0)
            <div class="px-6 pb-6">
                <h3 class="text-md font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-500">credit_card</span>
                    Payment Information
                </h3>
                <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                    @php
                        $paymentTypeColors = [
                            'full' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                            'dp'   => 'bg-amber-100 text-amber-700 border-amber-200',
                        ];
                        $paymentStatusColors = [
                            'paid'    => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                            'unpaid'  => 'bg-red-100 text-red-700 border-red-200',
                            'partial' => 'bg-amber-100 text-amber-700 border-amber-200',
                        ];
                    @endphp
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <span class="text-slate-400 text-sm">Payment Type</span>
                            <p class="mt-1">
                                @if($booking->payment_type)
                                    <span class="px-2 py-0.5 text-[9px] font-bold rounded border {{ $paymentTypeColors[$booking->payment_type] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                        {{ $booking->payment_type == 'dp' ? 'Down Payment (50%)' : 'Full Payment' }}
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400 text-sm">Total Amount</span>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-200">
                                @if($booking->total_amount > 0)
                                    Rp {{ number_format((float)$booking->total_amount, 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400 text-sm">Paid Amount</span>
                            <p class="mt-1 font-semibold text-emerald-600">
                                @if($booking->paid_amount > 0)
                                    Rp {{ number_format((float)$booking->paid_amount, 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400 text-sm">Payment Status</span>
                            <p class="mt-1">
                                @if($booking->payment_status)
                                    <span class="px-2 py-0.5 text-[9px] font-bold rounded border {{ $paymentStatusColors[$booking->payment_status] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                        {{ ucfirst($booking->payment_status) }}
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    @if($booking->payment_method)
                        <div class="mt-4 pt-4 border-t border-slate-200/50">
                            <span class="text-slate-400 text-sm">Payment Method:</span>
                            <span class="ml-2 text-slate-800 dark:text-slate-200">{{ $booking->payment_method }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ── Action Buttons ── --}}
        <div class="p-6 border-t border-slate-100 bg-slate-50/50 flex justify-between items-center flex-wrap gap-3">
            <a href="{{ route('admin.bookings.index') }}" class="flex items-center gap-2 text-slate-500 hover:text-slate-700 text-sm font-medium transition-colors">
                <span class="material-symbols-outlined text-base">arrow_back</span>Back to Bookings
            </a>

            <div class="flex gap-3 flex-wrap">
                @if($booking->status == 'pending')
                    <form method="POST" action="{{ route('admin.bookings.confirm', $booking->id) }}" class="inline">
                        @csrf
                        <button type="submit" class="bg-primary hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 transition-all">
                            <span class="material-symbols-outlined text-base">check</span>Confirm
                        </button>
                    </form>
                @endif

                @if(in_array($booking->status, ['pending', 'confirmed']))
                    <button type="button"
                        onclick="document.getElementById('reminder-modal').style.display='flex'"
                        class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 transition-all">
                        <span class="material-symbols-outlined text-base">notifications</span>Send Reminder
                    </button>

                    @if(!$booking->medicalRecord)
                        <a href="{{ route('admin.medical-records.create', $booking->id) }}"
                           class="bg-primary hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 transition-all">
                            <span class="material-symbols-outlined text-base">clinical_notes</span>Create Medical Record
                        </a>
                    @endif

                    <form method="POST" action="{{ route('admin.bookings.complete', $booking->id) }}" class="inline">
                        @csrf
                        <button type="submit" class="bg-primary hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 transition-all">
                            <span class="material-symbols-outlined text-base">task_alt</span>Complete
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Send Reminder Modal ── --}}
    <div id="reminder-modal" class="fixed inset-0 z-50 items-center justify-center bg-black/30 backdrop-blur-sm" style="display:none">
        <div class="bg-white/95 backdrop-blur rounded-2xl shadow-xl border border-slate-200/50 w-full max-w-md mx-4 overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-amber-100 rounded-xl p-2">
                        <span class="material-symbols-outlined text-amber-600 text-xl">notifications</span>
                    </div>
                    <div>
                        <h3 class="text-md font-bold text-slate-900">Send Reminder</h3>
                        <p class="text-xs text-slate-400 mt-0.5">to {{ $booking->user->name }}</p>
                    </div>
                </div>
                <button onclick="document.getElementById('reminder-modal').style.display='none'" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>

            <div class="px-6 pt-5 pb-2">
                <div class="bg-slate-50/50 rounded-xl p-4 flex items-center gap-4 mb-5">
                    <div class="bg-emerald-100 rounded-xl p-3">
                        <span class="material-symbols-outlined text-emerald-600 text-xl">pets</span>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800">{{ $booking->pet->name }}</p>
                        <p class="text-sm text-slate-500">with {{ $booking->doctor->name }}</p>
                        <p class="text-sm font-medium text-amber-600 mt-1 flex items-center gap-1 flex-wrap">
                            <span class="material-symbols-outlined text-sm">calendar_today</span>
                            {{ \Carbon\Carbon::parse($booking->booking_date)->format('D, d M Y') }}
                            &nbsp;·&nbsp;
                            <span class="material-symbols-outlined text-sm">schedule</span>
                            {{ $booking->booking_time }}
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.bookings.send-reminder', $booking->id) }}" id="reminder-form">
                    @csrf
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Choose reminder type:</p>
                    <div class="space-y-3 mb-4">
                        <label class="reminder-card flex items-center gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:border-amber-400 transition-colors">
                            <input type="radio" name="reminder_type" value="1_hour" class="accent-amber-500" onchange="toggleCustomMsg(this)">
                            <div>
                                <p class="font-medium text-sm text-slate-800">⏰ In 1 Hour</p>
                                <p class="text-xs text-slate-500">Appointment is TODAY in about 1 hour</p>
                            </div>
                        </label>
                        <label class="reminder-card flex items-center gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:border-amber-400 transition-colors">
                            <input type="radio" name="reminder_type" value="tomorrow" class="accent-amber-500" checked onchange="toggleCustomMsg(this)">
                            <div>
                                <p class="font-medium text-sm text-slate-800">📅 Tomorrow</p>
                                <p class="text-xs text-slate-500">Appointment is scheduled for tomorrow</p>
                            </div>
                        </label>
                        <label class="reminder-card flex items-center gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:border-amber-400 transition-colors">
                            <input type="radio" name="reminder_type" value="custom" class="accent-amber-500" onchange="toggleCustomMsg(this)">
                            <div>
                                <p class="font-medium text-sm text-slate-800">✏️ Custom Message</p>
                                <p class="text-xs text-slate-500">Write your own reminder message</p>
                            </div>
                        </label>
                    </div>

                    <div id="custom-msg-wrap" style="display:none" class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Custom Message</label>
                        <textarea name="custom_message" rows="3"
                            placeholder="e.g. Please remember to bring your pet's vaccination card..."
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-amber-500 focus:border-transparent text-sm resize-none"></textarea>
                    </div>

                    <div class="flex gap-3 pb-5">
                        <button type="button"
                            onclick="document.getElementById('reminder-modal').style.display='none'"
                            class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 py-2.5 rounded-xl text-sm font-semibold transition-all">
                            Cancel
                        </button>
                        <button type="submit"
                            class="flex-1 bg-amber-500 text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-amber-600 flex items-center justify-center gap-2 transition-all">
                            <span class="material-symbols-outlined text-base">send</span>Send Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleCustomMsg(radio) {
            document.getElementById('custom-msg-wrap').style.display = radio.value === 'custom' ? 'block' : 'none';
        }
        document.getElementById('reminder-modal').addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
        document.querySelectorAll('input[name="reminder_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.reminder-card').forEach(c => c.classList.remove('ring-2', 'ring-amber-500', 'bg-amber-50'));
                if (this.checked) {
                    this.closest('.reminder-card').classList.add('ring-2', 'ring-amber-500', 'bg-amber-50');
                }
            });
        });
    </script>

    {{-- ── Medical Record (if exists) ── --}}
    @if($booking->medicalRecord)
        <div class="mt-6 glass-card rounded-2xl overflow-hidden border border-slate-200/50">
            <div class="p-6 border-b border-slate-100">
                <h3 class="text-md font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-500">clinical_notes</span>
                    Medical Record
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Diagnosis</h4>
                        <p class="text-sm text-slate-700 dark:text-slate-300 bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-3">{{ $booking->medicalRecord->diagnosis }}</p>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Treatment</h4>
                        <p class="text-sm text-slate-700 dark:text-slate-300 bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-3">{{ $booking->medicalRecord->treatment }}</p>
                    </div>
                    @if($booking->medicalRecord->medicine)
                        <div>
                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Medicine</h4>
                            <p class="text-sm text-slate-700 dark:text-slate-300 bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-3">{{ $booking->medicalRecord->medicine }}</p>
                        </div>
                    @endif
                    @if($booking->medicalRecord->next_visit_date)
                        <div>
                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Next Visit</h4>
                            <p class="text-sm text-slate-700 dark:text-slate-300 bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-3">
                                {{ \Carbon\Carbon::parse($booking->medicalRecord->next_visit_date)->format('M d, Y') }}
                                @if($booking->medicalRecord->next_visit_time)
                                    at {{ $booking->medicalRecord->next_visit_time }}
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
                @if($booking->medicalRecord->notes)
                    <div class="mt-4">
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Notes</h4>
                        <p class="text-sm text-slate-700 dark:text-slate-300 bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-3">{{ $booking->medicalRecord->notes }}</p>
                    </div>
                @endif
                <div class="mt-4 pt-4 border-t border-slate-200/50">
                    <a href="{{ route('admin.medical-records.show', $booking->medicalRecord->id) }}"
                       class="text-primary hover:text-emerald-600 text-sm font-semibold flex items-center gap-1 transition-colors">
                        <span class="material-symbols-outlined text-base">visibility</span>View Full Medical Record
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
