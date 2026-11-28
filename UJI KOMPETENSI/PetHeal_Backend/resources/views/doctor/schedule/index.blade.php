@extends('layouts.admin')

@section('title', __('doctor.header_schedule') . ' — ' . ($doctor->clinic?->name ?? 'PetHeal'))
@section('header', __('doctor.header_schedule'))

@php
    $dayLabels = [
        'monday' => 'Senin', 'tuesday' => 'Selasa', 'wednesday' => 'Rabu',
        'thursday' => 'Kamis', 'friday' => 'Jumat', 'saturday' => 'Sabtu', 'sunday' => 'Minggu',
    ];
    $dayOrder = array_keys($dayLabels);
    $todayKey = strtolower(now()->format('l'));
    $weeklySet = collect($weeklyDays)->map(fn ($d) => strtolower(trim((string) $d)))->unique();
    $practiceLabel = substr((string) $doctor->start_time, 0, 5) . '–' . substr((string) $doctor->end_time, 0, 5);
    $practiceCount = $weeklySet->count();
@endphp

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        title="Jadwal & Cuti"
        eyebrow="Praktik"
        icon="event_busy"
        description="Tandai hari cuti atau ubah jam praktik untuk tanggal tertentu. Booking yang sudah ada tidak dibatalkan otomatis — koordinasikan dengan admin klinik bila perlu.">
        <x-slot:actions>
            <a href="{{ route('doctor.bookings.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">calendar_month</span>
                Booking Saya
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.last-updated :model="$doctor" :actions="['doctor.schedule_exception.save', 'doctor.schedule_exception.delete', 'doctor.update']" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)]">

        {{-- Kolom utama: aksi dulu, lalu daftar hasilnya --}}
        <div class="space-y-6">
            <x-admin.panel title="{{ __('doctor.set_leave') }}" subtitle="{{ __('doctor.set_leave_hint') }}">
                <form method="POST" action="{{ route('doctor.schedule.store') }}" class="space-y-5" id="scheduleForm">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="date" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Tanggal</label>
                            <input type="date" id="date" name="date" required
                                   min="{{ now()->toDateString() }}"
                                   value="{{ old('date', $prefillDate) }}"
                                   class="admin-input @error('date') admin-input-error @enderror">
                            @error('date')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="reason" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ __('doctor.reason_optional') }}</label>
                            <input type="text" id="reason" name="reason" maxlength="160"
                                   value="{{ old('reason') }}"
                                   placeholder="{{ __('doctor.reason_placeholder') }}"
                                   class="admin-input @error('reason') admin-input-error @enderror">
                            @error('reason')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 block text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ __('doctor.exception_type') }}</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="mode-card flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3.5 transition dark:border-slate-700">
                                <input type="radio" name="mode" value="closed" class="mt-0.5" @checked(old('mode', 'closed') === 'closed')>
                                <span>
                                    <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                        <span class="material-symbols-outlined text-[18px] text-rose-500" aria-hidden="true">event_busy</span>
                                        Cuti sehari penuh
                                    </span>
                                    <span class="mt-0.5 block text-[11px] leading-5 text-slate-500 dark:text-slate-400">
                                        Tidak ada slot baru pada tanggal ini.
                                    </span>
                                </span>
                            </label>

                            <label class="mode-card flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3.5 transition dark:border-slate-700">
                                <input type="radio" name="mode" value="custom" class="mt-0.5" @checked(old('mode') === 'custom')>
                                <span>
                                    <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                        <span class="material-symbols-outlined text-[18px] text-emerald-600 dark:text-emerald-400" aria-hidden="true">schedule</span>
                                        {{ __('doctor.custom_hours') }}
                                    </span>
                                    <span class="mt-0.5 block text-[11px] leading-5 text-slate-500 dark:text-slate-400">
                                        Ganti jam praktik hanya pada tanggal ini.
                                    </span>
                                </span>
                            </label>
                        </div>
                        @error('mode')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="time-group grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 transition sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-800/40">
                        <div>
                            <label for="start_time" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Mulai</label>
                            <input type="time" id="start_time" name="start_time" value="{{ old('start_time', '09:00') }}"
                                   class="admin-input @error('start_time') admin-input-error @enderror">
                            @error('start_time')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="end_time" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Selesai</label>
                            <input type="time" id="end_time" name="end_time" value="{{ old('end_time', '17:00') }}"
                                   class="admin-input @error('end_time') admin-input-error @enderror">
                            @error('end_time')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <p class="time-hint hidden text-[11px] leading-5 text-slate-500 sm:col-span-2">
                            Kolom jam diabaikan saat memilih cuti sehari penuh.
                        </p>
                    </div>

                    <div class="flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] leading-5 text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">info</span>
                        <p>{{ __('doctor.overwrite_warning') }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <button type="reset" class="admin-btn-ghost">Bersihkan</button>
                        <button type="submit" class="admin-btn-primary" id="scheduleSubmit">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                            Simpan Pengecualian
                        </button>
                    </div>
                </form>
            </x-admin.panel>

            <x-admin.panel
                title="{{ __('doctor.upcoming_exceptions') }}"
                :subtitle="$exceptions->isNotEmpty() ? $exceptions->count() . ' tanggal · urut dari yang terdekat' : null"
                flush>
                @if($exceptions->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[620px] text-left">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="px-5 py-3">Tanggal</th>
                                    <th class="px-5 py-3">Jenis</th>
                                    <th class="px-5 py-3">Alasan</th>
                                    <th class="px-5 py-3">Dampak</th>
                                    <th class="px-5 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                                @foreach($exceptions as $exception)
                                    @php
                                        $conflicts = (int) ($conflictsByDate[$exception->date] ?? 0);
                                    @endphp
                                    <tr class="admin-table-row">
                                        <td class="whitespace-nowrap px-5 py-3">
                                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                                {{ \Carbon\Carbon::parse($exception->date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                            </p>
                                            <p class="text-[11px] text-slate-500">
                                                {{ \Carbon\Carbon::parse($exception->date)->locale(app()->getLocale())->translatedFormat('l') }}
                                            </p>
                                        </td>
                                        <td class="px-5 py-3">
                                            @if($exception->is_closed)
                                                <x-admin.badge label="Cuti" tone="rose" dot />
                                            @else
                                                <x-admin.badge label="Jam khusus" tone="sky" dot />
                                                <p class="mt-1 text-[11px] tabular-nums text-slate-500 dark:text-slate-400">
                                                    {{ $exception->start_time }} - {{ $exception->end_time }}
                                                </p>
                                            @endif
                                        </td>
                                        <td class="max-w-[200px] px-5 py-3">
                                            <p class="truncate text-sm text-slate-600 dark:text-slate-300" title="{{ $exception->reason }}">{{ $exception->reason ?: '—' }}</p>
                                        </td>
                                        <td class="px-5 py-3">
                                            @if($conflicts > 0)
                                                <x-admin.badge :label="$conflicts . ' booking di luar jadwal'" tone="amber" />
                                                <p class="mt-1 text-[11px] text-slate-500">{{ __('doctor.not_cancelled_automatically') }}</p>
                                            @else
                                                <span class="text-xs text-slate-500">{{ __('doctor.no_conflict') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="{{ route('doctor.schedule.index', ['date' => $exception->date]) }}"
                                                   class="admin-icon-btn hover:!text-primary" title="{{ __('doctor.fill_with_date') }}" aria-label="Ubah pengecualian {{ $exception->date }}">
                                                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit_calendar</span>
                                                </a>
                                                <form method="POST" action="{{ route('doctor.schedule.destroy', $exception->id) }}"
                                                      onsubmit="return confirm('Hapus pengecualian pada tanggal ini? Jadwal mingguan akan dipakai kembali.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="admin-icon-btn hover:!text-rose-600" title="{{ __('doctor.delete_exception') }}" aria-label="Hapus pengecualian {{ $exception->date }}">
                                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-5">
                        <x-admin.empty-state
                            title="{{ __('doctor.no_exceptions') }}"
                            description="Semua tanggal masih mengikuti jadwal mingguan. Tambahkan lewat formulir di atas."
                            icon="event_available"
                            tone="primary" />
                    </div>
                @endif
            </x-admin.panel>
        </div>

        {{-- Sidebar: konteks sebelum mengubah jadwal --}}
        <div class="space-y-6">
            <x-admin.panel title="{{ __('doctor.weekly_schedule') }}" subtitle="{{ __('doctor.weekly_schedule_hint') }}" flush>
                <ul class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @foreach($dayOrder as $key)
                        @php
                            $isPractice = $weeklySet->contains($key);
                            $isToday = $key === $todayKey;
                        @endphp
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5 {{ $isToday ? 'bg-emerald-50/50 dark:bg-emerald-900/10' : '' }}">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="text-sm {{ $isPractice ? 'font-semibold text-slate-800 dark:text-slate-200' : 'text-slate-500 dark:text-slate-400' }}">
                                    {{ $dayLabels[$key] }}
                                </span>
                                @if($isToday)
                                    <span class="rounded-full bg-slate-900 px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wider text-white dark:bg-slate-100 dark:text-slate-900">{{ __('doctor.today') }}</span>
                                @endif
                            </div>
                            <span class="flex-shrink-0 text-[11px] font-semibold tabular-nums {{ $isPractice ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-500 dark:text-slate-500' }}">
                                {{ $isPractice ? $practiceLabel : 'Libur' }}
                            </span>
                        </li>
                    @endforeach
                </ul>

                <x-slot:footer>
                    <p class="text-[11px] leading-5 text-slate-500">
                        {{ $practiceCount }} hari praktik · {{ $practiceLabel }}
                    </p>
                </x-slot:footer>
            </x-admin.panel>

            <x-admin.panel title="{{ __('doctor.upcoming_bookings') }}" subtitle="{{ __('doctor.upcoming_bookings_hint') }}" flush>
                <x-slot:actions>
                    <a href="{{ route('doctor.bookings.index') }}" class="text-[11px] font-bold text-emerald-700 hover:underline dark:text-emerald-300">
                        Semua booking
                    </a>
                </x-slot:actions>

                @if($upcomingBookings->isNotEmpty())
                    <ul class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @foreach($upcomingBookings as $booking)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('doctor.bookings.show', $booking->id) }}"
                                       class="block truncate text-sm font-semibold text-slate-800 hover:text-primary dark:text-slate-200">
                                        {{ $booking->pet->name ?? '—' }}
                                    </a>
                                    <p class="truncate text-[11px] text-slate-500">
                                        {{ \Carbon\Carbon::parse($booking->booking_date)->locale(app()->getLocale())->translatedFormat('d M') }}
                                        · {{ substr((string) $booking->booking_time, 0, 5) }}
                                        · {{ $booking->user->name ?? '—' }}
                                    </p>
                                </div>
                                <x-admin.badge
                                    :label="\App\Support\AdminStatus::bookingLabel($booking->status)"
                                    :tone="\App\Support\AdminStatus::bookingTone($booking->status)" />
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="p-5 text-sm text-slate-500 dark:text-slate-400">{{ __('doctor.no_upcoming_bookings') }}</p>
                @endif
            </x-admin.panel>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('scheduleForm');
        if (!form) return;

        const timeGroup = form.querySelector('.time-group');
        const timeHint = form.querySelector('.time-hint');
        const start = form.querySelector('#start_time');
        const end = form.querySelector('#end_time');
        const cards = Array.from(form.querySelectorAll('.mode-card'));
        const modes = Array.from(form.querySelectorAll('input[name="mode"]'));

        // Status aktif kartu mode: ring + latar mengikuti pilihan.
        function paintCards() {
            cards.forEach(function (card) {
                const input = card.querySelector('input');
                card.classList.toggle('border-emerald-600', input.checked);
                card.classList.toggle('bg-emerald-50/70', input.checked);
                card.classList.toggle('dark:border-emerald-500', input.checked);
                card.classList.toggle('dark:bg-emerald-900/20', input.checked);
            });
        }

        function syncTimeFields() {
            const custom = modes.some((radio) => radio.checked && radio.value === 'custom');
            timeGroup.style.opacity = custom ? '1' : '0.6';
            start.disabled = !custom;
            end.disabled = !custom;
            timeHint.classList.toggle('hidden', custom);
        }

        modes.forEach((radio) => radio.addEventListener('change', function () {
            paintCards();
            syncTimeFields();
        }));
        paintCards();
        syncTimeFields();

        const submit = document.getElementById('scheduleSubmit');
        if (submit) {
            form.addEventListener('submit', function () {
                submit.disabled = true;
                submit.style.opacity = '0.8';
                submit.style.pointerEvents = 'none';
            });
        }
    })();
</script>
@endpush
