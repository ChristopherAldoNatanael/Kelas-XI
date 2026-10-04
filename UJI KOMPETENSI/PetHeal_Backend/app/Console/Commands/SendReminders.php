<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Vaccination;
use App\Services\FCMService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reminders:send';

    /**
     * The console command description.
     */
    protected $description = 'Send FCM reminders for upcoming bookings and vaccinations';

    protected FCMService $fcmService;

    public function __construct(FCMService $fcmService)
    {
        parent::__construct();
        $this->fcmService = $fcmService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting reminder job...');
        
        $bookingRemindersSent = $this->sendBookingReminders();
        $vaccinationRemindersSent = $this->sendVaccinationReminders();
        
        $this->info("Reminders sent: {$bookingRemindersSent} bookings, {$vaccinationRemindersSent} vaccinations");
        
        return Command::SUCCESS;
    }

    /**
     * Send reminders for tomorrow's bookings.
     */
    protected function sendBookingReminders(): int
    {
        $tomorrow = Carbon::tomorrow();
        $today = Carbon::today();
        
        $bookings = Booking::with(['user', 'pet', 'doctor'])
            ->whereBetween('booking_date', [$today, $tomorrow])
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();
        
        $sent = 0;
        foreach ($bookings as $booking) {
            // PHASE 3 (J-02): per-item isolation — one bad row must not abort
            // the rest, and only delivered reminders count as sent.
            try {
                $this->info("Sending booking reminder for {$booking->pet->name} on {$booking->booking_date}");

                $delivered = $this->fcmService->sendBookingReminder(
                    $booking->user_id,
                    $booking->pet->name,
                    $booking->booking_date,
                    $booking->booking_time,
                    $booking->id,
                    $booking->pet_id
                );

                if ($delivered) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                $this->error("Booking reminder failed for #{$booking->id}: {$e->getMessage()}");
            }
        }

        return $sent;
    }

    /**
     * Send reminders for upcoming/overdue vaccinations.
     */
    protected function sendVaccinationReminders(): int
    {
        // Upcoming due vaccinations (within next 7 days)
        $upcomingVaccinations = Vaccination::with(['pet'])
            ->upcomingDue(7)
            ->get();
        
        // Overdue vaccinations
        $overdueVaccinations = Vaccination::with(['pet'])
            ->overdue()
            ->get();
        
        $sent = 0;
        
        foreach ($upcomingVaccinations as $vaccination) {
            // PHASE 3 (J-02): see booking loop above.
            try {
                $this->info("Sending vaccination reminder: {$vaccination->vaccine_name} for {$vaccination->pet->name}");

                $daysUntilDue = now()->diffInDays($vaccination->next_due_date, false);

                $delivered = $this->fcmService->sendVaccinationReminder(
                    $vaccination->pet->user_id,
                    $vaccination->pet->name,
                    $vaccination->next_due_date->format('Y-m-d'),
                    $vaccination->pet_id
                );

                if ($delivered) {
                    $vaccination->update(['reminder_sent' => true]);
                    $sent++;
                }
            } catch (\Throwable $e) {
                $this->error("Vaccination reminder failed for #{$vaccination->id}: {$e->getMessage()}");
            }
        }

        foreach ($overdueVaccinations as $vaccination) {
            // PHASE 3 (J-02/C-04): isolate failures like above, and mark
            // overdue rows sent — both scopes filter reminder_sent=false, so
            // without this the same overdue pets were spammed every day.
            try {
                $this->warn("Sending OVERDUE vaccination reminder: {$vaccination->vaccine_name} for {$vaccination->pet->name}");

                $daysOverdue = now()->diffInDays($vaccination->next_due_date);

                $delivered = $this->fcmService->sendVaccinationReminder(
                    $vaccination->pet->user_id,
                    $vaccination->pet->name,
                    $vaccination->next_due_date->format('Y-m-d'),
                    $vaccination->pet_id
                );

                if ($delivered) {
                    $vaccination->update(['reminder_sent' => true]);
                    $sent++;
                }
            } catch (\Throwable $e) {
                $this->error("Overdue reminder failed for #{$vaccination->id}: {$e->getMessage()}");
            }
        }

        return $sent;
    }
}
