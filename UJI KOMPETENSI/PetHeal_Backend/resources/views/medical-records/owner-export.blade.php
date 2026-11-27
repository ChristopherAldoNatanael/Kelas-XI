<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Salinan Rekam Medis #{{ $record->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        .muted { color: #64748b; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { padding: 3px 6px 3px 0; vertical-align: top; }
        .label { width: 32%; color: #475569; }
        .box { border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px; white-space: pre-wrap; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 4px 5px; text-align: left; }
        table.data th { background: #f1f5f9; }
        .footer { margin-top: 24px; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    <h1>Salinan Rekam Medis Hewan</h1>
    <div class="muted">Rekam medis #{{ $record->id }} &middot; dibuat {{ $record->created_at?->format('d/m/Y H:i') }} WIB</div>

    <h2>Identitas</h2>
    <table class="grid">
        <tr><td class="label">Nama hewan</td><td>{{ $pet?->name ?? '-' }}</td></tr>
        <tr><td class="label">Spesies / ras</td><td>{{ $pet?->species ?? '-' }}{{ $pet?->breed ? ' / ' . $pet->breed : '' }}</td></tr>
        <tr><td class="label">Jenis kelamin</td><td>{{ $pet?->gender ?? '-' }}</td></tr>
        <tr><td class="label">Klinik</td><td>{{ $clinic?->name ?? '-' }}</td></tr>
        <tr><td class="label">Dokter</td><td>{{ $record->doctor?->name ?? '-' }}</td></tr>
        <tr><td class="label">STR / SIP</td><td>{{ $record->doctor?->str_number ?? '-' }} / {{ $record->doctor?->sip_number ?? '-' }}</td></tr>
        <tr><td class="label">Status rekam medis</td><td>{{ $record->status }}</td></tr>
    </table>

    <h2>Pemeriksaan</h2>
    <table class="grid">
        <tr><td class="label">Diagnosis</td><td>{{ $record->diagnosis ?: '-' }}</td></tr>
        <tr><td class="label">Subjective</td><td>{{ $record->subjective ?: '-' }}</td></tr>
        <tr><td class="label">Objective</td><td>{{ $record->objective ?: '-' }}</td></tr>
        <tr><td class="label">Assessment</td><td>{{ $record->assessment ?: '-' }}</td></tr>
        <tr><td class="label">Plan</td><td>{{ $record->plan ?: '-' }}</td></tr>
        <tr><td class="label">Tindakan</td><td>{{ $record->treatment ?: '-' }}</td></tr>
        <tr><td class="label">Obat</td><td>{{ $record->medicine ?: '-' }}</td></tr>
        <tr><td class="label">Catatan</td><td>{{ $record->notes ?: '-' }}</td></tr>
        <tr><td class="label">Kontrol berikutnya</td><td>{{ $record->next_visit_date ?: '-' }}</td></tr>
    </table>

    @if($vitals)
        <h2>Vital</h2>
        <table class="data">
            <tr>
                <th>Suhu</th><th>Nadi</th><th>Napas</th><th>CRT</th><th>Mukosa</th>
                <th>Hidrasi</th><th>BCS</th><th>Nyeri</th><th>Berat</th>
            </tr>
            <tr>
                <td>{{ $vitals->temperature_c ?? '-' }}</td>
                <td>{{ $vitals->heart_rate ?? '-' }}</td>
                <td>{{ $vitals->respiratory_rate ?? '-' }}</td>
                <td>{{ $vitals->crt_seconds ?? '-' }}</td>
                <td>{{ $vitals->mucosa ?? '-' }}</td>
                <td>{{ $vitals->hydration ?? '-' }}</td>
                <td>{{ $vitals->bcs ?? '-' }}</td>
                <td>{{ $vitals->pain_score ?? '-' }}</td>
                <td>{{ $vitals->weight_kg ?? '-' }}</td>
            </tr>
        </table>
    @endif

    @if($prescriptions->isNotEmpty())
        <h2>Resep</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Obat</th><th>Dosis</th><th>Rute</th><th>Frekuensi</th>
                    <th>Hari</th><th>Jumlah</th><th>Instruksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($prescriptions as $item)
                    <tr>
                        <td>{{ $item->medicine_name }}</td>
                        <td>{{ $item->dose ?: '-' }}</td>
                        <td>{{ $item->route ?: '-' }}</td>
                        <td>{{ $item->frequency ?: '-' }}</td>
                        <td>{{ $item->duration_days ?? '-' }}</td>
                        <td>{{ $item->quantity }} {{ $item->unit }}</td>
                        <td>{{ $item->instruction ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($allergies->isNotEmpty())
        <h2>Alergi</h2>
        <table class="data">
            <thead><tr><th>Alergen</th><th>Reaksi</th><th>Keparahan</th></tr></thead>
            <tbody>
                @foreach($allergies as $allergy)
                    <tr>
                        <td>{{ $allergy->allergen }}</td>
                        <td>{{ $allergy->reaction ?: '-' }}</td>
                        <td>{{ $allergy->severity ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($amendments->isNotEmpty())
        <h2>Riwayat Amendment</h2>
        <table class="data">
            <thead><tr><th>Tanggal</th><th>Jenis</th><th>Status</th><th>Versi diterapkan</th></tr></thead>
            <tbody>
                @foreach($amendments as $amendment)
                    <tr>
                        <td>{{ $amendment->applied_at?->format('d/m/Y H:i') ?? $amendment->requested_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $amendment->amendment_type }}</td>
                        <td>{{ $amendment->status }}</td>
                        <td>{{ $amendment->applied_version_no ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Dokumen ini dihasilkan dari sistem PetHeal. Salinan pemilik bukan pengganti
        dokumen resmi bertanda tangan. Token unduhan bersifat sekali pakai dan
        memiliki masa berlaku terbatas.
    </div>
</body>
</html>
