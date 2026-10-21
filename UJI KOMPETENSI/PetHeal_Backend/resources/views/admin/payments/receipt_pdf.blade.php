<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT) }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 36px 40px;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.55;
        }
        .top {
            width: 100%;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .clinic-name {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 4px;
        }
        .muted { color: #64748b; }
        .tiny { font-size: 10px; }
        .invoice-title {
            text-align: right;
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 0;
            color: #0f172a;
        }
        .invoice-number {
            text-align: right;
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: bold;
            background: #e8f9ef;
            color: #087a3a;
        }
        .badge-warning { background: #fff7e6; color: #b45309; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .panel {
            width: 100%;
            margin-bottom: 20px;
        }
        .panel td {
            width: 50%;
            vertical-align: top;
            padding: 0 12px 0 0;
        }
        .panel td:last-child { padding: 0 0 0 12px; }
        .label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin: 0 0 4px;
        }
        .value { margin: 0; font-weight: bold; }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.items th {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 8px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }
        table.items td {
            padding: 12px 8px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .right { text-align: right; }
        .totals {
            width: 48%;
            margin-left: auto;
            margin-top: 14px;
            border-collapse: collapse;
        }
        .totals td { padding: 6px 0; }
        .totals .total-row td {
            border-top: 2px solid #0f172a;
            padding-top: 10px;
            font-size: 15px;
            font-weight: bold;
        }
        .note {
            margin-top: 28px;
            padding: 12px 14px;
            background: #f8fafc;
            border-left: 3px solid #18c964;
            color: #475569;
            font-size: 10px;
        }
        .footer {
            margin-top: 36px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 9px;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $invoiceNumber = 'INV-' . str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT);
        $status = $booking->payment_status;
        $statusLabel = match ($status) {
            'paid' => 'LUNAS',
            'dp_paid' => 'DP TERBAYAR',
            'partial' => 'PEMBAYARAN SEBAGIAN',
            'pending' => 'MENUNGGU PEMBAYARAN',
            'failed' => 'PEMBAYARAN GAGAL',
            default => 'BELUM DIBAYAR',
        };
        $badgeClass = match ($status) {
            'paid' => 'badge',
            'dp_paid' => 'badge badge-info',
            'pending' => 'badge badge-warning',
            'failed' => 'badge badge-danger',
            default => 'badge badge-warning',
        };
        $clinic = $booking->clinic;
    @endphp

    <table class="top">
        <tr>
            <td style="vertical-align: top;">
                <p class="clinic-name">{{ $clinic->name ?? 'PetHeal Clinic' }}</p>
                @if($clinic?->address)
                    <p class="muted tiny" style="margin: 0;">{{ $clinic->address }}</p>
                @endif
                @if($clinic?->phone || $clinic?->email)
                    <p class="muted tiny" style="margin: 2px 0 0;">
                        {{ collect([$clinic->phone, $clinic->email])->filter()->join(' · ') }}
                    </p>
                @endif
            </td>
            <td style="vertical-align: top;">
                <p class="invoice-title">INVOICE</p>
                <p class="invoice-number">{{ $invoiceNumber }}</p>
                <p style="margin: 8px 0 0; text-align: right;">
                    <span class="{{ $badgeClass }}">{{ $statusLabel }}</span>
                </p>
            </td>
        </tr>
    </table>

    <table class="panel">
        <tr>
            <td>
                <p class="label">Ditagihkan kepada</p>
                <p class="value">{{ $booking->user->name ?? '-' }}</p>
                <p class="muted tiny" style="margin: 2px 0 0;">{{ $booking->user->email ?? '-' }}</p>
                <p class="muted tiny" style="margin: 2px 0 0;">{{ $booking->user->phone ?? '-' }}</p>
            </td>
            <td>
                <p class="label">Detail kunjungan</p>
                <p class="value">{{ $booking->pet->name ?? '-' }}</p>
                <p class="muted tiny" style="margin: 2px 0 0;">
                    {{ $booking->doctor->name ?? '-' }}
                    @if($booking->doctor?->specialization)
                        · {{ $booking->doctor->specialization }}
                    @endif
                </p>
                <p class="muted tiny" style="margin: 2px 0 0;">
                    {{ $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') : '-' }}
                    {{ $booking->booking_time ? '· ' . $booking->booking_time : '' }}
                </p>
            </td>
        </tr>
    </table>

    <p class="label">Rincian tagihan</p>
    <table class="items">
        <thead>
            <tr>
                <th>Layanan</th>
                <th>Jadwal</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $booking->service->name ?? 'Layanan klinik' }}</strong>
                    @if($booking->service?->description)
                        <p class="muted tiny" style="margin: 4px 0 0;">{{ $booking->service->description }}</p>
                    @endif
                </td>
                <td class="muted">
                    {{ $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') : '-' }}<br>
                    {{ $booking->booking_time ?? '-' }}
                </td>
                <td class="right">Rp {{ number_format((float) $booking->total_amount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="muted">Total tagihan</td>
            <td class="right">Rp {{ number_format((float) $booking->total_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="muted">Sudah dibayar</td>
            <td class="right" style="color: #087a3a;">Rp {{ number_format((float) $booking->paid_amount, 0, ',', '.') }}</td>
        </tr>
        @if($booking->payment_type === 'dp' && (float) $booking->dp_amount > 0)
            <tr>
                <td class="muted">DP yang disyaratkan</td>
                <td class="right">Rp {{ number_format((float) $booking->dp_amount, 0, ',', '.') }}</td>
            </tr>
        @endif
        <tr class="total-row">
            <td>Sisa tagihan</td>
            <td class="right">Rp {{ number_format((float) $booking->remaining_amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="note">
        <strong>Metode pembayaran:</strong> {{ ucfirst($booking->payment_method ?? 'belum ditentukan') }}<br>
        @if($booking->payment_date)
            <strong>Tanggal pembayaran terakhir:</strong> {{ \Carbon\Carbon::parse($booking->payment_date)->format('d M Y H:i') }}<br>
        @endif
        Invoice ini dibuat otomatis dari data booking dan pembayaran yang tercatat di sistem.
    </div>

    <div class="footer">
        {{ $clinic->name ?? 'PetHeal Clinic' }} · {{ $invoiceNumber }} · Dicetak {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
