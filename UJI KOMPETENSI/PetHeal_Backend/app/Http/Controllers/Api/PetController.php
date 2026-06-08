<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PetController extends Controller
{
    /**
     * Get all pets for authenticated user
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $pets = $request->user()->pets()
            ->with(['medicalRecords' => function ($query) {
                $query->latest()->limit(5);
            }])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $pets->items(),
            'pagination' => [
                'current_page' => $pets->currentPage(),
                'last_page' => $pets->lastPage(),
                'per_page' => $pets->perPage(),
                'total' => $pets->total(),
            ],
        ]);
    }

    /**
     * Get single pet details
     */
    public function show(Request $request, $id)
    {
        $pet = Pet::where('user_id', $request->user()->id)
            ->with(['medicalRecords.doctor', 'bookings.doctor'])
            ->find($id);

        if (!$pet) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $pet,
        ]);
    }

    /**
     * Create new pet
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'species'       => 'required|string|max:100',
            'breed'         => 'nullable|string|max:100',
            'age'           => 'nullable|integer|min:0',
            'weight'        => 'nullable|numeric|min:0',
            'gender'        => 'nullable|in:male,female,Male,Female',
            'date_of_birth' => 'nullable|date',
            'photo'         => 'nullable',
            'notes'         => 'nullable|string',
        ]);

        // Store relative path only (e.g. "pets/filename.jpg")
        // Android builds the full URL using its BASE_URL + "storage/" + path
        $photoPath = null;
        if ($request->hasFile('photo')) {
            // ✅ OPTIMIZED: Compress and resize to max 800px width, 80% quality
            $fileName = time() . '_' . uniqid() . '.jpg';
            $photoPath = \App\Services\ImageService::process(
                $request->file('photo'),
                'pets/' . $fileName,
                800,  // Max width 800px
                80    // 80% quality
            );
        } elseif ($request->filled('photo')) {
            $photoPath = $request->input('photo');
        }

        $gender = $request->input('gender');
        if ($gender) $gender = strtolower($gender);

        $pet = Pet::create([
            'user_id'       => $request->user()->id,
            'name'          => $request->input('name'),
            'species'       => $request->input('species'),
            'breed'         => $request->input('breed'),
            'age'           => $request->input('age'),
            'weight'        => $request->input('weight'),
            'gender'        => $gender,
            'date_of_birth' => $request->input('date_of_birth'),
            'photo'         => $photoPath,
            'notes'         => $request->input('notes'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pet created successfully',
            'data'    => $pet,
        ], 201);
    }

    /**
     * Create new pet with photo (multipart/form-data)
     */
    public function storeWithPhoto(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'species'       => 'required|string|max:100',
            'breed'         => 'nullable|string|max:100',
            'age'           => 'nullable|integer|min:0',
            'weight'        => 'nullable|numeric|min:0',
            'gender'        => 'nullable|in:male,female,Male,Female',
            'date_of_birth' => 'nullable|date',
            'photo'         => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
            'notes'         => 'nullable|string',
        ]);

        // Store relative path — Android builds full URL with BASE_URL
        // ✅ OPTIMIZED: Compress and resize to max 800px width, 80% quality
        $fileName = time() . '_' . uniqid() . '.jpg';
        $photoPath = \App\Services\ImageService::process(
            $request->file('photo'),
            'pets/' . $fileName,
            800,  // Max width 800px
            80    // 80% quality
        );

        $gender = $request->input('gender');
        if ($gender) $gender = strtolower($gender);

        $pet = Pet::create([
            'user_id'       => $request->user()->id,
            'name'          => $request->input('name'),
            'species'       => $request->input('species'),
            'breed'         => $request->input('breed'),
            'age'           => $request->input('age'),
            'weight'        => $request->input('weight'),
            'gender'        => $gender,
            'date_of_birth' => $request->input('date_of_birth'),
            'photo'         => $photoPath,
            'notes'         => $request->input('notes'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pet created successfully',
            'data'    => $pet,
        ], 201);
    }

    /**
     * Update pet
     */
    public function update(Request $request, $id)
    {
        $pet = Pet::where('user_id', $request->user()->id)->find($id);

        if (!$pet) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found',
            ], 404);
        }

        $request->validate([
            'name'          => 'sometimes|string|max:255',
            'species'       => 'sometimes|string|max:100',
            'breed'         => 'sometimes|nullable|string|max:100',
            'age'           => 'sometimes|nullable|integer|min:0',
            'weight'        => 'sometimes|nullable|numeric|min:0',
            'gender'        => 'sometimes|nullable|in:male,female,Male,Female',
            'date_of_birth' => 'sometimes|nullable|date',
            'photo'         => 'sometimes|nullable',
            'notes'         => 'sometimes|nullable|string',
        ]);

        $data = $request->only(['name', 'species', 'breed', 'age', 'weight', 'date_of_birth', 'notes']);

        if ($request->has('gender') && $request->input('gender') !== null) {
            $data['gender'] = strtolower($request->input('gender'));
        }

        // Handle photo update — store relative path
        if ($request->hasFile('photo')) {
            // Delete old file if it's a relative path in storage
            if ($pet->photo && !str_starts_with($pet->photo, 'http')) {
                Storage::disk('public')->delete($pet->photo);
            }
            // ✅ OPTIMIZED: Compress and resize
            $fileName = time() . '_' . uniqid() . '.jpg';
            $data['photo'] = \App\Services\ImageService::process(
                $request->file('photo'),
                'pets/' . $fileName,
                800,
                80
            );
        } elseif ($request->filled('photo')) {
            $data['photo'] = $request->input('photo');
        }

        $pet->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Pet updated successfully',
            'data'    => $pet,
        ]);
    }

    /**
     * Delete pet
     */
    public function destroy(Request $request, $id)
    {
        $pet = Pet::where('user_id', $request->user()->id)->find($id);

        if (!$pet) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found',
            ], 404);
        }

        // Delete photo file if it's a relative path (not an external URL)
        if ($pet->photo && !str_starts_with($pet->photo, 'http')) {
            Storage::disk('public')->delete($pet->photo);
        }

        $pet->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pet deleted successfully',
        ]);
    }

    /**
     * Upload pet photo (dedicated endpoint)
     */
    public function uploadPhoto(Request $request, $id)
    {
        $pet = Pet::where('user_id', $request->user()->id)->find($id);

        if (!$pet) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found',
            ], 404);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        // Delete old file (relative path)
        if ($pet->photo && !str_starts_with($pet->photo, 'http')) {
            Storage::disk('public')->delete($pet->photo);
        }

        // Store relative path — Android builds full URL with BASE_URL
        // ✅ OPTIMIZED: Compress and resize to max 800px width, 80% quality
        $fileName = time() . '_' . uniqid() . '.jpg';
        $relativePath = \App\Services\ImageService::process(
            $request->file('photo'),
            'pets/' . $fileName,
            800,
            80
        );

        $pet->photo = $relativePath;
        $pet->save();

        return response()->json([
            'success' => true,
            'message' => 'Photo uploaded successfully',
            'data'    => [
                'photo_url' => $relativePath,
            ],
        ]);
    }
}
