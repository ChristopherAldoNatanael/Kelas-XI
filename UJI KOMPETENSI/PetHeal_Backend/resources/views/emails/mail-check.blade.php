@php
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";
    $mono = "ui-monospace,SFMono-Regular,Menlo,Consolas,'Courier New',monospace";
    $muted = '#8A9AA3';
    $hair = 'height:1px;background-color:#EDF1EF;font-size:0;line-height:0;';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>Uji pengiriman email PetHeal</title>
</head>
<body style="margin:0;padding:0;background-color:#F4F6F5;">

<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#F4F6F5;">
    Konfirmasi bahwa jalur email PetHeal berfungsi. Tidak ada kode di email ini.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F6F5;">
    <tr>
        <td align="center" style="padding:36px 16px;">

            <table role="presentation" width="520" cellpadding="0" cellspacing="0" border="0" style="width:520px;max-width:100%;background-color:#FFFFFF;border:1px solid #E4E9E6;border-radius:12px;">

                <tr>
                    <td style="height:4px;background-color:#18C964;border-radius:12px 12px 0 0;font-size:0;line-height:0;">&nbsp;</td>
                </tr>

                <tr>
                    <td style="padding:30px 40px 0;">
                        <p style="margin:0;font-family:{{ $font }};font-size:18px;font-weight:700;letter-spacing:-0.01em;color:#087A3A;">PetHeal</p>
                        <p style="margin:4px 0 0;font-family:{{ $font }};font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:{{ $muted }};">Aplikasi kesehatan hewan</p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px 40px 0;"><div style="{{ $hair }}">&nbsp;</div></td>
                </tr>

                <tr>
                    <td style="padding:24px 40px 0;">
                        <h1 style="margin:0;font-family:{{ $font }};font-size:19px;line-height:1.35;font-weight:600;color:#0F172A;">Email uji berhasil terkirim</h1>
                        <p style="margin:12px 0 0;font-family:{{ $font }};font-size:15px;line-height:1.65;color:#42525E;">
                            Email ini dikirim dari server PetHeal untuk memastikan jalur pengiriman email
                            (SMTP, kredensial, dan alamat pengirim) bekerja. Anda tidak perlu melakukan apa pun.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 40px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F7F5;border:1px solid #E1E7E3;border-radius:10px;">
                            <tr>
                                <td style="padding:16px 18px;font-family:{{ $mono }};font-size:12px;line-height:1.9;color:#33424C;">
                                    Waktu kirim : {{ $sentAt }}<br>
                                    Mailer      : {{ $mailerName }}<br>
                                    Pengirim    : {{ $fromAddress }}<br>
                                    APP_URL     : {{ $appUrl }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 40px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td width="3" style="width:3px;background-color:#D9E2DD;border-radius:2px;font-size:0;line-height:0;">&nbsp;</td>
                                <td style="padding-left:14px;font-family:{{ $font }};font-size:13px;line-height:1.7;color:#5A6B76;">
                                    Kalau email ini masuk ke folder Spam/Promosi, tandai "Bukan spam" supaya
                                    email PetHeal berikutnya (termasuk kode verifikasi) langsung masuk kotak masuk.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px 40px 32px;">
                        <div style="{{ $hair }}">&nbsp;</div>
                        <p style="margin:16px 0 0;font-family:{{ $font }};font-size:12px;line-height:1.7;color:{{ $muted }};">
                            Email ini tidak berisi kode verifikasi dan tidak bisa dipakai untuk masuk ke akun mana pun.
                        </p>
                    </td>
                </tr>

            </table>

            <table role="presentation" width="520" cellpadding="0" cellspacing="0" border="0" style="width:520px;max-width:100%;">
                <tr>
                    <td align="center" style="padding:16px 8px 0;font-family:{{ $font }};font-size:11px;line-height:1.7;color:#9AA8AE;">
                        Email otomatis, balasan ke alamat ini tidak dipantau.<br>
                        &copy; {{ date('Y') }} PetHeal
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
</body>
</html>
