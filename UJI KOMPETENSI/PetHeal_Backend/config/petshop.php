<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Batas operasional Pet Shop
    |--------------------------------------------------------------------------
    | Semua nilai di sini adalah kebijakan bisnis yang dapat diubah tanpa
    | menyentuh logika. Nominal uang selalu integer rupiah.
    */
    'max_quantity_per_item' => (int) env('PETSHOP_MAX_QTY_PER_ITEM', 20),
    'max_items_per_order' => (int) env('PETSHOP_MAX_ITEMS_PER_ORDER', 20),

    // Reservasi stok untuk pembayaran online: berapa menit stok ditahan
    // sebelum pesanan kedaluwarsa dan reservasi dilepas.
    'online_reservation_minutes' => (int) env('PETSHOP_ONLINE_HOLD_MINUTES', 30),

    // Pesanan "bayar di klinik" tetap menahan stok sampai klinik memproses.
    // Batas ini mencegah stok tertahan selamanya bila pasien tidak datang.
    'pay_at_clinic_hold_hours' => (int) env('PETSHOP_PICKUP_HOLD_HOURS', 24),

    'image' => [
        'disk' => env('PETSHOP_IMAGE_DISK', 'public'),
        'directory' => 'pet-shop/products',
        'max_kb' => (int) env('PETSHOP_IMAGE_MAX_KB', 4096),
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    // Batas aman jumlah baris yang diproses scheduler rekonsiliasi per jalan.
    'reconciliation_batch' => (int) env('PETSHOP_RECON_BATCH', 50),
];
