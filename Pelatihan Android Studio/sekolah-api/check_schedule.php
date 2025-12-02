<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

// Check raw data from DB
$rawSchedule = DB::table('schedules')->where('id', 167)->first();
echo "=== RAW DATABASE DATA ===" . PHP_EOL;
echo "jam_mulai (raw): " . var_export($rawSchedule->jam_mulai, true) . PHP_EOL;
echo "jam_selesai (raw): " . var_export($rawSchedule->jam_selesai, true) . PHP_EOL;

// Check via Eloquent
$schedule = Schedule::find(167);
echo PHP_EOL . "=== ELOQUENT MODEL DATA ===" . PHP_EOL;
echo "jam_mulai: " . $schedule->jam_mulai . PHP_EOL;
echo "jam_mulai type: " . gettype($schedule->jam_mulai) . PHP_EOL;
if ($schedule->jam_mulai instanceof \Carbon\Carbon) {
    echo "jam_mulai is Carbon: format = " . $schedule->jam_mulai->format('H:i:s') . PHP_EOL;
}

// Check model casts
echo PHP_EOL . "=== MODEL CASTS ===" . PHP_EOL;
print_r($schedule->getCasts());
