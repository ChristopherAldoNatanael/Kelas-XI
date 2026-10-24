@extends('layouts.admin')

@section('title', __('admin.select_clinic_title') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.select_clinic_title'))

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-admin.page-header
        :title="__('admin.select_clinic_title')"
        :eyebrow="__('admin.eyebrow_operations')"
        icon="local_hospital"
        :description="__('admin.select_clinic_desc')" />

    @if($clinics->isEmpty())
        <x-admin.panel>
            <x-admin.empty-state
                :title="__('admin.clinics_empty_title')"
                :description="__('admin.clinics_empty_desc')"
                icon="local_hospital"
                tone="amber">
                <x-slot:actions>
                    <a href="{{ route('admin.clinics.index') }}" class="admin-btn-primary">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">local_hospital</span>
                        {{ __('menu.clinics') }}
                    </a>
                </x-slot:actions>
            </x-admin.empty-state>
        </x-admin.panel>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach($clinics as $clinic)
                <form method="POST" action="{{ route('admin.switch-clinic') }}">
                    @csrf
                    <input type="hidden" name="clinic_id" value="{{ $clinic->id }}">
                    <input type="hidden" name="return_to" value="schedule">
                    <button type="submit"
                            class="group w-full rounded-2xl border border-slate-200/70 bg-white p-5 text-left transition hover:border-emerald-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-700">
                        <div class="flex items-start gap-4">
                            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">local_hospital</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $clinic->name }}</h3>
                                    <x-admin.badge :label="__('common.active')" tone="primary" dot />
                                </div>
                                @if($clinic->address)
                                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $clinic->address }}</p>
                                @endif
                                @if($clinic->phone || $clinic->email)
                                    <p class="mt-2 text-[10px] text-slate-400">
                                        {{ collect([$clinic->phone, $clinic->email])->filter()->join(' · ') }}
                                    </p>
                                @endif
                            </div>
                            <span class="material-symbols-outlined text-slate-300 transition group-hover:text-emerald-500" aria-hidden="true">arrow_forward</span>
                        </div>
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</div>
@endsection
