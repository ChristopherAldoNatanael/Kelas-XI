<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Carbon\Carbon;

/**
 * Controller untuk siswa melaporkan kehadiran guru
 * - Siswa hanya bisa melaporkan: hadir, telat, tidak_hadir
 * - Opsi "diganti" hanya tersedia di web admin
 */
class SiswaKehadiranGuruController extends Controller
{
    /**
     * Helper: Manual auth check (bypass Sanctum middleware bug)
     */
    private function getAuthenticatedUser(Request $request): ?User
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
     * GET /api/siswa/kehadiran-guru/today
     * ULTRA LIGHTWEIGHT - Ambil jadwal hari ini untuk kelas siswa
     */
    public function todaySchedule(Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser($request);
            if (!$user || $user->role !== 'siswa') {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $classId = $user->class_id;
            if (!$classId) {
                return response()->json(['success' => false, 'message' => 'No class assigned'], 400);
            }

            // Get today's day in Indonesian
            $dayMap = ['Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                      'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'];
            $hariIni = $dayMap[date('l')] ?? date('l');
            $tanggalHariIni = date('Y-m-d');

            // ULTRA SIMPLE query - no relationships, just basic fields
            $schedules = \DB::select("
                SELECT s.id, s.jam_mulai, s.jam_selesai, s.mata_pelajaran, s.ruang,
                       COALESCE(g.nama, 'Guru') as teacher_name,
                       COALESCE(sub.nama, s.mata_pelajaran) as subject_name
                FROM schedules s
                LEFT JOIN users g ON s.guru_id = g.id
                LEFT JOIN subjects sub ON s.subject_id = sub.id
                WHERE s.class_id = ? AND s.hari = ?
                ORDER BY s.jam_mulai
                LIMIT 20
            ", [$classId, $hariIni]);

            // Get attendance data separately - ultra simple
            $scheduleIds = array_column($schedules, 'id');
            $attendances = [];
            if (!empty($scheduleIds)) {
                $attendanceData = \DB::select("
                    SELECT schedule_id, status, keterangan, DATE_FORMAT(created_at, '%H:%i') as submitted_at
                    FROM teacher_attendances
                    WHERE schedule_id IN (" . str_repeat('?,', count($scheduleIds) - 1) . "?)
                    AND tanggal = ?
                ", array_merge($scheduleIds, [$tanggalHariIni]));

                foreach ($attendanceData as $att) {
                    $attendances[$att->schedule_id] = $att;
                }
            }

            // Build ultra simple response
            $result = [];
            $periodNumber = 1;
            foreach ($schedules as $schedule) {
                $attendance = $attendances[$schedule->id] ?? null;

                $result[] = [
                    'schedule_id' => $schedule->id,
                    'period' => $periodNumber++,
                    'time' => ($schedule->jam_mulai ? date('H:i', strtotime($schedule->jam_mulai)) : '00:00') . ' - ' .
                             ($schedule->jam_selesai ? date('H:i', strtotime($schedule->jam_selesai)) : '00:00'),
                    'subject' => $schedule->subject_name ?: 'Mata Pelajaran',
                    'teacher' => $schedule->teacher_name ?: 'Guru',
                    'submitted' => $attendance !== null,
                    'status' => $attendance ? $attendance->status : null,
                    'catatan' => $attendance ? $attendance->keterangan : null,
                    'submitted_at' => $attendance ? $attendance->submitted_at : null,
                ];
            }

            return response()->json([
                'success' => true,
                'tanggal' => $tanggalHariIni,
                'hari' => $hariIni,
                'schedules' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/siswa/kehadiran-guru/submit
     * ULTRA LIGHTWEIGHT - Siswa melaporkan kehadiran guru
     */
    public function submitKehadiran(Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser($request);
            if (!$user || $user->role !== 'siswa') {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // Simple validation
            $scheduleId = $request->input('schedule_id');
            $status = $request->input('status');
            $catatan = $request->input('catatan', '');

            if (!$scheduleId || !in_array($status, ['hadir', 'telat', 'tidak_hadir'])) {
                return response()->json(['success' => false, 'message' => 'Invalid input'], 400);
            }

            $classId = $user->class_id;
            $tanggal = date('Y-m-d');
            $jamMasuk = in_array($status, ['hadir', 'telat']) ? date('H:i:s') : null;

            // ULTRA SIMPLE: Check if schedule belongs to student's class
            $scheduleCheck = \DB::selectOne("
                SELECT id FROM schedules
                WHERE id = ? AND class_id = ?
                LIMIT 1
            ", [$scheduleId, $classId]);

            if (!$scheduleCheck) {
                return response()->json(['success' => false, 'message' => 'Invalid schedule'], 403);
            }

            // Check if attendance already exists
            $existing = \DB::selectOne("
                SELECT id FROM teacher_attendances
                WHERE schedule_id = ? AND tanggal = ?
                LIMIT 1
            ", [$scheduleId, $tanggal]);

            if ($existing) {
                // Update existing
                \DB::update("
                    UPDATE teacher_attendances
                    SET status = ?, keterangan = ?, jam_masuk = ?, updated_at = NOW()
                    WHERE id = ?
                ", [$status, $catatan, $jamMasuk, $existing->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Attendance updated successfully',
                    'data' => ['id' => $existing->id, 'schedule_id' => $scheduleId, 'status' => $status]
                ]);
            } else {
                // Insert new - get teacher_id from schedule
                $teacherId = \DB::selectOne("
                    SELECT COALESCE(guru_id, teacher_id) as teacher_id
                    FROM schedules WHERE id = ? LIMIT 1
                ", [$scheduleId])->teacher_id;

                $newId = \DB::insert("
                    INSERT INTO teacher_attendances
                    (schedule_id, guru_id, tanggal, jam_masuk, status, keterangan, created_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [$scheduleId, $teacherId, $tanggal, $jamMasuk, $status, $catatan, $user->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Attendance submitted successfully',
                    'data' => ['id' => \DB::getPdo()->lastInsertId(), 'schedule_id' => $scheduleId, 'status' => $status]
                ], 201);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/siswa/kehadiran-guru/riwayat
     * ULTRA LIGHTWEIGHT - Riwayat laporan kehadiran guru
     */
    public function riwayat(Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser($request);
            if (!$user || $user->role !== 'siswa') {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $classId = $user->class_id;
            if (!$classId) {
                return response()->json(['success' => false, 'message' => 'No class assigned'], 400);
            }

            // Ultra simple pagination
            $page = max(1, (int)$request->get('page', 1));
            $limit = min(max(1, (int)$request->get('limit', 20)), 50);
            $offset = ($page - 1) * $limit;

            // ULTRA SIMPLE raw query - no relationships
            $riwayat = \DB::select("
                SELECT ta.id, ta.tanggal, ta.status, ta.keterangan,
                       DATE_FORMAT(ta.created_at, '%H:%i') as submitted_at,
                       s.mata_pelajaran, s.jam_mulai, s.jam_selesai, s.period,
                       COALESCE(u.nama, 'Guru') as teacher_name
                FROM teacher_attendances ta
                INNER JOIN schedules s ON ta.schedule_id = s.id
                LEFT JOIN users u ON ta.guru_id = u.id
                WHERE s.class_id = ?
                ORDER BY ta.tanggal DESC, ta.created_at DESC
                LIMIT ? OFFSET ?
            ", [$classId, $limit, $offset]);

            // Get total count
            $total = \DB::selectOne("
                SELECT COUNT(*) as total
                FROM teacher_attendances ta
                INNER JOIN schedules s ON ta.schedule_id = s.id
                WHERE s.class_id = ?
            ", [$classId])->total;

            // Format results - ultra simple
            $result = [];
            $dayMap = ['Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                      'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'];

            foreach ($riwayat as $item) {
                $englishDay = date('l', strtotime($item->tanggal));
                $hari = $dayMap[$englishDay] ?? $englishDay;

                $result[] = [
                    'id' => $item->id,
                    'tanggal' => $item->tanggal,
                    'day' => $hari,
                    'period' => $item->period ?: 1,
                    'time' => ($item->jam_mulai ? date('H:i', strtotime($item->jam_mulai)) : '00:00') . ' - ' .
                             ($item->jam_selesai ? date('H:i', strtotime($item->jam_selesai)) : '00:00'),
                    'subject' => $item->mata_pelajaran ?: 'Mata Pelajaran',
                    'teacher' => $item->teacher_name ?: 'Guru',
                    'status' => $item->status,
                    'catatan' => $item->keterangan,
                    'submitted_at' => $item->submitted_at,
                ];
            }

            // Simple pagination info
            $totalPages = ceil($total / $limit);

            return response()->json([
                'success' => true,
                'data' => $result,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_next_page' => $page < $totalPages,
                    'has_prev_page' => $page > 1
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
