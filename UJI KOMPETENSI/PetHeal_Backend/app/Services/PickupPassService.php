<?php

namespace App\Services;

use App\Models\ProductOrder;
use App\Models\ProductPickupHandover;
use App\Models\ProductPickupPass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * QR Pickup Pass & serah terima barang.
 *
 * Keamanan:
 * - token QR = 64 karakter acak (bukan id pesanan), hanya bisa dipakai sekali;
 * - kode manual 8 karakter tanpa huruf/angka ambigu (O/0/I/1), juga sekali pakai;
 * - QR hanya MEMULAI verifikasi. Barang baru dianggap diserahkan setelah
 *   transaksi database menandai pesanan `completed` + pembayaran lunas.
 */
class PickupPassService
{
    /** Tanpa 0/O/1/I/L agar tidak salah baca. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly ProductOrderService $orders
    ) {}

    /**
     * Terbitkan (atau pakai ulang) pass untuk pesanan yang siap diambil.
     */
    public function issue(ProductOrder $order): ProductPickupPass
    {
        $existing = $order->pickupPass()
            ->where('is_active', true)
            ->whereNull('used_at')
            ->first();

        if ($existing !== null && $existing->isUsable()) {
            return $existing;
        }

        $order->pickupPass()->where('is_active', true)->update(['is_active' => false]);

        return ProductPickupPass::create([
            'order_id' => $order->id,
            'clinic_id' => $order->clinic_id,
            'user_id' => $order->user_id,
            'token' => Str::random(64),
            'manual_code' => $this->uniqueManualCode(),
            'expires_at' => now()->addDays((int) config('petshop.pickup_pass_days', 14)),
            'is_active' => true,
        ]);
    }

    /**
     * Cabut semua pass aktif pesanan (batal / kedaluwarsa / selesai).
     */
    public function revoke(ProductOrder $order): void
    {
        $order->pickupPass()->where('is_active', true)->update(['is_active' => false]);
    }

    /**
     * Pass untuk pemilik pesanan. Hanya terbit ketika pesanan benar-benar
     * `ready_for_pickup`.
     */
    public function passForOwner(ProductOrder $order): ?ProductPickupPass
    {
        if ($order->status !== ProductOrder::STATUS_READY_FOR_PICKUP) {
            return null;
        }

        $pass = $order->pickupPass()->whereNull('used_at')->first();

        return $pass?->isUsable() ? $pass : null;
    }

    /**
     * Verifikasi QR / kode manual oleh petugas klinik.
     *
     * @return array{pass:ProductPickupPass,order:ProductOrder}
     */
    public function verify(string $input, int $clinicId): array
    {
        $normalized = $this->normalizeCode($input);

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'code' => 'Masukkan kode pengambilan atau pindai QR terlebih dahulu.',
            ]);
        }

        $pass = ProductPickupPass::query()
            ->where('token', $normalized)
            ->orWhere('manual_code', $normalized)
            ->first();

        if ($pass === null) {
            throw ValidationException::withMessages([
                'code' => 'Kode pengambilan tidak ditemukan. Periksa kembali atau cari lewat nomor pesanan.',
            ]);
        }

        if ((int) $pass->clinic_id !== $clinicId) {
            // Jangan membocorkan keberadaan pass klinik lain.
            throw ValidationException::withMessages([
                'code' => 'Kode pengambilan tidak ditemukan di klinik ini.',
            ]);
        }

        if (!$pass->is_active) {
            throw ValidationException::withMessages([
                'code' => 'Kode pengambilan ini sudah dinonaktifkan.',
            ]);
        }

        if ($pass->used_at !== null) {
            throw ValidationException::withMessages([
                'code' => 'Kode pengambilan ini sudah pernah dipakai.',
            ]);
        }

        if ($pass->expires_at !== null && $pass->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => 'Kode pengambilan sudah kedaluwarsa. Minta pelanggan membuka aplikasi kembali.',
            ]);
        }

        $order = $pass->order()->with('items')->first();

        if ($order === null) {
            throw ValidationException::withMessages(['code' => 'Pesanan untuk kode ini tidak ditemukan.']);
        }

        if ($order->status === ProductOrder::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'code' => 'Pesanan ini sudah diselesaikan sebelumnya.',
            ]);
        }

        if (in_array($order->status, [
            ProductOrder::STATUS_CANCELLED,
            ProductOrder::STATUS_REJECTED,
            ProductOrder::STATUS_EXPIRED,
        ], true)) {
            throw ValidationException::withMessages([
                'code' => 'Pesanan ini sudah dibatalkan dan tidak dapat diambil.',
            ]);
        }

        if ($order->status !== ProductOrder::STATUS_READY_FOR_PICKUP) {
            throw ValidationException::withMessages([
                'code' => 'Pesanan belum siap diambil. Siapkan pesanan terlebih dahulu.',
            ]);
        }

        return ['pass' => $pass, 'order' => $order];
    }

    /**
     * Konfirmasi serah terima barang.
     *
     * Idempotensi: pesanan dikunci, status + pembayaran dicek ulang di dalam
     * transaksi. Dua petugas yang menekan tombol bersamaan hanya menghasilkan
     * satu penyelesaian; permintaan kedua menerima pesan "sudah diserahkan".
     *
     * @return array{order:ProductOrder,handover:?ProductPickupHandover,already_completed:bool}
     */
    public function confirmHandover(
        ProductOrder $order,
        User $actor,
        string $verificationMethod = 'manual',
        ?ProductPickupPass $pass = null
    ): array {
        return DB::transaction(function () use ($order, $actor, $verificationMethod, $pass) {
            /** @var ProductOrder $locked */
            $locked = ProductOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->hasBeenPickedUp() || $locked->status === ProductOrder::STATUS_COMPLETED) {
                return ['order' => $locked, 'handover' => $locked->handover, 'already_completed' => true];
            }

            if ($locked->status !== ProductOrder::STATUS_READY_FOR_PICKUP) {
                throw ValidationException::withMessages([
                    'status' => 'Pesanan belum siap diambil; status harus ready_for_pickup.',
                ]);
            }

            if (!$locked->isPaid()) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran belum lunas. Catat pembayaran terlebih dahulu sebelum menyerahkan barang.',
                ]);
            }

            $pass ??= $locked->pickupPass()
                ->where('is_active', true)
                ->whereNull('used_at')
                ->first();

            if ($pass !== null) {
                if ((int) $pass->clinic_id !== (int) $locked->clinic_id) {
                    throw ValidationException::withMessages(['code' => 'Kode pengambilan bukan milik klinik ini.']);
                }

                if (!$pass->isUsable()) {
                    throw ValidationException::withMessages(['code' => 'Kode pengambilan sudah tidak berlaku.']);
                }
            }

            $updated = $this->orders->transition(
                $locked,
                ProductOrder::STATUS_COMPLETED,
                'clinic_admin',
                $actor,
                'Barang diserahkan ke pelanggan'
            );

            $updated->forceFill([
                'picked_up_at' => now(),
                'picked_up_by' => $actor->id,
            ])->save();

            if ($pass !== null) {
                $pass->forceFill([
                    'used_at' => now(),
                    'used_by' => $actor->id,
                    'is_active' => false,
                ])->save();
            }

            $updated->pickupPass()->where('is_active', true)->update(['is_active' => false]);

            $handover = ProductPickupHandover::create([
                'product_order_id' => $updated->id,
                'product_pickup_pass_id' => $pass?->id,
                'clinic_id' => $updated->clinic_id,
                'user_id' => $updated->user_id,
                'handed_over_by' => $actor->id,
                'items_snapshot' => $updated->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'quantity' => (int) $item->quantity,
                ])->values()->all(),
                'verification_method' => $verificationMethod,
                'handed_over_at' => now(),
            ]);

            return ['order' => $updated, 'handover' => $handover, 'already_completed' => false];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ProductOrder $order, ProductPickupPass $pass): array
    {
        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => 'Siap diambil',
            'qr_payload' => $pass->token,
            'manual_code' => substr($pass->manual_code, 0, 4) . '-' . substr($pass->manual_code, 4),
            'expires_at' => $pass->expires_at?->toIso8601String(),
            'clinic' => [
                'id' => $order->clinic_id,
                'name' => $order->pickup_clinic_name,
                'address' => $order->pickup_clinic_address,
                'phone' => $order->pickup_clinic_phone,
                'latitude' => $order->clinic?->latitude !== null ? (float) $order->clinic->latitude : null,
                'longitude' => $order->clinic?->longitude !== null ? (float) $order->clinic->longitude : null,
            ],
            'payment_status' => $order->payment_status,
            'payment_status_label' => $order->isPaid() ? 'Lunas' : 'Belum lunas',
            'instructions' => 'Silakan datang ke klinik dan tunjukkan QR Code atau kode pengambilan kepada petugas.',
            'items' => $order->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'quantity' => (int) $item->quantity,
            ])->values()->all(),
        ];
    }

    private function uniqueManualCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < 8; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (ProductPickupPass::where('manual_code', $code)->exists());

        // Disimpan tanpa tanda hubung; pemisah 4-4 hanya untuk tampilan.
        return $code;
    }

    /**
     * Kode manual dianggap sama tanpa memandang huruf besar/kecil, spasi, dan
     * tanda hubung ("abcd efgh" == "ABCD-EFGH").
     */
    private function normalizeCode(string $input): string
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            return '';
        }

        // Token QR = 64 karakter base62; jangan diubah.
        if (strlen($trimmed) >= 32) {
            return $trimmed;
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $trimmed));
    }
}
