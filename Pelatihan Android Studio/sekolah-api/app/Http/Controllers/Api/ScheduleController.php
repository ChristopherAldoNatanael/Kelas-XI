<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     * Optimized with caching and efficient queries
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->user() ? $request->user()->id : 'guest';
            $page = $request->query('page', 1);
            $perPage = $request->query('per_page', 30); // Default 30, bisa di-override client

            // Cache key sekarang menyertakan info halaman
            $cacheKey = "schedules_v3_{$userId}_" . md5($request->fullUrl() . "_page_{$page}_perpage_{$perPage}");

            // Cache 60 detik
            $paginatedSchedules = Cache::remember($cacheKey, 60, function () use ($request, $perPage) {
                $query = Schedule::query()
                    ->select([
                        'id',
                        'hari',
                        'kelas',
                        'mata_pelajaran',
                        'guru_id',
                        'jam_mulai',
                        'jam_selesai',
                        'ruang'
                    ])
                    ->with([
                        'guru:id,name,email'
                    ]);

                if ($request->has('day')) {
                    $query->where('hari', $request->day);
                }

                if ($request->has('kelas')) {
                    $query->where('kelas', $request->kelas);
                }

                if ($request->has('guru_id')) {
                    $query->where('guru_id', $request->guru_id);
                }

                if ($request->has('hari')) {
                    $query->where('hari', $request->hari);
                }

                if ($request->has('mata_pelajaran')) {
                    $query->where('mata_pelajaran', 'like', '%' . $request->mata_pelajaran . '%');
                }

                // KRUSIAL: Menggunakan paginate() bukan get() atau limit().
                // Ini akan secara otomatis menangani limit dan offset.
                return $query->orderBy('hari')
                    ->orderBy('jam_mulai')
                    ->paginate($perPage);
            });

            // Transformasi data setelah diambil dari cache/query
            $formattedSchedules = $paginatedSchedules->getCollection()->map(function ($schedule) {
                // Asumsi ada method toApiArray() di model Schedule, jika tidak ada, buat seperti ini:
                return [
                    'id' => $schedule->id,
                    'hari' => $schedule->hari,
                    'kelas' => $schedule->kelas,
                    'mata_pelajaran' => $schedule->mata_pelajaran,
                    'jam_mulai' => $schedule->jam_mulai,
                    'jam_selesai' => $schedule->jam_selesai,
                    'ruang' => $schedule->ruang,
                    'guru' => $schedule->guru ? [
                        'id' => $schedule->guru->id,
                        'name' => $schedule->guru->name,
                        'email' => $schedule->guru->email,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data jadwal berhasil diambil',
                'data' => $formattedSchedules,
                // Menggunakan struktur response pagination standar dari Laravel
                'pagination' => [
                    'current_page' => $paginatedSchedules->currentPage(),
                    'per_page' => $paginatedSchedules->perPage(),
                    'total' => $paginatedSchedules->total(),
                    'last_page' => $paginatedSchedules->lastPage(),
                    'from' => $paginatedSchedules->firstItem(),
                    'to' => $paginatedSchedules->lastItem()
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Schedule index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data jadwal',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Super lightweight endpoint khusus untuk Android dengan pagination
     * CRITICAL FIX: Ultra-optimized dengan timeout protection untuk prevent server crash
     */
    public function indexMobile(Request $request): JsonResponse
    {
        // CRITICAL FIX: Simplified and optimized for stability.
        @set_time_limit(15); // Set a reasonable time limit

        try {
            $classId = $request->query('class_id');
            $perPage = min((int)$request->query('per_page', 50), 100); // Sensible limit

            // class_id is mandatory to prevent server overload
            if (!$classId) {
                return response()->json([
                    'success' => false,
                    'message' => 'class_id is required.',
                    'data' => []
                ], 400);
            }

            $cacheKey = "mobile_schedules_class_{$classId}_page_{$request->query('page', 1)}";

            // Cache the paginated result for 5 minutes
            $schedules = Cache::remember($cacheKey, 300, function () use ($classId, $perPage) {
                return Schedule::query()
                    ->with([
                        'subject:id,nama',
                        'guru:id,nama',
                        'classroom:id,nama'
                    ])
                    ->where('kelas', $classId)
                    ->orderBy('hari')
                    ->orderBy('jam_mulai')
                    ->paginate($perPage);
            });

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil dimuat',
                'data' => $schedules->items(),
                'pagination' => [
                    'current_page' => $schedules->currentPage(),
                    'per_page' => $schedules->perPage(),
                    'total' => $schedules->total(),
                    'last_page' => $schedules->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('IndexMobile FATAL error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            // Return a server error response without crashing
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat memuat jadwal.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Lightweight today's schedule with optional class filter (for mobile)
     */
    public function todayMobile(Request $request): JsonResponse
    {
        try {
            $classId = $request->query('class_id');
            $today = strtolower(now()->format('l'));

            // OPTIMIZED: Cache jadwal hari ini per class (refresh setiap 2 menit)
            $cacheKey = "today_schedule_{$today}_{$classId}";

            $data = Cache::remember($cacheKey, 120, function () use ($today, $classId) {
                $query = Schedule::query()
                    ->select([
                        'id',
                        'hari',
                        'kelas',
                        'mata_pelajaran',
                        'guru_id',
                        'jam_mulai',
                        'jam_selesai',
                        'ruang'
                    ])
                    ->with([
                        'subject:id,nama',
                        'guru:id,name'
                    ])
                    ->where('hari', $today);

                if ($classId) {
                    $query->where('kelas', $classId);
                }

                return $query->orderBy('jam_mulai')
                    ->limit(20) // Safety limit
                    ->get();
            });

            return response()->json([
                'success' => true,
                'message' => 'Jadwal hari ini berhasil diambil',
                'data' => $data,
                'count' => $data->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat jadwal hari ini',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * Enhanced for Android compatibility
     */
    public function store(Request $request): JsonResponse
    {
        try {

            $validated = $request->validate([
                'mata_pelajaran' => 'required|string|max:255',
                'guru_id' => 'required|exists:teachers,id',
                'kelas' => 'required|string|max:100',
                'hari' => 'required|string|max:20',
                'jam_mulai' => 'required|date_format:H:i',
                'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
                'ruang' => 'nullable|string|max:100'
            ]);

            $scheduleData = [
                'hari' => $validated['hari'],
                'kelas' => $validated['kelas'],
                'mata_pelajaran' => $validated['mata_pelajaran'],
                'guru_id' => $validated['guru_id'],
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'ruang' => $validated['ruang']
            ];

            // Check if schedule already exists
            $existingSchedule = Schedule::where('kelas', $validated['kelas'])
                ->where('hari', $validated['hari'])
                ->where('jam_mulai', $validated['jam_mulai'])
                ->first();

            if ($existingSchedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal sudah ada untuk periode ini',
                    'details' => 'Jadwal untuk kelas ' . $validated['kelas'] . ' pada hari ' . $validated['hari'] .
                        ' jam ' . $validated['jam_mulai'] . ' sudah terdaftar.',
                    'existing_schedule' => [
                        'id' => $existingSchedule->id,
                        'subject' => $existingSchedule->mata_pelajaran,
                        'teacher' => $existingSchedule->guru->name ?? 'Unknown',
                        'time' => $existingSchedule->jam_mulai . ' - ' . $existingSchedule->jam_selesai
                    ]
                ], 409);
            }

            $schedule = Schedule::create($scheduleData);

            // Load relationships for response
            $schedule->load(['guru', 'subject']);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil ditambahkan ke database MySQL',
                'data' => [
                    'id' => $schedule->id,
                    'hari' => $schedule->hari,
                    'kelas' => $schedule->kelas,
                    'mata_pelajaran' => $schedule->mata_pelajaran,
                    'guru_name' => $schedule->guru->name ?? 'Unknown',
                    'jam_mulai' => $schedule->jam_mulai,
                    'jam_selesai' => $schedule->jam_selesai,
                    'ruang' => $schedule->ruang,
                    'created_at' => $schedule->created_at
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database integrity constraint violations
            if (str_contains($e->getMessage(), 'unique_schedule')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal sudah ada untuk periode ini',
                    'details' => 'Tidak dapat menambahkan jadwal karena sudah ada jadwal dengan kombinasi kelas, hari, periode, tahun akademik, dan semester yang sama.',
                    'error_code' => 'DUPLICATE_SCHEDULE'
                ], 409);
            }

            // Handle other database errors
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan database saat menyimpan jadwal',
                'error' => config('app.debug') ? $e->getMessage() : 'Database error'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan jadwal: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $schedule = Schedule::with(['guru', 'subject'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data jadwal berhasil diambil',
                'data' => [
                    'id' => $schedule->id,
                    'hari' => $schedule->hari,
                    'kelas' => $schedule->kelas,
                    'mata_pelajaran' => $schedule->mata_pelajaran,
                    'guru' => $schedule->guru ? [
                        'id' => $schedule->guru->id,
                        'nama' => $schedule->guru->name
                    ] : null,
                    'jam_mulai' => $schedule->jam_mulai,
                    'jam_selesai' => $schedule->jam_selesai,
                    'ruang' => $schedule->ruang
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $schedule = Schedule::findOrFail($id);

            $validated = $request->validate([
                'hari' => 'sometimes|required|string|max:20',
                'kelas' => 'sometimes|required|string|max:100',
                'mata_pelajaran' => 'sometimes|required|string|max:255',
                'guru_id' => 'sometimes|required|exists:teachers,id',
                'jam_mulai' => 'sometimes|required|date_format:H:i',
                'jam_selesai' => 'sometimes|required|date_format:H:i|after:jam_mulai',
                'ruang' => 'nullable|string|max:100'
            ]);

            $schedule->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil diperbarui',
                'data' => [
                    'id' => $schedule->id,
                    'hari' => $schedule->hari,
                    'kelas' => $schedule->kelas,
                    'mata_pelajaran' => $schedule->mata_pelajaran,
                    'guru_id' => $schedule->guru_id,
                    'jam_mulai' => $schedule->jam_mulai,
                    'jam_selesai' => $schedule->jam_selesai,
                    'ruang' => $schedule->ruang
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui jadwal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $schedule = Schedule::findOrFail($id);
            $schedule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus jadwal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear schedule-related cache
     */
    private function clearScheduleCache(?int $classId = null): void
    {
        try {
            // Clear general schedule list cache
            Cache::forget('schedules_v2_guest');
            // Note: For authenticated users, the fullUrl() will be different, so this might not be fully effective.
            // A more robust solution would involve cache tags or iterating through known user IDs.

            if ($classId) {
                // Clear caches specific to a class
                // This requires knowing the user IDs associated with the class, which is complex.
                // For now, we'll clear general class-related caches and rely on cache expiry for user-specific ones.

                // Clear mobile schedules for this class
                // This pattern needs to be more specific if page numbers are involved.
                // For simplicity, we assume a few common pages or rely on expiry.
                Cache::forget("mobile_schedules_class_{$classId}_page_1"); // Clear first page
                // ... potentially clear other pages if known

                // Clear today's schedule for this class
                $today = strtolower(now()->format('l'));
                Cache::forget("today_schedule_{$today}_{$classId}");

                // Clear weekly schedule for this class (from OptimizedController)
                Cache::forget("weekly_schedule_class_{$classId}");

                // Clear myClassSchedule and myWeeklySchedule for all users in this class
                // This is tricky without knowing all user IDs.
                // A pragmatic approach is to let these user-specific caches expire (300-600 seconds).
                // Or, if using Redis/Memcached, use cache tags.
            }

            // Clear cache tags only if using a driver that supports tags
            $cacheDriver = config('cache.default');
            if (in_array($cacheDriver, ['redis', 'memcached'])) { // 'array' driver does not persist tags across requests
                Cache::tags(['schedules', 'academic', 'timetable'])->flush();
                if ($classId) {
                    Cache::tags(["class_{$classId}_schedules"])->flush();
                }
            }
            Log::info('Schedule cache cleared', ['class_id' => $classId]);
        } catch (\Exception $e) {
            // Log cache clearing error but don't fail the request
            Log::warning('Failed to clear schedule cache: ' . $e->getMessage());
        }
    }

    /**
     * Get schedule for current user (student)
     */
    public function mySchedule(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // CRITICAL FIX: Add limit to prevent memory exhaustion
            $query = Schedule::with(['class:id,nama_kelas,kode_kelas', 'subject:id,nama,kode', 'guru:id,nama'])
                ->select(['id', 'hari', 'kelas', 'mata_pelajaran', 'guru_id', 'jam_mulai', 'jam_selesai', 'ruang']);

            if ($user && $user->class_id) {
                // Assuming class_id maps to kelas field or we need to get the class name
                $userClass = $user->class;
                if ($userClass) {
                    $query->where('kelas', $userClass->nama_kelas);
                }
            }

            // Menggunakan paginate untuk keamanan dan konsistensi
            $schedules = $query->orderBy('hari')->orderBy('jam_mulai')->paginate(50);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal pribadi berhasil diambil',
                'data' => $schedules->items(),
                'pagination' => [
                    'current_page' => $schedules->currentPage(),
                    'per_page' => $schedules->perPage(),
                    'total' => $schedules->total(),
                    'last_page' => $schedules->lastPage(),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('MySchedule error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil jadwal pribadi',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get schedule for today
     */
    public function todaySchedule(Request $request): JsonResponse
    {
        try {
            $today = strtolower(now()->format('l')); // Get current day name

            // CRITICAL FIX: Add limit and proper eager loading
            $schedules = Schedule::select([
                'id',
                'hari',
                'kelas',
                'mata_pelajaran',
                'guru_id',
                'jam_mulai',
                'jam_selesai',
                'ruang'
            ])
                ->with([
                    'guru:id,nama'
                ])
                ->where('hari', $today)
                ->orderBy('jam_mulai') // Tambahkan order by
                ->paginate(50); // Ganti limit() dengan paginate()

            return response()->json([
                'success' => true,
                'message' => 'Jadwal hari ini berhasil diambil',
                'data' => $schedules->items(),
                'pagination' => [
                    'current_page' => $schedules->currentPage(),
                    'per_page' => $schedules->perPage(),
                    'total' => $schedules->total(),
                    'last_page' => $schedules->lastPage(),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('TodaySchedule error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil jadwal hari ini',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get teachers by subject expertise
     */
    public function getTeachersBySubject(Request $request): JsonResponse
    {
        try {
            $subjectId = $request->query('subject_id');

            if (!$subjectId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subject ID is required'
                ], 400);
            }

            // Get subject to find related expertise keywords
            $subject = \App\Models\Subject::find($subjectId);
            if (!$subject) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subject not found'
                ], 404);
            }

            // Find teachers who teach this subject
            $teachers = \App\Models\Teacher::with(['user:id,nama'])
                ->whereHas('schedules', function ($query) use ($subject) {
                    $query->where('mata_pelajaran', $subject->nama);
                })
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data guru berdasarkan mata pelajaran berhasil diambil',
                'data' => $teachers->values()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data guru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ULTRA LIGHTWEIGHT - Get my class schedule (for authenticated siswa)
     * CRITICAL FIX: Dengan timeout protection dan ultra optimized
     * Endpoint: GET /api/siswa/my-schedule
     */
    public function myClassSchedule(Request $request): JsonResponse
    {
        // CRITICAL: Set time limit to prevent server crash
        @set_time_limit(10);

        try {
            $user = $request->user();

            // Validasi role siswa
            if (!$user || $user->role !== 'siswa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Endpoint ini hanya untuk siswa.'
                ], 403);
            }

            // Validasi siswa sudah punya class_id
            if (!$user->class_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak terdaftar di kelas manapun. Hubungi admin.'
                ], 404);
            }

            // ULTRA LIGHTWEIGHT: Limit ke 20 item per page max
            $perPage = min($request->query('per_page', 20), 20);
            $cacheKey = "ultra_light_schedule_user_{$user->id}_page_{$request->query('page', 1)}";

            $schedules = Cache::remember($cacheKey, 120, function () use ($user, $perPage) {
                // Get user's class name
                $userClass = $user->class;
                if (!$userClass) {
                    return collect([]);
                }

                // ULTRA OPTIMIZED: Minimal query dengan raw SQL untuk speed
                return DB::table('schedules as s')
                    ->select([
                        's.id',
                        's.hari',
                        's.jam_mulai',
                        's.jam_selesai',
                        's.mata_pelajaran as subject_name',
                        't.nama as teacher_name',
                        's.ruang as classroom_name'
                    ])
                    ->leftJoin('teachers as t', 's.guru_id', '=', 't.id')
                    ->where('s.kelas', $userClass->nama_kelas)
                    ->orderBy('s.hari')
                    ->orderBy('s.jam_mulai')
                    ->limit($perPage)
                    ->get();
            });

            return response()->json([
                'success' => true,
                'message' => 'Jadwal kelas berhasil dimuat (ultra lightweight).',
                'data' => $schedules,
                'count' => $schedules->count()
            ], 200);
        } catch (\Exception $e) {
            Log::error('CRITICAL myClassSchedule error: ' . $e->getMessage(), [
                'user_id' => $request->user() ? $request->user()->id : 'guest',
                'class_id' => $request->user() ? $request->user()->class_id : 'none'
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Server sedang sibuk. Coba lagi dalam beberapa detik.',
                'data' => []
            ], 503);
        }
    }

    /**
     * Get today's schedule for my class (authenticated siswa)
     * Endpoint: GET /api/siswa/today-schedule
     */
    public function myTodaySchedule(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validasi role siswa
            if ($user->role !== 'siswa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint ini hanya untuk siswa'
                ], 403);
            }

            // Validasi siswa sudah punya class_id
            if (!$user->class_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum di-assign ke kelas. Hubungi admin.',
                    'data' => []
                ], 200);
            }

            $today = strtolower(now()->format('l'));
            $user->load('class');

            Log::info('MyTodaySchedule called', [
                'user_id' => $user->id,
                'class_id' => $user->class_id,
                'today' => $today
            ]);

            // Get user's class
            $userClass = $user->class;
            if (!$userClass) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum di-assign ke kelas. Hubungi admin.',
                    'data' => []
                ], 200);
            }

            // Ambil jadwal hari ini untuk kelas siswa
            $schedules = Schedule::query()
                ->with(['guru:id,nama'])
                ->where('kelas', $userClass->nama_kelas)
                ->where('hari', $today)
                ->orderBy('jam_mulai')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal hari ini berhasil diambil',
                'data' => [
                    'class' => [
                        'id' => $userClass->id,
                        'name' => $userClass->nama_kelas,
                        'code' => $userClass->kode_kelas
                    ],
                    'day' => ucfirst($today),
                    'schedules' => $schedules->map(function ($schedule) {
                        return [
                            'id' => $schedule->id,
                            'mata_pelajaran' => $schedule->mata_pelajaran,
                            'guru' => $schedule->guru ? $schedule->guru->nama : 'Unknown',
                            'jam_mulai' => $schedule->jam_mulai,
                            'jam_selesai' => $schedule->jam_selesai,
                            'ruang' => $schedule->ruang
                        ];
                    })
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('MyTodaySchedule error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat jadwal hari ini',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get weekly schedule for student's class (auto-detect from user's class_id)
     * Endpoint khusus siswa - WAJIB sudah login & punya class_id
     */
    public function myWeeklySchedule(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            if (!$user->class_id) {
                return response()->json(['success' => false, 'message' => 'Anda belum di-assign ke kelas. Hubungi admin.', 'data' => []], 200);
            }

            // OPTIMIZED: Cache the result for 10 minutes per user
            $cacheKey = "weekly_schedule_user_{$user->id}_class_{$user->class_id}";

            $data = Cache::remember($cacheKey, 600, function () use ($user) {
                // Get user's class
                $userClass = $user->class;
                if (!$userClass) {
                    return [
                        'class' => null,
                        'total_schedules' => 0,
                        'schedules' => collect([]),
                    ];
                }

                // OPTIMIZED: Eager load with specific columns to prevent N+1 problem
                $schedules = Schedule::query()
                    ->with(['guru:id,nama'])
                    ->where('kelas', $userClass->nama_kelas)
                    ->orderBy('hari')
                    ->orderBy('jam_mulai')
                    ->limit(100) // CRITICAL: Add safety limit to prevent memory crash
                    ->get();

                return [
                    'class' => [
                        'id' => $userClass->id,
                        'nama_kelas' => $userClass->nama_kelas,
                        'kode_kelas' => $userClass->kode_kelas
                    ],
                    'total_schedules' => $schedules->count(),
                    'schedules' => $schedules,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Jadwal seminggu berhasil diambil dari cache',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            Log::error('MyWeeklySchedule error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat jadwal seminggu',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * SUPER LIGHTWEIGHT untuk Android App - Jadwal Hari Ini Siswa
     * CRITICAL: Ultra optimized untuk prevent server crash
     */
    public function siswaJadwalHariIni(Request $request): JsonResponse
    {
        @set_time_limit(5); // Very short timeout

        try {
            $user = $request->user();

            if (!$user || $user->role !== 'siswa' || !$user->class_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak atau tidak ada kelas.',
                    'data' => []
                ], 403);
            }

            $today = strtolower(now()->format('l'));
            $cacheKey = "siswa_today_{$user->id}_{$today}";

            $jadwal = Cache::remember($cacheKey, 60, function () use ($user, $today) {
                $userClass = $user->class;
                if (!$userClass) {
                    return collect([]);
                }

                return DB::table('schedules as s')
                    ->select([
                        DB::raw("CONCAT(s.jam_mulai, '-', s.jam_selesai) as waktu"),
                        's.mata_pelajaran as mapel',
                        't.nama as guru'
                    ])
                    ->leftJoin('teachers as t', 's.guru_id', '=', 't.id')
                    ->where('s.kelas', $userClass->nama_kelas)
                    ->where('s.hari', $today)
                    ->orderBy('s.jam_mulai')
                    ->limit(10)
                    ->get();
            });

            return response()->json([
                'success' => true,
                'hari' => ucfirst($today),
                'data' => $jadwal,
                'jumlah' => $jadwal->count()
            ]);
        } catch (\Exception $e) {
            Log::error('CRITICAL siswaJadwalHariIni error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server sibuk, coba lagi.',
                'data' => []
            ], 503);
        }
    }

    /**
     * SUPER LIGHTWEIGHT untuk Android App - Riwayat Kehadiran Siswa (paginated)
     * CRITICAL: Ultra optimized untuk prevent server crash
     */
    public function siswaRiwayatKehadiran(Request $request): JsonResponse
    {
        @set_time_limit(8); // Short timeout

        try {
            $user = $request->user();

            if (!$user || $user->role !== 'siswa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak.',
                    'data' => []
                ], 403);
            }

            $page = max(1, (int)$request->query('page', 1));
            $limit = min(10, (int)$request->query('limit', 10)); // Max 10 items
            $offset = ($page - 1) * $limit;

            $cacheKey = "siswa_riwayat_{$user->id}_p{$page}_l{$limit}";

            $result = Cache::remember($cacheKey, 300, function () use ($user, $limit, $offset) {
                $data = DB::table('kehadiran as k')
                    ->select([
                        'k.tanggal',
                        'k.guru_hadir',
                        'sub.name as mapel',
                        DB::raw("CONCAT(s.jam_mulai, '-', s.jam_selesai) as periode")
                    ])
                    ->join('schedules as s', 'k.schedule_id', '=', 's.id')
                    ->leftJoin('subjects as sub', 's.subject_id', '=', 'sub.id')
                    ->where('k.submitted_by', $user->id)
                    ->orderBy('k.tanggal', 'DESC')
                    ->limit($limit)
                    ->offset($offset)
                    ->get();

                $total = DB::table('kehadiran as k')
                    ->join('schedules as s', 'k.schedule_id', '=', 's.id')
                    ->where('k.submitted_by', $user->id)
                    ->count();

                return compact('data', 'total');
            });

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => [
                    'current_page' => $page,
                    'total' => $result['total'],
                    'has_more' => ($page * $limit) < $result['total']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('CRITICAL siswaRiwayatKehadiran error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server sibuk, coba lagi.',
                'data' => []
            ], 503);
        }
    }
}
