@extends('layouts.admin')

@section('title', __('clinics.clinic_management') . ' — PetHeal Admin')
@section('header', __('clinics.clinic_management'))

@section('content')
<div class="space-y-6">

    {{-- Header halaman --}}
    <x-admin.page-header
        :title="__('clinics.clinic_management')"
        :eyebrow="__('admin.eyebrow_management')"
        icon="local_hospital"
        :description="__('clinics.manage_all_clinics')">
        <x-slot:actions>
            <a href="{{ route('admin.clinics.create') }}"
               class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                {{ __('clinics.add_clinic') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    {{-- Kredensial akun admin klinik yang baru dibuat --}}
    @if(session('new_clinic_credentials'))
        @php $credentials = session('new_clinic_credentials'); @endphp
        <div class="relative rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-800 dark:border-blue-900/60 dark:bg-blue-900/20 dark:text-blue-200" id="cred-alert">
            <button type="button" onclick="document.getElementById('cred-alert').remove()"
                    class="absolute right-3 top-2 text-lg text-blue-400 transition hover:text-blue-600 dark:hover:text-blue-300"
                    aria-label="{{ __('common.close') }}">
                &times;
            </button>

            <p class="mb-3 font-bold">{{ __('clinics.admin_account') }} {{ $credentials['clinic'] }}</p>

            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-blue-600 dark:text-blue-300">{{ __('clinics.cred_email') }}:</span>
                    <code class="rounded bg-blue-100 px-2 py-0.5 font-mono text-xs text-blue-900 dark:bg-blue-900/60 dark:text-blue-100">{{ $credentials['email'] }}</code>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-blue-600 dark:text-blue-300">{{ __('clinics.cred_password') }}:</span>
                    <code class="rounded bg-blue-100 px-2 py-0.5 font-mono text-xs text-blue-900 dark:bg-blue-900/60 dark:text-blue-100">{{ $credentials['password'] }}</code>
                </div>
            </div>

            <p class="mt-3 text-[11px] text-blue-500 dark:text-blue-300/80">{{ __('clinics.save_credentials') }}</p>
        </div>
    @endif

    {{-- Tabel klinik --}}
    <x-admin.panel :title="__('clinics.th_clinic')" :subtitle="__('clinics.manage_all_clinics')" flush>
        <x-slot:actions>
            <x-admin.badge :label="__('admin.total') . ' · ' . $clinics->total()" tone="slate" />
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('clinics.th_clinic') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.contact') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.stats') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.color') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.th_status') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('clinics.th_actions') }}</th>
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
                            <td class="px-5 py-3">
                                <p class="text-xs text-slate-600 dark:text-slate-400">{{ $clinic->phone ?: '—' }}</p>
                                <p class="text-[11px] text-slate-400">{{ $clinic->email ?: '—' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    <x-admin.badge :label="$clinic->doctors_count . ' ' . __('dashboard.doctors')" tone="blue" />
                                    <x-admin.badge :label="$clinic->services_count . ' ' . __('dashboard.services_count')" tone="violet" />
                                    <x-admin.badge :label="$clinic->users_count . ' ' . __('dashboard.users')" tone="slate" />
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-5 w-5 rounded-full border border-slate-200 dark:border-slate-700"
                                          style="background-color: {{ $clinic->primary_color }}"></span>
                                    <span class="font-mono text-[10px] text-slate-500">{{ $clinic->primary_color }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="$clinic->is_active ? __('clinics.status_active') : __('clinics.status_inactive')"
                                    :tone="$clinic->is_active ? 'primary' : 'slate'"
                                    dot />
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('admin.clinics.edit', $clinic) }}"
                                       class="admin-icon-btn"
                                       title="{{ __('admin.edit') }}"
                                       aria-label="{{ __('admin.edit') }} {{ $clinic->name }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span>
                                    </a>

                                    <form method="POST" action="{{ route('admin.clinics.toggle-active', $clinic) }}"
                                          class="inline"
                                          onsubmit="return confirm('{{ __('clinics.toggle_confirm', ['name' => $clinic->name]) }}')">
                                        @csrf
                                        <button type="submit"
                                                class="admin-icon-btn hover:!text-orange-500"
                                                title="{{ $clinic->is_active ? __('common.inactive') : __('common.active') }}"
                                                aria-label="{{ $clinic->is_active ? __('common.inactive') : __('common.active') }} {{ $clinic->name }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">
                                                {{ $clinic->is_active ? 'block' : 'check_circle' }}
                                            </span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
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

        <x-slot:footer>
            {{ $clinics->links() }}
        </x-slot:footer>
    </x-admin.panel>
</div>
@endsection
