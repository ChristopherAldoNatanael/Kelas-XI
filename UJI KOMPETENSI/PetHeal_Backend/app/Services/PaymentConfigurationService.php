<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ClinicPaymentAccount;
use App\Models\PaymentCredentialVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pengelolaan kredensial Midtrans milik klinik (VCMS).
 *
 * Aturan penting:
 * - Server Key hanya ditulis (terenkripsi) dan tidak pernah dibaca ulang untuk
 *   ditampilkan; UI hanya menerima status & Merchant ID tersamarkan.
 * - Menyimpan kredensial BUKAN berarti aktif. Aktivasi hanya setelah uji
 *   koneksi berhasil.
 * - Perubahan kredensial membuat versi baru; versi lama tidak ditimpa supaya
 *   transaksi berjalan tetap dapat diverifikasi.
 */
class PaymentConfigurationService
{
    public function __construct(
        private readonly MidtransAccountGateway $gateway,
        private readonly PaymentAccountResolver $resolver
    ) {}

    public function accountFor(Clinic $clinic, ?User $actor = null): ClinicPaymentAccount
    {
        $account = ClinicPaymentAccount::firstOrCreate(
            ['clinic_id' => $clinic->id],
            [
                'provider' => ClinicPaymentAccount::PROVIDER_MIDTRANS,
                'status' => ClinicPaymentAccount::STATUS_NOT_CONFIGURED,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]
        );

        return $account->load('credentialVersions', 'currentCredentialVersion');
    }

    /**
     * @param  array{environment:string, merchant_id:string, client_key:string, server_key:?string}  $data
     */
    public function saveCredentials(Clinic $clinic, array $data, User $actor): PaymentCredentialVersion
    {
        $environment = $this->assertEnvironment((string) $data['environment']);
        $serverKey = trim((string) ($data['server_key'] ?? ''));

        if ($serverKey === '') {
            throw new RuntimeException(__('payments.server_key_required'));
        }

        return DB::transaction(function () use ($clinic, $data, $actor, $environment, $serverKey) {
            $account = $this->accountFor($clinic, $actor);

            $latest = $account->credentialVersions->first();
            $nextVersion = ((int) $latest?->version) + 1;

            // Nilai identik tidak perlu membuat versi baru.
            if ($latest
                && $latest->environment === $environment
                && $latest->merchant_id === $data['merchant_id']
                && $latest->client_key === $data['client_key']
                && $latest->server_key === $serverKey) {
                return $latest;
            }

            $version = PaymentCredentialVersion::create([
                'payment_account_id' => $account->id,
                'environment' => $environment,
                'merchant_id' => $data['merchant_id'],
                'client_key' => $data['client_key'],
                'server_key' => $serverKey,
                'version' => $nextVersion,
                'status' => PaymentCredentialVersion::STATUS_PENDING,
                'is_active' => false,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            // Kredensial baru belum terbukti; transaksi ditahan sampai uji
            // koneksi + aktivasi berhasil. Versi aktif lama TIDAK dihapus.
            $account->forceFill([
                'status' => ClinicPaymentAccount::STATUS_PENDING_VERIFICATION,
                'last_validation_message' => null,
                'updated_by' => $actor->id,
            ])->save();

            $this->audit(
                'payment_account.credentials_saved',
                sprintf(
                    'Kredensial Midtrans %s klinik %s disimpan sebagai versi %d (belum aktif).',
                    $environment,
                    $clinic->name,
                    $version->version
                ),
                $account,
                $actor
            );

            return $version->fresh();
        });
    }

    /**
     * @return array{valid:bool, message:string}
     */
    public function validate(PaymentCredentialVersion $version, User $actor): array
    {
        $version->loadMissing('account.clinic');
        $account = $version->account;

        if ($account === null || $version->status === PaymentCredentialVersion::STATUS_RETIRED) {
            throw new RuntimeException(__('payments.credential_not_testable'));
        }

        $probe = $this->gateway->testCredentials(
            $this->previewAccount($version),
            'PETHEAL-VERIFY-' . $account->clinic_id . '-' . $version->id . '-' . now()->timestamp
        );

        $version->forceFill([
            'status' => $probe['valid']
                ? PaymentCredentialVersion::STATUS_VALID
                : PaymentCredentialVersion::STATUS_INVALID,
            'validation_message' => mb_substr($probe['message'], 0, 255),
            'last_validated_at' => now(),
            'updated_by' => $actor->id,
        ])->save();

        $updates = [
            'last_validated_at' => now(),
            'last_validation_message' => mb_substr($probe['message'], 0, 255),
            'updated_by' => $actor->id,
        ];

        if (!$probe['valid']) {
            $updates['status'] = ClinicPaymentAccount::STATUS_INVALID;
        } elseif ($account->status === ClinicPaymentAccount::STATUS_INVALID || $account->status === ClinicPaymentAccount::STATUS_NOT_CONFIGURED) {
            $updates['status'] = ClinicPaymentAccount::STATUS_PENDING_VERIFICATION;
        }

        $account->forceFill($updates)->save();

        $this->audit(
            'payment_account.credentials_tested',
            sprintf(
                'Uji koneksi Midtrans %s klinik %s versi %d: %s.',
                $version->environment,
                $account->clinic?->name ?? ('#' . $account->clinic_id),
                $version->version,
                $probe['valid'] ? 'valid' : 'gagal'
            ),
            $account,
            $actor
        );

        return ['valid' => $probe['valid'], 'message' => $probe['message']];
    }

    public function activate(PaymentCredentialVersion $version, User $actor): ClinicPaymentAccount
    {
        $version->loadMissing('account.clinic');
        $account = $version->account;

        if ($account === null) {
            throw new RuntimeException(__('payments.credential_not_testable'));
        }

        if ($version->status !== PaymentCredentialVersion::STATUS_VALID || !$version->hasCredentials()) {
            throw new RuntimeException(__('payments.activation_requires_valid_test'));
        }

        if ($version->environment === 'production') {
            if ($actor->role !== 'super_admin' && $account->production_approved_at === null) {
                throw new RuntimeException(__('payments.production_requires_approval'));
            }
        }

        return DB::transaction(function () use ($account, $version, $actor) {
            ClinicPaymentAccount::whereKey($account->id)->lockForUpdate()->first();

            $account->credentialVersions()
                ->where('id', '!=', $version->id)
                ->update([
                    'is_active' => false,
                    'status' => DB::raw("CASE WHEN status = 'valid' THEN 'retired' ELSE status END"),
                    'retired_at' => now(),
                    'updated_at' => now(),
                ]);

            $version->forceFill([
                'is_active' => true,
                'status' => PaymentCredentialVersion::STATUS_VALID,
                'activated_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $account->forceFill([
                'status' => ClinicPaymentAccount::STATUS_ACTIVE,
                'active_environment' => $version->environment,
                'current_credential_version_id' => $version->id,
                'updated_by' => $actor->id,
            ])->save();

            $this->audit(
                'payment_account.activated',
                sprintf(
                    'Pembayaran online klinik %s diaktifkan (environment %s, merchant %s, versi %d).',
                    $account->clinic?->name ?? ('#' . $account->clinic_id),
                    $version->environment,
                    $version->merchant_id,
                    $version->version
                ),
                $account,
                $actor
            );

            return $account->fresh(['credentialVersions', 'currentCredentialVersion']);
        });
    }

    public function deactivate(ClinicPaymentAccount $account, User $actor, ?string $reason = null): ClinicPaymentAccount
    {
        return DB::transaction(function () use ($account, $actor, $reason) {
            $account->credentialVersions()->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

            $account->forceFill([
                'status' => ClinicPaymentAccount::STATUS_DISABLED,
                'current_credential_version_id' => null,
                'last_validation_message' => $reason,
                'updated_by' => $actor->id,
            ])->save();

            $this->audit(
                'payment_account.deactivated',
                sprintf(
                    'Pembayaran online klinik %s dinonaktifkan%s.',
                    $account->clinic?->name ?? ('#' . $account->clinic_id),
                    $reason ? ' (' . $reason . ')' : ''
                ),
                $account,
                $actor
            );

            return $account->fresh(['credentialVersions', 'currentCredentialVersion']);
        });
    }

    /**
     * Persetujuan super admin untuk aktivasi production.
     */
    public function approveProduction(ClinicPaymentAccount $account, User $actor): ClinicPaymentAccount
    {
        if ($actor->role !== 'super_admin') {
            throw new RuntimeException(__('payments.super_admin_only'));
        }

        $account->forceFill([
            'production_approved_at' => now(),
            'production_approved_by' => $actor->id,
            'updated_by' => $actor->id,
        ])->save();

        $this->audit(
            'payment_account.production_approved',
            sprintf(
                'Aktivasi production pembayaran klinik %s disetujui super admin.',
                $account->clinic?->name ?? ('#' . $account->clinic_id)
            ),
            $account,
            $actor
        );

        return $account->fresh();
    }

    /**
     * Mode terkini untuk ditampilkan di UI (status saja, tanpa secret).
     */
    public function summary(Clinic $clinic): array
    {
        $account = $this->accountFor($clinic);
        $environment = $this->resolver->environment();
        $availability = $this->resolver->availability($clinic);
        $current = $account->currentCredentialVersion;

        return [
            'account' => $account,
            'environment' => $environment,
            'active_version' => $current,
            'availability' => $availability,
            'has_configuration' => $account->hasEverBeenConfigured(),
            'production_approved' => $account->production_approved_at !== null,
        ];
    }

    private function previewAccount(PaymentCredentialVersion $version): ResolvedPaymentAccount
    {
        $endpoints = ResolvedPaymentAccount::endpointsFor($version->environment);

        return new ResolvedPaymentAccount(
            routingSource: ResolvedPaymentAccount::SOURCE_CLINIC,
            environment: $version->environment,
            paymentAccountId: $version->payment_account_id,
            credentialVersionId: $version->id,
            merchantId: (string) $version->merchant_id,
            clientKey: $version->client_key,
            serverKey: (string) $version->server_key,
            snapUrl: $endpoints['snap'],
            apiUrl: $endpoints['api'],
            label: $version->account?->clinic?->name ?? 'Klinik',
        );
    }

    private function assertEnvironment(string $environment): string
    {
        if (!in_array($environment, ['sandbox', 'production'], true)) {
            throw new RuntimeException(__('payments.invalid_environment'));
        }

        return $environment;
    }

    private function audit(string $action, string $description, ClinicPaymentAccount $account, User $actor): void
    {
        AuditLog::log($action, $description, $account, $actor->id);
    }
}
