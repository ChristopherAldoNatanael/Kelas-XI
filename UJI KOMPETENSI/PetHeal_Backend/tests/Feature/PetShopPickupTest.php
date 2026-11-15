<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicProduct;
use App\Models\ProductCategory;
use App\Models\ProductOrder;
use App\Models\ProductPickupHandover;
use App\Models\ProductPickupPass;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PickupPassService;
use App\Services\ProductOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Click & Collect end-to-end: klinik terdekat, bukti pembayaran, QR pickup,
 * pembayaran offline, dan isolasi antar klinik.
 */
class PetShopPickupTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private Clinic $clinicWithoutCoords;
    private User $owner;
    private User $otherUser;
    private User $adminA;
    private ProductCategory $category;
    private ClinicProduct $productA;
    private ProductVariant $variantA;
    private ClinicProduct $productB;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.partner.enabled' => false,
            'services.midtrans.environment' => 'sandbox',
            'services.midtrans.server_key' => 'petshop-pickup-test-key',
            'petshop.pickup_pass_days' => 14,
        ]);

        $this->suffix = strtolower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Pickup A ' . $this->suffix,
            'slug' => 'pickup-a-' . $this->suffix,
            'address' => 'Jl. A',
            'city' => 'Surabaya',
            'district' => 'Tegalsari',
            'phone' => '0811111111',
            'is_active' => true,
            'pickup_enabled' => true,
            'latitude' => -7.2500000,
            'longitude' => 112.7500000,
        ]);

        $this->clinicB = Clinic::create([
            'name' => 'Pickup B ' . $this->suffix,
            'slug' => 'pickup-b-' . $this->suffix,
            'address' => 'Jl. B',
            'city' => 'Sidoarjo',
            'district' => 'Waru',
            'phone' => '0822222222',
            'is_active' => true,
            'pickup_enabled' => true,
            'latitude' => -7.4000000,
            'longitude' => 112.9000000,
        ]);

        $this->clinicWithoutCoords = Clinic::create([
            'name' => 'Pickup Tanpa Koordinat ' . $this->suffix,
            'slug' => 'pickup-c-' . $this->suffix,
            'address' => 'Jl. C',
            'city' => 'Gresik',
            'district' => 'Kebomas',
            'phone' => '0833333333',
            'is_active' => true,
            'pickup_enabled' => true,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->owner = User::create([
            'name' => 'Pemilik ' . $this->suffix,
            'email' => 'owner' . $this->suffix . '@pickup.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->otherUser = User::create([
            'name' => 'Pengguna Lain ' . $this->suffix,
            'email' => 'other' . $this->suffix . '@pickup.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->adminA = User::create([
            'name' => 'Admin A ' . $this->suffix,
            'email' => 'admina' . $this->suffix . '@pickup.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Makanan ' . $this->suffix,
            'slug' => 'makanan-' . $this->suffix,
            'is_active' => true,
        ]);

        $sharedName = 'Produk Pickup ' . $this->suffix;

        $this->productA = $this->makeProduct($this->clinicA, $sharedName, 50000, 10);
        $this->variantA = $this->productA->variants()->firstOrFail();
        $this->productB = $this->makeProduct($this->clinicB, $sharedName, 45000, 5);
    }

    private function makeProduct(Clinic $clinic, string $name, int $price, int $stock): ClinicProduct
    {
        $product = ClinicProduct::create([
            'clinic_id' => $clinic->id,
            'product_category_id' => $this->category->id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'description' => 'Produk uji pickup',
            'is_active' => true,
        ]);

        $product->variants()->create([
            'sku' => 'SKU-' . strtoupper(Str::random(8)),
            'name' => 'Default',
            'price' => $price,
            'stock_on_hand' => $stock,
            'stock_reserved' => 0,
            'is_active' => true,
        ]);

        return $product->fresh('variants');
    }

    private function apiAs(User $user)
    {
        auth()->forgetGuards();

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->createToken('pickup-test')->plainTextToken,
            'Accept' => 'application/json',
        ]);
    }

    private function orderAtClinicA(int $quantity = 1): ProductOrder
    {
        $response = $this->apiAs($this->owner)->postJson('/api/pet-shop/orders', [
            'items' => [['variant_id' => $this->variantA->id, 'quantity' => $quantity]],
            'payment_method' => ProductOrder::METHOD_PAY_AT_CLINIC,
        ])->assertStatus(201);

        return ProductOrder::findOrFail($response->json('data.id'));
    }

    private function advanceToReady(ProductOrder $order): ProductOrder
    {
        $service = app(ProductOrderService::class);

        foreach ([
            ProductOrder::STATUS_ACCEPTED,
            ProductOrder::STATUS_PREPARING,
            ProductOrder::STATUS_READY_FOR_PICKUP,
        ] as $status) {
            $service->transition($order, $status, 'clinic_admin', $this->adminA);
            $order = $order->fresh();
        }

        return $order;
    }

    // ---------- klinik terdekat ----------

    public function test_nearby_clinics_are_sorted_by_distance_and_include_clinic_without_coordinates(): void
    {
        $response = $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/clinics/nearby?latitude=-7.2500000&longitude=112.7500000')
            ->assertOk();

        $clinics = collect($response->json('data'));
        $mine = $clinics->whereIn('id', [$this->clinicA->id, $this->clinicB->id, $this->clinicWithoutCoords->id])->values();

        $this->assertSame($this->clinicA->id, $mine[0]['id'], 'Klinik terdekat harus di urutan pertama.');
        $this->assertSame(0.0, (float) $mine[0]['distance_km']);
        $this->assertSame('<10 m', $mine[0]['distance_label']);

        $this->assertGreaterThan($mine[0]['distance_km'], $mine[1]['distance_km']);

        // Klinik tanpa koordinat tetap bisa ditemukan, jaraknya null.
        $withoutCoords = $clinics->firstWhere('id', $this->clinicWithoutCoords->id);
        $this->assertNotNull($withoutCoords);
        $this->assertNull($withoutCoords['distance_km']);
        $this->assertNull($withoutCoords['distance_label']);
    }

    public function test_nearby_without_coordinates_supports_manual_city_search(): void
    {
        $response = $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/clinics/nearby?city=Gresik')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($this->clinicWithoutCoords->id, $ids);
        $this->assertNotContains($this->clinicA->id, $ids);
        $this->assertFalse($response->json('meta.distance_available'));
    }

    public function test_partial_or_invalid_coordinates_are_rejected(): void
    {
        $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/clinics/nearby?latitude=-7.25')
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_coordinates');

        $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/clinics/nearby?latitude=999&longitude=112')
            ->assertStatus(422);
    }

    public function test_product_detail_lists_pickup_clinics_with_their_own_price_and_stock(): void
    {
        $response = $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/products/' . $this->productA->id . '?latitude=-7.25&longitude=112.75')
            ->assertOk();

        $clinics = collect($response->json('data.pickup_clinics'));

        $this->assertCount(2, $clinics);

        $clinicBEntry = $clinics->firstWhere('id', $this->clinicB->id);
        $this->assertNotNull($clinicBEntry);
        $this->assertSame(45000, $clinicBEntry['product']['price_min']);
        $this->assertSame(5, $clinicBEntry['product']['variants'][0]['available_stock']);
        $this->assertNotNull($clinicBEntry['distance_label']);
    }

    // ---------- bukti pembayaran ----------

    public function test_receipt_uses_snapshot_and_is_not_paid_before_settlement(): void
    {
        $order = $this->orderAtClinicA(2);

        $response = $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/orders/' . $order->id . '/receipt')
            ->assertOk();

        $this->assertFalse($response->json('data.is_settled'));
        $this->assertSame('pending', $response->json('data.payment.receipt_status'));
        $this->assertSame(100000, $response->json('data.totals.total_amount'));
        $this->assertSame(0, $response->json('data.totals.paid_amount'));

        // PDF lunas tidak boleh terbit untuk pembayaran yang belum diterima.
        $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/orders/' . $order->id . '/receipt/pdf')
            ->assertStatus(422)
            ->assertJsonPath('code', 'payment_not_settled');

        // Ubah nama produk di katalog: receipt historis tidak berubah.
        $this->productA->forceFill(['name' => 'Nama Baru Setelah Transaksi'])->save();

        $again = $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/orders/' . $order->id . '/receipt')
            ->assertOk();

        $this->assertSame(
            'Produk Pickup ' . $this->suffix,
            $again->json('data.items.0.product_name')
        );
    }

    public function test_receipt_pdf_is_available_after_offline_payment_recorded(): void
    {
        $order = $this->orderAtClinicA(1);

        app(PaymentService::class)->recordOfflinePayment(
            $order,
            (int) $order->total_amount,
            'cash',
            'STRUK-01',
            $this->adminA
        );

        $this->assertSame(ProductOrder::PAYMENT_PAID, $order->fresh()->payment_status);

        $response = $this->apiAs($this->owner)
            ->get('/api/pet-shop/orders/' . $order->id . '/receipt/pdf');

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'PetHeal-Receipt-' . $order->order_number . '.pdf',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_receipt_and_pickup_pass_are_not_accessible_to_other_users(): void
    {
        $order = $this->orderAtClinicA(1);
        $this->advanceToReady($order);

        $this->apiAs($this->otherUser)
            ->getJson('/api/pet-shop/orders/' . $order->id . '/receipt')
            ->assertStatus(404);

        $this->apiAs($this->otherUser)
            ->getJson('/api/pet-shop/orders/' . $order->id . '/pickup-pass')
            ->assertStatus(404);
    }

    // ---------- QR pickup pass ----------

    public function test_pickup_pass_is_only_issued_when_order_is_ready(): void
    {
        $order = $this->orderAtClinicA(1);

        $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/orders/' . $order->id . '/pickup-pass')
            ->assertStatus(422)
            ->assertJsonPath('code', 'pickup_not_ready');

        $this->assertNull($order->fresh()->pickupPass);

        $order = $this->advanceToReady($order);

        $pass = $order->pickupPass;
        $this->assertNotNull($pass);
        $this->assertTrue($pass->isUsable());
        $this->assertGreaterThanOrEqual(8, strlen($pass->manual_code));
        $this->assertNotSame($order->order_number, $pass->token);

        $response = $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/orders/' . $order->id . '/pickup-pass')
            ->assertOk();

        $this->assertSame($pass->token, $response->json('data.qr_payload'));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $response->json('data.manual_code'));

        // Gambar QR benar-benar PNG.
        $qr = $this->apiAs($this->owner)->get('/api/pet-shop/orders/' . $order->id . '/pickup-pass/qr');
        $qr->assertOk();
        $this->assertSame('image/png', $qr->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $qr->getContent());
    }

    public function test_pickup_pass_cannot_be_used_by_another_clinic_or_twice(): void
    {
        $order = $this->advanceToReady($this->orderAtClinicA(1));
        $pass = $order->pickupPass;

        $service = app(PickupPassService::class);

        try {
            $service->verify($pass->manual_code, $this->clinicB->id);
            $this->fail('Pass klinik lain seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tidak ditemukan', $e->validator->errors()->first('code'));
        }

        $verified = $service->verify($pass->manual_code, $this->clinicA->id);
        $this->assertSame($order->id, $verified['order']->id);

        app(PaymentService::class)->recordOfflinePayment(
            $order,
            (int) $order->total_amount,
            'cash',
            null,
            $this->adminA
        );

        $service->confirmHandover($order->fresh(), $this->adminA, 'manual', $pass);

        $pass->refresh();
        $this->assertFalse($pass->is_active);
        $this->assertNotNull($pass->used_at);
        $this->assertSame($this->adminA->id, $pass->used_by);

        try {
            $service->verify($pass->manual_code, $this->clinicA->id);
            $this->fail('Pass yang sudah dipakai harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('sudah', $e->validator->errors()->first('code'));
        }
    }

    public function test_handover_requires_settled_payment_and_completes_order_once(): void
    {
        $order = $this->advanceToReady($this->orderAtClinicA(2));
        $pass = $order->pickupPass;
        $service = app(PickupPassService::class);

        try {
            $service->confirmHandover($order, $this->adminA, 'manual', $pass);
            $this->fail('Penyerahan tanpa pembayaran harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Pembayaran belum lunas', $e->validator->errors()->first('payment'));
        }

        app(PaymentService::class)->recordOfflinePayment(
            $order,
            (int) $order->total_amount,
            'qris',
            'REF-1',
            $this->adminA
        );

        $stockBefore = $this->variantA->fresh()->stock_on_hand;

        $first = $service->confirmHandover($order->fresh(), $this->adminA, 'qr', $pass);
        $this->assertFalse($first['already_completed']);

        $completed = $order->fresh();
        $this->assertSame(ProductOrder::STATUS_COMPLETED, $completed->status);
        $this->assertNotNull($completed->picked_up_at);
        $this->assertSame($this->adminA->id, $completed->picked_up_by);
        $this->assertSame($stockBefore - 2, $this->variantA->fresh()->stock_on_hand);
        $this->assertSame(1, ProductPickupHandover::where('product_order_id', $order->id)->count());

        // Dua petugas menekan konfirmasi: yang kedua idempoten, bukan penyerahan baru.
        $second = $service->confirmHandover($order->fresh(), $this->adminA, 'manual');
        $this->assertTrue($second['already_completed']);
        $this->assertSame(1, ProductPickupHandover::where('product_order_id', $order->id)->count());
        $this->assertSame($stockBefore - 2, $this->variantA->fresh()->stock_on_hand);
    }

    // ---------- pembayaran offline ----------

    public function test_offline_payment_amount_must_match_outstanding_total(): void
    {
        $order = $this->orderAtClinicA(1);

        try {
            app(PaymentService::class)->recordOfflinePayment(
                $order,
                (int) $order->total_amount - 1,
                'cash',
                null,
                $this->adminA
            );
            $this->fail('Nominal kurang harus ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sisa tagihan', $e->getMessage());
        }

        $this->assertSame(ProductOrder::PAYMENT_UNPAID, $order->fresh()->payment_status);

        app(PaymentService::class)->recordOfflinePayment(
            $order,
            (int) $order->total_amount,
            'bank_transfer',
            'TRX-9',
            $this->adminA
        );

        $this->assertSame(ProductOrder::PAYMENT_PAID, $order->fresh()->payment_status);
    }

    public function test_vcms_pickup_page_and_history_are_scoped_to_the_admin_clinic(): void
    {
        $order = $this->advanceToReady($this->orderAtClinicA(1));

        $this->actingAs($this->adminA)
            ->get('/admin/pet-shop/pickups')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(substr($order->pickupPass->manual_code, 0, 4) . '-' . substr($order->pickupPass->manual_code, 4));

        $this->actingAs($this->adminA)
            ->get('/admin/pet-shop/pickups/history')
            ->assertOk();
    }

    /**
     * Halaman verifikasi harus memprioritaskan pemindaian kamera; kode
     * manual hanya jalur cadangan (dan tetap bisa dipakai).
     */
    public function test_pickup_page_prioritises_camera_scan_with_manual_code_as_fallback(): void
    {
        $order = $this->advanceToReady($this->orderAtClinicA(1));
        $manualCode = substr($order->pickupPass->manual_code, 0, 4) . '-' . substr($order->pickupPass->manual_code, 4);

        $response = $this->actingAs($this->adminA)->get('/admin/pet-shop/pickups');

        $response->assertOk()
            // Panel kamera ada, kamera dinyalakan otomatis, dan bingkai
            // bidik plus tombol senter tersedia.
            ->assertSee('data-scan-root', false)
            ->assertSee('data-scan-video', false)
            ->assertSee('data-scan-frame', false)
            ->assertSee('data-scan-torch', false)
            ->assertSee('data-scan-stop', false)
            ->assertSee('data-scan-strings', false)
            // Jalur kode manual tetap ada sebagai cadangan, termasuk
            // unggah foto QR.
            ->assertSee('data-scan-fallback-toggle', false)
            ->assertSee('pickup_code', false)
            ->assertSee('data-scan-file', false)
            // Pesan fallback untuk kondisi kamera tidak bisa dipakai.
            ->assertSee(__('petshop.pickup_scan_unsupported'))
            ->assertSee(__('petshop.pickup_scan_denied'))
            ->assertSee(__('petshop.pickup_scan_notfound'))
            ->assertSee(__('petshop.pickup_fallback_toggle'));

        // Modul pemindai harus punya mesin decode cadangan (jsQR) supaya
        // kamera tetap bisa dipakai di browser yang tidak punya
        // BarcodeDetector (mis. Chrome di PC).
        $scanner = file_get_contents(resource_path('js/pickup-scanner.js'));
        $this->assertStringContainsString("import('jsqr')", $scanner);
        $this->assertStringContainsString('startCamera()', $scanner);
        $this->assertStringContainsString('navigator.mediaDevices.getUserMedia', $scanner);

        // Verifikasi lewat kode manual tetap mengisi panel hasil.
        $this->actingAs($this->adminA)
            ->get('/admin/pet-shop/pickups?code=' . $manualCode . '&via=manual')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(__('petshop.pickup_verified_via_manual'));
    }

    /**
     * Kode yang dipindai (via=qr) dicatat berbeda dari kode ketik manual
     * pada jejak serah terima.
     */
    public function test_verification_method_is_recorded_from_the_scan_channel(): void
    {
        $order = $this->advanceToReady($this->orderAtClinicA(1));
        $manualCode = substr($order->pickupPass->manual_code, 0, 4) . '-' . substr($order->pickupPass->manual_code, 4);

        $this->actingAs($this->adminA)
            ->get('/admin/pet-shop/pickups?code=' . $manualCode . '&via=qr')
            ->assertOk()
            ->assertSee(__('petshop.pickup_verified_via_qr'))
            ->assertSee('name="method" value="qr"', false);
    }
}
