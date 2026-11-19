@extends('layouts.admin')

@section('title', 'Permintaan Layanan — ' . ($clinicName ?? 'PetHeal'))
@section('header', 'Permintaan Layanan')

@php
    use App\Models\ServiceRequest;
@endphp

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        title="Permintaan Layanan"
        eyebrow="Operasional"
        icon="support_agent"
        description="Pengajuan layanan yang belum ada di katalog klinik. Tinjau kebutuhan, minta evaluasi dokter, lalu kirim penawaran ke pemilik hewan.">
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.service-requests.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ $search }}" placeholder="Cari kode, hewan, pemilik"
                       class="admin-input !w-56 !py-2">
                <select name="status" class="admin-input !w-56 !py-2">
                    <option value="">Semua status</option>
                    @foreach(ServiceRequest::STATUSES as $statusOption)
                        <option value="{{ $statusOption }}" @selected($status === $statusOption)>
                            {{ ServiceRequest::statusLabel($statusOption) }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="admin-btn-secondary !py-2">Filter</button>
            </form>
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
        <x-admin.stat-card label="Perlu ditinjau" :value="(int) ($counts[ServiceRequest::STATUS_SUBMITTED] ?? 0)" icon="inbox" tone="amber" />
        <x-admin.stat-card label="Menunggu dokter" :value="(int) ($counts[ServiceRequest::STATUS_WAITING_DOCTOR] ?? 0)" icon="stethoscope" tone="blue" />
        <x-admin.stat-card label="Penawaran terkirim" :value="(int) ($counts[ServiceRequest::STATUS_OFFERED] ?? 0)" icon="request_quote" tone="primary" />
        <x-admin.stat-card label="Menjadi booking" :value="(int) ($counts[ServiceRequest::STATUS_CONVERTED] ?? 0)" icon="event_available" tone="slate" />
    </div>

    <x-admin.panel title="Daftar Permintaan" subtitle="Hanya permintaan klinik ini" flush>
        @forelse($requests as $item)
            <a href="{{ route('admin.service-requests.show', $item->id) }}"
               class="flex items-start gap-4 border-b border-slate-100 px-5 py-4 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">description</span>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $item->code }}</p>
                        <x-admin.badge :label="ServiceRequest::statusLabel($item->status)" :tone="ServiceRequest::statusTone($item->status)" dot />
                    </div>
                    <p class="mt-0.5 truncate text-sm text-slate-600 dark:text-slate-300">
                        {{ $item->pet->name ?? '—' }} · {{ $item->user->name ?? '—' }}
                        <span class="text-xs text-slate-400">· {{ $item->need_type_label }}</span>
                    </p>
                    <p class="mt-0.5 truncate text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($item->complaint, 110) }}</p>
                </div>
                <div class="hidden flex-shrink-0 text-right sm:block">
                    <p class="text-xs text-slate-400">{{ $item->created_at?->format('d M Y H:i') }}</p>
                    @if($item->doctor)
                        <p class="text-xs text-slate-500 dark:text-slate-400">Evaluasi: {{ $item->doctor->name }}</p>
                    @endif
                </div>
            </a>
        @empty
            <div class="px-5 py-10">
                <x-admin.empty-state
                    title="Belum ada permintaan layanan"
                    description="Pengajuan baru dari aplikasi pemilik hewan akan muncul di sini."
                    icon="support_agent"
                    tone="slate" />
            </div>
        @endforelse
    </x-admin.panel>

    @if($requests->hasPages())
        <div>{{ $requests->links() }}</div>
    @endif
</div>
@endsection
