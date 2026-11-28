@extends('layouts.admin')

@section('title', 'Ruang Klinis — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', 'Ruang Klinis')

@php
$labTones = ['draft' => 'slate', 'ordered' => 'amber', 'partially_collected' => 'sky', 'completed' => 'green', 'cancelled' => 'rose'];
$inpatientTones = ['admitted' => 'amber', 'in_treatment' => 'sky', 'discharged' => 'green', 'transferred' => 'blue', 'died' => 'rose'];
$referralTones = ['sent' => 'amber', 'accepted' => 'green', 'returned' => 'sky', 'cancelled' => 'slate', 'void' => 'rose'];
$reportTones = ['draft' => 'slate', 'ready' => 'amber', 'reported' => 'sky', 'verified' => 'green', 'cancelled' => 'rose'];
$formatBytes = static fn ($bytes) => $bytes ? number_format(((int) $bytes) / 1024, 0, ',', '.') . ' KB' : '—';
@endphp

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        title="Ruang Klinis"
        eyebrow="Laboratorium, rawat inap, rujukan, dan dokumen"
        icon="clinical_notes"
        description="Satu tempat untuk pekerjaan klinis lanjutan yang tidak masuk alur booking harian.">
        <x-slot:actions>
            <a href="{{ route('admin.walk-ins.index') }}" class="admin-btn-secondary">Walk-in & Darurat</a>
            <a href="{{ route('admin.queue.index') }}" class="admin-btn-secondary">Papan Antrian</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200/70 bg-white p-1.5 dark:border-slate-700 dark:bg-slate-900" role="tablist" aria-label="Bagian Ruang Klinis" data-clinical-tablist>
        @foreach([
            'lab' => 'Laboratorium',
            'inpatient' => 'Rawat Inap',
            'referral' => 'Rujukan',
            'disease' => 'Penyakit Menular',
            'templates' => 'Template',
            'exports' => 'Ekspor Medis',
            'welfare' => 'Kesejahteraan',
        ] as $key => $label)
            <button type="button" id="clinical-tab-{{ $key }}" data-clinical-tab="{{ $key }}"
                    role="tab" aria-selected="{{ $key === 'lab' ? 'true' : 'false' }}"
                    aria-controls="clinical-panel-{{ $key }}"
                    tabindex="{{ $key === 'lab' ? '0' : '-1' }}"
                    class="clinical-tab shrink-0 rounded-xl px-4 py-2 text-sm font-semibold text-slate-500 transition hover:bg-slate-50 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-white">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <section id="clinical-panel-lab" role="tabpanel" aria-labelledby="clinical-tab-lab" tabindex="0" data-clinical-panel="lab" class="space-y-4">
        <x-admin.panel title="Order laboratorium" subtitle="Status order, spesimen, dan hasil" flush>
            <x-slot:actions><x-admin.badge :label="'Total · ' . $labOrders->count()" tone="slate" /></x-slot:actions>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left">
                    <thead class="admin-table-head"><tr>
                        <th class="px-5 py-3">Waktu</th>
                        <th class="px-5 py-3">Hewan</th>
                        <th class="px-5 py-3">Pemeriksaan</th>
                        <th class="px-5 py-3">Prioritas</th>
                        <th class="px-5 py-3">Spesimen</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Rekam Medis</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @forelse($labOrders as $order)
                            <tr class="admin-table-row">
                                <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-500">{{ optional($order->ordered_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3 text-sm font-medium">{{ $order->medicalRecord->pet->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm">
                                    {{ $order->items->pluck('test_name')->take(2)->join(', ') ?: '—' }}
                                    @if($order->items->count() > 2)<span class="text-xs text-slate-500"> +{{ $order->items->count() - 2 }}</span>@endif
                                </td>
                                <td class="px-5 py-3"><x-admin.badge :label="ucfirst($order->priority)" :tone="$order->priority === 'stat' ? 'rose' : ($order->priority === 'urgent' ? 'amber' : 'slate')" /></td>
                                <td class="px-5 py-3 text-sm">{{ $order->specimens->count() }}</td>
                                <td class="px-5 py-3"><x-admin.badge :label="str_replace('_', ' ', $order->status)" :tone="$labTones[$order->status] ?? 'slate'" /></td>
                                <td class="px-5 py-3">
                                    @if($order->medical_record_id)
                                        <a href="{{ route('admin.medical-records.show', $order->medical_record_id) }}" class="text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-400">Buka #{{ $order->medical_record_id }}</a>
                                    @else — @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-admin.empty-state title="Belum ada order laboratorium" description="Order laboratorium akan muncul di sini." icon="science" tone="slate" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($labOrders->hasPages())
                <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    {{ $labOrders->links() }}
                </div>
            @endif
        </x-admin.panel>
    </section>

    <section id="clinical-panel-inpatient" role="tabpanel" aria-labelledby="clinical-tab-inpatient" tabindex="0" data-clinical-panel="inpatient" class="hidden space-y-4">
        <x-admin.panel title="Rawat inap" subtitle="Pasien yang dirawat dan riwayat pemulangan" flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[940px] text-left">
                    <thead class="admin-table-head"><tr>
                        <th class="px-5 py-3">Hewan</th>
                        <th class="px-5 py-3">Lokasi</th>
                        <th class="px-5 py-3">Dokter</th>
                        <th class="px-5 py-3">Masuk</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Tindakan</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @forelse($inpatients as $stay)
                            <tr class="admin-table-row align-top">
                                <td class="px-5 py-3 text-sm font-medium">{{ $stay->record->pet->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm">{{ trim(($stay->ward ?: '—') . ' / ' . ($stay->kennel_code ?: '—')) }}</td>
                                <td class="px-5 py-3 text-sm">{{ $stay->admittingDoctor->name ?? '—' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-500">{{ optional($stay->admitted_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3"><x-admin.badge :label="str_replace('_', ' ', $stay->status)" :tone="$inpatientTones[$stay->status] ?? 'slate'" /></td>
                                <td class="px-5 py-3">
                                    @if(in_array($stay->status, ['admitted', 'in_treatment'], true))
                                        <form method="POST" action="{{ route('admin.clinical.inpatient.discharge', $stay->id) }}" class="space-y-2">
                                            @csrf
                                            <textarea name="discharge_summary" rows="2" required maxlength="20000" class="admin-input" placeholder="Ringkasan pulang"></textarea>
                                            <input name="discharge_instructions" class="admin-input" maxlength="20000" placeholder="Instruksi pulang (opsional)">
                                            <button type="submit" class="admin-btn-secondary w-full" onclick="return confirm('Pulangkan pasien ini?')">Pulangkan</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-500">{{ $stay->discharge_summary ?: '—' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-admin.empty-state title="Belum ada rawat inap" description="Data rawat inap akan muncul di sini." icon="bed" tone="slate" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($inpatients->hasPages())
                <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    {{ $inpatients->links() }}
                </div>
            @endif
        </x-admin.panel>
    </section>

    <section id="clinical-panel-referral" role="tabpanel" aria-labelledby="clinical-tab-referral" tabindex="0" data-clinical-panel="referral" class="hidden space-y-4">
        <x-admin.panel title="Rujukan" subtitle="Rujukan keluar dan masuk" flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left">
                    <thead class="admin-table-head"><tr>
                        <th class="px-5 py-3">Nomor</th>
                        <th class="px-5 py-3">Hewan</th>
                        <th class="px-5 py-3">Tujuan</th>
                        <th class="px-5 py-3">Arah</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Tindakan</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @forelse($referrals as $referral)
                            <tr class="admin-table-row align-top">
                                <td class="px-5 py-3 font-mono text-xs">{{ $referral->referral_number }}</td>
                                <td class="px-5 py-3 text-sm font-medium">{{ $referral->record->pet->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm">{{ $referral->referred_to ?: $referral->external_facility ?: '—' }}</td>
                                <td class="px-5 py-3 text-sm">{{ ucfirst($referral->direction) }}</td>
                                <td class="px-5 py-3"><x-admin.badge :label="$referral->status" :tone="$referralTones[$referral->status] ?? 'slate'" /></td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-500">{{ optional($referral->referred_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3">
                                    @if($referral->status !== 'void')
                                        <form method="POST" action="{{ route('admin.clinical.referrals.void', $referral->id) }}" class="space-y-2">
                                            @csrf
                                            <input name="reason" required maxlength="20000" class="admin-input" placeholder="Alasan pembatalan">
                                            <button type="submit" class="admin-btn-secondary w-full" onclick="return confirm('Batalkan rujukan ini?')">Batalkan</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-rose-600">{{ $referral->void_reason }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-admin.empty-state title="Belum ada rujukan" description="Rujukan akan muncul di sini." icon="send" tone="slate" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($referrals->hasPages())
                <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    {{ $referrals->links() }}
                </div>
            @endif
        </x-admin.panel>
    </section>

    <section id="clinical-panel-disease" role="tabpanel" aria-labelledby="clinical-tab-disease" tabindex="0" data-clinical-panel="disease" class="hidden space-y-4">
        <x-admin.panel title="Laporan penyakit menular" subtitle="Tenggat 24 jam dan status pelaporan" flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left">
                    <thead class="admin-table-head"><tr>
                        <th class="px-5 py-3">Penyakit</th>
                        <th class="px-5 py-3">Hewan</th>
                        <th class="px-5 py-3">Klasifikasi</th>
                        <th class="px-5 py-3">Tenggat</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Verifikasi</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @forelse($diseaseReports as $report)
                            <tr class="admin-table-row">
                                <td class="px-5 py-3 text-sm font-medium">{{ $report->disease_name }} <span class="text-xs text-slate-500">{{ $report->disease_code }}</span></td>
                                <td class="px-5 py-3 text-sm">{{ $report->record->pet->name ?? '—' }}</td>
                                <td class="px-5 py-3"><x-admin.badge :label="$report->classification" tone="sky" /></td>
                                <td class="px-5 py-3 text-sm {{ $report->isOverdue() && $report->status !== 'verified' ? 'font-semibold text-rose-600' : 'text-slate-500' }}">{{ optional($report->report_deadline_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3"><x-admin.badge :label="$report->status" :tone="$reportTones[$report->status] ?? 'slate'" /></td>
                                <td class="px-5 py-3 text-sm">{{ $report->verifiedBy->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-admin.empty-state title="Belum ada laporan penyakit" description="Laporan penyakit menular akan muncul di sini." icon="coronavirus" tone="slate" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($diseaseReports->hasPages())
                <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    {{ $diseaseReports->links() }}
                </div>
            @endif
        </x-admin.panel>
    </section>

    <section id="clinical-panel-templates" role="tabpanel" aria-labelledby="clinical-tab-templates" tabindex="0" data-clinical-panel="templates" class="hidden grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-admin.panel title="Template klinis" subtitle="Versi aktif per klinik">
            <div class="space-y-3">
                @forelse($clinicalTemplates as $template)
                    <div class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-semibold text-sm">{{ $template->name }}</div>
                                <div class="text-xs text-slate-500">{{ $template->template_key }} · versi {{ $template->version }} {{ $template->species ? '· ' . $template->species : '' }}</div>
                            </div>
                            <x-admin.badge label="Aktif" tone="green" />
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada template klinis.</p>
                @endforelse
            </div>

            <details class="mt-4 rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                <summary class="cursor-pointer text-sm font-semibold">Publikasikan template klinis</summary>
                <form method="POST" action="{{ route('admin.clinical.templates.clinical') }}" class="mt-3 space-y-2">
                    @csrf
                    <input name="template_key" required maxlength="80" class="admin-input" placeholder="contoh: pemeriksaan_umum">
                    <input name="name" required maxlength="150" class="admin-input" placeholder="Nama template">
                    <input name="species" maxlength="40" class="admin-input" placeholder="Spesies (opsional)">
                    <textarea name="schema" rows="5" required class="admin-input" placeholder='{"fields":[{"key":"suhu","label":"Suhu","type":"number"}]}'>{{ old('schema') }}</textarea>
                    <button type="submit" class="admin-btn-primary w-full">Publikasikan</button>
                </form>
            </details>
        </x-admin.panel>

        <x-admin.panel title="Template consent" subtitle="Versi aktif per klinik">
            <div class="space-y-3">
                @forelse($consentTemplates as $template)
                    <div class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-semibold text-sm">{{ $template->name }}</div>
                                <div class="text-xs text-slate-500">{{ $template->template_key }} · {{ $template->consent_type }} · versi {{ $template->version }}</div>
                            </div>
                            <x-admin.badge label="Aktif" tone="green" />
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada template consent.</p>
                @endforelse
            </div>

            <details class="mt-4 rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                <summary class="cursor-pointer text-sm font-semibold">Publikasikan template consent</summary>
                <form method="POST" action="{{ route('admin.clinical.templates.consent') }}" class="mt-3 space-y-2">
                    @csrf
                    <input name="template_key" required maxlength="80" class="admin-input" placeholder="contoh: tindakan_umum">
                    <input name="name" required maxlength="150" class="admin-input" placeholder="Nama template">
                    <select name="consent_type" class="admin-input" required>
                        @foreach(['tindakan', 'anestesi', 'euthanasia', 'rawat_inap', 'darurat'] as $type)
                            <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                    <textarea name="body" rows="5" required maxlength="20000" class="admin-input" placeholder="Isi persetujuan...">{{ old('body') }}</textarea>
                    <button type="submit" class="admin-btn-primary w-full">Publikasikan</button>
                </form>
            </details>
        </x-admin.panel>
    </section>

    <section id="clinical-panel-exports" role="tabpanel" aria-labelledby="clinical-tab-exports" tabindex="0" data-clinical-panel="exports" class="hidden space-y-4">
        <x-admin.panel title="Ekspor rekam medis" subtitle="Salinan resmi dan status akses" flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left">
                    <thead class="admin-table-head"><tr>
                        <th class="px-5 py-3">Hewan</th>
                        <th class="px-5 py-3">Format</th>
                        <th class="px-5 py-3">Tujuan</th>
                        <th class="px-5 py-3">Penerima</th>
                        <th class="px-5 py-3">Kedaluwarsa</th>
                        <th class="px-5 py-3">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @forelse($exports as $export)
                            <tr class="admin-table-row">
                                <td class="px-5 py-3 text-sm font-medium">{{ $export->record->pet->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm uppercase">{{ $export->format }}</td>
                                <td class="px-5 py-3 text-sm">{{ str_replace('_', ' ', $export->purpose) }}</td>
                                <td class="px-5 py-3 text-sm">{{ $export->recipient_name ?: '—' }}</td>
                                <td class="px-5 py-3 text-sm text-slate-500">{{ optional($export->expires_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3">
                                    @if($export->revoked_at) <x-admin.badge label="Dicabut" tone="rose" />
                                    @elseif($export->isExpired()) <x-admin.badge label="Kedaluwarsa" tone="slate" />
                                    @elseif($export->isUsed()) <x-admin.badge label="Sudah diunduh" tone="green" />
                                    @else <x-admin.badge label="Aktif" tone="amber" /> @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-admin.empty-state title="Belum ada ekspor" description="Ekspor rekam medis akan muncul di sini." icon="download" tone="slate" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($exports->hasPages())
                <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    {{ $exports->links() }}
                </div>
            @endif
        </x-admin.panel>
    </section>

    <section id="clinical-panel-welfare" role="tabpanel" aria-labelledby="clinical-tab-welfare" tabindex="0" data-clinical-panel="welfare" class="hidden space-y-4">
        <x-admin.panel title="Kesejahteraan hewan" subtitle="Penilaian lima kebebasan" flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left">
                    <thead class="admin-table-head"><tr>
                        <th class="px-5 py-3">Hewan</th>
                        <th class="px-5 py-3">Skor rata-rata</th>
                        <th class="px-5 py-3">Dinilai oleh</th>
                        <th class="px-5 py-3">Waktu</th>
                        <th class="px-5 py-3">Catatan</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @forelse($welfare as $assessment)
                            <tr class="admin-table-row">
                                <td class="px-5 py-3 text-sm font-medium">{{ $assessment->record->pet->name ?? '—' }}</td>
                                <td class="px-5 py-3"><x-admin.badge :label="number_format($assessment->averageScore(), 1)" :tone="$assessment->averageScore() >= 4 ? 'green' : ($assessment->averageScore() >= 3 ? 'amber' : 'rose')" /></td>
                                <td class="px-5 py-3 text-sm">{{ $assessment->assessedBy->name ?? '—' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-500">{{ optional($assessed_at = $assessment->assessed_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($assessment->notes, 80) ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-admin.empty-state title="Belum ada penilaian" description="Penilaian kesejahteraan hewan akan muncul di sini." icon="pets" tone="slate" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($welfare->hasPages())
                <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    {{ $welfare->links() }}
                </div>
            @endif
        </x-admin.panel>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const tabs = Array.from(document.querySelectorAll('[data-clinical-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-clinical-panel]'));

    function activate(key, focus = false) {
        tabs.forEach((tab) => {
            const active = tab.dataset.clinicalTab === key;
            tab.classList.toggle('bg-emerald-700', active);
            tab.classList.toggle('text-white', active);
            tab.classList.toggle('text-slate-500', !active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.setAttribute('tabindex', active ? '0' : '-1');
            if (active && focus) tab.focus();
        });
        panels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.clinicalPanel !== key);
        });
        if (history.replaceState) history.replaceState(null, '', '#' + key);
    }

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab.dataset.clinicalTab));
        tab.addEventListener('keydown', (event) => {
            let next = index;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            else if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = tabs.length - 1;
            else return;
            event.preventDefault();
            activate(tabs[next].dataset.clinicalTab, true);
        });
    });
    activate(location.hash.replace('#', '') || 'lab');
})();
</script>
@endpush
