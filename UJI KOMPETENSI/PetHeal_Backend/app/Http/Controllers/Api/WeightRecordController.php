<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\WeightRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeightRecordController extends Controller
{
    /**
     * Get weight history for a pet.
     */
    public function index(Request $request, int $petId): JsonResponse
    {
        $pet = $request->user()->pets()->findOrFail($petId);
        
        $records = $pet->weightRecords()
            ->chronological()
            ->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => [
                'pet_id' => $petId,
                'pet_name' => $pet->name,
                'current_weight' => $pet->weight,
                'records' => $records->items(),
                'weight_change' => $this->calculateWeightChange($pet),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]
        ]);
    }

    /**
     * Record a new weight measurement.
     */
    public function store(Request $request, int $petId): JsonResponse
    {
        $validated = $request->validate([
            'weight' => 'required|numeric|min:0.1|max:200',
            'recorded_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);
        
        $pet = $request->user()->pets()->findOrFail($petId);
        
        $record = $pet->weightRecords()->create([
            'weight' => $validated['weight'],
            'recorded_at' => $validated['recorded_at'] ?? now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        // PHASE 3 (B7): current weight = latest measurement by recorded_at,
        // not blindly the just-created row (backdated entries corrupted it).
        $this->refreshCurrentWeight($pet);
        
        return response()->json([
            'success' => true,
            'message' => 'Weight recorded successfully',
            'data' => $record,
        ], 201);
    }

    /**
     * Delete a weight record.
     */
    public function destroy(Request $request, int $petId, int $recordId): JsonResponse
    {
        $pet = $request->user()->pets()->findOrFail($petId);
        $record = $pet->weightRecords()->findOrFail($recordId);
        $record->delete();

        // PHASE 3 (B7): recompute after delete so current weight never
        // points at a deleted measurement.
        $this->refreshCurrentWeight($pet);
        
        return response()->json([
            'success' => true,
            'message' => 'Weight record deleted',
        ]);
    }

    /**
     * PHASE 3 (B7): derive current weight from the newest record.
     */
    private function refreshCurrentWeight(Pet $pet): void
    {
        $latest = $pet->weightRecords()
            ->orderBy('recorded_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $pet->update(['weight' => $latest?->weight]);
    }

    /**
     * Calculate weight change between first and last record across ALL records (not paginated).
     */
    private function calculateWeightChange(Pet $pet): ?array
    {
        $totalRecords = $pet->weightRecords()->count();
        if ($totalRecords < 2) {
            return null;
        }

        $firstWeight = (float) $pet->weightRecords()->oldest('recorded_at')->value('weight');
        $lastWeight = (float) $pet->weightRecords()->latest('recorded_at')->value('weight');

        // PHASE 3 (B7): zero guard (legacy rows may hold 0).
        if ($firstWeight <= 0) {
            return [
                'absolute' => round($lastWeight - $firstWeight, 2),
                'percentage' => null,
                'trend' => $lastWeight > $firstWeight ? 'gaining' : ($lastWeight < $firstWeight ? 'losing' : 'stable'),
            ];
        }

        return [
            'absolute' => round($lastWeight - $firstWeight, 2),
            'percentage' => round((($lastWeight - $firstWeight) / $firstWeight) * 100, 2),
            'trend' => $lastWeight > $firstWeight ? 'gaining' : ($lastWeight < $firstWeight ? 'losing' : 'stable'),
        ];
    }
}
