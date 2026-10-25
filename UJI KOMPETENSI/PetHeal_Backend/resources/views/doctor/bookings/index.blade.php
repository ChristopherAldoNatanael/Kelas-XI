@extends('layouts.admin')

@section('title', 'Booking Saya — ' . ($doctor->clinic?->name ?? 'PetHeal'))
@section('header', 'Booking Saya')

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        title="Booking Saya"
        eyebrow="Praktik"
        icon="calendar_month"
        description="Hanya booking dengan nama Anda yang tampil di sini. Konfirmasi, selesaikan, atau batalkan sesuai kondisi pasien.">
        <x-slot:actions>
            <a href="{{ route('doctor.bookings.index', ['date' => now()->toDateString()]) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">today</span>
                Hari Ini
            </a>
            <a href="{{ route('doctor.bookings.index', ['date' => '']) }}" class="admin-btn-ghost">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">history</span>
                Semua Tanggal
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-admin.stat-card label="Total" :value="$summary['total']" icon="event" tone="blue" />
        <x-admin.stat-card label="Menunggu" :value="$summary['pending']" icon="hourglass_top" tone="amber" />
        <x-admin.stat-card label="Terkonfirmasi" :value="$summary['confirmed']" icon="event_available" tone="sky" />
        <x-admin.stat-card label="Selesai" :value="$summary['completed']" icon="check_circle" tone="primary" />
    </div>

    <x-admin.panel flush>
        <div class="border-b border-slate-100 p-4 dark:border-slate-800">
            <form method="GET" action="{{ route('doctor.bookings.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <div class="w-full lg:w-44">
                    <label for="date" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Tanggal</label>
                    <input type="date" id="date" name="date" value="{{ $date }}" class="admin-input">
                    <p class="mt-1 text-[10px] text-slate-400">Kosongkan untuk semua tanggal.</p>
                </div>
                <div class="w-full lg:w-44">
                    <label for="status" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Status</label>
                    <select id="status" name="status" class="admin-input">
                        <option value="">Semua status</option>
                        @foreach(['pending', 'confirmed', 'completed', 'cancelled'] as $option)
                            <option value="{{ $option }}" @selected($status === $option)>
                                {{ \App\Support\AdminStatus::bookingLabel($option) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full flex-1">
                    <label for="q" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Cari pasien / pemilik</label>
                    <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Nama hewan atau pemilik" class="admin-input">
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="admin-btn-primary">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
                        Terapkan
                    </button>
                    <a href="{{ route('doctor.bookings.index') }}" class="admin-btn-ghost">Reset</a>
                </div>
            </form>
        </div>

        @if($bookings->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-5 py-3">Jadwal</th>
                            <th class="px-5 py-3">Pasien</th>
                            <th class="px-5 py-3">Layanan</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Rekam Medis</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @foreach($bookings as $booking)
                            <tr class="admin-table-row">
                                <td class="whitespace-nowrap px-5 py-3">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                        {{ \Carbon\Carbon::parse($booking->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                    </p>
                                    <p class="text-[11px] tabular-nums text-slate-400">{{ substr((string) $booking->booking_time, 0, 5) }}</p>
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
                                    @if($booking->medicalRecord)
                                        <a href="{{ route('doctor.medical-records.show', $booking->medicalRecord->id) }}"
                                           class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-300">
                                            <span class="material-symbols-outlined text-[15px]" aria-hidden="true">description</span>
                                            Lihat
                                        </a>
                                    @elseif($booking->status !== 'cancelled')
                                        <a href="{{ route('doctor.medical-records.create', $booking->id) }}"
                                           class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-primary">
                                            <span class="material-symbols-outlined text-[15px]" aria-hidden="true">add_notes</span>
                                            Isi
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
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
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
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
                    title="Tidak ada booking yang cocok"
                    description="Coba ubah tanggal, status, atau kata kunci pencarian."
                    icon="event_busy"
                    tone="slate" />
            </div>
        @endif

        @if($bookings->hasPages())
            <x-slot:footer>
                {{ $bookings->links() }}
            </x-slot:footer>
        @endif
    </x-admin.panel>
</div>
@endsection
