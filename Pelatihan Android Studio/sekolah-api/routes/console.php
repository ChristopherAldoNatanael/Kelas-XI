<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CRITICAL FIX: Clean up old tokens to prevent database bloat and login timeouts
Artisan::command('tokens:cleanup', function () {
    $this->info('Cleaning up old authentication tokens...');

    // Delete tokens older than 30 days
    $deleted = DB::table('personal_access_tokens')
        ->where('created_at', '<', now()->subDays(30))
        ->delete();

    $this->info("Deleted {$deleted} old tokens.");

    // Also delete tokens that haven't been used in 7 days
    $deletedUnused = DB::table('personal_access_tokens')
        ->where('last_used_at', '<', now()->subDays(7))
        ->whereNotNull('last_used_at')
        ->delete();

    $this->info("Deleted {$deletedUnused} unused tokens.");

    $this->info('Token cleanup completed successfully!');
})->purpose('Clean up old authentication tokens');

// Schedule the token cleanup to run daily at 2 AM
Schedule::command('tokens:cleanup')->dailyAt('02:00');
