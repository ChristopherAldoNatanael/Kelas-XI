@php
    // Sama seperti versi HTML: label bisa ditimpa untuk email staf non-admin.
    $consoleLabel = $consoleLabel ?? 'Admin Console';
    $accessLabel = $accessLabel ?? 'Kode akses';
    $recipientRoleLabel = $recipientRoleLabel ?? 'admin';
    $footerLabel = $footerLabel ?? 'Dashboard Admin Klinik';
    $securityNote = $securityNote ?? 'Tim PetHeal tidak pernah meminta kode ini melalui telepon, WhatsApp, atau chat. Kode untuk dashboard admin tidak berlaku di aplikasi pet owner.';
@endphp
PetHeal - {{ $consoleLabel }}
{{ $clinicName }}
{{ $eyebrow }}

{{ $title }}

{{ $greeting }}
{{ $intro }}

{{ strtoupper($accessLabel) }}: {{ $code }}

Berlaku {{ $ttlMinutes }} menit dan hanya bisa dipakai sekali.

{{ $outcome }}

{{ $securityNote }}

Email ini dikirim ke alamat {{ $recipientRoleLabel }} yang terdaftar untuk {{ $clinicName }}.
Jika Anda tidak merasa memulai {{ $reason }}, abaikan email ini - tidak ada
perubahan pada akun Anda.

--
{{ $footerLabel }} - PetHeal
Email otomatis, balasan ke alamat ini tidak dipantau.
