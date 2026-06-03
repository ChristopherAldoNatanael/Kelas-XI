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
    public function index(int $petId): JsonResponse
    {
        $pet = Pet::findOrFail($petId);
        
        $vaccinations = $pet->vaccinations()
            ->orderBy('date_administered', 'desc')
            ->latest()->paginate(20);
        
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
            'date_administered' => 'required|date',
            'next_due_date' => 'nullable|date|after_or_equal:date_administered',
            'veterinarian' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:500',
        ]);
        
        $pet = Pet::findOrFail($petId);
        
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
        $pet = Pet::findOrFail($petId);
        $vaccination = $pet->vaccinations()->findOrFail($vaccinationId);
        
        $validated = $request->validate([
            'vaccine_name' => 'sometimes|string|max:100',
            'batch_number' => 'nullable|string|max:100',
            'date_administered' => 'sometimes|date',
            'next_due_date' => 'nullable|date|after_or_equal:date_administered',
            'veterinarian' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:500',
            'reminder_sent' => 'sometimes|boolean',
        ]);
        
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
    public function destroy(int $petId, int $vaccinationId): JsonResponse
    {
        $pet = Pet::findOrFail($petId);
        $vaccination = $pet->vaccinations()->findOrFail($vaccinationId);
        $vaccination->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Vaccination record deleted',
        ]);
    }
}
