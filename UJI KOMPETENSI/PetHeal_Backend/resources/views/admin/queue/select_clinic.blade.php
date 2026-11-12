@extends('layouts.admin')

@section('title', __('admin.queue_title') . ' — PetHeal Admin')
@section('header', __('admin.queue_title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('admin.queue_title')"
        :eyebrow="__('admin.eyebrow_management')"
        icon="groups"
        :description="__('admin.queue_select_clinic_desc')" />

    <x-admin.panel :title="__('admin.clinics')" flush>
        @forelse($clinics as $clinic)
            {{-- Antrian selalu butuh klinik aktif. Dulu baris ini memakai
                 route `admin.clinic-context.switch` yang tidak pernah ada,
                 sehingga halaman 500 untuk super_admin tanpa klinik. --}}
            <form method="POST" action="{{ route('admin.switch-clinic') }}"
                  class="border-b border-slate-100 last:border-0 dark:border-slate-800">
                @csrf
                <input type="hidden" name="clinic_id" value="{{ $clinic->id }}">
                <input type="hidden" name="return_to" value="queue">
                <button type="submit"
                        class="flex w-full items-center justify-between px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $clinic->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $clinic->address }}</p>
                    </div>
                    <span class="material-symbols-outlined text-slate-400" aria-hidden="true">chevron_right</span>
                </button>
            </form>
        @empty
            <div class="px-5 py-8">
                <x-admin.empty-state :title="__('admin.no_clinics_title')" icon="local_hospital" tone="slate" />
            </div>
        @endforelse
    </x-admin.panel>
</div>
@endsection
