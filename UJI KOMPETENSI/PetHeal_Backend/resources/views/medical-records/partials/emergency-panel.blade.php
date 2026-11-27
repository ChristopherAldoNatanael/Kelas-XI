@php
$isEmergency = $record->booking?->service_type === 'emergency'
    || $record->encounter?->type === 'emergency';
$isWalkIn = $record->booking?->service_type === 'walk_in'
    || $record->encounter?->type === 'walk_in';
$triageLabels = [
    'red' => 'Merah — mengancam nyawa',
    'orange' => 'Oranye — urgent',
    'yellow' => 'Kuning — perlu segera',
    'green' => 'Hijau — stabil',
];
$triageTones = ['red' => 'rose', 'orange' => 'amber', 'yellow' => 'sky', 'green' => 'green'];
$encounter = $record->encounter;
@endphp

@if($isEmergency || $isWalkIn)
    <x-admin.panel
        :title="$isEmergency ? 'Encounter Darurat' : 'Kunjungan Datang Langsung'"
        :subtitle="$isEmergency ? 'Triage, consent, dan disposisi' : 'Kunjungan tanpa booking dari aplikasi'">
        <x-slot:actions>
            <x-admin.badge :label="$isEmergency ? 'Darurat' : 'Walk-in'" :tone="$isEmergency ? 'rose' : 'sky'" />
        </x-slot:actions>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Triage</div>
                <div class="mt-2">
                    @if($record->booking?->triage_level)
                        <x-admin.badge :label="$triageLabels[$record->booking->triage_level] ?? $record->booking->triage_level" :tone="$triageTones[$record->booking->triage_level] ?? 'slate'" />
                    @else
                        <span class="text-sm text-slate-500">Belum diisi</span>
                    @endif
                </div>
            </div>
            <div class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Status encounter</div>
                <div class="mt-2 text-sm font-semibold">{{ $encounter->status ?? 'Belum terbentuk' }}</div>
            </div>
            <div class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-700">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Disposisi</div>
                <div class="mt-2 text-sm font-semibold">{{ $encounter->disposition ?: '—' }}</div>
            </div>
        </div>

        @if($isEmergency && $encounter)
            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                    <h3 class="text-sm font-bold">Perbarui triage</h3>
                    <form method="POST" action="{{ route($routeBase . '.emergency.triage', $record->id) }}" class="mt-3 space-y-2">
                        @csrf
                        <select name="triage_level" class="admin-input" required>
                            @foreach($triageLabels as $value => $label)
                                <option value="{{ $value }}" @selected(($record->booking?->triage_level ?? $encounter->triage_level) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <textarea name="chief_complaint" rows="2" maxlength="20000" class="admin-input" placeholder="Keluhan utama">{{ $encounter->chief_complaint }}</textarea>
                        <button type="submit" class="admin-btn-secondary w-full">Simpan triage</button>
                    </form>
                </div>

                <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                    <h3 class="text-sm font-bold">Tutup encounter</h3>
                    <form method="POST" action="{{ route($routeBase . '.emergency.close', $record->id) }}" class="mt-3 space-y-2">
                        @csrf
                        <select name="disposition" class="admin-input" required>
                            <option value="">Pilih disposisi</option>
                            @foreach(['stabil', 'rujuk', 'rawat_inap', 'pulang', 'meninggal'] as $disposition)
                                <option value="{{ $disposition }}" @selected($encounter->disposition === $disposition)>{{ str_replace('_', ' ', $disposition) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="admin-btn-secondary w-full" onclick="return confirm('Tutup encounter darurat ini?')">Tutup encounter</button>
                    </form>
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50/50 p-4 dark:border-rose-900/40 dark:bg-rose-950/20">
                <h3 class="text-sm font-bold text-rose-800 dark:text-rose-300">Consent darurat</h3>
                <p class="mt-1 text-xs text-rose-700 dark:text-rose-300">Wajib punya dasar tindakan. Tindakan darurat tidak memberi kredensial baru kepada dokter.</p>

                @if($record->treatmentConsents->isNotEmpty())
                    <ul class="mt-3 space-y-2">
                        @foreach($record->treatmentConsents as $consent)
                            <li class="rounded-lg bg-white/70 px-3 py-2 text-xs dark:bg-slate-900/60">
                                <span class="font-semibold">{{ $consent->consent_type }}</span>
                                · {{ $consent->emergency_basis ?: 'tanpa dasar' }}
                                · {{ $consent->signer_name }}
                                · {{ optional($consent->signed_at)->format('d/m/Y H:i') }}
                                @if($consent->revoked_at)
                                    <span class="text-rose-600">· dicabut</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route($routeBase . '.emergency.consent', $record->id) }}" class="mt-3 space-y-2">
                    @csrf
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <select name="emergency_basis" class="admin-input" required>
                            <option value="">Dasar tindakan</option>
                            <option value="life_threatening">Mengancam nyawa</option>
                            <option value="owner_unavailable">Pemilik tidak tersedia</option>
                            <option value="legal_obligation">Kewajiban hukum</option>
                            <option value="other">Lainnya</option>
                        </select>
                        <input name="signer_name" class="admin-input" placeholder="Nama penandatangan" value="{{ auth()->user()->name }}" required>
                        <input name="signer_relation" class="admin-input" placeholder="Hubungan (petugas/pemilik)" value="petugas" required>
                        <input name="witness_name" class="admin-input" placeholder="Saksi (opsional)">
                    </div>
                    <textarea name="risks_explained" rows="2" maxlength="20000" class="admin-input" placeholder="Risiko dan tindakan yang dijelaskan" required></textarea>
                    <button type="submit" class="admin-btn-secondary w-full">Catat consent darurat</button>
                </form>
            </div>
        @elseif($isEmergency && ! $encounter)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                Encounter darurat belum terbentuk. Simpan rekam medis terlebih dahulu agar relasi encounter dibuat.
            </div>
        @endif
    </x-admin.panel>
@endif
