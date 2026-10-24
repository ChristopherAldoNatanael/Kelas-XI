@php
    $dayKeys = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $weeklyLabel = collect($dayKeys)
        ->filter(fn ($day) => in_array($day, $weeklyDays, true))
        ->map(fn ($day) => __('admin.' . $day))
        ->implode(', ');
    $nextException = $exceptions->first();
    $locale = app()->getLocale();
@endphp

@extends('layouts.admin')

@section('title', __('admin.doctor_schedule_title') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.doctor_schedule_title'))

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        :title="$doctor->name"
        :eyebrow="__('admin.eyebrow_operations')"
        icon="edit_calendar"
        :description="($doctor->specialization ?: __('admin.veterinarian')) . ' · ' . __('admin.doctor_schedule_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.bookings.schedule', ['date' => $prefillDate, 'doctor_id' => $doctor->id]) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">calendar_month</span>
                {{ __('admin.open_schedule_board') }}
            </a>
            <a href="{{ route('admin.doctors.show', $doctor->id) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">stethoscope</span>
                {{ __('admin.doctor_profile_link') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @foreach(['success' => 'check_circle', 'warning' => 'warning', 'error' => 'error'] as $flashKey => $flashIcon)
        @if(session($flashKey))
            <div class="flex items-start gap-3 rounded-xl border px-5 py-4 text-sm font-medium
                {{ $flashKey === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : '' }}
                {{ $flashKey === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : '' }}
                {{ $flashKey === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : '' }}">
                <span class="material-symbols-outlined shrink-0 text-[20px]" aria-hidden="true">{{ $flashIcon }}</span>
                <span class="leading-relaxed">{{ session($flashKey) }}</span>
            </div>
        @endif
    @endforeach

    {{-- Ringkasan --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-admin.stat-card
            :label="__('admin.weekly_schedule')"
            :value="$weeklyLabel !== '' ? $weeklyLabel : __('admin.no_practice_days')"
            icon="calendar_month"
            tone="blue"
            :hint="$doctor->start_time . ' - ' . $doctor->end_time . ' WIB'" />

        <x-admin.stat-card
            :label="__('admin.next_leave')"
            :value="$nextException
                ? \Carbon\Carbon::parse($nextException->date)->locale($locale)->translatedFormat('l, d F Y')
                : __('admin.no_leave_scheduled')"
            icon="event_busy"
            tone="rose"
            :hint="$nextException
                ? ($nextException->is_closed ? __('admin.full_day_off') : $nextException->start_time . ' - ' . $nextException->end_time)
                : __('admin.follows_weekly_schedule')" />

        <x-admin.stat-card
            :label="__('admin.upcoming_bookings')"
            :value="$upcomingBookings->count()"
            icon="pending_actions"
            tone="amber"
            :hint="__('admin.upcoming_bookings_hint')" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">

        {{-- Daftar pengecualian --}}
        <x-admin.panel class="xl:col-span-3" :title="__('admin.exceptions_title')" :subtitle="__('admin.exceptions_desc')" flush>
            <x-slot:actions>
                <x-admin.badge :label="__('admin.dates_count', ['count' => $exceptions->count()])" tone="slate" />
            </x-slot:actions>

            @if($exceptions->isEmpty())
                <x-admin.empty-state
                    :title="__('admin.no_exceptions_title')"
                    :description="__('admin.no_exceptions_desc')"
                    icon="event_available"
                    tone="slate" />
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($exceptions as $exception)
                        @php
                            $conflicts = (int) ($conflictsByDate[$exception->date] ?? 0);
                            $exceptionDate = \Carbon\Carbon::parse($exception->date);
                        @endphp
                        <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:p-5">
                            <div class="w-16 shrink-0 rounded-xl border border-slate-200 bg-slate-50 py-2 text-center dark:border-slate-700 dark:bg-slate-800/50">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                    {{ $exceptionDate->locale($locale)->translatedFormat('M') }}
                                </p>
                                <p class="text-xl font-bold leading-none text-slate-900 dark:text-white">{{ $exceptionDate->format('d') }}</p>
                                <p class="mt-1 text-[10px] text-slate-400">{{ $exceptionDate->locale($locale)->translatedFormat('D') }}</p>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-bold text-slate-900 dark:text-white">
                                        {{ $exceptionDate->locale($locale)->translatedFormat('l, d F Y') }}
                                    </p>
                                    @if($exception->is_closed)
                                        <x-admin.badge :label="__('admin.day_off')" tone="rose" />
                                    @else
                                        <x-admin.badge :label="__('admin.special_hours')" tone="amber" />
                                    @endif
                                </div>

                                <p class="mt-1 flex items-center gap-1 text-xs text-slate-500">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">schedule</span>
                                    {{ $exception->is_closed ? __('admin.full_day_off') : $exception->start_time . ' - ' . $exception->end_time . ' WIB' }}
                                </p>

                                @if($exception->reason)
                                    <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ $exception->reason }}</p>
                                @endif

                                @if($conflicts > 0)
                                    <a href="{{ route('admin.bookings.schedule', ['date' => $exception->date]) }}"
                                       class="mt-3 inline-flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 transition hover:bg-amber-100">
                                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">notification_important</span>
                                        {{ __('admin.review_outside_schedule', ['count' => $conflicts]) }}
                                    </a>
                                @endif
                            </div>

                            <form method="POST"
                                  action="{{ route('admin.doctors.schedule-exceptions.destroy', ['id' => $doctor->id, 'exception' => $exception->id]) }}"
                                  onsubmit="return confirm(@js(__('admin.delete_exception_confirm', ['date' => $exceptionDate->format('d/m/Y')])))"
                                  class="shrink-0">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="admin-icon-btn h-9 w-9 hover:bg-rose-50 hover:text-rose-600"
                                        title="{{ __('admin.delete_exception_aria', ['date' => $exceptionDate->format('d/m/Y')]) }}"
                                        aria-label="{{ __('admin.delete_exception_aria', ['date' => $exceptionDate->format('d/m/Y')]) }}">
                                    <span class="material-symbols-outlined text-[19px]" aria-hidden="true">delete</span>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-admin.panel>

        {{-- Form pengecualian --}}
        <div class="xl:col-span-2">
            <x-admin.panel :title="__('admin.set_one_date')" :subtitle="__('admin.set_one_date_desc')">
                @php
                    // Nilai awal dirender di server supaya label tetap benar sebelum
                    // JavaScript jalan, dan tetap aman bila `old()` berisi nilai rusak.
                    $initialDate = old('date', $prefillDate);
                    $initialDateLabel = __('admin.pick_date');
                    if (is_string($initialDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $initialDate)) {
                        $initialDateLabel = \Carbon\Carbon::parse($initialDate)->locale($locale)->translatedFormat('j F Y');
                    } else {
                        $initialDate = '';
                    }
                @endphp

                <form id="exception-form" method="POST" action="{{ route('admin.doctors.schedule-exceptions.store', $doctor->id) }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="exception-date-trigger" class="admin-label">{{ __('admin.date_label') }}</label>
                        <button type="button" id="exception-date-trigger"
                                aria-haspopup="dialog" aria-controls="date-modal" aria-expanded="false"
                                class="flex w-full items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-left transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="material-symbols-outlined text-[18px] text-slate-400" aria-hidden="true">calendar_month</span>
                                <span id="exception-date-label" class="truncate text-sm {{ $initialDate ? 'text-slate-800 dark:text-slate-100' : 'text-slate-400' }}">{{ $initialDateLabel }}</span>
                            </span>
                            <span class="material-symbols-outlined text-[18px] text-slate-400" aria-hidden="true">expand_more</span>
                        </button>
                        {{-- Nilai asli form tetap format YYYY-MM-DD agar validasi server tidak berubah. --}}
                        <input id="exception-date" type="hidden" name="date" value="{{ $initialDate }}" data-min="{{ now()->toDateString() }}">
                        <p id="exception-date-error" class="admin-error hidden">{{ __('admin.date_required') }}</p>
                        @error('date')
                            <p class="admin-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <fieldset>
                        <legend class="admin-label">{{ __('admin.mode_label') }}</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="cursor-pointer">
                                <input type="radio" name="mode" value="closed" class="peer sr-only"
                                       {{ old('mode', 'closed') === 'closed' ? 'checked' : '' }}>
                                <span class="flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-600 transition-colors peer-checked:border-rose-300 peer-checked:bg-rose-50 peer-checked:text-rose-700 dark:border-slate-700 dark:text-slate-300">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">event_busy</span>
                                    {{ __('admin.closed_full_day') }}
                                </span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="mode" value="custom" class="peer sr-only"
                                       {{ old('mode') === 'custom' ? 'checked' : '' }}>
                                <span class="flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-600 transition-colors peer-checked:border-amber-300 peer-checked:bg-amber-50 peer-checked:text-amber-700 dark:border-slate-700 dark:text-slate-300">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">schedule</span>
                                    {{ __('admin.custom_hours') }}
                                </span>
                            </label>
                        </div>
                        @error('mode')
                            <p class="admin-error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div id="custom-hours" class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="exception-start" class="admin-label">{{ __('admin.start_time') }}</label>
                            <input id="exception-start" type="time" name="start_time"
                                   value="{{ old('start_time', $doctor->start_time ? substr($doctor->start_time, 0, 5) : '08:00') }}"
                                   class="admin-input">
                            @error('start_time')
                                <p class="admin-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="exception-end" class="admin-label">{{ __('admin.end_time') }}</label>
                            <input id="exception-end" type="time" name="end_time"
                                   value="{{ old('end_time', $doctor->end_time ? substr($doctor->end_time, 0, 5) : '17:00') }}"
                                   class="admin-input">
                            @error('end_time')
                                <p class="admin-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="exception-reason" class="admin-label">
                            {{ __('admin.reason_label') }}
                            <span class="font-normal normal-case text-slate-400">{{ __('admin.reason_optional') }}</span>
                        </label>
                        <textarea id="exception-reason" name="reason" rows="2" maxlength="160"
                                  placeholder="{{ __('admin.reason_placeholder') }}"
                                  class="admin-input resize-none">{{ old('reason') }}</textarea>
                        @error('reason')
                            <p class="admin-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-start gap-2 rounded-xl border border-slate-200/70 bg-slate-50 px-3.5 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                        <span class="material-symbols-outlined text-[17px] text-slate-400" aria-hidden="true">info</span>
                        <p class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">{{ __('admin.no_auto_cancel_note') }}</p>
                    </div>

                    <button type="submit" class="admin-btn-primary w-full justify-center">
                        <span class="material-symbols-outlined text-[19px]" aria-hidden="true">save</span>
                        {{ __('admin.save_exception') }}
                    </button>
                </form>
            </x-admin.panel>
        </div>
    </div>
</div>

{{-- Pemilih tanggal bergaya modal: tampilan hari, bulan, lalu tahun. --}}
<div id="date-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="date-modal-title">
    <div class="absolute inset-0 bg-slate-900/50" data-date-close></div>
    <div id="date-modal-panel" class="relative max-h-[calc(100vh-2rem)] w-full max-w-sm overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-xl sm:p-5 dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between gap-2">
            <button type="button" id="date-prev" title="{{ __('admin.prev_day') }}" aria-label="{{ __('admin.prev_day') }}"
                    class="admin-icon-btn h-9 w-9">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">chevron_left</span>
            </button>
            <div class="flex items-center gap-1">
                <button type="button" id="date-month-btn" class="rounded-lg px-2.5 py-1.5 text-sm font-bold text-slate-800 transition hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-800">—</button>
                <button type="button" id="date-year-btn" class="rounded-lg px-2.5 py-1.5 text-sm font-bold text-slate-800 transition hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-800">—</button>
            </div>
            <button type="button" id="date-next" title="{{ __('admin.next_day') }}" aria-label="{{ __('admin.next_day') }}"
                    class="admin-icon-btn h-9 w-9">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">chevron_right</span>
            </button>
        </div>

        <div id="date-days" class="mt-3">
            <div id="date-weekdays" class="mb-1 grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase tracking-wide text-slate-400"></div>
            <div id="date-grid" class="grid grid-cols-7 gap-1"></div>
        </div>

        <div id="date-months" class="mt-3 hidden">
            <div id="date-months-grid" class="grid grid-cols-3 gap-1.5"></div>
        </div>

        <div id="date-years" class="mt-3 hidden">
            <div id="date-years-grid" class="grid grid-cols-3 gap-1.5"></div>
        </div>

        <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-200 pt-3 dark:border-slate-700">
            <button type="button" id="date-today" class="rounded-lg px-3 py-2 text-xs font-semibold text-primary transition hover:bg-emerald-50">{{ __('admin.schedule_today') }}</button>
            <button type="button" data-date-close class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-500 transition hover:bg-slate-100 dark:hover:bg-slate-800">{{ __('common.close') }}</button>
        </div>
    </div>
</div>

<script>
    (function () {
        const customHours = document.getElementById('custom-hours');
        const startInput = document.getElementById('exception-start');
        const endInput = document.getElementById('exception-end');
        const radios = document.querySelectorAll('input[name="mode"]');

        function syncMode() {
            const mode = document.querySelector('input[name="mode"]:checked');
            const isCustom = mode && mode.value === 'custom';
            customHours.classList.toggle('hidden', !isCustom);
            // Cuti tidak membutuhkan jam. `required` dilepas supaya form tetap
            // bisa dikirim, dan server menyimpan NULL untuk start/end.
            startInput.required = isCustom;
            endInput.required = isCustom;
        }

        radios.forEach(radio => radio.addEventListener('change', syncMode));
        syncMode();
    })();

    // Pemilih tanggal modal: hari → bulan → tahun, tanpa dependensi eksternal.
    // Nilai disimpan ke input hidden berformat YYYY-MM-DD agar kontrak server
    // tetap sama; yang berubah hanya cara admin memilih tanggal.
    (function () {
        const trigger = document.getElementById('exception-date-trigger');
        const labelEl = document.getElementById('exception-date-label');
        const input = document.getElementById('exception-date');
        const errorEl = document.getElementById('exception-date-error');
        const form = document.getElementById('exception-form');
        const modal = document.getElementById('date-modal');
        const prevBtn = document.getElementById('date-prev');
        const nextBtn = document.getElementById('date-next');
        const monthBtn = document.getElementById('date-month-btn');
        const yearBtn = document.getElementById('date-year-btn');
        const daysView = document.getElementById('date-days');
        const monthsView = document.getElementById('date-months');
        const yearsView = document.getElementById('date-years');
        const weekdaysEl = document.getElementById('date-weekdays');
        const grid = document.getElementById('date-grid');
        const monthsGrid = document.getElementById('date-months-grid');
        const yearsGrid = document.getElementById('date-years-grid');
        const todayBtn = document.getElementById('date-today');

        // Nama bulan & hari weekday datang dari file bahasa supaya picker
        // mengikuti locale admin yang sedang aktif.
        const MONTHS = @json(__('admin.month_names'));
        const MONTHS_SHORT = @json(__('admin.month_names_short'));
        const WEEKDAYS = @json(__('admin.weekday_short'));
        const LOCALE = @json($locale === 'id' ? 'id-ID' : 'en-US');
        const PICK_DATE = @json(__('admin.pick_date'));

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const minISO = input.dataset.min || toISO(today);
        const min = parseISO(minISO);

        let view = 'days';
        let cursor = new Date(today.getFullYear(), today.getMonth(), 1);

        function toISO(date) {
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return date.getFullYear() + '-' + m + '-' + d;
        }

        function parseISO(value) {
            const parts = String(value || '').split('-');
            if (parts.length !== 3) return null;
            const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            return isNaN(date.getTime()) ? null : date;
        }

        function addMonths(date, count) {
            return new Date(date.getFullYear(), date.getMonth() + count, 1);
        }

        function button(text, className) {
            const el = document.createElement('button');
            el.type = 'button';
            el.textContent = text;
            el.className = 'h-9 rounded-lg text-[13px] font-semibold transition-colors ' + className;
            return el;
        }

        function updateLabel() {
            const date = parseISO(input.value);
            if (!date) {
                labelEl.textContent = PICK_DATE;
                labelEl.className = 'text-sm text-slate-400 truncate';
                return;
            }
            labelEl.textContent = date.toLocaleDateString(LOCALE, { day: 'numeric', month: 'long', year: 'numeric' });
            labelEl.className = 'text-sm text-slate-800 dark:text-slate-100 truncate';
        }

        function renderHeader() {
            monthBtn.textContent = MONTHS[cursor.getMonth()];
            monthBtn.classList.toggle('hidden', view === 'years');
            yearBtn.textContent = String(cursor.getFullYear());
        }

        function renderDays() {
            weekdaysEl.innerHTML = '';
            WEEKDAYS.forEach(function (day) {
                const el = document.createElement('span');
                el.textContent = day;
                weekdaysEl.appendChild(el);
            });

            grid.innerHTML = '';
            const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            const offset = (first.getDay() + 6) % 7; // Senin sebagai kolom pertama
            const daysInMonth = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
            const cells = Math.ceil((offset + daysInMonth) / 7) * 7;
            const selectedISO = input.value;

            for (let i = 0; i < cells; i++) {
                const date = new Date(cursor.getFullYear(), cursor.getMonth(), 1 - offset + i);
                const dateISO = toISO(date);
                const isSelected = dateISO === selectedISO;
                const isToday = dateISO === toISO(today);
                const isDisabled = dateISO < minISO;
                const outside = date.getMonth() !== cursor.getMonth();

                let className = 'h-9 rounded-lg text-[13px] font-semibold transition-colors ';
                if (isSelected) {
                    className += 'bg-primary text-white';
                } else if (isDisabled) {
                    className += 'text-slate-300 dark:text-slate-700 cursor-not-allowed';
                } else if (outside) {
                    className += 'text-slate-400 dark:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800';
                } else if (isToday) {
                    className += 'text-primary ring-1 ring-inset ring-primary/40 hover:bg-emerald-50';
                } else {
                    className += 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800';
                }

                const el = button(String(date.getDate()), className);
                el.disabled = isDisabled;
                el.setAttribute('aria-label', date.toLocaleDateString(LOCALE, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
                if (isSelected) el.setAttribute('aria-current', 'date');
                el.addEventListener('click', function () {
                    input.value = dateISO;
                    updateLabel();
                    errorEl.classList.add('hidden');
                    close();
                });
                grid.appendChild(el);
            }
        }

        function renderMonths() {
            monthsGrid.innerHTML = '';
            const selected = parseISO(input.value);
            for (let m = 0; m < 12; m++) {
                const endOfMonth = new Date(cursor.getFullYear(), m + 1, 0);
                const isDisabled = endOfMonth < min;
                const isSelected = selected && selected.getFullYear() === cursor.getFullYear() && selected.getMonth() === m;
                let className = 'h-11 rounded-xl border text-[13px] font-semibold transition-colors ';
                if (isSelected) {
                    className += 'bg-primary text-white border-primary';
                } else if (isDisabled) {
                    className += 'text-slate-300 dark:text-slate-700 border-transparent cursor-not-allowed';
                } else {
                    className += 'text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800';
                }
                const el = button(MONTHS_SHORT[m], className);
                el.disabled = isDisabled;
                el.addEventListener('click', function () {
                    cursor = new Date(cursor.getFullYear(), m, 1);
                    showView('days');
                });
                monthsGrid.appendChild(el);
            }
        }

        function renderYears() {
            yearsGrid.innerHTML = '';
            // 12 tahun per halaman dengan tahun aktif di tengah agar mundur/maju
            // sama jauhnya dari tahun yang sedang dipilih.
            const start = cursor.getFullYear() - 5;
            for (let i = 0; i < 12; i++) {
                const year = start + i;
                const isDisabled = new Date(year, 11, 31) < min;
                const isSelected = year === cursor.getFullYear();
                let className = 'h-11 rounded-xl border text-[13px] font-semibold transition-colors ';
                if (isSelected) {
                    className += 'bg-primary text-white border-primary';
                } else if (isDisabled) {
                    className += 'text-slate-300 dark:text-slate-700 border-transparent cursor-not-allowed';
                } else {
                    className += 'text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800';
                }
                const el = button(String(year), className);
                el.disabled = isDisabled;
                el.addEventListener('click', function () {
                    cursor = new Date(year, cursor.getMonth(), 1);
                    showView('months');
                });
                yearsGrid.appendChild(el);
            }
        }

        function render() {
            renderHeader();
            if (view === 'days') renderDays();
            if (view === 'months') renderMonths();
            if (view === 'years') renderYears();
        }

        function showView(next) {
            view = next;
            daysView.classList.toggle('hidden', next !== 'days');
            monthsView.classList.toggle('hidden', next !== 'months');
            yearsView.classList.toggle('hidden', next !== 'years');
            render();
        }

        function open() {
            const chosen = parseISO(input.value);
            const base = chosen || today;
            cursor = new Date(base.getFullYear(), base.getMonth(), 1);
            showView('days');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            trigger.setAttribute('aria-expanded', 'true');
            const focusTarget = grid.querySelector('[aria-current="date"]') || grid.querySelector('button:not([disabled])');
            if (focusTarget) focusTarget.focus();
        }

        function close() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        }

        // Satu langkah = satu bulan di tampilan hari, satu tahun di tampilan
        // bulan, dan satu halaman (12 tahun) di tampilan tahun.
        function step(direction) {
            const months = view === 'days' ? 1 : (view === 'months' ? 12 : 144);
            cursor = addMonths(cursor, direction * months);
            render();
        }

        prevBtn.addEventListener('click', function () { step(-1); });
        nextBtn.addEventListener('click', function () { step(1); });

        monthBtn.addEventListener('click', function () {
            showView(view === 'months' ? 'days' : 'months');
        });
        yearBtn.addEventListener('click', function () {
            showView(view === 'years' ? 'months' : 'years');
        });

        todayBtn.addEventListener('click', function () {
            input.value = toISO(today);
            updateLabel();
            errorEl.classList.add('hidden');
            close();
        });

        trigger.addEventListener('click', open);
        modal.querySelectorAll('[data-date-close]').forEach(function (el) {
            el.addEventListener('click', close);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) close();
        });

        // Hidden input tidak ikut validasi `required` browser, jadi dicek di sini
        // sebelum form dikirim. Aturan server tetap sumber kebenaran.
        form.addEventListener('submit', function (event) {
            if (!input.value) {
                event.preventDefault();
                open();
                errorEl.classList.remove('hidden');
            }
        });

        updateLabel();
    })();
</script>
@endsection
