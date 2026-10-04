<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\Vaccination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaccinationController extends Controller
{
    /**
     * Get all vaccinations for a pet.
     */
    public function index(Request $request, int $petId): JsonResponse
    {
        $pet = $request->user()->pets()->findOrFail($petId);
        
        $vaccinations = $pet->vaccinations()
            ->latest('date_administered')
            ->paginate(20);
        
        $upcoming = $pet->vaccinations()
            ->upcomingDue()
            ->orderBy('next_due_date', 'asc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'pet_id' => $petId,
                'pet_name' => $pet->name,
                'vaccinations' => $vaccinations->items(),
                'upcoming_due' => $upcoming,
                'pagination' => [
                    'current_page' => $vaccinations->currentPage(),
                    'last_page' => $vaccinations->lastPage(),
                    'per_page' => $vaccinations->perPage(),
                    'total' => $vaccinations->total(),
                ],
            ]
        ]);
    }

    /**
     * Record a new vaccination.
     */
    public function store(Request $request, int $petId): JsonResponse
    {
        $validated = $request->validate([
            'vaccine_name' => 'required|string|max:100',
            'batch_number' => 'nullable|string|max:100',
            // PHASE 3 (B13): an administered date in the future is nonsense.
            'date_administered' => 'required|date|before_or_equal:today',
            'next_due_date' => 'nullable|date|after_or_equal:date_administered',
            'veterinarian' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:500',
        ]);
        
        $pet = $request->user()->pets()->findOrFail($petId);
        
        $vaccination = $pet->vaccinations()->create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Vaccination recorded successfully',
            'data' => $vaccination,
        ], 201);
    }

    /**
     * Update a vaccination record.
     */
    public function update(Request $request, int $petId, int $vaccinationId): JsonResponse
    {
        $pet = $request->user()->pets()->findOrFail($petId);
        $vaccination = $pet->vaccinations()->findOrFail($vaccinationId);

        $validated = $request->validate([
            'vaccine_name' => 'sometimes|string|max:100',
            'batch_number' => 'nullable|string|max:100',
            // PHASE 3 (B13): see store(). Cross-field check below resolves
            // against the stored date when the request omits it.
            'date_administered' => 'sometimes|date|before_or_equal:today',
            'next_due_date' => 'nullable|date',
            'veterinarian' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:500',
            'reminder_sent' => 'sometimes|boolean',
        ]);

        // PHASE 3 (B13): `after_or_equal:date_administered` breaks when only
        // next_due_date is patched (reference resolves to null). Compare
        // against the effective administered date instead (frozen 422 shape).
        if (array_key_exists('next_due_date', $validated) && $validated['next_due_date'] !== null) {
            $effectiveAdministered = $validated['date_administered']
                ?? $vaccination->date_administered?->format('Y-m-d')
                ?? $vaccination->getRawOriginal('date_administered');
            if ($effectiveAdministered && $validated['next_due_date'] < $effectiveAdministered) {
                return response()->json([
                    'success' => false,
                    'message' => 'The next due date must be a date after or equal to date administered.',
                    'errors' => ['next_due_date' => ['The next due date must be a date after or equal to date administered.']],
                ], 422);
            }
        }

        $vaccination->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Vaccination updated successfully',
            'data' => $vaccination,
        ]);
    }

    /**
     * Delete a vaccination record.
     */
    public function destroy(Request $request, int $petId, int $vaccinationId): JsonResponse
    {
        $pet = $request->user()->pets()->findOrFail($petId);
        $vaccination = $pet->vaccinations()->findOrFail($vaccinationId);
        $vaccination->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Vaccination record deleted',
        ]);
    }
}
