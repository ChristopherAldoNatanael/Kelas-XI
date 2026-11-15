<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicProduct;
use App\Models\Payment;
use App\Models\ProductCategory;
use App\Models\ProductOrder;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ResolvedPaymentAccount;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sinkronisasi pembayaran Pet Shop dengan Midtrans.
 *
 * Dipakai aplikasi setelah user selesai membayar di halaman Snap (in-app
 * WebView): pesanan harus langsung berstatus "paid" tanpa menunggu webhook.
 */
class PetShopPaymentSyncTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinic;
    private User $owner;
    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.environment' => 'sandbox',
            'services.midtrans.central.sandbox.server_key' => 'petshop-sync-test-server-key',
            'services.midtrans.central.sandbox.client_key' => 'petshop-sync-test-client-key',
            'services.midtrans.central.sandbox.merchant_id' => 'petshop-sync-test-merchant',
        ]);

        $this->suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'Sync Shop ' . $this->suffix,
            'slug' => 'sync-shop-' . $this->suffix,
            'address' => 'Jl. Sync Shop',
            'phone' => '0813333333',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Sync Owner ' . $this->suffix,
            'email' => 'syncowner' . $this->suffix . '@shop.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinic->id,
        ]);

        $category = ProductCategory::create([
            'name' => 'Sync Kategori ' . $this->suffix,
            'slug' => 'sync-kategori-' . $this->suffix,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = ClinicProduct::create([
            'clinic_id' => $this->clinic->id,
            'product_category_id' => $category->id,
            'name' => 'Produk Sync ' . $this->suffix,
            'slug' => 'produk-sync-' . $this->suffix,
            'description' => 'Produk uji sinkronisasi',
            'is_active' => true,
        ]);

        $this->variant = $product->variants()->create([
            'sku' => 'SKU-SYNC-' . strtoupper(Str::random(6)),
            'name' => 'Default',
            'price' => 50000,
            'stock_on_hand' => 10,
            'stock_reserved' => 0,
            'is_active' => true,
        ]);
    }

    private function apiAs(User $user)
    {
        auth()->forgetGuards();
        $token = $user->createToken('petshop-sync-test')->plainTextToken;

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }

    private function placeOrder(): ProductOrder
    {
        $response = $this->apiAs($this->owner)->postJson('/api/pet-shop/orders', [
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]],
            'payment_method' => ProductOrder::METHOD_PAY_AT_CLINIC,
        ])->assertStatus(201);

        return ProductOrder::findOrFail($response->json('data.id'));
    }

    private function createPendingPayment(ProductOrder $order): Payment
    {
        return Payment::create([
            'payment_code' => Payment::generatePaymentCode(),
            'provider' => 'midtrans',
            'payment_type' => Payment::TYPE_PET_SHOP,
            'clinic_id' => $order->clinic_id,
            'user_id' => $order->user_id,
            'payable_type' => ProductOrder::class,
            'payable_id' => $order->id,
            'provider_order_id' => 'SHOP-' . $order->order_number,
            'routing_source' => ResolvedPaymentAccount::SOURCE_CENTRAL_FALLBACK,
            'environment' => 'sandbox',
            'gross_amount' => (int) $order->total_amount,
            'currency' => 'IDR',
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    public function test_sync_applies_provider_status_so_order_becomes_paid(): void
    {
        $order = $this->placeOrder();
        $payment = $this->createPendingPayment($order);

        $order->forceFill(['payment_status' => ProductOrder::PAYMENT_PENDING])->save();

        Http::fake([
            'api.sandbox.midtrans.com/*' => Http::response([
                'order_id' => $payment->provider_order_id,
                'transaction_status' => 'settlement',
                'transaction_id' => 'trx-sync-' . $this->suffix,
                'payment_type' => 'qris',
                'gross_amount' => (string) $order->total_amount,
                'fraud_status' => 'accept',
            ]),
        ]);

        $this->apiAs($this->owner)
            ->postJson('/api/pet-shop/orders/' . $order->id . '/payment/sync')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', ProductOrder::PAYMENT_PAID)
            ->assertJsonPath('data.payment.status', Payment::STATUS_PAID);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(ProductOrder::PAYMENT_PAID, $order->fresh()->payment_status);
        // Pesanan otomatis lanjut ke konfirmasi klinik setelah lunas.
        $this->assertSame(ProductOrder::STATUS_AWAITING_CONFIRMATION, $order->fresh()->status);
    }

    public function test_sync_keeps_order_unpaid_when_provider_still_pending(): void
    {
        $order = $this->placeOrder();
        $payment = $this->createPendingPayment($order);

        $order->forceFill(['payment_status' => ProductOrder::PAYMENT_PENDING])->save();

        Http::fake([
            'api.sandbox.midtrans.com/*' => Http::response([
                'order_id' => $payment->provider_order_id,
                'transaction_status' => 'pending',
                'payment_type' => 'bank_transfer',
                'gross_amount' => (string) $order->total_amount,
            ]),
        ]);

        $this->apiAs($this->owner)
            ->postJson('/api/pet-shop/orders/' . $order->id . '/payment/sync')
            ->assertOk()
            ->assertJsonPath('data.payment_status', ProductOrder::PAYMENT_PENDING);

        $this->assertNotSame(ProductOrder::PAYMENT_PAID, $order->fresh()->payment_status);
    }

    public function test_sync_rejects_order_owned_by_another_user(): void
    {
        $order = $this->placeOrder();
        $this->createPendingPayment($order);

        $other = User::create([
            'name' => 'Other ' . $this->suffix,
            'email' => 'other' . $this->suffix . '@shop.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinic->id,
        ]);

        Http::fake([
            'api.sandbox.midtrans.com/*' => Http::response(['transaction_status' => 'settlement']),
        ]);

        $this->apiAs($other)
            ->postJson('/api/pet-shop/orders/' . $order->id . '/payment/sync')
            ->assertStatus(404);
    }

    public function test_sync_reports_error_when_provider_is_unreachable(): void
    {
        $order = $this->placeOrder();
        $this->createPendingPayment($order);

        Http::fake([
            'api.sandbox.midtrans.com/*' => Http::response(['status_message' => 'not found'], 404),
        ]);

        $this->apiAs($this->owner)
            ->postJson('/api/pet-shop/orders/' . $order->id . '/payment/sync')
            ->assertStatus(502)
            ->assertJsonPath('success', false);

        $this->assertNotSame(ProductOrder::PAYMENT_PAID, $order->fresh()->payment_status);
    }
}
