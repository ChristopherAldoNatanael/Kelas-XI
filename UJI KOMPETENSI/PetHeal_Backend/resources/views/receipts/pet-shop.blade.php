<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Pembayaran {{ $receipt['order_number'] }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.5;
            margin: 0;
        }
        .header { width: 100%; border-bottom: 2px solid #0f766e; padding-bottom: 12px; }
        .header td { vertical-align: top; }
        .brand { font-size: 18px; font-weight: bold; color: #0f766e; letter-spacing: 0.4px; }
        .brand-sub { color: #64748b; font-size: 10px; }
        .doc-title { text-align: right; font-size: 14px; font-weight: bold; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.6px;
        }
        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .section { margin-top: 16px; }
        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { color: #64748b; width: 110px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th {
            background: #f1f5f9;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #475569;
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        table.items td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        table.items td.right, table.items th.right { text-align: right; }
        .totals { width: 46%; margin-left: 54%; margin-top: 8px; }
        .totals td { padding: 3px 0; }
        .totals td.right { text-align: right; }
        .totals .grand { font-weight: bold; font-size: 13px; color: #0f766e; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .muted { color: #64748b; }
        .box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
        }
        .footer { margin-top: 22px; border-top: 1px dashed #cbd5e1; padding-top: 10px; color: #64748b; font-size: 9px; }
        .notice {
            margin-top: 14px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="brand">PetHeal</div>
                <div class="brand-sub">Bukti pembayaran Pet Shop · Click &amp; Collect</div>
            </td>
            <td style="text-align: right;">
                <div class="doc-title">BUKTI PEMBAYARAN</div>
                <div class="muted">{{ $receipt['receipt_number'] }}</div>
                <div style="margin-top: 6px;">
                    <span class="badge {{ $receipt['is_settled'] ? 'badge-paid' : 'badge-pending' }}">
                        {{ $receipt['payment']['receipt_status_label'] }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <table style="width:100%; margin-top:16px;">
        <tr>
            <td style="width:50%; vertical-align:top;">
                <div class="section-title">Klinik</div>
                <div>{{ $receipt['clinic']['name'] }}</div>
                <div class="muted">{{ $receipt['clinic']['address'] }}</div>
                @if($receipt['clinic']['phone'])
                    <div class="muted">{{ $receipt['clinic']['phone'] }}</div>
                @endif
            </td>
            <td style="width:50%; vertical-align:top;">
                <div class="section-title">Transaksi</div>
                <table class="meta">
                    <tr><td class="label">No. pesanan</td><td>{{ $receipt['order_number'] }}</td></tr>
                    <tr><td class="label">Tanggal</td><td>{{ \Illuminate\Support\Carbon::parse($receipt['timeline']['created_at'])->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB</td></tr>
                    <tr><td class="label">Pelanggan</td><td>{{ $receipt['customer']['name'] }}</td></tr>
                    <tr>
                        <td class="label">Pembayaran</td>
                        <td>
                            {{ $receipt['payment']['method_label'] }}
                            @if($receipt['payment']['paid_at'])
                                <div class="muted">Lunas {{ \Illuminate\Support\Carbon::parse($receipt['payment']['paid_at'])->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Produk</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width:44%;">Produk</th>
                    <th style="width:20%;">Varian</th>
                    <th class="right" style="width:12%;">Qty</th>
                    <th class="right" style="width:12%;">Harga</th>
                    <th class="right" style="width:12%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receipt['items'] as $item)
                    <tr>
                        <td>
                            {{ $item['product_name'] }}
                            @if($item['sku'])<div class="muted">SKU {{ $item['sku'] }}</div>@endif
                        </td>
                        <td>{{ $item['variant_name'] ?: '-' }}</td>
                        <td class="right">{{ $item['quantity'] }}</td>
                        <td class="right">
                            {{ format_rupiah($item['unit_price']) }}
                            @if($item['discount_price'] !== null && $item['discount_price'] < $item['unit_price'])
                                <div class="muted">{{ format_rupiah($item['discount_price']) }}</div>
                            @endif
                        </td>
                        <td class="right">{{ format_rupiah($item['subtotal']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="muted">Subtotal</td>
                <td class="right">{{ format_rupiah($receipt['totals']['subtotal']) }}</td>
            </tr>
            @if($receipt['totals']['discount_total'] > 0)
                <tr>
                    <td class="muted">Diskon</td>
                    <td class="right">- {{ format_rupiah($receipt['totals']['discount_total']) }}</td>
                </tr>
            @endif
            <tr>
                <td class="grand">Total</td>
                <td class="grand right">{{ format_rupiah($receipt['totals']['total_amount']) }}</td>
            </tr>
            @if($receipt['totals']['refunded_amount'] > 0)
                <tr>
                    <td class="muted">Dikembalikan</td>
                    <td class="right">{{ format_rupiah($receipt['totals']['refunded_amount']) }}</td>
                </tr>
            @endif
            @if($receipt['is_settled'])
                <tr>
                    <td class="muted">Diterima</td>
                    <td class="right">{{ format_rupiah($receipt['totals']['paid_amount']) }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="section box">
        <div class="section-title">Pengambilan</div>
        <div>{{ $receipt['pickup']['clinic_name'] }}</div>
        <div class="muted">{{ $receipt['pickup']['clinic_address'] }}</div>
        <div style="margin-top:6px;">{{ $receipt['pickup']['note'] }} Tunjukkan QR Pickup Pass atau kode pengambilan kepada petugas klinik.</div>
    </div>

    @if(! $receipt['is_settled'])
        <div class="notice">
            Pembayaran belum diterima. Bukti ini BUKAN tanda lunas — status akan berubah setelah
            pembayaran dikonfirmasi {{ $receipt['payment']['method'] === 'pay_at_clinic' ? 'oleh petugas klinik' : 'oleh penyedia pembayaran' }}.
        </div>
    @endif

    <div class="footer">
        <div>Bukti pembayaran dibuat otomatis oleh PetHeal. Nomor bukti: {{ $receipt['receipt_number'] }}.</div>
        @if($receipt['payment']['payment_code'])
            <div>Kode pembayaran: {{ $receipt['payment']['payment_code'] }}@if($receipt['payment']['merchant_label']) · Merchant: {{ $receipt['payment']['merchant_label'] }}@endif</div>
        @endif
        <div>Hubungi klinik jika ada pertanyaan mengenai pesanan ini.</div>
    </div>
</body>
</html>
