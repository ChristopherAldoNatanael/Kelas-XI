@php
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";
    $ink = '#101B16';
    $body = '#48574F';
    $muted = '#7C8B84';
    $hair = 'height:1px;background-color:#E7EBE9;font-size:0;line-height:0;';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>Pendaftaran klinik belum disetujui</title>
</head>
<body style="margin:0;padding:0;background-color:#EEF1F3;">

<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#EEF1F3;">
    Pendaftaran klinik {{ $clinicName }} belum disetujui.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EEF1F3;">
    <tr>
        <td align="center" style="padding:36px 16px;">

            <table role="presentation" width="540" cellpadding="0" cellspacing="0" border="0" style="width:540px;max-width:100%;background-color:#FFFFFF;border:1px solid #DDE3E0;border-radius:10px;overflow:hidden;">

                <tr>
                    <td style="background-color:#101B16;padding:20px 36px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-family:{{ $font }};font-size:15px;font-weight:700;color:#FFFFFF;letter-spacing:0.02em;">
                                    PetHeal
                                    <span style="display:inline-block;margin-left:8px;padding:3px 7px;border:1px solid #355245;border-radius:4px;font-size:9px;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:#8FCBA9;vertical-align:middle;">Pendaftaran Klinik</span>
                                </td>
                                <td align="right" style="font-family:{{ $font }};font-size:11px;color:#93A89C;">
                                    {{ $clinicName }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:30px 36px 0;">
                        <p style="margin:0 0 10px;font-family:{{ $font }};font-size:10px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#9A3412;">Tinjauan super admin</p>
                        <h1 style="margin:0;font-family:{{ $font }};font-size:22px;line-height:1.3;font-weight:650;color:{{ $ink }};">Pendaftaran klinik belum disetujui</h1>
                        <p style="margin:14px 0 0;font-family:{{ $font }};font-size:14px;line-height:1.7;color:{{ $body }};">
                            Halo {{ $ownerName }},<br>
                            Setelah ditinjau, permintaan pendaftaran klinik <strong>{{ $clinicName }}</strong> belum dapat disetujui. Tidak ada klinik atau akun yang dibuat dari permintaan ini.
                        </p>
                    </td>
                </tr>

                @if(filled($reason))
                    <tr>
                        <td style="padding:18px 36px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FDF6F4;border:1px solid #F3D9D2;border-radius:6px;">
                                <tr>
                                    <td style="padding:12px 14px;font-family:{{ $font }};font-size:12px;line-height:1.65;color:#7A3A2A;">
                                        <strong>Catatan dari super admin:</strong><br>
                                        {{ $reason }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="padding:18px 36px 0;">
                        <p style="margin:0;font-family:{{ $font }};font-size:13px;line-height:1.7;color:{{ $body }};">
                            Anda tetap bisa mendaftar kembali dengan data yang sudah diperbaiki melalui halaman pendaftaran klinik. Jika menurut Anda ini keliru, hubungi super admin PetHeal.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 36px 30px;">
                        <div style="{{ $hair }}">&nbsp;</div>
                        <p style="margin:14px 0 0;font-family:{{ $font }};font-size:11px;line-height:1.7;color:{{ $muted }};">
                            Dashboard Klinik &middot; PetHeal<br>
                            Email otomatis, balasan ke alamat ini tidak dipantau.
                        </p>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
</body>
</html>
