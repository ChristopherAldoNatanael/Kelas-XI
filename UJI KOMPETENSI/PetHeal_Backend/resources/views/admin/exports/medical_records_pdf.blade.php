<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Medical Records Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitle { color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: 6px; vertical-align: top; }
        th { background: #ecfdf5; color: #047857; text-align: left; }
    </style>
</head>
<body>
    <h1>VCMS — Medical Records</h1>
    <p class="subtitle">Generated {{ now()->format('Y-m-d H:i') }}. Limited to 200 records.</p>
    <table>
        <thead>
            <tr>
                <th>Booking</th>
                <th>Owner</th>
                <th>Pet</th>
                <th>Doctor</th>
                <th>Date</th>
                <th>Diagnosis</th>
                <th>Treatment</th>
                <th>Medicine</th>
                <th>Consultation</th>
                <th>Treatment Cost</th>
                <th>Medicine Cost</th>
                <th>Total</th>
                <th>Payment</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
                <tr>
                    <td>#{{ $record->booking_id }}</td>
                    <td>{{ $record->booking?->user?->name ?? '-' }}</td>
                    <td>{{ $record->pet?->name ?? '-' }}</td>
                    <td>{{ $record->doctor?->name ?? '-' }}</td>
                    <td>{{ optional($record->created_at)->format('Y-m-d') }}</td>
                    <td>{{ $record->diagnosis }}</td>
                    <td>{{ $record->treatment }}</td>
                    <td>{{ $record->medicine }}</td>
                    <td>Rp {{ number_format($record->cost ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($record->treatment_cost ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($record->medicine_cost ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($record->total_medical_cost ?? 0, 0, ',', '.') }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $record->extra_payment_status ?? 'not_required')) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" style="text-align:center; color:#999;">Tidak ada data rekam medis untuk filter ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
