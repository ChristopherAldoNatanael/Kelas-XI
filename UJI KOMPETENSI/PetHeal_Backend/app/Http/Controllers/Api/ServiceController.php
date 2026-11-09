<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServiceController extends Controller
{
    /**
     * PHASE 1 (D4): resolve an explicit tenant for this request.
     *
     * Priority (first match wins, behavior otherwise unchanged):
     * 1. Authenticated user's clinic (Android authenticated flow — untouched).
     * 2. Explicit `?clinic_slug=` (public/anonymous callers must name a tenant).
     * 3. super_admin without clinic => null (existing unscoped overview).
     * 4. Anyone else without tenant => null meaning "missing", caller 422s.
     *
     * `?clinic_id=` selalu didahulukan di atas keempatnya (lihat
     * forcedClinicId): pemanggil menyatakan klinik tujuannya sendiri.
     */
    private function resolveTenant(Request $request): array
    {
        $user = $request->user() ?? auth('sanctum')->user();

        // MULTI-CLINIC DISCOVERY (2026-11-09): `?clinic_id=` memaksa katalog
        // satu klinik. Diperlukan alur booking Android: setelah dokter
        // dipilih, layanan yang ditawarkan HARUS dari klinik dokter itu,
        // meski klinik favorit pasien berbeda. Slug tidak dipakai untuk ini
        // karena slug = konteks tenant/staf; klinik booking dipilih per
        // transaksi. Klinik nonaktif dianggap tidak ada (fail-closed).
        $forced = $this->forcedClinicId($request);
        if ($forced !== null) {
            return $forced === 0 ? [null, 'not_found'] : [$forced, null];
        }

        // MULTI-CLINIC DISCOVERY: pasien = akun global. Katalog lintas klinik
        // dibutuhkan untuk perbandingan harga, jadi akun pasien tanpa
        // `?clinic_slug` mendapat seluruh layanan aktif (read-only, publik).
        // `users.clinic_id` lama pada akun pasien diabaikan.
        if ($user && ($user->role ?? null) === 'user') {
            if ($user->clinic_id && !$request->filled('clinic_slug')) {
                return [(int) $user->clinic_id, null];
            }
            if (!$request->filled('clinic_slug')) {
                return [null, null];
            }
        } elseif ($user?->clinic_id) {
            return [(int) $user->clinic_id, null];
        }

        if ($user && ($user->role ?? null) === 'super_admin') {
            return [null, null];
        }

        $slug = trim((string) $request->query('clinic_slug', ''));
        if ($slug === '') {
            return [null, 'missing'];
        }

        $clinic = Clinic::where('slug', $slug)->where('is_active', true)->first();
        if (!$clinic) {
            return [null, 'not_found'];
        }

        return [(int) $clinic->id, null];
    }

    /**
     * Klinik yang dipaksa klien lewat `?clinic_id=`.
     *
     * @return int|null null = tidak diminta, 0 = diminta tetapi tidak valid
     *                    (tidak dikenal / nonaktif — diperlakukan seperti 404).
     */
    private function forcedClinicId(Request $request): ?int
    {
        $raw = $request->input('clinic_id');
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!ctype_digit((string) $raw)) {
            return 0;
        }
        return Clinic::where('id', (int) $raw)->where('is_active', true)->exists()
            ? (int) $raw
            : 0;
    }

    public function index(Request $request)
    {
        [$clinicId, $tenantError] = $this->resolveTenant($request);
        // PHASE 1 (D4): never default to all clinics. Public callers must
        // pass ?clinic_slug=<slug>; unknown slug => 404 like other resources.
        if ($tenantError === 'missing') {
            return response()->json([
                'success' => false,
                'message' => 'clinic_slug wajib diisi untuk akses publik.',
            ], 422);
        }
        if ($tenantError === 'not_found') {
            return response()->json([
                'success' => false,
                'message' => 'Clinic not found',
            ], 404);
        }

        // PHASE 3 (B12): versioned like doctors — admin writes bump
        // `services_version` so price/availability edits surface immediately
        // instead of lingering up to 6h in cache.
        $serviceVersion = Cache::get('services_version', 1);
        $cacheKey = 'services_active:v' . $serviceVersion . ':c' . ($clinicId ?: 'all');
        // Catatan: clinic_id yang diminta (bukan yang dimiliki staf) juga
        // menghasilkan key unik karena $clinicId sudah klinik hasil resolve.

        $services = Cache::remember($cacheKey, 21600, function () use ($clinicId) {
            $query = Service::active()->orderBy('name');
            if ($clinicId) {
                $query->where('clinic_id', $clinicId);
            }
            return $query->get();
        });

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    public function show(Request $request, $id)
    {
        [$clinicId, $tenantError] = $this->resolveTenant($request);

        if ($tenantError === 'missing') {
            return response()->json([
                'success' => false,
                'message' => 'clinic_slug wajib diisi untuk akses publik.',
            ], 422);
        }
        if ($tenantError === 'not_found') {
            return response()->json([
                'success' => false,
                'message' => 'Clinic not found',
            ], 404);
        }

        $serviceVersion = Cache::get('services_version', 1);
        $cacheKey = 'service_' . $id . ':v' . $serviceVersion . ':c' . ($clinicId ?: 'all');

        $service = Cache::remember($cacheKey, 21600, function () use ($id, $clinicId) {
            $query = Service::where('id', $id);
            if ($clinicId) {
                $query->where('clinic_id', $clinicId);
            }
            return $query->first();
        });

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }
}
