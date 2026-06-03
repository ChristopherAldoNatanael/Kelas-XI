@extends('layouts.admin')

@section('title', 'Medical Record Details - PetHeal Admin')
@section('header', 'Medical Record Details')

@section('content')
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
        <div>
            <h2 class="text-md font-bold text-slate-900 dark:text-white">Record #{{ $record->id }}</h2>
            <p class="text-xs text-slate-400 mt-0.5">Medical record details</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.medical-records.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-1 transition-all">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>Back
            </a>
            <a href="{{ route('admin.medical-records.edit', $record->id) }}" class="bg-primary hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-1 transition-all">
                <span class="material-symbols-outlined text-[18px]">edit</span>Edit
            </a>
        </div>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Pet Info -->
            <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Pet Information</h3>
                <div class="flex items-center gap-3 mb-3">
                    @if($record->pet->photo)
                        <img class="h-12 w-12 rounded-full object-cover"
                             src="{{ asset('storage/' . $record->pet->photo) }}"
                             alt="{{ $record->pet->name }}">
                    @else
                        <div class="h-12 w-12 rounded-full bg-emerald-100 flex items-center justify-center">
                            <span class="material-symbols-outlined text-emerald-600 text-[24px]">pets</span>
                        </div>
                    @endif
                    <div>
                        <p class="text-lg font-semibold text-slate-800 dark:text-slate-200">{{ $record->pet->name }}</p>
                        <p class="text-sm text-slate-500">{{ $record->pet->species ?? 'Pet' }} - {{ $record->pet->breed ?? '' }}</p>
                    </div>
                </div>
            </div>

            <!-- Owner Info -->
            <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Owner Information</h3>
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-blue-600 text-[24px]">person</span>
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-slate-800 dark:text-slate-200">{{ $record->booking->user->name ?? 'N/A' }}</p>
                        <p class="text-sm text-slate-500">{{ $record->booking->user->email ?? '' }}</p>
                    </div>
                </div>
            </div>

            <!-- Doctor Info -->
            <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Doctor</h3>
                <div class="flex items-center gap-3">
                    @if($record->doctor->photo)
                        <img class="h-12 w-12 rounded-full object-cover"
                             src="{{ asset('storage/' . $record->doctor->photo) }}"
                             alt="{{ $record->doctor->name }}">
                    @else
                        <div class="h-12 w-12 rounded-full bg-purple-100 flex items-center justify-center">
                            <span class="material-symbols-outlined text-purple-600 text-[24px]">stethoscope</span>
                        </div>
                    @endif
                    <div>
                        <p class="text-lg font-semibold text-slate-800 dark:text-slate-200">{{ $record->doctor->name }}</p>
                        <p class="text-sm text-slate-500">{{ $record->doctor->specialization ?? 'Veterinarian' }}</p>
                    </div>
                </div>
            </div>

            <!-- Booking Info -->
            <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Booking Date</h3>
                <p class="text-lg font-semibold text-slate-800 dark:text-slate-200">{{ \Carbon\Carbon::parse($record->booking->booking_date)->format('M d, Y') }}</p>
                <p class="text-sm text-slate-500">Time: {{ \Carbon\Carbon::parse($record->booking->booking_time)->format('H:i') }}</p>
            </div>
        </div>

        <!-- Cost Summary -->
        <div class="mt-6 bg-emerald-50/50 rounded-xl p-4 border border-emerald-200/50">
            <h3 class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-3">Treatment Costs</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-slate-500">Consultation Cost</p>
                    <p class="text-lg font-semibold text-slate-800 dark:text-slate-200">Rp {{ number_format($record->cost ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Treatment Cost</p>
                    <p class="text-lg font-semibold text-slate-800 dark:text-slate-200">Rp {{ number_format($record->treatment_cost ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Medicine Cost</p>
                    <p class="text-lg font-semibold text-slate-800 dark:text-slate-200">Rp {{ number_format($record->medicine_cost ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
            <div class="mt-3 pt-3 border-t border-emerald-200/50">
                <div class="flex justify-between items-center">
                    <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Total Cost</p>
                    <p class="text-xl font-bold text-emerald-600">Rp {{ number_format(($record->cost ?? 0) + ($record->treatment_cost ?? 0) + ($record->medicine_cost ?? 0), 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <!-- Medical Details -->
        <div class="mt-6 space-y-4">
            <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Diagnosis</h3>
                <p class="text-sm text-slate-700 dark:text-slate-300">{{ $record->diagnosis }}</p>
            </div>

            <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Treatment</h3>
                <p class="text-sm text-slate-700 dark:text-slate-300">{{ $record->treatment }}</p>
            </div>

            @if($record->medicine)
            <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Medicine</h3>
                <p class="text-sm text-slate-700 dark:text-slate-300">{{ $record->medicine }}</p>
            </div>
            @endif

            @if($record->notes)
            <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Notes</h3>
                <p class="text-sm text-slate-700 dark:text-slate-300">{{ $record->notes }}</p>
            </div>
            @endif

            @if($record->next_visit_date)
            <div class="bg-amber-50/50 rounded-xl p-4 border border-amber-200/50">
                <h3 class="text-xs font-bold text-amber-600 uppercase tracking-wider mb-2">Next Visit Scheduled</h3>
                <p class="text-sm font-semibold text-amber-800">
                    {{ \Carbon\Carbon::parse($record->next_visit_date)->format('M d, Y') }}
                    @if($record->next_visit_time)
                        at {{ \Carbon\Carbon::parse($record->next_visit_time)->format('H:i') }}
                    @endif
                </p>
            </div>
            @endif
        </div>

        <div class="mt-6 pt-4 border-t border-slate-200/50">
            <p class="text-xs text-slate-400">
                Created: {{ $record->created_at->format('M d, Y H:i') }}
                @if($record->updated_at->ne($record->created_at))
                    | Updated: {{ $record->updated_at->format('M d, Y H:i') }}
                @endif
            </p>
        </div>
    </div>
</div>
@endsection
