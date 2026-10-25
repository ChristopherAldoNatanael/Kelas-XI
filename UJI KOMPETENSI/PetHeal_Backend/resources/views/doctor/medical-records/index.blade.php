@extends('layouts.admin')

@section('title', 'Rekam Medis Saya — ' . ($doctor->clinic?->name ?? 'PetHeal'))
@section('header', 'Rekam Medis')

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        title="Rekam Medis Saya"
        eyebrow="Dokumentasi Klinis"
        icon="clinical_notes"
        description="Rekam medis yang Anda tulis untuk pasien Anda. Data rekam medis dokter lain tidak ditampilkan di sini.">
        <x-slot:actions>
            <a href="{{ route('doctor.bookings.index', ['date' => '', 'status' => 'completed']) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">history</span>
                Booking Selesai
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.stat-card label="Total Rekam Medis" :value="$total" icon="description" tone="primary" />
        <x-admin.stat-card label="Ditampilkan" :value="$records->total()" icon="filter_alt" tone="blue" />
        <x-admin.stat-card label="Halaman Ini" :value="$records->count()" icon="list_alt" tone="slate" />
    </div>

    <x-admin.panel flush>
        <div class="border-b border-slate-100 p-4 dark:border-slate-800">
            <form method="GET" action="{{ route('doctor.medical-records.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <div class="w-full lg:w-44">
                    <label for="from" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Dari tanggal</label>
                    <input type="date" id="from" name="from" value="{{ request('from') }}" class="admin-input">
                </div>
                <div class="w-full lg:w-44">
                    <label for="to" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Sampai tanggal</label>
                    <input type="date" id="to" name="to" value="{{ request('to') }}" class="admin-input">
                </div>
                <div class="w-full flex-1">
                    <label for="q" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Cari pasien</label>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nama hewan" class="admin-input">
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="admin-btn-primary">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
                        Terapkan
                    </button>
                    <a href="{{ route('doctor.medical-records.index') }}" class="admin-btn-ghost">Reset</a>
                </div>
            </form>
        </div>

        @if($records->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Pasien</th>
                            <th class="px-5 py-3">Diagnosis</th>
                            <th class="px-5 py-3">Biaya Medis</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @foreach($records as $record)
                            <tr class="admin-table-row">
                                <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600 dark:text-slate-300">
                                    {{ optional($record->created_at)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                    <p class="text-[11px] text-slate-400">Booking #{{ $record->booking_id }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('doctor.medical-records.show', $record->id) }}"
                                       class="block text-sm font-semibold text-slate-800 hover:text-primary dark:text-slate-200">
                                        {{ $record->pet->name ?? '—' }}
                                    </a>
                                    <p class="text-[11px] text-slate-400">{{ $record->booking->user->name ?? '—' }}</p>
                                </td>
                                <td class="max-w-[240px] px-5 py-3">
                                    <p class="truncate text-sm text-slate-700 dark:text-slate-300">{{ $record->diagnosis }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-sm tabular-nums text-slate-700 dark:text-slate-300">
                                    Rp {{ number_format((float) ($record->total_medical_cost ?? 0), 0, ',', '.') }}
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('doctor.medical-records.show', $record->id) }}" class="admin-icon-btn hover:!text-primary" title="Lihat rekam medis">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
                                        </a>
                                        <a href="{{ route('doctor.medical-records.edit', $record->id) }}" class="admin-icon-btn hover:!text-emerald-600" title="Perbarui rekam medis">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span>
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
                    title="Belum ada rekam medis"
                    description="Rekam medis dibuat dari halaman booking pasien yang sudah Anda tangani."
                    icon="clinical_notes"
                    tone="primary">
                    <x-slot:actions>
                        <a href="{{ route('doctor.bookings.index') }}" class="admin-btn-primary">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">calendar_month</span>
                            Buka Booking Saya
                        </a>
                    </x-slot:actions>
                </x-admin.empty-state>
            </div>
        @endif

        @if($records->hasPages())
            <x-slot:footer>
                {{ $records->links() }}
            </x-slot:footer>
        @endif
    </x-admin.panel>
</div>
@endsection
