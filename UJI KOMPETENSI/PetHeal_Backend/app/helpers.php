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

if (!function_exists('medicine_expiry_warning_days')) {
    /**
     * Ambang peringatan kedaluwarsa (hari).
     *
     * Prioritas: AppSetting runtime → config/medicines.php → env default 60.
     * Satu sumber nilai ini dipakai model, controller, dan view supaya badge
     * "hampir kedaluwarsa" tidak pernah berbeda antar halaman.
     */
    function medicine_expiry_warning_days(): int
    {
        $setting = \App\Models\AppSetting::getValue('medicine_expiry_warning_days');

        if ($setting !== null && is_numeric($setting) && (int) $setting > 0) {
            return (int) $setting;
        }

        return max(1, (int) config('medicines.expiry_warning_days', 60));
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

if (!function_exists('format_rupiah')) {
    /**
     * Satu-satunya cara menulis rupiah di tampilan.
     *
     * Sebelumnya setiap halaman menyusun sendiri `'Rp ' . number_format(...)`,
     * sehingga pemisah ribuan dan spasi berbeda antar halaman. Helper ini
     * memakai format Indonesia yang sama di seluruh panel: "Rp 1.250.000".
     */
    function format_rupiah(mixed $value, bool $withPrefix = true): string
    {
        $amount = number_format((float) $value, 0, ',', '.');

        return $withPrefix ? 'Rp ' . $amount : $amount;
    }
}

if (!function_exists('format_qty')) {
    /**
     * Angka stok/jumlah tanpa nol di belakang koma: 42, 42,5, 0,25.
     * Dipakai pada stok obat, batch, dan mutasi agar tidak tampil "42,00".
     */
    function format_qty(mixed $value): string
    {
        $number = number_format((float) $value, 2, ',', '.');

        $number = rtrim(rtrim($number, '0'), ',');

        return $number === '' || $number === '-' ? '0' : $number;
    }
}

if (!function_exists('format_tanggal')) {
    /**
     * Tanggal yang mengikuti bahasa antarmuka (id/en).
     *
     * `->format('d M Y')` selalu menghasilkan nama bulan Inggris, sehingga
     * dashboard berbahasa Indonesia menampilkan "Nov" di tengah teks Indonesia.
     *
     * @param \DateTimeInterface|string|null $value
     */
    function format_tanggal(mixed $value, string $preset = 'date', string $fallback = '-'): string
    {
        if (empty($value)) {
            return $fallback;
        }

        $date = $value instanceof \DateTimeInterface
            ? \Carbon\Carbon::instance($value)
            : \Carbon\Carbon::parse($value);

        $pattern = match ($preset) {
            'datetime' => 'd M Y H:i',
            'time' => 'H:i',
            'long' => 'd F Y',
            'iso' => 'Y-m-d',
            default => 'd M Y',
        };

        return $date->locale(app()->getLocale())->translatedFormat($pattern);
    }
}

if (!function_exists('has_active_filters')) {
    /**
     * Apakah ada filter yang benar-benar terisi pada query string.
     *
     * Form filter selalu mengirim semua field (`search=`, `status=`, `date=`),
     * sehingga `request()->hasAny([...])` hampir selalu true. Akibatnya pesan
     * "tidak ada hasil untuk filter ini" muncul padahal pengguna belum
     * memfilter apa pun, dan tombol Reset ikut tampil terus. Helper ini hanya
     * menghitung nilai yang benar-benar berisi.
     *
     * @param array<int, string> $keys
     */
    function has_active_filters(array $keys): bool
    {
        foreach ($keys as $key) {
            if (trim((string) request($key)) !== '') {
                return true;
            }
        }

        return false;
    }
}
