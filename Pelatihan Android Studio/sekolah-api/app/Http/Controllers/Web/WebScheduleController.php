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
        try {
            $schedules = Schedule::with(['guru:id,nama', 'subject:id,nama'])
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
                'guru:id,nama',
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
                'teachers' => \App\Models\Teacher::select('id', 'nama')->get(),
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
        try {
            // Get dropdown data efficiently
            $dropdownData = [
                'classes' => \App\Models\ClassModel::select('id', 'nama_kelas', 'kode_kelas')->get(),
                'subjects' => \App\Models\Subject::select('id', 'nama', 'kode')->get(),
                'teachers' => \App\Models\Teacher::select('id', 'nama')->get(),
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
        try {
            $schedule = Schedule::with(['guru:id,nama', 'subject:id,nama'])->findOrFail($id);
            return view('schedules.show', compact('schedule'));
        } catch (\Exception $e) {
            return redirect()->route('web-schedules.index')->with('error', 'Schedule not found.');
        }
    }

    public function edit($id)
    {
        try {
            $schedule = Schedule::findOrFail($id);
            $schedule->load(['guru:id,nama', 'subject:id,nama']);

            // Get dropdown data efficiently
            $dropdownData = [
                'classes' => \App\Models\ClassModel::select('id', 'nama_kelas', 'kode_kelas')->get(),
                'subjects' => \App\Models\Subject::select('id', 'nama', 'kode')->get(),
                'teachers' => \App\Models\Teacher::select('id', 'nama')->get(),
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

    public function import()
    {
        return view('schedules.import');
    }

    public function importStore(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB max, removed mime type restriction
                'skip_duplicates' => 'sometimes|boolean',
                'update_existing' => 'sometimes|boolean',
            ]);

            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = $file->getMimeType();

            // Allow CSV files and text files that might be CSV
            if (!in_array($extension, ['csv', 'txt'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only CSV files are supported for schedule import. File extension: ' . $extension . ', MIME type: ' . $mimeType
                ], 400);
            }

            $result = $this->processScheduleCsvImport($file, $request);

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Schedule import validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . json_encode($e->errors()),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Schedule import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processScheduleCsvImport($file, Request $request)
    {
        $path = $file->getRealPath();

        // Read file content and handle potential encoding issues
        $content = file_get_contents($path);
        if (!$content) {
            throw new \Exception('Unable to read file content');
        }

        // Remove BOM if present
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        // Convert to UTF-8 if needed
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Split into lines and parse CSV
        $lines = explode("\n", trim($content));
        $data = [];

        foreach ($lines as $line) {
            // Skip empty lines
            $line = trim($line);
            if (empty($line)) continue;

            // Custom CSV parsing to handle mixed quoted/unquoted fields
            $parsed = $this->parseCsvLine($line);
            if ($parsed !== false) {
                $data[] = $parsed;
            }
        }

        if (empty($data)) {
            throw new \Exception('No data found in CSV file');
        }

        $header = array_shift($data);

        return $this->processScheduleImportData($data, $header, $request);
    }

    private function processScheduleImportData($data, $header, Request $request)
    {
        $processed = 0;
        $skipped = 0;
        $errors = [];
        $skipDuplicates = $request->boolean('skip_duplicates');
        $updateExisting = $request->boolean('update_existing');

        // Normalize header names - be more aggressive with cleaning
        $header = array_map(function ($col) {
            // Remove quotes, extra whitespace, and invisible characters
            $cleaned = trim($col, '"\'');
            $cleaned = preg_replace('/\s+/', ' ', $cleaned); // Normalize whitespace
            $cleaned = strtolower($cleaned);
            return $cleaned;
        }, $header);

        // Required columns mapping
        $requiredColumns = ['hari', 'kelas', 'mata_pelajaran', 'guru_id'];

        // More flexible column matching - check if required columns exist (case-insensitive partial match)
        $foundColumns = [];
        foreach ($requiredColumns as $required) {
            $found = false;
            foreach ($header as $available) {
                // Check if the required column name is contained in the available header
                if (strpos($available, $required) !== false) {
                    $found = true;
                    $foundColumns[$required] = $available;
                    break;
                }
            }
            if (!$found) {
                throw new \Exception("Required column '{$required}' is missing from the file. Available columns: " . implode(', ', $header));
            }
        }

        // Map found columns to their positions for data extraction
        $columnMapping = [];
        foreach ($header as $index => $colName) {
            if (isset($foundColumns['hari']) && strpos($colName, 'hari') !== false) {
                $columnMapping['hari'] = $index;
            } elseif (isset($foundColumns['kelas']) && strpos($colName, 'kelas') !== false) {
                $columnMapping['kelas'] = $index;
            } elseif (isset($foundColumns['mata_pelajaran']) && strpos($colName, 'mata_pelajaran') !== false) {
                $columnMapping['mata_pelajaran'] = $index;
            } elseif (isset($foundColumns['guru_id']) && strpos($colName, 'guru_id') !== false) {
                $columnMapping['guru_id'] = $index;
            } elseif (strpos($colName, 'jam_pelajaran') !== false) {
                $columnMapping['jam_pelajaran'] = $index;
            } elseif (strpos($colName, 'jam_mulai') !== false) {
                $columnMapping['jam_mulai'] = $index;
            } elseif (strpos($colName, 'jam_selesai') !== false) {
                $columnMapping['jam_selesai'] = $index;
            } elseif (strpos($colName, 'ruang') !== false) {
                $columnMapping['ruang'] = $index;
            }
        }

        foreach ($data as $rowIndex => $row) {
            try {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Convert row to associative array using column mapping
                $rowData = [];
                if (isset($columnMapping['hari'])) {
                    $rowData['hari'] = $row[$columnMapping['hari']] ?? null;
                }
                if (isset($columnMapping['kelas'])) {
                    $rowData['kelas'] = $row[$columnMapping['kelas']] ?? null;
                }
                if (isset($columnMapping['mata_pelajaran'])) {
                    $rowData['mata_pelajaran'] = $row[$columnMapping['mata_pelajaran']] ?? null;
                }
                if (isset($columnMapping['guru_id'])) {
                    $rowData['guru_id'] = $row[$columnMapping['guru_id']] ?? null;
                }

                // Handle jam_pelajaran format "07:00 - 08:00" -> parse to jam_mulai and jam_selesai
                if (isset($columnMapping['jam_pelajaran'])) {
                    $jamPelajaran = trim($row[$columnMapping['jam_pelajaran']] ?? '');
                    if (!empty($jamPelajaran) && preg_match('/(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})/', $jamPelajaran, $matches)) {
                        $rowData['jam_mulai'] = $matches[1];
                        $rowData['jam_selesai'] = $matches[2];
                    }
                }

                // Direct jam_mulai/jam_selesai columns (override if present)
                if (isset($columnMapping['jam_mulai']) && !empty($row[$columnMapping['jam_mulai']])) {
                    $rowData['jam_mulai'] = $row[$columnMapping['jam_mulai']];
                }
                if (isset($columnMapping['jam_selesai']) && !empty($row[$columnMapping['jam_selesai']])) {
                    $rowData['jam_selesai'] = $row[$columnMapping['jam_selesai']];
                }
                if (isset($columnMapping['ruang'])) {
                    $rowData['ruang'] = $row[$columnMapping['ruang']] ?? null;
                }

                // Validate required fields
                if (empty(trim($rowData['hari'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Day (hari) is required";
                    continue;
                }

                if (empty(trim($rowData['kelas'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Class (kelas) is required";
                    continue;
                }

                if (empty(trim($rowData['mata_pelajaran'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Subject (mata_pelajaran) is required";
                    continue;
                }

                if (empty(trim($rowData['guru_id'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Teacher ID (guru_id) is required";
                    continue;
                }

                // Validate day
                $validDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                if (!in_array($rowData['hari'], $validDays)) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Invalid day. Must be one of: " . implode(', ', $validDays);
                    continue;
                }

                // Validate teacher exists - support both ID and teacher_code
                $guruIdInput = trim($rowData['guru_id']);
                $teacher = null;

                // First try to find by teacher_code (e.g. TCH002)
                if (!is_numeric($guruIdInput)) {
                    $teacher = \App\Models\Teacher::where('teacher_code', $guruIdInput)->first();
                }

                // If not found by code, try by ID
                if (!$teacher && is_numeric($guruIdInput)) {
                    $teacher = \App\Models\Teacher::find($guruIdInput);
                }

                if (!$teacher) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Teacher with ID/Code '{$guruIdInput}' does not exist";
                    continue;
                }

                // Use the actual teacher ID for database storage
                $actualGuruId = $teacher->id;

                // Check for existing schedule (basic duplicate check)
                $existingSchedule = Schedule::where('hari', $rowData['hari'])
                    ->where('kelas', $rowData['kelas'])
                    ->where('mata_pelajaran', $rowData['mata_pelajaran'])
                    ->where('guru_id', $actualGuruId)
                    ->first();

                if ($existingSchedule) {
                    Log::info('Schedule import - Existing schedule found', [
                        'row' => $rowIndex + 2,
                        'hari' => $rowData['hari'],
                        'kelas' => $rowData['kelas'],
                        'mata_pelajaran' => $rowData['mata_pelajaran'],
                        'guru_id' => $rowData['guru_id'],
                        'skip_duplicates' => $skipDuplicates,
                        'update_existing' => $updateExisting
                    ]);
                    if ($skipDuplicates) {
                        $skipped++;
                        Log::info('Schedule import - Skipping duplicate', ['row' => $rowIndex + 2]);
                        continue;
                    } elseif (!$updateExisting) {
                        $errors[] = "Row " . ($rowIndex + 2) . ": Schedule already exists for this day/class/subject/teacher combination";
                        Log::warning('Schedule import - Duplicate error', ['row' => $rowIndex + 2, 'error' => end($errors)]);
                        continue;
                    }
                }

                // Prepare schedule data
                $scheduleData = [
                    'hari' => trim($rowData['hari']),
                    'kelas' => trim($rowData['kelas']),
                    'mata_pelajaran' => trim($rowData['mata_pelajaran']),
                    'guru_id' => $actualGuruId,
                ];

                // Handle optional time fields
                if (!empty($rowData['jam_mulai'])) {
                    $scheduleData['jam_mulai'] = trim($rowData['jam_mulai']);
                }

                if (!empty($rowData['jam_selesai'])) {
                    $scheduleData['jam_selesai'] = trim($rowData['jam_selesai']);
                }

                if (!empty($rowData['ruang'])) {
                    $scheduleData['ruang'] = trim($rowData['ruang']);
                }

                // Create or update schedule
                Log::info('Schedule import - Creating/updating schedule', [
                    'row' => $rowIndex + 2,
                    'schedule_data' => $scheduleData,
                    'existing' => $existingSchedule ? true : false
                ]);

                if ($existingSchedule && $updateExisting) {
                    $existingSchedule->update($scheduleData);
                    Log::info('Schedule import - Updated existing schedule', ['id' => $existingSchedule->id]);
                } else {
                    $newSchedule = Schedule::create($scheduleData);
                    Log::info('Schedule import - Created new schedule', ['id' => $newSchedule->id]);
                }

                $processed++;
                Log::info('Schedule import - Row processed successfully', ['row' => $rowIndex + 2, 'total_processed' => $processed]);
            } catch (\Exception $e) {
                $errorMsg = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                $errors[] = $errorMsg;
                Log::error('Schedule import - Row processing error', [
                    'row' => $rowIndex + 2,
                    'error' => $e->getMessage(),
                    'row_data' => $rowData
                ]);
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => "Import completed. {$processed} schedules processed, {$skipped} skipped, " . count($errors) . " errors."
        ];
    }

    public function downloadTemplate($format)
    {
        try {
            $format = strtolower($format);

            if ($format !== 'csv') {
                abort(404);
            }

            // Sample data
            $data = [
                ['hari', 'kelas', 'mata_pelajaran', 'guru_id', 'jam_mulai', 'jam_selesai', 'ruang'],
                ['Senin', 'X RPL 1', 'Matematika Dasar', '1', '07:00:00', '08:30:00', 'R101'],
                ['Selasa', 'XI RPL 2', 'Bahasa Indonesia', '2', '08:00:00', '09:30:00', 'Lab Bahasa'],
                ['Rabu', 'XII RPL 1', 'Fisika Lanjutan', '3', '09:00:00', '10:30:00', 'Lab Fisika'],
            ];

            $filename = 'schedules_import_template.csv';

            return $this->downloadCsvTemplate($data, $filename);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to download template: ' . $e->getMessage());
        }
    }

    private function downloadCsvTemplate($data, $filename)
    {
        $csv = '';
        foreach ($data as $row) {
            $csv .= implode(',', array_map(function ($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Parse a CSV line that may have mixed quoted/unquoted fields
     * Uses a hybrid approach: try str_getcsv first, then manual parsing if needed
     */
    private function parseCsvLine($line)
    {
        // First try PHP's built-in str_getcsv
        $parsed = str_getcsv($line, ',', '"', '\\');

        // If it worked and we have multiple fields, use it
        if ($parsed !== false && count($parsed) > 1) {
            return array_map('trim', $parsed);
        }

        // If str_getcsv failed, try manual parsing for mixed quoting
        $fields = [];
        $currentField = '';
        $inQuotes = false;
        $i = 0;
        $length = strlen($line);

        while ($i < $length) {
            $char = $line[$i];

            if ($char === '"') {
                if ($inQuotes) {
                    // End of quoted field
                    $inQuotes = false;
                    $i++;
                } else {
                    // Start of quoted field
                    $inQuotes = true;
                    $i++;
                }
            } elseif ($char === ',' && !$inQuotes) {
                // Field separator outside quotes
                $fields[] = trim($currentField);
                $currentField = '';
                $i++;
            } else {
                $currentField .= $char;
                $i++;
            }
        }

        // Add the last field
        $fields[] = trim($currentField);

        // Filter out empty trailing fields
        $fields = array_filter($fields, function ($field) {
            return $field !== '';
        });

        return array_values($fields);
    }

    public function bulkDelete(Request $request)
    {
        try {
            $request->validate([
                'schedule_ids' => 'required|array',
                'schedule_ids.*' => 'integer|exists:schedules,id'
            ]);

            $scheduleIds = $request->schedule_ids;
            $deletedCount = Schedule::whereIn('id', $scheduleIds)->delete();

            // Clear cache after bulk deleting schedules
            $this->scheduleService->clearScheduleCache();

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} schedule(s)."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkDeleteAll(Request $request)
    {
        try {
            $deletedCount = Schedule::query()->delete();

            // Clear cache after deleting all schedules
            $this->scheduleService->clearScheduleCache();

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted all {$deletedCount} schedule(s)."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete all schedules: ' . $e->getMessage()
            ], 500);
        }
    }
}
