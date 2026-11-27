@extends('layouts.admin')

@section('title', 'Permintaan PDP #' . $privacyRequest->id . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', 'Detail Permintaan PDP')

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
        :title="'Permintaan #' . $privacyRequest->id"
        eyebrow="Kepatuhan UU 27/2022"
        icon="shield_person"
        :description="$typeLabels[$privacyRequest->request_type] ?? $privacyRequest->request_type">
        <x-slot:actions>
            <a href="{{ route('admin.privacy-requests.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Kembali
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200">
            {{ session('error') }}
        </div>
    @endif

    @if($privacyRequest->isOverdue())
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
            Permintaan ini sudah melewati tenggat SLA internal. Segera verifikasi dan beri keputusan.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-admin.panel title="Uraian permintaan" subtitle="Isi yang dikirim pemohon">
                <div class="prose prose-sm max-w-none whitespace-pre-line text-slate-700 dark:prose-invert dark:text-slate-300">{{ $privacyRequest->description }}</div>
            </x-admin.panel>

            @if($privacyRequest->request_type === 'erasure')
                <x-admin.panel title="Pemeriksaan permintaan penghapusan" subtitle="Rekam medis tidak dapat dihapus selama retensi atau legal hold">
                    @if($erasureBlockReason)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                            {{ $erasureBlockReason }}
                        </div>
                        <p class="mt-3 text-sm text-slate-500">Tolak permintaan dengan alasan hukum yang jelas. Jangan menghapus rekam medis final.</p>
                    @else
                        <p class="text-sm text-slate-600 dark:text-slate-300">Tidak ada rekam medis final dalam retensi atau legal hold untuk pemohon ini. Tetap tinjau kewajiban retensi sebelum menyelesaikan.</p>
                    @endif
                </x-admin.panel>
            @endif

            @if($privacyRequest->status === 'rejected')
                <x-admin.panel title="Alasan penolakan" subtitle="Tercatat untuk pemohon dan audit">
                    <div class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $privacyRequest->rejection_reason }}</div>
                </x-admin.panel>
            @endif

            @if($privacyRequest->status === 'completed')
                <x-admin.panel title="Jawaban / lokasi berkas" subtitle="Disimpan sebagai referensi internal">
                    <div class="break-all text-sm text-slate-700 dark:text-slate-300">{{ $privacyRequest->response_path ?: 'Tidak ada path jawaban.' }}</div>
                </x-admin.panel>
            @endif
        </div>

        <div class="space-y-6">
            <x-admin.panel title="Ringkasan">
                <dl class="space-y-4 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Status</dt>
                        <dd><x-admin.badge :label="$statusLabels[$privacyRequest->status] ?? $privacyRequest->status" :tone="$statusTones[$privacyRequest->status] ?? 'slate'" /></dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Pemohon</dt>
                        <dd class="text-right font-medium">
                            @if($privacyRequest->user)
                                <a href="{{ route('admin.users.show', $privacyRequest->user->id) }}" class="text-emerald-700 hover:underline dark:text-emerald-400">{{ $privacyRequest->user->name }}</a>
                                <div class="text-xs text-slate-400">{{ $privacyRequest->user->email }}</div>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Klinik</dt>
                        <dd class="text-right font-medium">{{ $privacyRequest->clinic->name ?? 'Tanpa klinik' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Diajukan</dt>
                        <dd class="text-right font-medium tabular-nums">{{ optional($privacyRequest->requested_at)->translatedFormat('d M Y H:i') }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Tenggat</dt>
                        <dd class="text-right font-medium tabular-nums {{ $privacyRequest->isOverdue() ? 'text-rose-600 dark:text-rose-400' : '' }}">{{ optional($privacyRequest->due_at)->translatedFormat('d M Y H:i') }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Diverifikasi</dt>
                        <dd class="text-right font-medium tabular-nums">{{ optional($privacyRequest->verified_at)->translatedFormat('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Selesai</dt>
                        <dd class="text-right font-medium tabular-nums">{{ optional($privacyRequest->completed_at)->translatedFormat('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-slate-500">Ditangani</dt>
                        <dd class="text-right font-medium">{{ $privacyRequest->handledBy->name ?? '—' }}</dd>
                    </div>
                </dl>
            </x-admin.panel>

            <x-admin.panel title="Berkas jawaban" subtitle="Salinan data yang akan diunduh pemohon">
                @if($privacyRequest->response_file_path)
                    <div class="space-y-2 text-sm">
                        <div class="break-all font-medium text-slate-800 dark:text-slate-200">{{ $privacyRequest->response_file_name }}</div>
                        <div class="text-xs text-slate-500">
                            {{ number_format(((int) $privacyRequest->response_file_size) / 1024, 0, ',', '.') }} KB �
                            {{ $privacyRequest->response_file_encrypted ? 'terenkripsi' : 'plaintext' }} �
                            {{ (int) $privacyRequest->response_download_count }} unduhan
                        </div>
                        <div class="break-all text-[11px] text-slate-400">SHA-256: {{ $privacyRequest->response_file_sha256 }}</div>
                        @if($privacyRequest->response_last_downloaded_at)
                            <div class="text-xs text-slate-500">Terakhir diunduh {{ $privacyRequest->response_last_downloaded_at->translatedFormat('d M Y H:i') }}</div>
                        @endif
                        <a href="{{ route('admin.privacy-requests.response.download', $privacyRequest->id) }}" class="admin-btn-secondary w-full">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">download</span>
                            Unduh berkas jawaban
                        </a>
                    </div>
                @else
                    <p class="text-sm text-slate-500">Belum ada berkas jawaban.</p>
                @endif

                @if(in_array($privacyRequest->status, ['received', 'verified', 'in_progress'], true))
                    <div class="mt-4 space-y-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <form method="POST" action="{{ route('admin.privacy-requests.response.generate', $privacyRequest->id) }}">
                            @csrf
                            <button type="submit" class="admin-btn-primary w-full" onclick="return confirm('Buat salinan data otomatis dari profil, hewan, booking, dan rekam medis pemohon?')">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">auto_awesome</span>
                                Buat salinan data otomatis
                            </button>
                            <p class="mt-1 text-xs text-slate-500">Sistem membuat PDF dari data pemohon. Tidak perlu menyiapkan berkas manual.</p>
                        </form>

                        <form method="POST" action="{{ route('admin.privacy-requests.response.attach', $privacyRequest->id) }}" enctype="multipart/form-data" class="space-y-2">
                            @csrf
                            <label for="response_file" class="admin-label">Atau unggah PDF sendiri</label>
                            <input id="response_file" name="response_file" type="file" accept="application/pdf" required class="admin-input">
                            <button type="submit" class="admin-btn-secondary w-full">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">upload_file</span>
                                Unggah berkas jawaban
                            </button>
                        </form>
                    </div>
                @endif
            </x-admin.panel>

            @if(in_array($privacyRequest->status, ['received', 'verified', 'in_progress'], true))
                <x-admin.panel title="Tindakan" subtitle="Setiap tindakan dicatat di audit log">
                    <div class="space-y-3">
                        @if($privacyRequest->status === 'received')
                            <form method="POST" action="{{ route('admin.privacy-requests.verify', $privacyRequest->id) }}">
                                @csrf
                                <button type="submit" class="admin-btn-primary w-full">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">verified_user</span>
                                    Verifikasi identitas
                                </button>
                            </form>
                        @endif

                        @if($privacyRequest->status === 'verified')
                            <form method="POST" action="{{ route('admin.privacy-requests.start', $privacyRequest->id) }}">
                                @csrf
                                <button type="submit" class="admin-btn-primary w-full">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">play_arrow</span>
                                    Mulai proses
                                </button>
                            </form>
                        @endif

                        @if(in_array($privacyRequest->status, ['verified', 'in_progress'], true))
                            <form method="POST" action="{{ route('admin.privacy-requests.complete', $privacyRequest->id) }}" class="space-y-2">
                                @csrf
                                <label for="response_path" class="admin-label">Lokasi jawaban / berkas (opsional)</label>
                                <input id="response_path" name="response_path" type="text" maxlength="255" class="admin-input" placeholder="contoh: exports/pdp/PR-2026-001.pdf">
                                @if(! $privacyRequest->response_file_path)
                                    <p class="text-xs text-amber-700 dark:text-amber-300">Belum ada berkas jawaban. Pemohon tidak akan dapat mengunduh salinan data. Sebaiknya unggah atau buat otomatis terlebih dahulu.</p>
                                @endif
                                <button type="submit" class="admin-btn-primary w-full" onclick="return confirm('Tandai permintaan ini selesai?')">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">task_alt</span>
                                    Tandai selesai
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.privacy-requests.reject', $privacyRequest->id) }}" class="space-y-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                            @csrf
                            <label for="rejection_reason" class="admin-label">Alasan penolakan</label>
                            <textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="20000" required class="admin-input" placeholder="Tulis alasan hukum atau alasan penolakan yang dapat dipahami pemohon."></textarea>
                            <button type="submit" class="admin-btn-secondary w-full" onclick="return confirm('Tolak permintaan ini? Alasan akan tercatat.')">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">block</span>
                                Tolak permintaan
                            </button>
                        </form>
                    </div>
                </x-admin.panel>
            @endif
        </div>
    </div>
</div>
@endsection
