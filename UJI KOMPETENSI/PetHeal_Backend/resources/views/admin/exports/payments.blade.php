<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payments Export - PetHeal</title>
    <style>
        body { font-family: 'Inter', Arial, sans-serif; font-size: 12px; color: #1e293b; padding: 40px; }
        h1 { font-size: 24px; margin-bottom: 4px; }
        .subtitle { color: #64748b; font-size: 13px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; text-align: left; padding: 10px 12px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 2px solid #e2e8f0; }
        td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 600; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-dp_paid { background: #dbeafe; color: #1e40af; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-partial { background: #ffedd5; color: #9a3412; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .status-unpaid { background: #f1f5f9; color: #475569; }
        .footer { margin-top: 24px; font-size: 10px; color: #94a3b8; text-align: center; }
        @@media print {
            body { padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:20px;">
        <button onclick="window.print()" style="padding:8px 20px;background:#10b981;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px;">Print / Save PDF</button>
    </div>

    <h1>Payments Report</h1>
    <p class="subtitle">Generated on {{ now()->format('F d, Y H:i') }} &middot; {{ $bookings->count() }} total payments</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Customer</th>
                <th>Pet</th>
                <th>Amount Paid</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bookings as $index => $booking)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $booking->user->name ?? '-' }}</td>
                    <td>{{ $booking->pet->name ?? '-' }}</td>
                    <td>Rp {{ number_format($booking->paid_amount, 0, ',', '.') }}</td>
                    <td><span class="status status-{{ $booking->payment_status }}">{{ ucfirst(str_replace('_', ' ', $booking->payment_status)) }}</span></td>
                    <td>{{ $booking->updated_at ? $booking->updated_at->format('M d, Y') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:40px;">No payment records found</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">PetHeal &mdash; Veterinary Clinic Management System</div>
</body>
</html>