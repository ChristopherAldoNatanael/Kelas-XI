<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Verifikasi Dokumen Rekam Medis</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f8fafc; margin: 0; padding: 32px 16px; color: #0f172a; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 28px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #64748b; font-size: 13px; line-height: 1.6; }
        .ok { border-color: #16a34a; background: #f0fdf4; color: #166534; }
        .bad { border-color: #dc2626; background: #fef2f2; color: #991b1b; }
        .verdict { border: 1px solid; border-radius: 8px; padding: 12px 14px; margin: 18px 0; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        td { padding: 6px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        td:first-child { width: 38%; color: #64748b; }
        .hash { font-family: ui-monospace, monospace; font-size: 10px; word-break: break-all; color: #475569; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Verifikasi Dokumen Rekam Medis Hewan</h1>
        <p class="muted">Halaman ini memeriksa apakah isi rekam medis masih sama dengan saat dokumen disahkan.</p>

        @if($isIntact)
            <div class="verdict ok">Dokumen utuh — cap isi cocok dengan data yang tersimpan.</div>
        @else
            <div class="verdict bad">
                Cap isi tidak cocok. Dokumen mungkin diubah setelah dicetak, atau belum pernah disahkan.
                Hubungi klinik yang menerbitkan dokumen ini.
            </div>
        @endif

        <table>
            <tr><td>Nomor rekam medis</td><td>RM {{ str_pad((string) $record->id, 6, '0', STR_PAD_LEFT) }}</td></tr>
            <tr><td>Klinik</td><td>{{ $record->clinic?->name ?? '—' }}</td></tr>
            <tr><td>Dokter</td><td>{{ $record->doctor?->name ?? '—' }}</td></tr>
            <tr><td>Pasien</td><td>{{ $record->pet?->name ?? '—' }}</td></tr>
            <tr><td>Tanggal kunjungan</td><td>{{ optional($record->created_at)->format('d/m/Y') }}</td></tr>
            <tr><td>Status dokumen</td><td>{{ $record->isVoided() ? 'Dibatalkan' : 'Sah' }}</td></tr>
            <tr><td>Disahkan</td><td>{{ optional($record->finalized_at)->format('d/m/Y H:i') ?? '—' }}</td></tr>
            <tr><td>Cap isi tercatat</td><td class="hash">{{ $record->content_hash ?? 'belum ada' }}</td></tr>
            <tr><td>Cap isi saat ini</td><td class="hash">{{ $currentHash }}</td></tr>
        </table>

        <p class="muted" style="margin-top:18px;">
            Halaman ini tidak menampilkan diagnosis atau isi pemeriksaan. Salinan lengkap hanya
            diberikan oleh klinik kepada pemilik hewan yang berhak.
        </p>
    </div>
</body>
</html>
