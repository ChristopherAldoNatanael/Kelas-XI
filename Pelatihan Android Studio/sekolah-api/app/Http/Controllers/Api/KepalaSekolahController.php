<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherAttendance;
use App\Models\Teacher;
use App\Models\Schedule;
use App\Models\ClassModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KepalaSekolahController extends Controller
{
    /**
     * Get dashboard overview with weekly comparison
     * Shows: Hadir, Telat, Tidak Hadir, Izin statistics
     * Compares this week vs last week
     */    public function dashboardOverview(Request $request): JsonResponse
    {
        try {
            $weekOffset = (int) $request->get('week_offset', 0); // 0 = this week, -1 = last week

            // Calculate week boundaries
            $today = Carbon::now();
            $startOfWeek = $today->copy()->startOfWeek()->addWeeks($weekOffset);
            $endOfWeek = $startOfWeek->copy()->endOfWeek();

            // Get last week for comparison
            $lastWeekStart = $startOfWeek->copy()->subWeek();
            $lastWeekEnd = $lastWeekStart->copy()->endOfWeek();

            // This week statistics
            $thisWeekStats = $this->getWeekStatistics($startOfWeek, $endOfWeek);

            // Last week statistics (for comparison)
            $lastWeekStats = $this->getWeekStatistics($lastWeekStart, $lastWeekEnd);

            // Calculate trends (percentage change)
            $trends = $this->calculateTrends($thisWeekStats, $lastWeekStats);

            // Get daily breakdown for chart
            $dailyBreakdown = $this->getDailyBreakdown($startOfWeek, $endOfWeek);

            // Get teachers on leave with substitutes
            $teachersOnLeave = $this->getTeachersOnLeave($startOfWeek, $endOfWeek);

            // Get top late teachers
            $topLateTeachers = $this->getTopLateTeachers($startOfWeek, $endOfWeek);

            // Get attendance rate per class
            $classAttendanceRates = $this->getClassAttendanceRates($startOfWeek, $endOfWeek);

            // NEW: Get teachers attendance summary for today
            $todayDate = Carbon::today();
            $teachersAttendanceSummary = $this->getTeachersAttendanceSummary($todayDate);

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data berhasil diambil',
                'data' => [
                    'week_info' => [
                        'week_offset' => $weekOffset,
                        'week_start' => $startOfWeek->format('Y-m-d'),
                        'week_end' => $endOfWeek->format('Y-m-d'),
                        'week_label' => $weekOffset == 0 ? 'Minggu Ini' : ($weekOffset == -1 ? 'Minggu Lalu' : $startOfWeek->format('d M') . ' - ' . $endOfWeek->format('d M Y')),
                        'is_current_week' => $weekOffset == 0
                    ],
                    'this_week' => $thisWeekStats,
                    'last_week' => $lastWeekStats,
                    'trends' => $trends,
                    'daily_breakdown' => $dailyBreakdown,
                    'teachers_on_leave' => $teachersOnLeave,
                    'top_late_teachers' => $topLateTeachers,
                    'class_attendance_rates' => $classAttendanceRates,
                    'teachers_attendance_today' => $teachersAttendanceSummary
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get detailed attendance list with filters
     */
    public function attendanceList(Request $request): JsonResponse
    {
        try {
            $status = $request->get('status'); // hadir, telat, tidak_hadir, izin, diganti
            $weekOffset = $request->get('week_offset', 0);
            $className = $request->get('class_name');
            $teacherId = $request->get('teacher_id');
            $date = $request->get('date');

            // Calculate date range
            if ($date) {
                $startDate = Carbon::parse($date);
                $endDate = $startDate->copy();
            } else {
                $today = Carbon::now();
                $startDate = $today->copy()->startOfWeek()->addWeeks($weekOffset);
                $endDate = $startDate->copy()->endOfWeek();
            }

            $query = TeacherAttendance::with(['guru:id,nama,nip', 'guruAsli:id,nama,nip', 'schedule'])
                ->whereBetween('tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($teacherId) {
                $query->where(function ($q) use ($teacherId) {
                    $q->where('guru_id', $teacherId)
                        ->orWhere('guru_asli_id', $teacherId);
                });
            }

            if ($className) {
                $query->whereHas('schedule', function ($q) use ($className) {
                    $q->where('kelas', $className);
                });
            }

            $attendances = $query->orderBy('tanggal', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($att) {
                    $schedule = $att->schedule;
                    return [
                        'id' => $att->id,
                        'date' => $att->tanggal->format('Y-m-d'),
                        'day' => $att->tanggal->isoFormat('dddd'),
                        'time' => $att->jam_masuk ? Carbon::parse($att->jam_masuk)->format('H:i') : null,
                        'status' => $att->status,
                        'teacher_id' => $att->guru_id,
                        'teacher_name' => $att->guru->nama ?? 'Unknown',
                        'teacher_nip' => $att->guru->nip ?? null,
                        'original_teacher_id' => $att->guru_asli_id,
                        'original_teacher_name' => $att->guruAsli->nama ?? null,
                        'substitute_teacher_name' => $att->status === 'diganti' ? ($att->guru->nama ?? null) : null,
                        'class_name' => $schedule ? $schedule->kelas : 'Unknown',
                        'subject_name' => $schedule ? ($schedule->mata_pelajaran ?? 'Unknown') : 'Unknown',
                        'keterangan' => $att->keterangan
                    ];
                });

            // Group by status for summary
            $statusSummary = [
                'hadir' => $attendances->where('status', 'hadir')->count(),
                'telat' => $attendances->where('status', 'telat')->count(),
                'tidak_hadir' => $attendances->where('status', 'tidak_hadir')->count(),
                'izin' => $attendances->where('status', 'izin')->count(),
                'diganti' => $attendances->where('status', 'diganti')->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Data kehadiran berhasil diambil',
                'data' => [
                    'date_range' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d')
                    ],
                    'summary' => $statusSummary,
                    'total' => $attendances->count(),
                    'attendances' => $attendances->values()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kehadiran',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teachers on leave with their substitutes
     */
    public function teachersOnLeaveList(Request $request): JsonResponse
    {
        try {
            $weekOffset = $request->get('week_offset', 0);

            $today = Carbon::now();
            $startOfWeek = $today->copy()->startOfWeek()->addWeeks($weekOffset);
            $endOfWeek = $startOfWeek->copy()->endOfWeek();

            $onLeave = $this->getTeachersOnLeave($startOfWeek, $endOfWeek);

            return response()->json([
                'success' => true,
                'message' => 'Data guru izin berhasil diambil',
                'data' => [
                    'week_info' => [
                        'week_start' => $startOfWeek->format('Y-m-d'),
                        'week_end' => $endOfWeek->format('Y-m-d'),
                        'week_label' => $weekOffset == 0 ? 'Minggu Ini' : 'Minggu Lalu'
                    ],
                    'total' => count($onLeave),
                    'teachers_on_leave' => $onLeave
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data guru izin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher performance ranking
     */
    public function teacherPerformance(Request $request): JsonResponse
    {
        try {
            $weekOffset = $request->get('week_offset', 0);
            $sortBy = $request->get('sort_by', 'attendance_rate'); // attendance_rate, late_count, absent_count

            $today = Carbon::now();
            $startOfWeek = $today->copy()->startOfWeek()->addWeeks($weekOffset);
            $endOfWeek = $startOfWeek->copy()->endOfWeek();

            // Get all teachers with their attendance stats
            $teachers = Teacher::select('id', 'nama', 'nip')->get();

            $performance = $teachers->map(function ($teacher) use ($startOfWeek, $endOfWeek) {
                $attendances = TeacherAttendance::where('guru_id', $teacher->id)
                    ->whereBetween('tanggal', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
                    ->get();

                $total = $attendances->count();
                $hadir = $attendances->where('status', 'hadir')->count();
                $telat = $attendances->where('status', 'telat')->count();
                $tidakHadir = $attendances->where('status', 'tidak_hadir')->count();
                $izin = $attendances->where('status', 'izin')->count();

                $attendanceRate = $total > 0 ? round((($hadir + $telat) / $total) * 100, 1) : 0;

                return [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->nama,
                    'teacher_nip' => $teacher->nip,
                    'total_schedules' => $total,
                    'hadir' => $hadir,
                    'telat' => $telat,
                    'tidak_hadir' => $tidakHadir,
                    'izin' => $izin,
                    'attendance_rate' => $attendanceRate,
                    'on_time_rate' => $total > 0 ? round(($hadir / $total) * 100, 1) : 0
                ];
            })->filter(function ($item) {
                return $item['total_schedules'] > 0;
            });

            // Sort based on criteria
            if ($sortBy === 'late_count') {
                $performance = $performance->sortByDesc('telat');
            } elseif ($sortBy === 'absent_count') {
                $performance = $performance->sortByDesc('tidak_hadir');
            } else {
                $performance = $performance->sortByDesc('attendance_rate');
            }

            return response()->json([
                'success' => true,
                'message' => 'Data performa guru berhasil diambil',
                'data' => [
                    'week_info' => [
                        'week_start' => $startOfWeek->format('Y-m-d'),
                        'week_end' => $endOfWeek->format('Y-m-d')
                    ],
                    'sort_by' => $sortBy,
                    'teachers' => $performance->values()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data performa guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== HELPER METHODS =====

    private function getWeekStatistics($startDate, $endDate): array
    {
        $attendances = TeacherAttendance::whereBetween('tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])->get();

        $hadir = $attendances->where('status', 'hadir')->count();
        $telat = $attendances->where('status', 'telat')->count();
        $tidakHadir = $attendances->where('status', 'tidak_hadir')->count();
        $izinFromAttendance = $attendances->where('status', 'izin')->count();
        $diganti = $attendances->where('status', 'diganti')->count();
        $pending = $attendances->where('status', 'pending')->count();

        // ALSO count izin from leaves table (approved leaves that overlap with this week)
        $izinFromLeaves = $this->countIzinFromLeavesTable($startDate, $endDate);
        $izin = $izinFromAttendance + $izinFromLeaves;

        $total = $attendances->count() + $izinFromLeaves;

        return [
            'total' => $total,
            'hadir' => $hadir,
            'telat' => $telat,
            'tidak_hadir' => $tidakHadir,
            'izin' => $izin,
            'diganti' => $diganti,
            'pending' => $pending,
            'attendance_rate' => $total > 0 ? round((($hadir + $telat) / $total) * 100, 1) : 0,
            'on_time_rate' => $total > 0 ? round(($hadir / $total) * 100, 1) : 0
        ];
    }

    /**
     * Count izin schedules from leaves table
     * Counts how many schedule slots are affected by approved leaves
     */
    private function countIzinFromLeavesTable($startDate, $endDate): int
    {
        $count = 0;

        $leaves = \App\Models\Leave::where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate->format('Y-m-d'))
                            ->where('end_date', '>=', $endDate->format('Y-m-d'));
                    });
            })
            ->get();

        foreach ($leaves as $leave) {
            // Get schedules for this teacher
            $schedules = Schedule::where('guru_id', $leave->teacher_id)->get();

            foreach ($schedules as $schedule) {
                $currentDate = $startDate->copy();
                while ($currentDate->lte($endDate)) {
                    $dayName = $this->getDayName($currentDate->dayOfWeekIso);

                    // Check if schedule day matches current date AND date is within leave period
                    if (
                        $schedule->hari === $dayName &&
                        $currentDate->gte(Carbon::parse($leave->start_date)) &&
                        $currentDate->lte(Carbon::parse($leave->end_date))
                    ) {
                        $count++;
                    }
                    $currentDate->addDay();
                }
            }
        }

        return $count;
    }

    private function calculateTrends($thisWeek, $lastWeek): array
    {
        $calcTrend = function ($current, $previous) {
            if ($previous == 0) return $current > 0 ? 100 : 0;
            return round((($current - $previous) / $previous) * 100, 1);
        };

        return [
            'hadir' => [
                'value' => $thisWeek['hadir'] - $lastWeek['hadir'],
                'percentage' => $calcTrend($thisWeek['hadir'], $lastWeek['hadir']),
                'is_positive' => $thisWeek['hadir'] >= $lastWeek['hadir']
            ],
            'telat' => [
                'value' => $thisWeek['telat'] - $lastWeek['telat'],
                'percentage' => $calcTrend($thisWeek['telat'], $lastWeek['telat']),
                'is_positive' => $thisWeek['telat'] <= $lastWeek['telat'] // Less late is better
            ],
            'tidak_hadir' => [
                'value' => $thisWeek['tidak_hadir'] - $lastWeek['tidak_hadir'],
                'percentage' => $calcTrend($thisWeek['tidak_hadir'], $lastWeek['tidak_hadir']),
                'is_positive' => $thisWeek['tidak_hadir'] <= $lastWeek['tidak_hadir']
            ],
            'attendance_rate' => [
                'value' => $thisWeek['attendance_rate'] - $lastWeek['attendance_rate'],
                'percentage' => $thisWeek['attendance_rate'] - $lastWeek['attendance_rate'],
                'is_positive' => $thisWeek['attendance_rate'] >= $lastWeek['attendance_rate']
            ]
        ];
    }
    private function getDailyBreakdown($startDate, $endDate): array
    {
        $days = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dayAttendances = TeacherAttendance::where('tanggal', $current->format('Y-m-d'))->get();

            // Count izin from leaves table for this specific date
            $izinFromLeaves = $this->countIzinFromLeavesForDate($current);
            $izinFromAttendance = $dayAttendances->where('status', 'izin')->count();

            $days[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->isoFormat('ddd'),
                'day_full' => $current->isoFormat('dddd'),
                'hadir' => $dayAttendances->where('status', 'hadir')->count(),
                'telat' => $dayAttendances->where('status', 'telat')->count(),
                'tidak_hadir' => $dayAttendances->where('status', 'tidak_hadir')->count(),
                'izin' => $izinFromAttendance + $izinFromLeaves,
                'total' => $dayAttendances->count() + $izinFromLeaves
            ];

            $current->addDay();
        }

        return $days;
    }

    /**
     * Count izin schedules from leaves table for a specific date
     */
    private function countIzinFromLeavesForDate($date): int
    {
        $count = 0;
        $dayName = $this->getDayName($date->dayOfWeekIso);

        $leaves = \App\Models\Leave::where('status', 'approved')
            ->where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->get();

        foreach ($leaves as $leave) {
            // Count schedules for this teacher on this day of week
            $scheduleCount = Schedule::where('guru_id', $leave->teacher_id)
                ->where('hari', $dayName)
                ->count();
            $count += $scheduleCount;
        }

        return $count;
    }

    private function getTeachersOnLeave($startDate, $endDate): array
    {
        $result = collect();

        // 1. Get from leaves table (approved leaves)
        $leaves = \App\Models\Leave::with(['teacher:id,nama,nip', 'substituteTeacher:id,nama,nip'])
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate->format('Y-m-d'))
                            ->where('end_date', '>=', $endDate->format('Y-m-d'));
                    });
            })
            ->orderBy('start_date', 'desc')
            ->get();

        foreach ($leaves as $leave) {
            // Get schedules for this teacher within date range
            $schedules = Schedule::where('guru_id', $leave->teacher_id)->get();

            foreach ($schedules as $schedule) {
                // Check if this schedule's day falls within leave period
                $currentDate = $startDate->copy();
                while ($currentDate->lte($endDate)) {
                    $dayName = $this->getDayName($currentDate->dayOfWeekIso);
                    if (
                        $schedule->hari === $dayName &&
                        $currentDate->gte(Carbon::parse($leave->start_date)) &&
                        $currentDate->lte(Carbon::parse($leave->end_date))
                    ) {

                        $reasonText = $this->getLeaveReasonText($leave->reason, $leave->custom_reason);

                        $result->push([
                            'id' => $leave->id,
                            'date' => $currentDate->format('Y-m-d'),
                            'day' => $dayName,
                            'status' => 'izin',
                            'leave_type' => $leave->reason,
                            'original_teacher_id' => $leave->teacher_id,
                            'original_teacher_name' => $leave->teacher->nama ?? 'Unknown',
                            'substitute_teacher_id' => $leave->substitute_teacher_id,
                            'substitute_teacher_name' => $leave->substituteTeacher->nama ?? null,
                            'class_name' => $schedule->kelas ?? 'Unknown',
                            'subject_name' => $schedule->mata_pelajaran ?? 'Unknown',
                            'time' => $this->extractTimeOnly($schedule->jam_mulai) . ' - ' . $this->extractTimeOnly($schedule->jam_selesai),
                            'keterangan' => $reasonText . ($leave->notes ? ' - ' . $leave->notes : ''),
                            'source' => 'leaves_table'
                        ]);
                    }
                    $currentDate->addDay();
                }
            }
        }

        // 2. Also get from teacher_attendances (for real-time attendance status)
        $onLeave = TeacherAttendance::with(['guru:id,nama,nip', 'guruAsli:id,nama,nip', 'schedule'])
            ->whereIn('status', ['izin', 'diganti'])
            ->whereBetween('tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('tanggal', 'desc')
            ->get();

        foreach ($onLeave as $att) {
            // Check if this is not already in leaves result
            $exists = $result->contains(function ($item) use ($att) {
                return $item['date'] === $att->tanggal->format('Y-m-d') &&
                    $item['original_teacher_id'] === ($att->guru_asli_id ?? $att->guru_id) &&
                    ($item['class_name'] === ($att->schedule->kelas ?? 'Unknown'));
            });

            if (!$exists) {
                $result->push([
                    'id' => $att->id,
                    'date' => $att->tanggal->format('Y-m-d'),
                    'day' => $att->tanggal->isoFormat('dddd'),
                    'status' => $att->status,
                    'leave_type' => null,
                    'original_teacher_id' => $att->guru_asli_id ?? $att->guru_id,
                    'original_teacher_name' => $att->guruAsli->nama ?? $att->guru->nama ?? 'Unknown',
                    'substitute_teacher_id' => $att->status === 'diganti' ? $att->guru_id : null,
                    'substitute_teacher_name' => $att->status === 'diganti' ? ($att->guru->nama ?? null) : null,
                    'class_name' => $att->schedule ? $att->schedule->kelas : 'Unknown',
                    'subject_name' => $att->schedule->mata_pelajaran ?? 'Unknown',
                    'time' => $att->schedule ? $this->extractTimeOnly($att->schedule->jam_mulai) . ' - ' . $this->extractTimeOnly($att->schedule->jam_selesai) : null,
                    'keterangan' => $att->keterangan,
                    'source' => 'attendance_table'
                ]);
            }
        }

        // Sort by date descending
        return $result->sortByDesc('date')->values()->toArray();
    }

    private function getDayName($dayOfWeek): string
    {
        $days = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu'
        ];
        return $days[$dayOfWeek] ?? 'Unknown';
    }

    private function getLeaveReasonText($reason, $customReason = null): string
    {
        $reasons = [
            'sakit' => 'Sakit',
            'cuti_tahunan' => 'Cuti Tahunan',
            'urusan_keluarga' => 'Urusan Keluarga',
            'acara_resmi' => 'Acara Resmi',
            'lainnya' => $customReason ?: 'Lainnya'
        ];
        return $reasons[$reason] ?? $reason;
    }

    private function getTopLateTeachers($startDate, $endDate, $limit = 5): array
    {
        $lateTeachers = TeacherAttendance::select('guru_id', DB::raw('COUNT(*) as late_count'))
            ->where('status', 'telat')
            ->whereBetween('tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->groupBy('guru_id')
            ->orderByDesc('late_count')
            ->limit($limit)
            ->get();

        return $lateTeachers->map(function ($item) {
            $teacher = Teacher::find($item->guru_id);
            return [
                'teacher_id' => $item->guru_id,
                'teacher_name' => $teacher->nama ?? 'Unknown',
                'teacher_nip' => $teacher->nip ?? null,
                'late_count' => $item->late_count
            ];
        })->toArray();
    }
    private function getClassAttendanceRates($startDate, $endDate): array
    {
        // Get unique class names from schedules
        $classNames = Schedule::select('kelas')->distinct()->pluck('kelas');

        return $classNames->map(function ($className) use ($startDate, $endDate) {
            $scheduleIds = Schedule::where('kelas', $className)->pluck('id');

            $attendances = TeacherAttendance::whereIn('schedule_id', $scheduleIds)
                ->whereBetween('tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();

            $total = $attendances->count();
            $hadir = $attendances->where('status', 'hadir')->count();
            $telat = $attendances->where('status', 'telat')->count();

            return [
                'class_name' => $className,
                'total' => $total,
                'hadir' => $hadir,
                'telat' => $telat,
                'tidak_hadir' => $attendances->where('status', 'tidak_hadir')->count(),
                'attendance_rate' => $total > 0 ? round((($hadir + $telat) / $total) * 100, 1) : 0
            ];
        })->filter(function ($item) {
            return $item['total'] > 0;
        })->sortByDesc('attendance_rate')->values()->toArray();
    }

    /**
     * Get comprehensive teacher attendance summary for today
     * Returns lists of present, late, absent, and on-leave teachers
     * OPTIMIZED: Reduced payload size for mobile performance
     */
    private function getTeachersAttendanceSummary($date): array
    {
        $dateStr = $date->format('Y-m-d');
        $dayName = $this->getDayName($date->dayOfWeekIso);

        // Get all teachers who have schedules today
        $teachersWithSchedulesToday = Schedule::where('hari', $dayName)
            ->distinct()
            ->pluck('guru_id')
            ->filter()
            ->toArray();

        if (empty($teachersWithSchedulesToday)) {
            return [
                'date' => $dateStr,
                'day' => $dayName,
                'summary' => [
                    'total_scheduled' => 0,
                    'present' => 0,
                    'late' => 0,
                    'absent' => 0,
                    'on_leave' => 0,
                    'pending' => 0
                ],
                'teachers_present' => [],
                'teachers_late' => [],
                'teachers_absent' => [],
                'teachers_on_leave' => [],
                'teachers_pending' => []
            ];
        }

        $result = [
            'present' => [],
            'late' => [],
            'absent' => [],
            'on_leave' => [],
            'pending' => []
        ];

        $processedTeachers = [];

        foreach ($teachersWithSchedulesToday as $teacherId) {
            if (in_array($teacherId, $processedTeachers)) continue;
            $processedTeachers[] = $teacherId;

            $teacher = Teacher::find($teacherId);
            if (!$teacher) continue;

            // Get schedule count for this teacher today (OPTIMIZED: only count, not full data)
            $scheduleCount = Schedule::where('guru_id', $teacherId)
                ->where('hari', $dayName)
                ->count();

            // Get first schedule info only (for display)
            $firstSchedule = Schedule::where('guru_id', $teacherId)
                ->where('hari', $dayName)
                ->first();

            $firstScheduleInfo = $firstSchedule ? $firstSchedule->kelas . ' - ' . $firstSchedule->mata_pelajaran : '';

            // Check for approved leave
            $hasLeave = \App\Models\Leave::where('teacher_id', $teacherId)
                ->where('status', 'approved')
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->exists();

            if ($hasLeave) {
                $leave = \App\Models\Leave::where('teacher_id', $teacherId)
                    ->where('status', 'approved')
                    ->where('start_date', '<=', $dateStr)
                    ->where('end_date', '>=', $dateStr)
                    ->first();

                $result['on_leave'][] = [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->nama,
                    'teacher_nip' => $teacher->nip,
                    'reason' => $this->getLeaveReasonText($leave->reason ?? '', $leave->custom_reason ?? ''),
                    'first_schedule' => $firstScheduleInfo,
                    'schedule_count' => $scheduleCount
                ];
                continue;
            }

            // Check attendance records for today
            $attendances = TeacherAttendance::where('guru_id', $teacherId)
                ->where('tanggal', $dateStr)
                ->get();

            if ($attendances->isEmpty()) {
                // No attendance record yet - pending
                $result['pending'][] = [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->nama,
                    'teacher_nip' => $teacher->nip,
                    'first_schedule' => $firstScheduleInfo,
                    'schedule_count' => $scheduleCount
                ];
                continue;
            }

            // Determine teacher status based on attendance records
            $statuses = $attendances->pluck('status')->unique()->toArray();
            $latestAttendance = $attendances->sortByDesc('created_at')->first();
            $attendanceTime = $latestAttendance ? $this->extractTimeOnly($latestAttendance->waktu_hadir) : null;

            $teacherData = [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->nama,
                'teacher_nip' => $teacher->nip,
                'attendance_time' => $attendanceTime,
                'first_schedule' => $firstScheduleInfo,
                'schedule_count' => $scheduleCount
            ];

            // Prioritize status: hadir > telat > tidak_hadir > izin
            if (in_array('hadir', $statuses)) {
                $result['present'][] = $teacherData;
            } elseif (in_array('telat', $statuses)) {
                $result['late'][] = $teacherData;
            } elseif (in_array('tidak_hadir', $statuses)) {
                $result['absent'][] = $teacherData;
            } elseif (in_array('izin', $statuses) || in_array('diganti', $statuses)) {
                $result['on_leave'][] = array_merge($teacherData, ['reason' => 'Izin/Diganti']);
            } else {
                $result['pending'][] = $teacherData;
            }
        }

        // Sort by name
        foreach ($result as $key => $teachers) {
            usort($result[$key], fn($a, $b) => strcmp($a['teacher_name'], $b['teacher_name']));
        }

        return [
            'date' => $dateStr,
            'day' => $dayName,
            'summary' => [
                'total_scheduled' => count($processedTeachers),
                'present' => count($result['present']),
                'late' => count($result['late']),
                'absent' => count($result['absent']),
                'on_leave' => count($result['on_leave']),
                'pending' => count($result['pending'])
            ],
            'teachers_present' => $result['present'],
            'teachers_late' => $result['late'],
            'teachers_absent' => $result['absent'],
            'teachers_on_leave' => $result['on_leave'],
            'teachers_pending' => $result['pending']
        ];
    }

    private function extractTimeOnly($time): ?string
    {
        if (!$time) return null;

        if ($time instanceof \DateTime || $time instanceof Carbon) {
            return $time->format('H:i');
        }

        if (is_string($time)) {
            if (preg_match('/^\d{2}:\d{2}/', $time, $matches)) {
                return $matches[0];
            }
            if (preg_match('/\d{2}:\d{2}:\d{2}/', $time, $matches)) {
                return substr($matches[0], 0, 5);
            }
        }

        return null;
    }

    /**
     * Get all schedules with attendance status for a specific day
     * Used for Kepala Sekolah to monitor all classes
     */
    public function schedulesWithAttendance(Request $request): JsonResponse
    {
        try {
            $weekOffset = (int) $request->get('week_offset', 0);
            $dayFilter = $request->get('day'); // Senin, Selasa, etc.

            // Calculate the target date based on week offset and day
            $today = Carbon::now();
            $startOfWeek = $today->copy()->startOfWeek()->addWeeks($weekOffset);
            $endOfWeek = $startOfWeek->copy()->endOfWeek();

            // Get target date for the specific day
            $dayMap = [
                'Senin' => 0,
                'Selasa' => 1,
                'Rabu' => 2,
                'Kamis' => 3,
                'Jumat' => 4,
                'Sabtu' => 5,
            ];

            $targetDate = null;
            if ($dayFilter && isset($dayMap[$dayFilter])) {
                $targetDate = $startOfWeek->copy()->addDays($dayMap[$dayFilter]);
            }

            // Build schedules query
            $query = Schedule::query();

            if ($dayFilter) {
                $query->where('hari', $dayFilter);
            }

            $schedules = $query->orderBy('kelas')
                ->orderBy('jam_mulai')
                ->get();

            // Calculate period number based on time
            $periodCounter = [];

            $result = $schedules->map(function ($schedule) use ($targetDate, $startOfWeek, $endOfWeek, &$periodCounter) {
                // Calculate period number for this class
                $className = $schedule->kelas ?? 'Unknown';
                if (!isset($periodCounter[$className])) {
                    $periodCounter[$className] = 0;
                }
                $periodCounter[$className]++;
                $periodNum = $periodCounter[$className];

                // Get teacher info
                $teacher = Teacher::find($schedule->guru_id);

                // Get attendance for this schedule
                $attendance = null;
                $dateToCheck = $targetDate ? $targetDate->format('Y-m-d') : null;

                if ($targetDate) {
                    $attendance = TeacherAttendance::where('schedule_id', $schedule->id)
                        ->where('tanggal', $targetDate->format('Y-m-d'))
                        ->first();
                } else {
                    // If no specific date, get the latest attendance within the week
                    $attendance = TeacherAttendance::where('schedule_id', $schedule->id)
                        ->whereBetween('tanggal', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
                        ->orderBy('tanggal', 'desc')
                        ->first();
                    if ($attendance) {
                        $dateToCheck = $attendance->tanggal->format('Y-m-d');
                    }
                }

                // Check if teacher has approved leave for this date (even if no attendance record)
                $leaveStatus = null;
                $leaveInfo = null;
                if ($dateToCheck && $schedule->guru_id) {
                    $leave = \App\Models\Leave::where('teacher_id', $schedule->guru_id)
                        ->where('status', 'approved')
                        ->where('start_date', '<=', $dateToCheck)
                        ->where('end_date', '>=', $dateToCheck)
                        ->first();

                    if ($leave) {
                        $leaveStatus = 'izin';
                        $leaveInfo = $leave;
                    }
                }

                // Determine final attendance status
                $finalStatus = null;
                $substituteTeacher = null;

                if ($attendance) {
                    // Use attendance record if exists
                    $finalStatus = $attendance->status;
                    if (in_array($attendance->status, ['izin', 'diganti']) && $attendance->guru_pengganti_id) {
                        $substitute = Teacher::find($attendance->guru_pengganti_id);
                        $substituteTeacher = $substitute ? $substitute->nama : null;
                    }
                } elseif ($leaveStatus) {
                    // Use leave status if no attendance record but has approved leave
                    $finalStatus = 'izin';
                    if ($leaveInfo && $leaveInfo->substitute_teacher_id) {
                        $substitute = Teacher::find($leaveInfo->substitute_teacher_id);
                        $substituteTeacher = $substitute ? $substitute->nama : null;
                    }
                }

                return [
                    'schedule_id' => $schedule->id,
                    'class_id' => $schedule->class_id ?? 0,
                    'class_name' => $className,
                    'subject_name' => $schedule->mata_pelajaran ?? 'Unknown',
                    'teacher_name' => $teacher ? $teacher->nama : 'Unknown',
                    'period' => $periodNum,
                    'time_start' => $this->extractTimeOnly($schedule->jam_mulai) ?? '00:00',
                    'time_end' => $this->extractTimeOnly($schedule->jam_selesai) ?? '00:00',
                    'day_of_week' => $schedule->hari ?? '',
                    'attendance_status' => $finalStatus,
                    'attendance_time' => $attendance ? $this->extractTimeOnly($attendance->waktu_hadir) : null,
                    'substitute_teacher' => $substituteTeacher
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data jadwal dengan kehadiran berhasil diambil',
                'data' => $result->values()->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jadwal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
