@extends('layouts.admin')

@section('title', __('dashboard.system_overview') . ' — PetHeal Admin')
@section('header', __('dashboard.system_overview'))

@section('content')
<div class="space-y-6">

    {{-- Header halaman --}}
    <x-admin.page-header
        :title="__('dashboard.system_overview')"
        :eyebrow="__('admin.eyebrow_system')"
        icon="monitoring"
        :description="__('admin.super_overview_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.join-requests') }}"
               class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person_add</span>
                {{ __('dashboard.review_join_requests') }}
                @if($pendingJoinRequests > 0)
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                        {{ $pendingJoinRequests }}
                    </span>
                @endif
            </a>
            <a href="{{ route('admin.clinics.create') }}"
               class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                {{ __('clinics.add_clinic') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Permintaan join yang menunggu --}}
    @if($pendingJoinRequests > 0)
        <div class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900/60 dark:bg-amber-900/20">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">person_add</span>
                </span>
                <div>
                    <p class="text-sm font-bold text-amber-900 dark:text-amber-200">
                        {{ $pendingJoinRequests }} {{ __('dashboard.pending_requests') }}
                    </p>
                    <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-300/80">{{ __('dashboard.pending_requests_desc') }}</p>
                </div>
            </div>
            <a href="{{ route('admin.join-requests') }}" class="admin-btn-primary !bg-amber-500 hover:!bg-amber-600 self-start sm:self-auto">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">fact_check</span>
                {{ __('dashboard.review_now') }}
            </a>
        </div>
    @endif

    {{-- Kartu ringkasan --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card
            :label="__('dashboard.total_clinics')"
            :value="$totalClinics"
            icon="local_hospital"
            tone="primary"
            :hint="$activeClinics . ' ' . __('admin.stat_active_clinics')" />

        <x-admin.stat-card
            :label="__('dashboard.total_doctors')"
            :value="number_format($totalDoctors)"
            icon="medical_services"
            tone="blue" />

        <x-admin.stat-card
            :label="__('dashboard.total_patients')"
            :value="number_format($totalUsers)"
            icon="group"
            tone="violet" />

        <x-admin.stat-card
            :label="__('dashboard.total_bookings')"
            :value="number_format($totalBookings)"
            icon="calendar_month"
            tone="sky" />
    </div>

    {{-- Tabel seluruh klinik --}}
    <x-admin.panel :title="__('dashboard.all_clinics')" :subtitle="__('dashboard.all_clinics_desc')" flush>
        <x-slot:actions>
            <a href="{{ route('admin.clinics.index') }}"
               class="text-xs font-semibold text-primary transition hover:opacity-80">
                {{ __('dashboard.manage_clinics') }}
            </a>
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('clinics.th_clinic') }}</th>
                        <th class="px-5 py-3">{{ __('dashboard.location') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('dashboard.doctors') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('dashboard.services_count') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('dashboard.bookings') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('dashboard.users') }}</th>
                        <th class="px-5 py-3">{{ __('common.color') }}</th>
                        <th class="px-5 py-3">{{ __('common.status') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($clinics as $clinic)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    @if($clinic->logo_url)
                                        <img src="{{ $clinic->logo_url }}" alt="{{ $clinic->name }}"
                                             class="h-9 w-9 rounded-lg border border-slate-200 object-cover dark:border-slate-700">
                                    @else
                                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-[11px] font-bold text-white"
                                              style="background-color: {{ $clinic->primary_color }}">
                                            {{ \Illuminate\Support\Str::substr($clinic->name, 0, 2) }}
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $clinic->name }}</p>
                                        <p class="truncate text-[11px] text-slate-400">{{ $clinic->slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="max-w-[220px] truncate px-5 py-3 text-xs text-slate-600 dark:text-slate-400">
                                {{ $clinic->address ?: '—' }}
                            </td>
                            <td class="px-5 py-3 text-center text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">
                                {{ $clinic->doctors_count }}
                            </td>
                            <td class="px-5 py-3 text-center text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">
                                {{ $clinic->services_count }}
                            </td>
                            <td class="px-5 py-3 text-center text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">
                                {{ $clinic->bookings_count }}
                            </td>
                            <td class="px-5 py-3 text-center text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">
                                {{ $clinic->users_count }}
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-4 w-4 rounded-full border border-slate-200 dark:border-slate-700"
                                          style="background-color: {{ $clinic->primary_color }}"></span>
                                    <span class="font-mono text-[10px] text-slate-500">{{ $clinic->primary_color }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="$clinic->is_active ? __('common.active') : __('common.inactive')"
                                    :tone="$clinic->is_active ? 'primary' : 'slate'"
                                    dot />
                            </td>
                            <td class="px-5 py-3 text-center">
                                <form method="POST" action="{{ route('admin.switch-clinic') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="clinic_id" value="{{ $clinic->id }}">
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-primary hover:text-primary dark:border-slate-700 dark:text-slate-300"
                                            title="{{ __('admin.clinic_enter_hint') }}">
                                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">login</span>
                                        {{ __('dashboard.enter_clinic') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-admin.empty-state
                                    :title="__('admin.clinics_empty_title')"
                                    :description="__('admin.clinics_empty_desc')"
                                    icon="local_hospital"
                                    tone="primary">
                                    <x-slot:actions>
                                        <a href="{{ route('admin.clinics.create') }}" class="admin-btn-primary">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                                            {{ __('clinics.add_clinic') }}
                                        </a>
                                    </x-slot:actions>
                                </x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.panel>

    {{-- Ringkasan sistem & tautan cepat --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-admin.panel :title="__('dashboard.summary')" :subtitle="__('admin.summary_desc')">
            <dl class="space-y-3">
                @foreach([
                    ['label' => __('dashboard.total_services'), 'value' => number_format($totalServices)],
                    ['label' => __('dashboard.total_bookings'), 'value' => number_format($totalBookings)],
                    ['label' => __('dashboard.total_doctors'), 'value' => number_format($totalDoctors)],
                    ['label' => __('dashboard.total_patients'), 'value' => number_format($totalUsers)],
                ] as $row)
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2.5 last:border-0 last:pb-0 dark:border-slate-800">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">{{ $row['label'] }}</dt>
                        <dd class="text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="mt-5 rounded-xl border border-emerald-200/60 bg-emerald-50/60 p-4 dark:border-emerald-900/50 dark:bg-emerald-900/15">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">
                    {{ __('dashboard.total_revenue') }}
                </p>
                <p class="mt-1.5 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">
                    Rp {{ number_format($totalRevenue, 0, ',', '.') }}
                </p>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('dashboard.quick_links')" :subtitle="__('admin.quick_links_desc')">
            <div class="space-y-2">
                <a href="{{ route('admin.clinics.create') }}"
                   class="flex items-center gap-3 rounded-xl border border-slate-200/70 px-4 py-3 transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-900/25 dark:text-emerald-400">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_circle</span>
                    </span>
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('dashboard.add_new_clinic') }}</span>
                </a>

                <a href="{{ route('admin.join-requests') }}"
                   class="flex items-center gap-3 rounded-xl border border-slate-200/70 px-4 py-3 transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-900/25 dark:text-amber-400">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person_add</span>
                    </span>
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('dashboard.review_join_requests') }}</span>
                    @if($pendingJoinRequests > 0)
                        <span class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                            {{ $pendingJoinRequests }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('admin.settings') }}"
                   class="flex items-center gap-3 rounded-xl border border-slate-200/70 px-4 py-3 transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">settings</span>
                    </span>
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('menu.settings') }}</span>
                </a>
            </div>
        </x-admin.panel>
    </div>
</div>
@endsection
