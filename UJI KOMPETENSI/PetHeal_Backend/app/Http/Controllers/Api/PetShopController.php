<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicProduct;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Services\MerchantConfigurationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Katalog Pet Shop (read-only untuk pasien).
 *
 * Semua produk membawa identitas klinik penjual. Produk tanpa varian aktif
 * yang masih tersedia tidak pernah muncul di daftar.
 */
class PetShopController extends Controller
{
    /** @var array<int, bool> */
    private array $onlineAvailability = [];

    public function __construct(private readonly MerchantConfigurationService $merchants) {}

    public function categories()
    {
        $categories = ProductCategory::active()
            ->ordered()
            ->withCount(['products as products_count' => function (Builder $query) {
                $query->active()->purchasable();
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories->map(fn (ProductCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'products_count' => (int) $category->products_count,
            ])->values(),
        ]);
    }

    public function sellers()
    {
        $sellers = \App\Models\Clinic::active()
            ->whereHas('products', fn (Builder $query) => $query->active()->purchasable())
            ->withCount(['products as products_count' => function (Builder $query) {
                $query->active()->purchasable();
            }])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sellers->map(fn ($clinic) => [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'slug' => $clinic->slug,
                'address' => $clinic->address,
                'phone' => $clinic->phone,
                'logo_url' => $clinic->logo_url,
                'products_count' => (int) $clinic->products_count,
            ])->values(),
        ]);
    }

    public function products(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:140',
            'clinic_id' => 'nullable|integer|min:1',
            'clinic_slug' => 'nullable|string|max:140',
            'sort' => 'nullable|in:newest,popular,price_asc,price_desc,name',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = ClinicProduct::query()
            ->with(['category:id,name,slug', 'clinic:id,name,slug,address,phone,logo_path,primary_color', 'variants' => function ($variants) {
                $variants->active()->orderBy('price');
            }])
            ->withSum(['orderItems as sold_count' => function (Builder $items) {
                $items->whereHas('order', fn (Builder $order) => $order->where('status', \App\Models\ProductOrder::STATUS_COMPLETED));
            }], 'quantity')
            ->whereHas('clinic', fn (Builder $clinic) => $clinic->where('is_active', true))
            ->whereHas('variants', fn (Builder $variant) => $variant->active()->whereRaw('stock_on_hand > stock_reserved'))
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true));

        // Produk aktif = belum diarsipkan (soft delete) dan ditandai aktif.
        $query->active();

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('name', 'like', '%' . $search . '%')
                    ->orWhere('brand', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if (!empty($validated['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('slug', $validated['category']));
        }

        if (!empty($validated['clinic_id'])) {
            $query->where('clinic_id', (int) $validated['clinic_id']);
        }

        if (!empty($validated['clinic_slug'])) {
            $query->whereHas('clinic', fn (Builder $clinic) => $clinic->where('slug', $validated['clinic_slug']));
        }

        switch ($validated['sort'] ?? 'newest') {
            case 'popular':
                $query->orderByDesc('sold_count')->orderByDesc('id');
                break;
            case 'price_asc':
                $query->orderByRaw('(select min(coalesce(discount_price, price)) from product_variants where product_variants.clinic_product_id = clinic_products.id and product_variants.is_active = 1) asc');
                break;
            case 'price_desc':
                $query->orderByRaw('(select min(coalesce(discount_price, price)) from product_variants where product_variants.clinic_product_id = clinic_products.id and product_variants.is_active = 1) desc');
                break;
            case 'name':
                $query->orderBy('name');
                break;
            default:
                $query->orderByDesc('created_at')->orderByDesc('id');
        }

        $perPage = (int) ($validated['per_page'] ?? 12);
        $products = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => collect($products->items())->map(fn (ClinicProduct $product) => $this->productPayload($product))->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $product = ClinicProduct::query()
            ->with([
                'category:id,name,slug',
                'clinic:id,name,slug,address,phone,email,logo_path,primary_color',
                'variants' => fn ($variants) => $variants->orderBy('price'),
            ])
            ->withSum(['orderItems as sold_count' => function (Builder $items) {
                $items->whereHas('order', fn (Builder $order) => $order->where('status', \App\Models\ProductOrder::STATUS_COMPLETED));
            }], 'quantity')
            ->whereHas('clinic', fn (Builder $clinic) => $clinic->where('is_active', true))
            ->active()
            ->find($id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->productPayload($product, withVariants: true),
        ]);
    }

    private function onlinePaymentAvailable(Clinic $clinic): bool
    {
        return $this->onlineAvailability[$clinic->id] ??= (bool) $this->merchants
            ->onlineAvailability($clinic)['available'];
    }

    private function productPayload(ClinicProduct $product, bool $withVariants = false): array
    {
        $variants = $product->variants->filter(fn (ProductVariant $variant) => $variant->is_active);
        $availableVariants = $variants->filter(fn (ProductVariant $variant) => $variant->availableStock() > 0);
        $prices = $variants->map(fn (ProductVariant $variant) => $variant->effectivePrice());

        $payload = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'description' => $product->description,
            'image_url' => $product->image_url,
            'is_active' => $product->is_active,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'clinic' => $product->clinic ? [
                'id' => $product->clinic->id,
                'name' => $product->clinic->name,
                'slug' => $product->clinic->slug,
                'address' => $product->clinic->address,
                'phone' => $product->clinic->phone,
                'logo_url' => $product->clinic->logo_url,
                'primary_color' => $product->clinic->primary_color,
                // Aplikasi hanya menawarkan "Bayar Online" bila klinik penjual
                // benar-benar siap; kalau tidak, checkout hanya menampilkan
                // bayar di klinik (sesuai §9.6).
                'online_payment_available' => $this->onlinePaymentAvailable($product->clinic),
            ] : null,
            'price_min' => $prices->min(),
            'price_max' => $prices->max(),
            'has_discount' => $variants->contains(fn (ProductVariant $variant) => $variant->hasDiscount()),
            'total_available_stock' => $availableVariants->sum(fn (ProductVariant $variant) => $variant->availableStock()),
            'is_available' => $availableVariants->isNotEmpty(),
            'sold_count' => (int) ($product->sold_count ?? 0),
        ];

        if ($withVariants) {
            $payload['variants'] = $variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'price' => (int) $variant->price,
                'discount_price' => $variant->hasDiscount() ? (int) $variant->discount_price : null,
                'effective_price' => $variant->effectivePrice(),
                'available_stock' => $variant->availableStock(),
                'is_available' => $variant->availableStock() > 0,
            ])->values();
        }

        return $payload;
    }
}
