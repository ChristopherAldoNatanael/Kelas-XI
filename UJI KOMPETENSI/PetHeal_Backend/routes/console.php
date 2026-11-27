<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send reminders daily at 8 AM for upcoming bookings and vaccinations
Schedule::command('reminders:send')
    ->dailyAt('08:00')
    ->name('send-pet-reminders')
    ->withoutOverlapping();

// PHASE 3 (J-05) + OTP (2026-10-19): prune kode OTP yang sudah kedaluwarsa.
// Reset sandi tidak lagi memakai `password_reset_tokens` melainkan `otp_codes`,
// jadi jadwal ini memangkas `otp_codes`. OtpService juga memangkas saat
// menerbitkan kode baru — jadwal harian ini hanya jaring pengaman.
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('otp_codes')
        ->where('expires_at', '<', now()->subDay())
        ->delete();
})->daily()->name('prune-otp-codes');

// Pet Shop: lepas reservasi stok yang kedaluwarsa setiap 5 menit.
Schedule::command('petshop:expire-orders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('expire-pet-shop-orders');

// Payment: rekonsiliasi status pending secara berkala (bukan di request user).
Schedule::command('payments:reconcile --limit=50')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('reconcile-payments');

// Rekam medis: hitung ulang retention_until sebelum proses arsip.
Schedule::command('medical-records:refresh-retention')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->name('refresh-medical-record-retention');

// Rekam medis: pindahkan ke status arsip setelah masa retensi habis.
// Tidak menghapus data; legal hold selalu menahan arsip.
Schedule::command('medical-records:archive')
    ->monthlyOn(1, '02:30')
    ->withoutOverlapping()
    ->name('archive-medical-records');

// P4: pemeriksaan operasional read-only. Keluaran JSON dapat dipakai alert.
Schedule::command('medical-records:health-check')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->name('medical-records-health-check');

// P4: enkripsi lampiran lama yang belum memiliki encryption_version.
Schedule::command('medical-records:encrypt-attachments')
    ->weeklyOn(1, '03:15')
    ->withoutOverlapping()
    ->name('encrypt-medical-record-attachments');

// P3.1: tandai laporan penyakit menular yang melewati tenggat 24 jam.
// Ini hanya penanda eskalasi; pelaporan resmi tetap melalui kanal berwenang.
Schedule::command('reports:escalate-overdue')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('escalate-overdue-disease-reports');
