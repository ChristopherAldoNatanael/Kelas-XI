@extends('layouts.admin')

@php
    $clinic = $clinic ?? null;

    $tabs = [
        'akun' => ['label' => __('admin.tab_account'), 'desc' => __('admin.tab_account_desc'), 'icon' => 'person'],
        'keamanan' => ['label' => __('admin.tab_security'), 'desc' => __('admin.tab_security_desc'), 'icon' => 'lock'],
    ];
    if ($clinic) {
        $tabs['klinik'] = ['label' => __('menu.clinic_profile'), 'desc' => __('admin.tab_clinic_desc'), 'icon' => 'storefront'];
    }

    $activeTab = $activeTab ?? request('tab', 'akun');
    if (!array_key_exists($activeTab, $tabs)) {
        $activeTab = 'akun';
    }
@endphp

@section('title', __('menu.settings') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.settings'))

@section('content')
<div class="mx-auto max-w-5xl space-y-6">

    <x-admin.page-header
        :title="__('menu.settings')"
        :eyebrow="__('admin.settings_eyebrow')"
        icon="settings"
        :description="__('admin.settings_desc')">
        <x-slot:actions>
            @if($clinic)
                <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200/70 bg-white px-3 py-2 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $clinic->primary_color ?: '#10b981' }}"></span>
                    <span class="max-w-[200px] truncate">{{ $clinic->name }}</span>
                    <x-admin.badge
                        :label="$clinic->is_active ? __('admin.clinic_active_badge') : __('admin.clinic_inactive_badge')"
                        :tone="$clinic->is_active ? 'primary' : 'slate'" />
                </div>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[232px,1fr] lg:items-start">

        {{-- Tab rail --}}
        <nav class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0"
             aria-label="{{ __('menu.settings') }}">
            @foreach($tabs as $key => $tab)
                @php $isActive = $activeTab === $key; @endphp
                <a href="{{ route('admin.settings', ['tab' => $key]) }}"
                   class="group flex min-w-[148px] flex-1 items-center gap-3 rounded-xl border px-3.5 py-3 transition-colors lg:min-w-0 lg:flex-none
                          {{ $isActive
                                ? 'border-primary/30 bg-primary/10 text-slate-900 dark:text-white'
                                : 'border-slate-200/70 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:bg-slate-800/60' }}"
                   @if($isActive) aria-current="page" @endif>
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0 {{ $isActive ? 'text-primary' : 'text-slate-400' }}" aria-hidden="true">{{ $tab['icon'] }}</span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">{{ $tab['label'] }}</span>
                        <span class="block truncate text-[10px] font-medium {{ $isActive ? 'text-slate-600 dark:text-slate-300' : 'text-slate-400' }}">{{ $tab['desc'] }}</span>
                    </span>
                </a>
            @endforeach
        </nav>

        {{-- Active panel --}}
        <div class="min-w-0">
            @include('admin.settings.partials.' . $activeTab)
        </div>
    </div>
</div>
@endsection
