<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\ClinicProduct;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Penemuan klinik terdekat untuk Click & Collect.
 *
 * Aturan yang ditegakkan di sini:
 * - jarak = Haversine (garis lurus), TIDAK pernah diklaim sebagai jarak tempuh;
 * - klinik tanpa koordinat tetap bisa ditemukan lewat pencarian manual;
 * - ketersediaan produk & stok selalu diperiksa dari database
 *   (`available_stock = stock_on_hand - stock_reserved`);
 * - hasil diurutkan berdasarkan jarak, lalu nama (deterministik).
 */
class ClinicDiscoveryService
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * @param array{city?:?string,district?:?string,search?:?string,product_id?:?int,variant_id?:?int,quantity?:?int,limit?:?int} $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function nearby(?float $latitude, ?float $longitude, array $filters = []): Collection
    {
        [$latitude, $longitude] = $this->normalizeCoordinates($latitude, $longitude);

        $quantity = max(1, (int) ($filters['quantity'] ?? 1));
        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;
        $variantId = isset($filters['variant_id']) ? (int) $filters['variant_id'] : null;

        $query = Clinic::query()
            ->active()
            ->pickupEnabled()
            ->with(['products' => function ($products) {
                $products->active();
            }, 'products.variants' => function ($variants) {
                $variants->active()->orderBy('price');
            }]);

        if (!empty($filters['city'])) {
            $query->where('city', 'like', '%' . $filters['city'] . '%');
        }

        if (!empty($filters['district'])) {
            $query->where('district', 'like', '%' . $filters['district'] . '%');
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('name', 'like', '%' . $search . '%')
                    ->orWhere('address', 'like', '%' . $search . '%')
                    ->orWhere('city', 'like', '%' . $search . '%')
                    ->orWhere('district', 'like', '%' . $search . '%');
            });
        }

        // Produk tertentu: hanya klinik yang benar-benar menjual produk itu.
        if ($productId !== null && $productId > 0) {
            $query->whereHas('products', function (Builder $products) use ($productId) {
                $products->active()->whereKey($productId);
            });
        }

        $clinics = $query->get();

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 50)));

        return $clinics
            ->map(function (Clinic $clinic) use ($latitude, $longitude, $productId, $variantId, $quantity) {
                $distance = $this->distanceKm($latitude, $longitude, $clinic);
                $availability = null;

                if ($productId !== null && $productId > 0) {
                    $product = $clinic->products->firstWhere('id', $productId);
                    $availability = $this->availabilityFor($product, $variantId, $quantity);

                    // Klinik tanpa produk/varian yang diminta tidak ditawarkan
                    // sebagai tujuan pengambilan produk tersebut.
                    if ($availability === null) {
                        return null;
                    }
                }

                return $this->clinicPayload($clinic, $distance, $availability);
            })
            ->filter()
            ->sortBy(function (array $row) {
                return $row['distance_km'] === null
                    ? PHP_INT_MAX
                    : (int) round($row['distance_km'] * 1000);
            })
            ->values()
            ->take($limit);
    }

    /**
     * Klinik lain yang menjual produk yang sama (nama + brand), lengkap dengan
     * varian, harga, dan stok per klinik.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function clinicsSellingProduct(
        ClinicProduct $product,
        ?float $latitude,
        ?float $longitude,
        ?int $variantId = null,
        int $quantity = 1
    ): Collection {
        [$latitude, $longitude] = $this->normalizeCoordinates($latitude, $longitude);

        $siblings = ClinicProduct::query()
            ->active()
            ->where('name', $product->name)
            ->when($product->brand !== null, fn (Builder $query) => $query->where('brand', $product->brand))
            ->with([
                'clinic',
                'variants' => fn ($variants) => $variants->active()->orderBy('price'),
            ])
            ->whereHas('clinic', fn (Builder $clinic) => $clinic->active()->pickupEnabled())
            ->get();

        return $siblings
            ->map(function (ClinicProduct $candidate) use ($latitude, $longitude, $variantId, $quantity) {
                $clinic = $candidate->clinic;

                if ($clinic === null) {
                    return null;
                }

                $availability = $this->availabilityFor($candidate, $variantId, $quantity);

                if ($availability === null) {
                    return null;
                }

                return $this->clinicPayload(
                    $clinic,
                    $this->distanceKm($latitude, $longitude, $clinic),
                    $availability
                );
            })
            ->filter()
            ->sortBy(function (array $row) {
                return $row['distance_km'] === null
                    ? PHP_INT_MAX
                    : (int) round($row['distance_km'] * 1000);
            })
            ->values();
    }

    /**
     * Ketersediaan satu produk pada satu klinik.
     *
     * `$variantId` dipakai untuk memastikan varian yang dipilih memang ada di
     * klinik itu; bila tidak ada, klinik gugur (bukan menampilkan stok varian
     * klinik lain).
     *
     * @return array<string, mixed>|null
     */
    public function availabilityFor(?ClinicProduct $product, ?int $variantId, int $quantity = 1): ?array
    {
        if ($product === null) {
            return null;
        }

        $variants = $product->variants;

        $selected = $variantId !== null
            ? $variants->firstWhere('id', $variantId)
            : $variants->first(fn (ProductVariant $variant) => $variant->availableStock() >= $quantity);

        if ($variantId !== null && $selected === null) {
            return null;
        }

        // Varian ada tetapi stok kurang: tetap dikembalikan agar UI bisa
        // menampilkan "stok tidak mencukupi" alih-alih menyembunyikan klinik.

        $totalAvailable = (int) $variants->sum(fn (ProductVariant $variant) => $variant->availableStock());
        $canFulfil = $selected !== null && $selected->availableStock() >= $quantity;

        return [
            'clinic_product_id' => $product->id,
            'is_available' => $totalAvailable > 0,
            'can_fulfil_quantity' => $canFulfil,
            'available_stock' => $selected !== null ? $selected->availableStock() : $totalAvailable,
            'total_available_stock' => $totalAvailable,
            'price_min' => (int) $variants->min(fn (ProductVariant $variant) => $variant->effectivePrice()),
            'price_max' => (int) $variants->max(fn (ProductVariant $variant) => $variant->effectivePrice()),
            'selected_variant_id' => $selected?->id,
            'variants' => $variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'price' => (int) $variant->price,
                'discount_price' => $variant->hasDiscount() ? (int) $variant->discount_price : null,
                'effective_price' => $variant->effectivePrice(),
                'available_stock' => $variant->availableStock(),
                'is_available' => $variant->availableStock() > 0,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clinicPayload(Clinic $clinic, ?float $distanceKm, ?array $availability = null): array
    {
        return [
            'id' => $clinic->id,
            'name' => $clinic->name,
            'slug' => $clinic->slug,
            'address' => $clinic->address,
            'city' => $clinic->city,
            'district' => $clinic->district,
            'postal_code' => $clinic->postal_code,
            'phone' => $clinic->phone,
            'logo_url' => $clinic->logo_url,
            'primary_color' => $clinic->primary_color,
            'latitude' => $clinic->latitude !== null ? (float) $clinic->latitude : null,
            'longitude' => $clinic->longitude !== null ? (float) $clinic->longitude : null,
            'pickup_enabled' => (bool) $clinic->pickup_enabled,
            'is_open_now' => $clinic->isOpenNow(),
            'opening_time' => $clinic->opening_time,
            'closing_time' => $clinic->closing_time,
            'distance_km' => $distanceKm,
            'distance_label' => $this->formatDistance($distanceKm),
            'distance_estimated' => $distanceKm !== null,
            'product' => $availability,
        ];
    }

    /**
     * Jarak Haversine. Null bila klinik atau pengguna tidak punya koordinat —
     * UI harus menampilkan "jarak belum tersedia", bukan angka palsu.
     */
    public function distanceKm(?float $latitude, ?float $longitude, Clinic $clinic): ?float
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        if ($clinic->latitude === null || $clinic->longitude === null) {
            return null;
        }

        $latFrom = deg2rad((float) $clinic->latitude);
        $lngFrom = deg2rad((float) $clinic->longitude);
        $latTo = deg2rad($latitude);
        $lngTo = deg2rad($longitude);

        $latDiff = $latTo - $latFrom;
        $lngDiff = $lngTo - $lngFrom;

        $a = sin($latDiff / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDiff / 2) ** 2;

        $a = min(1.0, max(0.0, $a));
        $c = 2 * asin(sqrt($a));

        return round(self::EARTH_RADIUS_KM * $c, 3);
    }

    /**
     * Format mengikuti kebiasaan Indonesia: 850 m, 1,2 km, 3,5 km.
     */
    public function formatDistance(?float $km): ?string
    {
        if ($km === null) {
            return null;
        }

        if ($km < 0.01) {
            // Di bawah 10 meter: jangan tampilkan angka 0 m seolah salah hitung.
            return '<10 m';
        }

        if ($km < 1.0) {
            return (int) round($km * 1000) . ' m';
        }

        return number_format($km, 1, ',', '.') . ' km';
    }

    /**
     * Koordinat hanya dipakai bila lengkap dan valid. Nilai tidak valid dari
     * client tidak pernah dipakai untuk menghitung jarak.
     *
     * @return array{0:?float,1:?float}
     */
    private function normalizeCoordinates(?float $latitude, ?float $longitude): array
    {
        if ($latitude === null || $longitude === null) {
            return [null, null];
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return [null, null];
        }

        return [$latitude, $longitude];
    }
}
