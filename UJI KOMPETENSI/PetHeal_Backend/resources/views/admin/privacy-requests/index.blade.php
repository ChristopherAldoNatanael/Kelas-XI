@extends('layouts.admin')

@section('title', 'Permintaan PDP — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', 'Permintaan PDP')

@php
$statusLabels = [
    'received' => 'Diterima',
    'verified' => 'Terverifikasi',
    'in_progress' => 'Diproses',
    'completed' => 'Selesai',
    'rejected' => 'Ditolak',
];
$statusTones = [
    'received' => 'amber',
    'verified' => 'blue',
    'in_progress' => 'sky',
    'completed' => 'green',
    'rejected' => 'rose',
];
$typeLabels = [
    'access' => 'Akses data',
    'correction' => 'Koreksi',
    'erasure' => 'Penghapusan',
    'restriction' => 'Pembatasan',
    'portability' => 'Portabilitas',
    'objection' => 'Keberatan',
];
@endphp

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        title="Permintaan PDP"
        eyebrow="Kepatuhan UU 27/2022"
        icon="shield_person"
        description="Kelola permintaan akses, koreksi, penghapusan, dan hak lain pemilik data hewan. Semua aksi tercatat di audit log." />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Total permintaan" :value="number_format($stats['total'])" hint="Seluruh permintaan pada lingkup klinik ini" icon="inventory_2" tone="slate" />
        <x-admin.stat-card label="Masih terbuka" :value="number_format($stats['open'])" hint="Belum selesai atau belum ditolak" icon="pending_actions" tone="amber" />
        <x-admin.stat-card label="Lewat SLA" :value="number_format($stats['overdue'])" hint="Melewati tenggat internal 14 hari" icon="timer" tone="rose" />
        <x-admin.stat-card label="Selesai" :value="number_format($stats['completed'])" hint="Permintaan yang sudah dipenuhi" icon="task_alt" tone="green" />
    </div>

    <x-admin.panel title="Filter" subtitle="Persempit daftar berdasarkan status, jenis, atau keterlambatan">
        <form method="GET" action="{{ route('admin.privacy-requests.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="pr-status" class="admin-label">Status</label>
                <select id="pr-status" name="status" class="admin-input">
                    <option value="">Semua status</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="pr-type" class="admin-label">Jenis permintaan</label>
                <select id="pr-type" name="type" class="admin-input">
                    <option value="">Semua jenis</option>
                    @foreach($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="pr-overdue" class="admin-label">Keterlambatan</label>
                <select id="pr-overdue" name="overdue" class="admin-input">
                    <option value="">Semua</option>
                    <option value="1" @selected(request('overdue') === '1')>Hanya yang lewat SLA</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
                    Terapkan
                </button>
                <a href="{{ route('admin.privacy-requests.index') }}" class="admin-btn-secondary">Reset</a>
            </div>
        </form>
    </x-admin.panel>

    <x-admin.panel title="Daftar permintaan" subtitle="Urut berdasarkan status dan tenggat terdekat" flush>
        <x-slot:actions>
            <x-admin.badge :label="'Total · ' . $privacyRequests->total()" tone="slate" />
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">ID</th>
                        <th class="px-5 py-3">Pemohon</th>
                        <th class="px-5 py-3">Jenis</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Diajukan</th>
                        <th class="px-5 py-3">Tenggat</th>
                        <th class="px-5 py-3">Penanggung jawab</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($privacyRequests as $privacyRequest)
                        <tr class="admin-table-row">
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-500">#{{ $privacyRequest->id }}</td>
                            <td class="px-5 py-3">
                                <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $privacyRequest->user->name ?? 'Pengguna dihapus' }}</div>
                                <div class="text-xs text-slate-500">{{ $privacyRequest->user->email ?? '—' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-sm">{{ $typeLabels[$privacyRequest->request_type] ?? $privacyRequest->request_type }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <x-admin.badge :label="$statusLabels[$privacyRequest->status] ?? $privacyRequest->status" :tone="$statusTones[$privacyRequest->status] ?? 'slate'" />
                                @if($privacyRequest->isOverdue())
                                    <div class="mt-1"><x-admin.badge label="Lewat SLA" tone="rose" /></div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-500">
                                {{ optional($privacyRequest->requested_at)->translatedFormat('d M Y H:i') }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-sm {{ $privacyRequest->isOverdue() ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-slate-500' }}">
                                {{ optional($privacyRequest->due_at)->translatedFormat('d M Y H:i') }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-500">{{ $privacyRequest->handledBy->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('admin.privacy-requests.show', $privacyRequest->id) }}" class="admin-btn-secondary">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">open_in_new</span>
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state
                                    title="Belum ada permintaan PDP"
                                    description="Permintaan dari aplikasi pemilik akan muncul di sini."
                                    icon="shield_person"
                                    tone="slate" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            {{ $privacyRequests->links() }}
        </x-slot:footer>
    </x-admin.panel>
</div>
@endsection
