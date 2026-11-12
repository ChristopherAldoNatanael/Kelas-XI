@extends('layouts.admin')

@section('title', __('admin.queue_title') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.queue_title'))

@php
    use App\Services\QueueService;
@endphp

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('admin.queue_title')"
        :eyebrow="__('admin.eyebrow_operations')"
        icon="groups"
        :description="__('admin.queue_desc')">
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.queue.index') }}" class="flex items-center gap-2">
                <input type="date" name="date" value="{{ $date }}" class="admin-input !w-40 !py-2">
                <button type="submit" class="admin-btn-secondary !py-2">{{ __('admin.show') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.queue.call-next') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">campaign</span>
                    {{ __('admin.queue_call_next') }}
                </button>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    @foreach(['success' => 'emerald', 'error' => 'rose', 'warning' => 'amber'] as $flash => $tone)
        @if(session($flash))
            <div class="flex items-center gap-3 rounded-2xl border border-{{ $tone }}-200 bg-{{ $tone }}-50 px-5 py-3.5 text-sm font-medium text-{{ $tone }}-700 dark:border-{{ $tone }}-900/60 dark:bg-{{ $tone }}-900/20">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">
                    {{ $flash === 'success' ? 'check_circle' : ($flash === 'error' ? 'error' : 'warning') }}
                </span>
                <span>{{ session($flash) }}</span>
            </div>
        @endif
    @endforeach

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <x-admin.stat-card :label="__('admin.queue_waiting')" :value="$board['stats']['waiting']" icon="hourglass_top" tone="amber" />
        <x-admin.stat-card :label="__('admin.queue_serving')" :value="$board['stats']['serving']" icon="stethoscope" tone="primary" />
        <x-admin.stat-card :label="__('admin.queue_done')" :value="$board['stats']['done']" icon="task_alt" tone="blue" />
        <x-admin.stat-card :label="__('admin.queue_no_show')" :value="$board['stats']['no_show']" icon="person_off" tone="rose" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.3fr_1fr]">
        <div class="space-y-6">
            {{-- Sedang ditangani --}}
            <x-admin.panel :title="__('admin.queue_serving')" :subtitle="__('admin.queue_serving_desc')" flush>
                @forelse($board['serving'] as $booking)
                    <div class="flex items-center gap-4 border-b border-slate-100 px-5 py-4 last:border-0 dark:border-slate-800">
                        <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-lg font-bold tabular-nums text-primary">
                            {{ $booking->queue_number }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">
                                {{ $booking->pet->name ?? '—' }}
                                <span class="text-xs font-normal text-slate-500">· {{ $booking->doctor->name ?? '—' }}</span>
                            </p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ $booking->user->name ?? '—' }} · {{ $booking->booking_time }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('admin.queue.status', $booking->id) }}">
                                @csrf
                                <input type="hidden" name="queue_status" value="{{ QueueService::STATUS_DONE }}">
                                <button class="admin-btn-secondary !py-2 text-xs">{{ __('admin.queue_mark_done') }}</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8">
                        <x-admin.empty-state :title="__('admin.queue_empty_serving')" icon="stethoscope" tone="slate" />
                    </div>
                @endforelse
            </x-admin.panel>

            {{-- Menunggu --}}
            <x-admin.panel :title="__('admin.queue_waiting')" :subtitle="__('admin.queue_waiting_desc')" flush>
                @forelse($board['waiting'] as $booking)
                    <div class="flex items-center gap-4 border-b border-slate-100 px-5 py-4 last:border-0 dark:border-slate-800">
                        <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-lg font-bold tabular-nums text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                            {{ $booking->queue_number }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">
                                {{ $booking->pet->name ?? '—' }}
                                <span class="text-xs font-normal text-slate-500">· {{ $booking->doctor->name ?? '—' }}</span>
                            </p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ $booking->user->name ?? '—' }} · {{ __('admin.queue_booked_at') }} {{ $booking->booking_time }}
                            </p>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <form method="POST" action="{{ route('admin.queue.status', $booking->id) }}">
                                @csrf
                                <input type="hidden" name="queue_status" value="{{ QueueService::STATUS_IN_SERVICE }}">
                                <button class="admin-btn-primary !py-2 text-xs">{{ __('admin.queue_start_service') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.queue.status', $booking->id) }}">
                                @csrf
                                <input type="hidden" name="queue_status" value="{{ QueueService::STATUS_NO_SHOW }}">
                                <button class="admin-btn-ghost !py-2 text-xs"
                                        onclick="return confirm('{{ __('admin.queue_no_show_confirm') }}')">
                                    {{ __('admin.queue_no_show') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8">
                        <x-admin.empty-state :title="__('admin.queue_empty_waiting')" icon="hourglass_empty" tone="slate" />
                    </div>
                @endforelse
            </x-admin.panel>
        </div>

        <div class="space-y-6">
            {{-- Belum check-in --}}
            <x-admin.panel :title="__('admin.queue_not_checked_in')" :subtitle="__('admin.queue_not_checked_in_desc')" flush>
                @forelse($notCheckedIn as $booking)
                    <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 dark:border-slate-800">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">
                                {{ $booking->pet->name ?? '—' }} · {{ $booking->booking_time }}
                            </p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ $booking->doctor->name ?? '—' }}
                                @if($booking->status === 'pending')
                                    · {{ __('admin.queue_needs_confirm') }}
                                @endif
                            </p>
                        </div>
                        @if($booking->status === 'confirmed')
                            <form method="POST" action="{{ route('admin.queue.check-in', $booking->id) }}">
                                @csrf
                                <button class="admin-btn-secondary !py-2 text-xs">{{ __('admin.queue_check_in') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.bookings.confirm', $booking->id) }}">
                                @csrf
                                <button class="admin-btn-secondary !py-2 text-xs">{{ __('admin.confirm') }}</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-6">
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('admin.queue_all_checked_in') }}</p>
                    </div>
                @endforelse
            </x-admin.panel>

            {{-- Selesai & tidak datang --}}
            <x-admin.panel :title="__('admin.queue_finished')" :subtitle="__('admin.queue_finished_desc')" flush>
                @forelse($board['finished'] as $booking)
                    <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 dark:border-slate-800">
                        <span class="w-9 text-sm font-bold tabular-nums text-slate-400">{{ $booking->queue_number }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-slate-700 dark:text-slate-300">{{ $booking->pet->name ?? '—' }}</p>
                            <p class="text-[11px] text-slate-400">
                                {{ $booking->queue_finished_at?->format('H:i') }}
                            </p>
                        </div>
                        <x-admin.badge
                            :label="QueueService::statusLabel($booking->queue_status)"
                            :tone="$booking->queue_status === QueueService::STATUS_DONE ? 'primary' : 'rose'"
                            dot />
                    </div>
                @empty
                    <div class="px-5 py-6">
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('admin.queue_empty_finished') }}</p>
                    </div>
                @endforelse
            </x-admin.panel>
        </div>
    </div>
</div>
@endsection
