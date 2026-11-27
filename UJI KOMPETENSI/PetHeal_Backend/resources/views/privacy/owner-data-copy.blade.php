<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Salinan Data PDP #{{ $privacyRequest->id }}</title>
    <style>
        @page { margin: 32px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; line-height: 1.5; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #065f46; }
        h2 { font-size: 12px; margin: 18px 0 6px; color: #065f46; border-bottom: 1px solid #d1fae5; padding-bottom: 3px; }
        .muted { color: #6b7280; }
        .small { font-size: 8.5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { background: #f0fdf4; font-size: 8.5px; text-transform: uppercase; letter-spacing: .04em; color: #047857; }
        .box { border: 1px solid #d1d5db; padding: 8px 10px; margin-top: 8px; background: #fafafa; }
        .record { page-break-inside: avoid; margin-bottom: 10px; }
        .label { color: #6b7280; font-size: 8.5px; }
        .value { font-weight: bold; }
        .footer { margin-top: 20px; font-size: 8px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>Salinan Data Pribadi</h1>
    <div class="muted">Permintaan PDP #{{ $privacyRequest->id }} · {{ $privacyRequest->request_type }}</div>
    <div class="muted small">Dibuat otomatis oleh PetHeal pada {{ $generatedAt->translatedFormat('d F Y H:i') }} WIB.</div>

    <div class="box small">
        Dokumen ini berisi data yang tersimpan untuk pemohon. Bukan resume medis resmi dan bukan pengganti
        rekam medis yang ditandatangani dokter. Data internal audit dan catatan peninjauan tidak disertakan.
    </div>

    <h2>Data Pemilik</h2>
    <table>
        <tr><th style="width:28%">Nama</th><td>{{ $owner->name }}</td></tr>
        <tr><th>Email</th><td>{{ $owner->email }}</td></tr>
        <tr><th>Telepon</th><td>{{ $owner->phone ?: '—' }}</td></tr>
        <tr><th>Terdaftar</th><td>{{ optional($owner->created_at)->translatedFormat('d F Y H:i') }}</td></tr>
    </table>

    <h2>Data Hewan ({{ $pets->count() }})</h2>
    @if($pets->isEmpty())
        <p class="muted">Tidak ada data hewan.</p>
    @else
        <table>
            <thead>
                <tr><th>Nama</th><th>Spesies</th><th>Ras</th><th>Kelamin</th><th>Lahir</th><th>Berat</th></tr>
            </thead>
            <tbody>
                @foreach($pets as $pet)
                    <tr>
                        <td>{{ $pet->name }}</td>
                        <td>{{ $pet->species ?: '—' }}</td>
                        <td>{{ $pet->breed ?: '—' }}</td>
                        <td>{{ $pet->gender ?: '—' }}</td>
                        <td>{{ $pet->date_of_birth ?: '—' }}</td>
                        <td>{{ $pet->weight ? $pet->weight . ' kg' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Riwayat Booking ({{ $bookings->count() }})</h2>
    @if($bookings->isEmpty())
        <p class="muted">Tidak ada data booking.</p>
    @else
        <table>
            <thead>
                <tr><th>Tanggal</th><th>Hewan</th><th>Dokter</th><th>Klinik</th><th>Status</th><th>Total</th></tr>
            </thead>
            <tbody>
                @foreach($bookings as $booking)
                    <tr>
                        <td>{{ $booking->booking_date }} {{ $booking->booking_time }}</td>
                        <td>{{ $booking->pet->name ?? '—' }}</td>
                        <td>{{ $booking->doctor->name ?? '—' }}</td>
                        <td>{{ $booking->clinic->name ?? '—' }}</td>
                        <td>{{ $booking->status }}</td>
                        <td>Rp {{ number_format((float) $booking->total_amount, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Rekam Medis ({{ $records->count() }})</h2>
    @if($records->isEmpty())
        <p class="muted">Tidak ada rekam medis.</p>
    @else
        @foreach($records as $record)
            <div class="record">
                <table>
                    <tr>
                        <th style="width:20%">Tanggal</th>
                        <td>{{ optional($record->created_at)->translatedFormat('d F Y H:i') }}</td>
                        <th style="width:15%">Status</th>
                        <td>{{ $record->status }}</td>
                    </tr>
                    <tr>
                        <th>Hewan</th>
                        <td>{{ $record->pet->name ?? '—' }}</td>
                        <th>Dokter</th>
                        <td>{{ $record->doctor->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Klinik</th>
                        <td colspan="3">{{ $record->clinic->name ?? '—' }}</td>
                    </tr>
                    <tr><th>Diagnosis</th><td colspan="3">{{ $record->diagnosis ?: '—' }}</td></tr>
                    <tr><th>Tindakan</th><td colspan="3">{{ $record->treatment ?: '—' }}</td></tr>
                    @if($record->subjective)
                        <tr><th>Subjective</th><td colspan="3">{{ $record->subjective }}</td></tr>
                    @endif
                    @if($record->objective)
                        <tr><th>Objective</th><td colspan="3">{{ $record->objective }}</td></tr>
                    @endif
                    @if($record->assessment)
                        <tr><th>Assessment</th><td colspan="3">{{ $record->assessment }}</td></tr>
                    @endif
                    @if($record->plan)
                        <tr><th>Plan</th><td colspan="3">{{ $record->plan }}</td></tr>
                    @endif
                </table>
            </div>
        @endforeach
    @endif

    <div class="footer">
        Dokumen ini dihasilkan dari permintaan PDP #{{ $privacyRequest->id }} pada {{ $generatedAt->toIso8601String() }}.
        Setiap unduhan dicatat pada audit log. Jangan bagikan dokumen ini tanpa persetujuan pemilik data.
    </div>
</body>
</html>
