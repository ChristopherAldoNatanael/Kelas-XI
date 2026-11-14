<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\ClinicPaymentAccount;
use App\Models\Payment;
use App\Models\PaymentCredentialVersion;
use App\Models\PaymentRoutingLog;
use Illuminate\Support\Facades\Log;

/**
 * Menentukan akun Midtrans untuk satu transaksi.
 *
 * Urutan (tidak boleh dibalik):
 *  1. Route yang sudah terkunci pada payment/booking.
 *  2. Kredensial klinik yang aktif + terverifikasi untuk environment aplikasi.
 *  3. Fallback akun pusat HANYA bila klinik belum pernah mengonfigurasi
 *     dan compliance gate mengizinkan.
 *  4. Selain itu: tolak. Konfigurasi klinik yang invalid/nonaktif TIDAK
 *     pernah jatuh diam-diam ke akun pusat.
 */
class PaymentAccountResolver
{
    public const REASON_CLINIC_INVALID = 'clinic_credentials_invalid';
    public const REASON_CLINIC_PENDING = 'clinic_credentials_pending';
    public const REASON_ACCOUNT_DISABLED = 'clinic_account_disabled';
    public const REASON_ACCOUNT_SUSPENDED = 'clinic_account_suspended';
    public const REASON_ENVIRONMENT_MISMATCH = 'environment_mismatch';

    public function environment(): string
    {
        return (string) config('services.midtrans.environment', 'sandbox');
    }

    /**
     * @param  Payment|null  $lockedFrom  Payment lama yang route-nya harus diikuti.
     *
     * @throws PaymentRoutingException
     */
    public function resolve(?int $clinicId, ?Payment $lockedFrom = null): ResolvedPaymentAccount
    {
        $environment = $this->environment();

        if ($lockedFrom !== null) {
            $locked = $this->resolveLocked($lockedFrom, $environment);

            if ($locked !== null) {
                return $locked;
            }
        }

        if (!$clinicId) {
            return $this->resolveCentral($environment, 'tanpa klinik');
        }

        $account = ClinicPaymentAccount::with('credentialVersions')
            ->where('clinic_id', $clinicId)
            ->first();

        if ($account === null || !$account->hasEverBeenConfigured()) {
            return $this->resolveCentral($environment);
        }

        $version = $this->activeVersion($account, $environment);

        if ($account->status === ClinicPaymentAccount::STATUS_ACTIVE && $version !== null) {
            return $this->fromClinicVersion($account, $version, $environment);
        }

        throw $this->clinicRejection($account, $environment);
    }

    /**
     * Route terkunci untuk payment yang sudah ada. Null berarti payment ini
     * tidak memiliki route terkunci (mis. transaksi legacy/partner).
     */
    public function resolveLocked(Payment $payment, ?string $environment = null): ?ResolvedPaymentAccount
    {
        $environment ??= $this->environment();
        $payment->loadMissing(['paymentAccount.clinic', 'credentialVersion']);

        if ($payment->payment_account_id === null && $payment->routing_source === null) {
            return null;
        }

        if ($payment->routing_source === ResolvedPaymentAccount::SOURCE_CENTRAL_FALLBACK
            || $payment->routing_source === ResolvedPaymentAccount::SOURCE_LEGACY_CENTRAL) {
            return $this->resolveCentral($environment, null, false);
        }

        $version = $payment->credentialVersion;

        if ($version === null) {
            throw new PaymentRoutingException(
                'locked_route_missing',
                __('payments.routing_locked_missing'),
                409
            );
        }

        if (!$version->hasCredentials() || $version->status === PaymentCredentialVersion::STATUS_INVALID) {
            throw new PaymentRoutingException(
                'locked_route_invalid',
                __('payments.routing_locked_invalid'),
                409
            );
        }

        $account = $payment->paymentAccount;

        return new ResolvedPaymentAccount(
            routingSource: $payment->routing_source ?: ResolvedPaymentAccount::SOURCE_CLINIC,
            environment: $version->environment,
            paymentAccountId: $account?->id,
            credentialVersionId: $version->id,
            merchantId: (string) $version->merchant_id,
            clientKey: $version->client_key,
            serverKey: (string) $version->server_key,
            snapUrl: ResolvedPaymentAccount::endpointsFor($version->environment)['snap'],
            apiUrl: ResolvedPaymentAccount::endpointsFor($version->environment)['api'],
            label: $account?->clinic?->name ?: 'Klinik',
        );
    }

    /**
     * Route terkunci milik satu booking (dipakai pelunasan).
     */
    public function lockedRouteForBooking(int $bookingId): ?Payment
    {
        return Payment::where('payable_type', \App\Models\Booking::class)
            ->where('payable_id', $bookingId)
            ->whereNotNull('routing_source')
            ->where('status', '!=', Payment::STATUS_FAILED)
            ->latest('id')
            ->first();
    }

    /**
     * Ketersediaan online payment (dipakai katalog Pet Shop & validasi order).
     *
     * @return array{available:bool, reason:?string, provider:?string, environment:string, source:?string, merchant_label:?string}
     */
    public function availability(?Clinic $clinic): array
    {
        $environment = $this->environment();

        try {
            $resolved = $this->resolve($clinic?->id);

            return [
                'available' => true,
                'reason' => null,
                'provider' => 'midtrans',
                'environment' => $environment,
                'source' => $resolved->routingSource,
                'merchant_label' => $resolved->label,
            ];
        } catch (PaymentRoutingException $e) {
            return [
                'available' => false,
                'reason' => $e->reason,
                'provider' => 'midtrans',
                'environment' => $environment,
                'source' => null,
                'merchant_label' => null,
            ];
        }
    }

    public function logRouting(
        ?int $clinicId,
        array $context,
        string $outcome,
        ?string $reason = null,
        ?Payment $payment = null,
        ?string $payableType = null,
        ?int $payableId = null
    ): void {
        try {
            PaymentRoutingLog::create([
                'clinic_id' => $clinicId,
                'payment_id' => $payment?->id,
                'payable_type' => $payableType,
                'payable_id' => $payableId,
                'environment' => $context['environment'] ?? $this->environment(),
                'routing_source' => $context['routing_source'] ?? null,
                'payment_account_id' => $context['payment_account_id'] ?? null,
                'credential_version_id' => $context['credential_version_id'] ?? null,
                'merchant_id' => $context['merchant_id'] ?? null,
                'outcome' => $outcome,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            // Log routing tidak boleh menggagalkan transaksi.
            Log::warning('Failed to write payment routing log', [
                'clinic_id' => $clinicId,
                'reason' => $reason,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  bool  $enforceFallbackGate  Route terkunci milik transaksi lama
     *         harus tetap bisa dipakai walau kebijakan fallback berubah.
     */
    private function resolveCentral(
        string $environment,
        ?string $label = null,
        bool $enforceFallbackGate = true
    ): ResolvedPaymentAccount {
        $config = (array) config("services.midtrans.central.{$environment}", []);
        $serverKey = trim((string) ($config['server_key'] ?? ''));
        $merchantId = trim((string) ($config['merchant_id'] ?? ''));
        $clientKey = trim((string) ($config['client_key'] ?? ''));

        // Snap hanya membutuhkan Server Key (Basic auth). Merchant ID dan
        // Client Key adalah metadata untuk label/audit & aplikasi Android:
        // instalasi lama kerap hanya mengisi MIDTRANS_SERVER_KEY, dan itu
        // harus tetap bisa melayani klinik tanpa Midtrans sendiri.
        if ($serverKey === '') {
            throw PaymentRoutingException::centralNotConfigured();
        }

        if ($enforceFallbackGate && !$this->centralFallbackAllowed($environment)) {
            throw PaymentRoutingException::fallbackNotAllowed();
        }

        return new ResolvedPaymentAccount(
            routingSource: ResolvedPaymentAccount::SOURCE_CENTRAL_FALLBACK,
            environment: $environment,
            paymentAccountId: null,
            credentialVersionId: null,
            merchantId: $merchantId,
            clientKey: $clientKey !== '' ? $clientKey : null,
            serverKey: $serverKey,
            snapUrl: ResolvedPaymentAccount::endpointsFor($environment)['snap'],
            apiUrl: ResolvedPaymentAccount::endpointsFor($environment)['api'],
            label: $label ?? __('payments.central_account_label'),
        );
    }

    private function centralFallbackAllowed(string $environment): bool
    {
        if (!config('services.midtrans.central_fallback.enabled', false)) {
            return false;
        }

        if ($environment === 'production') {
            return (bool) config('services.midtrans.central_fallback.production_approved', false);
        }

        return true;
    }

    private function activeVersion(ClinicPaymentAccount $account, string $environment): ?PaymentCredentialVersion
    {
        $current = $account->currentCredentialVersion;

        if ($current && $account->active_environment === $environment && $current->isUsableFor($environment)) {
            return $current;
        }

        return $account->credentialVersions
            ->first(fn (PaymentCredentialVersion $version) => $version->isUsableFor($environment));
    }

    private function fromClinicVersion(
        ClinicPaymentAccount $account,
        PaymentCredentialVersion $version,
        string $environment
    ): ResolvedPaymentAccount {
        if (!$account->clinic) {
            $account->load('clinic');
        }

        return new ResolvedPaymentAccount(
            routingSource: ResolvedPaymentAccount::SOURCE_CLINIC,
            environment: $environment,
            paymentAccountId: $account->id,
            credentialVersionId: $version->id,
            merchantId: (string) $version->merchant_id,
            clientKey: $version->client_key,
            serverKey: (string) $version->server_key,
            snapUrl: ResolvedPaymentAccount::endpointsFor($environment)['snap'],
            apiUrl: ResolvedPaymentAccount::endpointsFor($environment)['api'],
            label: $account->clinic?->name ?: 'Klinik',
        );
    }

    private function clinicRejection(ClinicPaymentAccount $account, string $environment): PaymentRoutingException
    {
        [$reason, $key] = match (true) {
            $account->status === ClinicPaymentAccount::STATUS_DISABLED => [
                self::REASON_ACCOUNT_DISABLED, 'payments.routing_account_disabled',
            ],
            $account->status === ClinicPaymentAccount::STATUS_SUSPENDED => [
                self::REASON_ACCOUNT_SUSPENDED, 'payments.routing_account_suspended',
            ],
            $account->active_environment !== null && $account->active_environment !== $environment => [
                self::REASON_ENVIRONMENT_MISMATCH, 'payments.routing_environment_mismatch',
            ],
            $account->status === ClinicPaymentAccount::STATUS_INVALID => [
                self::REASON_CLINIC_INVALID, 'payments.routing_invalid',
            ],
            $account->status === ClinicPaymentAccount::STATUS_PENDING_VERIFICATION => [
                self::REASON_CLINIC_PENDING, 'payments.routing_pending',
            ],
            !$account->credentialVersions->contains(
                fn (PaymentCredentialVersion $version) => $version->hasCredentials()
                    && $version->status === PaymentCredentialVersion::STATUS_VALID
            ) => [self::REASON_CLINIC_PENDING, 'payments.routing_pending'],
            default => [self::REASON_CLINIC_INVALID, 'payments.routing_invalid'],
        };

        return new PaymentRoutingException($reason, __($key), 422);
    }
}
