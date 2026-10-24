@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $users */
$role = $role ?? 'all';
$stats = $stats ?? ['total' => 0, 'patients' => 0, 'admins' => 0, 'new_this_month' => 0];

$roleTabs = [
    'all' => ['label' => __('admin.role_all'), 'count' => $stats['total']],
    'patient' => ['label' => __('admin.role_patient'), 'count' => $stats['patients']],
    'admin' => ['label' => __('admin.role_team'), 'count' => $stats['admins']],
];

$roleBadges = [
    'user' => ['label' => __('admin.role_label_user'), 'tone' => 'slate'],
    'doctor' => ['label' => __('admin.role_label_doctor'), 'tone' => 'sky'],
    'clinic_admin' => ['label' => __('admin.role_label_clinic_admin'), 'tone' => 'violet'],
    'admin' => ['label' => __('admin.role_label_admin'), 'tone' => 'violet'],
    'super_admin' => ['label' => __('admin.role_label_super_admin'), 'tone' => 'primary'],
];
@endphp
@extends('layouts.admin')

@section('title', __('menu.users') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.users'))

@section('content')
<div class="space-y-6">

    {{-- Header halaman --}}
    <x-admin.page-header
        :title="__('admin.users_title')"
        :eyebrow="__('admin.eyebrow_management')"
        icon="group"
        :description="__('admin.users_desc')">
        <x-slot:actions>
            @if($role !== 'admin')
                <a href="{{ route('admin.users.create') }}" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person_add</span>
                    {{ __('admin.add_patient') }}
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card
            :label="__('admin.stat_total_users')"
            :value="number_format($stats['total'])"
            icon="group"
            tone="slate" />

        <x-admin.stat-card
            :label="__('admin.stat_patients')"
            :value="number_format($stats['patients'])"
            icon="pets"
            tone="primary" />

        <x-admin.stat-card
            :label="__('admin.stat_new_patients')"
            :value="number_format($stats['new_this_month'])"
            icon="person_add"
            tone="sky" />

        <x-admin.stat-card
            :label="__('admin.stat_admin_accounts')"
            :value="number_format($stats['admins'])"
            icon="badge"
            tone="violet" />
    </div>

    {{-- Tabel pengguna --}}
    <x-admin.panel :title="__('admin.users_title')" :subtitle="__('admin.users_desc')" flush>
        @if($role === 'admin')
            <div class="border-b border-sky-100 bg-sky-50/70 px-4 py-3 text-xs leading-5 text-sky-800 dark:border-sky-900/30 dark:bg-sky-900/20 dark:text-sky-300">
                {{ __('admin.need_more_admin_hint') }}
            </div>
        @endif

        {{-- Toolbar --}}
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-slate-800">
            <div class="inline-flex self-start rounded-xl border border-slate-200/80 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800/60"
                 role="tablist" aria-label="{{ __('admin.filter_role') }}">
                @foreach($roleTabs as $key => $tab)
                    @php $isActive = $role === $key; @endphp
                    <a href="{{ route('admin.users.index', array_filter(['role' => $key === 'all' ? null : $key, 'search' => request('search')])) }}"
                       role="tab" @if($isActive) aria-selected="true" @endif
                       class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors
                              {{ $isActive
                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white'
                                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                        {{ $tab['label'] }}
                        <span class="rounded bg-slate-100 px-1 text-[10px] font-bold tabular-nums text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            {{ $tab['count'] }}
                        </span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-1 items-center gap-2 lg:max-w-md">
                @if($role !== 'all')
                    <input type="hidden" name="role" value="{{ $role }}">
                @endif
                <div class="relative flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400" aria-hidden="true">search</span>
                    <label for="user-search" class="sr-only">{{ __('admin.search') }}</label>
                    <input id="user-search" type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('admin.search_users_placeholder') }}"
                           class="admin-input !pl-9">
                </div>
                <button type="submit" class="admin-btn-primary !px-4 !py-2.5 !text-xs">
                    {{ __('admin.search') }}
                </button>
                @if(request('search'))
                    <a href="{{ route('admin.users.index', $role !== 'all' ? ['role' => $role] : []) }}"
                       class="admin-btn-ghost !px-3 !py-2.5 !text-xs">
                        {{ __('admin.reset_filter') }}
                    </a>
                @endif
            </form>
        </div>

        @if($users->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('admin.col_user') }}</th>
                            <th class="px-5 py-3">{{ __('admin.col_contact') }}</th>
                            <th class="px-5 py-3">{{ __('admin.pets_owned') }}</th>
                            <th class="px-5 py-3">{{ __('admin.col_role') }}</th>
                            <th class="px-5 py-3">{{ __('admin.col_joined') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @foreach($users as $user)
                            @php $badge = $roleBadges[$user->role] ?? ['label' => ucfirst((string) $user->role), 'tone' => 'slate']; @endphp
                            <tr class="admin-table-row">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        @if($user->photo_url)
                                            <img src="{{ $user->photo_url }}" alt="" class="h-9 w-9 rounded-full object-cover" loading="lazy">
                                        @else
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-xs font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
                                                  aria-hidden="true">
                                                {{ mb_substr($user->name, 0, 1) }}
                                            </span>
                                        @endif
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.users.show', $user->id) }}"
                                               class="block truncate text-sm font-semibold text-slate-800 transition hover:text-primary dark:text-slate-100">
                                                {{ $user->name }}
                                            </a>
                                            <p class="text-[10px] text-slate-400">#{{ $user->id }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $user->email }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $user->phone ?: __('admin.no_phone') }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    @if($user->pets_count > 0)
                                        <x-admin.badge :label="$user->pets_count . ' ' . __('admin.pets_owned')" tone="slate" />
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <x-admin.badge :label="$badge['label']" :tone="$badge['tone']" />
                                </td>
                                <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $user->created_at?->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.users.show', $user->id) }}"
                                           class="admin-icon-btn hover:!text-primary"
                                           title="{{ __('admin.view_detail') }}"
                                           aria-label="{{ __('admin.view_detail') }} {{ $user->name }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
                                        </a>
                                        <a href="{{ route('admin.users.edit', $user->id) }}"
                                           class="admin-icon-btn hover:!text-primary"
                                           title="{{ __('admin.edit') }}"
                                           aria-label="{{ __('admin.edit') }} {{ $user->name }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span>
                                        </a>
                                        @if($user->role === 'user')
                                            <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" class="inline"
                                                  onsubmit="return confirm('{{ __('admin.confirm_delete') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="admin-icon-btn hover:!text-rose-600"
                                                        title="{{ __('admin.delete') }}"
                                                        aria-label="{{ __('admin.delete') }} {{ $user->name }}">
                                                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <x-slot:footer>
                    {{ $users->links() }}
                </x-slot:footer>
            @endif
        @else
            <div class="p-5">
                <x-admin.empty-state
                    :title="request('search') ? __('admin.no_users_found_title') : __('admin.no_users_title')"
                    :description="request('search')
                        ? __('admin.no_users_found_desc', ['search' => request('search')])
                        : __('admin.no_users_desc')"
                    icon="group_off"
                    tone="slate">
                    <x-slot:actions>
                        @if(request('search'))
                            <a href="{{ route('admin.users.index') }}" class="admin-btn-secondary">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">restart_alt</span>
                                {{ __('admin.reset_search') }}
                            </a>
                        @elseif($role !== 'admin')
                            <a href="{{ route('admin.users.create') }}" class="admin-btn-primary">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person_add</span>
                                {{ __('admin.add_patient') }}
                            </a>
                        @endif
                    </x-slot:actions>
                </x-admin.empty-state>
            </div>
        @endif
    </x-admin.panel>
</div>
@endsection
