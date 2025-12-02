<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherAttendance;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Teacher;
use App\Models\ClassModel;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * KurikulumController - API endpoints for Kurikulum role
 * Handles schedule monitoring, teacher substitution, and attendance history
 */
class KurikulumController extends Controller
{
    /**
     * Extract only the time portion from a time/datetime string
     * Handles cases where the value might already contain a date
     */
    public function extractTimeOnly($timeValue): ?string
    {
        if (empty($timeValue)) {
            return null;
        }

        $timeValue = trim($timeValue);

        // If it looks like a datetime (contains space and has date format)
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+(\d{2}:\d{2}(:\d{2})?)/', $timeValue, $matches)) {
            return $matches[1];
        }

        // If it's just a time (HH:MM or HH:MM:SS)
        if (preg_match('/^(\d{2}:\d{2}(:\d{2})?)$/', $timeValue, $matches)) {
            return $matches[1];
        }

        // Try to parse with Carbon and extract time
        try {
            return Carbon::parse($timeValue)->format('H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get dashboard overview - All classes with teacher attendance status
     * Cached for 30 seconds to reduce server load
     *
     * OPTIMIZED: If no class_id is provided, only return class list
     * User must select a class first to see schedules
     */
    public function dashboardOverview(Request $request): JsonResponse
    {
        try {
            $today = Carbon::now()->format('Y-m-d');
            $dayOfWeek = Carbon::now()->isoFormat('dddd');

            // Map day names
            $dayMap = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];

            // Reverse map for converting Indonesian day to English
            $reverseDayMap = array_flip($dayMap);

            $hari = $dayMap[Carbon::now()->format('l')] ?? $dayOfWeek;

            // Get filter parameters
            $filterDay = $request->get('day', $hari);
            $filterClass = $request->get('class_id');
            $filterSubject = $request->get('subject_id');
            $filterDate = $request->get('date'); // Optional: specific date filter
            $filterWeekOffset = (int) $request->get('week_offset', 0); // 0 = this week, -1 = last week, etc.

            // Calculate week boundaries
            // Week starts on Monday
            $baseStartOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
            $baseEndOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);

            // Apply week offset if provided (use copy to avoid mutating original)
            $startOfThisWeek = $baseStartOfWeek->copy();
            $endOfThisWeek = $baseEndOfWeek->copy();
            if ($filterWeekOffset < 0) {
                $startOfThisWeek = $startOfThisWeek->addWeeks($filterWeekOffset);
                $endOfThisWeek = $endOfThisWeek->addWeeks($filterWeekOffset);
            }

            // Calculate the target date based on filterDay within the selected week
            $englishDay = $reverseDayMap[$filterDay] ?? null;
            $targetDayNum = match ($englishDay) {
                'Monday' => 1,
                'Tuesday' => 2,
                'Wednesday' => 3,
                'Thursday' => 4,
                'Friday' => 5,
                'Saturday' => 6,
                'Sunday' => 7,
                default => 1
            };

            // Calculate target date within the selected week
            $targetDate = $startOfThisWeek->copy()->addDays($targetDayNum - 1)->format('Y-m-d');

            // Check if the target date is in the future (for current week)
            $isFutureDate = Carbon::parse($targetDate)->startOfDay()->isAfter(Carbon::now()->startOfDay());

            // If specific date is provided, use it and calculate the week offset
            if ($filterDate) {
                $targetDate = $filterDate;
                $parsedDate = Carbon::parse($filterDate);
                $filterDay = $dayMap[$parsedDate->format('l')] ?? 'Senin';
                $isFutureDate = $parsedDate->startOfDay()->isAfter(Carbon::now()->startOfDay());

                // Calculate which week this date belongs to
                $weekStart = $parsedDate->copy()->startOfWeek(Carbon::MONDAY);
                $currentWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
                $filterWeekOffset = (int) $weekStart->diffInWeeks($currentWeekStart, false);
            }

            // Generate week info for the response
            $weekInfo = [
                'week_offset' => $filterWeekOffset,
                'week_start' => $startOfThisWeek->format('Y-m-d'),
                'week_end' => $endOfThisWeek->format('Y-m-d'),
                'week_label' => $filterWeekOffset == 0 ? 'Minggu Ini' : ($filterWeekOffset == -1 ? 'Minggu Lalu' :
                        'Minggu ' . abs($filterWeekOffset) . ' yang lalu'),
                'is_current_week' => $filterWeekOffset == 0,
                'is_future_date' => $isFutureDate,
            ];

            // If no class filter, return only class list (lightweight response)
            if (!$filterClass) {
                $classes = ClassModel::select('id', 'nama_kelas', 'level', 'major')
                    ->orderBy('level')
                    ->orderBy('major')
                    ->orderBy('nama_kelas')
                    ->get()
                    ->map(function ($class) {
                        return [
                            'id' => $class->id,
                            'name' => $class->nama_kelas,
                            'level' => $class->level,
                            'major' => $class->major,
                            'display_name' => "{$class->nama_kelas}"
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'message' => 'Pilih kelas untuk melihat jadwal',
                    'requires_class_filter' => true,
                    'date' => $today,
                    'target_date' => $targetDate,
                    'day' => $filterDay,
                    'week_info' => $weekInfo,
                    'stats' => [
                        'total_schedules' => 0,
                        'hadir' => 0,
                        'telat' => 0,
                        'tidak_hadir' => 0,
                        'pending' => 0,
                        'diganti' => 0,
                    ],
                    'data' => [],
                    'grouped_by_class' => [],
                    'available_classes' => $classes
                ]);
            }            // Cache key based on filters
            $cacheKey = "kurikulum_dashboard_{$targetDate}_{$filterDay}_{$filterClass}_{$filterSubject}";

            // Check if refresh is requested (bypass cache)
            $forceRefresh = $request->get('refresh', false);
            if ($forceRefresh) {
                Cache::forget($cacheKey);
            }

            // Reduced cache time to 10 seconds for more real-time updates
            $data = Cache::remember($cacheKey, 10, function () use ($targetDate, $filterDay, $filterClass, $filterSubject, $isFutureDate) {
                // Get class name from ID
                $classModel = ClassModel::find($filterClass);
                if (!$classModel) {
                    return collect([]);
                }

                $query = Schedule::with([
                    'class',
                    'subject:id,nama,kode',
                    'teacher:id,nama,nip'
                ])
                    ->where('hari', $filterDay)
                    ->where('kelas', $classModel->nama_kelas);

                if ($filterSubject) {
                    $subject = Subject::find($filterSubject);
                    if ($subject) {
                        $query->where('mata_pelajaran', $subject->nama);
                    }
                }

                $schedules = $query->orderBy('jam_mulai')->get();

                // Get schedule IDs for this class
                $scheduleIds = $schedules->pluck('id')->toArray();

                // Get all attendances for the target date AND for these schedules only
                $attendances = collect([]);
                if (!empty($scheduleIds)) {
                    $attendances = TeacherAttendance::where('tanggal', $targetDate)
                        ->whereIn('schedule_id', $scheduleIds)
                        ->get()
                        ->keyBy('schedule_id');
                }

                // Get all teacher IDs from schedules to check for leaves
                $teacherIds = $schedules->pluck('guru_id')->filter()->unique()->toArray();

                // Check for approved teacher leaves on target date
                $teachersOnLeave = [];
                if (count($teacherIds) > 0 && Schema::hasTable('leaves')) {
                    $teachersOnLeave = DB::table('leaves')
                        ->where('status', 'approved')
                        ->where('start_date', '<=', $targetDate)
                        ->where('end_date', '>=', $targetDate)
                        ->whereIn('teacher_id', $teacherIds)
                        ->get()
                        ->keyBy('teacher_id');
                }

                // Map schedules with attendance status
                return $schedules->map(function ($schedule) use ($attendances, $targetDate, $teachersOnLeave, $isFutureDate) {
                    $attendance = $attendances->get($schedule->id);

                    $status = 'pending'; // Default: waiting for confirmation
                    $statusColor = 'gray';
                    $lateMinutes = null;
                    $substituteTeacher = null;
                    $keterangan = null;
                    $teacherOnLeave = false;
                    $leaveReason = null;

                    // If this is a future date, always show as "belum" (not yet)
                    if ($isFutureDate) {
                        $status = 'belum';
                        $statusColor = 'gray';
                        $keterangan = 'Jadwal belum terjadi';

                        return [
                            'schedule_id' => $schedule->id,
                            'class_id' => $schedule->class->id ?? null,
                            'class_name' => $schedule->class->nama_kelas ?? $schedule->kelas ?? 'Unknown',
                            'class_level' => $schedule->class->level ?? null,
                            'class_major' => $schedule->class->major ?? null,
                            'subject_id' => $schedule->subject->id ?? null,
                            'subject_name' => $schedule->subject->nama ?? $schedule->mata_pelajaran ?? 'Unknown',
                            'subject_code' => $schedule->subject->kode ?? null,
                            'teacher_id' => $schedule->guru_id,
                            'teacher_name' => $schedule->teacher->nama ?? 'Unknown',
                            'teacher_nip' => $schedule->teacher->nip ?? null,
                            'period' => null,
                            'start_time' => $schedule->jam_mulai,
                            'end_time' => $schedule->jam_selesai,
                            'status' => $status,
                            'status_color' => $statusColor,
                            'late_minutes' => null,
                            'substitute_teacher' => null,
                            'keterangan' => $keterangan,
                            'attendance_id' => null,
                            'last_updated' => null,
                            'teacher_on_leave' => false,
                            'leave_reason' => null,
                            'is_future' => true
                        ];
                    }

                    // Check if teacher is on leave
                    if (isset($teachersOnLeave[$schedule->guru_id])) {
                        $leave = $teachersOnLeave[$schedule->guru_id];
                        $teacherOnLeave = true;
                        $leaveReason = $leave->reason ?? 'Izin';
                        $status = 'izin';
                        $statusColor = 'purple';

                        // Check if there's a substitute teacher assigned in leave
                        if (!empty($leave->substitute_teacher_id)) {
                            $substitute = Teacher::find($leave->substitute_teacher_id);
                            $substituteTeacher = $substitute ? $substitute->nama : null;
                        }
                    } elseif ($attendance) {
                        $status = $attendance->status;
                        switch ($attendance->status) {
                            case 'hadir':
                                $statusColor = 'green';
                                break;
                            case 'telat':
                                $statusColor = 'yellow';
                                // Calculate late minutes
                                if ($attendance->jam_masuk && $schedule->jam_mulai) {
                                    $jamMulai = $this->extractTimeOnly($schedule->jam_mulai);
                                    $jamMasuk = $this->extractTimeOnly($attendance->jam_masuk);
                                    if ($jamMulai && $jamMasuk) {
                                        $scheduledTime = Carbon::parse($targetDate . ' ' . $jamMulai);
                                        $actualTime = Carbon::parse($targetDate . ' ' . $jamMasuk);
                                        $lateMinutes = (int) $actualTime->diffInMinutes($scheduledTime);
                                    }
                                }
                                break;
                            case 'tidak_hadir':
                                $statusColor = 'red';
                                break;
                            case 'diganti':
                                $statusColor = 'blue';
                                if ($attendance->guru_asli_id) {
                                    $substitute = Teacher::find($attendance->guru_id);
                                    $substituteTeacher = $substitute ? $substitute->nama : null;
                                }
                                break;
                        }
                        $keterangan = $attendance->keterangan;
                    }

                    return [
                        'schedule_id' => $schedule->id,
                        'class_id' => $schedule->class->id ?? null,
                        'class_name' => $schedule->class->nama_kelas ?? $schedule->kelas ?? 'Unknown',
                        'class_level' => $schedule->class->level ?? null,
                        'class_major' => $schedule->class->major ?? null,
                        'subject_id' => $schedule->subject->id ?? null,
                        'subject_name' => $schedule->subject->nama ?? $schedule->mata_pelajaran ?? 'Unknown',
                        'subject_code' => $schedule->subject->kode ?? null,
                        'teacher_id' => $schedule->guru_id,
                        'teacher_name' => $schedule->teacher->nama ?? 'Unknown',
                        'teacher_nip' => $schedule->teacher->nip ?? null,
                        'period' => null,
                        'start_time' => $schedule->jam_mulai,
                        'end_time' => $schedule->jam_selesai,
                        'status' => $status,
                        'status_color' => $statusColor,
                        'late_minutes' => $lateMinutes,
                        'substitute_teacher' => $substituteTeacher,
                        'keterangan' => $keterangan,
                        'attendance_id' => $attendance->id ?? null,
                        'last_updated' => $attendance ? $attendance->updated_at->toISOString() : null,
                        'teacher_on_leave' => $teacherOnLeave,
                        'leave_reason' => $leaveReason,
                        'is_future' => false
                    ];
                });
            });            // Group by class for easy display
            $groupedByClass = collect($data)->groupBy('class_name');

            // Statistics
            $stats = [
                'total_schedules' => count($data),
                'hadir' => collect($data)->where('status', 'hadir')->count(),
                'telat' => collect($data)->where('status', 'telat')->count(),
                'tidak_hadir' => collect($data)->where('status', 'tidak_hadir')->count(),
                'pending' => collect($data)->where('status', 'pending')->count(),
                'diganti' => collect($data)->where('status', 'diganti')->count(),
                'izin' => collect($data)->where('status', 'izin')->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => $isFutureDate ? 'Tanggal ini belum terjadi (jadwal masa depan)' : 'Dashboard overview berhasil diambil',
                'date' => $targetDate,
                'day' => $filterDay,
                'week_info' => $weekInfo,
                'is_future_date' => $isFutureDate,
                'stats' => $stats,
                'data' => $data,
                'grouped_by_class' => $groupedByClass
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil dashboard overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get classes sorted by teacher status
     * Returns all classes but only shows teachers with tidak_hadir or telat status
     * Grouped by class for easy management
     */
    public function classManagement(Request $request): JsonResponse
    {
        try {
            $today = Carbon::now()->format('Y-m-d');
            $dayMap = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];
            $hari = $dayMap[Carbon::now()->format('l')] ?? 'Senin';

            // Get current period based on time
            $currentTime = Carbon::now()->format('H:i:s');

            // Get all schedules for today
            $schedules = Schedule::with([
                'class',
                'subject:id,nama',
                'teacher:id,nama,nip'
            ])
                ->where('hari', $hari)
                ->orderBy('kelas')
                ->orderBy('jam_mulai')
                ->get();

            // Get attendances for today
            $attendances = TeacherAttendance::where('tanggal', $today)
                ->get()
                ->keyBy('schedule_id');

            // Get all teacher IDs from schedules to check for leaves
            $teacherIds = $schedules->pluck('guru_id')->filter()->unique()->toArray();

            // Check for approved teacher leaves on target date
            $teachersOnLeave = [];
            if (count($teacherIds) > 0 && Schema::hasTable('leaves')) {
                $teachersOnLeave = DB::table('leaves')
                    ->where('status', 'approved')
                    ->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today)
                    ->whereIn('teacher_id', $teacherIds)
                    ->get()
                    ->keyBy('teacher_id');
            }

            // Map schedules with attendance status
            $result = $schedules->map(function ($schedule) use ($attendances, $today, $currentTime, $teachersOnLeave) {
                $attendance = $attendances->get($schedule->id);

                $status = 'pending';
                $lateMinutes = null;
                $substituteTeacherId = null;
                $substituteTeacherName = null;
                $keterangan = null;
                $isCurrentPeriod = false;
                $teacherOnLeave = false;
                $leaveReason = null;

                // Check if this is current period
                $jamMulaiRaw = $this->extractTimeOnly($schedule->jam_mulai);
                $jamSelesaiRaw = $this->extractTimeOnly($schedule->jam_selesai);
                if ($jamMulaiRaw && $jamSelesaiRaw) {
                    $isCurrentPeriod = $currentTime >= $jamMulaiRaw && $currentTime <= $jamSelesaiRaw;
                }

                // Check if class has been without teacher for more than 15 minutes
                $noTeacherAlert = false;
                if (!$attendance && $isCurrentPeriod && $jamMulaiRaw) {
                    $periodStart = Carbon::parse($today . ' ' . $jamMulaiRaw);
                    $minutesSinceStart = Carbon::now()->diffInMinutes($periodStart);
                    $noTeacherAlert = $minutesSinceStart >= 15;
                }

                // Check if teacher is on leave
                if (isset($teachersOnLeave[$schedule->guru_id])) {
                    $leave = $teachersOnLeave[$schedule->guru_id];
                    $teacherOnLeave = true;
                    $leaveReason = $leave->reason ?? 'Izin';
                    $status = 'izin';
                }

                if ($attendance) {
                    $status = $attendance->status;
                    $keterangan = $attendance->keterangan;

                    if ($attendance->status === 'telat' && $attendance->jam_masuk && $schedule->jam_mulai) {
                        $jamMulai = $this->extractTimeOnly($schedule->jam_mulai);
                        $jamMasuk = $this->extractTimeOnly($attendance->jam_masuk);
                        if ($jamMulai && $jamMasuk) {
                            $scheduledTime = Carbon::parse($today . ' ' . $jamMulai);
                            $actualTime = Carbon::parse($today . ' ' . $jamMasuk);
                            $lateMinutes = (int) $actualTime->diffInMinutes($scheduledTime);
                        }
                    }

                    if ($attendance->status === 'diganti' && $attendance->guru_id) {
                        $substituteTeacherId = $attendance->guru_id;
                        $substitute = Teacher::find($attendance->guru_id);
                        $substituteTeacherName = $substitute ? $substitute->nama : null;
                    }
                }

                return [
                    'schedule_id' => $schedule->id,
                    'class_id' => $schedule->class->id ?? null,
                    'class_name' => $schedule->class->nama_kelas ?? $schedule->kelas ?? 'Unknown',
                    'class_level' => $schedule->class->level ?? null,
                    'class_major' => $schedule->class->major ?? null,
                    'subject_id' => $schedule->subject->id ?? null,
                    'subject_name' => $schedule->subject->nama ?? $schedule->mata_pelajaran ?? 'Unknown',
                    'teacher_id' => $schedule->guru_id,
                    'teacher_name' => $schedule->teacher->nama ?? 'Unknown',
                    'teacher_nip' => $schedule->teacher->nip ?? null,
                    'period' => null,
                    'start_time' => $jamMulaiRaw,
                    'end_time' => $jamSelesaiRaw,
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                    'substitute_teacher_id' => $substituteTeacherId,
                    'substitute_teacher_name' => $substituteTeacherName,
                    'keterangan' => $keterangan,
                    'attendance_id' => $attendance->id ?? null,
                    'is_current_period' => $isCurrentPeriod,
                    'no_teacher_alert' => $noTeacherAlert,
                    'teacher_on_leave' => $teacherOnLeave,
                    'leave_reason' => $leaveReason,
                    'needs_substitute' => in_array($status, ['tidak_hadir', 'izin']) || $noTeacherAlert
                ];
            });

            // Count total by status (before filtering)
            $allStatusCounts = [
                'hadir' => $result->where('status', 'hadir')->count(),
                'telat' => $result->where('status', 'telat')->count(),
                'tidak_hadir' => $result->where('status', 'tidak_hadir')->count(),
                'pending' => $result->where('status', 'pending')->count(),
                'diganti' => $result->where('status', 'diganti')->count(),
                'izin' => $result->where('status', 'izin')->count(),
            ];

            // Filter: show classes that need SUBSTITUTE teacher assignment
            // - tidak_hadir = perlu assign guru pengganti
            // - izin = guru izin, mungkin perlu pengganti
            // - telat = informasi saja
            // NOTE: 'pending' status handled in separate Pending Management screen
            $needsAttention = $result->filter(function ($item) {
                return in_array($item['status'], ['tidak_hadir', 'telat', 'izin']) || $item['no_teacher_alert'];
            });

            // Sort by priority: tidak_hadir > izin > telat
            $statusPriority = ['tidak_hadir' => 0, 'izin' => 1, 'telat' => 2];
            $needsAttention = $needsAttention->sortBy(function ($item) use ($statusPriority) {
                return $statusPriority[$item['status']] ?? 99;
            })->values();

            // Group by class
            $groupedByClass = $needsAttention->groupBy('class_name')->map(function ($items, $className) {
                $firstItem = $items->first();
                return [
                    'class_id' => $firstItem['class_id'],
                    'class_name' => $className,
                    'class_level' => $firstItem['class_level'],
                    'class_major' => $firstItem['class_major'],
                    'total_issues' => $items->count(),
                    'has_urgent' => $items->where('status', 'tidak_hadir')->count() > 0,
                    'has_pending' => $items->where('status', 'pending')->count() > 0,
                    'schedules' => $items->values()
                ];
            })->values();

            // Classes with alert (no teacher > 15 minutes)
            $alertClasses = $result->where('no_teacher_alert', true)->values();

            // Summary for quick view
            $summary = [
                'total_classes_need_attention' => $groupedByClass->count(),
                'total_schedules_need_attention' => $needsAttention->count(),
                'pending_count' => $needsAttention->where('status', 'pending')->count(),
                'tidak_hadir_count' => $needsAttention->where('status', 'tidak_hadir')->count(),
                'telat_count' => $needsAttention->where('status', 'telat')->count(),
                'izin_count' => $needsAttention->where('status', 'izin')->count(),
                'alert_count' => $alertClasses->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Data manajemen kelas berhasil diambil',
                'date' => $today,
                'day' => $hari,
                'current_time' => $currentTime,
                'summary' => $summary,
                'status_counts' => $allStatusCounts,
                'alert_classes' => $alertClasses,
                'grouped_by_class' => $groupedByClass,
                'data' => $needsAttention
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data manajemen kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available substitute teachers
     */
    public function getAvailableSubstitutes(Request $request): JsonResponse
    {
        try {
            $today = Carbon::now()->format('Y-m-d');
            $dayMap = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];
            $hari = $dayMap[Carbon::now()->format('l')] ?? 'Senin';

            $periodNumber = $request->get('period');
            $subjectId = $request->get('subject_id');
            $startTime = $request->get('start_time');

            // Get all teachers
            $allTeachers = Teacher::select('id', 'nama', 'nip')
                ->orderBy('nama')
                ->get();

            // Get teachers who are currently teaching at this time slot
            $busyQuery = Schedule::where('hari', $hari);
            if ($startTime) {
                $busyQuery->where('jam_mulai', $startTime);
            }
            $busyTeacherIds = $busyQuery->pluck('guru_id')->toArray();

            // Filter available teachers
            $availableTeachers = $allTeachers->filter(function ($teacher) use ($busyTeacherIds) {
                return !in_array($teacher->id, $busyTeacherIds);
            })->values();            // Prioritize teachers who teach the same subject
            if ($subjectId) {
                // Check if teacher_subject table exists
                if (Schema::hasTable('teacher_subject')) {
                    $subjectTeacherIds = DB::table('teacher_subject')
                        ->where('subject_id', $subjectId)
                        ->pluck('teacher_id')
                        ->toArray();

                    $availableTeachers = $availableTeachers->sortByDesc(function ($teacher) use ($subjectTeacherIds) {
                        return in_array($teacher->id, $subjectTeacherIds) ? 1 : 0;
                    })->values();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Daftar guru pengganti berhasil diambil',
                'period' => $periodNumber,
                'data' => $availableTeachers->map(function ($teacher) use ($subjectId) {
                    return [
                        'id' => $teacher->id,
                        'name' => $teacher->nama,
                        'nip' => $teacher->nip,
                        'is_subject_teacher' => false // Will be updated if subject matching
                    ];
                })
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar guru pengganti',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign substitute teacher to a class
     */
    public function assignSubstitute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'required|exists:schedules,id',
            'substitute_teacher_id' => 'required|exists:teachers,id',
            'keterangan' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $today = Carbon::now()->format('Y-m-d');
            $schedule = Schedule::findOrFail($request->schedule_id);

            // Check if attendance record exists
            $attendance = TeacherAttendance::where('schedule_id', $request->schedule_id)
                ->where('tanggal', $today)
                ->first();

            if ($attendance) {
                // Update existing record
                $attendance->update([
                    'guru_asli_id' => $schedule->guru_id,
                    'guru_id' => $request->substitute_teacher_id,
                    'status' => 'diganti',
                    'keterangan' => $request->keterangan ?? 'Guru diganti oleh kurikulum',
                    'assigned_by' => auth()->id()
                ]);
            } else {
                // Create new record
                $attendance = TeacherAttendance::create([
                    'schedule_id' => $request->schedule_id,
                    'guru_asli_id' => $schedule->guru_id,
                    'guru_id' => $request->substitute_teacher_id,
                    'tanggal' => $today,
                    'jam_masuk' => Carbon::now()->format('H:i:s'),
                    'status' => 'diganti',
                    'keterangan' => $request->keterangan ?? 'Guru diganti oleh kurikulum',
                    'created_by' => auth()->id(),
                    'assigned_by' => auth()->id()
                ]);
            }

            // Clear cache
            $dayMap = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];
            $hari = $dayMap[Carbon::now()->format('l')] ?? 'Senin';
            Cache::forget("kurikulum_dashboard_{$today}_{$hari}__");

            // Get substitute teacher info
            $substituteTeacher = Teacher::find($request->substitute_teacher_id);

            return response()->json([
                'success' => true,
                'message' => 'Guru pengganti berhasil ditugaskan',
                'data' => [
                    'attendance_id' => $attendance->id,
                    'schedule_id' => $request->schedule_id,
                    'original_teacher_id' => $schedule->guru_id,
                    'substitute_teacher_id' => $request->substitute_teacher_id,
                    'substitute_teacher_name' => $substituteTeacher->nama ?? 'Unknown',
                    'status' => 'diganti',
                    'assigned_at' => Carbon::now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menugaskan guru pengganti',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance history with filters and statistics
     */
    public function attendanceHistory(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $limit = min($request->get('limit', 20), 100);
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $teacherId = $request->get('teacher_id');
            $classId = $request->get('class_id');
            $status = $request->get('status');

            $query = TeacherAttendance::with([
                'schedule',
                'schedule.class',
                'schedule.subject:id,nama',
                'guru:id,nama,nip',
                'guruAsli:id,nama,nip'
            ])
                ->orderBy('tanggal', 'desc')
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($dateFrom) {
                $query->where('tanggal', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('tanggal', '<=', $dateTo);
            }
            if ($teacherId) {
                $query->where(function ($q) use ($teacherId) {
                    $q->where('guru_id', $teacherId)
                        ->orWhere('guru_asli_id', $teacherId);
                });
            }
            if ($classId) {
                // classId is class ID, we need to get the nama_kelas
                $classModel = ClassModel::find($classId);
                if ($classModel) {
                    $query->whereHas('schedule', function ($q) use ($classModel) {
                        $q->where('kelas', $classModel->nama_kelas);
                    });
                }
            }
            if ($status) {
                $query->where('status', $status);
            }

            $total = $query->count();
            $attendances = $query->skip(($page - 1) * $limit)->take($limit)->get();

            $controller = $this;
            $data = $attendances->map(function ($attendance) use ($controller) {
                $jamMulai = $controller->extractTimeOnly($attendance->schedule->jam_mulai ?? null);
                $jamSelesai = $controller->extractTimeOnly($attendance->schedule->jam_selesai ?? null);
                $jamMasuk = $controller->extractTimeOnly($attendance->jam_masuk);

                return [
                    'id' => $attendance->id,
                    'date' => $attendance->tanggal->format('Y-m-d'),
                    'day' => $attendance->schedule->hari ?? null,
                    'period' => null,
                    'time' => $attendance->schedule && $jamMulai && $jamSelesai ?
                        ($jamMulai . ' - ' . $jamSelesai) : null,
                    'class_name' => $attendance->schedule->class->nama_kelas ?? 'Unknown',
                    'class_level' => $attendance->schedule->class->level ?? null,
                    'subject_name' => $attendance->schedule->subject->nama ?? 'Unknown',
                    'original_teacher_id' => $attendance->guru_asli_id,
                    'original_teacher_name' => $attendance->guruAsli->nama ?? null,
                    'teacher_id' => $attendance->guru_id,
                    'teacher_name' => $attendance->guru->nama ?? 'Unknown',
                    'status' => $attendance->status,
                    'arrival_time' => $jamMasuk,
                    'keterangan' => $attendance->keterangan,
                    'is_substituted' => $attendance->guru_asli_id !== null,
                    'created_at' => $attendance->created_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Riwayat kehadiran berhasil diambil',
                'data' => $data,
                'pagination' => [
                    'current_page' => (int) $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => ceil($total / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil riwayat kehadiran',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance statistics for reports
     */
    public function attendanceStatistics(Request $request): JsonResponse
    {
        try {
            $month = $request->get('month', Carbon::now()->month);
            $year = $request->get('year', Carbon::now()->year);
            $teacherId = $request->get('teacher_id');

            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();

            $query = TeacherAttendance::whereBetween('tanggal', [$startDate, $endDate]);

            if ($teacherId) {
                $query->where(function ($q) use ($teacherId) {
                    $q->where('guru_id', $teacherId)
                        ->orWhere('guru_asli_id', $teacherId);
                });
            }

            $attendances = $query->get();

            $stats = [
                'month' => $month,
                'year' => $year,
                'month_name' => Carbon::create($year, $month, 1)->isoFormat('MMMM'),
                'total_records' => $attendances->count(),
                'hadir' => $attendances->where('status', 'hadir')->count(),
                'telat' => $attendances->where('status', 'telat')->count(),
                'tidak_hadir' => $attendances->where('status', 'tidak_hadir')->count(),
                'diganti' => $attendances->where('status', 'diganti')->count(),
                'percentage' => [
                    'hadir' => $attendances->count() > 0 ?
                        round(($attendances->where('status', 'hadir')->count() / $attendances->count()) * 100, 1) : 0,
                    'telat' => $attendances->count() > 0 ?
                        round(($attendances->where('status', 'telat')->count() / $attendances->count()) * 100, 1) : 0,
                    'tidak_hadir' => $attendances->count() > 0 ?
                        round(($attendances->where('status', 'tidak_hadir')->count() / $attendances->count()) * 100, 1) : 0,
                    'diganti' => $attendances->count() > 0 ?
                        round(($attendances->where('status', 'diganti')->count() / $attendances->count()) * 100, 1) : 0
                ]
            ];

            // Get daily breakdown
            $dailyStats = $attendances->groupBy(function ($item) {
                return $item->tanggal->format('Y-m-d');
            })->map(function ($dayGroup) {
                return [
                    'hadir' => $dayGroup->where('status', 'hadir')->count(),
                    'telat' => $dayGroup->where('status', 'telat')->count(),
                    'tidak_hadir' => $dayGroup->where('status', 'tidak_hadir')->count(),
                    'diganti' => $dayGroup->where('status', 'diganti')->count(),
                    'total' => $dayGroup->count()
                ];
            });

            // Get per teacher statistics if not filtered by teacher
            $teacherStats = [];
            if (!$teacherId) {
                $teacherStats = $attendances->groupBy('guru_id')->map(function ($group, $guruId) {
                    $teacher = Teacher::find($guruId);
                    return [
                        'teacher_id' => $guruId,
                        'teacher_name' => $teacher->nama ?? 'Unknown',
                        'hadir' => $group->where('status', 'hadir')->count(),
                        'telat' => $group->where('status', 'telat')->count(),
                        'tidak_hadir' => $group->where('status', 'tidak_hadir')->count(),
                        'total' => $group->count()
                    ];
                })->values();
            }

            return response()->json([
                'success' => true,
                'message' => 'Statistik kehadiran berhasil diambil',
                'statistics' => $stats,
                'daily_breakdown' => $dailyStats,
                'teacher_statistics' => $teacherStats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik kehadiran',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export attendance data to CSV
     */
    public function exportAttendance(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
            $teacherId = $request->get('teacher_id');
            $classId = $request->get('class_id');

            $query = TeacherAttendance::with([
                'schedule.class',
                'schedule.subject:id,nama',
                'guru:id,nama',
                'guruAsli:id,nama'
            ])
                ->whereBetween('tanggal', [$dateFrom, $dateTo])
                ->orderBy('tanggal', 'desc');

            if ($teacherId) {
                $query->where(function ($q) use ($teacherId) {
                    $q->where('guru_id', $teacherId)
                        ->orWhere('guru_asli_id', $teacherId);
                });
            }

            if ($classId) {
                // classId is class ID, we need to get the nama_kelas
                $classModel = ClassModel::find($classId);
                if ($classModel) {
                    $query->whereHas('schedule', function ($q) use ($classModel) {
                        $q->where('kelas', $classModel->nama_kelas);
                    });
                }
            }

            $attendances = $query->get();

            $exportData = $attendances->map(function ($att) {
                return [
                    'tanggal' => $att->tanggal->format('Y-m-d'),
                    'hari' => $att->schedule->hari ?? '',
                    'jam_ke' => '',
                    'waktu' => ($att->schedule->jam_mulai ?? '') . ' - ' . ($att->schedule->jam_selesai ?? ''),
                    'kelas' => $att->schedule->class->nama_kelas ?? $att->schedule->kelas ?? '',
                    'mata_pelajaran' => $att->schedule->subject->nama ?? $att->schedule->mata_pelajaran ?? '',
                    'guru_asli' => $att->guruAsli->nama ?? ($att->guru->nama ?? ''),
                    'guru_pengganti' => $att->guru_asli_id ? ($att->guru->nama ?? '') : '',
                    'status' => $att->status,
                    'jam_masuk' => $att->jam_masuk ? Carbon::parse($att->jam_masuk)->format('H:i') : '',
                    'keterangan' => $att->keterangan ?? ''
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data export berhasil disiapkan',
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'total_records' => $exportData->count(),
                'data' => $exportData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengexport data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get students in a class (for viewing class details)
     */
    public function getClassStudents(Request $request, $classId): JsonResponse
    {
        try {
            $students = User::where('class_id', $classId)
                ->where('role', 'siswa')
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get();

            $class = ClassModel::find($classId);

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diambil',
                'class' => [
                    'id' => $class->id ?? null,
                    'name' => $class->nama_kelas ?? 'Unknown',
                    'level' => $class->level ?? null,
                    'major' => $class->major ?? null
                ],
                'total_students' => $students->count(),
                'students' => $students
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data siswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     /**
     * Get list of all classes for filters
     */
    public function getClasses(): JsonResponse
    {
        try {
            $classes = ClassModel::select('id', 'nama_kelas', 'level', 'major')
                ->orderBy('level')
                ->orderBy('nama_kelas')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $classes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of all teachers for filters
     */
    public function getTeachers(): JsonResponse
    {
        try {
            $teachers = Teacher::select('id', 'nama', 'nip')
                ->orderBy('nama')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $teachers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all pending attendances for confirmation
     */
    public function getPendingAttendances(Request $request): JsonResponse
    {
        try {
            $targetDate = $request->get('date', Carbon::now()->format('Y-m-d'));
            $dayName = Carbon::parse($targetDate)->isoFormat('dddd');

            // Map Indonesian day names
            $dayMap = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];
            $hari = $dayMap[$dayName] ?? $dayName;

            // Get schedules for today
            $schedules = Schedule::with(['guru:id,nama,nip', 'class', 'subject:id,nama'])
                ->where('hari', $hari)
                ->orderBy('jam_mulai')
                ->get();

            $result = [];
            $controller = $this;

            foreach ($schedules as $schedule) {
                // Check existing attendance
                $attendance = TeacherAttendance::where('schedule_id', $schedule->id)
                    ->where('tanggal', $targetDate)
                    ->first();

                // Only include pending status
                if ($attendance && $attendance->status === 'pending') {
                    $jamMulai = $controller->extractTimeOnly($schedule->jam_mulai);
                    $jamSelesai = $controller->extractTimeOnly($schedule->jam_selesai);
                    $jamMasuk = $controller->extractTimeOnly($attendance->jam_masuk);

                    $result[] = [
                        'id' => $attendance->id,
                        'schedule_id' => $schedule->id,
                        'date' => $targetDate,
                        'day' => $hari,
                        'time_start' => $jamMulai,
                        'time_end' => $jamSelesai,
                        'arrival_time' => $jamMasuk,
                        'class_id' => $schedule->class->id ?? null,
                        'class_name' => $schedule->class->nama_kelas ?? $schedule->kelas,
                        'subject_name' => $schedule->subject->nama ?? $schedule->mata_pelajaran,
                        'teacher_id' => $schedule->guru_id,
                        'teacher_name' => $schedule->guru->nama ?? 'Unknown',
                        'teacher_nip' => $schedule->guru->nip ?? null,
                        'status' => $attendance->status,
                        'keterangan' => $attendance->keterangan,
                        'created_at' => $attendance->created_at->toISOString()
                    ];
                }
            }

            // Group by class
            $groupedByClass = collect($result)->groupBy('class_name')->map(function ($items, $className) {
                return [
                    'class_name' => $className,
                    'class_id' => $items->first()['class_id'],
                    'total_pending' => $items->count(),
                    'schedules' => $items->values()->toArray()
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Data kehadiran pending berhasil diambil',
                'data' => [
                    'date' => $targetDate,
                    'day' => $hari,
                    'total_pending' => count($result),
                    'grouped_by_class' => $groupedByClass,
                    'all_pending' => $result
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kehadiran pending',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm pending attendance to hadir/telat
     */
    public function confirmAttendance(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'attendance_id' => 'required|integer|exists:teacher_attendances,id',
                'status' => 'required|in:hadir,telat',
                'keterangan' => 'nullable|string|max:255'
            ]);

            $attendance = TeacherAttendance::findOrFail($request->attendance_id);

            // Only allow confirmation of pending status
            if ($attendance->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kehadiran ini sudah dikonfirmasi sebelumnya',
                    'current_status' => $attendance->status
                ], 400);
            }

            // Determine final status based on arrival time vs schedule time
            $schedule = $attendance->schedule;
            $finalStatus = $request->status;

            if ($schedule && $attendance->jam_masuk) {
                $jamMulai = $this->extractTimeOnly($schedule->jam_mulai);
                $jamMasuk = $this->extractTimeOnly($attendance->jam_masuk);

                if ($jamMulai && $jamMasuk) {
                    $scheduledTime = Carbon::parse($jamMulai);
                    $actualTime = Carbon::parse($jamMasuk);

                    // Auto-detect telat if arrival > scheduled + 5 minutes
                    if ($actualTime->gt($scheduledTime->copy()->addMinutes(5))) {
                        $finalStatus = 'telat';
                    }
                }
            }

            $attendance->update([
                'status' => $finalStatus,
                'keterangan' => $request->keterangan ?? $attendance->keterangan
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kehadiran berhasil dikonfirmasi',
                'data' => [
                    'id' => $attendance->id,
                    'status' => $finalStatus,
                    'teacher_name' => $attendance->guru->nama ?? 'Unknown',
                    'class_name' => $attendance->schedule->class->nama_kelas ?? 'Unknown',
                    'subject_name' => $attendance->schedule->subject->nama ?? 'Unknown'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengkonfirmasi kehadiran',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk confirm multiple pending attendances
     */
    public function bulkConfirmAttendance(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'attendance_ids' => 'required|array|min:1',
                'attendance_ids.*' => 'integer|exists:teacher_attendances,id',
                'status' => 'required|in:hadir,telat'
            ]);

            $confirmed = 0;
            $skipped = 0;
            $results = [];

            foreach ($request->attendance_ids as $attendanceId) {
                $attendance = TeacherAttendance::find($attendanceId);

                if (!$attendance || $attendance->status !== 'pending') {
                    $skipped++;
                    continue;
                }

                // Determine final status
                $schedule = $attendance->schedule;
                $finalStatus = $request->status;

                if ($schedule && $attendance->jam_masuk) {
                    $jamMulai = $this->extractTimeOnly($schedule->jam_mulai);
                    $jamMasuk = $this->extractTimeOnly($attendance->jam_masuk);

                    if ($jamMulai && $jamMasuk) {
                        $scheduledTime = Carbon::parse($jamMulai);
                        $actualTime = Carbon::parse($jamMasuk);

                        if ($actualTime->gt($scheduledTime->copy()->addMinutes(5))) {
                            $finalStatus = 'telat';
                        }
                    }
                }

                $attendance->update(['status' => $finalStatus]);
                $confirmed++;

                $results[] = [
                    'id' => $attendance->id,
                    'status' => $finalStatus,
                    'teacher_name' => $attendance->guru->nama ?? 'Unknown'
                ];
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil mengkonfirmasi $confirmed kehadiran" . ($skipped > 0 ? ", $skipped dilewati" : ""),
                'data' => [
                    'confirmed_count' => $confirmed,
                    'skipped_count' => $skipped,
                    'results' => $results
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengkonfirmasi kehadiran',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
