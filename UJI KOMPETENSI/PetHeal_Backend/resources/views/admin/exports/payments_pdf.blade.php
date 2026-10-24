<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('admin.export_payments_title') }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #10B981; color: white; font-size: 9px; text-transform: uppercase; }
        h1 { font-size: 18px; color: #065F46; margin-bottom: 5px; }
        .subtitle { color: #666; font-size: 11px; margin-bottom: 15px; }
        .footer { margin-top: 20px; font-size: 8px; color: #999; text-align: center; }
    </style>
</head>
<body>
    @php
        $locale = app()->getLocale();
        $money = static fn ($amount) => number_format(
            (float) $amount,
            0,
            $locale === 'id' ? ',' : '.',
            $locale === 'id' ? '.' : ','
        );
    @endphp
    <h1>{{ __('admin.export_payments_title') }}</h1>
    <p class="subtitle">{{ __('admin.export_generated') }}: {{ now()->locale($locale)->translatedFormat('d M Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('admin.export_pet') }}</th>
                <th>{{ __('admin.export_owner') }}</th>
                <th>{{ __('admin.export_doctor') }}</th>
                <th>{{ __('admin.export_date') }}</th>
                <th>{{ __('admin.export_service') }}</th>
                <th>{{ __('admin.export_total') }}</th>
                <th>{{ __('admin.export_paid') }}</th>
                <th>{{ __('admin.export_status') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bookings as $i => $b)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $b->pet?->name ?? '-' }}</td>
                <td>{{ $b->pet?->user?->name ?? '-' }}</td>
                <td>{{ $b->doctor?->name ?? '-' }}</td>
                <td>{{ $b->booking_date ? \Carbon\Carbon::parse($b->booking_date)->locale($locale)->translatedFormat('d M Y') : '-' }}</td>
                <td>{{ $b->service?->name ?? $b->service_type ?? '-' }}</td>
                <td>{{ $money($b->total_amount ?? 0) }}</td>
                <td>{{ $money($b->paid_amount ?? 0) }}</td>
                <td>{{ \App\Support\AdminStatus::paymentLabel($b->payment_status) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="text-align:center; color:#999;">{{ __('admin.export_no_payments') }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">{{ __('admin.export_footer') }} — {{ config('app.url') }}</div>
</body>
</html>
