<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\ClinicProduct;
use App\Models\ProductOrder;

/**
 * Scope klinik untuk seluruh halaman Pet Shop di VCMS.
 *
 * Prinsipnya sama dengan modul admin lain: clinic_admin terkunci ke
 * kliniknya; super_admin mengikuti klinik yang sedang dipilih di sesi, dan
 * tanpa pilihan berarti tampilan seluruh klinik (read-only overview).
 */
trait ResolvesPetShopScope
{
    protected function scopeClinicId(): ?int
    {
        return currentClinicId();
    }

    /**
     * Klinik WAJIB ada untuk aksi tulis (tambah produk, ubah pesanan).
     */
    protected function requireWritableClinic(): int
    {
        $clinicId = currentClinicId();

        if (!$clinicId) {
            abort(redirect()
                ->route('admin.pet-shop.dashboard')
                ->with('error', __('petshop.select_clinic_first')));
        }

        return (int) $clinicId;
    }

    protected function authorizeProduct(ClinicProduct $product): void
    {
        $clinicId = $this->scopeClinicId();

        abort_unless($clinicId === null || (int) $product->clinic_id === (int) $clinicId, 404);
    }

    protected function authorizeOrder(ProductOrder $order): void
    {
        $clinicId = $this->scopeClinicId();

        abort_unless($clinicId === null || (int) $order->clinic_id === (int) $clinicId, 404);
    }
}
