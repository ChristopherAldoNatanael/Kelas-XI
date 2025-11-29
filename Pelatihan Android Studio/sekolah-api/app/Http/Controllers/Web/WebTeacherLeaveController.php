<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\User;
use App\Models\TeacherAttendance;
use App\Models\Schedule;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
        $teachers = User::where('role', '!=', 'siswa')
                       ->select('id', 'name', 'nama')
                       ->orderBy('name')
                       ->get();

        return view('teacher-leave.index', compact('teachers'));
    }

    /**
     * Get leave data for AJAX
     */
    public function getData(Request $request): JsonResponse
    {
        $query = Leave::with(['teacher:id,name,nama', 'substituteTeacher:id,name,nama', 'approvedBy:id,name']);

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
                    $subQ->where('name', 'like', "%{$search}%")
                         ->orWhere('nama', 'like', "%{$search}%");
                })
                ->orWhere('reason', 'like', "%{$search}%")
                ->orWhere('custom_reason', 'like', "%{$search}%");
            });
        }

        $leaves = $query->orderBy('created_at', 'desc')
                        ->paginate($request->get('per_page', 15));

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
     * Show create leave form
     */
    public function create()
    {
        $teachers = User::where('role', '!=', 'siswa')
                       ->select('id', 'name', 'nama')
                       ->orderBy('name')
                       ->get();

        return view('teacher-leave.create', compact('teachers'));
    }

    /**
     * Store new leave request
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:users,id',
            'reason' => 'required|in:sakit,cuti_tahunan,urusan_keluarga,acara_resmi,lainnya',
            'custom_reason' => 'required_if:reason,lainnya|string|max:255',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'substitute_teacher_id' => 'nullable|exists:users,id|different:teacher_id',
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

        // Check for schedule conflicts
        if ($this->hasScheduleConflict($request->teacher_id, $request->start_date, $request->end_date)) {
            return response()->json([
                'success' => false,
                'message' => 'Guru memiliki jadwal mengajar pada periode tersebut'
            ], 422);
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
                'custom_reason' => $request->reason === 'lainnya' ? $request->custom_reason : null,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'substitute_teacher_id' => $request->substitute_teacher_id,
                'attachment' => $attachmentPath,
                'created_by' => auth()->id(),
            ]);

            // Log activity
            ActivityLog::log('create_leave', "Pengajuan izin baru oleh {$leave->teacher->name}", $leave);

            // Send notification email
            try {
                Mail::to($leave->teacher->email)->send(new LeaveSubmitted($leave));
            } catch (\Exception $e) {
                // Log email error but don't fail the request
                \Log::error('Failed to send leave submission email: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin berhasil dibuat',
                'data' => $leave->load(['teacher:id,name,nama', 'substituteTeacher:id,name,nama'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pengajuan izin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show leave details
     */
    public function show($id)
    {
        $leave = Leave::with([
            'teacher:id,name,nama,email',
            'substituteTeacher:id,name,nama,email',
            'approvedBy:id,name',
            'createdBy:id,name'
        ])->findOrFail($id);

        return view('teacher-leave.show', compact('leave'));
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $leave = Leave::findOrFail($id);
        $teachers = User::where('role', '!=', 'siswa')
                       ->select('id', 'name', 'nama')
                       ->orderBy('name')
                       ->get();

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
            'substitute_teacher_id' => 'nullable|exists:users,id|different:teacher_id',
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
                'data' => $leave->load(['teacher:id,name,nama', 'substituteTeacher:id,name,nama'])
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

            // Assign substitute teacher to schedules if specified
            if ($leave->substitute_teacher_id) {
                $this->assignSubstituteTeacher($leave);
            }

            // Log activity
            ActivityLog::log('approve_leave', "Izin disetujui oleh " . auth()->user()->name, $leave, $oldValues, $leave->toArray());

            // Send notification emails
            try {
                Mail::to($leave->teacher->email)->send(new LeaveApproved($leave));
                if ($leave->substituteTeacher) {
                    Mail::to($leave->substituteTeacher->email)->send(new LeaveApproved($leave, true));
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

            // Send notification email
            try {
                Mail::to($leave->teacher->email)->send(new LeaveRejected($leave));
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
     * Check for schedule conflicts
     */
    private function hasScheduleConflict($teacherId, $startDate, $endDate): bool
    {
        return Schedule::where('guru_id', $teacherId)
                      ->whereBetween('tanggal', [$startDate, $endDate])
                      ->exists();
    }

    /**
     * Assign substitute teacher to schedules
     */
    private function assignSubstituteTeacher(Leave $leave)
    {
        $schedules = Schedule::where('guru_id', $leave->teacher_id)
                            ->whereBetween('tanggal', [$leave->start_date, $leave->end_date])
                            ->get();

        foreach ($schedules as $schedule) {
            // Create or update attendance record for substitute teacher
            TeacherAttendance::updateOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'tanggal' => $schedule->tanggal,
                ],
                [
                    'guru_id' => $leave->substitute_teacher_id,
                    'guru_asli_id' => $leave->teacher_id,
                    'status' => 'diganti',
                    'keterangan' => 'Pengganti ' . $leave->teacher->name,
                    'created_by' => auth()->id(),
                ]
            );
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
            ->select('id', 'name', 'nama')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $availableTeachers
        ]);
    }
}
