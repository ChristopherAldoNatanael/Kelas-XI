<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Services\FCMService;
use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        // Booking batal yang uangnya belum seluruhnya dikembalikan / sudah
        // dikembalikan. Lihat Booking::syncRefundPaymentStatus().
        Booking::PAYMENT_REFUND_PENDING,
        Booking::PAYMENT_REFUNDED,
    ];

    private const PAYMENT_TYPES = ['dp', 'full'];

    protected FCMService $fcmService;
    protected \App\Services\RefundService $refunds;
    // Hanya dipakai jalur "Kembalikan via Midtrans" (dana kembali ke metode
    // pembayaran asal). Jalur manual tetap murni pencatatan.
    protected \App\Services\PaymentService $payments;

    public function __construct(
        FCMService $fcmService,
        \App\Services\RefundService $refunds,
        \App\Services\PaymentService $payments
    ) {
        $this->fcmService = $fcmService;
        $this->refunds = $refunds;
        $this->payments = $payments;
    }

    /**
     * Catat pengembalian uang untuk booking yang sudah dibatalkan.
     *
     * Ada dua jalur, dan keduanya sama-sama MENULIS BUKTI ke `booking_refunds`:
     *
     *  1. `midtrans` — bila pasien membayar online, sistem meminta Midtrans
     *     mengembalikan dana ke metode pembayaran ASAL (VA/QRIS/e-wallet). Pasien
     *     tidak perlu mengirim nomor rekening, dan tidak perlu bertemu fisik.
     *  2. `transfer`/`cash`/`ewallet`/`other` — klinik mengirim dana sendiri
     *     (pasien membayar tunai/transfer ke klinik). Alur ini dibantu tombol
     *     WhatsApp di halaman booking untuk meminta data rekening pasien, dan
     *     bukti transfer bisa diunggah.
     *
     * Nominal selalu dicek lebih dulu (`refundableAmount`), jadi provider tidak
     * pernah diminta mengembalikan dana yang memang belum pernah masuk.
     */
    public function refund(Request $request, int $id)
    {
        $clinicId = currentClinicId();

        $booking = Booking::query()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->whereKey($id)
            ->firstOrFail();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:' . implode(',', array_keys(\App\Models\BookingRefund::METHODS)),
            'reference' => 'nullable|string|max:100',
            'reason' => 'required|string|max:500',
            // Bukti transfer: foto/screenshot atau PDF, maksimal 2 MB.
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:2048',
        ], [
            'amount.required' => __('admin.refund_amount_required'),
            'reason.required' => __('admin.refund_reason_required'),
            'proof.mimes' => __('admin.refund_proof_invalid'),
            'proof.max' => __('admin.refund_proof_invalid'),
        ]);

        $amount = (float) $validated['amount'];
        $method = $validated['method'];
        $reason = $validated['reason'];

        // Batas refund dicek SEBELUM menyentuh provider.
        $refundable = $this->refunds->refundableAmount($booking);
        if ($amount <= 0 || $amount > $refundable) {
            return back()->with('error', __('admin.refund_exceeds_refundable', [
                'amount' => 'Rp ' . number_format($refundable, 0, ',', '.'),
            ]));
        }

        $providerRefundId = null;
        $reference = $validated['reference'] ?? null;

        if ($method === 'midtrans') {
            $payment = $this->findRefundableOnlinePayment($booking);

            if ($payment === null) {
                return back()->with('error', __('admin.refund_midtrans_unavailable'));
            }

            $result = $this->payments->requestRefund(
                $payment,
                (int) round($amount),
                $reason,
                $request->user()
            );

            if (! ($result['success'] ?? false)) {
                return back()->with('error', __('admin.refund_midtrans_failed', [
                    'message' => $result['message'] ?: __('admin.refund_midtrans_unavailable'),
                ]));
            }

            $paymentRefund = $result['refund'];
            $providerRefundId = $paymentRefund?->provider_refund_id;
            $reference = $reference ?: ($paymentRefund?->refund_code ?? $payment->provider_order_id);
        }

        $proofPath = null;
        if ($method !== 'midtrans' && $request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('refund-proofs', 'public');
        }

        try {
            $refund = $this->refunds->record(
                $booking,
                $amount,
                $method,
                $reference,
                $reason,
                null,
                $providerRefundId,
                $proofPath
            );
        } catch (\RuntimeException $e) {
            // Refund lewat provider sudah diterima tetapi pencatatan gagal
            // (mis. booking berubah status bersamaan). Bukti tetap disimpan agar
            // admin bisa mencatat ulang tanpa kehilangan lampirannya.
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log(
            'payment.refund',
            'Refunded Rp ' . number_format((float) $refund->amount, 0, ',', '.')
                . " for booking #{$booking->id} via {$refund->method}. Reason: {$refund->reason}"
                . ($providerRefundId ? " (provider refund {$providerRefundId})" : ''),
            $booking
        );

        // Refund sebelumnya senyap dari sisi pasien: uang sudah diterima kembali
        // (tunai/transfer), tetapi tidak ada pemberitahuan apa pun sehingga pasien
        // menanyakan status berulang kali. Notifikasi juga menyebut nominal agar
        // tercocokkan dengan mutasi rekeningnya.
        $this->notifyRefundRecorded($booking, (float) $refund->amount, $refund->method);

        return back()->with('success', __('admin.refund_saved'));
    }

    /**
     * Pembayaran online (Midtrans) milik booking ini yang masih punya sisa dana
     * untuk dikembalikan lewat API provider.
     */
    private function findRefundableOnlinePayment(Booking $booking): ?\App\Models\Payment
    {
        return $booking->refundableOnlinePayment();
    }

    /**
     * Kabari pasien bahwa refund sudah dicatat (tidak menggagalkan aksi admin
     * bila pengiriman push gagal).
     */
    private function notifyRefundRecorded(Booking $booking, float $amount, string $method = 'transfer'): void
    {
        if ($amount <= 0) {
            return;
        }

        $channel = $method === 'midtrans'
            ? 'lewat Midtrans ke metode pembayaran awal Anda'
            : 'lewat ' . (\App\Models\BookingRefund::METHODS[$method] ?? $method);

        try {
            $this->fcmService->sendToUser(
                (int) $booking->user_id,
                'Dana Anda sudah dikembalikan',
                'Pengembalian dana Rp' . number_format($amount, 0, ',', '.')
                    . ' untuk booking #' . $booking->id . ' sudah diproses klinik ' . $channel . '.',
                [
                    'type' => 'payment',
                    'booking_id' => (string) $booking->id,
                    'clinic_id' => (string) $booking->clinic_id,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim notifikasi refund ke pasien', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
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
        // Uang yang benar-benar diterima = seluruh `paid_amount` (termasuk
        // booking yang kemudian dibatalkan, karena uangnya memang sempat
        // masuk) dikurangi refund yang sudah dicatat. Pembatalan TIDAK lagi
        // mengecualikan paid_amount: kalau dikeluarkan di sini lalu refund
        // juga dikurangkan, booking yang direfund penuh menghasilkan angka
        // negatif dan laporan berbeda dari dashboard.
        $totals = (clone $query)
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as total_collected')
            ->selectRaw("COALESCE(SUM(CASE WHEN status != 'cancelled' AND remaining_amount > 0 THEN remaining_amount ELSE 0 END), 0) as total_outstanding")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END), 0) as paid_count")
            ->first();

        // Refund mengurangi uang terkumpul: uang yang sudah dikembalikan bukan
        // pendapatan. Dihitung dari tabel refund untuk booking pada query yang
        // sama, sehingga filter tanggal/status tetap konsisten.
        $bookingIds = (clone $query)->select('id');

        $refunded = (float) \Illuminate\Support\Facades\DB::table('booking_refunds')
            ->whereIn('booking_id', $bookingIds)
            ->sum('amount');

        // Kewajiban refund yang belum dicatat: booking batal yang uangnya belum
        // kembali seluruhnya. Angkanya dihitung dari paid_amount dikurangi refund
        // yang sudah tercatat, sehingga kartu ini turun tepat saat admin mencatat
        // pengembalian dan hilang saat lunas.
        $refundDueQuery = (clone $query)
            ->where('status', 'cancelled')
            ->where('payment_status', Booking::PAYMENT_REFUND_PENDING);

        $refundDueCount = (int) (clone $refundDueQuery)->count();
        $refundDuePaid = (float) (clone $refundDueQuery)->sum('paid_amount');
        $refundDueRefunded = (float) \Illuminate\Support\Facades\DB::table('booking_refunds')
            ->whereIn('booking_id', (clone $refundDueQuery)->select('id'))
            ->sum('amount');

        return [
            'total_transactions' => $totals->total_transactions,
            'total_collected' => (float) $totals->total_collected - $refunded,
            'total_refunded' => $refunded,
            'total_outstanding' => $totals->total_outstanding,
            'paid_count' => $totals->paid_count,
            'refund_due_count' => $refundDueCount,
            'refund_due_amount' => (float) max(0, $refundDuePaid - $refundDueRefunded),
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

        // Invoice harus sepakat dengan panel admin: bila ada refund,
        // struknya ikut mencantumkannya, bukan hanya menampilkan "dibayar".
        $refundedTotal = app(\App\Services\RefundService::class)->refundedTotal($booking);

        $html = view('admin.payments.receipt_pdf', compact('booking', 'refundedTotal'))->render();
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

            // Booking yang sudah dibatalkan tidak boleh ditagih lagi. Uang yang
            // masuk setelah pembatalan adalah kewajiban refund, bukan pelunasan,
            // dan menambah `paid_amount` di sini akan menghapus status
            // `refund_pending` booking tersebut.
            if ($booking->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'amount' => __('admin.payment_confirm_cancelled'),
                ]);
            }

            // Rupiah integer: nominal dari form dibulatkan sekali, lalu
            // dibandingkan apa adanya. Tidak ada lagi toleransi 0,001 rupiah.
            $remaining = max(0, Money::rupiah($booking->remaining_amount));
            $amount = Money::rupiah($request->input('amount'));

            // Backend guard for the UI's max attribute: never let a manual
            // confirmation exceed the outstanding balance.
            if ($amount > $remaining) {
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

            $total = Money::rupiah($booking->total_amount);
            $paidAfter = min($total, Money::rupiah($booking->paid_amount) + $amount);
            $booking->paid_amount = Money::toFloat($paidAfter);
            $booking->remaining_amount = Money::toFloat(max(0, $total - $paidAfter));
            $booking->payment_date = now();

            if ($booking->remaining_amount <= 0) {
                $booking->payment_status = 'paid';
            } elseif ($booking->payment_type === 'dp' && $booking->paid_amount >= $booking->dp_amount) {
                $booking->payment_status = 'dp_paid';
            } else {
                $booking->payment_status = 'partial';
            }

            $booking->save();

            // Konfirmasi kas manual juga mengubah `paid_amount`, jadi biaya
            // tambahan rekam medis harus ikut disesuaikan di dalam transaksi yang
            // sama — kalau tidak, kasir mencatat pelunasan tetapi aplikasi pasien
            // masih menampilkan sisa tagihan yang sudah dibayar.
            MedicalRecord::syncExtraPaymentForBooking($booking);

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
        Cache::forget('dashboard_stats_v4_c' . ($cacheClinicId ?: 'all') . '_all');

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
