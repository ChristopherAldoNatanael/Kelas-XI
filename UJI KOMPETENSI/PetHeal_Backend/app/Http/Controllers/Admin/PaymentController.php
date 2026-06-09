<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Jobs\SendFcmNotification;
use App\Services\FCMService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    protected FCMService $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Display payment management page
     */
    public function index(Request $request)
    {
        $query = Booking::with(['user', 'pet', 'doctor'])
            ->whereNotNull('total_amount')
            ->where('total_amount', '>', 0);

        // Filter by payment status
        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }

        // Filter by payment type
        if ($request->filled('type')) {
            $query->where('payment_type', $request->type);
        }

        // Filter by payment method
        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }

        // Search by customer name, pet name, or booking ID
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "{$search}%")
                        ->orWhere('email', 'like', "{$search}%");
                })->orWhereHas('pet', function ($pq) use ($search) {
                    $pq->where('name', 'like', "{$search}%");
                });
            });
        }

        // Sort
        $sort = $request->get('sort', 'updated_at');
        $direction = $request->get('direction', 'desc');
        $allowedSorts = ['updated_at', 'total_amount', 'paid_amount', 'remaining_amount', 'payment_status'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        $payments = $query->paginate(20)->withQueryString();

        // Statistics - cached for 5 minutes
        $stats = Cache::remember('payment_stats', 300, function () {
            $totals = Booking::selectRaw('COUNT(*) as total_transactions')
                ->selectRaw('COALESCE(SUM(paid_amount), 0) as total_collected')
                ->selectRaw('COALESCE(SUM(CASE WHEN remaining_amount > 0 THEN remaining_amount ELSE 0 END), 0) as total_outstanding')
                ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END), 0) as paid_count")
                ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count")
                ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'dp_paid' THEN 1 ELSE 0 END), 0) as dp_paid_count")
                ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END), 0) as failed_count")
                ->whereNotNull('total_amount')
                ->where('total_amount', '>', 0)
                ->first();

            return [
                'total_transactions' => $totals->total_transactions,
                'total_collected' => $totals->total_collected,
                'total_outstanding' => $totals->total_outstanding,
                'paid_count' => $totals->paid_count,
                'pending_count' => $totals->pending_count,
                'dp_paid_count' => $totals->dp_paid_count,
                'failed_count' => $totals->failed_count,
            ];
        });

        return view('admin.payments.index', compact('payments', 'stats'));
    }

    /**
     * Display payment detail
     */
    public function show($id)
    {
        $booking = Booking::with(['user', 'pet', 'doctor', 'medicalRecords'])
            ->findOrFail($id);

        // Calculate payment progress
        $paymentProgress = $booking->total_amount > 0
            ? round(($booking->paid_amount / $booking->total_amount) * 100, 2)
            : 0;

        return view('admin.payments.show', compact('booking', 'paymentProgress'));
    }

    /**
     * Manually confirm payment (admin override)
     */
    public function confirm(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $booking = Booking::findOrFail($id);

        // Update payment info
        $booking->paid_amount += $request->amount;
        $booking->remaining_amount = max(0, $booking->total_amount - $booking->paid_amount);
        if ($request->amount > 0) {
            $booking->payment_date = now();
        }

        // Update status based on remaining amount
        if ($booking->remaining_amount <= 0) {
            $booking->payment_status = 'paid';
        } elseif ($booking->payment_type === 'dp' && $booking->paid_amount >= $booking->dp_amount) {
            $booking->payment_status = 'dp_paid';
        } else {
            $booking->payment_status = 'partial';
        }

        $booking->save();
        Cache::forget('payment_stats');
        Cache::forget('dashboard_stats_all');

        return redirect()->back()->with('success', 'Payment confirmed successfully.');
    }

    /**
     * Export payments as PDF
     */
    public function exportPdf(Request $request)
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $query = Booking::where('paid_amount', '>', 0)
            ->with(['pet.user', 'doctor', 'service'])
            ->latest();

        if (!empty($validated['from'])) {
            $query->whereDate('updated_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->whereDate('updated_at', '<=', $validated['to']);
        }

        $bookings = $query->limit(200)->get();
        $html = view('admin.exports.payments_pdf', compact('bookings'))->render();
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="payments-export-' . now()->format('Y-m-d') . '.pdf"');
    }

    public function sendReminder($id)
    {
        $booking = Booking::with(['user', 'pet'])->findOrFail($id);

        if (!$booking->user) {
            return redirect()->back()->with('error', 'Customer not found.');
        }

        SendFcmNotification::dispatch(
            $booking->user->id,
            'Payment Reminder',
            "Hi {$booking->user->name}! Your booking for {$booking->pet->name} has a remaining balance of Rp " . number_format($booking->remaining_amount, 0, ',', '.') . ". Please complete the payment.",
            [
                'type' => 'payment_reminder',
                'booking_id' => (string) $booking->id,
                'remaining_amount' => (string) $booking->remaining_amount,
                'pet_name' => $booking->pet->name,
            ]
        );

        return redirect()->back()->with('success', 'Payment reminder sent to customer successfully.');
    }
}
