<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Izin Ditolak</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #EF4444; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .info-row { margin-bottom: 10px; }
        .label { font-weight: bold; }
        .rejected { color: #EF4444; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Izin Ditolak</h1>
        </div>
        <div class="content">
            <p>Halo <strong>{{ $teacher->name }}</strong>,</p>

            <p class="rejected">Mohon maaf, pengajuan izin Anda telah DITOLAK.</p>
              <div class="info-row">
                <span class="label">Alasan Izin:</span> {{ $leave->reason }}{{ $leave->custom_reason ? ' - ' . $leave->custom_reason : '' }}
            </div>
            <div class="info-row">
                <span class="label">Tanggal Mulai:</span> {{ $leave->start_date }}
            </div>
            <div class="info-row">
                <span class="label">Tanggal Selesai:</span> {{ $leave->end_date }}
            </div>
            @if($rejectionReason)
            <div class="info-row">
                <span class="label">Alasan Penolakan:</span> {{ $rejectionReason }}
            </div>
            @endif

            <p>Silakan hubungi bagian administrasi untuk informasi lebih lanjut.</p>
        </div>
    </div>
</body>
</html>
