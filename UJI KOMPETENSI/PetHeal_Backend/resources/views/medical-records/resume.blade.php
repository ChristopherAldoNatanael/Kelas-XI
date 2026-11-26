@php
    $clinic = $record->clinic;
    $owner = $record->booking?->user;
    $pet = $record->pet;
    $doctor = $record->doctor;
    $vitals = $record->vitals;
    $statusLabel = $record->isVoided() ? 'DIBATALKAN' : 'SAH';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Resume Medis #{{ $record->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111827; margin: 28px 32px; }
        h1 { font-size: 16px; margin: 0; letter-spacing: .4px; }
        h2 { font-size: 11px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #cbd5e1; text-transform: uppercase; letter-spacing: .6px; }
        .head { display: flex; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 10px; }
        .clinic-name { font-size: 15px; font-weight: bold; }
        .muted { color: #4b5563; font-size: 9.5px; line-height: 1.5; }
        .doc-title { text-align: right; }
        .doc-title .badge { display: inline-block; border: 1px solid #0f172a; padding: 2px 6px; font-size: 8.5px; letter-spacing: .8px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 3px 0; }
        .kv td:first-child { width: 34%; color: #4b5563; }
        .box { border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 10px; }
        .grid { display: flex; gap: 14px; }
        .grid > div { flex: 1; }
        .data th { text-align: left; border-bottom: 1px solid #cbd5e1; font-size: 9px; text-transform: uppercase; letter-spacing: .4px; color: #4b5563; padding-bottom: 4px; }
        .data td { border-bottom: 1px solid #e5e7eb; padding: 4px 0; }
        .pre { white-space: pre-line; line-height: 1.5; }
        .sign { margin-top: 26px; }
        .sign .line { margin-top: 42px; border-top: 1px solid #111827; width: 210px; padding-top: 4px; }
        .hash { word-break: break-all; font-family: DejaVu Sans Mono, monospace; font-size: 8px; color: #4b5563; }
        .void { border: 1px solid #b91c1c; color: #b91c1c; padding: 6px 8px; margin-top: 8px; font-weight: bold; letter-spacing: .5px; }
        .foot { margin-top: 20px; border-top: 1px solid #cbd5e1; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="head">
        <div>
            <div class="clinic-name">{{ $clinic?->name ?? 'Klinik Hewan' }}</div>
            <div class="muted">
                {{ $clinic?->address ?? '—' }}<br>
                @if($clinic?->phone) Telp. {{ $clinic->phone }} · @endif
                @if($clinic?->email) {{ $clinic->email }} @endif
                @if($clinic?->license_number)
                    <br>Izin klinik hewan: {{ $clinic->license_number }}
                    @if($clinic->license_expiry) (berlaku s.d. {{ $clinic->license_expiry->format('d/m/Y') }}) @endif
                @endif
            </div>
        </div>
        <div class="doc-title">
            <div class="badge">RESUME MEDIS HEWAN</div>
            <div class="muted">
                No. RM {{ str_pad((string) $record->id, 6, '0', STR_PAD_LEFT) }}<br>
                Tanggal: {{ optional($record->created_at)->format('d/m/Y H:i') }}<br>
                Status dokumen: {{ $statusLabel }}
            </div>
        </div>
    </div>

    @if($record->isVoided())
        <div class="void">
            DOKUMEN DIBATALKAN{{ $record->void_reason ? ' — ' . $record->void_reason : '' }}.
            Isi tetap disimpan untuk keperluan retensi dan audit.
        </div>
    @endif

    <div class="grid" style="margin-top: 12px;">
        <div>
            <h2>Identitas hewan</h2>
            <table class="kv">
                <tr><td>Nama</td><td>{{ $pet?->name ?? '—' }}</td></tr>
                <tr><td>Spesies / ras</td><td>{{ $pet?->species ?? '—' }}{{ $pet?->breed ? ' / ' . $pet->breed : '' }}</td></tr>
                <tr><td>Jenis kelamin</td><td>{{ $pet?->gender ?? '—' }}</td></tr>
                <tr><td>Tanggal lahir / umur</td><td>{{ optional($pet?->date_of_birth)->format('d/m/Y') ?? ($pet?->age ? $pet->age . ' bulan' : '—') }}</td></tr>
                <tr><td>Berat tercatat</td><td>{{ $vitals?->weight_kg ? $vitals->weight_kg . ' kg' : ($pet?->weight ? $pet->weight . ' kg' : '—') }}</td></tr>
                <tr><td>Microchip</td><td>{{ $pet?->microchip_number ?? '—' }}</td></tr>
            </table>
        </div>
        <div>
            <h2>Pemilik</h2>
            <table class="kv">
                <tr><td>Nama</td><td>{{ $owner?->name ?? '—' }}</td></tr>
                <tr><td>Telepon</td><td>{{ $owner?->phone ?? '—' }}</td></tr>
                <tr><td>Email</td><td>{{ $owner?->email ?? '—' }}</td></tr>
                <tr><td>Booking</td><td>#{{ $record->booking_id }}</td></tr>
            </table>
        </div>
    </div>

    <h2>Anamnesis dan pemeriksaan</h2>
    @if($record->subjective)
        <div class="box"><strong>S — Subjective</strong><div class="pre">{{ $record->subjective }}</div></div>
    @endif
    @if($record->objective)
        <div class="box" style="margin-top:6px;"><strong>O — Objective</strong><div class="pre">{{ $record->objective }}</div></div>
    @endif

    @if($vitals)
        <table class="data" style="margin-top:8px;">
            <thead>
                <tr>
                    <th>Suhu</th><th>Jantung</th><th>Napas</th><th>CRT</th>
                    <th>Mukosa</th><th>Hidrasi</th><th>BCS</th><th>Nyeri</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $vitals->temperature_c ? $vitals->temperature_c . ' °C' : '—' }}</td>
                    <td>{{ $vitals->heart_rate ? $vitals->heart_rate . '/mnt' : '—' }}</td>
                    <td>{{ $vitals->respiratory_rate ? $vitals->respiratory_rate . '/mnt' : '—' }}</td>
                    <td>{{ $vitals->crt_seconds ? $vitals->crt_seconds . ' dtk' : '—' }}</td>
                    <td>{{ $vitals->mucosa ?? '—' }}</td>
                    <td>{{ $vitals->hydration ?? '—' }}</td>
                    <td>{{ $vitals->bcs ?? '—' }}</td>
                    <td>{{ $vitals->pain_score ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if($record->assessment)
        <div class="box" style="margin-top:6px;"><strong>A — Assessment</strong><div class="pre">{{ $record->assessment }}</div></div>
    @endif
    @if($record->plan)
        <div class="box" style="margin-top:6px;"><strong>P — Plan</strong><div class="pre">{{ $record->plan }}</div></div>
    @endif

    <h2>Diagnosis dan tindakan</h2>
    <table class="kv">
        <tr><td>Diagnosis</td><td>{{ $record->diagnosis }}</td></tr>
        <tr><td>Tindakan / terapi</td><td class="pre">{{ $record->treatment }}</td></tr>
        @if($record->notes)
            <tr><td>Catatan</td><td class="pre">{{ $record->notes }}</td></tr>
        @endif
        @if($record->outcome)
            <tr><td>Hasil akhir</td><td>{{ ucfirst($record->outcome) }}{{ $record->cause_of_death ? ' — ' . $record->cause_of_death : '' }}</td></tr>
        @endif
        @if($record->next_visit_date)
            <tr><td>Kontrol berikutnya</td><td>{{ $record->next_visit_date->format('d/m/Y') }}{{ $record->next_visit_time ? ', ' . substr((string) $record->next_visit_time, 0, 5) : '' }}</td></tr>
        @endif
    </table>

    @if($record->prescriptionItems->isNotEmpty())
        <h2>Resep obat</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Obat</th><th>Dosis</th><th>Rute</th><th>Frekuensi</th>
                    <th>Jumlah</th><th>Batch / ED</th><th>Biaya</th>
                </tr>
            </thead>
            <tbody>
                @foreach($record->prescriptionItems as $item)
                    <tr>
                        <td>
                            {{ $item->medicine_name }}
                            @if($item->is_antibiotic) <span class="muted">(antibiotik)</span> @endif
                        </td>
                        <td>{{ $item->dose ?? '—' }}</td>
                        <td>{{ $item->route ?? '—' }}</td>
                        <td>{{ $item->frequency ?? '—' }}{{ $item->duration_days ? ' · ' . $item->duration_days . ' hari' : '' }}</td>
                        <td>{{ format_qty((float) $item->quantity) }} {{ $item->unit }}</td>
                        <td>{{ $item->batch_number ?? '—' }}{{ $item->expiry_date ? ' / ' . $item->expiry_date->format('m/Y') : '' }}</td>
                        <td>{{ format_rupiah($item->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($record->medicine)
        <h2>Obat</h2>
        <div class="box pre">{{ $record->medicine }}</div>
    @endif

    @if($pet?->allergies?->isNotEmpty())
        <h2>Alergi</h2>
        <div class="box">
            {{ $pet->allergies->where('is_active', true)->map(fn ($a) => $a->allergen . ($a->reaction ? ' (' . $a->reaction . ')' : ''))->join(', ') }}
        </div>
    @endif

    @if($record->attachments->isNotEmpty())
        <h2>Lampiran pemeriksaan</h2>
        <ul style="margin:0; padding-left:14px;">
            @foreach($record->attachments as $attachment)
                <li>{{ $attachment->caption ?: $attachment->original_name }}</li>
            @endforeach
        </ul>
    @endif

    <table class="sign" style="margin-top: 26px;">
        <tr>
            <td style="width:60%;" class="muted">
                <strong>Keaslian dokumen</strong><br>
                Cap isi (SHA-256): <span class="hash">{{ $contentHash }}</span><br>
                Verifikasi: <span class="hash">{{ $verifyUrl }}</span>
            </td>
            <td style="text-align:center;">
                Dokter hewan penanggung jawab,
                <div class="line">
                    <strong>{{ $doctor?->name ?? '—' }}</strong><br>
                    @if($doctor?->str_number) STR: {{ $doctor->str_number }}<br>@endif
                    @if($doctor?->sip_number) SIP: {{ $doctor->sip_number }} @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="foot muted">
        Dokumen ini dihasilkan dari sistem rekam medis elektronik PetHeal pada
        {{ now()->format('d/m/Y H:i') }}. Tautan verifikasi berlaku sampai
        {{ now()->addDays(365)->format('d/m/Y') }}. Perubahan setelah dokumen dicetak
        akan membuat cap isi tidak lagi cocok saat diverifikasi.
    </div>
</body>
</html>
