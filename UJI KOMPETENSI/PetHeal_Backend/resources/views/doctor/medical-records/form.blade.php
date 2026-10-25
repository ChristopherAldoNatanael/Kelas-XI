@extends('layouts.admin')

@php
    $isEdit = $record !== null;
    $pageTitle = $isEdit ? 'Perbarui Rekam Medis' : 'Rekam Medis Baru';
@endphp

@section('title', $pageTitle . ' — ' . ($doctor->clinic?->name ?? 'PetHeal'))
@section('header', $pageTitle)

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        :title="$pageTitle"
        eyebrow="Dokumentasi Klinis"
        icon="clinical_notes"
        description="Isi hasil pemeriksaan dengan lengkap. Menyimpan rekam medis baru akan menandai booking sebagai selesai.">
        <x-slot:actions>
            <a href="{{ $isEdit ? route('doctor.medical-records.show', $record->id) : route('doctor.bookings.show', $booking->id) }}"
               class="admin-btn-ghost">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Kembali
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.panel title="Informasi Booking">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pasien</p>
                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $booking->pet->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Spesies / Ras</p>
                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                    {{ ucfirst($booking->pet->species ?? '—') }}{{ $booking->pet->breed ? ' · ' . $booking->pet->breed : '' }}
                </p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pemilik</p>
                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $booking->user->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Layanan</p>
                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $booking->service->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tanggal</p>
                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                    {{ \Carbon\Carbon::parse($booking->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                </p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Jam</p>
                <p class="mt-1 text-sm tabular-nums text-slate-700 dark:text-slate-300">{{ substr((string) $booking->booking_time, 0, 5) }}</p>
            </div>
        </div>

        @if($booking->notes)
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Catatan pemilik:</span>
                {{ $booking->notes }}
            </div>
        @endif
    </x-admin.panel>

    <form method="POST"
          action="{{ $isEdit ? route('doctor.medical-records.update', $record->id) : route('doctor.medical-records.store') }}">
        @csrf
        @if($isEdit)
            @method('PUT')
        @else
            <input type="hidden" name="booking_id" value="{{ $booking->id }}">
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-admin.panel title="Hasil Pemeriksaan">
                    <div class="space-y-4">
                        <div>
                            <label for="diagnosis" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Diagnosis <span class="text-rose-500">*</span></label>
                            <textarea id="diagnosis" name="diagnosis" rows="3" required class="admin-input @error('diagnosis') admin-input-error @enderror"
                                      placeholder="Contoh: Dermatitis alergi pada kulit dorsal.">{{ old('diagnosis', $record?->diagnosis) }}</textarea>
                            @error('diagnosis')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="treatment" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Tindakan / Terapi <span class="text-rose-500">*</span></label>
                            <textarea id="treatment" name="treatment" rows="3" required class="admin-input @error('treatment') admin-input-error @enderror"
                                      placeholder="Contoh: Pembersihan area, pemberian salep, edukasi pemilik.">{{ old('treatment', $record?->treatment) }}</textarea>
                            @error('treatment')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="medicine" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Obat / Resep</label>
                            <textarea id="medicine" name="medicine" rows="3" class="admin-input"
                                      placeholder="Nama obat, dosis, dan durasi.">{{ old('medicine', $record?->medicine) }}</textarea>
                        </div>

                        <div>
                            <label for="notes" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Catatan Tambahan</label>
                            <textarea id="notes" name="notes" rows="2" class="admin-input"
                                      placeholder="Informasi lain yang perlu diingat.">{{ old('notes', $record?->notes) }}</textarea>
                        </div>
                    </div>
                </x-admin.panel>
            </div>

            <div class="space-y-6">
                <x-admin.panel title="Kunjungan Berikutnya">
                    <div class="space-y-4">
                        <div>
                            <label for="next_visit_date" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Tanggal</label>
                            <input type="date" id="next_visit_date" name="next_visit_date"
                                   value="{{ old('next_visit_date', optional($record?->next_visit_date)->format('Y-m-d')) }}"
                                   class="admin-input @error('next_visit_date') admin-input-error @enderror">
                            @error('next_visit_date')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="next_visit_time" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Jam (opsional)</label>
                            <input type="time" id="next_visit_time" name="next_visit_time"
                                   value="{{ old('next_visit_time', $record?->next_visit_time ? substr((string) $record->next_visit_time, 0, 5) : '') }}"
                                   class="admin-input">
                        </div>
                        <p class="text-[11px] leading-5 text-slate-400">
                            Jika diisi, pemilik hewan menerima pengingat kunjungan berikutnya lewat aplikasi.
                        </p>
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Biaya Medis">
                    <div class="space-y-4">
                        <div>
                            <label for="cost" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Biaya konsultasi</label>
                            <input type="number" min="0" step="0.01" id="cost" name="cost"
                                   value="{{ old('cost', $record?->cost ?? $booking->total_amount) }}"
                                   class="admin-input tabular-nums">
                        </div>
                        <div>
                            <label for="treatment_cost" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Biaya tindakan</label>
                            <input type="number" min="0" step="0.01" id="treatment_cost" name="treatment_cost"
                                   value="{{ old('treatment_cost', $record?->treatment_cost ?? 0) }}"
                                   class="admin-input tabular-nums">
                        </div>
                        <div>
                            <label for="medicine_cost" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Biaya obat</label>
                            <input type="number" min="0" step="0.01" id="medicine_cost" name="medicine_cost"
                                   value="{{ old('medicine_cost', $record?->medicine_cost ?? 0) }}"
                                   class="admin-input tabular-nums">
                        </div>
                        <p class="text-[11px] leading-5 text-slate-400">
                            Total tagihan dihitung otomatis dan diteruskan ke halaman pembayaran pasien.
                        </p>
                    </div>
                </x-admin.panel>

                <button type="submit" class="admin-btn-primary w-full justify-center">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan & Selesaikan Booking' }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
