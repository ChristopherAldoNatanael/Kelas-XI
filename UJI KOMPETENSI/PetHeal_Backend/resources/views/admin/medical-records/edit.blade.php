@extends('layouts.admin')

@section('title', 'Edit Medical Record - PetHeal Admin')
@section('header', 'Edit Medical Record')

@section('content')
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
        <div>
            <h2 class="text-md font-bold text-slate-900 dark:text-white">Edit Medical Record #{{ $record->id }}</h2>
            <p class="text-xs text-slate-400 mt-0.5">Update medical record information</p>
        </div>
        <a href="{{ route('admin.medical-records.show', $record->id) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 transition-all">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Back
        </a>
    </div>

    <div class="p-6">
        <!-- Booking Info -->
        <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-6 mb-8">
            <h3 class="text-[9px] font-bold text-slate-400 uppercase tracking-[0.15em] mb-4">Booking Information</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">Pet</p>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $record->pet->name }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">Owner</p>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $record->booking->user->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">Doctor</p>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $record->doctor->name }}</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.medical-records.update', $record->id) }}">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Diagnosis *</label>
                    <textarea name="diagnosis" required rows="3"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                        placeholder="Enter diagnosis">{{ old('diagnosis', $record->diagnosis) }}</textarea>
                    @error('diagnosis')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Treatment *</label>
                    <textarea name="treatment" required rows="3"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                        placeholder="Enter treatment">{{ old('treatment', $record->treatment) }}</textarea>
                    @error('treatment')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Medicine</label>
                    <textarea name="medicine" rows="2"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                        placeholder="Enter medicines prescribed">{{ old('medicine', $record->medicine) }}</textarea>
                </div>

                <!-- Cost Fields -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Consultation Cost</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                            <input type="number" name="cost" min="0" value="{{ old('cost', $record->cost ?? 0) }}"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm pl-12"
                                placeholder="0">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Treatment Cost</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                            <input type="number" name="treatment_cost" min="0" value="{{ old('treatment_cost', $record->treatment_cost ?? 0) }}"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm pl-12"
                                placeholder="0">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Medicine Cost</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                            <input type="number" name="medicine_cost" min="0" value="{{ old('medicine_cost', $record->medicine_cost ?? 0) }}"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm pl-12"
                                placeholder="0">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Notes</label>
                    <textarea name="notes" rows="2"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                        placeholder="Additional notes">{{ old('notes', $record->notes) }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Next Visit Date</label>
                        <input type="date" name="next_visit_date" value="{{ old('next_visit_date', $record->next_visit_date ? \Carbon\Carbon::parse($record->next_visit_date)->format('Y-m-d') : '') }}"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Next Visit Time</label>
                        <input type="time" name="next_visit_time" value="{{ old('next_visit_time', $record->next_visit_time ? \Carbon\Carbon::parse($record->next_visit_time)->format('H:i') : '') }}"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('admin.medical-records.show', $record->id) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-6 py-2.5 rounded-xl text-sm font-semibold transition-all">
                    Cancel
                </a>
                <button type="submit" class="bg-primary hover:bg-emerald-600 text-white px-6 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 transition-all">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Update Record
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
