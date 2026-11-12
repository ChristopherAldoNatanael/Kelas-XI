<?php

use App\Models\Clinic;

if (!function_exists('currentClinic')) {
    function currentClinic(): ?Clinic
    {
        // Cache per REQUEST (bukan static per proses): currentClinic() dipanggil
        // 5-7x per request admin, tapi cache static pernah bertahan melewati
        // request. Setelah `migrate:fresh` atau pergantian user, id yang sama
        // bisa menunjuk klinik lama yang sudah tidak ada — query jadi bocor
        // antar klinik/tenant. Request attribute selalu dibuang di akhir request.
        $user = auth()->user();
        $cacheKey = 'petheal.current_clinic.'
            . ($user?->id ?? 'guest') . '|'
            . ($user->role ?? '') . '|'
            . (string) session('current_clinic_id');

        $request = request();

        if ($request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        if ($user && $user->role !== 'super_admin') {
            $clinic = $user->clinic;
        } else {
            $clinicId = session('current_clinic_id');
            $clinic = $clinicId ? Clinic::find($clinicId) : null;
        }

        $request->attributes->set($cacheKey, $clinic);

        return $clinic;
    }
}

if (!function_exists('currentClinicId')) {
    function currentClinicId(): ?int
    {
        return currentClinic()?->id;
    }
}

if (!function_exists('isSuperAdmin')) {
    /**
     * PHASE 1 (D1): centralized role check.
     * Only super_admin may operate without a clinic scope (system overview).
     */
    function isSuperAdmin($user = null): bool
    {
        $user = $user ?: auth()->user();
        return $user && $user->role === 'super_admin';
    }
}

if (!function_exists('tenantClinicId')) {
    /**
     * PHASE 1 (D1): resolve the effective clinic id for a given user object.
     * - super_admin: session-based overview target (may be null = all clinics).
     * - tenant roles: the user's own clinic_id directly (never the session).
     */
    function tenantClinicId($user = null): ?int
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return null;
        }

        if (($user->role ?? null) === 'super_admin') {
            return currentClinicId();
        }

        return isset($user->clinic_id) && $user->clinic_id ? (int) $user->clinic_id : null;
    }
}
if (!function_exists('requireTenantClinicId')) {
    /**
     * PHASE 1 (D1): centralized fail-closed tenant resolution.
     *
     * - super_admin: may return null (system overview, existing behavior preserved).
     * - tenant roles (clinic_admin, admin, doctor, user): MUST have clinic_id,
     *   otherwise abort 403 instead of falling back to unrestricted access.
     *
     * Use this wherever a clinic scope is mandatory. Existing
     * currentClinicId() is kept for read-only overview contexts.
     */
    function requireTenantClinicId($user = null): ?int
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            abort(403, 'Akses ditolak. Anda belum terautentikasi.');
        }

        if (($user->role ?? null) === 'super_admin') {
            return currentClinicId();
        }

        $clinicId = tenantClinicId($user);

        if (!$clinicId) {
            abort(403, 'Akses ditolak. Akun Anda tidak terikat pada klinik manapun.');
        }

        return (int) $clinicId;
    }
}

if (!function_exists('applyClinicScope')) {
    /**
     * PHASE 1 (D1): centralized fail-closed query scope.
     *
     * Replaces the fail-open pattern:
     *   ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
     * which silently becomes UNSCOPED when $clinicId === null.
     *
     * Behavior:
     * - super_admin with null clinic: query unchanged (overview preserved).
     * - tenant role with null clinic: whereRaw('1 = 0') (empty result, never all rows).
     * - otherwise: where('clinic_id', $clinicId).
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     */
    function applyClinicScope($query, ?int $clinicId = null, $user = null)
    {
        $user = $user ?: auth()->user();
        // Resolve from the explicit user object when given, so the scope can
        // never inherit another request identity (auth()->user()).
        $clinicId = $clinicId ?? tenantClinicId($user);

        if ($clinicId) {
            return $query->where('clinic_id', $clinicId);
        }

        if ($user && ($user->role ?? null) === 'super_admin') {
            return $query;
        }

        // Fail closed: tenant context missing => match nothing.
        return $query->whereRaw('1 = 0');
    }
}