<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Services\FCMService;
use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    private const PAYMENT_STATUSES = [
        'unpaid',
        'pending',
        'dp_pending',
        'dp_paid',
        'paid',
        'partial',
        'failed',
    ];

    private const PAYMENT_TYPES = ['dp', 'full'];

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
        $this->validateFilters($request);

        $clinicId = currentClinicId();
        $filteredQuery = $this->filteredPaymentsQuery($request);

        // Statistics follow the active filters so the cards always agree with
        // the table/export below. The unfiltered card set keeps its 5-minute
        // cache; filtered combinations are computed live (and never leave a
        // stale aggregate behind for an arbitrary filter).
        $statsCacheKey = 'payment_stats_c' . ($clinicId ?: 'all');
        $stats = $this->hasActiveFilters($request)
            ? $this->paymentStats($filteredQuery)
            : Cache::remember($statsCacheKey, 300, fn() => $this->paymentStats($filteredQuery));

        $query = clone $filteredQuery;
        $this->applySort($query, $request);

        $payments = $query->with(['user', 'pet', 'doctor'])
            ->paginate(20)
            ->withQueryString();

        // Stored values are payment-method names (QRIS, GoPay, Bank
        // Transfer - BCA, ...), not the old midtrans/cash/bank_transfer
        // pseudo-types. Populate the filter from real rows.
        $paymentMethods = Booking::query()
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->whereNotNull('payment_method')
            ->where('payment_method', '!=', '')
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method');

        return view('admin.payments.index', compact('payments', 'stats', 'paymentMethods'));
    }

    private function hasActiveFilters(Request $request): bool
    {
        return trim((string) $request->input('search', '')) !== ''
            || $request->filled('method')
            || in_array((string) $request->input('status'), self::PAYMENT_STATUSES, true)
            || in_array((string) $request->input('type'), self::PAYMENT_TYPES, true);
    }

    private function paymentStats(Builder $query): array
    {
        // Cancelled bookings stay in the transaction count but never inflate
        // the outstanding balance.
        $totals = (clone $query)
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as total_collected')
            ->selectRaw("COALESCE(SUM(CASE WHEN status != 'cancelled' AND remaining_amount > 0 THEN remaining_amount ELSE 0 END), 0) as total_outstanding")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END), 0) as paid_count")
            ->first();

        return [
            'total_transactions' => $totals->total_transactions,
            'total_collected' => $totals->total_collected,
            'total_outstanding' => $totals->total_outstanding,
            'paid_count' => $totals->paid_count,
        ];
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'method' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            // status/type/sort/direction are intentionally not validated
            // here. Stale or tampered filter links must still render the page,
            // so the query whitelist ignores unknown values and applySort()
            // falls back to updated_at + desc instead of redirecting.
        ]);
    }

    private function filteredPaymentsQuery(Request $request): Builder
    {
        $clinicId = currentClinicId();

        $query = Booking::query()
            ->whereNotNull('total_amount')
            ->where('total_amount', '>', 0)
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId));

        $status = (string) $request->input('status', '');
        if (in_array($status, self::PAYMENT_STATUSES, true)) {
            $query->where('payment_status', $status);
        }

        $type = (string) $request->input('type', '');
        if (in_array($type, self::PAYMENT_TYPES, true)) {
            $query->where('payment_type', $type);
        }

        if ($request->filled('method')) {
            $query->where('payment_method', $request->input('method'));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
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

        return $query;
    }

    private function applySort(Builder $query, Request $request): void
    {
        // Direction is whitelisted; raw input would be an ORDER BY vector.
        $sort = (string) $request->input('sort', 'updated_at');
        $direction = strtolower((string) $request->input('direction', 'desc'));

        $allowedSorts = ['updated_at', 'total_amount', 'paid_amount', 'remaining_amount', 'payment_status'];
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('updated_at', 'desc');
        }
    }

    /**
     * Payment detail is no longer a separate page.
     *
     * Satu booking = satu catatan. Panel pembayaran (rincian, invoice,
     * pengingat, dan konfirmasi manual) hidup di halaman detail booking,
     * jadi tautan lama tetap diarahkan ke sana, bukan menampilkan halaman
     * kedua dengan data booking yang sama.
     */
    public function show($id)
    {
        // Tetap tegakkan isolasi klinik sebelum mengalihkan: booking milik
        // klinik lain harus 404, bukan bocor lewat redirect.
        $clinicId = currentClinicId();
        $query = Booking::whereKey($id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $query->firstOrFail();

        return redirect()->to(
            route('admin.bookings.show', $id) . '#pembayaran'
        );
    }

    /**
     * Download a single booking invoice/receipt as PDF.
     *
     * Reuses the same booking payment fields as the payment detail page so the
     * document can never disagree with the admin screen or customer app.
     */
    public function receipt($id)
    {
        $clinicId = currentClinicId();
        $query = Booking::with(['user', 'pet', 'doctor', 'service', 'clinic'])->whereKey($id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $booking = $query->firstOrFail();

        $html = view('admin.payments.receipt_pdf', compact('booking'))->render();
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'invoice-' . str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT) . '.pdf';

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Manually confirm payment (admin override)
     */
    public function confirm(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $clinicId = currentClinicId();

        // PHASE 3 (D-09): read-modify-write on paid/remaining must be atomic
        // with a row lock — concurrent admin confirm vs Midtrans webhook
        // previously lost updates to paid_amount.
        [$booking, $appliedAmount] = \Illuminate\Support\Facades\DB::transaction(function () use ($id, $clinicId, $request) {
            $query = Booking::where('id', $id);
            if ($clinicId) {
                $query->where('clinic_id', $clinicId);
            }
            $booking = $query->lockForUpdate()->firstOrFail();

            $remaining = max(0, (float) $booking->remaining_amount);
            $amount = (float) $request->input('amount');

            // Backend guard for the UI's max attribute: never let a manual
            // confirmation exceed the outstanding balance.
            if ($amount > $remaining + 0.001) {
                $locale = app()->getLocale();
                throw ValidationException::withMessages([
                    'amount' => __('admin.payment_amount_exceeds_remaining', [
                        'remaining' => number_format(
                            $remaining,
                            0,
                            $locale === 'id' ? ',' : '.',
                            $locale === 'id' ? '.' : ','
                        ),
                    ]),
                ]);
            }

            $booking->paid_amount = min((float) $booking->total_amount, (float) $booking->paid_amount + $amount);
            $booking->remaining_amount = max(0, (float) $booking->total_amount - (float) $booking->paid_amount);
            $booking->payment_date = now();

            if ($booking->remaining_amount <= 0) {
                $booking->payment_status = 'paid';
            } elseif ($booking->payment_type === 'dp' && $booking->paid_amount >= $booking->dp_amount) {
                $booking->payment_status = 'dp_paid';
            } else {
                $booking->payment_status = 'partial';
            }

            $booking->save();

            return [$booking, $amount];
        });

        // PHASE 4: the validated `notes` memo previously went nowhere — keep
        // it as an audit trail (no column exists for it; never overwrite the
        // booking's appointment `notes` with a payment memo).
        $memo = trim((string) $request->input('notes', ''));
        AuditLog::log(
            'payment.confirm',
            "Manually confirmed Rp " . number_format($appliedAmount, 0, ',', '.')
                . " for booking #{$booking->id} ({$booking->payment_status})"
                . ($memo !== '' ? " — {$memo}" : ''),
            $booking
        );

        $cacheClinicId = $booking->clinic_id ?: currentClinicId();
        Cache::forget('payment_stats_c' . ($cacheClinicId ?: 'all'));
        Cache::forget('dashboard_stats_v3_c' . ($cacheClinicId ?: 'all') . '_all');

        return redirect()->back()->with('success', __('admin.payment_confirmed'));
    }

    /**
     * Export payments as PDF
     */
    public function exportPdf(Request $request)
    {
        $this->validateFilters($request);

        $query = $this->filteredPaymentsQuery($request)
            ->with(['pet.user', 'doctor', 'service']);

        if ($request->filled('from')) {
            $query->whereDate('updated_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('updated_at', '<=', $request->input('to'));
        }

        // Export exactly the rows the list would show: the old unconditional
        // paid_amount > 0 clause made status=unpaid/dp_pending exports empty.
        $this->applySort($query, $request);
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
            return redirect()->back()->with('error', __('admin.customer_not_found'));
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
                ? __('admin.payment_reminder_sent')
                : __('admin.payment_reminder_failed')
        );
    }
}
