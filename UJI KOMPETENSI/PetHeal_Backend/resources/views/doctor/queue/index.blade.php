@extends('layouts.admin')

@section('title', 'Antrian Pasien — ' . ($doctor->clinic?->name ?? 'PetHeal'))
@section('header', 'Antrian Pasien')

@php
    use App\Services\QueueService;
@endphp

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        title="Antrian Pasien Saya"
        eyebrow="Portal Dokter"
        icon="groups"
        description="Pasien yang check-in untuk praktik Anda. Pemeriksaan selesai tercatat otomatis saat rekam medis disimpan.">
        <x-slot:actions>
            <form method="GET" action="{{ route('doctor.queue.index') }}" class="flex items-center gap-2">
                <input type="date" name="date" value="{{ $date }}" class="admin-input !w-40 !py-2">
                <button class="admin-btn-secondary !py-2">Tampilkan</button>
            </form>
            {{-- Jalan pintas tanggal agar perpindahan hari saat presentasi/uji
                 tidak perlu membuka date picker. --}}
            <div class="flex items-center gap-1">
                <a href="{{ route('doctor.queue.index', ['date' => today()->toDateString()]) }}"
                   class="admin-btn-ghost !py-2 text-xs @if($date === today()->toDateString()) !bg-primary/10 !text-primary @endif">Hari ini</a>
                <a href="{{ route('doctor.queue.index', ['date' => today()->addDay()->toDateString()]) }}"
                   class="admin-btn-ghost !py-2 text-xs @if($date === today()->addDay()->toDateString()) !bg-primary/10 !text-primary @endif">Besok</a>
            </div>
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

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-admin.stat-card label="Menunggu" :value="$stats['waiting']" icon="hourglass_top" tone="amber" />
        <x-admin.stat-card label="Sedang ditangani" :value="$stats['serving']" icon="stethoscope" tone="primary" />
        <x-admin.stat-card label="Selesai" :value="$stats['done']" icon="task_alt" tone="blue" />
        <x-admin.stat-card label="Tidak datang" :value="$stats['no_show']" icon="person_off" tone="rose" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-admin.panel title="Sedang Ditangani" subtitle="Lanjutkan pemeriksaan melalui rekam medis" flush>
            @forelse($serving as $booking)
                <div class="border-b border-slate-100 px-5 py-4 last:border-0 dark:border-slate-800">
                    <div class="flex items-start gap-4">
                        <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-lg font-bold tabular-nums text-primary">
                            {{ $booking->queue_number }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                {{ $booking->pet->name ?? '—' }}
                                <span class="text-xs font-normal text-slate-500">· {{ $booking->pet->species ?? '' }}</span>
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $booking->user->name ?? '—' }} · {{ $booking->booking_time }}
                            </p>
                            @if($booking->notes)
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Keluhan: {{ $booking->notes }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($booking->medicalRecord)
                            <a href="{{ route('doctor.medical-records.show', $booking->medicalRecord->id) }}" class="admin-btn-secondary !py-2 text-xs">Buka rekam medis</a>
                        @else
                            <a href="{{ route('doctor.medical-records.create', $booking->id) }}" class="admin-btn-primary !py-2 text-xs">Buat rekam medis</a>
                        @endif
                        <a href="{{ route('doctor.bookings.show', $booking->id) }}" class="admin-btn-ghost !py-2 text-xs">Detail booking</a>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8">
                    <x-admin.empty-state title="Tidak ada pasien yang sedang ditangani" icon="stethoscope" tone="slate" />
                </div>
            @endforelse
        </x-admin.panel>

        <x-admin.panel title="Menunggu" subtitle="Panggil sesuai urutan nomor" flush>
            @forelse($waiting as $booking)
                <div class="border-b border-slate-100 px-5 py-4 last:border-0 dark:border-slate-800">
                    <div class="flex items-start gap-4">
                        <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-lg font-bold tabular-nums text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                            {{ $booking->queue_number }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                {{ $booking->pet->name ?? '—' }}
                                <span class="text-xs font-normal text-slate-500">· {{ $booking->pet->species ?? '' }}</span>
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $booking->user->name ?? '—' }} · {{ $booking->booking_time }}
                            </p>
                            @if($booking->notes)
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Keluhan: {{ $booking->notes }}</p>
                            @endif
                            @if($booking->queue_status === QueueService::STATUS_CALLED)
                                <p class="mt-1 text-xs font-semibold text-primary">Sudah dipanggil {{ $booking->called_at?->format('H:i') }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($booking->queue_status === QueueService::STATUS_WAITING)
                            <form method="POST" action="{{ route('doctor.queue.call', $booking->id) }}">
                                @csrf
                                <button class="admin-btn-primary !py-2 text-xs">Panggil</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('doctor.queue.recall', $booking->id) }}">
                                @csrf
                                <button class="admin-btn-secondary !py-2 text-xs">Panggil ulang</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('doctor.queue.start', $booking->id) }}">
                            @csrf
                            <button class="admin-btn-secondary !py-2 text-xs">Mulai pemeriksaan</button>
                        </form>
                        <form method="POST" action="{{ route('doctor.queue.skip', $booking->id) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="text" name="reason" placeholder="Alasan dilewati" class="admin-input !w-40 !py-1.5 !text-xs">
                            <button class="admin-btn-ghost !py-2 text-xs"
                                    onclick="return confirm('Tandai pasien tidak merespons panggilan?')">Lewati</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8">
                    <x-admin.empty-state title="Tidak ada pasien menunggu" icon="hourglass_empty" tone="slate" />
                </div>
            @endforelse
        </x-admin.panel>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-admin.panel title="Belum Check-in" subtitle="Booking Anda hari ini yang belum tiba" flush>
            @forelse($notCheckedIn as $booking)
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 dark:border-slate-800">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">
                            {{ $booking->pet->name ?? '—' }} · {{ $booking->booking_time }}
                        </p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $booking->user->name ?? '—' }}</p>
                    </div>
                    <span class="text-[11px] uppercase tracking-wider text-slate-400">{{ $booking->status }}</span>
                </div>
            @empty
                <div class="px-5 py-6">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Semua pasien hari ini sudah check-in.</p>
                </div>
            @endforelse
        </x-admin.panel>

        <x-admin.panel title="Selesai & Dilewati" flush>
            @forelse($finished as $booking)
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 dark:border-slate-800">
                    <span class="w-9 text-sm font-bold tabular-nums text-slate-400">{{ $booking->queue_number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-slate-700 dark:text-slate-300">{{ $booking->pet->name ?? '—' }}</p>
                        <p class="text-[11px] text-slate-400">
                            {{ $booking->queue_finished_at?->format('H:i') }}
                            @if($booking->queue_note) · {{ $booking->queue_note }} @endif
                        </p>
                    </div>
                    <x-admin.badge :label="QueueService::statusLabel($booking->queue_status)"
                                   :tone="$booking->queue_status === QueueService::STATUS_DONE ? 'primary' : 'rose'" dot />
                </div>
            @empty
                <div class="px-5 py-6">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada pemeriksaan yang selesai.</p>
                </div>
            @endforelse
        </x-admin.panel>
    </div>
</div>
@endsection
