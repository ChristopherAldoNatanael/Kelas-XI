@extends('layouts.admin')

@section('title', __('admin.new_record') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.new_record'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('admin.new_record')"
        :eyebrow="__('admin.documentation_clinical')"
        icon="clinical_notes"
        :description="__('admin.new_record_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.bookings.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back_to_bookings') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.panel :title="__('admin.booking_information')">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.pet') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->pet->name ?? __('admin.pet_placeholder') }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.owner') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->user->name ?? __('admin.unknown_owner') }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.doctor') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->doctor->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('common.date') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">
                    {{ \Carbon\Carbon::parse($booking->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                </p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('common.time') }}</p>
                <p class="mt-1 text-sm font-medium tabular-nums text-slate-800 dark:text-slate-200">
                    {{ \Carbon\Carbon::parse($booking->booking_time)->format('H:i') }} WIB
                </p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.col_total_amount') }}</p>
                <p class="mt-1 text-sm font-medium tabular-nums text-emerald-600 dark:text-emerald-400">
                    Rp {{ number_format($booking->total_amount ?? 0, 0, ',', '.') }}
                </p>
            </div>
        </div>
    </x-admin.panel>

    <x-admin.panel :title="__('admin.documentation_clinical')">
        <form method="POST" action="{{ route('admin.medical-records.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="booking_id" value="{{ $booking->id }}">

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <label for="diagnosis" class="admin-label">{{ __('admin.diagnosis') }} <span class="text-rose-500">*</span></label>
                    <textarea name="diagnosis" id="diagnosis" required rows="3" class="admin-input"
                              placeholder="{{ __('admin.diagnosis_placeholder') }}">{{ old('diagnosis') }}</textarea>
                    @error('diagnosis') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="treatment" class="admin-label">{{ __('admin.treatment') }} <span class="text-rose-500">*</span></label>
                    <textarea name="treatment" id="treatment" required rows="3" class="admin-input"
                              placeholder="{{ __('admin.treatment_placeholder') }}">{{ old('treatment') }}</textarea>
                    @error('treatment') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="medicine" class="admin-label">{{ __('admin.medicine') }}</label>
                <textarea name="medicine" id="medicine" rows="2" class="admin-input"
                          placeholder="{{ __('admin.medicine_placeholder') }}">{{ old('medicine') }}</textarea>
                @error('medicine') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div>
                    <label for="cost" class="admin-label">{{ __('admin.consultation_cost') }}</label>
                    <input type="number" name="cost" id="cost" min="0" step="0.01"
                           value="{{ old('cost', $booking->total_amount ?? 0) }}" class="admin-input">
                    <p class="admin-hint">{{ __('admin.auto_fill_cost_hint') }}</p>
                    @error('cost') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="treatment_cost" class="admin-label">{{ __('admin.treatment_cost') }}</label>
                    <input type="number" name="treatment_cost" id="treatment_cost" min="0" step="0.01"
                           value="{{ old('treatment_cost', 0) }}" class="admin-input">
                    @error('treatment_cost') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="medicine_cost" class="admin-label">{{ __('admin.medicine_cost') }}</label>
                    <input type="number" name="medicine_cost" id="medicine_cost" min="0" step="0.01"
                           value="{{ old('medicine_cost', 0) }}" class="admin-input">
                    @error('medicine_cost') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="notes" class="admin-label">{{ __('admin.notes') }}</label>
                <textarea name="notes" id="notes" rows="2" class="admin-input"
                          placeholder="{{ __('admin.notes_placeholder') }}">{{ old('notes') }}</textarea>
                @error('notes') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label for="next_visit_date" class="admin-label">{{ __('admin.next_visit_date') }}</label>
                    <input type="date" name="next_visit_date" id="next_visit_date" class="admin-input"
                           min="{{ now()->addDay()->format('Y-m-d') }}" value="{{ old('next_visit_date') }}">
                    @error('next_visit_date') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="next_visit_time" class="admin-label">{{ __('admin.next_visit_time') }}</label>
                    <input type="time" name="next_visit_time" id="next_visit_time" class="admin-input"
                           value="{{ old('next_visit_time') }}">
                    @error('next_visit_time') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-6 dark:border-slate-800">
                <a href="{{ route('admin.bookings.index') }}" class="admin-btn-ghost">{{ __('common.cancel') }}</a>
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                    {{ __('admin.save_record') }}
                </button>
            </div>
        </form>
    </x-admin.panel>
</div>
@endsection
