<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pengajuan Izin Baru</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4F46E5; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .info-row { margin-bottom: 10px; }
        .label { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Pengajuan Izin Baru</h1>
        </div>
        <div class="content">
            <p>Pengajuan izin baru telah disubmit:</p>

            <div class="info-row">
                <span class="label">Guru:</span> {{ $teacher->name }}
            </div>            <div class="info-row">
                <span class="label">Alasan Izin:</span> {{ $leave->reason }}{{ $leave->custom_reason ? ' - ' . $leave->custom_reason : '' }}
            </div>
            <div class="info-row">
                <span class="label">Tanggal Mulai:</span> {{ $leave->start_date }}
            </div>
            <div class="info-row">
                <span class="label">Tanggal Selesai:</span> {{ $leave->end_date }}
            </div>
            <div class="info-row">
                <span class="label">Alasan:</span> {{ $leave->reason }}
            </div>

            <p>Silakan login ke sistem untuk menyetujui atau menolak pengajuan ini.</p>
        </div>
    </div>
</body>
</html>
