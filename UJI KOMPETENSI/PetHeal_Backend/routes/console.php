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
