<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bookings Export - PetHeal</title>
    <style>
        body { font-family: 'Inter', Arial, sans-serif; font-size: 12px; color: #1e293b; padding: 40px; }
        h1 { font-size: 24px; margin-bottom: 4px; }
        .subtitle { color: #64748b; font-size: 13px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; text-align: left; padding: 10px 12px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 2px solid #e2e8f0; }
        td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
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

    <h1>Bookings Report</h1>
    <p class="subtitle">Generated on {{ now()->format('F d, Y H:i') }} &middot; {{ $bookings->count() }} total bookings</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Pet Name</th>
                <th>Owner</th>
                <th>Doctor</th>
                <th>Date</th>
                <th>Status</th>
                <th>Payment</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bookings as $index => $booking)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $booking->pet->name ?? '-' }}</td>
                    <td>{{ $booking->user->name ?? '-' }}</td>
                    <td>{{ $booking->doctor->name ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($booking->booking_date)->format('M d, Y') }} {{ $booking->booking_time }}</td>
                    <td><span class="status status-{{ $booking->status }}">{{ ucfirst($booking->status) }}</span></td>
                    <td>{{ $booking->payment_status ? ucfirst(str_replace('_', ' ', $booking->payment_status)) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:40px;">No bookings found</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">PetHeal &mdash; Veterinary Clinic Management System</div>
</body>
</html>