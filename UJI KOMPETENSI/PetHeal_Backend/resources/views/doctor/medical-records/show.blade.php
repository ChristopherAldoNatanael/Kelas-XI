@extends('layouts.admin')

@php
    $currency = static fn ($amount) => 'Rp ' . number_format((float) $amount, 0, ',', '.');
    $owner = $record->booking?->user;
    $extraStatus = $record->extra_payment_status ?? 'not_required';
@endphp

@section('title', 'Rekam Medis #' . $record->id . ' — ' . ($doctor->clinic?->name ?? 'PetHeal'))
@section('header', 'Rekam Medis')

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        :title="'Rekam Medis #' . $record->id"
        eyebrow="Dokumentasi Klinis"
        icon="clinical_notes"
        :description="'Hasil pemeriksaan ' . ($record->pet->name ?? 'pasien') . ' pada ' . \Carbon\Carbon::parse($record->booking?->booking_date)->locale(app()->getLocale())->translatedFormat('d F Y') . '.'">
        <x-slot:actions>
            <a href="{{ route('doctor.medical-records.edit', $record->id) }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                Perbarui
            </a>
            <a href="{{ route('doctor.bookings.show', $record->booking_id) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">event_note</span>
                Lihat Booking
            </a>
            <a href="{{ route('doctor.medical-records.index') }}" class="admin-btn-ghost">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Daftar
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.6fr_0.95fr]">
        <div class="space-y-6">
            <x-admin.panel title="Ringkasan Klinis" subtitle="Diagnosis, tindakan, dan resep">
                <div class="space-y-4">
                    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Diagnosis</p>
                        <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $record->diagnosis ?: '—' }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tindakan / Terapi</p>
                        <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $record->treatment ?: '—' }}</p>
                    </div>

                    @if($record->medicine)
                        <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Obat / Resep</p>
                            <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $record->medicine }}</p>
                        </div>
                    @endif

                    @if($record->notes)
                        <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Catatan Tambahan</p>
                            <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $record->notes }}</p>
                        </div>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel title="Rincian Biaya" subtitle="Biaya medis yang tercatat pada kunjungan ini">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-admin.stat-card label="Konsultasi" :value="$currency($record->cost ?? 0)" icon="stethoscope" tone="blue" />
                    <x-admin.stat-card label="Tindakan" :value="$currency($record->treatment_cost ?? 0)" icon="medical_services" tone="violet" />
                    <x-admin.stat-card label="Obat" :value="$currency($record->medicine_cost ?? 0)" icon="medication" tone="amber" />
                </div>

                <div class="mt-5 flex flex-col gap-4 rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-emerald-900/60 dark:bg-emerald-900/20">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">Total biaya medis</p>
                        <p class="mt-1.5 text-3xl font-bold tabular-nums tracking-tight text-slate-900 dark:text-white">
                            {{ $currency($record->total_medical_cost ?? 0) }}
                        </p>
                    </div>
                    <div class="flex flex-col items-start gap-2 sm:items-end">
                        <x-admin.badge
                            :label="\App\Support\AdminStatus::extraPaymentLabel($extraStatus)"
                            :tone="\App\Support\AdminStatus::extraPaymentTone($extraStatus)"
                            dot />
                        <p class="max-w-[15rem] text-[11px] leading-5 text-slate-500 sm:text-right dark:text-slate-400">
                            Pelunasan dan verifikasi pembayaran ditangani admin klinik.
                        </p>
                    </div>
                </div>
            </x-admin.panel>
        </div>

        <div class="space-y-6">
            <x-admin.panel title="Pasien & Pemilik">
                <div class="space-y-3">
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                        @if($record->pet?->photo_url)
                            <img class="h-12 w-12 rounded-xl object-cover ring-1 ring-slate-200" src="{{ $record->pet->photo_url }}" alt="{{ $record->pet->name }}">
                        @else
                            <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30">
                                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">pets</span>
                            </span>
                        @endif
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Hewan</p>
                            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $record->pet->name ?? '—' }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ ucfirst($record->pet->species ?? '—') }}{{ $record->pet->breed ? ' · ' . $record->pet->breed : '' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                        <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/30">
                            <span class="material-symbols-outlined text-[22px]" aria-hidden="true">person</span>
                        </span>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pemilik</p>
                            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $owner->name ?? '—' }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $owner->phone ?: ($owner->email ?? '—') }}</p>
                        </div>
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel title="Kunjungan & Tindak Lanjut">
                <div class="space-y-3">
                    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kunjungan</p>
                        <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">
                            {{ \Carbon\Carbon::parse($record->booking?->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                        </p>
                        <p class="text-xs tabular-nums text-slate-500 dark:text-slate-400">
                            {{ substr((string) $record->booking?->booking_time, 0, 5) }} · {{ $record->booking?->service->name ?? '—' }}
                        </p>
                    </div>

                    @if($record->next_visit_date)
                        <div class="rounded-xl border border-amber-200/70 bg-amber-50/70 p-4 dark:border-amber-900/60 dark:bg-amber-900/20">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Kunjungan berikutnya</p>
                            <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($record->next_visit_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                            </p>
                            <p class="text-xs tabular-nums text-amber-700/80 dark:text-amber-300/80">
                                {{ $record->next_visit_time ? substr((string) $record->next_visit_time, 0, 5) : 'Jam belum ditentukan' }}
                            </p>
                            <p class="mt-1 text-[11px] text-amber-700/80 dark:text-amber-300/80">
                                {{ $record->reminder_sent ? 'Pengingat sudah dikirim ke aplikasi pemilik.' : 'Pengingat akan dikirim otomatis ke aplikasi pemilik.' }}
                            </p>
                        </div>
                    @endif

                    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Riwayat catatan</p>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                            Dibuat: {{ optional($record->created_at)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                            Diperbarui: {{ optional($record->updated_at)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                            Dokter: {{ $record->doctor->name ?? '—' }}
                        </p>
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel title="Kewenangan Dokter">
                <p class="text-xs leading-6 text-slate-500 dark:text-slate-400">
                    Dokter dapat memperbarui isi klinis dan biaya medis pada rekam medis miliknya.
                    Penagihan, verifikasi pembayaran, dan perubahan jadwal di luar praktik sendiri tetap dikelola admin klinik.
                </p>
            </x-admin.panel>
        </div>
    </div>
</div>
@endsection
