@php
    $extraStatus = $record->extra_payment_status ?? 'not_required';
    $currency = static fn ($amount) => 'Rp ' . number_format((float) $amount, 0, ',', '.');
    $owner = $record->booking?->user;
@endphp

@extends('layouts.admin')

@section('title', __('admin.records_title') . ' #' . $record->id . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.records_title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('admin.records_title') . ' #' . $record->id"
        :eyebrow="__('admin.documentation_clinical')"
        icon="clinical_notes"
        :description="__('admin.records_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.medical-records.edit', $record->id) }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                {{ __('admin.edit_record_short') }}
            </a>
            <a href="{{ route('admin.medical-records.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back_to_records') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.6fr_0.95fr]">
        <div class="space-y-6">
            <x-admin.panel :title="__('admin.col_clinical_summary')" :subtitle="__('admin.documentation_clinical')">
                <div class="space-y-4">
                    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.diagnosis') }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $record->diagnosis }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.treatment') }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $record->treatment }}</p>
                    </div>

                    @if($record->medicine)
                        <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.medicine') }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $record->medicine }}</p>
                        </div>
                    @endif

                    @if($record->notes)
                        <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.additional_notes') }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $record->notes }}</p>
                        </div>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel :title="__('admin.billing_breakdown')" :subtitle="__('admin.cost_composition')">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-admin.stat-card :label="__('admin.consultation')" :value="$currency($record->cost ?? 0)" icon="stethoscope" tone="blue" />
                    <x-admin.stat-card :label="__('admin.treatment')" :value="$currency($record->treatment_cost ?? 0)" icon="medical_services" tone="violet" />
                    <x-admin.stat-card :label="__('admin.medicine')" :value="$currency($record->medicine_cost ?? 0)" icon="medication" tone="amber" />
                </div>

                <div class="mt-5 rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-5 dark:border-emerald-900/60 dark:bg-emerald-900/20">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">
                                {{ __('admin.financial_result') }}
                            </p>
                            <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight text-slate-900 dark:text-white">
                                {{ $currency($record->total_medical_cost ?? 0) }}
                            </p>
                            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{{ __('admin.financial_result_desc') }}</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-white/80 bg-white/80 px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.extra_payment_required') }}</p>
                                <p class="mt-1 text-lg font-bold tabular-nums text-slate-900 dark:text-white">
                                    {{ $currency($record->extra_payment_amount ?? 0) }}
                                </p>
                            </div>
                            <div class="rounded-xl border border-white/80 bg-white/80 px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.extra_paid') }}</p>
                                <p class="mt-1 text-lg font-bold tabular-nums text-slate-900 dark:text-white">
                                    {{ $currency($record->extra_payment_paid_amount ?? 0) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </x-admin.panel>
        </div>

        <div class="space-y-6">
            <x-admin.panel :title="__('admin.case_participants')" :subtitle="__('admin.people_pet_involved')">
                <div class="space-y-3">
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                        @if($record->pet?->photo_url)
                            <img class="h-12 w-12 rounded-xl object-cover ring-1 ring-slate-200" src="{{ $record->pet->photo_url }}" alt="{{ $record->pet->name }}">
                        @else
                            <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30">
                                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">pets</span>
                            </span>
                        @endif
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.pet') }}</p>
                            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $record->pet->name ?? __('admin.pet_placeholder') }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ $record->pet->species ?? __('admin.pet_placeholder') }}{{ $record->pet->breed ? ' · ' . $record->pet->breed : '' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                        <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/30">
                            <span class="material-symbols-outlined text-[22px]" aria-hidden="true">person</span>
                        </span>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.owner') }}</p>
                            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $owner->name ?? __('admin.unknown_owner') }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $owner->email ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                        @if($record->doctor?->photo_url)
                            <img class="h-12 w-12 rounded-xl object-cover ring-1 ring-slate-200" src="{{ $record->doctor->photo_url }}" alt="{{ $record->doctor->name }}">
                        @else
                            <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-900/30">
                                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">stethoscope</span>
                            </span>
                        @endif
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.doctor') }}</p>
                            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $record->doctor->name ?? '—' }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $record->doctor->specialization ?? __('admin.veterinarian') }}</p>
                        </div>
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel :title="__('admin.visit_timeline')" :subtitle="__('admin.appointment_and_followup')">
                <div class="space-y-3">
                    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.booking_date') }}</p>
                        <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">
                            {{ \Carbon\Carbon::parse($record->booking?->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('admin.at_time', ['time' => \Carbon\Carbon::parse($record->booking?->booking_time)->format('H:i')]) }}
                        </p>
                    </div>

                    @if($record->next_visit_date)
                        <div class="rounded-xl border border-amber-200/70 bg-amber-50/70 p-4 dark:border-amber-900/60 dark:bg-amber-900/20">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">{{ __('admin.follow_up') }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($record->next_visit_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                            </p>
                            <p class="text-xs text-amber-700/80 dark:text-amber-300/80">
                                @if($record->next_visit_time)
                                    {{ __('admin.at_time', ['time' => \Carbon\Carbon::parse($record->next_visit_time)->format('H:i')]) }}
                                @else
                                    {{ __('admin.schedule_pending_time') }}
                                @endif
                            </p>
                        </div>
                    @endif

                    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.audit_trail') }}</p>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                            {{ __('common.created_at') }}: {{ optional($record->created_at)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                            {{ __('common.updated_at') }}: {{ optional($record->updated_at)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}
                        </p>
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel :title="__('admin.extra_payment')">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ __('admin.col_financials') }}</span>
                    <x-admin.badge
                        :label="\App\Support\AdminStatus::extraPaymentLabel($extraStatus)"
                        :tone="\App\Support\AdminStatus::extraPaymentTone($extraStatus)"
                        dot />
                </div>
            </x-admin.panel>
        </div>
    </div>
</div>
@endsection
