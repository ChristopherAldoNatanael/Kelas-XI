<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Vaccination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get personal dashboard data (stats, upcoming appointments, health summaries).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Pet statistics
        $totalPets = Pet::where('user_id', $user->id)->count();
        $activePets = Pet::where('user_id', $user->id)->whereHas('bookings', function ($query) {
            $query->whereIn('status', ['pending', 'confirmed']);
        })->count();
        
        // Booking statistics
        $upcomingBookings = Booking::with(['pet', 'doctor'])
            ->whereHas('pet', fn($q) => $q->where('user_id', $user->id))
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereDate('booking_date', '>=', now())
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->limit(5)
            ->get();
        
        $pendingBookings = Booking::whereHas('pet', fn($q) => $q->where('user_id', $user->id))->where('status', 'pending')->count();
        $confirmedBookings = Booking::whereHas('pet', fn($q) => $q->where('user_id', $user->id))->where('status', 'confirmed')->count();
        
        // Medical statistics
        $totalVisits = MedicalRecord::whereHas('pet', fn($q) => $q->where('user_id', $user->id))->count();
        $totalSpent = MedicalRecord::whereHas('pet', fn($q) => $q->where('user_id', $user->id))
            ->selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
            ->value('total') ?? 0;
        
        $recentVisits = MedicalRecord::whereHas('pet', fn($q) => $q->where('user_id', $user->id))
            ->with(['pet', 'doctor'])
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
        
        // Vaccination alerts
        $dueVaccinations = Vaccination::with(['pet'])
            ->whereHas('pet', fn($q) => $q->where('user_id', $user->id))
            ->upcomingDue(7)
            ->get();
        
        $overdueVaccinations = Vaccination::with(['pet'])
            ->whereHas('pet', fn($q) => $q->where('user_id', $user->id))
            ->overdue()
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'pets' => [
                    'total' => $totalPets,
                    'active' => $activePets,
                ],
                'bookings' => [
                    'upcoming' => $upcomingBookings,
                    'pending' => $pendingBookings,
                    'confirmed' => $confirmedBookings,
                ],
                'medical' => [
                    'total_visits' => $totalVisits,
                    'total_spent' => (float) $totalSpent,
                    'recent_visits' => $recentVisits,
                ],
                'vaccination_alerts' => [
                    'due_soon' => $dueVaccinations,
                    'overdue' => $overdueVaccinations,
                ],
                'summary' => $this->generateSummary($totalPets, $upcomingBookings->count(), $dueVaccinations->count() + $overdueVaccinations->count()),
            ]
        ]);
    }

    /**
     * Generate a quick summary message.
     */
    private function generateSummary(int $pets, int $upcomingBookings, int $vaccinationAlerts): string
    {
        $parts = [];
        
        if ($pets > 0) {
            $parts[] = "You have {$pets} pet" . ($pets > 1 ? 's' : '');
        }
        
        if ($upcomingBookings > 0) {
            $parts[] = "{$upcomingBookings} upcoming appointment" . ($upcomingBookings > 1 ? 's' : '');
        }
        
        if ($vaccinationAlerts > 0) {
            $parts[] = "{$vaccinationAlerts} vaccination alert" . ($vaccinationAlerts > 1 ? 's' : '');
        }
        
        return empty($parts) 
            ? 'Welcome to PetHeal!' 
            : implode(', ', $parts) . '.';
    }
}
