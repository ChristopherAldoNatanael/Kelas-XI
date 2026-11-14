<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ClinicMerchantConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Konfigurasi merchant klinik.
 *
 * Hanya super_admin (lewat VCMS) yang boleh mengubah merchant ID. Perubahan
 * ditulis ke audit log karena menentukan tujuan penerimaan dana.
 */
class MerchantConfigurationService
{
    public function forClinic(int $clinicId): ?ClinicMerchantConfig
    {
        return ClinicMerchantConfig::where('clinic_id', $clinicId)->first();
    }

    /**
     * @return array{available:bool, reason:?string, provider:?string, environment:string, commission_percent:float}
     */
    public function onlineAvailability(Clinic $clinic, ?MidtransPartnerGateway $gateway = null): array
    {
        $gateway ??= app(MidtransPartnerGateway::class);
        $environment = (string) config('services.midtrans.environment', 'sandbox');
        $config = $this->forClinic($clinic->id);

        if (!config('services.midtrans.partner.enabled')) {
            return [
                'available' => false,
                'reason' => 'partner_mode_disabled',
                'provider' => null,
                'environment' => $environment,
                'commission_percent' => (float) ($config?->commission_percent ?? 0),
            ];
        }

        if (!$gateway->isConfigured()) {
            return [
                'available' => false,
                'reason' => 'partner_credentials_missing',
                'provider' => null,
                'environment' => $environment,
                'commission_percent' => (float) ($config?->commission_percent ?? 0),
            ];
        }

        if (!$config || !$config->isReadyForOnlinePayment()) {
            return [
                'available' => false,
                'reason' => $config?->onlinePaymentBlockReason() ?? 'merchant_not_registered',
                'provider' => 'midtrans',
                'environment' => $environment,
                'commission_percent' => (float) ($config?->commission_percent ?? 0),
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'provider' => 'midtrans',
            'environment' => $environment,
            'commission_percent' => (float) $config->commission_percent,
        ];
    }

    /**
     * @param array{merchant_id?:?string, partner_id?:?string, environment?:string, onboarding_status?:string, is_online_payment_enabled?:bool, commission_percent?:float|string, notes?:?string} $data
     */
    public function upsert(Clinic $clinic, array $data, User $actor): ClinicMerchantConfig
    {
        return DB::transaction(function () use ($clinic, $data, $actor) {
            $config = ClinicMerchantConfig::firstOrNew(['clinic_id' => $clinic->id]);

            $wasOnline = (bool) $config->is_online_payment_enabled;
            $previousMerchant = $config->merchant_id;

            $config->fill([
                'provider' => 'midtrans',
                'environment' => $data['environment'] ?? config('services.midtrans.environment', 'sandbox'),
                'merchant_id' => $data['merchant_id'] ?? null,
                'partner_id' => $data['partner_id'] ?? null,
                'onboarding_status' => $data['onboarding_status'] ?? ClinicMerchantConfig::STATUS_NOT_STARTED,
                'is_online_payment_enabled' => (bool) ($data['is_online_payment_enabled'] ?? false),
                'commission_percent' => round((float) ($data['commission_percent'] ?? 0), 2),
                'notes' => $data['notes'] ?? null,
            ]);

            // Verifikasi dicap hanya saat merchant benar-benar aktif.
            if ($config->onboarding_status === ClinicMerchantConfig::STATUS_ACTIVE && $config->verified_at === null) {
                $config->verified_at = now();
            }

            if (in_array($config->onboarding_status, [
                ClinicMerchantConfig::STATUS_REJECTED,
                ClinicMerchantConfig::STATUS_SUSPENDED,
                ClinicMerchantConfig::STATUS_NOT_STARTED,
            ], true)) {
                $config->is_online_payment_enabled = false;
                $config->verified_at = null;
            }

            $config->save();

            AuditLog::log(
                'pet_shop.merchant_config.updated',
                sprintf(
                    'Merchant config klinik %s: %s → %s, online %s → %s, komisi %s%%',
                    $clinic->name,
                    $previousMerchant ?: 'kosong',
                    $config->merchant_id ?: 'kosong',
                    $wasOnline ? 'aktif' : 'nonaktif',
                    $config->is_online_payment_enabled ? 'aktif' : 'nonaktif',
                    rtrim(rtrim((string) $config->commission_percent, '0'), '.')
                ),
                $config,
                $actor->id
            );

            return $config->fresh();
        });
    }
}
