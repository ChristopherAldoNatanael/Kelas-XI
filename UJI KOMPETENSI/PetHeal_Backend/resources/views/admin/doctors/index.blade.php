@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $doctors */
@endphp
@extends('layouts.admin')

@section('title', __('menu.veterinarians') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.veterinarians'))

@section('content')
<div class="space-y-6">

    {{-- Header halaman --}}
    <x-admin.page-header
        :title="__('admin.doctors_title')"
        :eyebrow="__('admin.eyebrow_management')"
        icon="medical_services"
        :description="__('admin.doctors_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.doctors.create') }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                {{ __('admin.add_doctor') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Tabel dokter --}}
    <x-admin.panel :title="__('admin.doctors_title')" :subtitle="__('admin.doctors_desc')" flush>
        <x-slot:actions>
            <x-admin.badge :label="__('admin.total') . ' · ' . $doctors->total()" tone="slate" />
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('admin.col_doctor') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_specialization') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_rating') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_routine') }}</th>
                        <th class="px-5 py-3">{{ __('common.status') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($doctors as $doctor)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    @if($doctor->photo_url)
                                        <img class="h-10 w-10 flex-shrink-0 rounded-full object-cover ring-2 ring-slate-100 dark:ring-slate-700"
                                             src="{{ $doctor->photo_url }}?t={{ time() }}" alt="{{ $doctor->name }}">
                                    @else
                                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-2 ring-slate-100 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-slate-700"
                                              aria-hidden="true">
                                            <span class="material-symbols-outlined text-[20px]">person</span>
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.doctors.show', $doctor->id) }}"
                                           class="block truncate text-sm font-semibold text-slate-800 transition hover:text-primary dark:text-slate-200">
                                            {{ $doctor->name }}
                                        </a>
                                        <p class="truncate text-[11px] text-slate-400">{{ $doctor->email ?: __('admin.email_empty') }}</p>
                                        <div class="mt-1">
                                            @if($doctor->user)
                                                <x-admin.badge label="Portal aktif" tone="primary" dot />
                                            @else
                                                <x-admin.badge label="Belum ada akun" tone="slate" />
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge :label="$doctor->specialization" tone="blue" />
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px] text-amber-500" aria-hidden="true">star</span>
                                        <span class="text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">
                                            {{ $doctor->reviews_count > 0 ? number_format((float) $doctor->reviews_avg_rating, 1) : '0.0' }}
                                        </span>
                                        <span class="text-[11px] text-slate-400">
                                            ({{ $doctor->reviews_count }} {{ __('admin.col_reviews') }})
                                        </span>
                                    </div>
                                    <span class="text-[11px] text-slate-400">
                                        {{ $doctor->completed_bookings_count }} {{ __('admin.col_bookings_done') }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-xs text-slate-600 dark:text-slate-300">
                                    {{ implode(', ', array_slice($doctor->available_days, 0, 3)) }}
                                </p>
                                <p class="mt-0.5 text-[11px] text-slate-400">{{ $doctor->start_time }} - {{ $doctor->end_time }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="$doctor->is_active ? __('common.active') : __('common.inactive')"
                                    :tone="$doctor->is_active ? 'primary' : 'slate'"
                                    dot />
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('admin.doctors.show', $doctor->id) }}"
                                       class="admin-icon-btn hover:!text-primary"
                                       title="{{ __('admin.view_detail') }}"
                                       aria-label="{{ __('admin.view_detail') }} {{ $doctor->name }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
                                    </a>

                                    <a href="{{ route('admin.doctors.schedule', $doctor->id) }}"
                                       class="admin-icon-btn hover:!text-amber-600"
                                       title="{{ __('admin.schedule_tab') }}"
                                       aria-label="{{ __('admin.schedule_tab') }} {{ $doctor->name }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">event_busy</span>
                                    </a>

                                    <a href="{{ route('admin.doctors.edit', $doctor->id) }}"
                                       class="admin-icon-btn hover:!text-emerald-600"
                                       title="{{ __('admin.edit') }}"
                                       aria-label="{{ __('admin.edit') }} {{ $doctor->name }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span>
                                    </a>

                                    <form method="POST" action="{{ route('admin.doctors.destroy', $doctor->id) }}" class="inline"
                                          onsubmit="return confirm('{{ __('admin.delete_doctor_confirm') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="admin-icon-btn hover:!text-rose-600"
                                                title="{{ __('admin.delete') }}"
                                                aria-label="{{ __('admin.delete') }} {{ $doctor->name }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state
                                    :title="__('admin.no_doctors_title')"
                                    :description="__('admin.no_doctors_desc')"
                                    icon="medical_services"
                                    tone="primary">
                                    <x-slot:actions>
                                        <a href="{{ route('admin.doctors.create') }}" class="admin-btn-primary">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                                            {{ __('admin.add_doctor') }}
                                        </a>
                                    </x-slot:actions>
                                </x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($doctors->hasPages())
            <x-slot:footer>
                {{ $doctors->links() }}
            </x-slot:footer>
        @endif
    </x-admin.panel>
</div>
@endsection
