<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Leave Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007BFF;
            padding-bottom: 20px;
        }

        .header h1 {
            color: #007BFF;
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }

        .header p {
            margin: 5px 0;
            color: #666;
        }

        .stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007BFF;
            display: block;
        }

        .stat-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px 4px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #007BFF;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
        }

        .reason-cell {
            max-width: 120px;
            word-wrap: break-word;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        .filters-info {
            background-color: #e9ecef;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 3px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Teacher Leave Report</h1>
        <p>Generated on: {{ now()->format('d F Y H:i:s') }}</p>
        <p>Period: {{ $request->date_from ? \Carbon\Carbon::parse($request->date_from)->format('d/m/Y') : 'All' }} - {{ $request->date_to ? \Carbon\Carbon::parse($request->date_to)->format('d/m/Y') : 'All' }}</p>
    </div>

    @if($request->filled('status') || $request->filled('teacher_id'))
    <div class="filters-info">
        <strong>Applied Filters:</strong>
        @if($request->filled('status'))
            Status: {{ ucfirst($request->status) }} |
        @endif
        @if($request->filled('teacher_id'))
            Teacher ID: {{ $request->teacher_id }}
        @endif
    </div>
    @endif

    <div class="stats">
        <div class="stat-item">
            <span class="stat-number">{{ $stats['total'] }}</span>
            <span class="stat-label">Total Leaves</span>
        </div>
        <div class="stat-item">
            <span class="stat-number">{{ $stats['pending'] }}</span>
            <span class="stat-label">Pending</span>
        </div>
        <div class="stat-item">
            <span class="stat-number">{{ $stats['approved'] }}</span>
            <span class="stat-label">Approved</span>
        </div>
        <div class="stat-item">
            <span class="stat-number">{{ $stats['rejected'] }}</span>
            <span class="stat-label">Rejected</span>
        </div>
    </div>

    @if($leaves->count() > 0)
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 15%;">Teacher</th>
                <th style="width: 12%;">Reason</th>
                <th style="width: 10%;">Start Date</th>
                <th style="width: 10%;">End Date</th>
                <th style="width: 8%;">Days</th>
                <th style="width: 15%;">Substitute</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 15%;">Approved By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($leaves as $index => $leave)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>{{ $leave->teacher_nama ?? 'N/A' }}</td>
                <td class="reason-cell">
                    @if($leave->reason === 'lainnya')
                        {{ $leave->custom_reason ?? 'Other' }}
                    @else
                        {{ ucwords(str_replace('_', ' ', $leave->reason)) }}
                    @endif
                </td>
                <td>{{ \Carbon\Carbon::parse($leave->start_date)->format('d/m/Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($leave->end_date)->format('d/m/Y') }}</td>
                <td style="text-align: center;">
                    {{ \Carbon\Carbon::parse($leave->start_date)->diffInDays(\Carbon\Carbon::parse($leave->end_date)) + 1 }}
                </td>
                <td>{{ $leave->substitute_teacher_nama ?? '-' }}</td>
                <td style="text-align: center;">
                    <span class="status-{{ $leave->status }}">
                        {{ ucfirst($leave->status) }}
                    </span>
                </td>
                <td>{{ $leave->approved_by_name ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-data">
        <p>No leave records found for the selected criteria.</p>
    </div>
    @endif

    <div class="footer">
        <p>This report was generated automatically by the School Management System</p>
        <p>Total Records: {{ $leaves->count() }}</p>
    </div>
</body>
</html>
