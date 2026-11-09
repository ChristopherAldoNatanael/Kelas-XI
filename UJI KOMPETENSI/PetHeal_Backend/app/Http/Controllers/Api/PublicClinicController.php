<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ServiceCategory;
use App\Services\ClinicRatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicClinicController extends Controller
{
    /**
     * GET /api/public/clinics
     * List active clinics with optional search, filter, and geolocation sorting.
     *
     * Query params:
     * - search: string (name or address)
     * - sort: name|rating|distance (default: name)
     * - lat: float (user latitude for distance sorting)
     * - lng: float (user longitude for distance sorting)
     * - min_rating: float (0–5)
     * - max_distance_km: float (requires lat+lng)
     * - category: string (service category slug)
     */
    public function index(Request $request)
    {
        $request->validate([
            'search'          => 'nullable|string|max:100',
            'sort'            => 'nullable|in:name,rating,distance',
            'lat'             => 'nullable|numeric|between:-90,90',
            'lng'             => 'nullable|numeric|between:-180,180',
            'min_rating'      => 'nullable|numeric|between:0,5',
            'max_distance_km' => 'nullable|numeric|min:0.1',
            'category'        => 'nullable|string|max:60',
            'service'         => 'nullable|string|max:100',
            'open_now'        => 'nullable|boolean',
            'limit'           => 'nullable|integer|min:1|max:100',
            'sync_rating'     => 'nullable|boolean',
        ]);

        // MULTI-CLINIC DISCOVERY (2026-11-09): `sync_rating=1` memaksa
        // penghitungan ulang rating klinik dari review (dipakai setelah
        // import/seed atau saat admin memperbaiki data).
        if ($request->boolean('sync_rating')) {
            ClinicRatingService::refreshAll();
        }

        $search   = $request->input('search');
        $sort     = $request->input('sort', 'name');
        $lat      = $request->input('lat') !== null && $request->input('lat') !== '' ? (float) $request->input('lat') : null;
        $lng      = $request->input('lng') !== null && $request->input('lng') !== '' ? (float) $request->input('lng') : null;
        $minRating = $request->input('min_rating') !== null && $request->input('min_rating') !== ''
            ? (float) $request->input('min_rating') : null;
        $maxDist  = $request->input('max_distance_km') !== null && $request->input('max_distance_km') !== ''
            ? (float) $request->input('max_distance_km') : null;
        $category = $request->input('category');
        $serviceSearch = $request->input('service');
        $limit    = min(max((int) $request->query('limit', 100), 1), 100);

        $query = Clinic::active();

        // Search by name, address, or service name
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('address', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhereHas('services', function ($sq) use ($search) {
                      $sq->where('is_active', true)->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Filter by minimum rating (clinics tanpa rating TIDAK lolos filter)
        if ($minRating !== null) {
            $query->whereNotNull('rating')->where('rating', '>=', $minRating);
        }

        // Filter by service category (clinics that have at least one service in this category)
        if ($category) {
            $query->whereHas('services', function ($q) use ($category) {
                $q->where('is_active', true)
                  ->whereHas('serviceCategory', fn($sc) => $sc->where('slug', $category));
            });
        }

        // Filter by service name (mis. "vaksinasi")
        if ($serviceSearch) {
            $query->whereHas('services', function ($q) use ($serviceSearch) {
                $q->where('is_active', true)->where('name', 'like', '%' . $serviceSearch . '%');
            });
        }

        // Sort
        if ($sort === 'rating') {
            $query->orderByRaw('rating IS NULL, rating DESC');
        } else {
            $query->orderBy('name');
        }

        $clinics = $query->withCount([
                'services as services_count' => fn ($q) => $q->where('is_active', true),
                'doctors as doctors_count' => fn ($q) => $q->where('is_active', true),
            ])->get();

        // Build response with computed distance
        $result = $clinics->map(function (Clinic $c) use ($lat, $lng) {
            $distance = ($lat !== null && $lng !== null) ? $c->distanceFrom($lat, $lng) : null;
            return $this->formatClinic($c, $distance);
        });

        // Sort by distance (client-requested)
        if ($sort === 'distance') {
            // Klinik tanpa koordinat ditaruh paling akhir — bukan disembunyikan.
            $result = $result->sortBy(fn($c) => $c['distance_km'] ?? PHP_INT_MAX)->values();
        }

        // Filter by max distance (hanya saat lokasi tersedia)
        if ($maxDist !== null && $lat !== null && $lng !== null) {
            $result = $result->filter(fn($c) => $c['distance_km'] !== null && $c['distance_km'] <= $maxDist)->values();
        }

        // Filter status buka sekarang
        if ($request->boolean('open_now')) {
            $result = $result->filter(fn($c) => $c['is_open_now'] === true)->values();
        }

        $result = $result->take($limit)->values();

        return response()->json([
            'success' => true,
            'data'    => $result,
            'meta'    => [
                'total'        => $result->count(),
                'sort'         => $sort,
                'has_location' => $lat !== null && $lng !== null,
            ],
        ]);
    }

    /**
     * GET /api/public/clinics/{slug}
     * Show clinic detail with counts and services.
     */
    public function show(Request $request, string $slug)
    {
        $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $clinic = Clinic::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$clinic) {
            return response()->json([
                'success' => false,
                'message' => 'Klinik tidak ditemukan.',
            ], 404);
        }

        $lat = $request->input('lat') ? (float) $request->input('lat') : null;
        $lng = $request->input('lng') ? (float) $request->input('lng') : null;
        $distance = ($lat !== null && $lng !== null) ? $clinic->distanceFrom($lat, $lng) : null;

        // Load services with category info
        $services = $clinic->services()
            ->where('is_active', true)
            ->with('serviceCategory')
            ->orderBy('name')
            ->get()
            ->map(fn($s) => $this->formatService($s));

        $data = $this->formatClinic($clinic, $distance);
        $data['doctors_count']  = $clinic->doctors()->where('is_active', true)->count();
        $data['services_count'] = $clinic->services()->where('is_active', true)->count();
        $data['services']       = $services;
        $data['is_open_now']    = $clinic->isOpenNow();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/public/clinics/{slug}/services
     * Get all active services for a specific clinic.
     */
    public function services(string $slug)
    {
        $clinic = Clinic::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$clinic) {
            return response()->json([
                'success' => false,
                'message' => 'Klinik tidak ditemukan.',
            ], 404);
        }

        $services = $clinic->services()
            ->where('is_active', true)
            ->with('serviceCategory')
            ->orderBy('name')
            ->get()
            ->map(fn($s) => $this->formatService($s));

        return response()->json([
            'success' => true,
            'data'    => $services,
        ]);
    }

    /**
     * GET /api/public/services/{id}
     * Detail satu layanan + daftar klinik yang menyediakan layanan SETARA
     * (kategori standar sama). Dipakai layar "Detail layanan" Android sebelum
     * masuk ke perbandingan harga.
     */
    public function serviceDetail(Request $request, int $id)
    {
        $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $service = \App\Models\Service::with(['clinic', 'serviceCategory'])
            ->whereKey($id)
            ->where('is_active', true)
            ->whereHas('clinic', fn ($q) => $q->where('is_active', true))
            ->first();

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan tidak ditemukan.',
            ], 404);
        }

        $lat = $request->filled('lat') ? (float) $request->input('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->input('lng') : null;

        $clinic = $service->clinic;
        $distance = ($lat !== null && $lng !== null && $clinic) ? $clinic->distanceFrom($lat, $lng) : null;

        // Klinik lain yang menyediakan layanan setara (kategori standar sama).
        $equivalents = collect();
        if ($service->service_category_id) {
            $equivalents = \App\Models\Service::with('clinic')
                ->where('service_category_id', $service->service_category_id)
                ->where('id', '!=', $service->id)
                ->where('is_active', true)
                ->whereHas('clinic', fn ($q) => $q->where('is_active', true))
                ->get()
                ->map(function ($other) use ($lat, $lng) {
                    $otherClinic = $other->clinic;
                    return [
                        'service_id'      => $other->id,
                        'service_name'    => $other->name,
                        'price'           => $other->price !== null ? (float) $other->price : null,
                        'price_available' => $other->price !== null,
                        'price_formatted' => $other->price !== null
                            ? 'Rp ' . number_format((float) $other->price, 0, ',', '.')
                            : null,
                        'clinic'          => $otherClinic ? [
                            'id'            => $otherClinic->id,
                            'name'          => $otherClinic->name,
                            'slug'          => $otherClinic->slug,
                            'logo_url'      => $otherClinic->logo_url,
                            'address'       => $otherClinic->address,
                            'rating'        => $otherClinic->rating !== null ? (float) $otherClinic->rating : null,
                            'is_open_now'   => $otherClinic->isOpenNow(),
                        ] : null,
                        'distance_km'     => ($lat !== null && $lng !== null && $otherClinic)
                            ? $otherClinic->distanceFrom($lat, $lng) : null,
                    ];
                })
                ->sortBy(fn ($i) => $i['price'] ?? PHP_INT_MAX)
                ->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'service'          => $this->formatService($service),
                'clinic'           => $clinic ? $this->formatClinic($clinic, $distance) : null,
                'equivalents'      => $equivalents,
                'equivalents_count' => $equivalents->count(),
                'comparison_basis' => $service->service_category_id ? 'service_category' : 'none',
            ],
        ]);
    }

    /**
     * GET /api/public/service-categories
     * List all active service categories.
     */
    public function serviceCategories()
    {
        $categories = ServiceCategory::active()->orderBy('name')->get()->map(fn($c) => [
            'id'          => $c->id,
            'name'        => $c->name,
            'slug'        => $c->slug,
            'description' => $c->description,
            'icon'        => $c->icon,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $categories,
        ]);
    }

    /**
     * GET /api/public/services/compare?category_id=X&category_slug=X&lat=Y&lng=Z
     * Compare service prices across clinics for a given standard category.
     * Also supports ?name=search_term to find services by name.
     */
    public function compareServices(Request $request)
    {
        $request->validate([
            'category_id'     => 'nullable|integer|exists:service_categories,id',
            'category_slug'   => 'nullable|string|max:60',
            'name'            => 'nullable|string|max:100',
            'service_id'      => 'nullable|integer|exists:services,id',
            'lat'             => 'nullable|numeric|between:-90,90',
            'lng'             => 'nullable|numeric|between:-180,180',
            'sort'            => 'nullable|in:price_asc,price_desc,distance,rating',
            'max_price'       => 'nullable|numeric|min:0',
            'min_price'       => 'nullable|numeric|min:0',
            'max_distance_km' => 'nullable|numeric|min:0.1',
            'min_rating'      => 'nullable|numeric|between:0,5',
            'open_now'        => 'nullable|boolean',
        ]);

        $lat = $request->filled('lat') ? (float) $request->input('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->input('lng') : null;
        $sort = $request->input('sort', 'price_asc');

        // Resolve category
        $categoryId = null;
        $category = null;
        if ($request->filled('category_id')) {
            $categoryId = (int) $request->input('category_id');
            $category = ServiceCategory::find($categoryId);
        } elseif ($request->filled('category_slug')) {
            $category = ServiceCategory::where('slug', $request->input('category_slug'))->first();
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori layanan tidak ditemukan.',
                ], 404);
            }
            $categoryId = $category->id;
        }

        // Acuan dari satu layanan: pakai kategori standarnya, BUKAN nama mirip.
        $referenceService = null;
        if ($request->filled('service_id')) {
            $referenceService = \App\Models\Service::with('serviceCategory')->find((int) $request->input('service_id'));
            if ($referenceService && !$categoryId && $referenceService->service_category_id) {
                $categoryId = (int) $referenceService->service_category_id;
                $category = $referenceService->serviceCategory;
            }
        }

        if (!$categoryId && !$request->filled('name') && !$request->filled('service_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Wajib menyertakan category_id, category_slug, service_id, atau name untuk perbandingan.',
            ], 422);
        }

        // Build service query
        $serviceQuery = \App\Models\Service::with(['clinic', 'serviceCategory'])
            ->where('is_active', true)
            ->whereHas('clinic', fn($q) => $q->where('is_active', true));

        if ($categoryId) {
            $serviceQuery->where('service_category_id', $categoryId);
        }

        // Hanya jika TIDAK ada kategori yang bisa dipakai, pencarian nama
        // dipakai sebagai upaya terakhir. Dengan kategori, semua layanan
        // berkategori itu dibandingkan (setara), bukan hanya yang namanya mirip.
        if (!$categoryId && $request->filled('name')) {
            $name = trim((string) $request->input('name'));
            $serviceQuery->where('name', 'like', '%' . $name . '%');
        }

        // Price filters
        if ($request->filled('min_price')) {
            $serviceQuery->where('price', '>=', (float) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $serviceQuery->where('price', '<=', (float) $request->input('max_price'));
        }

        $services = $serviceQuery->get();

        // Map to comparison items
        $items = $services->map(function ($service) use ($lat, $lng) {
            $clinic = $service->clinic;
            $distance = ($lat !== null && $lng !== null && $clinic) ? $clinic->distanceFrom($lat, $lng) : null;

            return [
                'service_id'       => $service->id,
                'service_name'     => $service->name,
                'service_category' => $service->serviceCategory ? [
                    'id'   => $service->serviceCategory->id,
                    'name' => $service->serviceCategory->name,
                    'slug' => $service->serviceCategory->slug,
                    'icon' => $service->serviceCategory->icon,
                ] : null,
                // Harga NULL = backend tidak punya data. UI WAJIB menampilkan
                // "Harga belum tersedia", bukan Rp 0.
                'price'            => $service->price !== null ? (float) $service->price : null,
                'price_available'  => $service->price !== null,
                'price_formatted'  => $service->price !== null
                    ? 'Rp ' . number_format((float) $service->price, 0, ',', '.')
                    : null,
                'price_status'     => $service->price !== null ? 'available' : 'unavailable',
                'duration_minutes' => $service->duration,
                'description'      => $service->description,
                'clinic'           => $clinic ? [
                    'id'            => $clinic->id,
                    'name'          => $clinic->name,
                    'slug'          => $clinic->slug,
                    'address'       => $clinic->address,
                    'logo_url'      => $clinic->logo_url,
                    'primary_color' => $clinic->primary_color,
                    'rating'        => $clinic->rating !== null ? (float) $clinic->rating : null,
                    'reviews_count' => (int) ($clinic->reviews_count ?? 0),
                    'phone'         => $clinic->phone,
                    'is_open_now'   => $clinic->isOpenNow(),
                    'latitude'      => $clinic->latitude !== null ? (float) $clinic->latitude : null,
                    'longitude'     => $clinic->longitude !== null ? (float) $clinic->longitude : null,
                ] : null,
                'distance_km'      => $distance,
                'distance_text'    => $distance === null ? null : $this->formatDistance($distance),
            ];
        });

        // Filter by max distance
        if ($request->filled('max_distance_km') && $lat !== null && $lng !== null) {
            $maxDist = (float) $request->input('max_distance_km');
            $items = $items->filter(fn($i) => $i['distance_km'] !== null && $i['distance_km'] <= $maxDist)->values();
        }

        // Filter by minimum clinic rating
        if ($request->filled('min_rating')) {
            $minRating = (float) $request->input('min_rating');
            $items = $items->filter(fn($i) => ($i['clinic']['rating'] ?? null) !== null
                && $i['clinic']['rating'] >= $minRating)->values();
        }

        // Filter status buka sekarang
        if ($request->boolean('open_now')) {
            $items = $items->filter(fn($i) => ($i['clinic']['is_open_now'] ?? null) === true)->values();
        }

        // Sort (harga kosong selalu di akhir)
        $items = match ($sort) {
            'price_asc'  => $items->sortBy(fn($i) => $i['price'] ?? PHP_INT_MAX)->values(),
            'price_desc' => $items->sortByDesc(fn($i) => $i['price'] ?? -1)->values(),
            'distance'   => $items->sortBy(fn($i) => $i['distance_km'] ?? PHP_INT_MAX)->values(),
            'rating'     => $items->sortByDesc(fn($i) => $i['clinic']['rating'] ?? -1)->values(),
            default      => $items->values(),
        };

        // Ringkasan statistik nyata dari hasil yang sama (bukan angka karangan).
        $prices = $items->pluck('price')->filter(fn($p) => $p !== null);

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'           => $items->count(),
                'sort'            => $sort,
                'has_location'    => $lat !== null && $lng !== null,
                'category'        => $category ? [
                    'id' => $category->id, 'name' => $category->name, 'slug' => $category->slug,
                ] : null,
                'reference_service' => $referenceService ? [
                    'id' => $referenceService->id, 'name' => $referenceService->name,
                ] : null,
                'comparison_basis' => $categoryId
                    ? 'service_category'
                    : ($referenceService ? 'service_id' : 'name_search'),
                'price_available_count' => $items->where('price_available', true)->count(),
                'lowest_price'    => $prices->isEmpty() ? null : (float) $prices->min(),
                'highest_price'   => $prices->isEmpty() ? null : (float) $prices->max(),
                'lowest_price_formatted' => $prices->isEmpty() ? null
                    : 'Rp ' . number_format((float) $prices->min(), 0, ',', '.'),
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatClinic(Clinic $c, ?float $distance = null): array
    {
        $isOpenNow = $c->isOpenNow();
        $hasCoordinates = $c->latitude !== null && $c->longitude !== null;

        return [
            'id'                 => $c->id,
            'name'               => $c->name,
            'slug'               => $c->slug,
            'address'            => $c->address,
            'phone'              => $c->phone,
            'email'              => $c->email,
            'logo_url'           => $c->logo_url,
            'featured_image_url' => $c->featured_image_url,
            'primary_color'      => $c->primary_color,
            'description'        => $c->description,
            'latitude'           => $hasCoordinates ? (float) $c->latitude : null,
            'longitude'          => $hasCoordinates ? (float) $c->longitude : null,
            'has_coordinates'    => $hasCoordinates,
            // Rating NULL = belum ada ulasan (bukan 0). UI wajib menampilkan
            // "Belum ada rating", bukan bintang kosong yang menyesatkan.
            'rating'             => $c->rating !== null ? (float) $c->rating : null,
            'reviews_count'      => (int) ($c->reviews_count ?? 0),
            'opening_time'       => $c->opening_time,
            'closing_time'       => $c->closing_time,
            'operating_days'     => $c->operating_days,
            'is_open_now'        => $isOpenNow,
            'operating_status'   => $isOpenNow === null ? 'unknown' : ($isOpenNow ? 'open' : 'closed'),
            'website'            => $c->website,
            'distance_km'        => $distance,
            'distance_text'      => $distance === null ? null : $this->formatDistance($distance),
            'doctors_count'      => isset($c->doctors_count) ? (int) $c->doctors_count : null,
            'services_count'     => isset($c->services_count) ? (int) $c->services_count : null,
            'is_active'          => $c->is_active,
        ];
    }

    private function formatDistance(float $km): string
    {
        if ($km < 1) {
            return number_format($km * 1000, 0, ',', '.') . ' m';
        }

        return number_format($km, 1, ',', '.') . ' km';
    }

    private function formatService(\App\Models\Service $s): array
    {
        return [
            'id'              => $s->id,
            'name'            => $s->name,
            'description'     => $s->description,
            'price'           => $s->price !== null ? (float) $s->price : null,
            'price_formatted' => $s->price !== null
                ? 'Rp ' . number_format((float) $s->price, 0, ',', '.')
                : null,
            'duration_minutes' => $s->duration,
            'category'         => $s->category,
            'service_category' => $s->serviceCategory ? [
                'id'   => $s->serviceCategory->id,
                'name' => $s->serviceCategory->name,
                'slug' => $s->serviceCategory->slug,
                'icon' => $s->serviceCategory->icon,
            ] : null,
            'is_active'       => $s->is_active,
        ];
    }
}
