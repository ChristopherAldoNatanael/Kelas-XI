<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MedicalRecordController extends Controller
{
    /**
     * Get all medical records for authenticated user's pets
     */
    public function index(Request $request)
    {
        try {
            $petIds = $request->user()->pets()->pluck('id');

            // ✅ FIXED: Booking already has $with = ['pet', 'doctor'], so just load booking
            $records = MedicalRecord::whereIn('pet_id', $petIds)
                ->with(['booking', 'pet', 'doctor'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $records->items(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MedicalRecord index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load medical records.',
            ], 500);
        }
    }

    /**
     * Get medical records for a specific pet
     */
    public function getByPet(Request $request, $petId)
    {
        try {
            $pet = $request->user()->pets()->find($petId);
            if (!$pet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pet not found',
                ], 404);
            }

            // ✅ FIXED: Booking already has $with = ['pet', 'doctor']
            $records = MedicalRecord::where('pet_id', $petId)
                ->with(['booking', 'pet', 'doctor'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $records->items(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MedicalRecord getByPet error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load records for this pet.',
            ], 500);
        }
    }

    /**
     * Get single medical record details
     */
    public function show(Request $request, $id)
    {
        try {
            $petIds = $request->user()->pets()->pluck('id');

            // ✅ FIXED: Don't load nested relationships manually since Booking already has $with
            $record = MedicalRecord::whereIn('pet_id', $petIds)
                ->with(['booking', 'pet', 'doctor'])
                ->find($id);

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Medical record not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $record,
            ]);
        } catch (\Exception $e) {
            Log::error('MedicalRecord show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load medical record details.',
            ], 500);
        }
    }
}
