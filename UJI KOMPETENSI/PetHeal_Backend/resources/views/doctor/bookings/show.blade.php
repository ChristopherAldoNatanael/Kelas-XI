@extends('layouts.admin')

@section('title', 'Booking #' . $booking->id . ' — ' . ($doctor->clinic?->name ?? 'PetHeal'))
@section('header', 'Detail Booking')

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        :title="($booking->pet->name ?? 'Pasien') . ' · #' . $booking->id"
        eyebrow="Detail Booking"
        icon="event_note"
        :description="'Booking untuk layanan ' . ($booking->service->name ?? '—') . ' pada ' . \Carbon\Carbon::parse($booking->booking_date)->locale(app()->getLocale())->translatedFormat('l, d F Y') . '.'">
        <x-slot:actions>
            <a href="{{ route('doctor.bookings.index') }}" class="admin-btn-ghost">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Kembali
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-admin.panel title="Informasi Pasien">
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Hewan</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $booking->pet->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Spesies</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ ucfirst($booking->pet->species ?? '—') }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ras</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $booking->pet->breed ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pemilik</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $booking->user->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Telepon</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $booking->user->phone ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Layanan</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $booking->service->name ?? '—' }}</p>
                    </div>
                </div>

                @if($booking->notes)
                    <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Catatan dari pemilik</p>
                        <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $booking->notes }}</p>
                    </div>
                @endif

                @if($booking->status === 'cancelled' && $booking->cancellation_reason)
                    <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-200">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Alasan pembatalan</p>
                        <p class="mt-1 leading-6">{{ $booking->cancellation_reason }}</p>
                    </div>
                @endif
            </x-admin.panel>

            <x-admin.panel title="Rekam Medis">
                @if($booking->medicalRecord)
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                Rekam medis #{{ $booking->medicalRecord->id }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Dibuat {{ optional($booking->medicalRecord->created_at)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('doctor.medical-records.show', $booking->medicalRecord->id) }}" class="admin-btn-secondary">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                                Lihat
                            </a>
                            <a href="{{ route('doctor.medical-records.edit', $booking->medicalRecord->id) }}" class="admin-btn-primary">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                                Perbarui
                            </a>
                        </div>
                    </div>
                @elseif($booking->status !== 'cancelled')
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Belum ada rekam medis</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Menyimpan rekam medis akan menandai booking ini sebagai selesai.
                            </p>
                        </div>
                        <a href="{{ route('doctor.medical-records.create', $booking->id) }}" class="admin-btn-primary">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_notes</span>
                            Isi Rekam Medis
                        </a>
                    </div>
                @else
                    <p class="text-sm text-slate-500 dark:text-slate-400">Booking dibatalkan, rekam medis tidak diperlukan.</p>
                @endif
            </x-admin.panel>
        </div>

        <div class="space-y-6">
            <x-admin.panel title="Jadwal">
                <dl class="space-y-4 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">Tanggal</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-200">
                            {{ \Carbon\Carbon::parse($booking->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">Jam</dt>
                        <dd class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                            {{ substr((string) $booking->booking_time, 0, 5) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">Status</dt>
                        <dd>
                            <x-admin.badge
                                :label="\App\Support\AdminStatus::bookingLabel($booking->status)"
                                :tone="\App\Support\AdminStatus::bookingTone($booking->status)" />
                        </dd>
                    </div>
                </dl>
            </x-admin.panel>

            @if(in_array($booking->status, ['pending', 'confirmed'], true))
                <x-admin.panel title="Tindakan">
                    <div class="space-y-2">
                        @if($booking->status === 'pending')
                            <form method="POST" action="{{ route('doctor.bookings.confirm', $booking->id) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-xl bg-sky-600 px-3 py-2.5 text-sm font-bold text-white transition hover:bg-sky-700">
                                    Konfirmasi Booking
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('doctor.bookings.complete', $booking->id) }}"
                              onsubmit="return confirm('Tandai booking ini selesai? Tindakan ini tidak bisa dibatalkan.')">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700">
                                Tandai Selesai
                            </button>
                        </form>

                        <details class="group rounded-xl border border-rose-200 dark:border-rose-900/60">
                            <summary class="cursor-pointer list-none px-3 py-2.5 text-center text-sm font-bold text-rose-700 marker:content-none dark:text-rose-300">
                                Batalkan Booking
                            </summary>
                            <form method="POST" action="{{ route('doctor.bookings.cancel', $booking->id) }}" class="space-y-2 border-t border-rose-100 p-3 dark:border-rose-900/60">
                                @csrf
                                <label for="reason" class="block text-[10px] font-bold uppercase tracking-wider text-rose-500">Alasan pembatalan</label>
                                <textarea id="reason" name="reason" rows="3" required maxlength="500"
                                          class="admin-input" placeholder="Contoh: dokter berhalangan hadir, mohon jadwalkan ulang."></textarea>
                                <button type="submit" class="w-full rounded-lg bg-rose-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-rose-700">
                                    Kirim Pembatalan
                                </button>
                            </form>
                        </details>
                    </div>
                </x-admin.panel>
            @endif

            <x-admin.panel title="Keamanan Data">
                <p class="text-xs leading-6 text-slate-500 dark:text-slate-400">
                    Anda hanya dapat melihat dan mengubah booking atas nama Anda sendiri.
                    Perubahan jadwal di luar kewenangan dokter dilakukan oleh admin klinik.
                </p>
            </x-admin.panel>
        </div>
    </div>
</div>
@endsection
