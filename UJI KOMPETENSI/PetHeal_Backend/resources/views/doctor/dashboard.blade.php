@extends('layouts.admin')

@section('title', 'Ringkasan Praktik — ' . ($doctor->clinic?->name ?? 'PetHeal'))
@section('header', 'Ringkasan Hari Ini')

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        title="Selamat bertugas, {{ $doctor->name }}"
        eyebrow="Portal Dokter"
        icon="stethoscope"
        description="Ringkasan pasien hari ini. Semua data di sini hanya milik praktik Anda di {{ $doctor->clinic?->name ?? 'klinik ini' }}.">
        <x-slot:actions>
            <a href="{{ route('doctor.bookings.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">calendar_month</span>
                Semua Booking
            </a>
            <a href="{{ route('doctor.medical-records.index') }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">clinical_notes</span>
                Rekam Medis
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($todayException && $todayException->is_closed)
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-200">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">event_busy</span>
            <div>
                <p class="font-semibold">Anda tercatat cuti hari ini.</p>
                <p class="mt-0.5 text-xs leading-5">
                    {{ $todayException->reason ?: 'Tidak ada alasan tertulis.' }}
                    Booking yang masih aktif tidak dibatalkan otomatis — tinjau daftar di bawah.
                </p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-admin.stat-card label="Booking Hari Ini" :value="$stats['today_total']" icon="event" tone="blue" />
        <x-admin.stat-card label="Menunggu Konfirmasi" :value="$stats['today_pending']" icon="hourglass_top" tone="amber" />
        <x-admin.stat-card label="Terkonfirmasi" :value="$stats['today_confirmed']" icon="event_available" tone="sky" />
        <x-admin.stat-card label="Selesai Hari Ini" :value="$stats['today_completed']" icon="check_circle" tone="primary" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-admin.panel title="Jadwal Pasien Hari Ini"
                           subtitle="{{ \Carbon\Carbon::today()->locale(app()->getLocale())->translatedFormat('l, d F Y') }}"
                           flush>
                <x-slot:actions>
                    <x-admin.badge :label="$stats['today_total'] . ' booking'" tone="slate" />
                </x-slot:actions>

                @if($todayBookings->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[620px] text-left">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="px-5 py-3">Jam</th>
                                    <th class="px-5 py-3">Pasien</th>
                                    <th class="px-5 py-3">Layanan</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                                @foreach($todayBookings as $booking)
                                    <tr class="admin-table-row">
                                        <td class="whitespace-nowrap px-5 py-3 text-sm font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                                            {{ substr((string) $booking->booking_time, 0, 5) }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <a href="{{ route('doctor.bookings.show', $booking->id) }}"
                                               class="block text-sm font-semibold text-slate-800 hover:text-primary dark:text-slate-200">
                                                {{ $booking->pet->name ?? '—' }}
                                            </a>
                                            <p class="text-[11px] text-slate-400">{{ $booking->user->name ?? '—' }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-slate-600 dark:text-slate-300">
                                            {{ $booking->service->name ?? '—' }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <x-admin.badge
                                                :label="\App\Support\AdminStatus::bookingLabel($booking->status)"
                                                :tone="\App\Support\AdminStatus::bookingTone($booking->status)" />
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                @if($booking->status === 'pending')
                                                    <form method="POST" action="{{ route('doctor.bookings.confirm', $booking->id) }}">
                                                        @csrf
                                                        <button type="submit" class="admin-icon-btn hover:!text-sky-600" title="Konfirmasi booking">
                                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">event_available</span>
                                                        </button>
                                                    </form>
                                                @endif
                                                @if(in_array($booking->status, ['pending', 'confirmed'], true))
                                                    <form method="POST" action="{{ route('doctor.bookings.complete', $booking->id) }}"
                                                          onsubmit="return confirm('Tandai booking ini selesai?')">
                                                        @csrf
                                                        <button type="submit" class="admin-icon-btn hover:!text-emerald-600" title="Tandai selesai">
                                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">check_circle</span>
                                                        </button>
                                                    </form>
                                                @endif
                                                <a href="{{ route('doctor.bookings.show', $booking->id) }}" class="admin-icon-btn hover:!text-primary" title="Lihat detail">
                                                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">chevron_right</span>
                                                </a>
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
                            title="Belum ada booking hari ini"
                            description="Jadwal pasien yang masuk akan tampil di sini begitu pemilik hewan melakukan booking."
                            icon="event_available"
                            tone="primary" />
                    </div>
                @endif
            </x-admin.panel>
        </div>

        <div class="space-y-6">
            <x-admin.panel title="Pasien Berikutnya">
                @if($nextBooking)
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-4 dark:border-emerald-900/50 dark:bg-emerald-900/20">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                            {{ \Carbon\Carbon::parse($nextBooking->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                            · {{ substr((string) $nextBooking->booking_time, 0, 5) }}
                        </p>
                        <p class="mt-1.5 text-base font-bold text-slate-900 dark:text-white">{{ $nextBooking->pet->name ?? '—' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $nextBooking->user->name ?? '—' }} · {{ $nextBooking->service->name ?? '—' }}</p>
                        <a href="{{ route('doctor.bookings.show', $nextBooking->id) }}"
                           class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:underline dark:text-emerald-300">
                            Lihat detail
                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">arrow_forward</span>
                        </a>
                    </div>
                @else
                    <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada booking aktif berikutnya.</p>
                @endif
            </x-admin.panel>

            <x-admin.panel title="Perlu Perhatian">
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">Booking menunggu konfirmasi</span>
                        <span class="font-bold tabular-nums text-amber-600">{{ $pendingTotal }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">Booking 7 hari ke depan</span>
                        <span class="font-bold tabular-nums text-slate-800 dark:text-slate-200">{{ $upcomingWeek }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">Rekam medis bulan ini</span>
                        <span class="font-bold tabular-nums text-slate-800 dark:text-slate-200">{{ $recordsThisMonth }}</span>
                    </div>
                </div>

                @if($pendingTotal > 0)
                    <a href="{{ route('doctor.bookings.index', ['date' => '', 'status' => 'pending']) }}"
                       class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700 transition hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-300">
                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">hourglass_top</span>
                        Tinjau booking menunggu
                    </a>
                @endif
            </x-admin.panel>

            <x-admin.panel title="Jadwal Praktik">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Hari praktik</dt>
                        <dd class="text-right font-semibold text-slate-800 dark:text-slate-200">
                            {{ $doctor->available_days ? collect($doctor->available_days)->map(fn ($d) => ucfirst($d))->implode(', ') : '—' }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Jam</dt>
                        <dd class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                            {{ substr((string) $doctor->start_time, 0, 5) }} - {{ substr((string) $doctor->end_time, 0, 5) }}
                        </dd>
                    </div>
                </dl>
                <a href="{{ route('doctor.schedule.index') }}" class="mt-4 inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:underline dark:text-emerald-300">
                    Atur cuti & jam khusus
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">arrow_forward</span>
                </a>
            </x-admin.panel>
        </div>
    </div>
</div>
@endsection
