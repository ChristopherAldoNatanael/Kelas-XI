@extends('layouts.admin')

@section('title', 'Permintaan Layanan — PetHeal Admin')
@section('header', 'Permintaan Layanan')

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        title="Permintaan Layanan"
        eyebrow="Manajemen"
        icon="support_agent"
        description="Permintaan layanan selalu milik satu klinik. Pilih klinik yang ingin ditinjau." />

    <x-admin.panel title="Pilih Klinik" flush>
        @forelse($clinics as $clinic)
            <form method="POST" action="{{ route('admin.switch-clinic') }}"
                  class="border-b border-slate-100 last:border-0 dark:border-slate-800">
                @csrf
                <input type="hidden" name="clinic_id" value="{{ $clinic->id }}">
                <button type="submit"
                        class="flex w-full items-center justify-between px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $clinic->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $clinic->address }}</p>
                    </div>
                    <span class="material-symbols-outlined text-slate-500" aria-hidden="true">chevron_right</span>
                </button>
            </form>
        @empty
            <div class="px-5 py-8">
                <x-admin.empty-state title="Belum ada klinik aktif" icon="local_hospital" tone="slate" />
            </div>
        @endforelse
    </x-admin.panel>
</div>
@endsection
