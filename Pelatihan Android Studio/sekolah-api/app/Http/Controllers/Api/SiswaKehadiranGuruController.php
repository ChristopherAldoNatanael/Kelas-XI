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

            // Get the class name from classes table
            $userClass = \DB::selectOne("SELECT nama_kelas FROM classes WHERE id = ? LIMIT 1", [$classId]);
            if (!$userClass) {
                return response()->json(['success' => false, 'message' => 'Class not found'], 400);
            }
            $className = $userClass->nama_kelas;

            // Get today's day in Indonesian
            $dayMap = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];
            $hariIni = $dayMap[date('l')] ?? date('l');
            $tanggalHariIni = date('Y-m-d');

            // FIXED: Use 'kelas' column with class NAME, not class_id
            // Join with teachers table using guru_id
            $schedules = \DB::select("
                SELECT s.id, s.jam_mulai, s.jam_selesai, s.mata_pelajaran, s.ruang, s.guru_id,
                       COALESCE(t.nama, 'Guru') as teacher_name
                FROM schedules s
                LEFT JOIN teachers t ON s.guru_id = t.id
                WHERE s.kelas = ? AND s.hari = ?
                ORDER BY s.jam_mulai
                LIMIT 20
            ", [$className, $hariIni]);

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

            // Get teacher IDs for leave checking
            $teacherIds = array_filter(array_column($schedules, 'guru_id'));
            $teachersOnLeave = [];
            if (!empty($teacherIds)) {
                $leaveData = \DB::select("
                    SELECT l.teacher_id, l.reason, l.custom_reason, l.substitute_teacher_id,
                           COALESCE(t.nama, '') as substitute_name
                    FROM leaves l
                    LEFT JOIN teachers t ON l.substitute_teacher_id = t.id
                    WHERE l.status = 'approved'
                    AND l.start_date <= ?
                    AND l.end_date >= ?
                    AND l.teacher_id IN (" . str_repeat('?,', count($teacherIds) - 1) . "?)
                ", array_merge([$tanggalHariIni, $tanggalHariIni], $teacherIds));

                foreach ($leaveData as $leave) {
                    $teachersOnLeave[$leave->teacher_id] = $leave;
                }
            }

            // Build ultra simple response
            $result = [];
            $periodNumber = 1;
            foreach ($schedules as $schedule) {
                $attendance = $attendances[$schedule->id] ?? null;
                $teacherLeave = $teachersOnLeave[$schedule->guru_id] ?? null;

                // Determine if teacher is on approved leave
                $isOnLeave = $teacherLeave !== null;
                $leaveReason = null;
                $substituteTeacher = null;

                if ($isOnLeave) {
                    // Get leave reason text
                    $leaveReason = match ($teacherLeave->reason) {
                        'sakit' => 'Guru sedang Sakit',
                        'cuti_tahunan' => 'Guru sedang Cuti Tahunan',
                        'urusan_keluarga' => 'Guru sedang Urusan Keluarga',
                        'acara_resmi' => 'Guru sedang Acara Resmi',
                        'lainnya' => 'Guru sedang ' . ($teacherLeave->custom_reason ?: 'Izin'),
                        default => 'Guru sedang Izin'
                    };
                    $substituteTeacher = $teacherLeave->substitute_name ?: null;
                }

                $result[] = [
                    'schedule_id' => $schedule->id,
                    'period' => $periodNumber++,
                    'time' => ($schedule->jam_mulai ? date('H:i', strtotime($schedule->jam_mulai)) : '00:00') . ' - ' .
                        ($schedule->jam_selesai ? date('H:i', strtotime($schedule->jam_selesai)) : '00:00'),
                    'subject' => $schedule->mata_pelajaran ?: 'Mata Pelajaran',
                    'teacher' => $schedule->teacher_name ?: 'Guru',
                    'teacherId' => (int) $schedule->guru_id,
                    'submitted' => $isOnLeave ? true : ($attendance !== null),
                    'status' => $isOnLeave ? 'izin' : ($attendance ? $attendance->status : null),
                    'catatan' => $isOnLeave ? $leaveReason : ($attendance ? $attendance->keterangan : null),
                    'submitted_at' => $attendance ? $attendance->submitted_at : null,
                    // New fields for leave info
                    'teacher_on_leave' => $isOnLeave,
                    'leave_reason' => $leaveReason,
                    'substitute_teacher' => $substituteTeacher,
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
     * Status langsung tercatat (hadir/telat/tidak_hadir) tanpa menunggu konfirmasi
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
            $statusReport = $request->input('status'); // Status yang dilaporkan siswa
            $catatan = $request->input('catatan', '');

            if (!$scheduleId || !in_array($statusReport, ['hadir', 'telat', 'tidak_hadir'])) {
                return response()->json(['success' => false, 'message' => 'Invalid input'], 400);
            }

            $classId = $user->class_id;
            if (!$classId) {
                return response()->json(['success' => false, 'message' => 'No class assigned'], 400);
            }

            // Get the class name from classes table
            $userClass = \DB::selectOne("SELECT nama_kelas FROM classes WHERE id = ? LIMIT 1", [$classId]);
            if (!$userClass) {
                return response()->json(['success' => false, 'message' => 'Class not found'], 400);
            }
            $className = $userClass->nama_kelas;

            $tanggal = date('Y-m-d');
            $jamMasuk = in_array($statusReport, ['hadir', 'telat']) ? date('H:i:s') : null;

            // FIXED: Check if schedule belongs to student's class using 'kelas' column with class NAME
            $scheduleData = \DB::selectOne("
                SELECT id, guru_id FROM schedules
                WHERE id = ? AND kelas = ?
                LIMIT 1
            ", [$scheduleId, $className]);

            if (!$scheduleData) {
                return response()->json(['success' => false, 'message' => 'Invalid schedule for your class'], 403);
            }

            // Langsung gunakan status yang dilaporkan siswa tanpa pending
            // - hadir → langsung hadir
            // - telat → langsung telat  
            // - tidak_hadir → langsung tidak_hadir
            $initialStatus = $statusReport;
            $keteranganFinal = $catatan ?: null;

            // Check if attendance already exists
            $existing = \DB::selectOne("
                SELECT id, status FROM teacher_attendances
                WHERE schedule_id = ? AND tanggal = ?
                LIMIT 1
            ", [$scheduleId, $tanggal]);

            if ($existing) {
                // Jangan update jika sudah dikonfirmasi (hadir/telat/diganti)
                if (in_array($existing->status, ['hadir', 'telat', 'diganti'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kehadiran sudah dikonfirmasi oleh Kurikulum'
                    ], 400);
                }

                // Update existing
                \DB::update("
                    UPDATE teacher_attendances
                    SET status = ?, keterangan = ?, jam_masuk = ?, updated_at = NOW()
                    WHERE id = ?
                ", [$initialStatus, $keteranganFinal, $jamMasuk, $existing->id]);

                $statusLabel = match ($initialStatus) {
                    'hadir' => 'Hadir',
                    'telat' => 'Telat',
                    'tidak_hadir' => 'Tidak Hadir',
                    default => $initialStatus
                };

                return response()->json([
                    'success' => true,
                    'message' => "Kehadiran guru berhasil dicatat: $statusLabel",
                    'data' => ['id' => $existing->id, 'schedule_id' => $scheduleId, 'status' => $initialStatus]
                ]);
            } else {
                // Insert new - use guru_id from scheduleData that we already fetched
                $teacherId = $scheduleData->guru_id;

                \DB::insert("
                    INSERT INTO teacher_attendances
                    (schedule_id, guru_id, tanggal, jam_masuk, status, keterangan, created_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [$scheduleId, $teacherId, $tanggal, $jamMasuk, $initialStatus, $keteranganFinal, $user->id]);

                $statusLabel = match ($initialStatus) {
                    'hadir' => 'Hadir',
                    'telat' => 'Telat',
                    'tidak_hadir' => 'Tidak Hadir',
                    default => $initialStatus
                };

                return response()->json([
                    'success' => true,
                    'message' => "Kehadiran guru berhasil dicatat: $statusLabel",
                    'data' => ['id' => \DB::getPdo()->lastInsertId(), 'schedule_id' => $scheduleId, 'status' => $initialStatus]
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

            // Get the class name from classes table
            $userClass = \DB::selectOne("SELECT nama_kelas FROM classes WHERE id = ? LIMIT 1", [$classId]);
            if (!$userClass) {
                return response()->json(['success' => false, 'message' => 'Class not found'], 400);
            }
            $className = $userClass->nama_kelas;

            // Ultra simple pagination
            $page = max(1, (int)$request->get('page', 1));
            $limit = min(max(1, (int)$request->get('limit', 20)), 50);
            $offset = ($page - 1) * $limit;

            // FIXED: Use 'kelas' column with class NAME, join with teachers table
            $riwayat = \DB::select("
                SELECT ta.id, ta.tanggal, ta.status, ta.keterangan,
                       DATE_FORMAT(ta.created_at, '%H:%i') as submitted_at,
                       s.mata_pelajaran, s.jam_mulai, s.jam_selesai, s.hari,
                       COALESCE(t.nama, 'Guru') as teacher_name
                FROM teacher_attendances ta
                INNER JOIN schedules s ON ta.schedule_id = s.id
                LEFT JOIN teachers t ON ta.guru_id = t.id
                WHERE s.kelas = ?
                ORDER BY ta.tanggal DESC, s.jam_mulai ASC
                LIMIT ? OFFSET ?
            ", [$className, $limit, $offset]);

            // Get total count
            $total = \DB::selectOne("
                SELECT COUNT(*) as total
                FROM teacher_attendances ta
                INNER JOIN schedules s ON ta.schedule_id = s.id
                WHERE s.kelas = ?
            ", [$className])->total;

            // Format results - calculate period based on order within same day
            $result = [];
            $dayMap = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];

            // Group by tanggal to calculate period within same day
            $groupedByDate = [];
            foreach ($riwayat as $item) {
                $groupedByDate[$item->tanggal][] = $item;
            }

            foreach ($groupedByDate as $tanggal => $items) {
                // Sort by jam_mulai to get correct period order
                usort($items, function ($a, $b) {
                    return strcmp($a->jam_mulai ?? '', $b->jam_mulai ?? '');
                });

                $periodCounter = 1;
                foreach ($items as $item) {
                    $englishDay = date('l', strtotime($item->tanggal));
                    $hari = $item->hari ?? ($dayMap[$englishDay] ?? $englishDay);

                    $result[] = [
                        'id' => $item->id,
                        'tanggal' => $item->tanggal,
                        'day' => $hari,
                        'hari' => $hari,
                        'period' => $periodCounter++,
                        'time' => ($item->jam_mulai ? date('H:i', strtotime($item->jam_mulai)) : '00:00') . ' - ' .
                            ($item->jam_selesai ? date('H:i', strtotime($item->jam_selesai)) : '00:00'),
                        'jam' => ($item->jam_mulai ? date('H:i', strtotime($item->jam_mulai)) : '00:00') . ' - ' .
                            ($item->jam_selesai ? date('H:i', strtotime($item->jam_selesai)) : '00:00'),
                        'subject' => $item->mata_pelajaran ?: 'Mata Pelajaran',
                        'mapel' => $item->mata_pelajaran ?: 'Mata Pelajaran',
                        'teacher' => $item->teacher_name ?: 'Guru',
                        'guru' => $item->teacher_name ?: 'Guru',
                        'status' => $item->status,
                        'catatan' => $item->keterangan,
                        'keterangan' => $item->keterangan,
                        'submitted_at' => $item->submitted_at,
                    ];
                }
            }

            // Sort final result by tanggal DESC, then by jam
            usort($result, function ($a, $b) {
                $dateCompare = strcmp($b['tanggal'], $a['tanggal']); // DESC
                if ($dateCompare !== 0) return $dateCompare;
                return strcmp($a['time'], $b['time']); // ASC for time
            });

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
