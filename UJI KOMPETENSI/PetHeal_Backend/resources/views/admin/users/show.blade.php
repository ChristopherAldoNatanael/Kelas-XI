@php
    $roleTones = [
        'super_admin' => 'violet',
        'clinic_admin' => 'blue',
        'admin' => 'blue',
        'doctor' => 'sky',
        'user' => 'slate',
    ];
    $recentBookings = $user->bookings->take(5);
@endphp

@extends('layouts.admin')

@section('title', __('admin.user_detail_title', ['id' => $user->id]) . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.users'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('admin.user_detail_title', ['id' => $user->id])"
        :eyebrow="__('admin.eyebrow_management')"
        icon="group"
        :description="__('admin.user_detail_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.users.edit', $user->id) }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                {{ __('admin.edit_user') }}
            </a>
            <a href="{{ route('admin.users.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-admin.panel :title="__('admin.user_info')">
            <div class="flex items-center gap-4">
                @if($user->photo_url)
                    <img src="{{ $user->photo_url }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-full object-cover">
                @else
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-2xl font-bold text-emerald-600 dark:bg-emerald-900/30">
                        {{ mb_substr($user->name, 0, 1) }}
                    </span>
                @endif
                <div class="min-w-0">
                    <p class="truncate text-xl font-bold text-slate-900 dark:text-white">{{ $user->name }}</p>
                    <div class="mt-1.5">
                        <x-admin.badge
                            :label="__('admin.role_label_' . $user->role)"
                            :tone="$roleTones[$user->role] ?? 'slate'" />
                    </div>
                </div>
            </div>

            <dl class="mt-5 space-y-3">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('common.email') }}</dt>
                    <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-200">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('common.phone') }}</dt>
                    <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-200">{{ $user->phone ?? __('admin.no_phone') }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Firebase UID</dt>
                    <dd class="mt-0.5 inline-block rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        {{ $user->firebase_uid ?? __('admin.local_user') }}
                    </dd>
                </div>
            </dl>
        </x-admin.panel>

        @if($firebaseData ?? null)
            <x-admin.panel :title="__('admin.firebase_data')">
                <div class="space-y-2">
                    @foreach($firebaseData as $key => $value)
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $key }}</p>
                            <p class="text-sm text-slate-800 dark:text-slate-200">{{ is_array($value) ? json_encode($value) : $value }}</p>
                        </div>
                    @endforeach
                </div>
            </x-admin.panel>
        @endif
    </div>

    <x-admin.panel :title="__('admin.pets_owned') . ' (' . $user->pets->count() . ')'">
        @if($user->pets->isNotEmpty())
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($user->pets as $pet)
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                        @if($pet->photo_url)
                            <img class="h-10 w-10 rounded-full object-cover" src="{{ $pet->photo_url }}" alt="{{ $pet->name }}">
                        @else
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30">
                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">pets</span>
                            </span>
                        @endif
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900 dark:text-white">{{ $pet->name }}</p>
                            <p class="truncate text-sm text-slate-500">
                                {{ $pet->species ?? __('admin.pet_placeholder') }}{{ $pet->breed ? ' - ' . $pet->breed : '' }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <x-admin.empty-state :title="__('admin.no_pets')" icon="pets" tone="slate" />
        @endif
    </x-admin.panel>

    <x-admin.panel :title="__('admin.last_bookings') . ' (' . $recentBookings->count() . ')'" flush>
        @if($recentBookings->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('admin.pet') }}</th>
                            <th class="px-5 py-3">{{ __('admin.doctor') }}</th>
                            <th class="px-5 py-3">{{ __('common.date') }}</th>
                            <th class="px-5 py-3">{{ __('common.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @foreach($recentBookings as $booking)
                            <tr class="admin-table-row">
                                <td class="px-5 py-3 text-sm text-slate-800 dark:text-slate-200">
                                    {{ $booking->pet->name ?? __('admin.pet_placeholder') }}
                                </td>
                                <td class="px-5 py-3 text-sm text-slate-500">{{ $booking->doctor->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-slate-500">
                                    {{ \Carbon\Carbon::parse($booking->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                </td>
                                <td class="px-5 py-3">
                                    <x-admin.badge
                                        :label="\App\Support\AdminStatus::bookingLabel($booking->status)"
                                        :tone="\App\Support\AdminStatus::bookingTone($booking->status)" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-admin.empty-state :title="__('admin.no_visits')" :description="__('admin.no_visits_desc')" icon="event_busy" tone="slate" />
        @endif
    </x-admin.panel>

    <p class="text-xs text-slate-400">
        {{ __('admin.registered_at') }}: {{ optional($user->created_at)->locale(app()->getLocale())->translatedFormat('d F Y, H:i') }}
        @if($user->updated_at && $user->updated_at->ne($user->created_at))
            · {{ __('common.updated_at') }}: {{ $user->updated_at->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}
        @endif
    </p>
</div>
@endsection
