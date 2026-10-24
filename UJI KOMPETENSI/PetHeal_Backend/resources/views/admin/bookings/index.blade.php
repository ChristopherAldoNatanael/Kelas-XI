@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $bookings */
@endphp
@extends('layouts.admin')

@section('title', __('menu.appointments') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.appointments_schedule'))

@section('content')
<div class="space-y-6">

    @include('admin.bookings._tabs')

    {{-- Filter --}}
    <x-admin.panel :title="__('common.filter')" :subtitle="__('admin.bookings_export_hint')">
        <form method="GET" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="filter-status" class="admin-label">{{ __('common.status') }}</label>
                <select id="filter-status" name="status" class="admin-input">
                    <option value="">{{ __('admin.all_status') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('admin.filter_status_pending') }}</option>
                    <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>{{ __('admin.filter_status_confirmed') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('common.completed') }}</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('common.cancelled') }}</option>
                </select>
            </div>

            <div>
                <label for="filter-date" class="admin-label">{{ __('common.date') }}</label>
                <input id="filter-date" type="date" name="date" value="{{ request('date') }}" class="admin-input">
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:col-span-2">
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_list</span>
                    {{ __('admin.apply_filter') }}
                </button>
                @if(request()->hasAny(['status', 'date']))
                    <a href="{{ route('admin.bookings.index') }}" class="admin-btn-ghost">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">restart_alt</span>
                        {{ __('admin.reset_filter_short') }}
                    </a>
                @endif
                <a href="{{ route('admin.bookings.export', request()->only(['status', 'date'])) }}" target="_blank" rel="noopener"
                   class="admin-btn-secondary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">print</span>
                    {{ __('admin.export_pdf') }}
                </a>
            </div>
        </form>
    </x-admin.panel>

    {{-- Tabel booking --}}
    <x-admin.panel :title="__('admin.bookings_title')" :subtitle="__('admin.bookings_desc')" flush>
        <x-slot:actions>
            <x-admin.badge :label="__('admin.total') . ' · ' . $bookings->total()" tone="slate" />
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1040px] text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('admin.col_booking') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_pet_owner') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_doctor') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_service') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_schedule') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_payment') }}</th>
                        <th class="px-5 py-3">{{ __('common.status') }}</th>
                        <th class="px-5 py-3 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($bookings as $booking)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                   class="text-sm font-semibold text-slate-800 transition hover:text-primary dark:text-slate-100">
                                    #{{ $booking->id }}
                                </a>
                                <p class="mt-0.5 text-[10px] text-slate-400">{{ $booking->created_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2.5">
                                    @if($booking->pet?->photo_url)
                                        <img class="h-8 w-8 rounded-full object-cover" src="{{ $booking->pet->photo_url }}" alt="">
                                    @else
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30"
                                              aria-hidden="true">
                                            <span class="material-symbols-outlined text-[16px]">pets</span>
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">
                                            {{ $booking->pet->name ?? __('admin.pet_placeholder') }}
                                        </p>
                                        <a href="{{ route('admin.users.show', $booking->user_id) }}"
                                           class="truncate text-[11px] text-slate-500 transition hover:text-primary dark:text-slate-400">
                                            {{ $booking->user->name ?? __('admin.owner_placeholder') }}
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $booking->doctor->name ?? '—' }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ $booking->service?->name ?? '—' }}
                                </span>
                                @if($booking->service?->price)
                                    <span class="block text-[11px] tabular-nums text-slate-400">
                                        Rp {{ number_format($booking->service->price, 0, ',', '.') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-sm text-slate-700 dark:text-slate-300">
                                    {{ \Carbon\Carbon::parse($booking->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                </p>
                                <p class="text-[11px] tabular-nums text-slate-400">
                                    {{ \Carbon\Carbon::parse($booking->booking_time)->format('H:i') }}
                                </p>
                            </td>
                            <td class="px-5 py-3">
                                @if($booking->payment_status)
                                    <div class="flex flex-col items-start gap-1">
                                        <x-admin.badge
                                            :label="\App\Support\AdminStatus::paymentLabel($booking->payment_status)"
                                            :tone="\App\Support\AdminStatus::paymentTone($booking->payment_status)" />
                                        @if($booking->total_amount > 0)
                                            <span class="text-[11px] tabular-nums text-slate-500">
                                                Rp {{ number_format($booking->total_amount, 0, ',', '.') }}
                                                @if($booking->remaining_amount > 0)
                                                    <span class="text-orange-600 dark:text-orange-400">
                                                        · {{ __('admin.remaining_amount_short', ['amount' => number_format($booking->remaining_amount, 0, ',', '.')]) }}
                                                    </span>
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="\App\Support\AdminStatus::bookingLabel($booking->status)"
                                    :tone="\App\Support\AdminStatus::bookingTone($booking->status)"
                                    dot />
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                       class="admin-icon-btn hover:!text-primary"
                                       title="{{ __('admin.view_detail') }}"
                                       aria-label="{{ __('admin.view_detail') }} #{{ $booking->id }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
                                    </a>

                                    @if($booking->status === 'pending')
                                        <form method="POST" action="{{ route('admin.bookings.confirm', $booking->id) }}" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="admin-icon-btn hover:!text-emerald-600"
                                                    title="{{ __('admin.confirm_booking_short') }}"
                                                    aria-label="{{ __('admin.confirm_booking_short') }} #{{ $booking->id }}">
                                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">check_circle</span>
                                            </button>
                                        </form>
                                    @endif

                                    @if(in_array($booking->status, ['pending', 'confirmed'], true))
                                        <form method="POST" action="{{ route('admin.bookings.complete', $booking->id) }}" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="admin-icon-btn hover:!text-emerald-600"
                                                    title="{{ __('admin.mark_complete') }}"
                                                    aria-label="{{ __('admin.mark_complete') }} #{{ $booking->id }}">
                                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">task_alt</span>
                                            </button>
                                        </form>

                                        <button type="button"
                                                data-cancel-booking="{{ $booking->id }}"
                                                data-cancel-pet="{{ $booking->pet->name ?? __('admin.pet_placeholder') }}"
                                                class="admin-icon-btn hover:!text-rose-600"
                                                title="{{ __('admin.cancel_booking_action') }}"
                                                aria-label="{{ __('admin.cancel_booking_action') }} #{{ $booking->id }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">cancel</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state
                                    :title="request()->hasAny(['status', 'date']) ? __('admin.bookings_filtered_title') : __('admin.bookings_empty_title')"
                                    :description="request()->hasAny(['status', 'date']) ? __('admin.bookings_filtered_desc') : __('admin.bookings_empty_desc')"
                                    icon="event_none"
                                    tone="slate">
                                    <x-slot:actions>
                                        @if(request()->hasAny(['status', 'date']))
                                            <a href="{{ route('admin.bookings.index') }}" class="admin-btn-secondary">
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">restart_alt</span>
                                                {{ __('admin.reset_filter_short') }}
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.bookings.schedule') }}" class="admin-btn-primary">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">view_timeline</span>
                                            {{ __('admin.open_board') }}
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
            {{ $bookings->withQueryString()->links() }}
        </x-slot:footer>
    </x-admin.panel>
</div>

{{-- Modal pembatalan --}}
<div id="cancelModal" class="fixed inset-0 z-50 hidden bg-slate-950/40 px-4 py-10 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
    <div class="mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center gap-2 border-b border-slate-100 p-5 dark:border-slate-800">
            <span class="material-symbols-outlined text-rose-500" aria-hidden="true">cancel</span>
            <h3 id="cancelModalTitle" class="text-sm font-bold text-slate-900 dark:text-white">{{ __('admin.cancel_booking_title') }}</h3>
        </div>

        <form id="cancelForm" method="POST" class="p-5">
            @csrf
            <p id="cancelTarget" class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('admin.cancel_booking_desc') }}</p>

            <label for="cancel-reason" class="admin-label">
                {{ __('admin.cancel_reason_label') }} <span class="text-rose-500">*</span>
            </label>
            <textarea id="cancel-reason" name="reason" required rows="3"
                      class="admin-input"
                      placeholder="{{ __('admin.cancel_reason_placeholder') }}"></textarea>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" id="cancelModalClose" class="admin-btn-secondary">
                    {{ __('common.back') }}
                </button>
                <button type="submit" class="admin-btn-danger">
                    {{ __('admin.cancel_booking_action') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('cancelModal');
        const form = document.getElementById('cancelForm');
        const target = document.getElementById('cancelTarget');
        const reason = document.getElementById('cancel-reason');
        const cancelBase = @json(route('admin.bookings.index'));
        const cancelTemplate = @json(__('admin.cancel_booking_target', ['pet' => '__PET__']));

        function hideCancelModal() {
            modal.classList.add('hidden');
        }

        // The close button lives inside this IIFE's scope, so it must be
        // wired here rather than through an inline onclick (which previously
        // threw "hideCancelModal is not defined").
        document.getElementById('cancelModalClose')?.addEventListener('click', hideCancelModal);

        document.querySelectorAll('[data-cancel-booking]').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-cancel-booking');
                const pet = button.getAttribute('data-cancel-pet') || '';
                form.action = cancelBase + '/' + id + '/cancel';
                target.textContent = cancelTemplate.replace('__PET__', pet);
                reason.value = '';
                modal.classList.remove('hidden');
                reason.focus();
            });
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) hideCancelModal();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') hideCancelModal();
        });
    })();
</script>
@endsection
