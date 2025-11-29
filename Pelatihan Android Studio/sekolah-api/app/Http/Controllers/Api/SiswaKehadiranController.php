<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\TeacherAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Controller untuk fitur Kehadiran Guru dari sisi Siswa
 * - Menampilkan jadwal hari ini
 * - Submit status kehadiran guru (hadir/telat/tidak_hadir)
 * - Riwayat kehadiran
 */
class SiswaKehadiranController extends Controller
{
    /**
     * Manual token authentication helper
     */
    private function authenticateManual(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            return null;
        }

        return $accessToken->tokenable;
    }

    /**
     * Get today's schedule for siswa with attendance status
     * Endpoint: GET /api/siswa/kehadiran/today
     */
    public function todaySchedule(Request $request): JsonResponse
    {
        try {
            // Manual authentication
            $user = $this->authenticateManual($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid atau tidak ditemukan',
                    'tanggal' => date('Y-m-d'),
                    'dayOfWeek' => $this->getDayName(),
                    'schedules' => []
                ], 401);
            }

            // Get user's class
            $classId = $user->class_id;
            if (!$classId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Siswa belum memiliki kelas',
                    'tanggal' => date('Y-m-d'),
                    'dayOfWeek' => $this->getDayName(),
                    'schedules' => []
                ]);
            }

            // Get class name
            $userClass = DB::table('classes')->where('id', $classId)->first();
            if (!$userClass) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kelas tidak ditemukan',
                    'tanggal' => date('Y-m-d'),
                    'dayOfWeek' => $this->getDayName(),
                    'schedules' => []
                ]);
            }

            $className = $userClass->nama_kelas;
            $today = date('Y-m-d');
            $dayName = $this->getDayName();

            // Simple query to get today's schedules for the class
            $schedules = DB::table('schedules')
                ->select([
                    'schedules.id as schedule_id',
                    'schedules.mata_pelajaran',
                    'schedules.jam_mulai',
                    'schedules.jam_selesai',
                    'schedules.guru_id',
                    'teachers.nama as guru_nama'
                ])
                ->leftJoin('teachers', 'schedules.guru_id', '=', 'teachers.id')
                ->where('schedules.kelas', $className)
                ->where('schedules.hari', $dayName)
                ->orderBy('schedules.jam_mulai')
                ->limit(10)  // Limit results to prevent heavy load
                ->get();

            // Get existing attendance records for today
            $scheduleIds = $schedules->pluck('schedule_id')->toArray();
            $existingAttendances = collect(); // Initialize as empty collection
            if (!empty($scheduleIds)) {
                $existingAttendances = DB::table('teacher_attendances')
                    ->whereIn('schedule_id', $scheduleIds)
                    ->where('tanggal', $today)
                    ->get()
                    ->keyBy('schedule_id');
            }

            // Build response with attendance status
            $scheduleItems = [];
            $periodNumber = 1;

            foreach ($schedules as $schedule) {
                $attendance = $existingAttendances->get($schedule->schedule_id);

                // Format time
                $jamMulai = $schedule->jam_mulai;
                $jamSelesai = $schedule->jam_selesai;

                // Remove seconds if present for cleaner display
                if ($jamMulai && strlen($jamMulai) > 5) {
                    $jamMulai = substr($jamMulai, 0, 5);
                }
                if ($jamSelesai && strlen($jamSelesai) > 5) {
                    $jamSelesai = substr($jamSelesai, 0, 5);
                }

                $scheduleItems[] = [
                    'scheduleId' => (int) $schedule->schedule_id,
                    'period' => $periodNumber,
                    'time' => $jamMulai . ' - ' . $jamSelesai,
                    'subject' => $schedule->mata_pelajaran ?? 'Tidak ada mapel',
                    'teacher' => $schedule->guru_nama ?? 'Tidak ada guru',
                    'teacherId' => (int) ($schedule->guru_id ?? 0),
                    'submitted' => $attendance !== null,
                    'status' => $attendance ? $attendance->status : null,
                    'catatan' => $attendance ? $attendance->keterangan : ''
                ];

                $periodNumber++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Jadwal hari ini berhasil dimuat',
                'tanggal' => $today,
                'dayOfWeek' => $dayName,
                'kelas' => $className,
                'schedules' => $scheduleItems
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'tanggal' => date('Y-m-d'),
                'dayOfWeek' => $this->getDayName(),
                'schedules' => []
            ], 500);
        }
    }

    /**
     * Submit teacher attendance by siswa
     * Endpoint: POST /api/siswa/kehadiran
     *
     * Body: {
     *   "schedule_id": 123,
     *   "tanggal": "2025-01-20",
     *   "status": "hadir|telat|tidak_hadir",
     *   "keterangan": "optional notes"
     * }
     */
    public function submitAttendance(Request $request): JsonResponse
    {
        try {
            // Manual authentication
            $user = $this->authenticateManual($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid atau tidak ditemukan'
                ], 401);
            }

            // Validate input
            $validator = Validator::make($request->all(), [
                'schedule_id' => 'required|integer|exists:schedules,id',
                'tanggal' => 'required|date',
                'status' => 'required|in:hadir,telat,tidak_hadir',
                'keterangan' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $scheduleId = $request->schedule_id;
            $tanggal = $request->tanggal;
            $status = $request->status;
            $keterangan = $request->keterangan ?? '';

            // Get schedule to find guru_id
            $schedule = DB::table('schedules')->where('id', $scheduleId)->first();
            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan'
                ], 404);
            }

            // Check if attendance already exists for this schedule and date
            $existingAttendance = DB::table('teacher_attendances')
                ->where('schedule_id', $scheduleId)
                ->where('tanggal', $tanggal)
                ->first();

            if ($existingAttendance) {
                // Update existing record
                DB::table('teacher_attendances')
                    ->where('id', $existingAttendance->id)
                    ->update([
                        'status' => $status,
                        'keterangan' => $keterangan,
                        'updated_at' => now()
                    ]);

                $message = 'Status kehadiran guru berhasil diperbarui';
            } else {
                // Create new record
                DB::table('teacher_attendances')->insert([
                    'schedule_id' => $scheduleId,
                    'guru_id' => $schedule->guru_id,
                    'tanggal' => $tanggal,
                    'status' => $status,
                    'keterangan' => $keterangan,
                    'created_by' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $message = 'Status kehadiran guru berhasil disimpan';
            }

            Log::info('siswa/kehadiran submit', [
                'user_id' => $user->id,
                'schedule_id' => $scheduleId,
                'status' => $status,
                'tanggal' => $tanggal
            ]);

            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            Log::error('siswa/kehadiran submit error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance history for siswa's class
     * Endpoint: GET /api/siswa/kehadiran/riwayat
     */
    public function riwayat(Request $request): JsonResponse
    {
        try {
            // Manual authentication
            $user = $this->authenticateManual($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid',
                    'data' => []
                ], 401);
            }

            // Get user's class
            $classId = $user->class_id;
            if (!$classId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Siswa belum memiliki kelas',
                    'data' => []
                ]);
            }

            // Get class name
            $userClass = DB::table('classes')->where('id', $classId)->first();
            if (!$userClass) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kelas tidak ditemukan',
                    'data' => []
                ]);
            }

            $className = $userClass->nama_kelas;

            // Get attendance history for the class (last 30 days)
            $history = DB::table('teacher_attendances')
                ->select([
                    'teacher_attendances.id',
                    'teacher_attendances.tanggal',
                    'teacher_attendances.status',
                    'teacher_attendances.keterangan',
                    'teacher_attendances.jam_masuk',
                    'teacher_attendances.created_at',
                    'schedules.mata_pelajaran',
                    'schedules.jam_mulai',
                    'schedules.jam_selesai',
                    'schedules.hari',
                    'teachers.nama as guru_nama'
                ])
                ->join('schedules', 'teacher_attendances.schedule_id', '=', 'schedules.id')
                ->leftJoin('teachers', 'teacher_attendances.guru_id', '=', 'teachers.id')
                ->where('schedules.kelas', $className)
                ->where('teacher_attendances.tanggal', '>=', now()->subDays(30)->toDateString())
                ->orderBy('teacher_attendances.tanggal', 'desc')
                ->orderBy('schedules.jam_mulai', 'desc')
                ->limit(100)
                ->get();

            $riwayatItems = [];
            foreach ($history as $item) {
                // Format time
                $jamMulai = $item->jam_mulai;
                $jamSelesai = $item->jam_selesai;
                if ($jamMulai && strlen($jamMulai) > 5) {
                    $jamMulai = substr($jamMulai, 0, 5);
                }
                if ($jamSelesai && strlen($jamSelesai) > 5) {
                    $jamSelesai = substr($jamSelesai, 0, 5);
                }

                // Format jam masuk guru (untuk status terlambat/hadir)
                $jamMasukGuru = null;
                if ($item->jam_masuk) {
                    $jamMasukGuru = substr($item->jam_masuk, 0, 5); // Format HH:MM
                }

                $riwayatItems[] = [
                    'id' => (int) $item->id,
                    'tanggal' => $item->tanggal,
                    'hari' => $item->hari ?? '',
                    'mapel' => $item->mata_pelajaran ?? '',
                    'guru' => $item->guru_nama ?? '',
                    'jam' => $jamMulai . ' - ' . $jamSelesai,
                    'jam_masuk' => $jamMasukGuru,
                    'status' => $item->status,
                    'keterangan' => $item->keterangan ?? ''
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Riwayat kehadiran berhasil dimuat',
                'data' => $riwayatItems
            ]);
        } catch (\Exception $e) {
            Log::error('siswa/kehadiran/riwayat error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get Indonesian day name for today
     */
    private function getDayName(): string
    {
        $days = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];

        return $days[date('l')] ?? date('l');
    }
}
