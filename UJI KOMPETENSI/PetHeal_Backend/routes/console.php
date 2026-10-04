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

// PHASE 3 (J-05): prune expired password-reset tokens (hashed, 15-min TTL).
// Rows were only deleted on verify/reset attempts, so abandoned requests
// littered the table forever.
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('password_reset_tokens')
        ->where('created_at', '<', now()->subHour())
        ->delete();
})->daily()->name('prune-password-reset-tokens');
