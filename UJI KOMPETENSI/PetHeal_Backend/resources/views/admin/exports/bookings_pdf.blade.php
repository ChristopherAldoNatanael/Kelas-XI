<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bookings Export</title>
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
    <h1>VCMS — Bookings Report</h1>
    <p class="subtitle">Generated: {{ now()->format('d M Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Pet</th>
                <th>Owner</th>
                <th>Doctor</th>
                <th>Date</th>
                <th>Time</th>
                <th>Service</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bookings as $i => $b)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $b->pet?->name ?? '-' }}</td>
                <td>{{ $b->pet?->user?->name ?? '-' }}</td>
                <td>{{ $b->doctor?->name ?? '-' }}</td>
                <td>{{ $b->booking_date }}</td>
                <td>{{ $b->booking_time }}</td>
                <td>{{ $b->service?->name ?? $b->service_type ?? '-' }}</td>
                <td>{{ ucfirst($b->status) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align:center; color:#999;">Tidak ada data booking untuk filter ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">VCMS — Veterinary Clinic Management System — {{ config('app.url') }}</div>
</body>
</html>
