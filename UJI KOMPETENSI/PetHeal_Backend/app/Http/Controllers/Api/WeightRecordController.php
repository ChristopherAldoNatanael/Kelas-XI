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
    public function index(int $petId): JsonResponse
    {
        $pet = Pet::findOrFail($petId);
        
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
                'weight_change' => $this->calculateWeightChange($records),
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
        
        $pet = Pet::findOrFail($petId);
        
        $record = $pet->weightRecords()->create([
            'weight' => $validated['weight'],
            'recorded_at' => $validated['recorded_at'] ?? now(),
            'notes' => $validated['notes'] ?? null,
        ]);
        
        // Update pet's current weight
        $pet->update(['weight' => $validated['weight']]);
        
        return response()->json([
            'success' => true,
            'message' => 'Weight recorded successfully',
            'data' => $record,
        ], 201);
    }

    /**
     * Delete a weight record.
     */
    public function destroy(int $petId, int $recordId): JsonResponse
    {
        $pet = Pet::findOrFail($petId);
        $record = $pet->weightRecords()->findOrFail($recordId);
        $record->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Weight record deleted',
        ]);
    }

    /**
     * Calculate weight change between first and last record.
     */
    private function calculateWeightChange($records)
    {
        if ($records->count() < 2) {
            return null;
        }
        
        $firstWeight = $records->first()->weight;
        $lastWeight = $records->last()->weight;
        
        return [
            'absolute' => round($lastWeight - $firstWeight, 2),
            'percentage' => round((($lastWeight - $firstWeight) / $firstWeight) * 100, 2),
            'trend' => $lastWeight > $firstWeight ? 'gaining' : ($lastWeight < $firstWeight ? 'losing' : 'stable'),
        ];
    }
}
