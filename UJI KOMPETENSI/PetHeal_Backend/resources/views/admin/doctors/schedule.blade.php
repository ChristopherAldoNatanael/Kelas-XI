@extends('layouts.admin')

@section('title', 'Jadwal & Cuti Dokter')
@section('header', 'Jadwal & Cuti Dokter')

@section('content')
@php
    $dayLabels = [
        'monday' => 'Senin', 'tuesday' => 'Selasa', 'wednesday' => 'Rabu',
        'thursday' => 'Kamis', 'friday' => 'Jumat', 'saturday' => 'Sabtu',
        'sunday' => 'Minggu',
    ];
    $dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $weeklyLabel = collect($dayOrder)
        ->filter(fn ($day) => in_array($day, $weeklyDays, true))
        ->map(fn ($day) => $dayLabels[$day])
        ->implode(', ');
    $nextException = $exceptions->first();
@endphp

<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-6">
    <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 mb-2">Operasional dokter</p>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $doctor->name }}</h2>
        <p class="text-sm text-slate-500 mt-1">
            {{ $doctor->specialization ?: 'Dokter hewan' }} · atur cuti dan jam praktik khusus per tanggal.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.bookings.schedule', ['date' => $prefillDate, 'doctor_id' => $doctor->id]) }}"
           class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
            Buka Papan Jadwal
        </a>
        <a href="{{ route('admin.doctors.show', $doctor->id) }}"
           class="px-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
            Profil Dokter
        </a>
    </div>
</div>

@foreach(['success' => 'check_circle', 'warning' => 'warning', 'error' => 'error'] as $flashKey => $flashIcon)
    @if(session($flashKey))
        <div class="mb-4 flex items-start gap-3 px-5 py-4 rounded-xl border text-sm font-medium
            {{ $flashKey === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : '' }}
            {{ $flashKey === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-700' : '' }}
            {{ $flashKey === 'error' ? 'bg-rose-50 border-rose-200 text-rose-700' : '' }}">
            <span class="material-symbols-outlined text-[20px] shrink-0">{{ $flashIcon }}</span>
            <span class="leading-relaxed">{{ session($flashKey) }}</span>
        </div>
    @endif
@endforeach

{{-- Ringkasan jadwal --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="glass-card rounded-2xl border border-slate-200/60 p-5">
        <div class="flex items-center gap-2 text-slate-400">
            <span class="material-symbols-outlined text-[18px]">calendar_month</span>
            <p class="text-[10px] font-bold uppercase tracking-wider">Jadwal mingguan</p>
        </div>
        <p class="text-sm font-semibold text-slate-900 dark:text-white mt-2">
            {{ $weeklyLabel !== '' ? $weeklyLabel : 'Belum ada hari praktik' }}
        </p>
        <p class="text-xs text-slate-500 mt-1">{{ $doctor->start_time }} - {{ $doctor->end_time }} WIB</p>
    </div>

    <div class="glass-card rounded-2xl border border-slate-200/60 p-5">
        <div class="flex items-center gap-2 text-slate-400">
            <span class="material-symbols-outlined text-[18px]">event_busy</span>
            <p class="text-[10px] font-bold uppercase tracking-wider">Cuti terdekat</p>
        </div>
        @if($nextException)
            <p class="text-sm font-semibold text-slate-900 dark:text-white mt-2">
                {{ \Carbon\Carbon::parse($nextException->date)->locale('id')->translatedFormat('l, d F Y') }}
            </p>
            <p class="text-xs text-slate-500 mt-1">
                {{ $nextException->is_closed ? 'Sehari penuh' : $nextException->start_time . ' - ' . $nextException->end_time }}
            </p>
        @else
            <p class="text-sm font-semibold text-slate-900 dark:text-white mt-2">Belum ada cuti terjadwal</p>
            <p class="text-xs text-slate-500 mt-1">Dokter mengikuti jadwal mingguan.</p>
        @endif
    </div>

    <div class="glass-card rounded-2xl border border-slate-200/60 p-5">
        <div class="flex items-center gap-2 text-slate-400">
            <span class="material-symbols-outlined text-[18px]">pending_actions</span>
            <p class="text-[10px] font-bold uppercase tracking-wider">Booking mendatang</p>
        </div>
        <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $upcomingBookings->count() }}</p>
        <p class="text-xs text-slate-500 mt-1">Menunggu & terkonfirmasi, belum termasuk yang lewat.</p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
    {{-- Daftar pengecualian --}}
    <section class="xl:col-span-3 glass-card rounded-2xl border border-slate-200/60 overflow-hidden">
        <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Cuti &amp; jam khusus</h3>
                <p class="text-xs text-slate-500 mt-1">30 tanggal terdekat. Satu tanggal hanya punya satu pengecualian.</p>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500">
                {{ $exceptions->count() }} tanggal
            </span>
        </div>

        @if($exceptions->isEmpty())
            <div class="px-6 py-14 text-center">
                <span class="material-symbols-outlined text-5xl text-slate-300">event_available</span>
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300 mt-3">Belum ada pengecualian jadwal</p>
                <p class="text-xs text-slate-400 mt-1">Dokter mengikuti jadwal mingguan untuk semua tanggal.</p>
            </div>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($exceptions as $exception)
                    @php
                        $conflicts = (int) ($conflictsByDate[$exception->date] ?? 0);
                    @endphp
                    <li class="p-4 sm:p-5 flex flex-col gap-3 sm:flex-row sm:items-start">
                        <div class="w-16 shrink-0 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-center py-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                {{ \Carbon\Carbon::parse($exception->date)->locale('id')->translatedFormat('M') }}
                            </p>
                            <p class="text-xl font-bold text-slate-900 dark:text-white leading-none">
                                {{ \Carbon\Carbon::parse($exception->date)->format('d') }}
                            </p>
                            <p class="text-[10px] text-slate-400 mt-1">
                                {{ \Carbon\Carbon::parse($exception->date)->locale('id')->translatedFormat('D') }}
                            </p>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ \Carbon\Carbon::parse($exception->date)->locale('id')->translatedFormat('l, d F Y') }}
                                </p>
                                @if($exception->is_closed)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200">Cuti</span>
                                @else
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">Jam khusus</span>
                                @endif
                            </div>

                            <p class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">schedule</span>
                                {{ $exception->is_closed ? 'Sehari penuh' : $exception->start_time . ' - ' . $exception->end_time . ' WIB' }}
                            </p>

                            @if($exception->reason)
                                <p class="text-xs text-slate-600 dark:text-slate-300 mt-2 leading-relaxed">{{ $exception->reason }}</p>
                            @endif

                            @if($conflicts > 0)
                                <a href="{{ route('admin.bookings.schedule', ['date' => $exception->date]) }}"
                                   class="mt-3 inline-flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 hover:bg-amber-100 transition-colors">
                                    <span class="material-symbols-outlined text-[16px]">notification_important</span>
                                    {{ $conflicts }} booking aktif perlu ditinjau — booking tidak dibatalkan otomatis.
                                </a>
                            @endif
                        </div>

                        <form method="POST"
                              action="{{ route('admin.doctors.schedule-exceptions.destroy', ['id' => $doctor->id, 'exception' => $exception->id]) }}"
                              onsubmit="return confirm('Hapus pengecualian {{ \Carbon\Carbon::parse($exception->date)->format('d/m/Y') }}? Dokter kembali memakai jadwal mingguan pada tanggal itu.')"
                              class="shrink-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-9 h-9 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors inline-flex items-center justify-center"
                                    title="Hapus pengecualian" aria-label="Hapus pengecualian {{ $exception->date }}">
                                <span class="material-symbols-outlined text-[19px]">delete</span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Form --}}
    <section class="xl:col-span-2">
        <div class="glass-card rounded-2xl border border-slate-200/60 p-5 sm:p-6 xl:sticky xl:top-24">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">edit_calendar</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Atur satu tanggal</h3>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Pilih tanggal, lalu tandai cuti atau ganti jam praktik hanya untuk hari itu.
            </p>

            @php
                // Nilai awal dirender di server supaya label tetap benar sebelum
                // JavaScript jalan, dan tetap aman bila `old()` berisi nilai rusak.
                $initialDate = old('date', $prefillDate);
                $initialDateLabel = 'Pilih tanggal';
                if (is_string($initialDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $initialDate)) {
                    $initialDateLabel = \Carbon\Carbon::parse($initialDate)->locale('id')->translatedFormat('j F Y');
                } else {
                    $initialDate = '';
                }
            @endphp

            <form id="exception-form" method="POST" action="{{ route('admin.doctors.schedule-exceptions.store', $doctor->id) }}" class="mt-5 space-y-4">
                @csrf

                <div>
                    <label for="exception-date-trigger" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Tanggal</label>
                    <button type="button" id="exception-date-trigger"
                            aria-haspopup="dialog" aria-controls="date-modal" aria-expanded="false"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border @error('date') border-rose-300 @else border-slate-200 dark:border-slate-700 @enderror flex items-center justify-between gap-2 text-left focus:ring-2 focus:ring-primary focus:border-transparent">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="material-symbols-outlined text-[18px] text-slate-400">calendar_month</span>
                            <span id="exception-date-label" class="text-sm truncate {{ $initialDate ? 'text-slate-800 dark:text-slate-100' : 'text-slate-400' }}">{{ $initialDateLabel }}</span>
                        </span>
                        <span class="material-symbols-outlined text-[18px] text-slate-400">expand_more</span>
                    </button>
                    {{-- Nilai asli form tetap format YYYY-MM-DD agar validasi server tidak berubah. --}}
                    <input id="exception-date" type="hidden" name="date"
                           value="{{ $initialDate }}"
                           data-min="{{ now()->toDateString() }}">
                    <p id="exception-date-error" class="hidden text-xs text-rose-600 mt-1">Pilih tanggal terlebih dahulu.</p>
                    @error('date')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <fieldset>
                    <legend class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Jenis</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="mode" value="closed" class="peer sr-only"
                                   {{ old('mode', 'closed') === 'closed' ? 'checked' : '' }}>
                            <span class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 peer-checked:border-rose-300 peer-checked:bg-rose-50 peer-checked:text-rose-700 transition-colors">
                                <span class="material-symbols-outlined text-[16px]">event_busy</span>
                                Cuti penuh
                            </span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="mode" value="custom" class="peer sr-only"
                                   {{ old('mode') === 'custom' ? 'checked' : '' }}>
                            <span class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 peer-checked:border-amber-300 peer-checked:bg-amber-50 peer-checked:text-amber-700 transition-colors">
                                <span class="material-symbols-outlined text-[16px]">schedule</span>
                                Jam khusus
                            </span>
                        </label>
                    </div>
                    @error('mode')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div id="custom-hours" class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="exception-start" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Mulai</label>
                        <input id="exception-start" type="time" name="start_time" value="{{ old('start_time', $doctor->start_time ? substr($doctor->start_time, 0, 5) : '08:00') }}"
                               class="w-full px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                        @error('start_time')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="exception-end" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Selesai</label>
                        <input id="exception-end" type="time" name="end_time" value="{{ old('end_time', $doctor->end_time ? substr($doctor->end_time, 0, 5) : '17:00') }}"
                               class="w-full px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                        @error('end_time')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="exception-reason" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Alasan <span class="font-normal normal-case text-slate-400">(opsional)</span></label>
                    <textarea id="exception-reason" name="reason" rows="2" maxlength="160"
                              placeholder="Contoh: seminar, cuti tahunan, atau praktik pagi saja"
                              class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm resize-none focus:ring-2 focus:ring-primary focus:border-transparent">{{ old('reason') }}</textarea>
                    @error('reason')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-700 px-3.5 py-3 flex items-start gap-2">
                    <span class="material-symbols-outlined text-[17px] text-slate-400">info</span>
                    <p class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                        Booking yang sudah ada tidak dibatalkan otomatis. Sistem menandai booking di luar jadwal baru agar Anda meninjau atau menjadwalkan ulang.
                    </p>
                </div>

                <button type="submit"
                        class="w-full py-3 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-emerald-600 transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[19px]">save</span>
                    Simpan pengecualian
                </button>
            </form>
        </div>
    </section>
</div>

{{-- Pemilih tanggal bergaya modal: tampilan hari, bulan, lalu tahun. --}}
<div id="date-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="date-modal-title">
    <div class="absolute inset-0 bg-slate-900/50" data-date-close></div>
    <div id="date-modal-panel" class="relative w-full max-w-sm max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl p-4 sm:p-5">
        <div class="flex items-center justify-between gap-2">
            <button type="button" id="date-prev" title="Sebelumnya" aria-label="Sebelumnya"
                    class="w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 inline-flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-[20px]">chevron_left</span>
            </button>
            <div class="flex items-center gap-1">
                <button type="button" id="date-month-btn" class="px-2.5 py-1.5 rounded-lg text-sm font-bold text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">—</button>
                <button type="button" id="date-year-btn" class="px-2.5 py-1.5 rounded-lg text-sm font-bold text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">—</button>
            </div>
            <button type="button" id="date-next" title="Berikutnya" aria-label="Berikutnya"
                    class="w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 inline-flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-[20px]">chevron_right</span>
            </button>
        </div>

        <div id="date-days" class="mt-3">
            <div id="date-weekdays" class="grid grid-cols-7 gap-1 mb-1 text-[10px] font-bold uppercase tracking-wide text-slate-400 text-center"></div>
            <div id="date-grid" class="grid grid-cols-7 gap-1"></div>
        </div>

        <div id="date-months" class="mt-3 hidden">
            <div id="date-months-grid" class="grid grid-cols-3 gap-1.5"></div>
        </div>

        <div id="date-years" class="mt-3 hidden">
            <div id="date-years-grid" class="grid grid-cols-3 gap-1.5"></div>
        </div>

        <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between gap-2">
            <button type="button" id="date-today" class="px-3 py-2 rounded-lg text-xs font-semibold text-primary hover:bg-emerald-50 transition-colors">Hari ini</button>
            <button type="button" data-date-close class="px-3 py-2 rounded-lg text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">Tutup</button>
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

        const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
            'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

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
                labelEl.textContent = 'Pilih tanggal';
                labelEl.className = 'text-sm text-slate-400 truncate';
                return;
            }
            labelEl.textContent = date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
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
                el.setAttribute('aria-label', date.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
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
                let className = 'h-11 rounded-xl text-[13px] font-semibold border transition-colors ';
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
                let className = 'h-11 rounded-xl text-[13px] font-semibold border transition-colors ';
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
