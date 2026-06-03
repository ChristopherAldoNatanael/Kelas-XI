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
            $this->info("Sending booking reminder for {$booking->pet->name} on {$booking->booking_date}");
            
            $this->fcmService->sendBookingReminder(
                $booking->user_id,
                $booking->pet->name,
                $booking->booking_date,
                $booking->booking_time
            );
            
            $sent++;
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
            $this->info("Sending vaccination reminder: {$vaccination->vaccine_name} for {$vaccination->pet->name}");
            
            $daysUntilDue = now()->diffInDays($vaccination->next_due_date, false);
            
            $this->fcmService->sendVaccinationReminder(
                $vaccination->pet->user_id,
                $vaccination->pet->name,
                $vaccination->next_due_date->format('Y-m-d')
            );
            
            $vaccination->update(['reminder_sent' => true]);
            $sent++;
        }
        
        foreach ($overdueVaccinations as $vaccination) {
            $this->warn("Sending OVERDUE vaccination reminder: {$vaccination->vaccine_name} for {$vaccination->pet->name}");
            
            $daysOverdue = now()->diffInDays($vaccination->next_due_date);
            
            $this->fcmService->sendVaccinationReminder(
                $vaccination->pet->user_id,
                $vaccination->pet->name,
                $vaccination->next_due_date->format('Y-m-d')
            );
            
            $sent++;
        }
        
        return $sent;
    }
}
