<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Izin Disetujui</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10B981; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .info-row { margin-bottom: 10px; }
        .label { font-weight: bold; }
        .success { color: #10B981; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Izin Disetujui</h1>
        </div>
        <div class="content">
            <p>Halo <strong>{{ $teacher->name }}</strong>,</p>

            <p class="success">Pengajuan izin Anda telah DISETUJUI.</p>
              <div class="info-row">
                <span class="label">Alasan Izin:</span> {{ $leave->reason }}{{ $leave->custom_reason ? ' - ' . $leave->custom_reason : '' }}
            </div>
            <div class="info-row">
                <span class="label">Tanggal Mulai:</span> {{ $leave->start_date }}
            </div>
            <div class="info-row">
                <span class="label">Tanggal Selesai:</span> {{ $leave->end_date }}
            </div>
            @if($substituteTeacher)
            <div class="info-row">
                <span class="label">Guru Pengganti:</span> {{ $substituteTeacher->name }}
            </div>
            @endif

            <p>Terima kasih.</p>
        </div>
    </div>
</body>
</html>
