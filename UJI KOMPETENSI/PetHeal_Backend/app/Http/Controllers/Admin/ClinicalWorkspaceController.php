<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Http\Controllers\Controller;
use App\Models\AnimalWelfareAssessment;
use App\Models\ClinicalTemplate;
use App\Models\ConsentTemplate;
use App\Models\InpatientStay;
use App\Models\LabOrder;
use App\Models\MedicalRecordExport;
use App\Models\NotifiableDiseaseReport;
use App\Models\Referral;
use App\Services\ClinicalTemplateService;
use App\Services\ConsentService;
use App\Services\InpatientService;
use App\Services\ReferralService;
use Illuminate\Http\Request;

/**
 * Ruang kerja klinis untuk modul yang sebelumnya hanya tersedia di service:
 * laboratorium, rawat inap, rujukan, laporan penyakit, template, ekspor, dan
 * kesejahteraan hewan.
 */
class ClinicalWorkspaceController extends Controller
{
    public function index()
    {
        $clinicId = currentClinicId();

        if (! $clinicId) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'Pilih klinik terlebih dahulu.');
        }

        $labOrders = LabOrder::with(['medicalRecord.pet', 'items', 'specimens'])
            ->where('clinic_id', $clinicId)
            ->orderByDesc('ordered_at')
            ->paginate(25, ['*'], 'lab_page')
            ->withQueryString();

        $inpatients = InpatientStay::with(['record.pet', 'admittingDoctor', 'locationLogs'])
            ->where('clinic_id', $clinicId)
            ->orderByDesc('admitted_at')
            ->paginate(25, ['*'], 'inpatient_page')
            ->withQueryString();

        $referrals = Referral::with(['record.pet', 'record.doctor'])
            ->where('clinic_id', $clinicId)
            ->orderByDesc('created_at')
            ->paginate(25, ['*'], 'referral_page')
            ->withQueryString();

        $diseaseReports = NotifiableDiseaseReport::with(['record.pet', 'verifiedBy'])
            ->where('clinic_id', $clinicId)
            ->orderByDesc('indicated_at')
            ->paginate(25, ['*'], 'disease_page')
            ->withQueryString();

        $clinicalTemplates = ClinicalTemplate::where('clinic_id', $clinicId)
            ->where('is_current', true)
            ->orderBy('name')
            ->get();

        $consentTemplates = ConsentTemplate::where('clinic_id', $clinicId)
            ->where('is_current', true)
            ->orderBy('name')
            ->get();

        $exports = MedicalRecordExport::with(['record.pet'])
            ->where('clinic_id', $clinicId)
            ->orderByDesc('created_at')
            ->paginate(25, ['*'], 'exports_page')
            ->withQueryString();

        $welfare = AnimalWelfareAssessment::with(['record.pet', 'assessedBy'])
            ->where('clinic_id', $clinicId)
            ->orderByDesc('assessed_at')
            ->paginate(25, ['*'], 'welfare_page')
            ->withQueryString();

        return view('admin.clinical.index', compact(
            'labOrders',
            'inpatients',
            'referrals',
            'diseaseReports',
            'clinicalTemplates',
            'consentTemplates',
            'exports',
            'welfare'
        ));
    }

    public function discharge(Request $request, int $id)
    {
        $stay = InpatientStay::where('clinic_id', currentClinicId())->findOrFail($id);

        $validated = $request->validate([
            'discharge_summary' => 'required|string|max:20000',
            'discharge_instructions' => 'nullable|string|max:20000',
        ]);

        try {
            app(InpatientService::class)->discharge($stay, $validated, auth()->user());
        } catch (MedicalRecordLifecycleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pasien rawat inap dipulangkan.');
    }

    public function voidReferral(Request $request, int $id)
    {
        $referral = Referral::where('clinic_id', currentClinicId())->findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|max:20000',
        ]);

        try {
            app(ReferralService::class)->void($referral, (string) $validated['reason'], auth()->user());
        } catch (MedicalRecordLifecycleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rujukan dibatalkan dengan alasan tercatat.');
    }

    public function publishClinicalTemplate(Request $request)
    {
        $validated = $request->validate([
            'template_key' => 'required|string|max:80',
            'name' => 'required|string|max:150',
            'species' => 'nullable|string|max:40',
            'schema' => 'required|string',
        ]);

        try {
            app(ClinicalTemplateService::class)->publish(
                currentClinic(),
                $validated,
                auth()->user()
            );
        } catch (MedicalRecordLifecycleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Template klinis baru dipublikasikan.');
    }

    public function publishConsentTemplate(Request $request)
    {
        $validated = $request->validate([
            'template_key' => 'required|string|max:80',
            'name' => 'required|string|max:150',
            'consent_type' => 'required|string|max:30',
            'body' => 'required|string|max:20000',
        ]);

        try {
            app(ConsentService::class)->publishTemplate($validated, auth()->user());
        } catch (MedicalRecordLifecycleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Template consent baru dipublikasikan.');
    }
}
