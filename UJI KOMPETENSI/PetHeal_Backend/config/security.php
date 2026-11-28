<?php

return [
    /*
    | Panel masih memiliki script inline (Blade) dan memuat font/peta/frame
    | pembayaran eksternal. Karena itu CSP dikirim sebagai report-only sampai
    | inline script dipindahkan ke bundle. Set false untuk enforcement.
    */
    'csp_report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', true),

    'csp' => env(
        'SECURITY_CSP',
        "default-src 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'; "
        . "frame-ancestors 'self'; "
        . "form-action 'self'; "
        . "script-src 'self' 'unsafe-inline' https://app.midtrans.com https://app.sandbox.midtrans.com; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' data: https://fonts.gstatic.com; "
        . "img-src 'self' data: blob: https:; "
        . "connect-src 'self' https:; "
        . "frame-src 'self' https://app.midtrans.com https://app.sandbox.midtrans.com;"
    ),
];
