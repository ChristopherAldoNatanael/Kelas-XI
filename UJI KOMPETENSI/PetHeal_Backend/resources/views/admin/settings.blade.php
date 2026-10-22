@extends('layouts.admin')

@php
    $clinic = $clinic ?? null;
    $clinicAdmins = $clinicAdmins ?? collect();

    $tabs = [
        'akun' => ['label' => 'Akun saya', 'desc' => 'Nama & email login', 'icon' => 'person'],
        'keamanan' => ['label' => 'Keamanan', 'desc' => 'Password akun', 'icon' => 'lock'],
    ];
    if ($clinic) {
        $tabs['klinik'] = ['label' => __('menu.clinic_profile'), 'desc' => 'Identitas & branding', 'icon' => 'storefront'];
        $tabs['tim'] = ['label' => 'Tim klinik', 'desc' => 'Admin dengan akses', 'icon' => 'group'];
    }

    $activeTab = $activeTab ?? request('tab', 'akun');
    if (!array_key_exists($activeTab, $tabs)) {
        $activeTab = 'akun';
    }
@endphp

@section('title', __('menu.settings') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.settings'))

@section('content')
<div class="mx-auto max-w-5xl">

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Preferensi & identitas</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ __('menu.settings') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Semua pengaturan akun dan klinik ada di satu tempat — tanpa perlu berpindah halaman.
            </p>
        </div>
        @if($clinic)
            <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200/70 bg-white px-3 py-2 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $clinic->primary_color }}"></span>
                <span class="max-w-[200px] truncate">{{ $clinic->name }}</span>
                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    {{ $clinic->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[232px,1fr] lg:items-start">

        {{-- Tab rail --}}
        <nav class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0"
             aria-label="Bagian pengaturan">
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
