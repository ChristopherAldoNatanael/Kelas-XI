<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $subjects = Subject::all()->map(function ($subject) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'created_at' => $subject->created_at,
                    'updated_at' => $subject->updated_at
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil diambil',
                'data' => $subjects
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mata pelajaran',
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
            // Hanya validasi field yang ada di database (nama dan kode)
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:subjects,kode',
            ]);

            // Map API field names ke database field names
            $createData = [
                'nama' => $validated['name'],
                'kode' => $validated['code']
            ];

            $subject = Subject::create($createData);

            return response()->json([
                'success' => true,
                'message' => 'Mata pelajaran berhasil ditambahkan',
                'data' => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'created_at' => $subject->created_at,
                    'updated_at' => $subject->updated_at
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan mata pelajaran',
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
            $subject = Subject::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil diambil',
                'data' => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'created_at' => $subject->created_at,
                    'updated_at' => $subject->updated_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Mata pelajaran tidak ditemukan',
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
            $subject = Subject::findOrFail($id);

            // Hanya validasi field yang ada di database (nama dan kode)
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:50|unique:subjects,kode,' . $id,
            ]);

            // Map API field names ke database field names
            $updateData = [];
            if (isset($validated['name'])) {
                $updateData['nama'] = $validated['name'];
            }
            if (isset($validated['code'])) {
                $updateData['kode'] = $validated['code'];
            }

            $subject->update($updateData);

            // Reload subject untuk mendapatkan data terbaru dengan accessor
            $subject->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Mata pelajaran berhasil diperbarui',
                'data' => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'created_at' => $subject->created_at,
                    'updated_at' => $subject->updated_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui mata pelajaran',
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
            $subject = Subject::findOrFail($id);
            $subject->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mata pelajaran berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus mata pelajaran',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subjects with their teachers
     */
    public function getSubjectsWithTeachers(): JsonResponse
    {
        try {
            $subjects = Subject::with(['teachers.user', 'primaryTeacher.user'])
                ->active()
                ->get()
                ->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code,
                        'category' => $subject->category,
                        'description' => $subject->description,
                        'credit_hours' => $subject->credit_hours,
                        'semester' => $subject->semester,
                        'status' => $subject->status,
                        'teachers' => $subject->teachers->map(function ($teacher) {
                            return [
                                'id' => $teacher->id,
                                'teacher_code' => $teacher->teacher_code,
                                'name' => $teacher->user->nama,
                                'position' => $teacher->position,
                                'department' => $teacher->department,
                                'expertise' => $teacher->expertise,
                                'is_primary' => $teacher->pivot->is_primary
                            ];
                        }),
                        'primary_teacher' => $subject->primaryTeacher->first() ? [
                            'id' => $subject->primaryTeacher->first()->id,
                            'teacher_code' => $subject->primaryTeacher->first()->teacher_code,
                            'name' => $subject->primaryTeacher->first()->user->nama,
                            'position' => $subject->primaryTeacher->first()->position,
                            'department' => $subject->primaryTeacher->first()->department,
                            'expertise' => $subject->primaryTeacher->first()->expertise,
                        ] : null
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran dengan guru berhasil diambil',
                'data' => $subjects
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mata pelajaran dengan guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teachers by subject
     */
    public function getTeachersBySubject($subjectId): JsonResponse
    {
        try {
            $subject = Subject::with(['teachers.user'])->findOrFail($subjectId);

            $teachers = $subject->teachers->map(function ($teacher) {
                return [
                    'id' => $teacher->id,
                    'teacher_code' => $teacher->teacher_code,
                    'name' => $teacher->user->nama,
                    'position' => $teacher->position,
                    'department' => $teacher->department,
                    'expertise' => $teacher->expertise,
                    'is_primary' => $teacher->pivot->is_primary,
                    'status' => $teacher->status
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data guru untuk mata pelajaran ' . $subject->name . ' berhasil diambil',
                'data' => [
                    'subject' => [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code,
                        'category' => $subject->category
                    ],
                    'teachers' => $teachers
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
