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
                        'guru:id,nama,email'
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
                        'name' => $schedule->guru->nama,
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
                        'guru:id,nama'
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
                        'guru:id,nama'
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

            // DEBUG: Log guru_id validation
            Log::info('Schedule creation attempt', [
                'guru_id' => $validated['guru_id'],
                'teacher_exists' => \App\Models\Teacher::where('id', $validated['guru_id'])->exists(),
                'schedule_data' => $scheduleData
            ]);

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
                        'name' => $schedule->guru->nama
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
                ->limit(100)
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
            Log::info('myClassSchedule called', [
                'user_id' => $request->user() ? $request->user()->id : 'guest',
                'headers' => [
                    'authorization_present' => $request->bearerToken() !== null,
                    'content_type' => $request->header('Content-Type'),
                    'user_agent' => $request->header('User-Agent')
                ],
                'query_params' => $request->query(),
                'start_time' => microtime(true)
            ]);

            $user = $request->user();

            // Validasi role siswa
            if (!$user || $user->role !== 'siswa') {
                Log::info('myClassSchedule access denied', [
                    'user_id' => $user ? $user->id : null,
                    'user_role' => $user ? $user->role : null
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Endpoint ini hanya untuk siswa.'
                ], 403);
            }

            // Validasi siswa sudah punya class_id
            if (!$user->class_id) {
                Log::info('myClassSchedule no class assigned', [
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak terdaftar di kelas manapun. Hubungi admin.'
                ], 404);
            }

            // ULTRA LIGHTWEIGHT: Limit ke 20 item per page max
            $perPage = min($request->query('per_page', 20), 20);
            $cacheKey = "ultra_light_schedule_user_{$user->id}_page_{$request->query('page', 1)}";

            Log::info('myClassSchedule preparing query', [
                'user_id' => $user->id,
                'user_class_id' => $user->class_id,
                'per_page' => $perPage,
                'cache_key' => $cacheKey
            ]);

            $schedules = Cache::remember($cacheKey, 120, function () use ($user, $perPage) {
                Log::info('myClassSchedule cache miss, executing query', [
                    'user_id' => $user->id,
                    'user_class' => $user->class_id
                ]);

                // Get user's class name
                $userClass = $user->class;
                if (!$userClass) {
                    Log::warning('myClassSchedule user has no class', [
                        'user_id' => $user->id
                    ]);
                    return collect([]);
                }

                Log::info('myClassSchedule query params', [
                    'user_class_name' => $userClass->nama_kelas,
                    'per_page_limit' => $perPage
                ]);

                // ULTRA OPTIMIZED: Minimal query dengan raw SQL untuk speed
                $query = DB::table('schedules as s')
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
                    ->limit($perPage);

                Log::info('myClassSchedule query built', [
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings()
                ]);

                $result = $query->get();

                Log::info('myClassSchedule query executed', [
                    'result_count' => $result->count(),
                    'user_id' => $user->id
                ]);

                return $result;
            });

            Log::info('myClassSchedule success', [
                'user_id' => $user->id,
                'result_count' => $schedules->count(),
                'response_data' => $schedules->toArray()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal kelas berhasil dimuat (ultra lightweight).',
                'data' => $schedules,
                'count' => $schedules->count()
            ], 200);
        } catch (\PDOException $e) {
            Log::error('CRITICAL myClassSchedule database error: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user() ? $request->user()->id : 'guest',
                'request_headers' => [
                    'authorization_present' => $request->bearerToken() !== null,
                    'content_type' => $request->header('Content-Type'),
                    'user_agent' => $request->header('User-Agent')
                ],
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server sedang sibuk. Coba lagi dalam beberapa detik.',
                'data' => []
            ], 503);
        } catch (\Exception $e) {
            Log::error('CRITICAL myClassSchedule error: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user() ? $request->user()->id : 'guest',
                'request_headers' => [
                    'authorization_present' => $request->bearerToken() !== null,
                    'content_type' => $request->header('Content-Type'),
                    'user_agent' => $request->header('User-Agent')
                ],
                'request_data' => $request->all()
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
        @set_time_limit(10); // Set reasonable time limit

        try {
            Log::info('myTodaySchedule called', [
                'user_id' => $request->user() ? $request->user()->id : 'guest',
                'headers' => [
                    'authorization_present' => $request->bearerToken() !== null,
                    'content_type' => $request->header('Content-Type'),
                    'user_agent' => $request->header('User-Agent')
                ],
                'start_time' => microtime(true)
            ]);

            $user = $request->user();

            if (!$user) {
                Log::warning('myTodaySchedule: No authenticated user');
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            // Validasi role siswa
            if ($user->role !== 'siswa') {
                Log::warning('myTodaySchedule: Invalid role', [
                    'user_id' => $user->id,
                    'user_role' => $user->role
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint ini hanya untuk siswa'
                ], 403);
            }

            // Validasi siswa sudah punya class_id
            if (!$user->class_id) {
                Log::info('myTodaySchedule: No class assigned', [
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum di-assign ke kelas. Hubungi admin.',
                    'data' => []
                ], 200);
            }

            $today = strtolower(now()->format('l'));

            Log::info('myTodaySchedule preparing query', [
                'user_id' => $user->id,
                'class_id' => $user->class_id,
                'today' => $today
            ]);

            // Get user's class (with detailed logging)
            $userClass = $user->class;
            if (!$userClass) {
                Log::warning('myTodaySchedule: User has no associated class record', [
                    'user_id' => $user->id,
                    'user_class_id' => $user->class_id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum di-assign ke kelas. Hubungi admin.',
                    'data' => []
                ], 200);
            }

            Log::info('myTodaySchedule class found', [
                'user_id' => $user->id,
                'class_id' => $userClass->id,
                'class_name' => $userClass->nama_kelas
            ]);

            // Build query with detailed logging
            $query = Schedule::query()
                ->select(['schedules.*']) // Explicitly select schedules fields to avoid issues with eager loading
                ->with(['guru:id,nama'])
                ->where('kelas', $userClass->nama_kelas)
                ->where('hari', $today)
                ->orderBy('jam_mulai')
                ->limit(50); // Add limit to prevent memory issues

            Log::info('myTodaySchedule query built', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'user_id' => $user->id
            ]);

            // Execute query with timeout protection
            try {
                $schedules = $query->get();

                Log::info('myTodaySchedule query executed', [
                    'result_count' => $schedules->count(),
                    'user_id' => $user->id
                ]);
            } catch (\Exception $e) {
                Log::error('myTodaySchedule query execution failed', [
                    'error' => $e->getMessage(),
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings(),
                    'user_id' => $user->id
                ]);
                $schedules = collect(); // Return empty collection on error
            }

            $responseData = [
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
            ];

            Log::info('myTodaySchedule success response', [
                'user_id' => $user->id,
                'response_data_count' => count($responseData['data']['schedules'])
            ]);

            return response()->json($responseData, 200);
        } catch (\PDOException $e) {
            Log::error('myTodaySchedule database error: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user() ? $request->user()->id : 'guest',
                'request_headers' => [
                    'authorization_present' => $request->bearerToken() !== null,
                    'content_type' => $request->header('Content-Type'),
                    'user_agent' => $request->header('User-Agent')
                ],
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan database saat mengambil jadwal',
                'error' => config('app.debug') ? $e->getMessage() : 'Database error'
            ], 500);
        } catch (\Error $e) {
            // Catch fatal errors like memory exhaustion, recursion, etc.
            Log::error('myTodaySchedule fatal error: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan fatal saat mengambil jadwal',
                'error' => 'Server error'
            ], 500);
        } catch (\Exception $e) {
            Log::error('myTodaySchedule error: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user() ? $request->user()->id : 'guest',
                'request_headers' => [
                    'authorization_present' => $request->bearerToken() !== null,
                    'content_type' => $request->header('Content-Type'),
                    'user_agent' => $request->header('User-Agent')
                ],
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat jadwal hari ini',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Weekly schedule for siswa - ULTRA SIMPLE to prevent connection reset
     * Returns schedule data matching Android ScheduleApi format
     */
    public function myWeeklySchedule(Request $request): JsonResponse
    {
        @set_time_limit(15);

        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'data' => []
                ], 401);
            }

            $classId = $user->class_id;

            if (!$classId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Belum ada kelas yang ditetapkan',
                    'data' => []
                ], 200);
            }

            // Get user's class name - simple query
            $userClass = DB::table('classes')->where('id', $classId)->first();

            if (!$userClass) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kelas tidak ditemukan',
                    'data' => []
                ], 200);
            }

            $className = $userClass->nama_kelas;

            // Simple query without complex joins
            $schedules = DB::table('schedules')
                ->select([
                    'schedules.id',
                    'schedules.hari',
                    'schedules.mata_pelajaran',
                    'schedules.jam_mulai',
                    'schedules.jam_selesai',
                    'schedules.guru_id',
                    'teachers.nama as guru_nama'
                ])
                ->leftJoin('teachers', 'schedules.guru_id', '=', 'teachers.id')
                ->where('schedules.kelas', $className)
                ->orderBy('schedules.hari')
                ->orderBy('schedules.jam_mulai')
                ->limit(50)
                ->get();

            // Transform to match Android ScheduleApi format
            $formattedData = [];
            foreach ($schedules as $item) {
                $formattedData[] = [
                    'id' => (int) $item->id,
                    'class_id' => (int) $classId,
                    'subject_id' => 0,
                    'teacher_id' => (int) ($item->guru_id ?? 0),
                    'day_of_week' => $item->hari ?? '',
                    'period' => 1,
                    'start_time' => $item->jam_mulai ?? '',
                    'end_time' => $item->jam_selesai ?? '',
                    'status' => 'active',
                    'class_name' => $className,
                    'subject_name' => $item->mata_pelajaran ?? '',
                    'teacher_name' => $item->guru_nama ?? ''
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Jadwal mingguan berhasil dimuat',
                'data' => $formattedData
            ], 200);
        } catch (\Exception $e) {
            Log::error('myWeeklySchedule error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat jadwal',
                'data' => []
            ], 500);
        }
    }

    /**
     * Weekly schedule with MANUAL AUTH - bypasses Sanctum middleware bug
     * This is the main endpoint called by Android app
     */
    public function myWeeklyScheduleManualAuth(Request $request)
    {
        try {
            // Manual token authentication
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak ditemukan',
                    'data' => []
                ], 401);
            }

            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid',
                    'data' => []
                ], 401);
            }

            $user = $accessToken->tokenable;

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                    'data' => []
                ], 401);
            }

            $classId = $user->class_id;

            if (!$classId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Belum ada kelas yang ditetapkan',
                    'data' => []
                ], 200);
            }

            // Get user's class name - simple query
            $userClass = DB::table('classes')->where('id', $classId)->first();

            if (!$userClass) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kelas tidak ditemukan',
                    'data' => []
                ], 200);
            }

            $className = $userClass->nama_kelas;

            // Simple query with limit to prevent heavy load
            $schedules = DB::table('schedules')
                ->select([
                    'schedules.id',
                    'schedules.hari',
                    'schedules.mata_pelajaran',
                    'schedules.jam_mulai',
                    'schedules.jam_selesai',
                    'schedules.guru_id',
                    'teachers.nama as guru_nama'
                ])
                ->leftJoin('teachers', 'schedules.guru_id', '=', 'teachers.id')
                ->where('schedules.kelas', $className)
                ->orderBy('schedules.hari')
                ->orderBy('schedules.jam_mulai')
                ->limit(50) // Keep it simple and light
                ->get();

            // Transform to match Android ScheduleApi format
            $formattedData = [];
            foreach ($schedules as $item) {
                // Format jam untuk konsistensi
                $jamMulai = $item->jam_mulai;
                $jamSelesai = $item->jam_selesai;

                // Pastikan format HH:MM:SS
                if ($jamMulai && strlen($jamMulai) == 5) {
                    $jamMulai .= ':00';
                }
                if ($jamSelesai && strlen($jamSelesai) == 5) {
                    $jamSelesai .= ':00';
                }

                $formattedData[] = [
                    'id' => (int) $item->id,
                    'class_id' => (int) $classId,
                    'subject_id' => 0,
                    'teacher_id' => (int) ($item->guru_id ?? 0),
                    'day_of_week' => $item->hari ?? '',
                    'period' => 1,
                    'start_time' => $jamMulai ?? '',
                    'end_time' => $jamSelesai ?? '',
                    'status' => 'active',
                    'class_name' => $className,
                    'subject_name' => $item->mata_pelajaran ?? '',
                    'teacher_name' => $item->guru_nama ?? ''
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Jadwal mingguan berhasil dimuat (' . count($formattedData) . ' jadwal)',
                'data' => $formattedData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'data' => []
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
            Log::info('siswaJadwalHariIni called', [
                'user_id' => $request->user() ? $request->user()->id : 'guest',
                'headers' => [
                    'authorization_present' => $request->bearerToken() !== null,
                    'content_type' => $request->header('Content-Type'),
                    'user_agent' => $request->header('User-Agent')
                ],
                'start_time' => microtime(true)
            ]);

            $user = $request->user();

            if (!$user || $user->role !== 'siswa' || !$user->class_id) {
                Log::info('siswaJadwalHariIni access denied', [
                    'user_id' => $user ? $user->id : null,
                    'user_role' => $user ? $user->role : null,
                    'user_class_id' => $user ? $user->class_id : null
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak atau tidak ada kelas.',
                    'data' => []
                ], 403);
            }

            $today = strtolower(now()->format('l'));
            $cacheKey = "siswa_today_{$user->id}_{$today}";

            Log::info('siswaJadwalHariIni preparing query', [
                'user_id' => $user->id,
                'user_class_id' => $user->class_id,
                'today' => $today,
                'cache_key' => $cacheKey
            ]);

            $jadwal = Cache::remember($cacheKey, 60, function () use ($user, $today) {
                Log::info('siswaJadwalHariIni cache miss, executing query', [
                    'user_id' => $user->id,
                    'user_class' => $user->class_id
                ]);

                // Safe way to get user class to prevent crashes
                try {
                    $userClass = $user->class;
                } catch (\Exception $e) {
                    Log::warning('siswaJadwalHariIni unable to load user class', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                    return collect([]);
                }

                if (!$userClass) {
                    Log::info('siswaJadwalHariIni user has no class', [
                        'user_id' => $user->id
                    ]);
                    return collect([]);
                }

                Log::info('siswaJadwalHariIni query params', [
                    'user_class_name' => $userClass->nama_kelas,
                    'today' => $today
                ]);

                // Get today's date for leave check
                $todayDate = now()->toDateString();

                $query = DB::table('schedules as s')
                    ->select([
                        DB::raw("CONCAT(s.jam_mulai, '-', s.jam_selesai) as waktu"),
                        's.mata_pelajaran as mapel',
                        't.nama as guru',
                        's.guru_id',
                        // Check if teacher is on leave today
                        DB::raw("(SELECT l.id FROM leaves l WHERE l.teacher_id = s.guru_id AND l.status = 'approved' AND l.start_date <= '{$todayDate}' AND l.end_date >= '{$todayDate}' LIMIT 1) as leave_id"),
                        // Get substitute teacher name if exists
                        DB::raw("(SELECT st.nama FROM leaves l JOIN teachers st ON l.substitute_teacher_id = st.id WHERE l.teacher_id = s.guru_id AND l.status = 'approved' AND l.start_date <= '{$todayDate}' AND l.end_date >= '{$todayDate}' AND l.substitute_teacher_id IS NOT NULL LIMIT 1) as guru_pengganti"),
                        // Get leave reason
                        DB::raw("(SELECT l.reason FROM leaves l WHERE l.teacher_id = s.guru_id AND l.status = 'approved' AND l.start_date <= '{$todayDate}' AND l.end_date >= '{$todayDate}' LIMIT 1) as alasan_izin")
                    ])
                    ->leftJoin('teachers as t', 's.guru_id', '=', 't.id')
                    ->where('s.kelas', $userClass->nama_kelas)
                    ->where('s.hari', $today)
                    ->orderBy('s.jam_mulai')
                    ->limit(10);

                Log::info('siswaJadwalHariIni query built', [
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings()
                ]);

                try {
                    $result = $query->get();

                    // Transform result to add teacher_on_leave flag
                    $result = $result->map(function ($item) {
                        return [
                            'waktu' => $item->waktu,
                            'mapel' => $item->mapel,
                            'guru' => $item->guru,
                            'guru_izin' => !empty($item->leave_id),
                            'guru_pengganti' => $item->guru_pengganti,
                            'alasan_izin' => $item->alasan_izin,
                        ];
                    });

                    Log::info('siswaJadwalHariIni query executed', [
                        'result_count' => $result->count(),
                        'user_id' => $user->id
                    ]);

                    return $result;
                } catch (\Exception $e) {
                    Log::error('siswaJadwalHariIni query execution failed', [
                        'error' => $e->getMessage(),
                        'sql' => $query->toSql(),
                        'bindings' => $query->getBindings(),
                        'user_id' => $user->id
                    ]);
                    return collect([]);
                }
            });

            Log::info('siswaJadwalHariIni success', [
                'user_id' => $user->id,
                'result_count' => $jadwal->count(),
                'response_data' => $jadwal->toArray()
            ]);

            return response()->json([
                'success' => true,
                'hari' => ucfirst($today),
                'data' => $jadwal,
                'jumlah' => $jadwal->count()
            ]);
        } catch (\PDOException $e) {
            Log::error('CRITICAL siswaJadwalHariIni database error: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user() ? $request->user()->id : 'guest',
                'request_headers' => [
                    'authorization_present' => $request->bearerToken() !== null,
                    'content_type' => $request->header('Content-Type'),
                    'user_agent' => $request->header('User-Agent')
                ],
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server sibuk, coba lagi.',
                'data' => []
            ], 503);
        } catch (\Error $e) {
            // Catch fatal errors like memory exhaustion, recursion, etc.
            Log::error('CRITICAL siswaJadwalHariIni fatal error: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user() ? $request->user()->id : 'guest'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server sibuk, coba lagi.',
                'data' => []
            ], 503);
        } catch (\Exception $e) {
            Log::error('CRITICAL siswaJadwalHariIni error: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user() ? $request->user()->id : 'guest',
                'request_headers' => [
                    'authorization_present' => $request->bearerToken() !== null,
                    'content_type' => $request->header('Content-Type'),
                    'user_agent' => $request->header('User-Agent')
                ],
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server sibuk, coba lagi.',
                'data' => []
            ], 503);
        }
    }

    /**
     * SIMPLE TEST VERSION - No complex queries to prevent crashes
     */
    public function myWeeklyScheduleSimple(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            if (!$user->class_id) {
                return response()->json(['success' => false, 'message' => 'No class assigned', 'data' => []], 200);
            }

            // Simple hardcoded test data
            $testData = [
                'Senin' => [
                    ['subject' => 'Matematika', 'teacher' => 'Guru A', 'time' => '07:00-08:30'],
                    ['subject' => 'Bahasa Indonesia', 'teacher' => 'Guru B', 'time' => '08:45-10:15']
                ],
                'Selasa' => [
                    ['subject' => 'Fisika', 'teacher' => 'Guru C', 'time' => '07:00-08:30']
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Test schedule loaded successfully',
                'data' => [
                    'class' => [
                        'id' => $user->class_id,
                        'nama_kelas' => 'Test Class',
                        'kode_kelas' => 'TEST'
                    ],
                    'total_schedules' => 3,
                    'grouped_by_day' => $testData
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test endpoint error: ' . $e->getMessage()
            ], 500);
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
                        's.mata_pelajaran as mapel',
                        DB::raw("CONCAT(s.jam_mulai, '-', s.jam_selesai) as periode")
                    ])
                    ->join('schedules as s', 'k.schedule_id', '=', 's.id')
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

    /**
     * Weekly schedule WITH teacher attendance status
     * Shows whether teacher is hadir/telat/tidak_hadir/diganti with substitute teacher name
     */
    public function myWeeklyScheduleWithAttendance(Request $request): JsonResponse
    {
        @set_time_limit(10);

        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'today' => '',
                    'data' => []
                ], 401);
            }

            $classId = $user->class_id;

            if (!$classId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Belum ada kelas yang ditetapkan',
                    'today' => '',
                    'data' => []
                ], 200);
            }

            // Get user's class name
            $userClass = DB::table('classes')->where('id', $classId)->first();

            if (!$userClass) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kelas tidak ditemukan',
                    'today' => '',
                    'data' => []
                ], 200);
            }

            $className = $userClass->nama_kelas;
            $today = now()->format('Y-m-d');

            // Get Indonesian day name without locale dependency
            $dayMap = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];
            $todayDayName = $dayMap[now()->format('l')] ?? now()->format('l');

            Log::info('myWeeklyScheduleWithAttendance', [
                'user_id' => $user->id,
                'class_id' => $classId,
                'class_name' => $className,
                'today' => $todayDayName
            ]);

            // Get schedules with teacher info - SIMPLE query
            $schedules = DB::table('schedules')
                ->select([
                    'schedules.id',
                    'schedules.hari',
                    'schedules.mata_pelajaran',
                    'schedules.jam_mulai',
                    'schedules.jam_selesai',
                    'schedules.guru_id',
                    'teachers.nama as guru_nama'
                ])
                ->leftJoin('teachers', 'schedules.guru_id', '=', 'teachers.id')
                ->where('schedules.kelas', $className)
                ->orderByRaw("FIELD(schedules.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")
                ->orderBy('schedules.jam_mulai')
                ->limit(50)
                ->get();

            // Get today's attendance records from teacher_attendances table
            $scheduleIds = $schedules->pluck('id')->toArray();
            $todayAttendance = collect();

            if (!empty($scheduleIds)) {
                $todayAttendance = DB::table('teacher_attendances')
                    ->select(['schedule_id', 'status', 'keterangan', 'guru_id', 'guru_asli_id'])
                    ->where('tanggal', $today)
                    ->whereIn('schedule_id', $scheduleIds)
                    ->get()
                    ->keyBy('schedule_id');
            }

            // Get teacher IDs from schedules for leave checking
            $teacherIds = $schedules->pluck('guru_id')->filter()->unique()->toArray();

            // Get approved leaves for today - teacher is on leave if today falls within start_date and end_date
            $teachersOnLeave = collect();
            if (!empty($teacherIds)) {
                $teachersOnLeave = DB::table('leaves')
                    ->select(['teacher_id', 'reason', 'custom_reason', 'substitute_teacher_id'])
                    ->where('status', 'approved')
                    ->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today)
                    ->whereIn('teacher_id', $teacherIds)
                    ->get()
                    ->keyBy('teacher_id');
            }

            // Get substitute teacher names (guru_id is substitute when status is 'diganti')
            $substituteTeacherIds = $todayAttendance
                ->filter(fn($a) => $a->status === 'diganti' && $a->guru_id)
                ->pluck('guru_id')
                ->unique()
                ->toArray();

            // Also get substitute teacher IDs from leaves
            $leaveSubstituteIds = $teachersOnLeave
                ->filter(fn($l) => $l->substitute_teacher_id)
                ->pluck('substitute_teacher_id')
                ->unique()
                ->toArray();

            $allSubstituteIds = array_unique(array_merge($substituteTeacherIds, $leaveSubstituteIds));

            $substituteTeachers = [];
            if (!empty($allSubstituteIds)) {
                $substituteTeachers = DB::table('teachers')
                    ->whereIn('id', $allSubstituteIds)
                    ->pluck('nama', 'id')
                    ->toArray();
            }

            // Transform data
            $formattedData = [];
            $period = 1;
            $lastDay = null;

            foreach ($schedules as $item) {
                if ($lastDay !== $item->hari) {
                    $period = 1;
                    $lastDay = $item->hari;
                }

                $isToday = strtolower($item->hari ?? '') === strtolower($todayDayName);
                $attendance = $todayAttendance->get($item->id);
                $teacherLeave = $teachersOnLeave->get($item->guru_id);

                $attendanceStatus = null;
                $attendanceCatatan = null;
                $substituteTeacherName = null;

                // Priority: 1. Check if teacher is on approved leave, 2. Check attendance record
                if ($isToday && $teacherLeave) {
                    // Teacher is on approved leave today
                    $attendanceStatus = 'izin';
                    // Get leave reason
                    $leaveReason = $teacherLeave->reason === 'lainnya'
                        ? ($teacherLeave->custom_reason ?? 'Izin')
                        : match ($teacherLeave->reason) {
                            'sakit' => 'Sakit',
                            'cuti_tahunan' => 'Cuti Tahunan',
                            'urusan_keluarga' => 'Urusan Keluarga',
                            'acara_resmi' => 'Acara Resmi',
                            default => ucfirst($teacherLeave->reason ?? 'Izin')
                        };
                    $attendanceCatatan = "Guru sedang $leaveReason";

                    // Get substitute teacher if assigned
                    if ($teacherLeave->substitute_teacher_id) {
                        $substituteTeacherName = $substituteTeachers[$teacherLeave->substitute_teacher_id] ?? null;
                    }
                } elseif ($isToday && $attendance) {
                    $attendanceStatus = $attendance->status;
                    $attendanceCatatan = $attendance->keterangan;

                    if ($attendance->status === 'diganti' && $attendance->guru_id) {
                        $substituteTeacherName = $substituteTeachers[$attendance->guru_id] ?? null;
                    }
                }

                $formattedData[] = [
                    'id' => (int) $item->id,
                    'class_id' => (int) $classId,
                    'subject_id' => 0,
                    'teacher_id' => (int) ($item->guru_id ?? 0),
                    'day_of_week' => $item->hari ?? '',
                    'period' => $period,
                    'start_time' => $item->jam_mulai ?? '',
                    'end_time' => $item->jam_selesai ?? '',
                    'status' => 'active',
                    'class_name' => $className,
                    'subject_name' => $item->mata_pelajaran ?? '',
                    'teacher_name' => $item->guru_nama ?? '',
                    'is_today' => $isToday,
                    'attendance_status' => $attendanceStatus,
                    'attendance_catatan' => $attendanceCatatan,
                    'substitute_teacher_name' => $substituteTeacherName
                ];

                $period++;
            }

            Log::info('myWeeklyScheduleWithAttendance response', [
                'count' => count($formattedData),
                'today' => $todayDayName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal mingguan dengan status kehadiran berhasil dimuat',
                'today' => $todayDayName,
                'date' => $today,
                'data' => $formattedData
            ], 200);
        } catch (\Exception $e) {
            Log::error('myWeeklyScheduleWithAttendance error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat jadwal',
                'today' => '',
                'data' => []
            ], 500);
        }
    }

    /**
     * Weekly schedule WITH teacher attendance status - MANUAL AUTH VERSION
     * Bypasses Sanctum middleware to prevent server crashes
     * Shows whether teacher is hadir/telat/tidak_hadir/diganti with substitute teacher name
     */
    public function myWeeklyScheduleWithAttendanceManualAuth(Request $request): JsonResponse
    {
        @set_time_limit(30); // Increased from 10 to prevent timeout
        @ini_set('memory_limit', '128M'); // Ensure enough memory

        try {
            // Manual token validation (bypass Sanctum middleware)
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak ditemukan',
                    'today' => '',
                    'data' => []
                ], 401);
            }

            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid',
                    'today' => '',
                    'data' => []
                ], 401);
            }

            $user = $accessToken->tokenable;
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                    'today' => '',
                    'data' => []
                ], 401);
            }

            $classId = $user->class_id;

            if (!$classId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Belum ada kelas yang ditetapkan',
                    'today' => '',
                    'data' => []
                ], 200);
            }

            // Get user's class name
            $userClass = DB::table('classes')->where('id', $classId)->first();

            if (!$userClass) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kelas tidak ditemukan',
                    'today' => '',
                    'data' => []
                ], 200);
            }

            $className = $userClass->nama_kelas;
            $today = now()->format('Y-m-d');

            // Get Indonesian day name without locale dependency
            $dayMap = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];
            $todayDayName = $dayMap[now()->format('l')] ?? now()->format('l');

            // Cache key for this class's schedules (cache for 2 minutes)
            $cacheKey = "weekly_schedule_v2_{$classId}_{$today}";

            // Try to get from cache first
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                Log::info('myWeeklyScheduleWithAttendanceManualAuth - returning cached data', [
                    'class_id' => $classId,
                    'cache_key' => $cacheKey
                ]);
                return response()->json($cachedData, 200);
            }

            // Get schedules with teacher info - SIMPLE query
            $schedules = DB::table('schedules')
                ->select([
                    'schedules.id',
                    'schedules.hari',
                    'schedules.mata_pelajaran',
                    'schedules.jam_mulai',
                    'schedules.jam_selesai',
                    'schedules.guru_id',
                    'teachers.nama as guru_nama'
                ])
                ->leftJoin('teachers', 'schedules.guru_id', '=', 'teachers.id')
                ->where('schedules.kelas', $className)
                ->orderByRaw("FIELD(schedules.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")
                ->orderBy('schedules.jam_mulai')
                ->limit(50)
                ->get();

            // Get today's attendance records from teacher_attendances table
            $scheduleIds = $schedules->pluck('id')->toArray();
            $todayAttendance = collect();

            if (!empty($scheduleIds)) {
                $todayAttendance = DB::table('teacher_attendances')
                    ->select(['schedule_id', 'status', 'keterangan', 'guru_id', 'guru_asli_id'])
                    ->where('tanggal', $today)
                    ->whereIn('schedule_id', $scheduleIds)
                    ->get()
                    ->keyBy('schedule_id');
            }

            // Get teacher IDs from schedules for leave checking
            $teacherIds = $schedules->pluck('guru_id')->filter()->unique()->toArray();

            // Get approved leaves for today - teacher is on leave if today falls within start_date and end_date
            $teachersOnLeave = collect();
            if (!empty($teacherIds)) {
                $teachersOnLeave = DB::table('leaves')
                    ->select(['teacher_id', 'reason', 'custom_reason', 'substitute_teacher_id'])
                    ->where('status', 'approved')
                    ->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today)
                    ->whereIn('teacher_id', $teacherIds)
                    ->get()
                    ->keyBy('teacher_id');
            }

            // Get substitute teacher names (guru_id is substitute when status is 'diganti')
            $substituteTeacherIds = $todayAttendance
                ->filter(fn($a) => $a->status === 'diganti' && $a->guru_id)
                ->pluck('guru_id')
                ->unique()
                ->toArray();

            // Also get substitute teacher IDs from leaves
            $leaveSubstituteIds = $teachersOnLeave
                ->filter(fn($l) => $l->substitute_teacher_id)
                ->pluck('substitute_teacher_id')
                ->unique()
                ->toArray();

            $allSubstituteIds = array_unique(array_merge($substituteTeacherIds, $leaveSubstituteIds));

            $substituteTeachers = [];
            if (!empty($allSubstituteIds)) {
                $substituteTeachers = DB::table('teachers')
                    ->whereIn('id', $allSubstituteIds)
                    ->pluck('nama', 'id')
                    ->toArray();
            }

            // Transform data
            $formattedData = [];
            $period = 1;
            $lastDay = null;

            foreach ($schedules as $item) {
                if ($lastDay !== $item->hari) {
                    $period = 1;
                    $lastDay = $item->hari;
                }

                $isToday = strtolower($item->hari ?? '') === strtolower($todayDayName);
                $attendance = $todayAttendance->get($item->id);
                $teacherLeave = $teachersOnLeave->get($item->guru_id);

                $attendanceStatus = null;
                $attendanceCatatan = null;
                $substituteTeacherName = null;

                // Priority: 1. Check if teacher is on approved leave, 2. Check attendance record
                if ($isToday && $teacherLeave) {
                    // Teacher is on approved leave today
                    $attendanceStatus = 'izin';
                    // Get leave reason
                    $leaveReason = $teacherLeave->reason === 'lainnya'
                        ? ($teacherLeave->custom_reason ?? 'Izin')
                        : match ($teacherLeave->reason) {
                            'sakit' => 'Sakit',
                            'cuti_tahunan' => 'Cuti Tahunan',
                            'urusan_keluarga' => 'Urusan Keluarga',
                            'acara_resmi' => 'Acara Resmi',
                            default => ucfirst($teacherLeave->reason ?? 'Izin')
                        };
                    $attendanceCatatan = "Guru sedang $leaveReason";

                    // Get substitute teacher if assigned
                    if ($teacherLeave->substitute_teacher_id) {
                        $substituteTeacherName = $substituteTeachers[$teacherLeave->substitute_teacher_id] ?? null;
                    }
                } elseif ($isToday && $attendance) {
                    $attendanceStatus = $attendance->status;
                    $attendanceCatatan = $attendance->keterangan;

                    if ($attendance->status === 'diganti' && $attendance->guru_id) {
                        $substituteTeacherName = $substituteTeachers[$attendance->guru_id] ?? null;
                    }
                }

                $formattedData[] = [
                    'id' => (int) $item->id,
                    'class_id' => (int) $classId,
                    'subject_id' => 0,
                    'teacher_id' => (int) ($item->guru_id ?? 0),
                    'day_of_week' => $item->hari ?? '',
                    'period' => $period,
                    'start_time' => $item->jam_mulai ?? '',
                    'end_time' => $item->jam_selesai ?? '',
                    'status' => 'active',
                    'class_name' => $className,
                    'subject_name' => $item->mata_pelajaran ?? '',
                    'teacher_name' => $item->guru_nama ?? '',
                    'is_today' => $isToday,
                    'attendance_status' => $attendanceStatus,
                    'attendance_catatan' => $attendanceCatatan,
                    'substitute_teacher_name' => $substituteTeacherName
                ];

                $period++;
            }

            // Prepare response data
            $responseData = [
                'success' => true,
                'message' => 'Jadwal mingguan dengan status kehadiran berhasil dimuat',
                'today' => $todayDayName,
                'date' => $today,
                'data' => $formattedData
            ];

            // Cache the result for 2 minutes
            Cache::put($cacheKey, $responseData, 120);

            return response()->json($responseData, 200, [
                'Content-Type' => 'application/json; charset=utf-8'
            ]);
        } catch (\Exception $e) {
            Log::error('myWeeklyScheduleWithAttendanceManualAuth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat jadwal',
                'today' => '',
                'data' => []
            ], 500);
        }
    }
}
