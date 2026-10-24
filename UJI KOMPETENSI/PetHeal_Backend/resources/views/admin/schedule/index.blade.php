@extends('layouts.admin')

@section('title', __('menu.schedule') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.appointments_schedule'))

@section('content')
<div class="space-y-6">
    @include('admin.bookings._tabs')

    {{-- Navigasi tanggal & dokter --}}
    <x-admin.panel :title="__('admin.schedule_title')" :subtitle="__('admin.schedule_selected_date')">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="flex flex-wrap items-end gap-2">
                <a href="{{ route('admin.bookings.schedule', ['date' => $previousDate, 'doctor_id' => $selectedDoctorId]) }}"
                   class="admin-icon-btn h-10 w-10"
                   title="{{ __('admin.prev_day') }}"
                   aria-label="{{ __('admin.prev_day') }}">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">chevron_left</span>
                </a>

                <form method="GET" action="{{ route('admin.bookings.schedule') }}" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="schedule-date" class="admin-label">{{ __('admin.schedule_date') }}</label>
                        <input id="schedule-date" type="date" name="date" value="{{ $selectedDate }}" class="admin-input">
                    </div>
                    <div>
                        <label for="schedule-doctor" class="admin-label">{{ __('admin.schedule_doctor') }}</label>
                        <select id="schedule-doctor" name="doctor_id" class="admin-input">
                            <option value="">{{ __('admin.schedule_doctor') }}</option>
                            @foreach($doctors as $doctor)
                                <option value="{{ $doctor->id }}" {{ (string) $selectedDoctorId === (string) $doctor->id ? 'selected' : '' }}>
                                    {{ $doctor->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="admin-btn-primary">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">search</span>
                        {{ __('admin.schedule_show') }}
                    </button>
                </form>

                <a href="{{ route('admin.bookings.schedule', ['date' => now()->toDateString(), 'doctor_id' => $selectedDoctorId]) }}"
                   class="admin-btn-secondary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">today</span>
                    {{ __('admin.schedule_today') }}
                </a>

                <a href="{{ route('admin.bookings.schedule', ['date' => $nextDate, 'doctor_id' => $selectedDoctorId]) }}"
                   class="admin-icon-btn h-10 w-10"
                   title="{{ __('admin.next_day') }}"
                   aria-label="{{ __('admin.next_day') }}">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">chevron_right</span>
                </a>
            </div>

            <div class="flex items-center gap-3">
                <div class="text-right">
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">
                        {{ $isToday ? __('admin.schedule_today') : __('admin.schedule_selected_date') }}
                    </p>
                    <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">
                        {{ \Carbon\Carbon::parse($selectedDate)->locale(app()->getLocale())->translatedFormat('l, d F Y') }}
                    </p>
                </div>
                <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                    <span class="material-symbols-outlined text-[22px]" aria-hidden="true">calendar_month</span>
                </span>
            </div>
        </div>
    </x-admin.panel>

    {{-- Ringkasan hari ini --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-admin.stat-card :label="__('admin.stat_total_visits')" :value="$stats['total']" icon="event_available" tone="slate" />
        <x-admin.stat-card :label="__('admin.filter_status_pending')" :value="$stats['pending']" icon="hourglass_top" tone="amber" />
        <x-admin.stat-card :label="__('admin.filter_status_confirmed')" :value="$stats['confirmed']" icon="event_upcoming" tone="sky" />
        <x-admin.stat-card :label="__('common.completed')" :value="$stats['completed']" icon="task_alt" tone="primary" />
    </div>

    {{-- Papan jadwal per dokter --}}
    @if($doctors->isEmpty())
        <x-admin.panel>
            <x-admin.empty-state
                :title="__('admin.schedule_no_doctors_title')"
                :description="__('admin.schedule_no_doctors_desc')"
                icon="event_busy"
                tone="amber">
                <x-slot:actions>
                    <a href="{{ route('admin.doctors.index') }}" class="admin-btn-primary">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                        {{ __('admin.manage_doctors') }}
                    </a>
                </x-slot:actions>
            </x-admin.empty-state>
        </x-admin.panel>
    @else
        <div class="overflow-x-auto pb-3">
            <div class="flex items-start gap-4 min-w-max">
                @foreach($doctors as $doctor)
                    @php
                        $doctorBookings = $bookingsByDoctor[$doctor->id] ?? collect();
                        $doctorSchedule = $effectiveSchedules[$doctor->id] ?? null;
                        $isClosed = (bool) ($doctorSchedule['is_closed'] ?? false);
                        $isWorking = (bool) ($doctorSchedule['is_working_day'] ?? true);
                        $isSpecial = (bool) ($doctorSchedule['has_exception'] ?? false);
                        $reviewCount = $doctorBookings->filter(fn ($b) => isset($bookingsNeedingReview[$b->id]))->count();
                    @endphp
                    <section class="flex w-[310px] flex-shrink-0 flex-col overflow-hidden rounded-2xl border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900"
                             aria-label="{{ __('admin.schedule_doctor_aria', ['name' => $doctor->name]) }}">
                        <header class="border-b border-slate-100 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-800/30">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">medical_services</span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <h3 class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $doctor->name }}</h3>
                                        @if(!$doctor->is_active)
                                            <x-admin.badge :label="__('common.inactive')" tone="slate" />
                                        @endif
                                        @if($isClosed)
                                            <x-admin.badge :label="__('admin.day_off')" tone="rose" />
                                        @elseif($isSpecial)
                                            <x-admin.badge :label="__('admin.special_hours')" tone="amber" />
                                        @elseif(!$isWorking)
                                            <x-admin.badge :label="__('admin.not_working')" tone="slate" />
                                        @endif
                                    </div>
                                    <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">
                                        {{ $doctor->specialization ?? __('admin.veterinarian') }}
                                    </p>
                                </div>
                                <x-admin.badge :label="__('admin.appointments_count', ['count' => $doctorBookings->count()])" tone="blue" />
                            </div>

                            <div class="mt-3 flex items-start justify-between gap-2">
                                <p class="mt-0.5 flex items-start gap-1 text-[10px] {{ $isClosed ? 'text-rose-600' : 'text-slate-400' }}">
                                    <span class="material-symbols-outlined text-[13px]" aria-hidden="true">
                                        {{ $isClosed ? 'event_busy' : 'schedule' }}
                                    </span>
                                    <span class="leading-snug">
                                        @if($isClosed)
                                            {{ __('admin.full_day_off') }}{{ !empty($doctorSchedule['reason']) ? ' · ' . $doctorSchedule['reason'] : '' }}
                                        @elseif($isWorking)
                                            {{ $doctorSchedule['start_time'] }} - {{ $doctorSchedule['end_time'] }} WIB
                                        @else
                                            {{ __('admin.no_practice_hours') }}
                                        @endif
                                    </span>
                                </p>
                                <a href="{{ route('admin.doctors.schedule', ['id' => $doctor->id, 'date' => $selectedDate]) }}"
                                   class="inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-emerald-300 hover:text-emerald-600 dark:border-slate-700 dark:bg-slate-800"
                                   title="{{ __('admin.setup_schedule') }}"
                                   aria-label="{{ __('admin.set_schedule_aria', ['name' => $doctor->name]) }}">
                                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">tune</span>
                                </a>
                            </div>

                            @if($reviewCount > 0)
                                <p class="mt-2 flex items-start gap-1 rounded-lg border border-rose-100 bg-rose-50 px-2 py-1.5 text-[10px] font-medium text-rose-700">
                                    <span class="material-symbols-outlined text-[13px]" aria-hidden="true">notification_important</span>
                                    {{ __('admin.review_outside_schedule', ['count' => $reviewCount]) }}
                                </p>
                            @endif
                        </header>

                        <div class="custom-scrollbar max-h-[620px] flex-1 space-y-3 overflow-y-auto p-3">
                            @forelse($doctorBookings as $booking)
                                <article class="rounded-xl border border-slate-200/80 bg-white p-3.5 transition hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900">
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-12 w-12 flex-shrink-0 flex-col items-center justify-center rounded-xl bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900">
                                            <span class="text-[13px] font-bold leading-none">{{ $booking->booking_time }}</span>
                                            <span class="mt-0.5 text-[8px] uppercase tracking-wider opacity-70">WIB</span>
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-bold text-slate-900 dark:text-white">
                                                        {{ $booking->pet->name ?? __('admin.pet_placeholder') }}
                                                    </p>
                                                    <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">
                                                        {{ $booking->user->name ?? __('admin.owner_placeholder') }}
                                                    </p>
                                                </div>
                                                <x-admin.badge
                                                    :label="\App\Support\AdminStatus::bookingLabel($booking->status)"
                                                    :tone="\App\Support\AdminStatus::bookingTone($booking->status)" />
                                            </div>

                                            <p class="mt-2 truncate text-[11px] text-slate-500 dark:text-slate-400">
                                                {{ $booking->service->name ?? __('admin.clinic_service') }}
                                            </p>

                                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                                <x-admin.badge
                                                    :label="\App\Support\AdminStatus::paymentLabel($booking->payment_status)"
                                                    :tone="\App\Support\AdminStatus::paymentTone($booking->payment_status)" />
                                                @if($booking->payment_status !== 'paid' && $booking->remaining_amount > 0)
                                                    <span class="text-[10px] tabular-nums text-slate-400">
                                                        {{ __('admin.remaining_amount_short', ['amount' => number_format($booking->remaining_amount, 0, ',', '.')]) }}
                                                    </span>
                                                @endif
                                                @if(isset($bookingsNeedingReview[$booking->id]))
                                                    <x-admin.badge :label="__('admin.outside_schedule')" tone="rose" />
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center gap-1.5 border-t border-slate-100 pt-3 dark:border-slate-800">
                                        <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                           class="flex-1 rounded-lg bg-slate-100 px-2 py-1.5 text-center text-[11px] font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                                            {{ __('admin.detail') }}
                                        </a>

                                        @if($booking->status === 'pending')
                                            <form method="POST" action="{{ route('admin.bookings.confirm', $booking->id) }}" class="flex-1">
                                                @csrf
                                                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-2 py-1.5 text-[11px] font-semibold text-white transition hover:bg-emerald-700">
                                                    {{ __('admin.confirm_booking_short') }}
                                                </button>
                                            </form>
                                        @elseif($booking->status === 'confirmed')
                                            <form method="POST" action="{{ route('admin.bookings.complete', $booking->id) }}" class="flex-1">
                                                @csrf
                                                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-2 py-1.5 text-[11px] font-semibold text-white transition hover:bg-emerald-700">
                                                    {{ __('admin.mark_complete') }}
                                                </button>
                                            </form>
                                        @endif

                                        @if(in_array($booking->status, ['pending', 'confirmed'], true))
                                            <button type="button"
                                                    data-cancel-booking="{{ $booking->id }}"
                                                    data-cancel-pet="{{ $booking->pet->name ?? __('admin.pet_placeholder') }}"
                                                    class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                                    title="{{ __('admin.cancel_booking_action') }}"
                                                    aria-label="{{ __('admin.cancel_booking_action') }} {{ $booking->pet->name ?? '' }}">
                                                <span class="material-symbols-outlined text-[17px]" aria-hidden="true">cancel</span>
                                            </button>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center dark:border-slate-700">
                                    <span class="material-symbols-outlined text-3xl text-slate-300 dark:text-slate-600" aria-hidden="true">event_available</span>
                                    <p class="mt-2 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('admin.no_visits') }}</p>
                                    <p class="mt-1 text-[10px] text-slate-400">{{ __('admin.no_visits_desc') }}</p>
                                </div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</div>

{{-- Modal pembatalan --}}
<div id="cancelModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/40 px-4 py-10 backdrop-blur-sm"
     role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
    <div class="mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center gap-2 border-b border-slate-100 p-5 dark:border-slate-800">
            <span class="material-symbols-outlined text-rose-500" aria-hidden="true">cancel</span>
            <h3 id="cancelModalTitle" class="text-sm font-bold text-slate-900 dark:text-white">{{ __('admin.cancel_booking_title') }}</h3>
        </div>

        <form id="cancelForm" method="POST" class="p-5">
            @csrf
            <p id="cancelTarget" class="mb-4 text-sm text-slate-500 dark:text-slate-400">{{ __('admin.cancel_booking_intro') }}</p>

            <label for="cancel-reason" class="admin-label">
                {{ __('admin.cancel_reason_label') }} <span class="text-rose-500">*</span>
            </label>
            <textarea id="cancel-reason" name="reason" required rows="3"
                      class="admin-input"
                      placeholder="{{ __('admin.cancel_reason_placeholder') }}"></textarea>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="hideScheduleCancelModal()" class="admin-btn-secondary">
                    {{ __('common.cancel') }}
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

        window.hideScheduleCancelModal = function () {
            modal.classList.add('hidden');
        };

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
            if (event.target === modal) window.hideScheduleCancelModal();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') window.hideScheduleCancelModal();
        });
    })();
</script>
@endsection
