<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicProduct;
use App\Services\ClinicDiscoveryService;
use Illuminate\Http\Request;

/**
 * Penemuan klinik untuk Click & Collect.
 *
 * Koordinat pengguna TIDAK disimpan — hanya dipakai untuk menghitung jarak
 * pada request ini. Jarak selalu dihitung server; nilai jarak dari client
 * tidak pernah dipercaya.
 */
class PetShopClinicController extends Controller
{
    public function __construct(private readonly ClinicDiscoveryService $discovery) {}

    public function nearby(Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:100',
            'product_id' => 'nullable|integer|min:1',
            'variant_id' => 'nullable|integer|min:1',
            'quantity' => 'nullable|integer|min:1|max:20',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $hasLat = array_key_exists('latitude', $validated) && $validated['latitude'] !== null;
        $hasLng = array_key_exists('longitude', $validated) && $validated['longitude'] !== null;

        if ($hasLat !== $hasLng) {
            return response()->json([
                'success' => false,
                'message' => 'Latitude dan longitude harus dikirim bersama.',
                'code' => 'invalid_coordinates',
            ], 422);
        }

        $clinics = $this->discovery->nearby(
            $hasLat ? (float) $validated['latitude'] : null,
            $hasLng ? (float) $validated['longitude'] : null,
            [
                'city' => $validated['city'] ?? null,
                'district' => $validated['district'] ?? null,
                'search' => $validated['search'] ?? null,
                'product_id' => $validated['product_id'] ?? null,
                'variant_id' => $validated['variant_id'] ?? null,
                'quantity' => $validated['quantity'] ?? 1,
                'limit' => $validated['limit'] ?? 50,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $clinics,
            'meta' => [
                'sorted_by' => $hasLat ? 'distance' : 'name',
                'distance_available' => $hasLat,
                'total' => $clinics->count(),
            ],
        ]);
    }

    /**
     * Ketersediaan satu produk di seluruh klinik (untuk pemilihan pickup).
     */
    public function productClinics(Request $request, int $id)
    {
        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'variant_id' => 'nullable|integer|min:1',
            'quantity' => 'nullable|integer|min:1|max:20',
        ]);

        $hasLat = array_key_exists('latitude', $validated) && $validated['latitude'] !== null;
        $hasLng = array_key_exists('longitude', $validated) && $validated['longitude'] !== null;

        if ($hasLat !== $hasLng) {
            return response()->json([
                'success' => false,
                'message' => 'Latitude dan longitude harus dikirim bersama.',
                'code' => 'invalid_coordinates',
            ], 422);
        }

        $product = ClinicProduct::query()
            ->active()
            ->with('clinic')
            ->find($id);

        if (!$product || !$product->clinic?->is_active) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
        }

        if (!$product->clinic->pickup_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Klinik penjual tidak melayani pengambilan produk.',
                'code' => 'pickup_unavailable',
            ], 422);
        }

        $clinics = $this->discovery->clinicsSellingProduct(
            $product,
            $hasLat ? (float) $validated['latitude'] : null,
            $hasLng ? (float) $validated['longitude'] : null,
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
            (int) ($validated['quantity'] ?? 1)
        );

        return response()->json([
            'success' => true,
            'data' => $clinics,
            'meta' => [
                'product_name' => $product->name,
                'distance_available' => $hasLat,
                'total' => $clinics->count(),
            ],
        ]);
    }

    /**
     * Pilihan pencarian manual saat izin lokasi ditolak.
     */
    public function filters()
    {
        $clinics = Clinic::query()->active()->pickupEnabled();

        $cities = (clone $clinics)->whereNotNull('city')->distinct()->orderBy('city')->pluck('city');
        $districts = (clone $clinics)->whereNotNull('district')->distinct()->orderBy('district')->pluck('district');

        return response()->json([
            'success' => true,
            'data' => [
                'cities' => $cities->values(),
                'districts' => $districts->values(),
                'pickup_clinics_total' => (clone $clinics)->count(),
            ],
        ]);
    }
}
