<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\User;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\Schedule;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TeacherLeaveExport;
use App\Mail\LeaveApproved;
use App\Mail\LeaveRejected;
use App\Mail\LeaveSubmitted;

class WebTeacherLeaveController extends Controller
{
    /**
     * Display leave management index
     */
    public function index(Request $request)
    {
        // Get teachers directly from teachers table
        $teachers = Teacher::select('id', 'nama', 'nip')
            ->where('status', 'active')
            ->orderBy('nama')
            ->get()
            ->map(function ($teacher) {
                return (object) [
                    'id' => $teacher->id, // Use teacher ID as user ID for compatibility
                    'nama' => $teacher->nama, // Use nama field from teachers table
                    'nip' => $teacher->nip,
                ];
            });

        // Get statistics for the hero header
        $pendingCount = Leave::where('status', 'pending')->count();
        $approvedCount = Leave::where('status', 'approved')->count();
        $rejectedCount = Leave::where('status', 'rejected')->count();

        // Get leave data for initial page load (paginated) with left joins to handle missing teachers
        $leaves = Leave::leftJoin('teachers as t', 'leaves.teacher_id', '=', 't.id')
            ->leftJoin('teachers as st', 'leaves.substitute_teacher_id', '=', 'st.id')
            ->select('leaves.*', 't.nama as teacher_nama', 'st.nama as substitute_teacher_nama')
            ->orderBy('leaves.created_at', 'desc')
            ->paginate(12);

        // Manually attach teacher and substituteTeacher objects for compatibility
        foreach ($leaves as $leave) {
            $leave->teacher = $leave->teacher_nama ? (object)['nama' => $leave->teacher_nama] : null;
            $leave->substituteTeacher = $leave->substitute_teacher_nama ? (object)['nama' => $leave->substitute_teacher_nama] : null;
            unset($leave->teacher_nama, $leave->substitute_teacher_nama);
        }

        return view('teacher-leave.index', compact('teachers', 'pendingCount', 'approvedCount', 'rejectedCount', 'leaves'));
    }

    /**
     * Get leave data for AJAX
     */
    public function getData(Request $request): JsonResponse
    {
        $query = Leave::leftJoin('teachers as t', 'leaves.teacher_id', '=', 't.id')
            ->leftJoin('teachers as st', 'leaves.substitute_teacher_id', '=', 'st.id')
            ->leftJoin('users as ab', 'leaves.approved_by', '=', 'ab.id')
            ->select('leaves.*', 't.nama as teacher_nama', 'st.nama as substitute_teacher_nama', 'ab.name as approved_by_name');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->date_from, $request->date_to])
                    ->orWhereBetween('end_date', [$request->date_from, $request->date_to])
                    ->orWhere(function ($subQ) use ($request) {
                        $subQ->where('start_date', '<=', $request->date_from)
                            ->where('end_date', '>=', $request->date_to);
                    });
            });
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('teacher', function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%");
                })
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('custom_reason', 'like', "%{$search}%");
            });
        }

        $leaves = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Manually attach relationship objects for compatibility
        foreach ($leaves as $leave) {
            $leave->teacher = $leave->teacher_nama ? (object)['nama' => $leave->teacher_nama] : null;
            $leave->substituteTeacher = $leave->substitute_teacher_nama ? (object)['nama' => $leave->substitute_teacher_nama] : null;
            $leave->approvedBy = $leave->approved_by_name ? (object)['name' => $leave->approved_by_name] : null;
            unset($leave->teacher_nama, $leave->substitute_teacher_nama, $leave->approved_by_name);
        }

        return response()->json([
            'success' => true,
            'data' => $leaves,
            'stats' => $this->getLeaveStats()
        ]);
    }

    /**
     * Get leave statistics
     */
    private function getLeaveStats()
    {
        return [
            'total' => Leave::count(),
            'pending' => Leave::where('status', 'pending')->count(),
            'approved' => Leave::where('status', 'approved')->count(),
            'rejected' => Leave::where('status', 'rejected')->count(),
        ];
    }

    /**
     * Export leave data to PDF
     */
    public function exportPdf(Request $request)
    {
        $query = $this->buildExportQuery($request);

        $leaves = $query->with([
            'teacher:id,nama',
            'substituteTeacher:id,nama',
            'approvedBy:id,name'
        ])->orderBy('created_at', 'desc')->get();

        $stats = $this->getLeaveStats();

        $pdf = Pdf::loadView('teacher-leave.export-pdf', compact('leaves', 'stats', 'request'));

        return $pdf->download('teacher-leave-report-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export leave data to Excel
     */
    public function exportExcel(Request $request)
    {
        return Excel::download(new TeacherLeaveExport($request), 'teacher-leave-report-' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Build query for exports
     */
    private function buildExportQuery(Request $request)
    {
        $query = Leave::leftJoin('teachers as t', 'leaves.teacher_id', '=', 't.id')
            ->leftJoin('teachers as st', 'leaves.substitute_teacher_id', '=', 'st.id')
            ->leftJoin('users as ab', 'leaves.approved_by', '=', 'ab.id')
            ->select('leaves.*', 't.nama as teacher_nama', 'st.nama as substitute_teacher_nama', 'ab.name as approved_by_name');

        // Apply same filters as getData method
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->date_from, $request->date_to])
                    ->orWhereBetween('end_date', [$request->date_from, $request->date_to])
                    ->orWhere(function ($subQ) use ($request) {
                        $subQ->where('start_date', '<=', $request->date_from)
                            ->where('end_date', '>=', $request->date_to);
                    });
            });
        }

        return $query;
    }

    /**
     * Show create leave form
     */
    public function create()
    {
        // Get teachers directly from teachers table
        // Since teachers table is decoupled from users, we'll use teacher IDs as user IDs for compatibility
        $teachers = Teacher::select('id', 'nama', 'nip', 'position')
            ->where('status', 'active')
            ->orderBy('nama')
            ->get()
            ->map(function ($teacher) {
                return (object) [
                    'id' => $teacher->id, // Use teacher ID as user ID for compatibility
                    'nama' => $teacher->nama, // Use nama field from teachers table
                    'nip' => $teacher->nip,
                    'position' => $teacher->position,
                ];
            });

        // Get statistics for the hero header
        $pendingCount = Leave::where('status', 'pending')->count();
        $approvedCount = Leave::where('status', 'approved')->count();
        $rejectedCount = Leave::where('status', 'rejected')->count();

        return view('teacher-leave.create', compact('teachers', 'pendingCount', 'approvedCount', 'rejectedCount'));
    }

    /**
     * Store new leave request
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'reason' => 'required|in:sakit,cuti_tahunan,urusan_keluarga,acara_resmi,lainnya',
            'custom_reason' => 'nullable|string|max:255',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'substitute_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        // Custom validation for custom_reason when reason is 'lainnya'
        $validator->after(function ($validator) use ($request) {
            if ($request->reason === 'lainnya' && empty(trim($request->custom_reason))) {
                $validator->errors()->add('custom_reason', 'Custom reason is required when reason is "Lainnya"');
            }
        });

        if ($validator->fails()) {
            // Check if this is an AJAX request
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            } else {
                // Web form submission - redirect back with errors
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        // Validate duration
        $startDate = \Carbon\Carbon::parse($request->start_date);
        $endDate = \Carbon\Carbon::parse($request->end_date);
        $days = $startDate->diffInDays($endDate) + 1;

        if ($days > 30) {
            $errorMessage = 'Durasi izin maksimal 30 hari';

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 422);
            } else {
                return redirect()->back()
                    ->withErrors(['duration' => $errorMessage])
                    ->withInput();
            }
        }

        // Check for schedule conflicts with approved leaves
        if ($this->hasScheduleConflict($request->teacher_id, $request->start_date, $request->end_date)) {
            $errorMessage = 'Guru sudah memiliki izin yang disetujui pada periode tanggal yang sama';

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 422);
            } else {
                return redirect()->back()
                    ->withErrors(['conflict' => $errorMessage])
                    ->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('leave-attachments', 'public');
            }

            $leave = Leave::create([
                'teacher_id' => $request->teacher_id,
                'reason' => $request->reason,
                'custom_reason' => $request->reason === 'lainnya' ? trim($request->custom_reason) : null,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'substitute_teacher_id' => $request->substitute_teacher_id,
                'attachment' => $attachmentPath,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Log activity
            ActivityLog::log('create_leave', "Pengajuan izin baru oleh {$leave->teacher->name}", $leave);

            // Send notification email (skip if teacher has no email)
            try {
                if ($leave->teacher && method_exists($leave->teacher, 'getAttribute') && $leave->teacher->getAttribute('email')) {
                    Mail::to($leave->teacher->email)->send(new LeaveSubmitted($leave));
                } else {
                    \Log::info('Skipping leave submission email - teacher has no email', ['leave_id' => $leave->id, 'teacher_id' => $leave->teacher_id]);
                }
            } catch (\Exception $e) {
                // Log email error but don't fail the request
                \Log::error('Failed to send leave submission email: ' . $e->getMessage());
            }

            DB::commit();

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pengajuan izin berhasil dibuat',
                    'data' => $leave->load(['teacher:id,nama', 'substituteTeacher:id,nama'])
                ], 201);
            } else {
                // Web form submission - redirect with success message
                return redirect()->route('teacher-leaves.index')
                    ->with('success', 'Pengajuan izin berhasil dibuat');
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat pengajuan izin',
                    'error' => $e->getMessage()
                ], 500);
            } else {
                return redirect()->back()
                    ->withErrors(['general' => 'Gagal membuat pengajuan izin: ' . $e->getMessage()])
                    ->withInput();
            }
        }
    }

    /**
     * Show leave details - RETURN JSON FOR MODAL
     */
    public function show($id)
    {
        $leave = Leave::with([
            'teacher:id,nama',
            'substituteTeacher:id,nama',
            'approvedBy:id,name',
            'createdBy:id,name'
        ])->findOrFail($id);

        // Check if this is an AJAX request
        if (request()->ajax() || request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $leave
            ]);
        }

        // For regular requests, redirect to index with info
        $teacherName = $leave->teacher ? $leave->teacher->name : 'Unknown Teacher';
        return redirect()->route('teacher-leaves.index')
            ->with('info', "Detail izin {$teacherName} - {$leave->reason} ({$leave->start_date} sampai {$leave->end_date})");
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $leave = Leave::findOrFail($id);

        // Get teachers directly from teachers table
        $teachers = Teacher::select('id', 'nama', 'nip', 'position')
            ->where('status', 'active')
            ->orderBy('nama')
            ->get()
            ->map(function ($teacher) {
                return (object) [
                    'id' => $teacher->id, // Use teacher ID as user ID for compatibility
                    'nama' => $teacher->nama, // Use nama field from teachers table
                    'nip' => $teacher->nip,
                    'position' => $teacher->position,
                ];
            });

        return view('teacher-leave.edit', compact('leave', 'teachers'));
    }

    /**
     * Update leave
     */
    public function update(Request $request, $id)
    {
        $leave = Leave::findOrFail($id);

        // Only allow updates for pending leaves
        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Izin yang sudah diproses tidak dapat diubah'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|in:sakit,cuti_tahunan,urusan_keluarga,acara_resmi,lainnya',
            'custom_reason' => 'required_if:reason,lainnya|string|max:255',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'substitute_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate duration
        $startDate = \Carbon\Carbon::parse($request->start_date);
        $endDate = \Carbon\Carbon::parse($request->end_date);
        $days = $startDate->diffInDays($endDate) + 1;

        if ($days > 30) {
            return response()->json([
                'success' => false,
                'message' => 'Durasi izin maksimal 30 hari'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldValues = $leave->toArray();

            $attachmentPath = $leave->attachment;
            if ($request->hasFile('attachment')) {
                // Delete old attachment
                if ($leave->attachment) {
                    Storage::disk('public')->delete($leave->attachment);
                }
                $attachmentPath = $request->file('attachment')->store('leave-attachments', 'public');
            }

            $leave->update([
                'reason' => $request->reason,
                'custom_reason' => $request->reason === 'lainnya' ? $request->custom_reason : null,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'substitute_teacher_id' => $request->substitute_teacher_id,
                'attachment' => $attachmentPath,
            ]);

            // Log activity
            ActivityLog::log('update_leave', "Izin diperbarui oleh {$leave->teacher->name}", $leave, $oldValues, $leave->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Izin berhasil diperbarui',
                'data' => $leave->load(['teacher:id,nama', 'substituteTeacher:id,nama'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui izin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve leave
     */
    public function approve(Request $request, $id)
    {
        $leave = Leave::findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Izin sudah diproses'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldValues = $leave->toArray();

            $leave->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Create attendance records for the leave period
            $this->createLeaveAttendanceRecords($leave);

            // Assign substitute teacher to schedules if specified
            if ($leave->substitute_teacher_id) {
                $this->assignSubstituteTeacher($leave);
            }

            // Log activity
            ActivityLog::log('approve_leave', "Izin disetujui oleh " . auth()->user()->name, $leave, $oldValues, $leave->toArray());

            // Send notification emails (skip if teacher/substitute has no email)
            try {
                if ($leave->teacher && method_exists($leave->teacher, 'getAttribute') && $leave->teacher->getAttribute('email')) {
                    Mail::to($leave->teacher->email)->send(new LeaveApproved($leave));
                } else {
                    \Log::info('Skipping leave approval email to teacher - no email', ['leave_id' => $leave->id, 'teacher_id' => $leave->teacher_id]);
                }

                if ($leave->substituteTeacher && method_exists($leave->substituteTeacher, 'getAttribute') && $leave->substituteTeacher->getAttribute('email')) {
                    Mail::to($leave->substituteTeacher->email)->send(new LeaveApproved($leave, true));
                } else if ($leave->substituteTeacher) {
                    \Log::info('Skipping leave approval email to substitute teacher - no email', ['leave_id' => $leave->id, 'substitute_teacher_id' => $leave->substitute_teacher_id]);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send leave approval emails: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Izin berhasil disetujui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui izin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject leave
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Alasan penolakan harus diisi',
                'errors' => $validator->errors()
            ], 422);
        }

        $leave = Leave::findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Izin sudah diproses'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldValues = $leave->toArray();

            $leave->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Log activity
            ActivityLog::log('reject_leave', "Izin ditolak oleh " . auth()->user()->name, $leave, $oldValues, $leave->toArray());

            // Send notification email (skip if teacher has no email)
            try {
                if ($leave->teacher && method_exists($leave->teacher, 'getAttribute') && $leave->teacher->getAttribute('email')) {
                    Mail::to($leave->teacher->email)->send(new LeaveRejected($leave));
                } else {
                    \Log::info('Skipping leave rejection email - teacher has no email', ['leave_id' => $leave->id, 'teacher_id' => $leave->teacher_id]);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send leave rejection email: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Izin berhasil ditolak'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menolak izin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete leave
     */
    public function destroy($id)
    {
        $leave = Leave::findOrFail($id);

        // Only allow deletion of pending leaves
        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Izin yang sudah diproses tidak dapat dihapus'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Delete attachment if exists
            if ($leave->attachment) {
                Storage::disk('public')->delete($leave->attachment);
            }

            $leave->delete();

            // Log activity
            ActivityLog::log('delete_leave', "Izin dihapus oleh " . auth()->user()->name, null, $leave->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Izin berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus izin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check for schedule conflicts with approved leaves
     */
    private function hasScheduleConflict($teacherId, $startDate, $endDate): bool
    {
        // Check if teacher has approved leaves that overlap with the requested dates
        $overlappingLeaves = Leave::where('teacher_id', $teacherId)
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($subQuery) use ($startDate, $endDate) {
                        $subQuery->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->count();

        return $overlappingLeaves > 0;
    }

    /**
     * Create attendance records for approved leave period
     */
    private function createLeaveAttendanceRecords(Leave $leave)
    {
        $startDate = \Carbon\Carbon::parse($leave->start_date);
        $endDate = \Carbon\Carbon::parse($leave->end_date);

        // Map English day names to Indonesian
        $dayMap = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        // Create attendance records for each day of the leave
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            // Get Indonesian day name from date
            $dayNameEnglish = $date->format('l'); // Monday, Tuesday, etc.
            $dayNameIndonesian = $dayMap[$dayNameEnglish] ?? $dayNameEnglish;

            // Find schedules for this teacher on this day of week (using 'hari' column)
            $schedules = Schedule::where('guru_id', $leave->teacher_id)
                ->where('hari', $dayNameIndonesian)
                ->get();

            foreach ($schedules as $schedule) {
                // Create or update attendance record for the leave
                TeacherAttendance::updateOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'tanggal' => $date->format('Y-m-d'),
                    ],
                    [
                        'guru_id' => $leave->teacher_id,
                        'guru_asli_id' => null, // No substitute for regular leave
                        'status' => 'izin', // Mark as on leave
                        'keterangan' => 'Izin: ' . $leave->reason . ($leave->custom_reason ? ' - ' . $leave->custom_reason : ''),
                        'created_by' => auth()->id(),
                    ]
                );
            }

            // Note: We don't create attendance records if no schedules exist for that day
            // This is intentional - attendance records are tied to specific schedules
        }
    }

    /**
     * Assign substitute teacher to schedules
     */
    private function assignSubstituteTeacher(Leave $leave)
    {
        $startDate = \Carbon\Carbon::parse($leave->start_date);
        $endDate = \Carbon\Carbon::parse($leave->end_date);

        // Map English day names to Indonesian
        $dayMap = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        // Get all schedules for this teacher
        $teacherSchedules = Schedule::where('guru_id', $leave->teacher_id)->get();

        // For each day in the leave period, find matching schedules by day of week
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dayNameEnglish = $date->format('l');
            $dayNameIndonesian = $dayMap[$dayNameEnglish] ?? $dayNameEnglish;

            // Filter schedules that match this day
            $matchingSchedules = $teacherSchedules->filter(function ($schedule) use ($dayNameIndonesian) {
                return $schedule->hari === $dayNameIndonesian;
            });

            foreach ($matchingSchedules as $schedule) {
                // Update the existing attendance record to show substitute teacher
                TeacherAttendance::updateOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'tanggal' => $date->format('Y-m-d'),
                    ],
                    [
                        'guru_id' => $leave->substitute_teacher_id,
                        'guru_asli_id' => $leave->teacher_id,
                        'status' => 'diganti',
                        'keterangan' => 'Pengganti ' . ($leave->teacher ? $leave->teacher->nama : 'Unknown Teacher') . ' - Izin: ' . $leave->reason,
                        'created_by' => auth()->id(),
                    ]
                );
            }
        }
    }

    /**
     * Get available substitute teachers
     */
    public function getSubstituteTeachers(Request $request): JsonResponse
    {
        $teacherId = $request->teacher_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $availableTeachers = User::where('role', '!=', 'siswa')
            ->where('id', '!=', $teacherId)
            ->whereDoesntHave('leaves', function ($query) use ($startDate, $endDate) {
                $query->where('status', 'approved')
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate])
                            ->orWhere(function ($subQ) use ($startDate, $endDate) {
                                $subQ->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                            });
                    });
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
