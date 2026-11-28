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
        :title="$isEdit ? $pageTitle . ' #' . $record->id : $pageTitle"
        eyebrow="Dokumentasi Klinis"
        icon="clinical_notes"
        :description="$isEdit
            ? 'Perubahan tersimpan di riwayat rekam medis beserta alasannya.'
            : 'Isi hasil pemeriksaan dengan lengkap. Menyimpan rekam medis baru akan menandai booking sebagai selesai.'">
        <x-slot:actions>
            @if($isEdit)
                <a href="{{ route('doctor.medical-records.show', $record->id) }}" class="admin-btn-secondary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                    Lihat Detail
                </a>
                <a href="{{ route('doctor.medical-records.index') }}" class="admin-btn-ghost">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                    Daftar Rekam Medis
                </a>
            @else
                <a href="{{ route('doctor.bookings.show', $booking->id) }}" class="admin-btn-secondary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                    Kembali ke Booking
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.panel :title="__('doctor.booking_info')">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Pasien</p>
                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->pet->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Pemilik</p>
                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->user->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ __('doctor.species_breed') }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">
                    {{ ucfirst($booking->pet->species ?? '—') }}{{ $booking->pet->breed ? ' · ' . $booking->pet->breed : '' }}
                </p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Layanan</p>
                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->service->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Tanggal</p>
                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">
                    {{ \Carbon\Carbon::parse($booking->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                </p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Jam</p>
                <p class="mt-1 text-sm font-medium tabular-nums text-slate-800 dark:text-slate-200">
                    {{ substr((string) $booking->booking_time, 0, 5) }} WIB
                </p>
            </div>
        </div>

        @if($booking->notes)
            <div class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ __('doctor.owner_notes') }}</p>
                <p class="mt-1 whitespace-pre-line rounded-xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">{{ $booking->notes }}</p>
            </div>
        @endif
    </x-admin.panel>

    <x-admin.panel :title="__('admin.documentation_clinical')">
        <form method="POST" enctype="multipart/form-data"
              action="{{ $isEdit ? route('doctor.medical-records.update', $record->id) : route('doctor.medical-records.store') }}"
              class="space-y-6">
            @csrf
            @if($isEdit)
                @method('PUT')
            @else
                <input type="hidden" name="booking_id" value="{{ $booking->id }}">
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <label for="diagnosis" class="admin-label">
                        {{ __('doctor.diagnosis') }} <span class="text-rose-500">*</span>
                    </label>
                    <textarea name="diagnosis" id="diagnosis" required rows="3" class="admin-input"
                              placeholder="{{ __('doctor.diagnosis_placeholder') }}">{{ old('diagnosis', $record?->diagnosis) }}</textarea>
                    @error('diagnosis') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="treatment" class="admin-label">
                        {{ __('doctor.treatment') }} <span class="text-rose-500">*</span>
                    </label>
                    <textarea name="treatment" id="treatment" required rows="3" class="admin-input"
                              placeholder="{{ __('doctor.treatment_placeholder') }}">{{ old('treatment', $record?->treatment) }}</textarea>
                    @error('treatment') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="border-t border-slate-100 pt-6 dark:border-slate-800">
                @include('admin.medical-records.partials.soap-fields', ['record' => $record])
            </div>

            <div>
                <label for="medicine" class="admin-label">{{ __('doctor.prescription') }}</label>
                <textarea name="medicine" id="medicine" rows="2" class="admin-input"
                          placeholder="{{ __('doctor.prescription_placeholder') }}">{{ old('medicine', $record?->medicine) }}</textarea>
                @error('medicine') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div class="border-t border-slate-100 pt-6 dark:border-slate-800">
                @include('admin.medical-records.partials.prescription-fields', [
                    'record' => $record,
                    'medicines' => $medicines ?? collect(),
                    'allergies' => $allergies ?? collect(),
                ])
            </div>

            <div class="border-t border-slate-100 pt-6 dark:border-slate-800">
                @include('admin.medical-records.partials.attachments-fields', ['record' => $record])
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div>
                    <label for="cost" class="admin-label">{{ __('doctor.consultation_fee') }}</label>
                    <input type="number" name="cost" id="cost" min="0" step="0.01"
                           value="{{ old('cost', $record?->cost ?? $booking->total_amount ?? 0) }}" class="admin-input">
                    @error('cost') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="treatment_cost" class="admin-label">{{ __('doctor.treatment_fee') }}</label>
                    <input type="number" name="treatment_cost" id="treatment_cost" min="0" step="0.01"
                           value="{{ old('treatment_cost', $record?->treatment_cost ?? 0) }}" class="admin-input">
                    @error('treatment_cost') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="medicine_cost" class="admin-label">{{ __('doctor.medicine_fee') }}</label>
                    <input type="number" name="medicine_cost" id="medicine_cost" min="0" step="0.01"
                           value="{{ old('medicine_cost', $record?->medicine_cost ?? 0) }}" class="admin-input">
                    @error('medicine_cost') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="notes" class="admin-label">{{ __('doctor.additional_notes') }}</label>
                <textarea name="notes" id="notes" rows="2" class="admin-input"
                          placeholder="{{ __('doctor.additional_notes_placeholder') }}">{{ old('notes', $record?->notes) }}</textarea>
                @error('notes') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label for="next_visit_date" class="admin-label">{{ __('admin.next_visit_date') }}</label>
                    <input type="date" name="next_visit_date" id="next_visit_date" class="admin-input"
                           min="{{ $isEdit ? now()->toDateString() : now()->addDay()->format('Y-m-d') }}"
                           value="{{ old('next_visit_date', optional($record?->next_visit_date)->format('Y-m-d')) }}">
                    @error('next_visit_date') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="next_visit_time" class="admin-label">{{ __('admin.next_visit_time') }}</label>
                    <input type="time" name="next_visit_time" id="next_visit_time" class="admin-input"
                           value="{{ old('next_visit_time', $record?->next_visit_time ? substr((string) $record->next_visit_time, 0, 5) : '') }}">
                    @error('next_visit_time') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="admin-hint">Jika diisi, pemilik hewan menerima pengingat kunjungan berikutnya lewat aplikasi.</p>

            @if($isEdit)
                <div class="border-t border-slate-100 pt-6 dark:border-slate-800">
                    <label for="change_reason" class="admin-label">Alasan koreksi <span class="font-normal text-slate-500">(opsional)</span></label>
                    <input type="text" name="change_reason" id="change_reason" maxlength="255"
                           value="{{ old('change_reason') }}" class="admin-input"
                           placeholder="Contoh: koreksi dosis setelah konsultasi lanjutan">
                    <p class="admin-hint">Tersimpan di riwayat perubahan rekam medis.</p>
                    @error('change_reason') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-6 dark:border-slate-800">
                <a href="{{ $isEdit ? route('doctor.medical-records.show', $record->id) : route('doctor.bookings.show', $booking->id) }}"
                   class="admin-btn-ghost">{{ __('common.cancel') }}</a>
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan & Selesaikan Booking' }}
                </button>
            </div>
        </form>
    </x-admin.panel>
</div>
@endsection
