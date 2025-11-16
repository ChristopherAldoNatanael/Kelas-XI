<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $teachers = Teacher::with('user')->get();

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil diambil',
                'data' => $teachers
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'nip' => 'required|string|max:50|unique:teachers',
                'teacher_code' => 'required|string|max:50|unique:teachers',
                'position' => 'required|string|max:100',
                'department' => 'required|string|max:100',
                'expertise' => 'nullable|string|max:255',
                'certification' => 'nullable|string|max:255',
                'join_date' => 'required|date',
                'status' => 'required|in:active,inactive'
            ]);

            $teacher = Teacher::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Guru berhasil ditambahkan',
                'data' => $teacher->load('user')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $teacher = Teacher::with('user')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil diambil',
                'data' => $teacher
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Guru tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $teacher = Teacher::findOrFail($id);

            $validated = $request->validate([
                'user_id' => 'sometimes|required|exists:users,id',
                'nip' => 'sometimes|required|string|max:50|unique:teachers,nip,' . $id,
                'teacher_code' => 'sometimes|required|string|max:50|unique:teachers,teacher_code,' . $id,
                'position' => 'sometimes|required|string|max:100',
                'department' => 'sometimes|required|string|max:100',
                'expertise' => 'nullable|string|max:255',
                'certification' => 'nullable|string|max:255',
                'join_date' => 'sometimes|required|date',
                'status' => 'sometimes|required|in:active,inactive'
            ]);

            $teacher->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Guru berhasil diperbarui',
                'data' => $teacher->load('user')
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $teacher = Teacher::findOrFail($id);
            $teacher->delete();

            return response()->json([
                'success' => true,
                'message' => 'Guru berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
