<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TeacherAttendance;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TeacherAttendanceExport;

class WebTeacherAttendanceController extends Controller
{
    /**
     * Display teacher attendance index page - OPTIMIZED VERSION
     */
    public function index(Request $request)
    {
        // Enhanced caching with better keys and longer cache times
        $subjects = Cache::remember('attendance_subjects_v2', 7200, function () {
            return Subject::select('id', 'nama')
                ->orderBy('nama')
                ->get()
                ->map(function ($subject) {
                    $subject->nama_mapel = $subject->nama;
                    $subject->name = $subject->nama;
                    return $subject;
                });
        });

        $teachers = Cache::remember('attendance_teachers_v2', 3600, function () {
            return User::where('role', '!=', 'siswa')
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get();
        });

        // Add current date range for better UX
        $defaultDateFrom = now()->startOfMonth()->format('Y-m-d');
        $defaultDateTo = now()->format('Y-m-d');

        return view('teacher-attendance.index', compact('subjects', 'teachers', 'defaultDateFrom', 'defaultDateTo'));
    }

    /**
     * Get attendance data for AJAX requests - OPTIMIZED VERSION
     */
    public function getData(Request $request): JsonResponse
    {
        try {
            // TEMPORARY: Remove all filters to debug
            $query = TeacherAttendance::query();

            // For debugging, let's see what records exist
            \Log::info('Total teacher attendances in DB: ' . TeacherAttendance::count());

            // Apply optimized filters - but make them optional for debugging
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('tanggal', [$request->date_from, $request->date_to]);
                \Log::info('Filtering by date range: ' . $request->date_from . ' to ' . $request->date_to);
            } elseif ($request->filled('date_from')) {
                $query->where('tanggal', '>=', $request->date_from);
            } elseif ($request->filled('date_to')) {
                $query->where('tanggal', '<=', $request->date_to);
            }

            if ($request->filled('teacher_id')) {
                $query->where('guru_id', $request->teacher_id);
            }

            if ($request->filled('status')) {
                if ($request->status === 'present') {
                    $query->whereIn('status', ['hadir', 'diganti']);
                } elseif ($request->status === 'absent') {
                    $query->whereIn('status', ['tidak_hadir']);
                } elseif ($request->status === 'late') {
                    $query->where('status', 'telat');
                } elseif ($request->status === 'on_leave') {
                    $query->where('status', 'diganti');
                }
            }

            // Store search term for later use in relationship loading
            $searchTerm = $request->filled('search') ? $request->search : null;

            $perPage = min($request->get('per_page', 15), 50);
            $attendances = $query->orderBy('tanggal', 'desc')
                ->orderBy('jam_masuk', 'desc')
                ->paginate($perPage);

            \Log::info('Query returned ' . $attendances->total() . ' records');

            // Add relationship data manually but optimized
            $filteredAttendances = collect();
            foreach ($attendances as $attendance) {
                // Use single optimized queries instead of multiple
                $scheduleData = Cache::remember("schedule_{$attendance->schedule_id}", 3600, function () use ($attendance) {
                    return DB::table('schedules')
                        ->where('id', $attendance->schedule_id)
                        ->select('id', 'mata_pelajaran', 'kelas', 'hari', 'jam_mulai', 'jam_selesai')
                        ->first();
                });

                $guruData = Cache::remember("user_{$attendance->guru_id}", 3600, function () use ($attendance) {
                    return DB::table('users')
                        ->where('id', $attendance->guru_id)
                        ->select('id', 'name', 'email')
                        ->first();
                });

                $guruAsliData = $attendance->guru_asli_id ?
                    Cache::remember("user_{$attendance->guru_asli_id}", 3600, function () use ($attendance) {
                        return DB::table('users')
                            ->where('id', $attendance->guru_asli_id)
                            ->select('id', 'name', 'email')
                            ->first();
                    }) : null;

                // Apply search filter if provided (client-side filtering as fallback)
                if ($searchTerm) {
                    $searchMatch = false;
                    if ($guruData && stripos($guruData->name, $searchTerm) !== false) {
                        $searchMatch = true;
                    }
                    if ($scheduleData &&
                        (stripos($scheduleData->mata_pelajaran, $searchTerm) !== false ||
                         stripos($scheduleData->kelas, $searchTerm) !== false)) {
                        $searchMatch = true;
                    }
                    if (!$searchMatch) {
                        continue; // Skip this record if it doesn't match search
                    }
                }

                // Format data to match frontend expectations
                $attendance->schedule = $scheduleData ? (object)[
                    'id' => $scheduleData->id,
                    'mata_pelajaran' => $scheduleData->mata_pelajaran ?? 'N/A',
                    'kelas' => $scheduleData->kelas ?? 'N/A',
                    'hari' => $scheduleData->hari ?? 'N/A',
                    'jam_mulai' => $scheduleData->jam_mulai ?? 'N/A',
                    'jam_selesai' => $scheduleData->jam_selesai ?? 'N/A',
                    'subject' => (object)['nama_mapel' => $scheduleData->mata_pelajaran ?? 'N/A'],
                    'class_model' => (object)['nama_kelas' => $scheduleData->kelas ?? 'N/A']
                ] : null;

                $attendance->guru = $guruData ? (object)[
                    'id' => $guruData->id,
                    'name' => $guruData->name ?? 'N/A',
                    'nama' => $guruData->name ?? 'N/A', // Add backward compatibility
                    'email' => $guruData->email ?? 'N/A'
                ] : null;

                $attendance->guru_asli = $guruAsliData ? (object)[
                    'id' => $guruAsliData->id,
                    'name' => $guruAsliData->name ?? 'N/A',
                    'nama' => $guruAsliData->name ?? 'N/A'
                ] : null;

                $filteredAttendances->push($attendance);
            }

            // Replace attendances with filtered results
            $attendances->setCollection($filteredAttendances);

            // Cache statistics for 5 minutes
            $cacheKey = 'attendance_stats_' . md5(serialize($request->all()));
            $stats = Cache::remember($cacheKey, 300, function () use ($query) {
                return $this->getOptimizedAttendanceStats($query);
            });

            return response()->json([
                'success' => true,
                'data' => $attendances,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading attendance data: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get attendance statistics - OPTIMIZED VERSION
     */
    private function getOptimizedAttendanceStats($query)
    {
        // Use single query with conditional aggregation for better performance
        $stats = (clone $query)->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status IN ("hadir", "diganti") THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = "tidak_hadir" THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = "telat" THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN status = "diganti" THEN 1 ELSE 0 END) as on_leave
        ')->first();

        return [
            'total' => (int) $stats->total,
            'present' => (int) $stats->present,
            'absent' => (int) $stats->absent,
            'late' => (int) $stats->late,
            'on_leave' => (int) $stats->on_leave,
        ];
    }

    /**
     * Get attendance statistics - LEGACY VERSION (kept for compatibility)
     */
    private function getAttendanceStats($query)
    {
        $baseQuery = clone $query;

        return [
            'total' => $baseQuery->count(),
            'present' => (clone $baseQuery)->whereIn('status', ['hadir', 'diganti'])->count(),
            'absent' => (clone $baseQuery)->where('status', 'tidak_hadir')->count(),
            'late' => (clone $baseQuery)->where('status', 'telat')->count(),
            'on_leave' => (clone $baseQuery)->where('status', 'diganti')->count(),
        ];
    }

    /**
     * Export attendance data to PDF
     */
    public function exportPdf(Request $request)
    {
        $query = $this->buildExportQuery($request);

        $attendances = $query->with([
            'schedule.subject',
            'schedule.class',
            'guru:id,name',
            'guruAsli:id,name'
        ])->limit(10000)->get(); // Add reasonable limit for exports

        $stats = $this->getAttendanceStats($query);

        $pdf = Pdf::loadView('teacher-attendance.export-pdf', compact('attendances', 'stats', 'request'));

        return $pdf->download('teacher-attendance-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export attendance data to Excel
     */
    public function exportExcel(Request $request)
    {
        return Excel::download(new TeacherAttendanceExport($request), 'teacher-attendance-' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Build query for exports
     */
    private function buildExportQuery(Request $request)
    {
        $query = TeacherAttendance::query();

        // Apply same filters as getData method
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('tanggal', [$request->date_from, $request->date_to]);
        } elseif ($request->filled('date_from')) {
            $query->where('tanggal', '>=', $request->date_from);
        } elseif ($request->filled('date_to')) {
            $query->where('tanggal', '<=', $request->date_to);
        }

        if ($request->filled('subject_id')) {
            $subject = Subject::find($request->subject_id);
            if ($subject) {
                $query->whereHas('schedule', function ($q) use ($subject) {
                    $q->where('mata_pelajaran', $subject->nama);
                });
            }
        }

        if ($request->filled('teacher_id')) {
            $query->where('guru_id', $request->teacher_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'present') {
                $query->whereIn('status', ['hadir', 'diganti']);
            } elseif ($request->status === 'absent') {
                $query->whereIn('status', ['tidak_hadir']);
            } elseif ($request->status === 'late') {
                $query->where('status', 'telat');
            } elseif ($request->status === 'on_leave') {
                $query->where('status', 'diganti');
            }
        }

        return $query->orderBy('tanggal', 'desc')->orderBy('jam_masuk');
    }

    /**
     * Show attendance details
     */
    public function show($id)
    {
        $attendance = TeacherAttendance::findOrFail($id);

        // Manually load related data
        $schedule = \DB::table('schedules')->where('id', $attendance->schedule_id)->first();
        $guru = \DB::table('users')->where('id', $attendance->guru_id)->first();
        $guruAsli = $attendance->guru_asli_id ? \DB::table('users')->where('id', $attendance->guru_asli_id)->first() : null;
        $createdBy = \DB::table('users')->where('id', $attendance->created_by)->first();
        $assignedBy = $attendance->assigned_by ? \DB::table('users')->where('id', $attendance->assigned_by)->first() : null;

        // Add related data to attendance object
        $attendance->schedule = $schedule ? (object)[
            'id' => $schedule->id,
            'mata_pelajaran' => $schedule->mata_pelajaran ?? 'N/A',
            'kelas' => $schedule->kelas ?? 'N/A',
            'hari' => $schedule->hari ?? 'N/A',
            'jam_mulai' => $schedule->jam_mulai ?? 'N/A',
            'jam_selesai' => $schedule->jam_selesai ?? 'N/A'
        ] : null;

        $attendance->guru = $guru ? (object)[
            'id' => $guru->id,
            'name' => $guru->name ?? 'N/A',
            'email' => $guru->email ?? 'N/A'
        ] : null;

        $attendance->guru_asli = $guruAsli ? (object)[
            'id' => $guruAsli->id,
            'name' => $guruAsli->name ?? 'N/A',
            'email' => $guruAsli->email ?? 'N/A'
        ] : null;

        $attendance->created_by_user = $createdBy ? (object)[
            'id' => $createdBy->id,
            'name' => $createdBy->name ?? 'N/A'
        ] : null;

        $attendance->assigned_by_user = $assignedBy ? (object)[
            'id' => $assignedBy->id,
            'name' => $assignedBy->name ?? 'N/A'
        ] : null;

        return view('teacher-attendance.show', compact('attendance'));
    }

    /**
     * Get available substitute teachers for AJAX
     */
    public function getSubstituteTeachers(Request $request): JsonResponse
    {
        $scheduleId = $request->schedule_id;
        $date = $request->date;
        $excludeTeacherId = $request->exclude_teacher_id;

        $schedule = Schedule::find($scheduleId);
        if (!$schedule) {
            return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan']);
        }

        // Get teachers who are available (not on leave, not busy with other schedules)
        $availableTeachers = User::where('role', '!=', 'siswa')
            ->where('id', '!=', $excludeTeacherId)
            ->whereDoesntHave('leaves', function ($query) use ($date) {
                $query->where('status', 'approved')
                    ->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date);
            })
            ->whereDoesntHave('teacherAttendances', function ($query) use ($date, $schedule) {
                $query->where('tanggal', $date)
                    ->where('schedule_id', '!=', $schedule->id)
                    ->whereIn('status', ['hadir', 'telat']);
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $availableTeachers
        ]);
    }
}
