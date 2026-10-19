<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    {{-- Kunci mode terang: Gmail/Outlook dark mode jangan membalik warna sendiri. --}}
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>{{ $title }}</title>
</head>
@php
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";
    $mono = "ui-monospace,SFMono-Regular,Menlo,Consolas,'Courier New',monospace";
    $muted = '#8A9AA3';
    $hair = 'height:1px;background-color:#EDF1EF;font-size:0;line-height:0;';
@endphp
<body style="margin:0;padding:0;background-color:#F4F6F5;">

{{-- Teks pratinjau di daftar inbox (tidak tampil di dalam email). --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#F4F6F5;">
    Kode {{ $code }} &mdash; berlaku {{ $ttlMinutes }} menit.
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
                        <p style="margin:0;font-family:{{ $font }};font-size:16px;font-weight:700;letter-spacing:-0.01em;color:#087A3A;">PetHeal</p>
                        <p style="margin:3px 0 0;font-family:{{ $font }};font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:{{ $muted }};">Aplikasi kesehatan hewan</p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px 40px 0;"><div style="{{ $hair }}">&nbsp;</div></td>
                </tr>

                <tr>
                    <td style="padding:24px 40px 0;">
                        <h1 style="margin:0;font-family:{{ $font }};font-size:19px;line-height:1.35;font-weight:600;color:#0F172A;">{{ $title }}</h1>
                        <p style="margin:12px 0 0;font-family:{{ $font }};font-size:15px;line-height:1.65;color:#42525E;">{{ $intro }}</p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 40px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F2FBF5;border:1px solid #CFEBD9;border-radius:10px;">
                            <tr>
                                <td align="center" style="padding:18px 16px 6px;font-family:{{ $font }};font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#4B7A5F;">Kode verifikasi</td>
                            </tr>
                            <tr>
                                <td align="center" style="padding:0 16px 20px;font-family:{{ $mono }};font-size:34px;line-height:1.15;font-weight:700;letter-spacing:9px;text-indent:9px;color:#087A3A;">{{ $code }}</td>
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
                                    Kode berlaku <strong style="color:#33424C;">{{ $ttlMinutes }} menit</strong> dan hanya bisa dipakai sekali.
                                    Jangan bagikan kode ini kepada siapa pun, termasuk yang mengaku dari PetHeal.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px 40px 32px;">
                        <div style="{{ $hair }}">&nbsp;</div>
                        <p style="margin:16px 0 0;font-family:{{ $font }};font-size:12px;line-height:1.7;color:{{ $muted }};">
                            Anda menerima email ini karena ada permintaan {{ $reason }} di aplikasi PetHeal.
                            Kalau bukan Anda yang meminta, abaikan saja email ini &mdash; tidak ada yang perlu Anda lakukan.
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
