<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\ClassModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DropdownController extends Controller
{
    /**
     * Get all subjects for dropdown
     * OPTIMIZED: Cached for 10 minutes
     */
    public function getSubjects(): JsonResponse
    {
        try {
            $subjects = Cache::remember('dropdown_subjects', 600, function () {
                return Subject::select('id', 'nama', 'kode')
                    ->orderBy('nama')
                    ->get();
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
     * Get teachers by subject ID
     * OPTIMIZED: Cached for 10 minutes per subject
     */
    public function getTeachersBySubject($subjectId): JsonResponse
    {
        try {
            // Validate subject ID
            if (!is_numeric($subjectId) || $subjectId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID mata pelajaran tidak valid',
                    'data' => null
                ], 400);
            }

            $cacheKey = "dropdown_teachers_subject_{$subjectId}";

            $data = Cache::remember($cacheKey, 600, function () use ($subjectId) {
                $subject = Subject::find($subjectId);

                if (!$subject) {
                    return null;
                }

                $teachers = Teacher::where('mata_pelajaran', $subject->nama)
                    ->get()
                    ->map(function ($teacher) {
                        return [
                            'id' => $teacher->id,
                            'name' => $teacher->name,
                            'email' => $teacher->email,
                            'is_primary' => true // Since it's direct match, consider all as primary
                        ];
                    });

                return [
                    'primary_teacher' => $teachers->first(),
                    'support_teachers' => $teachers->skip(1)->values()
                ];
            });

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata pelajaran tidak ditemukan',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil diambil',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading teachers: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }


    /**
     * Get all data for dropdowns (subjects, teachers, classrooms)
     * OPTIMIZED: Cached for 10 minutes
     */
    public function getAllDropdownData(): JsonResponse
    {
        try {
            $data = Cache::remember('dropdown_all_data', 600, function () {
                // Get subjects with their teachers
                $subjects = Subject::get()
                    ->map(function ($subject) {
                        $teachers = Teacher::where('mata_pelajaran', $subject->nama)->get();
                        return [
                            'id' => $subject->id,
                            'name' => $subject->nama,
                            'code' => $subject->kode,
                            'teachers' => $teachers->map(function ($teacher) {
                                return [
                                    'id' => $teacher->id,
                                    'name' => $teacher->name,
                                    'email' => $teacher->email,
                                    'is_primary' => true
                                ];
                            })
                        ];
                    });

                return [
                    'subjects' => $subjects
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data dropdown berhasil diambil',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data dropdown',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get classes for dropdown (optional filters: major, level)
     * For RPL major, returns only 3 main classes: X RPL, XI RPL, XII RPL
     * Optimized with caching for better performance
     */
    public function getClasses(): JsonResponse
    {
        try {
            $major = request()->query('major');
            $level = request()->query('level');

            // Create cache key based on parameters
            $cacheKey = 'classes_dropdown_' . md5($major . '_' . $level);

            // Cache for 10 minutes since class data doesn't change frequently
            $data = Cache::remember($cacheKey, 600, function () use ($major, $level) {
                // Special handling for RPL major - return only 3 main classes
                if ($major === 'Rekayasa Perangkat Lunak') {
                    return ClassModel::select('id', 'nama_kelas as name', 'kode_kelas')
                        ->where('kode_kelas', 'like', '%RPL%')
                        ->where('status', 'active')
                        ->whereIn('name', ['X RPL', 'XI RPL', 'XII RPL']) // Only these 3 classes
                        ->orderBy('level')
                        ->get()
                        ->map(function ($class) {
                            return [
                                'id' => $class->id,
                                'name' => $class->name,
                                'level' => $class->level,
                                'major' => $class->major
                            ];
                        });
                }

                // Default behavior for other majors - optimized query
                $query = ClassModel::select('id', 'nama_kelas as name', 'kode_kelas');

                if ($major) {
                    $query->where('major', $major);
                }
                if ($level) {
                    $query->where('level', (int)$level);
                }

                return $query->orderBy('level')
                    ->orderBy('name')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil diambil',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
