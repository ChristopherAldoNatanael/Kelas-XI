<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Attendance Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 11px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status-hadir {
            color: #28a745;
            font-weight: bold;
        }
        .status-tidak_hadir {
            color: #dc3545;
            font-weight: bold;
        }
        .status-telat {
            color: #ffc107;
            font-weight: bold;
        }
        .status-diganti {
            color: #17a2b8;
            font-weight: bold;
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
            padding: 50px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Teacher Attendance Report</h1>
        <p>Generated on: {{ now()->format('d F Y H:i:s') }}</p>
        @if($request->filled('date_from') || $request->filled('date_to'))
            <p>
                Period:
                @if($request->filled('date_from'))
                    From: {{ \Carbon\Carbon::parse($request->date_from)->format('d F Y') }}
                @endif
                @if($request->filled('date_to'))
                    @if($request->filled('date_from')) - @endif
                    To: {{ \Carbon\Carbon::parse($request->date_to)->format('d F Y') }}
                @endif
            </p>
        @endif
    </div>

    <div class="stats">
        <div class="stat-item">
            <div class="stat-number">{{ $stats['total'] ?? 0 }}</div>
            <div class="stat-label">Total Records</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">{{ $stats['present'] ?? 0 }}</div>
            <div class="stat-label">Present</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">{{ $stats['absent'] ?? 0 }}</div>
            <div class="stat-label">Absent</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">{{ $stats['late'] ?? 0 }}</div>
            <div class="stat-label">Late</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">{{ $stats['on_leave'] ?? 0 }}</div>
            <div class="stat-label">On Leave</div>
        </div>
    </div>

    @if($attendances->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Teacher</th>
                    <th>Subject</th>
                    <th>Class</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($attendances as $index => $attendance)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($attendance->tanggal)->format('d/m/Y') }}</td>
                        <td>{{ $attendance->jam_masuk ? \Carbon\Carbon::parse($attendance->jam_masuk)->format('H:i') : '-' }}</td>
                        <td>
                            {{ $attendance->guru->name ?? 'N/A' }}
                            @if($attendance->guru_asli && $attendance->guru_asli->id !== $attendance->guru->id)
                                <br><small>(Original: {{ $attendance->guru_asli->name ?? 'N/A' }})</small>
                            @endif
                        </td>
                        <td>{{ $attendance->schedule->subject->nama_mapel ?? $attendance->schedule->mata_pelajaran ?? 'N/A' }}</td>
                        <td>{{ $attendance->schedule->class_model->nama_kelas ?? $attendance->schedule->kelas ?? 'N/A' }}</td>
                        <td>
                            <span class="status-{{ $attendance->status }}">
                                {{ ucfirst(str_replace('_', ' ', $attendance->status)) }}
                            </span>
                        </td>
                        <td>{{ $attendance->keterangan ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="no-data">
            <p>No attendance records found for the selected criteria.</p>
        </div>
    @endif

    <div class="footer">
        <p>This report was generated automatically by the School Management System</p>
        <p>Total Records: {{ $attendances->count() }}</p>
    </div>
</body>
</html>
