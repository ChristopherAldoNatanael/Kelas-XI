<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Payment;
use App\Models\PaymentCredentialVersion;
use App\Models\PaymentNotification;
use App\Models\PaymentStatusHistory;
use App\Models\Pet;
use App\Models\ProductOrder;
use App\Models\Service;
use App\Models\User;
use App\Services\PaymentConfigurationService;
use App\Services\PaymentReconciliationService;
use App\Services\PaymentService;
use App\Services\ResolvedPaymentAccount;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Addendum: Advanced Multi-Clinic Midtrans Configuration & Automatic Routing.
 *
 * A. Multi-merchant      : klinik A & B memakai kredensial masing-masing.
 * B. Fallback pusat      : hanya klinik yang belum pernah konfigurasi.
 * C. Konfigurasi invalid : ditolak, TIDAK fallback ke pusat.
 * D. Merchant salah      : webhook merchant lain ditolak.
 * E. DP                  : nominal DP dari database, bukan request.
 * F. Lunas               : nominal penuh dihitung server.
 * G. Pelunasan           : mengikuti route transaksi pertama.
 * H. Rotasi kredensial   : transaksi lama tetap diverifikasi versi lamanya.
 * I. Pet Shop            : ikut routing + webhook menandai order lunas.
 * J. Webhook duplikat    : idempoten, dana tidak tercatat dua kali.
 * K. Proteksi secret     : Server Key tidak keluar ke API/serialisasi/UI.
 * L. Regresi & gate      : fallback nonaktif menolak, tanpa membuat payment.
 */
class ClinicPaymentRoutingTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY_A = 'SB-Mid-server-CLINIC-A-SECRET';
    private const KEY_B = 'SB-Mid-server-CLINIC-B-SECRET';
    private const KEY_CENTRAL = 'SB-Mid-server-CENTRAL-SECRET';

    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $superAdmin;
    private User $adminA;
    private User $patient;
    private Doctor $doctorA;
    private Doctor $doctorB;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.environment' => 'sandbox',
            'services.midtrans.central.sandbox.merchant_id' => 'CENTRAL-MERCHANT',
            'services.midtrans.central.sandbox.client_key' => 'SB-Mid-client-CENTRAL',
            'services.midtrans.central.sandbox.server_key' => self::KEY_CENTRAL,
            'services.midtrans.central_fallback.enabled' => true,
            'services.midtrans.central_fallback.production_approved' => false,
        ]);

        Http::fake([
            '*' => Http::response([
                'token' => 'snap-token-123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-123',
                'transaction_id' => 'tx-123',
            ], 200),
        ]);

        $suffix = strtolower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Routing A ' . $suffix, 'slug' => 'routing-a-' . $suffix,
            'address' => 'Jl. A', 'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'Routing B ' . $suffix, 'slug' => 'routing-b-' . $suffix,
            'address' => 'Jl. B', 'is_active' => true,
        ]);

        $mk = fn (string $tag, string $role, ?int $clinicId) => User::create([
            'name' => $tag, 'email' => $tag . $suffix . '@routing.test',
            'password' => Hash::make('password123'), 'role' => $role, 'clinic_id' => $clinicId,
        ]);

        $this->superAdmin = $mk('rsuper', 'super_admin', null);
        $this->adminA = $mk('radmina', 'clinic_admin', $this->clinicA->id);
        $this->patient = $mk('rpatient', 'user', $this->clinicA->id);

        $this->doctorA = $this->makeDoctor($this->clinicA, 'A', $suffix);
        $this->doctorB = $this->makeDoctor($this->clinicB, 'B', $suffix);
    }

    // ---------------------------------------------------------------- A / E / F

    public function test_booking_dp_uses_clinic_merchant_and_server_side_amount(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);

        $booking = $this->makeBooking($this->clinicA, [
            'payment_type' => 'dp',
            'total_amount' => 100000,
            'dp_amount' => 30000,
            'remaining_amount' => 70000,
            'paid_amount' => 0,
        ]);

        $orderId = 'BOOKING-' . $booking->id . '-' . (int) (microtime(true) * 1000);

        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => 30000,
            ],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.routing_source', ResolvedPaymentAccount::SOURCE_CLINIC);

        $payment = Payment::where('provider_order_id', $orderId)->firstOrFail();

        $this->assertSame(30000, (int) $payment->gross_amount);
        $this->assertSame($this->clinicA->id, (int) $payment->clinic_id);
        $this->assertNotNull($payment->payment_account_id);
        $this->assertNotNull($payment->payment_credential_version_id);
        $this->assertSame('MERCHANT-A', $payment->merchant_id);
        $this->assertSame(ResolvedPaymentAccount::SOURCE_CLINIC, $payment->routing_source);

        $this->assertSnapUsedKey(self::KEY_A);

        // Request ulang order id yang sama mengembalikan payment yang sama.
        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => 30000,
            ],
        ])->assertOk()->assertJsonPath('data.payment_id', $payment->id);

        $this->assertSame(1, Payment::where('provider_order_id', $orderId)->count());

        // Nominal dari request tidak boleh menimpa nominal database.
        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', [
            'transaction_details' => [
                'order_id' => 'BOOKING-' . $booking->id . '-999',
                'gross_amount' => 1000,
            ],
        ])->assertStatus(422);
    }

    public function test_two_clinics_use_their_own_keys(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);
        $this->configureClinic($this->clinicB, 'MERCHANT-B', self::KEY_B);

        $bookingA = $this->makeBooking($this->clinicA, ['total_amount' => 50000]);
        $bookingB = $this->makeBooking($this->clinicB, ['total_amount' => 60000]);

        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', [
            'transaction_details' => [
                'order_id' => 'BOOKING-' . $bookingA->id . '-' . (int) (microtime(true) * 1000),
                'gross_amount' => 50000,
            ],
        ])->assertOk();

        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', [
            'transaction_details' => [
                'order_id' => 'BOOKING-' . $bookingB->id . '-' . (int) (microtime(true) * 1000 + 1),
                'gross_amount' => 60000,
            ],
        ])->assertOk();

        $authorizations = $this->snapAuthorizations();
        $this->assertCount(2, $authorizations);
        $this->assertSame($this->expectedAuth(self::KEY_A), $authorizations[0]);
        $this->assertSame($this->expectedAuth(self::KEY_B), $authorizations[1]);
    }

    // -------------------------------------------------------------------- B / C / L

    public function test_unconfigured_clinic_falls_back_to_central_in_sandbox(): void
    {
        $booking = $this->makeBooking($this->clinicB, ['total_amount' => 40000]);

        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', [
            'transaction_details' => [
                'order_id' => 'BOOKING-' . $booking->id . '-' . (int) (microtime(true) * 1000),
                'gross_amount' => 40000,
            ],
        ])->assertOk()->assertJsonPath('data.routing_source', ResolvedPaymentAccount::SOURCE_CENTRAL_FALLBACK);

        $this->assertSnapUsedKey(self::KEY_CENTRAL);
    }

    public function test_invalid_clinic_credentials_never_fall_back_to_central(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);

        // Versi baru disimpan tetapi belum diuji/diaktifkan → akun pending.
        app(PaymentConfigurationService::class)->saveCredentials($this->clinicA, [
            'environment' => 'sandbox',
            'merchant_id' => 'MERCHANT-A-BROKEN',
            'client_key' => 'SB-Mid-client-broken',
            'server_key' => 'SB-Mid-server-broken',
        ], $this->adminA);

        $booking = $this->makeBooking($this->clinicA, ['total_amount' => 45000]);

        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', [
            'transaction_details' => [
                'order_id' => 'BOOKING-' . $booking->id . '-' . (int) (microtime(true) * 1000),
                'gross_amount' => 45000,
            ],
        ])->assertStatus(422)
            ->assertJsonPath('code', 'online_payment_unavailable')
            ->assertJsonPath('reason', 'clinic_credentials_pending');

        $this->assertSame(0, Payment::where('payable_type', Booking::class)
            ->where('payable_id', $booking->id)->count());
        // Tidak ada panggilan Snap sama sekali.
        $this->assertCount(0, $this->snapAuthorizations());
    }

    public function test_fallback_disabled_rejects_clinic_without_configuration(): void
    {
        config(['services.midtrans.central_fallback.enabled' => false]);

        $booking = $this->makeBooking($this->clinicB, ['total_amount' => 20000]);

        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', [
            'transaction_details' => [
                'order_id' => 'BOOKING-' . $booking->id . '-' . (int) (microtime(true) * 1000),
                'gross_amount' => 20000,
            ],
        ])->assertStatus(422)
            ->assertJsonPath('code', 'online_payment_unavailable')
            ->assertJsonPath('reason', 'central_fallback_disabled');

        $this->assertCount(0, $this->snapAuthorizations());
    }

    public function test_preflight_follows_clinic_routing_without_central_credentials(): void
    {
        config([
            'services.midtrans.central.sandbox.server_key' => null,
            'services.midtrans.central.sandbox.client_key' => null,
            'services.midtrans.central_fallback.enabled' => false,
        ]);

        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);

        // Klinik punya kredensial sendiri: preflight tidak boleh gagal hanya
        // karena key pusat kosong.
        $this->apiAs($this->patient)->getJson('/api/payment/preflight')
            ->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.mode', 'sandbox')
            ->assertJsonPath('data.source', ResolvedPaymentAccount::SOURCE_CLINIC);

        // Tanpa konteks klinik dan tanpa key pusat: jujur tidak tersedia.
        $this->apiAs($this->superAdmin)->getJson('/api/payment/preflight')
            ->assertStatus(422)
            ->assertJsonPath('code', 'online_payment_unavailable')
            ->assertJsonPath('data.ready', false);
    }

    // ----------------------------------------------------------------- D / H / J

    public function test_webhook_signature_uses_locked_version_and_rejects_wrong_merchant(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);
        $this->configureClinic($this->clinicB, 'MERCHANT-B', self::KEY_B);

        $payment = $this->createPaidReadyBookingPayment($this->clinicA, 30000);

        // 1. Signature dengan key klinik lain → ditolak.
        $this->postWebhook($this->payload($payment, self::KEY_B, 'MERCHANT-A'))
            ->assertStatus(403);

        // 2. Signature benar tetapi merchant berbeda → ditolak.
        $this->postWebhook($this->payload($payment, self::KEY_A, 'MERCHANT-B'))
            ->assertStatus(422);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);

        // 3. Signature + merchant benar → diterima.
        $this->postWebhook($this->payload($payment, self::KEY_A, 'MERCHANT-A'))
            ->assertOk();

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
    }

    public function test_rotation_keeps_old_transaction_verifiable_and_new_payment_uses_new_version(): void
    {
        $first = $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);
        $payment = $this->createPaidReadyBookingPayment($this->clinicA, 25000);

        // Rotasi: versi 2 diuji & diaktifkan.
        $second = $this->configureClinic($this->clinicA, 'MERCHANT-A2', self::KEY_B);

        $this->assertGreaterThan($first->version, $second->version);
        $this->assertSame($second->id, (int) Clinic::find($this->clinicA->id)
            ->paymentAccount->current_credential_version_id);

        // Transaksi lama tetap diverifikasi dengan Server Key versi 1.
        $this->postWebhook($this->payload($payment, self::KEY_A, 'MERCHANT-A'))->assertOk();
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);

        // Transaksi baru memakai versi 2.
        $booking = $this->makeBooking($this->clinicA, ['total_amount' => 15000]);
        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', [
            'transaction_details' => [
                'order_id' => 'BOOKING-' . $booking->id . '-' . (int) (microtime(true) * 1000),
                'gross_amount' => 15000,
            ],
        ])->assertOk();

        $newPayment = Payment::where('payable_type', Booking::class)
            ->where('payable_id', $booking->id)->firstOrFail();

        $this->assertSame($second->id, (int) $newPayment->payment_credential_version_id);
        $this->assertSnapUsedKey(self::KEY_B);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);
        $payment = $this->createPaidReadyBookingPayment($this->clinicA, 30000);

        $body = $this->payload($payment, self::KEY_A, 'MERCHANT-A');
        $this->postWebhook($body)->assertOk();
        $paidAt = $payment->fresh()->paid_at;

        $this->postWebhook($body)->assertOk();

        $fresh = $payment->fresh();
        $this->assertSame(Payment::STATUS_PAID, $fresh->status);
        $this->assertEquals($paidAt, $fresh->paid_at);
        $this->assertSame(1, PaymentStatusHistory::where('payment_id', $payment->id)
            ->where('to_status', Payment::STATUS_PAID)->count());
        $this->assertSame(2, PaymentNotification::where('provider_order_id', $payment->provider_order_id)->count());
    }

    // -------------------------------------------------------------------------- G

    public function test_remaining_payment_follows_first_payment_route(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);

        $booking = $this->makeBooking($this->clinicA, [
            'payment_type' => 'dp',
            'total_amount' => 100000,
            'dp_amount' => 30000,
            'remaining_amount' => 70000,
            'paid_amount' => 30000,
            'payment_status' => 'dp_paid',
        ]);

        $firstPayment = $this->createPaidReadyBookingPayment($this->clinicA, 30000, $booking->id);
        $firstVersionId = (int) $firstPayment->payment_credential_version_id;

        // Klinik berganti kredensial setelah DP dibayar.
        $this->configureClinic($this->clinicA, 'MERCHANT-A2', self::KEY_B);
        Http::fake(['*' => Http::response(['token' => 'snap-2', 'redirect_url' => 'https://x', 'transaction_id' => 'tx-2'], 200)]);

        $this->apiAs($this->patient)
            ->postJson('/api/payment/remaining/' . $booking->id)
            ->assertOk()
            ->assertJsonPath('data.gross_amount', 70000)
            ->assertJsonPath('data.routing_source', ResolvedPaymentAccount::SOURCE_CLINIC);

        $remaining = Payment::where('payable_type', Booking::class)
            ->where('payable_id', $booking->id)
            ->where('gross_amount', 70000)
            ->firstOrFail();

        $this->assertSame($firstVersionId, (int) $remaining->payment_credential_version_id);
        $this->assertSame('MERCHANT-A', $remaining->merchant_id);
        $this->assertSnapUsedKey(self::KEY_A);
    }

    // -------------------------------------------------------------------------- I

    public function test_pet_shop_payment_routes_to_clinic_and_webhook_marks_order_paid(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);

        $order = ProductOrder::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'user_id' => $this->patient->id,
            'clinic_id' => $this->clinicA->id,
            'status' => ProductOrder::STATUS_PENDING_PAYMENT,
            'payment_status' => ProductOrder::PAYMENT_UNPAID,
            'payment_method' => ProductOrder::METHOD_ONLINE,
            'subtotal' => 25000,
            'discount_total' => 0,
            'total_amount' => 25000,
            'currency' => 'IDR',
            'pickup_clinic_name' => $this->clinicA->name,
            'pickup_clinic_address' => $this->clinicA->address,
            'pickup_clinic_phone' => $this->clinicA->phone ?? '0800000000',
            'customer_name' => 'Pet Shop Buyer',
            'customer_phone' => '08123456789',
        ]);

        $payment = app(PaymentService::class)->createPetShopPayment($order);

        $this->assertSame(ResolvedPaymentAccount::SOURCE_CLINIC, $payment->routing_source);
        $this->assertSame('MERCHANT-A', $payment->merchant_id);
        $this->assertSame(25000, (int) $payment->gross_amount);
        $this->assertSnapUsedKey(self::KEY_A);

        $this->postWebhook($this->payload($payment, self::KEY_A, 'MERCHANT-A'))->assertOk();
        $this->postWebhook($this->payload($payment, self::KEY_A, 'MERCHANT-A'))->assertOk();

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(ProductOrder::PAYMENT_PAID, $order->fresh()->payment_status);
    }

    // -------------------------------------------------------------------------- K

    public function test_server_key_never_leaks_to_api_response_or_serialization(): void
    {
        $version = $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);

        $booking = $this->makeBooking($this->clinicA, ['total_amount' => 12000]);
        $response = $this->apiAs($this->patient)->postJson('/api/payment/snap-token', [
            'transaction_details' => [
                'order_id' => 'BOOKING-' . $booking->id . '-' . (int) (microtime(true) * 1000),
                'gross_amount' => 12000,
            ],
        ])->assertOk();

        $this->assertStringNotContainsString(self::KEY_A, $response->getContent());
        $this->assertArrayNotHasKey('server_key', $version->toArray());
        $this->assertArrayNotHasKey('server_key', $version->fresh()->toArray());

        // UI VCMS juga tidak boleh menampilkan secret.
        $this->actingAs($this->adminA)
            ->get('/admin/payment-settings')
            ->assertOk()
            ->assertDontSee(self::KEY_A);
    }

    public function test_super_admin_overview_lists_clinics_without_secret(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);

        $this->actingAs($this->superAdmin)
            ->get('/admin/payment-settings')
            ->assertOk()
            ->assertSee($this->clinicA->name)
            ->assertDontSee(self::KEY_A);
    }

    // ------------------------------------------------------------- MEDREC & ops

    public function test_medical_record_extra_payment_routes_to_clinic_and_webhook_marks_record_paid(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);
        $this->configureClinic($this->clinicB, 'MERCHANT-B', self::KEY_B);

        $booking = $this->makeBooking($this->clinicA, [
            'total_amount' => 50000,
            'paid_amount' => 50000,
            'payment_status' => 'paid',
        ]);
        $record = $this->makeMedicalRecord($booking, 20000);

        // Merchant & clinic dari client tidak boleh dipakai; nominal juga
        // selalu dari database.
        $response = $this->apiAs($this->patient)
            ->postJson('/api/medical-records/' . $record->id . '/pay', [
                'merchant_id' => 'ATTACKER-MERCHANT',
                'clinic_id' => $this->clinicB->id,
                'gross_amount' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.routing_source', ResolvedPaymentAccount::SOURCE_CLINIC)
            ->assertJsonPath('data.gross_amount', 20000);

        $payment = Payment::where('provider_order_id', $response->json('data.order_id'))->firstOrFail();

        $this->assertSame(Payment::TYPE_MEDICAL_RECORD, $payment->payment_type);
        $this->assertSame(MedicalRecord::class, $payment->payable_type);
        $this->assertSame($record->id, (int) $payment->payable_id);
        $this->assertSame($this->clinicA->id, (int) $payment->clinic_id);
        $this->assertSame('MERCHANT-A', $payment->merchant_id);
        $this->assertSame(20000, (int) $payment->gross_amount);
        $this->assertSnapUsedKey(self::KEY_A);

        // Currency asing ditolak sebelum dana dicatat.
        $wrongCurrency = $this->payload($payment, self::KEY_A, 'MERCHANT-A');
        $wrongCurrency['currency'] = 'USD';
        $this->postWebhook($wrongCurrency)->assertStatus(422);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);

        // Merchant lain ditolak.
        $this->postWebhook($this->payload($payment, self::KEY_A, 'MERCHANT-B'))->assertStatus(422);

        // Notifikasi sah menulis ke Payment dan rekam medis sekaligus.
        $this->postWebhook($this->payload($payment, self::KEY_A, 'MERCHANT-A'))->assertOk();

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $record->refresh();
        $this->assertSame('paid', $record->extra_payment_status);
        $this->assertEquals(20000, (float) $record->extra_payment_paid_amount);
        $this->assertSame($payment->provider_order_id, $record->extra_payment_order_id);
    }

    public function test_manual_status_sync_updates_medical_record_and_payment_row(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);

        $booking = $this->makeBooking($this->clinicA, [
            'total_amount' => 50000,
            'paid_amount' => 50000,
            'payment_status' => 'paid',
        ]);
        $record = $this->makeMedicalRecord($booking, 15000);

        $response = $this->apiAs($this->patient)
            ->postJson('/api/medical-records/' . $record->id . '/pay')
            ->assertOk();

        $payment = Payment::where('provider_order_id', $response->json('data.order_id'))->firstOrFail();

        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            '*' => Http::response([
                'transaction_status' => 'settlement',
                'status_code' => '200',
                'gross_amount' => '15000.00',
                'currency' => 'IDR',
                'merchant_id' => 'MERCHANT-A',
                'transaction_id' => 'tx-sync',
                'payment_type' => 'qris',
            ], 200),
        ]);

        $this->apiAs($this->patient)
            ->postJson('/api/payment/sync-status', ['order_id' => $payment->provider_order_id])
            ->assertOk();

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame('paid', $record->fresh()->extra_payment_status);
    }

    public function test_reconciliation_uses_locked_clinic_account_not_central_key(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);
        $payment = $this->createPaidReadyBookingPayment($this->clinicA, 25000);

        // SetUp sudah memasang stub '*' untuk Snap. Reset factory supaya
        // respons status transaksi di bawah tidak tertutup stub lama.
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            '*' => Http::response([
                'transaction_status' => 'settlement',
                'status_code' => '200',
                'gross_amount' => number_format((float) $payment->gross_amount, 2, '.', ''),
                'currency' => 'IDR',
                'merchant_id' => 'MERCHANT-A',
                'transaction_id' => 'tx-reconcile',
                'payment_type' => 'qris',
            ], 200),
        ]);

        $result = app(PaymentReconciliationService::class)->sync($payment);

        $this->assertTrue($result['checked']);
        $this->assertTrue($result['changed'], json_encode($result));
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);

        $statusRequest = collect(Http::recorded())
            ->first(fn ($pair) => str_contains((string) $pair[0]->url(), '/status'));

        $this->assertNotNull($statusRequest, 'Status request was not sent.');
        $this->assertStringContainsString('api.sandbox.midtrans.com', (string) $statusRequest[0]->url());
        $this->assertSame($this->expectedAuth(self::KEY_A), $statusRequest[0]->header('Authorization')[0] ?? null);
    }

    public function test_production_fallback_requires_explicit_approval(): void
    {
        config([
            'services.midtrans.environment' => 'production',
            'services.midtrans.central.production.merchant_id' => 'CENTRAL-PROD',
            'services.midtrans.central.production.client_key' => 'Mid-client-prod',
            'services.midtrans.central.production.server_key' => 'Mid-server-prod',
            'services.midtrans.central_fallback.enabled' => true,
            'services.midtrans.central_fallback.production_approved' => false,
        ]);

        $booking = $this->makeBooking($this->clinicB, ['total_amount' => 30000]);
        $body = [
            'transaction_details' => [
                'order_id' => 'BOOKING-' . $booking->id . '-' . (int) (microtime(true) * 1000),
                'gross_amount' => 30000,
            ],
        ];

        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', $body)
            ->assertStatus(422)
            ->assertJsonPath('code', 'online_payment_unavailable')
            ->assertJsonPath('reason', 'central_fallback_disabled');

        $this->assertSame(0, Payment::where('payable_type', Booking::class)
            ->where('payable_id', $booking->id)->count());

        config(['services.midtrans.central_fallback.production_approved' => true]);

        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', $body)
            ->assertOk()
            ->assertJsonPath('data.routing_source', ResolvedPaymentAccount::SOURCE_CENTRAL_FALLBACK);

        $payment = Payment::where('payable_type', Booking::class)
            ->where('payable_id', $booking->id)->firstOrFail();

        $this->assertSame('production', $payment->environment);
        $this->assertSnapUsedKey('Mid-server-prod');
    }

    public function test_client_supplied_merchant_and_clinic_are_ignored_for_booking(): void
    {
        $this->configureClinic($this->clinicA, 'MERCHANT-A', self::KEY_A);
        $this->configureClinic($this->clinicB, 'MERCHANT-B', self::KEY_B);

        $booking = $this->makeBooking($this->clinicA, ['total_amount' => 30000]);

        $this->apiAs($this->patient)->postJson('/api/payment/snap-token', [
            'transaction_details' => [
                'order_id' => 'BOOKING-' . $booking->id . '-' . (int) (microtime(true) * 1000),
                'gross_amount' => 30000,
            ],
            'merchant_id' => 'MERCHANT-B',
            'clinic_id' => $this->clinicB->id,
        ])->assertOk();

        $payment = Payment::where('payable_type', Booking::class)
            ->where('payable_id', $booking->id)->firstOrFail();

        $this->assertSame($this->clinicA->id, (int) $payment->clinic_id);
        $this->assertSame('MERCHANT-A', $payment->merchant_id);
        $this->assertSnapUsedKey(self::KEY_A);
        $this->assertNotContains($this->expectedAuth(self::KEY_B), $this->snapAuthorizations());
    }

    // ---------------------------------------------------------------- VCMS gate

    public function test_production_credential_save_requires_password_reauthentication(): void
    {
        $payload = [
            'environment' => 'production',
            'merchant_id' => 'MERCHANT-PROD',
            'client_key' => 'Mid-client-prod',
            'server_key' => 'Mid-server-prod',
        ];

        $this->actingAs($this->adminA)
            ->post('/admin/payment-settings/credentials', $payload)
            ->assertSessionHasErrors('current_password');

        $this->assertSame(0, PaymentCredentialVersion::where('merchant_id', 'MERCHANT-PROD')->count());

        $payload['current_password'] = 'password123';

        $this->actingAs($this->adminA)
            ->post('/admin/payment-settings/credentials', $payload)
            ->assertRedirect();

        $this->assertSame(1, PaymentCredentialVersion::where('merchant_id', 'MERCHANT-PROD')->count());
    }

    public function test_clinic_admin_cannot_touch_another_clinics_credential_version(): void
    {
        $versionB = $this->configureClinic($this->clinicB, 'MERCHANT-B', self::KEY_B);

        $this->actingAs($this->adminA)
            ->post('/admin/payment-settings/credentials/' . $versionB->id . '/test')
            ->assertNotFound();
    }

    public function test_credential_validation_is_localized_and_never_flashes_server_key(): void
    {
        app()->setLocale('id');

        $secret = 'SB-Mid-server-DO-NOT-ECHO';
        $payload = [
            'environment' => 'sandbox',
            'merchant_id' => 'bad id!',
            'client_key' => 'Mid-client-x',
            'server_key' => $secret,
        ];

        $this->actingAs($this->adminA)
            ->post('/admin/payment-settings/credentials', $payload)
            ->assertSessionHasErrors(['merchant_id' => __('payments.validation_merchant_format')]);

        // Server Key tidak ikut ter-flash ke session input.
        $oldInput = session()->getOldInput();
        $this->assertArrayNotHasKey('server_key', $oldInput);
        $this->assertSame('bad id!', $oldInput['merchant_id'] ?? null);

        $page = $this->actingAs($this->adminA)->get('/admin/payment-settings');
        $page->assertOk();
        // Error tampil pada panel yang dikirim saja, bukan di kedua panel.
        $page->assertSee('id="merchant_id_error_sandbox"', false);
        $page->assertDontSee('id="merchant_id_error_production"', false);
        $page->assertSee(__('payments.validation_merchant_format'));
        $page->assertDontSee($secret, false);

        $this->assertSame(0, PaymentCredentialVersion::count());
    }

    public function test_doctor_is_redirected_away_from_payment_settings(): void
    {
        $doctorUser = User::create([
            'name' => 'Dr Blocked',
            'email' => 'dr-blocked-' . Str::random(6) . '@routing.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->doctorA->forceFill(['user_id' => $doctorUser->id])->save();

        $this->actingAs($doctorUser)
            ->get('/admin/payment-settings')
            ->assertRedirect(route('doctor.dashboard'));
    }

    // ------------------------------------------------------------------- helpers

    private function makeMedicalRecord(Booking $booking, int $extraAmount): MedicalRecord
    {
        return MedicalRecord::create([
            'booking_id' => $booking->id,
            'pet_id' => $booking->pet_id,
            'doctor_id' => $booking->doctor_id,
            'clinic_id' => $booking->clinic_id,
            'diagnosis' => 'Pemeriksaan routing',
            'treatment' => 'Observasi',
            'cost' => 0,
            'treatment_cost' => 0,
            'medicine_cost' => 0,
            'total_medical_cost' => $extraAmount,
            'extra_payment_amount' => $extraAmount,
            'extra_payment_paid_amount' => 0,
            'extra_payment_status' => 'unpaid',
        ]);
    }

    private function configureClinic(Clinic $clinic, string $merchantId, string $serverKey): PaymentCredentialVersion
    {
        $service = app(PaymentConfigurationService::class);

        $version = $service->saveCredentials($clinic, [
            'environment' => 'sandbox',
            'merchant_id' => $merchantId,
            'client_key' => 'SB-Mid-client-' . $merchantId,
            'server_key' => $serverKey,
        ], $this->superAdmin);

        $service->validate($version, $this->superAdmin);
        $service->activate($version->fresh(), $this->superAdmin);

        return $version->fresh();
    }

    private function makeDoctor(Clinic $clinic, string $tag, string $suffix): Doctor
    {
        return Doctor::create([
            'name' => 'Dr Routing ' . $tag . ' ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
    }

    private function makeBooking(Clinic $clinic, array $overrides = []): Booking
    {
        $doctor = $clinic->id === $this->clinicA->id ? $this->doctorA : $this->doctorB;
        $service = Service::create([
            'name' => 'Layanan Routing ' . Str::random(5),
            'price' => 100000,
            'is_active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $pet = Pet::create([
            'user_id' => $this->patient->id,
            'name' => 'RoutingPet ' . Str::random(4),
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        return Booking::create(array_merge([
            'user_id' => $this->patient->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'clinic_id' => $clinic->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00:00',
            'status' => 'confirmed',
            'payment_method' => 'online',
            'payment_type' => 'full',
            'payment_status' => 'unpaid',
            'total_amount' => 50000,
            'dp_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
        ], $overrides));
    }

    /**
     * Payment pending dengan route terkunci untuk uji webhook.
     */
    private function createPaidReadyBookingPayment(Clinic $clinic, int $amount, ?int $bookingId = null): Payment
    {
        $booking = $bookingId
            ? Booking::findOrFail($bookingId)
            : $this->makeBooking($clinic, ['total_amount' => $amount]);

        $version = app(PaymentConfigurationService::class)->summary($clinic)['account']->currentCredentialVersion;

        return Payment::create([
            'payment_code' => Payment::generatePaymentCode(),
            'provider' => 'midtrans',
            'payment_type' => Payment::TYPE_CONSULTATION,
            'clinic_id' => $clinic->id,
            'user_id' => $this->patient->id,
            'payable_type' => Booking::class,
            'payable_id' => $booking->id,
            'provider_order_id' => 'BOOKING-' . $booking->id . '-' . (int) (microtime(true) * 1000),
            'payment_account_id' => $version->payment_account_id,
            'payment_credential_version_id' => $version->id,
            'routing_source' => ResolvedPaymentAccount::SOURCE_CLINIC,
            'merchant_id' => $version->merchant_id,
            'environment' => 'sandbox',
            'gross_amount' => $amount,
            'currency' => 'IDR',
            'status' => Payment::STATUS_PENDING,
            'provider_status' => 'pending',
            'snap_token' => 'snap-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token',
        ]);
    }

    private function payload(Payment $payment, string $serverKey, string $merchantId): array
    {
        $statusCode = '200';
        $gross = number_format((float) $payment->gross_amount, 2, '.', '');

        return [
            'order_id' => $payment->provider_order_id,
            'status_code' => $statusCode,
            'gross_amount' => $gross,
            'transaction_status' => 'settlement',
            'transaction_id' => 'tx-' . $payment->id,
            'payment_type' => 'qris',
            'merchant_id' => $merchantId,
            'signature_key' => hash('sha512', $payment->provider_order_id . $statusCode . $gross . $serverKey),
        ];
    }

    private function postWebhook(array $payload)
    {
        return $this->postJson('/api/midtrans/webhook', $payload);
    }

    private function expectedAuth(string $serverKey): string
    {
        return 'Basic ' . base64_encode($serverKey . ':');
    }

    private function snapAuthorizations(): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => str_contains((string) $pair[0]->url(), 'snap'))
            ->map(fn ($pair) => $pair[0]->header('Authorization')[0] ?? null)
            ->values()
            ->all();
    }

    private function assertSnapUsedKey(string $serverKey): void
    {
        $this->assertContains($this->expectedAuth($serverKey), $this->snapAuthorizations());
    }

    private function apiAs(User $user)
    {
        auth()->forgetGuards();
        $token = $user->createToken('routing-test')->plainTextToken;

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }
}
