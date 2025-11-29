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
     * Display teacher attendance index page
     */
    public function index(Request $request)
    {
        $subjects = Cache::remember('subjects_for_attendance', 3600, function () {
            return Subject::select('id', 'nama')->get()->map(function ($subject) {
                $subject->nama_mapel = $subject->nama; // Add backward compatibility
                $subject->name = $subject->nama; // English alias
                return $subject;
            });
        });

        $teachers = Cache::remember('teachers_for_attendance', 3600, function () {
            return User::where('role', '!=', 'siswa')
                ->select('id', 'name')
                ->orderBy('name')
                ->limit(100) // Add reasonable limit for substitute teachers list
                ->get();
        });

        return view('teacher-attendance.index', compact('subjects', 'teachers'));
    }

    /**
     * Get attendance data for AJAX requests
     */
    public function getData(Request $request): JsonResponse
    {
        try {
            // Simple query without complex relationships first
            $query = TeacherAttendance::query();

            // Apply basic filters
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('tanggal', [$request->date_from, $request->date_to]);
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

            $attendances = $query->orderBy('tanggal', 'desc')
                ->orderBy('jam_masuk')
                ->paginate($request->get('per_page', 15));

            // Add basic relationship data manually for now
            foreach ($attendances as $attendance) {
                $schedule = \DB::table('schedules')->where('id', $attendance->schedule_id)->first();
                $guru = \DB::table('users')->where('id', $attendance->guru_id)->first();
                $guruAsli = $attendance->guru_asli_id ? \DB::table('users')->where('id', $attendance->guru_asli_id)->first() : null;

                $attendance->schedule = $schedule ? (object)[
                    'id' => $schedule->id,
                    'mata_pelajaran' => $schedule->mata_pelajaran ?? 'N/A',
                    'kelas' => $schedule->kelas ?? 'N/A'
                ] : null;

                $attendance->guru = $guru ? (object)[
                    'id' => $guru->id,
                    'name' => $guru->name ?? 'N/A'
                ] : null;

                $attendance->guru_asli = $guruAsli ? (object)[
                    'id' => $guruAsli->id,
                    'name' => $guruAsli->name ?? 'N/A'
                ] : null;
            }

            return response()->json([
                'success' => true,
                'data' => $attendances,
                'stats' => $this->getAttendanceStats($query)
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
     * Get attendance statistics
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
