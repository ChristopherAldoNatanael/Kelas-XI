<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherAttendance;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class TeacherAttendanceController extends Controller
{
    /**
     * Display a listing of teacher attendances
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = TeacherAttendance::with(['schedule', 'guru:id,nama,email', 'guruAsli:id,nama,email']);

            // Filter by date if provided
            if ($request->has('tanggal')) {
                $query->where('tanggal', $request->tanggal);
            }

            // Filter by guru_id if provided
            if ($request->has('guru_id')) {
                $query->where('guru_id', $request->guru_id);
            }

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $attendances = $query->orderBy('tanggal', 'desc')
                ->orderBy('jam_masuk')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Data kehadiran guru berhasil diambil',
                'data' => $attendances
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kehadiran guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store teacher attendance
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'required|exists:schedules,id',
            'guru_id' => 'required|exists:users,id',
            'tanggal' => 'required|date',
            'jam_masuk' => 'nullable|date_format:H:i:s',
            'status' => 'required|in:hadir,telat,tidak_hadir,diganti',
            'keterangan' => 'nullable|string|max:500',
            'guru_asli_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attendance = TeacherAttendance::create([
                'schedule_id' => $request->schedule_id,
                'guru_id' => $request->guru_id,
                'guru_asli_id' => $request->guru_asli_id,
                'tanggal' => $request->tanggal,
                'jam_masuk' => $request->jam_masuk,
                'status' => $request->status,
                'keterangan' => $request->keterangan,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data kehadiran guru berhasil disimpan',
                'data' => $attendance->load(['schedule', 'guru:id,nama,email'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data kehadiran guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display specific teacher attendance
     */
    public function show(string $id): JsonResponse
    {
        try {
            $attendance = TeacherAttendance::with([
                'schedule',
                'guru:id,nama,email',
                'guruAsli:id,nama,email',
                'createdBy:id,name',
                'assignedBy:id,name'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data kehadiran guru berhasil diambil',
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data kehadiran guru tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update teacher attendance
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'jam_masuk' => 'nullable|date_format:H:i:s',
            'status' => 'required|in:hadir,telat,tidak_hadir,diganti',
            'keterangan' => 'nullable|string|max:500',
            'guru_asli_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attendance = TeacherAttendance::findOrFail($id);

            $attendance->update([
                'jam_masuk' => $request->jam_masuk ?? $attendance->jam_masuk,
                'status' => $request->status,
                'keterangan' => $request->keterangan,
                'guru_asli_id' => $request->guru_asli_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data kehadiran guru berhasil diupdate',
                'data' => $attendance->load(['schedule', 'guru:id,nama,email'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate data kehadiran guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove teacher attendance
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $attendance = TeacherAttendance::findOrFail($id);
            $attendance->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data kehadiran guru berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data kehadiran guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
