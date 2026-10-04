<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Vaccination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Get personal dashboard data (stats, upcoming appointments, health summaries).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "dashboard_user_{$user->id}";

        $data = Cache::remember($cacheKey, 60, function () use ($user) {
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
            
            $userBookingsQuery = Booking::whereHas('pet', fn($q) => $q->where('user_id', $user->id));
            $pendingBookings = (clone $userBookingsQuery)->where('status', 'pending')->count();
            $confirmedBookings = (clone $userBookingsQuery)->where('status', 'confirmed')->count();
            $paymentAttentionCount = (clone $userBookingsQuery)
                ->where('remaining_amount', '>', 0)
                ->whereIn('payment_status', ['pending', 'unpaid', 'partial', 'dp_paid', 'failed'])
                ->count();
            $outstandingAmount = (clone $userBookingsQuery)
                ->where('remaining_amount', '>', 0)
                ->sum('remaining_amount');

            // PHASE 3 (B15): booking outstanding alone undercounts — unpaid
            // medical extra balances are also due. Both stay separate keys in
            // shape; only the summed value now reflects the real total.
            $extraOutstanding = MedicalRecord::whereHas('pet', fn($q) => $q->where('user_id', $user->id))
                ->whereNotIn('extra_payment_status', ['paid', 'not_required'])
                ->selectRaw('COALESCE(SUM(GREATEST(extra_payment_amount - extra_payment_paid_amount, 0)), 0) as total')
                ->value('total') ?? 0;
            $extraAttentionCount = MedicalRecord::whereHas('pet', fn($q) => $q->where('user_id', $user->id))
                ->whereNotIn('extra_payment_status', ['paid', 'not_required'])
                ->whereRaw('extra_payment_amount > extra_payment_paid_amount')
                ->count();
            $paymentAttentionCount += $extraAttentionCount;
            $outstandingAmount = (float) $outstandingAmount + (float) $extraOutstanding;
            
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
            // PHASE 3 (B15): lower bound added — overdue visits are not
            // "due in the next 14 days". Overdue stays visible via the
            // vaccination overdue alerts and visit history.
            $followUpDueCount = MedicalRecord::whereHas('pet', fn($q) => $q->where('user_id', $user->id))
                ->whereNotNull('next_visit_date')
                ->whereDate('next_visit_date', '>=', now()->toDateString())
                ->whereDate('next_visit_date', '<=', now()->addDays(14))
                ->count();
            
            // Vaccination alerts
            $dueVaccinations = Vaccination::with(['pet'])
                ->whereHas('pet', fn($q) => $q->where('user_id', $user->id))
                ->upcomingDue(7)
                ->get();
            
            $overdueVaccinations = Vaccination::with(['pet'])
                ->whereHas('pet', fn($q) => $q->where('user_id', $user->id))
                ->overdue()
                ->get();

            return [
                'pets' => [
                    'total' => $totalPets,
                    'active' => $activePets,
                ],
                'bookings' => [
                    'upcoming' => $upcomingBookings,
                    'pending' => $pendingBookings,
                    'confirmed' => $confirmedBookings,
                ],
                'payments' => [
                    'attention_count' => $paymentAttentionCount,
                    'outstanding_amount' => (float) $outstandingAmount,
                ],
                'medical' => [
                    'total_visits' => $totalVisits,
                    'total_spent' => (float) $totalSpent,
                    'recent_visits' => $recentVisits,
                    'follow_up_due' => $followUpDueCount,
                ],
                'vaccination_alerts' => [
                    'due_soon' => $dueVaccinations,
                    'overdue' => $overdueVaccinations,
                ],
                'summary' => $this->generateSummary(
                    $totalPets,
                    $upcomingBookings->count(),
                    $dueVaccinations->count() + $overdueVaccinations->count(),
                    $paymentAttentionCount
                ),
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Generate a quick summary message.
     */
    private function generateSummary(int $pets, int $upcomingBookings, int $vaccinationAlerts, int $paymentAttentionCount): string
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

        if ($paymentAttentionCount > 0) {
            $parts[] = "{$paymentAttentionCount} payment item" . ($paymentAttentionCount > 1 ? 's need' : ' needs') . ' attention';
        }
        
        return empty($parts) 
            ? 'Welcome to PetHeal!' 
            : implode(', ', $parts) . '.';
    }
}
