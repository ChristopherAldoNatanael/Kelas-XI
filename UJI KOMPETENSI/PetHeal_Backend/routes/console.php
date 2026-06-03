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
