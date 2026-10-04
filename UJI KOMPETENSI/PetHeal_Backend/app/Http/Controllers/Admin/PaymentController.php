<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
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
     * Display payment management page — clinic scoped
     */
    public function index(Request $request)
    {
        $clinicId = currentClinicId();

        $query = Booking::with(['user', 'pet', 'doctor'])
            ->whereNotNull('total_amount')
            ->where('total_amount', '>', 0)
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId));

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

        // Sort (PHASE 3 V-03: direction whitelisted — raw direction into
        // orderBy() turned invalid values into 500s and is an ORDER BY vector).
        $sort = $request->get('sort', 'updated_at');
        $direction = strtolower((string) $request->get('direction', 'desc'));
        $allowedSorts = ['updated_at', 'total_amount', 'paid_amount', 'remaining_amount', 'payment_status'];
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        $payments = $query->paginate(20)->withQueryString();

        // Statistics - cached for 5 minutes, scoped by clinic
        $statsCacheKey = 'payment_stats_c' . ($clinicId ?: 'all');
        $stats = Cache::remember($statsCacheKey, 300, function () use ($clinicId) {
            $totals = Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->selectRaw('COUNT(*) as total_transactions')
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
     * Display payment detail — clinic scoped
     */
    public function show($id)
    {
        $clinicId = currentClinicId();
        $query = Booking::with(['user', 'pet', 'doctor', 'medicalRecord'])->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $booking = $query->firstOrFail();

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

        $clinicId = currentClinicId();

        // PHASE 3 (D-09): read-modify-write on paid/remaining must be atomic
        // with a row lock — concurrent admin confirm vs Midtrans webhook
        // previously lost updates to paid_amount.
        $booking = \Illuminate\Support\Facades\DB::transaction(function () use ($id, $clinicId, $request) {
            $query = Booking::where('id', $id);
            if ($clinicId) {
                $query->where('clinic_id', $clinicId);
            }
            $booking = $query->lockForUpdate()->firstOrFail();

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

            return $booking;
        });

        // PHASE 4: the validated `notes` memo previously went nowhere — keep
        // it as an audit trail (no column exists for it; never overwrite the
        // booking's appointment `notes` with a payment memo).
        $memo = trim((string) $request->input('notes', ''));
        AuditLog::log(
            'payment.confirm',
            "Manually confirmed Rp " . number_format((float) $request->input('amount', 0), 0, ',', '.')
                . " for booking #{$booking->id} ({$booking->payment_status})"
                . ($memo !== '' ? " — {$memo}" : ''),
            $booking
        );

        $cacheClinicId = $booking->clinic_id ?: currentClinicId();
        Cache::forget('payment_stats_c' . ($cacheClinicId ?: 'all'));
        Cache::forget('dashboard_stats_c' . ($cacheClinicId ?: 'all') . '_all');

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

        $clinicId = currentClinicId();
        $query = Booking::where('paid_amount', '>', 0)
            ->with(['pet.user', 'doctor', 'service'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->latest();

        if (!empty($validated['from'])) {
            $query->whereDate('updated_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->whereDate('updated_at', '<=', $validated['to']);
        }

        $bookings = $query->get();
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
        $clinicId = currentClinicId();
        $query = Booking::with(['user', 'pet', 'doctor'])->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $booking = $query->firstOrFail();

        if (!$booking->user) {
            return redirect()->back()->with('error', 'Customer not found.');
        }

        $sent = $this->fcmService->sendPaymentReminder(
            $booking->user->id,
            $booking->pet->name,
            'Rp ' . number_format((float) $booking->remaining_amount, 0, ',', '.'),
            $booking->id,
            $booking->pet_id,
            $booking->doctor_id
        );

        return redirect()->back()->with(
            $sent ? 'success' : 'warning',
            $sent
                ? 'Payment reminder sent to customer successfully.'
                : 'Payment reminder could not be delivered to the device. Check FCM token, notification permission, or Firebase credentials.'
        );
    }
}
