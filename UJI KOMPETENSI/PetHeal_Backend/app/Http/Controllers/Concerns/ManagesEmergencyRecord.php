<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\MedicalRecord;
use App\Models\TreatmentConsent;
use App\Services\ConsentService;
use App\Services\EmergencyEncounterService;
use Illuminate\Http\Request;

/**
 * Aksi encounter darurat dari halaman rekam medis admin/dokter.
 *
 * Controller pemakai wajib menyediakan resolveEmergencyRecord() yang sudah
 * menerapkan scope klinik/dokter.
 */
trait ManagesEmergencyRecord
{
    abstract protected function resolveEmergencyRecord(int $id): MedicalRecord;

    public function emergencyConsent(Request $request, int $id)
    {
        $record = $this->resolveEmergencyRecord($id);
        $this->assertEmergencyRecord($record);

        $validated = $request->validate([
            'emergency_basis' => 'required|in:' . implode(',', TreatmentConsent::EMERGENCY_BASES),
            'signer_name' => 'required|string|max:120',
            'signer_relation' => 'required|string|max:40',
            'witness_name' => 'nullable|string|max:120',
            'risks_explained' => 'required|string|max:20000',
        ]);

        try {
            app(ConsentService::class)->recordEmergencyConsent(
                $record,
                $validated,
                auth()->user()
            );
        } catch (MedicalRecordLifecycleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Consent darurat tercatat.');
    }

    public function emergencyTriage(Request $request, int $id)
    {
        $record = $this->resolveEmergencyRecord($id);
        $this->assertEmergencyRecord($record);

        $encounter = $record->encounter;

        if (! $encounter) {
            return back()->with('error', 'Encounter darurat belum terbentuk.');
        }

        $validated = $request->validate([
            'triage_level' => 'required|in:' . implode(',', EmergencyEncounterService::TRIAGE_LEVELS),
            'chief_complaint' => 'nullable|string|max:20000',
        ]);

        try {
            app(EmergencyEncounterService::class)->updateTriage(
                $encounter,
                $validated['triage_level'],
                $validated['chief_complaint'] ?? null,
                auth()->user()
            );
        } catch (MedicalRecordLifecycleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Triage darurat diperbarui.');
    }

    public function emergencyClose(Request $request, int $id)
    {
        $record = $this->resolveEmergencyRecord($id);
        $this->assertEmergencyRecord($record);

        $encounter = $record->encounter;

        if (! $encounter) {
            return back()->with('error', 'Encounter darurat belum terbentuk.');
        }

        $validated = $request->validate([
            'disposition' => 'required|string|max:30',
        ]);

        try {
            app(EmergencyEncounterService::class)->close(
                $encounter,
                $validated['disposition'],
                auth()->user()
            );
        } catch (MedicalRecordLifecycleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Encounter darurat ditutup dengan disposisi: ' . $validated['disposition'] . '.');
    }

    private function assertEmergencyRecord(MedicalRecord $record): void
    {
        $isEmergency = $record->booking?->service_type === 'emergency'
            || $record->encounter?->type === 'emergency';

        if (! $isEmergency) {
            abort(404);
        }
    }
}
