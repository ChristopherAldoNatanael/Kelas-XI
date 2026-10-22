<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>{{ $title }}</title>
</head>
@php
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";
    $mono = "ui-monospace,SFMono-Regular,Menlo,Consolas,'Courier New',monospace";
    $ink = '#101B16';
    $body = '#48574F';
    $muted = '#7C8B84';
    $hair = 'height:1px;background-color:#E7EBE9;font-size:0;line-height:0;';
@endphp
<body style="margin:0;padding:0;background-color:#EEF1F3;">

<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#EEF1F3;">
    Kode akses admin {{ $clinicName }} &mdash; {{ $code }} &mdash; berlaku {{ $ttlMinutes }} menit.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EEF1F3;">
    <tr>
        <td align="center" style="padding:36px 16px;">

            <table role="presentation" width="540" cellpadding="0" cellspacing="0" border="0" style="width:540px;max-width:100%;background-color:#FFFFFF;border:1px solid #DDE3E0;border-radius:10px;overflow:hidden;">

                {{-- Console header --}}
                <tr>
                    <td style="background-color:#101B16;padding:20px 36px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-family:{{ $font }};font-size:15px;font-weight:700;color:#FFFFFF;letter-spacing:0.02em;">
                                    PetHeal
                                    <span style="display:inline-block;margin-left:8px;padding:3px 7px;border:1px solid #355245;border-radius:4px;font-size:9px;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:#8FCBA9;vertical-align:middle;">Admin Console</span>
                                </td>
                                <td align="right" style="font-family:{{ $font }};font-size:11px;color:#93A89C;">
                                    {{ $clinicName }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Purpose --}}
                <tr>
                    <td style="padding:30px 36px 0;">
                        <p style="margin:0 0 10px;font-family:{{ $font }};font-size:10px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#3F7A5B;">{{ $eyebrow }}</p>
                        <h1 style="margin:0;font-family:{{ $font }};font-size:22px;line-height:1.3;font-weight:650;color:{{ $ink }};">{{ $title }}</h1>
                        <p style="margin:14px 0 0;font-family:{{ $font }};font-size:14px;line-height:1.7;color:{{ $body }};">
                            {{ $greeting }}<br>{{ $intro }}
                        </p>
                    </td>
                </tr>

                {{-- Code --}}
                <tr>
                    <td style="padding:24px 36px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #DDE3E0;border-top:3px solid #14794B;border-radius:6px;background-color:#FAFBFB;">
                            <tr>
                                <td align="center" style="padding:16px 16px 4px;font-family:{{ $font }};font-size:9px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:{{ $muted }};">Kode akses</td>
                            </tr>
                            <tr>
                                <td align="center" style="padding:0 16px 14px;font-family:{{ $mono }};font-size:32px;line-height:1.2;font-weight:700;letter-spacing:10px;text-indent:10px;color:{{ $ink }};">{{ $code }}</td>
                            </tr>
                            <tr>
                                <td align="center" style="padding:0 16px 16px;font-family:{{ $font }};font-size:11px;color:{{ $muted }};">
                                    Berlaku {{ $ttlMinutes }} menit &nbsp;·&nbsp; hanya untuk satu kali pakai
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Consequence --}}
                <tr>
                    <td style="padding:18px 36px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F6F8F7;border-radius:6px;">
                            <tr>
                                <td style="padding:12px 14px;font-family:{{ $font }};font-size:12px;line-height:1.65;color:#4A5A52;">
                                    {{ $outcome }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Security note --}}
                <tr>
                    <td style="padding:18px 36px 0;">
                        <p style="margin:0;font-family:{{ $font }};font-size:12px;line-height:1.7;color:{{ $muted }};">
                            Tim PetHeal tidak pernah meminta kode ini melalui telepon, WhatsApp, atau chat.
                            Kode untuk dashboard admin tidak berlaku di aplikasi pet owner.
                        </p>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:24px 36px 30px;">
                        <div style="{{ $hair }}">&nbsp;</div>
                        <p style="margin:14px 0 0;font-family:{{ $font }};font-size:11px;line-height:1.7;color:{{ $muted }};">
                            Email ini dikirim ke alamat admin yang terdaftar untuk {{ $clinicName }}.
                            Jika Anda tidak merasa memulai {{ $reason }}, abaikan email ini &mdash;
                            tidak ada perubahan pada akun Anda.
                        </p>
                    </td>
                </tr>
            </table>

            <table role="presentation" width="540" cellpadding="0" cellspacing="0" border="0" style="width:540px;max-width:100%;">
                <tr>
                    <td align="center" style="padding:16px 8px 0;font-family:{{ $font }};font-size:10px;line-height:1.7;color:#93A29B;">
                        Dashboard Admin Klinik &middot; PetHeal<br>
                        Email otomatis, balasan ke alamat ini tidak dipantau.
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
</body>
</html>
