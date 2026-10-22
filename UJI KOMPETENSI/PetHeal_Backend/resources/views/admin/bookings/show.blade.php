@extends('layouts.admin')

@php
    /** @var \App\Models\Booking $booking */
    $statusStyles = [
        'pending' => ['bg-amber-50 text-amber-700 border-amber-200', 'Menunggu konfirmasi'],
        'confirmed' => ['bg-sky-50 text-sky-700 border-sky-200', 'Terkonfirmasi'],
        'completed' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'Selesai'],
        'cancelled' => ['bg-rose-50 text-rose-700 border-rose-200', 'Dibatalkan'],
    ];
    $statusStyle = $statusStyles[$booking->status] ?? ['bg-slate-50 text-slate-600 border-slate-200', ucfirst($booking->status)];

    $paymentStyles = [
        'paid' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'Lunas'],
        'dp_paid' => ['bg-sky-50 text-sky-700 border-sky-200', 'DP dibayar'],
        'partial' => ['bg-orange-50 text-orange-700 border-orange-200', 'Dibayar sebagian'],
        'pending' => ['bg-amber-50 text-amber-700 border-amber-200', 'Menunggu pembayaran'],
        'unpaid' => ['bg-slate-50 text-slate-600 border-slate-200', 'Belum dibayar'],
        'failed' => ['bg-rose-50 text-rose-700 border-rose-200', 'Gagal'],
    ];
    $paymentStyle = $paymentStyles[$booking->payment_status] ?? ['bg-slate-50 text-slate-600 border-slate-200', ucfirst((string) $booking->payment_status)];

    $hasPayment = (bool) ($booking->payment_type || $booking->total_amount > 0);
    $progress = $booking->total_amount > 0 ? min(100, round(($booking->paid_amount / $booking->total_amount) * 100)) : 0;
    $canFinish = in_array($booking->status, ['pending', 'confirmed'], true);
@endphp

@section('title', 'Booking #' . $booking->id . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', 'Detail Booking')

@section('content')
<div class="mx-auto max-w-5xl space-y-5">

    {{-- ── Head ── --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
            <a href="{{ route('admin.bookings.index') }}"
               class="mt-0.5 inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
               aria-label="Kembali ke daftar booking">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">arrow_back</span>
            </a>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Booking #{{ $booking->id }}</h2>
                    <span class="rounded border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $statusStyle[0] }}">{{ $statusStyle[1] }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Dibuat {{ $booking->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}
                    @if($booking->service?->name) · {{ $booking->service->name }} @endif
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($booking->status === 'pending')
                <form method="POST" action="{{ route('admin.bookings.confirm', $booking->id) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-600">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check_circle</span>
                        Konfirmasi
                    </button>
                </form>
            @endif

            @if($canFinish)
                <button type="button" onclick="document.getElementById('reminder-modal').style.display='flex'"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">notifications</span>
                    Ingatkan
                </button>

                @if(!$booking->medicalRecord)
                    <a href="{{ route('admin.medical-records.create', $booking->id) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">clinical_notes</span>
                        Rekam medis
                    </a>
                @endif

                <form method="POST" action="{{ route('admin.bookings.complete', $booking->id) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">task_alt</span>
                        Selesaikan
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

        {{-- ── Left column ── --}}
        <div class="space-y-5 lg:col-span-2">

            {{-- Ringkasan kunjungan --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center gap-2 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">event</span>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Ringkasan kunjungan</h3>
                </div>

                <div class="grid grid-cols-1 gap-6 p-6 sm:grid-cols-2">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Hewan</p>
                        <div class="mt-2 flex items-center gap-3">
                            @if($booking->pet?->photo_url)
                                <img src="{{ $booking->pet->photo_url }}" alt="" class="h-12 w-12 rounded-full object-cover">
                            @else
                                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                                    <span class="material-symbols-outlined text-emerald-600" aria-hidden="true">pets</span>
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $booking->pet->name ?? 'Hewan' }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $booking->pet->species ?? '—' }}@if($booking->pet?->breed) · {{ $booking->pet->breed }}@endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pemilik</p>
                        <div class="mt-2 flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300" aria-hidden="true">
                                {{ \Illuminate\Support\Str::substr($booking->user->name ?? 'P', 0, 1) }}
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('admin.users.show', $booking->user_id) }}" class="block truncate text-sm font-semibold text-slate-900 hover:text-primary dark:text-white">
                                    {{ $booking->user->name ?? 'Pemilik' }}
                                </a>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $booking->user->phone ?? $booking->user->email }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Dokter</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ $booking->doctor->name ?? '—' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $booking->doctor->specialization ?? 'Dokter hewan' }}</p>
                    </div>

                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Jadwal</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ \Carbon\Carbon::parse($booking->booking_date)->locale('id')->translatedFormat('l, d F Y') }}
                        </p>
                        <p class="text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($booking->booking_time)->format('H:i') }} WIB</p>
                    </div>

                    <div class="sm:col-span-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Layanan</p>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800/50">
                            <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->service?->name ?? 'Belum dipilih' }}</span>
                            @if($booking->service?->price)
                                <span class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">Rp {{ number_format($booking->service->price, 0, ',', '.') }}</span>
                            @endif
                        </div>
                    </div>

                    @if($booking->notes)
                        <div class="sm:col-span-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Catatan dari pemilik</p>
                            <p class="mt-2 rounded-xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-700 dark:bg-slate-800/50 dark:text-slate-300">{{ $booking->notes }}</p>
                        </div>
                    @endif

                    @if($booking->cancellation_reason)
                        <div class="sm:col-span-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Alasan pembatalan</p>
                            <p class="mt-2 rounded-xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $booking->cancellation_reason }}</p>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Pembayaran (satu-satunya tempat konfirmasi & invoice) --}}
            <section id="pembayaran" class="scroll-mt-20 overflow-hidden rounded-2xl border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">payments</span>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pembayaran</h3>
                    </div>
                    <span class="rounded border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $paymentStyle[0] }}">{{ $paymentStyle[1] }}</span>
                </div>

                @if($hasPayment)
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/50">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total tagihan</p>
                                <p class="mt-1 text-base font-bold tabular-nums text-slate-900 dark:text-white">Rp {{ number_format((float) $booking->total_amount, 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-xl bg-emerald-50 p-4 dark:bg-emerald-900/20">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Dibayar</p>
                                <p class="mt-1 text-base font-bold tabular-nums text-emerald-700 dark:text-emerald-400">Rp {{ number_format((float) $booking->paid_amount, 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-xl bg-orange-50 p-4 dark:bg-orange-900/20">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-orange-600">Sisa</p>
                                <p class="mt-1 text-base font-bold tabular-nums text-orange-700 dark:text-orange-400">Rp {{ number_format((float) $booking->remaining_amount, 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/50">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tipe</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ $booking->payment_type === 'dp' ? 'Down Payment' : ($booking->payment_type ? 'Lunas di muka' : '—') }}</p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <div class="mb-1.5 flex items-center justify-between text-[11px]">
                                <span class="font-semibold text-slate-500 dark:text-slate-400">Progres pembayaran</span>
                                <span class="font-bold tabular-nums text-primary">{{ $progress }}%</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>

                        <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 text-xs dark:border-slate-800">
                            <div>
                                <dt class="text-slate-400">Metode</dt>
                                <dd class="mt-0.5 font-semibold capitalize text-slate-700 dark:text-slate-300">{{ $booking->payment_method ?? 'Belum dipilih' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-400">Tanggal bayar</dt>
                                <dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-300">{{ $booking->payment_date ? \Carbon\Carbon::parse($booking->payment_date)->format('d M Y, H:i') : '—' }}</dd>
                            </div>
                            @if($booking->payment_type === 'dp' && $booking->dp_amount > 0)
                                <div>
                                    <dt class="text-slate-400">DP wajib</dt>
                                    <dd class="mt-0.5 font-semibold tabular-nums text-slate-700 dark:text-slate-300">Rp {{ number_format((float) $booking->dp_amount, 0, ',', '.') }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="text-slate-400">Invoice</dt>
                                <dd class="mt-0.5 font-mono font-semibold text-slate-700 dark:text-slate-300">INV-{{ str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT) }}</dd>
                            </div>
                        </dl>

                        <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
                            <a href="{{ route('admin.payments.receipt', $booking->id) }}"
                               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">download</span>
                                Unduh invoice
                            </a>

                            @if($booking->payment_status !== 'paid' && $booking->remaining_amount > 0)
                                <form method="POST" action="{{ route('admin.payments.send-reminder', $booking->id) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">send</span>
                                        Kirim tagihan
                                    </button>
                                </form>
                            @endif

                            <a href="{{ route('admin.payments.index', ['search' => $booking->id]) }}"
                               class="ml-auto text-xs font-semibold text-slate-500 hover:text-primary dark:text-slate-400">
                                Lihat di daftar pembayaran
                            </a>
                        </div>

                        @if($booking->payment_status !== 'paid' && $booking->remaining_amount > 0)
                            <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/40">
                                <summary class="cursor-pointer text-xs font-bold text-slate-600 dark:text-slate-300">
                                    Catat pembayaran manual (transfer / tunai)
                                </summary>
                                <form method="POST" action="{{ route('admin.payments.confirm', $booking->id) }}" class="mt-4 space-y-3">
                                    @csrf
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <label for="payment-amount" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Jumlah dibayar (Rp)</label>
                                            <input id="payment-amount" type="number" name="amount" value="{{ (int) $booking->remaining_amount }}" min="0" max="{{ (int) $booking->remaining_amount }}" required
                                                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm tabular-nums outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                        </div>
                                        <div>
                                            <label for="payment-notes" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Catatan (opsional)</label>
                                            <input id="payment-notes" type="text" name="notes" maxlength="500" placeholder="Contoh: transfer BCA 12 Nov"
                                                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                        </div>
                                    </div>
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-600">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check_circle</span>
                                        Simpan pembayaran
                                    </button>
                                </form>
                            </details>
                        @endif
                    </div>
                @else
                    <div class="p-6 text-sm text-slate-500 dark:text-slate-400">
                        Booking ini belum memiliki tagihan. Pembayaran dibuat saat pemilik memilih layanan di aplikasi.
                    </div>
                @endif
            </section>

            {{-- Rekam medis --}}
            @if($booking->medicalRecord)
                <section class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">clinical_notes</span>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Rekam medis</h3>
                        </div>
                        <a href="{{ route('admin.medical-records.show', $booking->medicalRecord->id) }}" class="text-xs font-semibold text-primary hover:underline">Buka lengkap</a>
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Diagnosis</p>
                            <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $booking->medicalRecord->diagnosis }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Penanganan</p>
                            <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $booking->medicalRecord->treatment }}</p>
                        </div>
                        @if($booking->medicalRecord->medicine)
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Obat</p>
                                <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $booking->medicalRecord->medicine }}</p>
                            </div>
                        @endif
                        @if($booking->medicalRecord->next_visit_date)
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kontrol berikutnya</p>
                                <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">
                                    {{ \Carbon\Carbon::parse($booking->medicalRecord->next_visit_date)->format('d M Y') }}
                                    @if($booking->medicalRecord->next_visit_time) · {{ $booking->medicalRecord->next_visit_time }} @endif
                                </p>
                            </div>
                        @endif
                        @if($booking->medicalRecord->notes)
                            <div class="sm:col-span-2">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Catatan dokter</p>
                                <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $booking->medicalRecord->notes }}</p>
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        {{-- ── Right column ── --}}
        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Riwayat status</h3>
                <ol class="mt-4 space-y-4">
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-slate-300" aria-hidden="true"></span>
                        <div>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Dibuat</p>
                            <p class="text-[11px] text-slate-400">{{ $booking->created_at?->format('d M Y, H:i') }}</p>
                        </div>
                    </li>
                    @if($booking->confirmed_at)
                        <li class="flex gap-3">
                            <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-sky-400" aria-hidden="true"></span>
                            <div>
                                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Dikonfirmasi</p>
                                <p class="text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($booking->confirmed_at)->format('d M Y, H:i') }}</p>
                            </div>
                        </li>
                    @endif
                    @if($booking->completed_at)
                        <li class="flex gap-3">
                            <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span>
                            <div>
                                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Selesai</p>
                                <p class="text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($booking->completed_at)->format('d M Y, H:i') }}</p>
                            </div>
                        </li>
                    @endif
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-amber-300" aria-hidden="true"></span>
                        <div>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Terakhir diingatkan</p>
                            <p class="text-[11px] text-slate-400">{{ $booking->last_reminder_at?->format('d M Y, H:i') ?? 'Belum diingatkan' }}</p>
                        </div>
                    </li>
                </ol>
            </section>

            @if($canFinish)
                <section class="rounded-2xl border border-rose-100 bg-rose-50/60 p-5 dark:border-rose-900/40 dark:bg-rose-900/10">
                    <h3 class="text-sm font-bold text-rose-800 dark:text-rose-300">Batalkan booking</h3>
                    <p class="mt-1 text-[11px] leading-5 text-rose-700/80 dark:text-rose-300/70">
                        Pemilik hewan akan menerima notifikasi pembatalan beserta alasannya.
                    </p>
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs font-bold text-rose-700 dark:text-rose-300">Isi alasan pembatalan</summary>
                        <form method="POST" action="{{ route('admin.bookings.cancel', $booking->id) }}" class="mt-3 space-y-2">
                            @csrf
                            <textarea name="reason" rows="3" required
                                      class="w-full rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-200 dark:border-rose-900/50 dark:bg-slate-900 dark:text-slate-100"
                                      placeholder="Contoh: pemilik meminta jadwal ulang"></textarea>
                            <button type="submit" class="w-full rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700">
                                Batalkan booking
                            </button>
                        </form>
                    </details>
                </section>
            @endif
        </aside>
    </div>
</div>

{{-- ── Reminder modal ── --}}
<div id="reminder-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm" style="display:none" role="dialog" aria-modal="true" aria-labelledby="reminder-modal-title">
    <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-500" aria-hidden="true">notifications</span>
                <div>
                    <h3 id="reminder-modal-title" class="text-sm font-bold text-slate-900 dark:text-white">Kirim pengingat</h3>
                    <p class="text-[11px] text-slate-400">ke {{ $booking->user->name ?? 'pemilik' }}</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('reminder-modal').style.display='none'"
                    class="text-slate-400 transition hover:text-slate-600" aria-label="Tutup">
                <span class="material-symbols-outlined text-xl" aria-hidden="true">close</span>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.bookings.send-reminder', $booking->id) }}" class="p-5">
            @csrf
            <div class="mb-4 flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                    <span class="material-symbols-outlined text-emerald-600" aria-hidden="true">pets</span>
                </span>
                <div class="min-w-0 text-xs">
                    <p class="truncate font-semibold text-slate-800 dark:text-slate-200">{{ $booking->pet->name ?? 'Hewan' }}</p>
                    <p class="text-slate-500 dark:text-slate-400">
                        {{ \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') }} · {{ $booking->booking_time }} WIB
                    </p>
                </div>
            </div>

            <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Jenis pengingat</p>
            <div class="space-y-2">
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-amber-400 dark:border-slate-700">
                    <input type="radio" name="reminder_type" value="1_hour" class="accent-amber-500" onchange="toggleCustomMsg(this)">
                    <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">1 jam sebelum</span>
                        <span class="block text-[11px] text-slate-500">Kunjungan hari ini dalam waktu dekat</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-amber-400 dark:border-slate-700">
                    <input type="radio" name="reminder_type" value="tomorrow" checked class="accent-amber-500" onchange="toggleCustomMsg(this)">
                    <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">Besok</span>
                        <span class="block text-[11px] text-slate-500">Jadwal kunjungan jatuh besok</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-amber-400 dark:border-slate-700">
                    <input type="radio" name="reminder_type" value="custom" class="accent-amber-500" onchange="toggleCustomMsg(this)">
                    <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">Pesan custom</span>
                        <span class="block text-[11px] text-slate-500">Tulis pesan sendiri</span>
                    </span>
                </label>
            </div>

            <div id="custom-msg-wrap" class="mt-3" style="display:none">
                <label for="custom_message" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Pesan</label>
                <textarea id="custom_message" name="custom_message" rows="3" placeholder="Contoh: bawa buku vaksin ya"
                          class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-amber-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"></textarea>
            </div>

            <div class="mt-4 flex gap-2">
                <button type="button" onclick="document.getElementById('reminder-modal').style.display='none'"
                        class="flex-1 rounded-xl bg-slate-100 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300">
                    Batal
                </button>
                <button type="submit" class="flex-1 rounded-xl bg-amber-500 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600">
                    Kirim sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleCustomMsg(radio) {
        document.getElementById('custom-msg-wrap').style.display = radio.value === 'custom' ? 'block' : 'none';
    }
    document.getElementById('reminder-modal').addEventListener('click', function (event) {
        if (event.target === this) this.style.display = 'none';
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') document.getElementById('reminder-modal').style.display = 'none';
    });
</script>
@endsection
