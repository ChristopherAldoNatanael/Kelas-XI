<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Schedule;

use App\Services\ScheduleOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebScheduleController extends Controller
{
    protected $scheduleService;

    public function __construct(ScheduleOptimizationService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    public function dashboard()
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $schedules = Schedule::with(['guru:id,name', 'subject:id,nama'])
                ->orderBy('hari')
                ->orderBy('jam_mulai')
                ->limit(10)
                ->get();

            return view('dashboard', compact('schedules'));
        } catch (\Exception $e) {
            return view('dashboard', ['schedules' => collect(), 'error' => $e->getMessage()]);
        }
    }

    public function index(Request $request)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            // Query directly to get proper data
            $query = Schedule::query();

            // Apply filters based on actual database columns
            if ($request->has('hari') && !empty($request->hari)) {
                $query->where('hari', $request->hari);
            }
            if ($request->has('kelas') && !empty($request->kelas)) {
                $query->where('kelas', $request->kelas);
            }
            if ($request->has('guru_id') && !empty($request->guru_id)) {
                $query->where('guru_id', $request->guru_id);
            }
            if ($request->has('mata_pelajaran') && !empty($request->mata_pelajaran)) {
                $query->where('mata_pelajaran', 'like', '%' . $request->mata_pelajaran . '%');
            }

            // Get schedules with relationships
            $scheduleModels = $query->with([
                'guru:id,name',
                'subject:id,nama,kode'
            ])->orderBy('hari', 'asc')
                ->orderBy('jam_mulai', 'asc')
                ->get();

            // Transform to array format with proper data structure
            $schedules = $scheduleModels->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'hari' => $schedule->hari,
                    'kelas' => $schedule->kelas,
                    'mata_pelajaran' => $schedule->mata_pelajaran,
                    'guru_nama' => $schedule->guru?->name ?? 'Unknown Teacher',
                    'jam_mulai' => $schedule->jam_mulai,
                    'jam_selesai' => $schedule->jam_selesai,
                    'ruang' => $schedule->ruang,
                    'created_at' => $schedule->created_at,
                    'updated_at' => $schedule->updated_at
                ];
            })->toArray();

            // Get dropdown data efficiently
            $dropdownData = [
                'classes' => \App\Models\ClassModel::select('id', 'nama_kelas', 'kode_kelas')->get(),
                'subjects' => \App\Models\Subject::select('id', 'nama', 'kode')->get(),
                'teachers' => \App\Models\User::where('role', 'guru')->select('id', 'name', 'mata_pelajaran')->get(),
                'classrooms' => collect([
                    (object)['id' => 'R101', 'nama' => 'R101'],
                    (object)['id' => 'R102', 'nama' => 'R102'],
                    (object)['id' => 'R103', 'nama' => 'R103'],
                    (object)['id' => 'Lab Komputer 1', 'nama' => 'Lab Komputer 1'],
                    (object)['id' => 'Lab Komputer 2', 'nama' => 'Lab Komputer 2'],
                    (object)['id' => 'Lab Komputer 3', 'nama' => 'Lab Komputer 3'],
                    (object)['id' => 'Lab Multimedia', 'nama' => 'Lab Multimedia'],
                    (object)['id' => 'Perpustakaan', 'nama' => 'Perpustakaan'],
                ]),
            ];

            return view('schedules.index', compact('schedules', 'dropdownData'));
        } catch (\Exception $e) {
            Log::error('Error loading schedules: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load schedules: ' . $e->getMessage());
        }
    }

    public function create()
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            // Get dropdown data efficiently
            $dropdownData = [
                'classes' => \App\Models\ClassModel::select('id', 'nama_kelas', 'kode_kelas')->get(),
                'subjects' => \App\Models\Subject::select('id', 'nama', 'kode')->get(),
                'teachers' => \App\Models\User::where('role', 'guru')->select('id', 'name', 'mata_pelajaran')->get(),
                'classrooms' => collect([
                    (object)['id' => 'R101', 'nama' => 'R101'],
                    (object)['id' => 'R102', 'nama' => 'R102'],
                    (object)['id' => 'R103', 'nama' => 'R103'],
                    (object)['id' => 'Lab Komputer 1', 'nama' => 'Lab Komputer 1'],
                    (object)['id' => 'Lab Komputer 2', 'nama' => 'Lab Komputer 2'],
                    (object)['id' => 'Lab Komputer 3', 'nama' => 'Lab Komputer 3'],
                    (object)['id' => 'Lab Multimedia', 'nama' => 'Lab Multimedia'],
                    (object)['id' => 'Perpustakaan', 'nama' => 'Perpustakaan'],
                ]),
            ];

            return view('schedules.create', compact('dropdownData'));
        } catch (\Exception $e) {
            return redirect()->route('web-schedules.index')->with('error', 'Failed to load create form: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $request->validate([
                'guru_id' => 'required|integer|exists:teachers,id',
                'mata_pelajaran' => 'required|string|max:255',
                'kelas' => 'required|string|max:100',
                'hari' => 'required|string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
                'jam_mulai' => 'required|date_format:H:i',
                'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
                'ruang' => 'nullable|string|max:100',
            ]);

            $scheduleData = [
                'kelas' => $request->kelas,
                'mata_pelajaran' => $request->mata_pelajaran,
                'guru_id' => $request->guru_id,
                'hari' => $request->hari,
                'jam_mulai' => $request->jam_mulai,
                'jam_selesai' => $request->jam_selesai,
                'ruang' => $request->ruang
            ];

            $schedule = Schedule::create($scheduleData);

            // Redirect to index with success message
            return redirect()->route('web-schedules.index')->with('success', 'Schedule created successfully! New schedule ID: ' . $schedule->id);
        } catch (\Exception $e) {
            return back()->withErrors('Failed to create schedule: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $schedule = Schedule::with(['guru:id,name', 'subject:id,nama'])->findOrFail($id);
            return view('schedules.show', compact('schedule'));
        } catch (\Exception $e) {
            return redirect()->route('web-schedules.index')->with('error', 'Schedule not found.');
        }
    }

    public function edit($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $schedule = Schedule::findOrFail($id);
            $schedule->load(['guru:id,name', 'subject:id,nama']);

            // Get dropdown data efficiently
            $dropdownData = [
                'classes' => \App\Models\ClassModel::select('id', 'nama_kelas', 'kode_kelas')->get(),
                'subjects' => \App\Models\Subject::select('id', 'nama', 'kode')->get(),
                'teachers' => \App\Models\User::where('role', 'guru')->select('id', 'name', 'mata_pelajaran')->get(),
                'classrooms' => collect([
                    (object)['id' => 'R101', 'nama' => 'R101'],
                    (object)['id' => 'R102', 'nama' => 'R102'],
                    (object)['id' => 'R103', 'nama' => 'R103'],
                    (object)['id' => 'Lab Komputer 1', 'nama' => 'Lab Komputer 1'],
                    (object)['id' => 'Lab Komputer 2', 'nama' => 'Lab Komputer 2'],
                    (object)['id' => 'Lab Komputer 3', 'nama' => 'Lab Komputer 3'],
                    (object)['id' => 'Lab Multimedia', 'nama' => 'Lab Multimedia'],
                    (object)['id' => 'Perpustakaan', 'nama' => 'Perpustakaan'],
                ]),
            ];

            return view('schedules.edit', compact('schedule', 'dropdownData'));
        } catch (\Exception $e) {
            return redirect()->route('web-schedules.index')->with('error', 'Schedule not found.');
        }
    }

    public function update(Request $request, $id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $schedule = Schedule::findOrFail($id);

            $validated = $request->validate([
                'guru_id' => 'required|integer|exists:teachers,id',
                'mata_pelajaran' => 'required|string|max:255',
                'kelas' => 'required|string|max:100',
                'hari' => 'required|string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
                'jam_mulai' => 'required|date_format:H:i',
                'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
                'ruang' => 'nullable|string|max:100',
            ]);

            // Update the schedule with validated data
            $updateData = [
                'kelas' => $validated['kelas'],
                'mata_pelajaran' => $validated['mata_pelajaran'],
                'guru_id' => $validated['guru_id'],
                'hari' => $validated['hari'],
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'ruang' => $validated['ruang']
            ];

            $schedule->update($updateData);

            // Clear cache after updating schedule
            $this->scheduleService->clearScheduleCache();

            return redirect()->route('web-schedules.index')->with('success', 'Schedule updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return back()->withErrors('Failed to update schedule: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $schedule = Schedule::findOrFail($id);

            $schedule->delete();

            // Clear cache after deleting schedule
            $this->scheduleService->clearScheduleCache();

            return redirect()->route('web-schedules.index')->with('success', 'Schedule deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to delete schedule: ' . $e->getMessage());
        }
    }

    public function apiIndex(Request $request)
    {
        // API endpoint that provides optimized schedule data
        $filters = [];
        if ($request->has('status') && !empty($request->status)) {
            $filters['status'] = $request->status;
        }
        if ($request->has('day') && !empty($request->day)) {
            $filters['day_of_week'] = $request->day;
        }
        if ($request->has('class_id') && !empty($request->class_id)) {
            $filters['class_id'] = $request->class_id;
        }
        if ($request->has('teacher_id') && !empty($request->teacher_id)) {
            $filters['teacher_id'] = $request->teacher_id;
        }

        $schedules = $this->scheduleService->getCachedSchedules(true, $filters);

        return response()->json([
            'data' => $schedules->items(),
            'pagination' => [
                'current_page' => $schedules->currentPage(),
                'per_page' => $schedules->perPage(),
                'total' => $schedules->total(),
                'last_page' => $schedules->lastPage(),
                'from' => $schedules->firstItem(),
                'to' => $schedules->lastItem(),
            ]
        ]);
    }

    public function statistics()
    {
        $stats = $this->scheduleService->getScheduleStatistics();
        return view('schedules.statistics', compact('stats'));
    }
}
