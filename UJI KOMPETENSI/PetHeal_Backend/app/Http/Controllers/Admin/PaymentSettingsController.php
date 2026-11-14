<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ClinicPaymentAccount;
use App\Models\PaymentCredentialVersion;
use App\Models\PaymentNotification;
use App\Models\PaymentRoutingLog;
use App\Services\PaymentAccountResolver;
use App\Services\PaymentConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * VCMS: pengaturan Midtrans per klinik.
 *
 * Clinic Admin hanya melihat & mengubah kliniknya sendiri. Super Admin dapat
 * memilih klinik (session context) atau melihat ringkasan semua klinik.
 * Server Key tidak pernah dikirim kembali ke view.
 */
class PaymentSettingsController extends Controller
{
    public function __construct(
        private readonly PaymentConfigurationService $configuration,
        private readonly PaymentAccountResolver $resolver
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user->role === 'super_admin';
        $clinic = $isSuperAdmin ? currentClinic() : $user->clinic;

        if (!$isSuperAdmin && !$clinic) {
            abort(403, 'Akun Anda tidak terikat pada klinik manapun.');
        }

        $accounts = ClinicPaymentAccount::with(['currentCredentialVersion', 'credentialVersions'])
            ->when(!$isSuperAdmin, fn ($query) => $query->where('clinic_id', $clinic->id))
            ->get()
            ->keyBy('clinic_id');

        $summary = $clinic ? $this->configuration->summary($clinic) : null;
        $versions = $summary
            ? $summary['account']->credentialVersions()->with('account')->get()
            : collect();

        $environmentPanels = [];

        if ($summary) {
            $current = $summary['account']->currentCredentialVersion;

            foreach (['sandbox', 'production'] as $environment) {
                $latest = $summary['account']->credentialVersions
                    ->first(fn (PaymentCredentialVersion $version) => $version->environment === $environment);
                $active = $current && $current->environment === $environment ? $current : null;

                $environmentPanels[] = [
                    'environment' => $environment,
                    'label' => __('payments.env_' . $environment),
                    'latest' => $latest,
                    'active' => $active,
                    'is_app_environment' => $environment === $this->resolver->environment(),
                    'last_updated' => $latest?->updated_at,
                    'last_validated_at' => $latest?->last_validated_at,
                    'merchant' => $latest?->maskedMerchantId(),
                    'central_ready' => $this->centralReadyFor($environment),
                ];
            }
        }

        $routingBase = PaymentRoutingLog::query()
            ->when($clinic, fn ($query) => $query->where('clinic_id', $clinic->id));

        $routingFailures = (clone $routingBase)
            ->where('outcome', PaymentRoutingLog::OUTCOME_REJECTED)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $routingLogs = (clone $routingBase)
            ->with('clinic')
            ->latest('id')
            ->limit(12)
            ->get();

        $notificationFailures = PaymentNotification::query()
            ->where('signature_valid', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->when($clinic, fn ($query) => $query->whereHas(
                'payment',
                fn ($payment) => $payment->where('clinic_id', $clinic->id)
            ))
            ->count();

        $auditLogs = AuditLog::with('user')
            ->where('action', 'like', 'payment_account.%')
            ->when($clinic, fn ($query) => $query->where('clinic_id', $clinic->id))
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.payment-settings.index', [
            'isSuperAdmin' => $isSuperAdmin,
            'clinic' => $clinic,
            'clinics' => $isSuperAdmin ? Clinic::orderBy('name')->get() : collect([$clinic]),
            'accounts' => $accounts,
            'summary' => $summary,
            'versions' => $versions,
            'environmentPanels' => $environmentPanels,
            'environment' => $this->resolver->environment(),
            'centralReady' => $this->centralReady(),
            'centralReadiness' => [
                'sandbox' => $this->centralReadyFor('sandbox'),
                'production' => $this->centralReadyFor('production'),
            ],
            'fallbackEnabled' => (bool) config('services.midtrans.central_fallback.enabled', false),
            'fallbackApproved' => (bool) config('services.midtrans.central_fallback.production_approved', false),
            'callbackUrl' => url('/api/midtrans/webhook'),
            'reauthValid' => $this->hasRecentProductionReauth($request),
            'routingFailures' => $routingFailures,
            'routingLogs' => $routingLogs,
            'notificationFailures' => $notificationFailures,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * Super Admin memilih klinik yang akan dikelola (session context).
     */
    public function selectClinic(Request $request)
    {
        if ($request->user()->role !== 'super_admin') {
            abort(403);
        }

        $validated = $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
        ]);

        $request->session()->put('current_clinic_id', (int) $validated['clinic_id']);

        return redirect()->route('admin.payment-settings.index');
    }

    public function storeCredentials(Request $request)
    {
        $clinic = $this->targetClinic($request);

        $validated = $request->validate([
            'environment' => 'required|in:sandbox,production',
            'merchant_id' => 'required|string|max:64|regex:/^[A-Za-z0-9._-]+$/',
            'client_key' => 'required|string|max:190',
            'server_key' => 'required|string|min:8|max:500',
            'current_password' => 'nullable|string|max:191',
        ], [
            // Pesan Indonesia: halaman ini berbahasa Indonesia, sedangkan
            // pesan bawaan Laravel memakai bahasa Inggris.
            'environment.required' => __('payments.validation_env_required'),
            'environment.in' => __('payments.validation_env_invalid'),
            'merchant_id.required' => __('payments.validation_merchant_required'),
            'merchant_id.regex' => __('payments.validation_merchant_format'),
            'merchant_id.max' => __('payments.validation_merchant_max'),
            'client_key.required' => __('payments.validation_client_required'),
            'client_key.max' => __('payments.validation_client_max'),
            'server_key.required' => __('payments.validation_server_required'),
            'server_key.min' => __('payments.validation_server_min'),
            'server_key.max' => __('payments.validation_server_max'),
        ]);

        // Aksi production adalah perubahan sensitif: minta password ulang
        // (atau sesi konfirmasi 15 menit yang masih berlaku).
        $this->assertProductionReauth($request, $validated['environment'], $validated['current_password'] ?? null);

        try {
            $version = $this->configuration->saveCredentials($clinic, $validated, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['server_key' => $e->getMessage()])->withInput();
        }

        return back()->with(
            'success',
            __('payments.credentials_saved_message', ['version' => $version->version])
        );
    }

    public function testConnection(Request $request, int $version)
    {
        $clinic = $this->targetClinic($request);
        $credential = $this->ownedVersion($clinic, $version);

        try {
            $result = $this->configuration->validate($credential, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with(
            $result['valid'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function activate(Request $request, int $version)
    {
        $clinic = $this->targetClinic($request);
        $credential = $this->ownedVersion($clinic, $version);

        $this->assertProductionReauth(
            $request,
            (string) $credential->environment,
            $request->input('current_password')
        );

        try {
            $this->configuration->activate($credential, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', __('payments.activation_success'));
    }

    public function deactivate(Request $request)
    {
        $clinic = $this->targetClinic($request);
        $account = ClinicPaymentAccount::where('clinic_id', $clinic->id)->firstOrFail();

        $this->configuration->deactivate($account, $request->user(), $request->input('reason'));

        return back()->with('success', __('payments.deactivation_success'));
    }

    public function approveProduction(Request $request)
    {
        $clinic = $this->targetClinic($request);
        $account = ClinicPaymentAccount::where('clinic_id', $clinic->id)->firstOrFail();

        $this->assertProductionReauth($request, 'production', $request->input('current_password'));

        $this->configuration->approveProduction($account, $request->user());

        return back()->with('success', __('payments.production_approved'));
    }

    private function targetClinic(Request $request): Clinic
    {
        $user = $request->user();
        $clinic = $user->role === 'super_admin' ? currentClinic() : $user->clinic;

        if (!$clinic) {
            abort(403, 'Pilih klinik terlebih dahulu.');
        }

        return $clinic;
    }

    private function ownedVersion(Clinic $clinic, int $versionId): PaymentCredentialVersion
    {
        $credential = PaymentCredentialVersion::with('account.clinic')->findOrFail($versionId);

        if ($credential->account?->clinic_id !== $clinic->id) {
            abort(404);
        }

        return $credential;
    }

    private function centralReady(): bool
    {
        return $this->centralReadyFor($this->resolver->environment());
    }

    private function centralReadyFor(string $environment): bool
    {
        $config = (array) config("services.midtrans.central.{$environment}", []);

        // Server Key adalah satu-satunya kredensial yang benar-benar dipakai
        // Snap. Merchant ID & Client Key hanya pelengkap (label/audit dan
        // aplikasi Android), jadi central tetap dianggap siap bila keduanya
        // kosong — sama seperti aturan di PaymentAccountResolver.
        return filled($config['server_key'] ?? null);
    }

    /**
     * Konfirmasi ulang password untuk aksi production.
     *
     * Password benar membuka jendela 15 menit sehingga admin tidak perlu
     * mengetik ulang pada setiap uji/aktivasi dalam satu sesi kerja.
     */
    private function assertProductionReauth(Request $request, string $environment, ?string $password): void
    {
        if ($environment !== 'production') {
            return;
        }

        if ($this->hasRecentProductionReauth($request)) {
            return;
        }

        $password = (string) $password;

        if ($password === '' || !Hash::check($password, (string) $request->user()?->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('payments.production_reauth_failed'),
            ]);
        }

        $request->session()->put('payment_settings_reauth_at', now()->timestamp);
        $request->session()->put('payment_settings_reauth_user', (int) $request->user()?->id);
    }

    private function hasRecentProductionReauth(Request $request): bool
    {
        $at = (int) $request->session()->get('payment_settings_reauth_at', 0);
        $userId = (int) $request->session()->get('payment_settings_reauth_user', 0);

        return $at > 0
            && $userId === (int) $request->user()?->id
            && (now()->timestamp - $at) < 900;
    }
}
