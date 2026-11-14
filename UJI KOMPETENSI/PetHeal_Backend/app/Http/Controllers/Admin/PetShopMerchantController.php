<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicMerchantConfig;
use App\Services\MerchantConfigurationService;
use App\Services\MidtransPartnerGateway;
use Illuminate\Http\Request;

/**
 * Konfigurasi merchant per klinik (super_admin).
 *
 * Halaman ini menentukan tujuan dana transaksi. clinic_admin tidak memiliki
 * akses; perubahan selalu diaudit.
 */
class PetShopMerchantController extends Controller
{
    public function __construct(
        private readonly MerchantConfigurationService $merchants,
        private readonly MidtransPartnerGateway $partnerGateway
    ) {}

    public function index()
    {
        $clinics = Clinic::orderBy('name')->get();

        $configs = ClinicMerchantConfig::all()->keyBy('clinic_id');

        $partnerReady = $this->partnerGateway->isConfigured();

        return view('admin.pet-shop.merchants.index', [
            'clinics' => $clinics,
            'configs' => $configs,
            'partnerReady' => $partnerReady,
            'environment' => config('services.midtrans.environment'),
            'statuses' => ClinicMerchantConfig::ONBOARDING_STATUSES,
        ]);
    }

    public function update(Request $request, Clinic $clinic)
    {
        $validated = $request->validate([
            'merchant_id' => 'nullable|string|max:64|regex:/^[A-Za-z0-9_-]+$/',
            'partner_id' => 'nullable|string|max:64|regex:/^[A-Za-z0-9_-]+$/',
            'environment' => 'required|in:sandbox,production',
            'onboarding_status' => 'required|in:' . implode(',', ClinicMerchantConfig::ONBOARDING_STATUSES),
            'commission_percent' => 'required|numeric|min:0|max:100',
            'is_online_payment_enabled' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $online = $request->boolean('is_online_payment_enabled');

        if ($online && ($validated['onboarding_status'] !== ClinicMerchantConfig::STATUS_ACTIVE || blank($validated['merchant_id'] ?? null))) {
            return back()->withErrors([
                'is_online_payment_enabled' => __('petshop.merchant_must_be_active'),
            ])->withInput();
        }

        $this->merchants->upsert($clinic, [
            'merchant_id' => $validated['merchant_id'] ?? null,
            'partner_id' => $validated['partner_id'] ?? null,
            'environment' => $validated['environment'],
            'onboarding_status' => $validated['onboarding_status'],
            'is_online_payment_enabled' => $online,
            'commission_percent' => (float) $validated['commission_percent'],
            'notes' => $validated['notes'] ?? null,
        ], $request->user());

        return back()->with('success', __('petshop.merchant_updated', ['clinic' => $clinic->name]));
    }
}
