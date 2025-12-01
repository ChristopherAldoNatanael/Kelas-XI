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
{    /**
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
            $hari = $dayMap[Carbon::now()->format('l')] ?? $dayOfWeek;

            // Get filter parameters
            $filterDay = $request->get('day', $hari);
            $filterClass = $request->get('class_id');
            $filterSubject = $request->get('subject_id');

            // If no class filter, return only class list (lightweight response)
            if (!$filterClass) {
                $classes = ClassModel::select('id', 'nama_kelas', 'level', 'major')
                    ->orderBy('level')
                    ->orderBy('major')
                    ->orderBy('nama_kelas')
                    ->get()
                    ->map(function($class) {
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
                    'day' => $filterDay,
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
            }

            // Cache key based on filters
            $cacheKey = "kurikulum_dashboard_{$today}_{$filterDay}_{$filterClass}_{$filterSubject}";

            $data = Cache::remember($cacheKey, 30, function () use ($today, $filterDay, $filterClass, $filterSubject) {
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

                // Get all attendances for today
                $attendances = TeacherAttendance::where('tanggal', $today)
                    ->get()
                    ->keyBy('schedule_id');

                // Map schedules with attendance status
                return $schedules->map(function ($schedule) use ($attendances, $today) {
                    $attendance = $attendances->get($schedule->id);

                    $status = 'pending'; // Default: waiting for confirmation
                    $statusColor = 'gray';
                    $lateMinutes = null;
                    $substituteTeacher = null;
                    $keterangan = null;

                    if ($attendance) {
                        $status = $attendance->status;
                        switch ($attendance->status) {
                            case 'hadir':
                                $statusColor = 'green';
                                break;
                            case 'telat':
                                $statusColor = 'yellow';
                                // Calculate late minutes
                                if ($attendance->jam_masuk && $schedule->jam_mulai) {
                                    $scheduledTime = Carbon::parse($today . ' ' . $schedule->jam_mulai);
                                    $actualTime = Carbon::parse($today . ' ' . $attendance->jam_masuk);
                                    $lateMinutes = $actualTime->diffInMinutes($scheduledTime);
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
                        'last_updated' => $attendance ? $attendance->updated_at->toISOString() : null
                    ];
                });
            });

            // Group by class for easy display
            $groupedByClass = collect($data)->groupBy('class_name');

            // Statistics
            $stats = [
                'total_schedules' => count($data),
                'hadir' => collect($data)->where('status', 'hadir')->count(),
                'telat' => collect($data)->where('status', 'telat')->count(),
                'tidak_hadir' => collect($data)->where('status', 'tidak_hadir')->count(),
                'pending' => collect($data)->where('status', 'pending')->count(),
                'diganti' => collect($data)->where('status', 'diganti')->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard overview berhasil diambil',
                'date' => $today,
                'day' => $filterDay,
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

            $filterStatus = $request->get('status'); // hadir, telat, tidak_hadir, pending

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

            // Map and filter
            $result = $schedules->map(function ($schedule) use ($attendances, $today, $currentTime) {
                $attendance = $attendances->get($schedule->id);

                $status = 'pending';
                $lateMinutes = null;
                $substituteTeacherId = null;
                $substituteTeacherName = null;
                $keterangan = null;
                $isCurrentPeriod = false;

                // Check if this is current period
                if ($schedule->jam_mulai && $schedule->jam_selesai) {
                    $isCurrentPeriod = $currentTime >= $schedule->jam_mulai && $currentTime <= $schedule->jam_selesai;
                }

                // Check if class has been without teacher for more than 15 minutes
                $noTeacherAlert = false;
                if (!$attendance && $isCurrentPeriod) {
                    $periodStart = Carbon::parse($today . ' ' . $schedule->jam_mulai);
                    $minutesSinceStart = Carbon::now()->diffInMinutes($periodStart);
                    $noTeacherAlert = $minutesSinceStart >= 15;
                }

                if ($attendance) {
                    $status = $attendance->status;
                    $keterangan = $attendance->keterangan;

                    if ($attendance->status === 'telat' && $attendance->jam_masuk && $schedule->jam_mulai) {
                        $scheduledTime = Carbon::parse($today . ' ' . $schedule->jam_mulai);
                        $actualTime = Carbon::parse($today . ' ' . $attendance->jam_masuk);
                        $lateMinutes = $actualTime->diffInMinutes($scheduledTime);
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
                    'period' => null,
                    'start_time' => $schedule->jam_mulai,
                    'end_time' => $schedule->jam_selesai,
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                    'substitute_teacher_id' => $substituteTeacherId,
                    'substitute_teacher_name' => $substituteTeacherName,
                    'keterangan' => $keterangan,
                    'attendance_id' => $attendance->id ?? null,
                    'is_current_period' => $isCurrentPeriod,
                    'no_teacher_alert' => $noTeacherAlert
                ];
            });

            // Apply status filter if provided
            if ($filterStatus) {
                $result = $result->filter(function ($item) use ($filterStatus) {
                    return $item['status'] === $filterStatus;
                });
            }

            // Sort by status priority: tidak_hadir > pending > telat > hadir
            $statusPriority = ['tidak_hadir' => 1, 'pending' => 2, 'telat' => 3, 'diganti' => 4, 'hadir' => 5];
            $result = $result->sortBy(function ($item) use ($statusPriority) {
                return $statusPriority[$item['status']] ?? 99;
            })->values();

            // Count by status
            $statusCounts = [
                'hadir' => $result->where('status', 'hadir')->count(),
                'telat' => $result->where('status', 'telat')->count(),
                'tidak_hadir' => $result->where('status', 'tidak_hadir')->count(),
                'pending' => $result->where('status', 'pending')->count(),
                'diganti' => $result->where('status', 'diganti')->count(),
            ];

            // Classes with alert (no teacher > 15 minutes)
            $alertClasses = $result->where('no_teacher_alert', true)->values();

            return response()->json([
                'success' => true,
                'message' => 'Data manajemen kelas berhasil diambil',
                'date' => $today,
                'day' => $hari,
                'current_time' => $currentTime,
                'status_counts' => $statusCounts,
                'alert_classes' => $alertClasses,
                'data' => $result->values()
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

            $data = $attendances->map(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'date' => $attendance->tanggal->format('Y-m-d'),
                    'day' => $attendance->schedule->hari ?? null,
                    'period' => null,
                    'time' => $attendance->schedule ?
                        ($attendance->schedule->jam_mulai . ' - ' . $attendance->schedule->jam_selesai) : null,
                    'class_name' => $attendance->schedule->class->nama_kelas ?? 'Unknown',
                    'class_level' => $attendance->schedule->class->level ?? null,
                    'subject_name' => $attendance->schedule->subject->nama ?? 'Unknown',
                    'original_teacher_id' => $attendance->guru_asli_id,
                    'original_teacher_name' => $attendance->guruAsli->nama ?? null,
                    'teacher_id' => $attendance->guru_id,
                    'teacher_name' => $attendance->guru->nama ?? 'Unknown',
                    'status' => $attendance->status,
                    'arrival_time' => $attendance->jam_masuk ?
                        Carbon::parse($attendance->jam_masuk)->format('H:i') : null,
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
}
