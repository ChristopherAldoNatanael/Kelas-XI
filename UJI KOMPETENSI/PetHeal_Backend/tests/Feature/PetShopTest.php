<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicProduct;
use App\Models\Payment;
use App\Models\ProductCategory;
use App\Models\ProductOrder;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pet Shop end-to-end (API + admin + stok + payment gating).
 *
 * DatabaseTransactions: data uji tidak pernah tertinggal di database test.
 * Uang selalu integer rupiah; assertion memakai nilai integer, bukan float.
 */
class PetShopTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $owner;
    private User $adminA;
    private ProductCategory $category;
    private ClinicProduct $productA;
    private ProductVariant $variantA;
    private ClinicProduct $productB;
    private ProductVariant $variantB;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.partner.enabled' => false,
            'services.midtrans.environment' => 'sandbox',
            'services.midtrans.server_key' => 'petshop-test-server-key',
        ]);

        $this->suffix = strtolower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Shop A ' . $this->suffix,
            'slug' => 'shop-a-' . $this->suffix,
            'address' => 'Jl. Shop A',
            'phone' => '0811111111',
            'is_active' => true,
        ]);

        $this->clinicB = Clinic::create([
            'name' => 'Shop B ' . $this->suffix,
            'slug' => 'shop-b-' . $this->suffix,
            'address' => 'Jl. Shop B',
            'phone' => '0822222222',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Shop Owner ' . $this->suffix,
            'email' => 'shopowner' . $this->suffix . '@shop.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->adminA = User::create([
            'name' => 'Shop Admin ' . $this->suffix,
            'email' => 'shopadmin' . $this->suffix . '@shop.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Makanan ' . $this->suffix,
            'slug' => 'makanan-' . $this->suffix,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->productA = $this->makeProduct($this->clinicA, 'Royal Canin A', 50000, 10);
        $this->variantA = $this->productA->variants()->firstOrFail();

        $this->productB = $this->makeProduct($this->clinicB, 'Whiskas B', 35000, 4);
        $this->variantB = $this->productB->variants()->firstOrFail();
    }

    private function makeProduct(
        Clinic $clinic,
        string $name,
        int $price,
        int $stock,
        ?ProductCategory $category = null
    ): ClinicProduct {
        $product = ClinicProduct::create([
            'clinic_id' => $clinic->id,
            'product_category_id' => ($category ?? $this->category)->id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'description' => 'Produk uji',
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
        $token = $user->createToken('petshop-test')->plainTextToken;

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }

    private function placePayAtClinicOrder(int $quantity = 2): ProductOrder
    {
        $response = $this->apiAs($this->owner)->postJson('/api/pet-shop/orders', [
            'items' => [['variant_id' => $this->variantA->id, 'quantity' => $quantity]],
            'payment_method' => ProductOrder::METHOD_PAY_AT_CLINIC,
            // Nilai dari client ini harus DIABAIKAN server.
            'total_amount' => 1,
            'subtotal' => 1,
        ])->assertStatus(201);

        return ProductOrder::findOrFail($response->json('data.id'));
    }

    // ---------- katalog ----------

    public function test_catalog_only_lists_products_that_can_actually_be_bought(): void
    {
        $soldOut = $this->makeProduct($this->clinicA, 'Produk Habis ' . $this->suffix, 20000, 0);
        $inactive = $this->makeProduct($this->clinicA, 'Produk Nonaktif ' . $this->suffix, 20000, 5);
        $inactive->forceFill(['is_active' => false])->save();

        $response = $this->apiAs($this->owner)->getJson('/api/pet-shop/products')->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains($this->productA->name));
        $this->assertTrue($names->contains($this->productB->name));
        $this->assertFalse($names->contains($soldOut->name));
        $this->assertFalse($names->contains($inactive->name));

        $first = collect($response->json('data'))->firstWhere('id', $this->productA->id);
        $this->assertSame(50000, $first['price_min']);
        $this->assertSame(50000, $first['price_max']);
        $this->assertFalse($first['has_discount']);
        $this->assertSame(10, $first['total_available_stock']);
        $this->assertSame($this->clinicA->name, $first['clinic']['name']);
        // Partner belum aktif -> aplikasi hanya boleh menawarkan bayar di klinik.
        $this->assertFalse($first['clinic']['online_payment_available']);

        // Filter klinik & kategori memakai query server, bukan penyaringan lokal.
        $clinicFiltered = $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/products?clinic_id=' . $this->clinicB->id)
            ->assertOk();
        $this->assertSame(
            [$this->productB->name],
            collect($clinicFiltered->json('data'))->pluck('name')->all()
        );

        $categoryFiltered = $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/products?category=' . $this->category->slug)
            ->assertOk();
        $this->assertCount(2, $categoryFiltered->json('data'));
    }

    public function test_product_detail_returns_variants_and_no_inactive_variant(): void
    {
        $this->variantA->forceFill(['stock_on_hand' => 3])->save();

        $response = $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/products/' . $this->productA->id)
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame($this->productA->name, $data['name']);
        $this->assertCount(1, $data['variants']);
        $this->assertSame($this->variantA->id, $data['variants'][0]['id']);
        $this->assertSame(50000, $data['variants'][0]['effective_price']);
        $this->assertSame(3, $data['variants'][0]['available_stock']);
        $this->assertTrue($data['variants'][0]['is_available']);
    }

    // ---------- pesanan & stok ----------

    public function test_order_uses_server_prices_and_reserves_stock(): void
    {
        $order = $this->placePayAtClinicOrder(quantity: 2);

        $this->assertSame($this->owner->id, $order->user_id);
        $this->assertSame($this->clinicA->id, $order->clinic_id);
        $this->assertSame(ProductOrder::STATUS_AWAITING_CONFIRMATION, $order->status);
        $this->assertSame(100000, (int) $order->total_amount);
        $this->assertSame(100000, (int) $order->subtotal);
        $this->assertSame(0, (int) $order->discount_total);
        $this->assertNotNull($order->stock_reserved_at);
        $this->assertNull($order->stock_committed_at);

        $variant = $this->variantA->fresh();
        $this->assertSame(2, (int) $variant->stock_reserved);
        $this->assertSame(10, (int) $variant->stock_on_hand);
        $this->assertSame(8, $variant->availableStock());

        $this->assertDatabaseHas('product_stock_movements', [
            'product_variant_id' => $this->variantA->id,
            'type' => ProductStockMovement::TYPE_RESERVATION,
            'quantity' => 2,
            'stock_after' => 10,
            'reserved_after' => 2,
            'reference_id' => $order->id,
        ]);

        // Pengguna lain tidak boleh melihat pesanan ini.
        $other = User::create([
            'name' => 'Other ' . $this->suffix,
            'email' => 'other' . $this->suffix . '@shop.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinicA->id,
        ]);
        $this->apiAs($other)
            ->getJson('/api/pet-shop/orders/' . $order->id)
            ->assertStatus(404);

        $this->apiAs($this->owner)
            ->getJson('/api/pet-shop/orders/' . $order->id)
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.can_cancel', true);
    }

    public function test_order_rejects_quantity_beyond_available_stock(): void
    {
        $this->apiAs($this->owner)->postJson('/api/pet-shop/orders', [
            'items' => [['variant_id' => $this->variantA->id, 'quantity' => 11]],
            'payment_method' => ProductOrder::METHOD_PAY_AT_CLINIC,
        ])->assertStatus(422)->assertJsonPath('code', 'insufficient_stock');

        $this->assertSame(0, ProductOrder::where('user_id', $this->owner->id)->count());
        $this->assertSame(0, (int) $this->variantA->fresh()->stock_reserved);
    }

    public function test_order_cannot_mix_products_from_different_clinics(): void
    {
        $this->apiAs($this->owner)->postJson('/api/pet-shop/orders', [
            'items' => [
                ['variant_id' => $this->variantA->id, 'quantity' => 1],
                ['variant_id' => $this->variantB->id, 'quantity' => 1],
            ],
            'payment_method' => ProductOrder::METHOD_PAY_AT_CLINIC,
        ])->assertStatus(422);

        $this->assertSame(0, ProductOrder::where('user_id', $this->owner->id)->count());
    }

    public function test_cancelling_order_releases_reserved_stock_once(): void
    {
        $order = $this->placePayAtClinicOrder(quantity: 2);
        $this->assertSame(2, (int) $this->variantA->fresh()->stock_reserved);

        $this->apiAs($this->owner)
            ->postJson('/api/pet-shop/orders/' . $order->id . '/cancel', ['reason' => 'Berubah pikiran'])
            ->assertOk()
            ->assertJsonPath('data.status', ProductOrder::STATUS_CANCELLED);

        $variant = $this->variantA->fresh();
        $this->assertSame(0, (int) $variant->stock_reserved);
        $this->assertSame(10, (int) $variant->stock_on_hand);
        $this->assertNotNull($order->fresh()->stock_released_at);

        // Pembatalan kedua tidak boleh melepas stok dua kali.
        $this->apiAs($this->owner)
            ->postJson('/api/pet-shop/orders/' . $order->id . '/cancel', [])
            ->assertStatus(422);

        $this->assertSame(0, (int) $this->variantA->fresh()->stock_reserved);
    }

    public function test_completing_order_commits_stock_and_marks_cash_paid(): void
    {
        $order = $this->placePayAtClinicOrder(quantity: 2);

        foreach (['accepted', 'preparing', 'ready_for_pickup', 'completed'] as $status) {
            $this->actingAs($this->adminA)
                ->post('/admin/pet-shop/orders/' . $order->id . '/transition', ['status' => $status])
                ->assertRedirect();
        }

        $order = $order->fresh();
        $this->assertSame(ProductOrder::STATUS_COMPLETED, $order->status);
        $this->assertSame(ProductOrder::PAYMENT_PAID, $order->payment_status);
        $this->assertNotNull($order->stock_committed_at);
        $this->assertSame(8, (int) $this->variantA->fresh()->stock_on_hand);
        $this->assertSame(0, (int) $this->variantA->fresh()->stock_reserved);

        $this->assertDatabaseHas('product_stock_movements', [
            'product_variant_id' => $this->variantA->id,
            'type' => ProductStockMovement::TYPE_SALE,
            'quantity' => -2,
            'stock_after' => 8,
            'reserved_after' => 0,
        ]);

        // Pembayaran tunai tercatat sekali dan bertaut ke order yang sama.
        $this->assertSame(1, Payment::where('payable_type', ProductOrder::class)
            ->where('payable_id', $order->id)
            ->where('provider', 'cash')
            ->count());
    }

    // ---------- payment gating ----------

    public function test_online_order_is_rejected_and_released_when_partner_is_not_ready(): void
    {
        config(['services.midtrans.partner.enabled' => false]);

        $this->apiAs($this->owner)->postJson('/api/pet-shop/orders', [
            'items' => [['variant_id' => $this->variantA->id, 'quantity' => 2]],
            'payment_method' => ProductOrder::METHOD_ONLINE,
        ])->assertStatus(422)->assertJsonPath('code', 'online_payment_unavailable');

        $order = ProductOrder::where('user_id', $this->owner->id)->firstOrFail();
        $this->assertSame(ProductOrder::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->stock_released_at);
        $this->assertSame(0, (int) $this->variantA->fresh()->stock_reserved);
        $this->assertSame(0, Payment::where('payable_type', ProductOrder::class)
            ->where('payable_id', $order->id)
            ->count());
    }

    public function test_pet_shop_webhook_marks_payment_and_order_paid(): void
    {
        $order = $this->placePayAtClinicOrder(quantity: 2);
        $providerOrderId = 'SHOP-' . $order->order_number;

        $payment = Payment::create([
            'payment_code' => Payment::generatePaymentCode(),
            'provider' => 'midtrans',
            'payment_type' => Payment::TYPE_PET_SHOP,
            'clinic_id' => $order->clinic_id,
            'user_id' => $order->user_id,
            'payable_type' => ProductOrder::class,
            'payable_id' => $order->id,
            'provider_order_id' => $providerOrderId,
            'environment' => 'sandbox',
            'gross_amount' => (int) $order->total_amount,
            'currency' => 'IDR',
            'status' => Payment::STATUS_PENDING,
        ]);

        $statusCode = '200';
        $gross = '100000.00';
        $signature = hash(
            'sha512',
            $providerOrderId . $statusCode . $gross . config('services.midtrans.server_key')
        );

        $this->postJson('/api/midtrans/webhook', [
            'order_id' => $providerOrderId,
            'status_code' => $statusCode,
            'gross_amount' => $gross,
            'transaction_status' => 'settlement',
            'transaction_id' => 'trx-' . $this->suffix,
            'payment_type' => 'qris',
            'signature_key' => $signature,
        ])->assertOk()->assertJsonPath('status', 'ok');

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(ProductOrder::PAYMENT_PAID, $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_notifications', [
            'provider_order_id' => $providerOrderId,
            'signature_valid' => true,
            'processed' => true,
        ]);

        // Notifikasi duplikat tidak mengubah apa pun dan tidak membuat baris ganda.
        $this->postJson('/api/midtrans/webhook', [
            'order_id' => $providerOrderId,
            'status_code' => $statusCode,
            'gross_amount' => $gross,
            'transaction_status' => 'settlement',
            'transaction_id' => 'trx-' . $this->suffix,
            'signature_key' => $signature,
        ])->assertOk();

        $this->assertSame(1, Payment::where('payable_type', ProductOrder::class)
            ->where('payable_id', $order->id)
            ->count());
    }

    public function test_webhook_with_wrong_signature_is_rejected(): void
    {
        $order = $this->placePayAtClinicOrder(quantity: 1);

        $this->postJson('/api/midtrans/webhook', [
            'order_id' => 'SHOP-' . $order->order_number,
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'transaction_status' => 'settlement',
            'signature_key' => str_repeat('0', 128),
        ])->assertStatus(403);

        $this->assertSame(ProductOrder::PAYMENT_UNPAID, $order->fresh()->payment_status);
    }

    // ---------- isolasi tenant admin ----------

    public function test_clinic_admin_cannot_open_another_clinics_order(): void
    {
        $order = $this->placePayAtClinicOrder(quantity: 1);

        $this->actingAs($this->adminA)
            ->get('/admin/pet-shop/orders/' . $order->id)
            ->assertOk();

        $adminB = User::create([
            'name' => 'Admin B ' . $this->suffix,
            'email' => 'shopadminb' . $this->suffix . '@shop.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinicB->id,
        ]);

        auth()->logout();
        $this->actingAs($adminB)
            ->get('/admin/pet-shop/orders/' . $order->id)
            ->assertStatus(404);
    }

    public function test_admin_cannot_transition_another_clinics_order(): void
    {
        $order = $this->placePayAtClinicOrder(quantity: 1);

        $adminB = User::create([
            'name' => 'Admin B2 ' . $this->suffix,
            'email' => 'shopadminb2' . $this->suffix . '@shop.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinicB->id,
        ]);

        $this->actingAs($adminB)
            ->post('/admin/pet-shop/orders/' . $order->id . '/transition', ['status' => 'accepted'])
            ->assertStatus(404);

        $this->assertSame(ProductOrder::STATUS_AWAITING_CONFIRMATION, $order->fresh()->status);
    }
}
