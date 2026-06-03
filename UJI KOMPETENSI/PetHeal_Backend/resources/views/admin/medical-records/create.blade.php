@extends('layouts.admin')

@section('title', 'Create Medical Record - PetHeal Admin')
@section('header', 'Create Medical Record')

@section('content')
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
        <div>
            <h2 class="text-md font-bold text-slate-900 dark:text-white">New Medical Record</h2>
            <p class="text-xs text-slate-400 mt-0.5">Create a new medical record for the booking</p>
        </div>
        <a href="{{ route('admin.bookings.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 transition-all">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Back to Bookings
        </a>
    </div>

    <div class="p-6">
        <!-- Booking Info -->
        <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-6 mb-8">
            <h3 class="text-[9px] font-bold text-slate-400 uppercase tracking-[0.15em] mb-4">Booking Information</h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">Pet</p>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->pet->name }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">Owner</p>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->user->name }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">Doctor</p>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->doctor->name }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">Date</p>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ \Carbon\Carbon::parse($booking->booking_date)->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">Time</p>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ \Carbon\Carbon::parse($booking->booking_time)->format('H:i') }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">Total Amount</p>
                    <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400">Rp {{ number_format($booking->total_amount ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.medical-records.store') }}">
            @csrf
            <input type="hidden" name="booking_id" value="{{ $booking->id }}">

            <div class="space-y-6">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Diagnosis *</label>
                    <textarea name="diagnosis" required rows="3"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                        placeholder="Enter diagnosis">{{ old('diagnosis') }}</textarea>
                    @error('diagnosis')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Treatment *</label>
                    <textarea name="treatment" required rows="3"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                        placeholder="Enter treatment">{{ old('treatment') }}</textarea>
                    @error('treatment')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Medicine</label>
                    <textarea name="medicine" rows="2"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                        placeholder="Enter medicines prescribed">{{ old('medicine') }}</textarea>
                </div>

                <!-- Cost Fields -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Consultation Cost</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                            <input type="number" name="cost" min="0" value="{{ $booking->total_amount ?? 0 }}"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm pl-12"
                                placeholder="0">
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Auto-filled from booking consultation fee</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Treatment Cost</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                            <input type="number" name="treatment_cost" min="0" value="0"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm pl-12"
                                placeholder="0">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Medicine Cost</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                            <input type="number" name="medicine_cost" min="0" value="0"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm pl-12"
                                placeholder="0">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Notes</label>
                    <textarea name="notes" rows="2"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                        placeholder="Additional notes">{{ old('notes') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Next Visit Date</label>
                        <input type="date" name="next_visit_date"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                            min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Next Visit Time</label>
                        <input type="time" name="next_visit_time"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('admin.bookings.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-6 py-2.5 rounded-xl text-sm font-semibold transition-all">
                    Cancel
                </a>
                <button type="submit" class="bg-primary hover:bg-emerald-600 text-white px-6 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 transition-all">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Save Record
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
