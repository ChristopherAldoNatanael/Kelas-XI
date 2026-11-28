@props(['model', 'actions' => [], 'class' => ''])

{{--
    Jejak "terakhir diubah" untuk satu record.

    Halaman yang bisa disentuh lebih dari satu peran (mis. data dokter diubah
    admin klinik maupun dokter sendiri) perlu menyebut siapa mengubah terakhir
    kali, supaya perselisihan data bisa ditelusuri dari layar yang sama
    (docs/AUDIT_FIXES_REPORT.md UIX-02).

    @example
    <x-admin.last-updated :model="$doctor" :actions="['doctor.update', 'doctor.profile.update']" />
--}}
@php
    $log = \App\Models\AuditLog::latestFor($model, $actions);
@endphp

@if($log)
    <p {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[11px] leading-5 text-slate-500 dark:text-slate-400 ' . $class]) }}>
        <span class="material-symbols-outlined text-[14px] text-slate-500" aria-hidden="true">history</span>
        <span>{{ __('admin.saved_by') }} {{ $log->created_at?->translatedFormat('d M Y, H:i') }}</span>
        @if($log->user)
            <span aria-hidden="true">·</span>
            <span class="font-medium text-slate-600 dark:text-slate-300">{{ $log->user->name }}</span>
        @endif
    </p>
@endif
