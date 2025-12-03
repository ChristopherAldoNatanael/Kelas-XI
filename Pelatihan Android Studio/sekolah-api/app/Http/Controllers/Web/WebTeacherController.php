<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebTeacherController extends Controller
{
    public function index()
    {
        try {
            $teachers = Teacher::paginate(20);
            return view('teachers.index', compact('teachers'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Failed to load teachers: ' . $e->getMessage());
        }
    }

    public function create()
    {
        try {
            Log::info('WebTeacherController@create: Loading teacher creation form');
            return view('teachers.create');
        } catch (\Exception $e) {
            Log::error('WebTeacherController@create: Exception - ' . $e->getMessage());
            return redirect()->route('web-teachers.index')->with('error', 'Failed to load create form: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            Log::info('Teacher creation attempt', [
                'data' => $request->all(),
            ]);

            $validated = $request->validate([
                'nama' => 'required|string|max:255',
                'nip' => 'required|string|max:50|unique:teachers',
                'teacher_code' => 'required|string|max:50|unique:teachers',
                'position' => 'required|string|max:100',
                'department' => 'required|string|max:100',
                'expertise' => 'nullable|string|max:255',
                'certification' => 'nullable|string|max:255',
                'join_date' => 'required|date',
                'status' => 'required|in:active,inactive,retired'
            ]);

            Log::info('Teacher validation passed', ['validated' => $validated]);

            $teacher = Teacher::create($validated);

            Log::info('Teacher created successfully', [
                'teacher_id' => $teacher->id,
                'nama' => $teacher->nama,
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Teacher created successfully: ' . $teacher->nama,
                    'teacher' => $teacher
                ]);
            }

            return redirect()->route('web-teachers.index')->with('success', 'Teacher created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Teacher validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors()
                ], 422);
            }

            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Teacher creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create teacher: ' . $e->getMessage()
                ], 500);
            }

            return back()->withErrors('Failed to create teacher: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        try {
            $teacher = Teacher::findOrFail($id);
            return view('teachers.show', compact('teacher'));
        } catch (\Exception $e) {
            return redirect()->route('web-teachers.index')->with('error', 'Teacher not found.');
        }
    }

    public function edit($id)
    {
        try {
            $teacher = Teacher::findOrFail($id);
            return view('teachers.edit', compact('teacher'));
        } catch (\Exception $e) {
            return redirect()->route('web-teachers.index')->with('error', 'Teacher not found.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $teacher = Teacher::findOrFail($id);

            // Handle status-only updates (PATCH requests)
            if ($request->isMethod('patch') && $request->has('status')) {
                $validated = $request->validate([
                    'status' => 'required|in:active,inactive,retired',
                ]);

                $teacher->update($validated);

                $statusMessage = match ($validated['status']) {
                    'active' => 'Teacher has been activated successfully.',
                    'inactive' => 'Teacher has been set to inactive.',
                    'retired' => 'Teacher has been marked as retired.',
                };

                return redirect()->back()->with('success', $statusMessage);
            }

            // Handle full updates (PUT requests)
            $validated = $request->validate([
                'nama' => 'required|string|max:255',
                'nip' => 'required|string|max:50|unique:teachers,nip,' . $id,
                'teacher_code' => 'required|string|max:50|unique:teachers,teacher_code,' . $id,
                'position' => 'required|string|max:100',
                'department' => 'required|string|max:100',
                'expertise' => 'nullable|string|max:255',
                'certification' => 'nullable|string|max:255',
                'join_date' => 'required|date',
                'status' => 'required|in:active,inactive,retired',
            ]);

            $teacher->update($validated);

            return redirect()->route('web-teachers.index')->with('success', 'Teacher updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to update teacher: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $teacher = Teacher::findOrFail($id);
            $teacher->delete();

            return redirect()->route('web-teachers.index')->with('success', 'Teacher deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to delete teacher: ' . $e->getMessage());
        }
    }

    public function import()
    {
        return view('teachers.import');
    }

    public function importStore(Request $request)
    {
        try {
            // More permissive file validation for CSV
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
                'skip_duplicates' => 'sometimes|boolean',
                'update_existing' => 'sometimes|boolean',
            ]);

            $file = $request->file('file');

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file uploaded'
                ], 400);
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = $file->getMimeType();
            $originalName = $file->getClientOriginalName();

            Log::info('Teacher CSV Import attempt', [
                'filename' => $originalName,
                'extension' => $extension,
                'mime_type' => $mimeType,
                'size' => $file->getSize()
            ]);

            // Check if it's a CSV file - be very permissive
            $allowedExtensions = ['csv', 'txt'];
            $allowedMimes = [
                'text/csv',
                'text/plain',
                'application/csv',
                'application/vnd.ms-excel',
                'text/comma-separated-values',
                'application/octet-stream' // Some systems report CSV as this
            ];

            if (!in_array($extension, $allowedExtensions) && !in_array($mimeType, $allowedMimes)) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid file type. Expected CSV but got: {$extension} ({$mimeType})"
                ], 400);
            }

            $result = $this->processTeacherCsvImport($file, $request);

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                $errorMessages[] = $field . ': ' . implode(', ', $messages);
            }

            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode('; ', $errorMessages),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Teacher import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processTeacherCsvImport($file, Request $request)
    {
        $path = $file->getRealPath();

        // Use fgetcsv for better handling of quoted fields
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new \Exception('Could not open file for reading');
        }

        $data = [];
        $header = null;
        $lineNumber = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $lineNumber++;
            if ($lineNumber === 1) {
                $header = $row;
                continue;
            }
            $data[] = $row;
        }

        fclose($handle);

        if (empty($header)) {
            throw new \Exception('CSV file is empty or has no header');
        }

        Log::info('CSV Import - Parsed data', [
            'header_count' => count($header),
            'data_rows' => count($data),
            'header' => $header
        ]);

        return $this->processTeacherImportData($data, $header, $request);
    }

    private function processTeacherImportData($data, $header, Request $request)
    {
        $processed = 0;
        $skipped = 0;
        $errors = [];
        $skipDuplicates = $request->boolean('skip_duplicates');
        $updateExisting = $request->boolean('update_existing');

        // Normalize header names
        $header = array_map(function ($col) {
            return strtolower(trim($col));
        }, $header);

        // Log header for debugging
        Log::info('CSV Import - Header columns:', $header);

        // Required columns mapping
        $requiredColumns = ['nama', 'nip', 'teacher_code', 'position', 'department', 'join_date'];
        $optionalColumns = ['expertise', 'certification', 'status'];

        // Columns to ignore (from database exports)
        $ignoreColumns = ['id', 'created_at', 'updated_at', 'deleted_at'];

        // Validate required columns exist
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $header)) {
                throw new \Exception("Required column '{$col}' is missing from the file");
            }
        }

        foreach ($data as $rowIndex => $row) {
            try {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Convert row to associative array
                $rowData = [];
                foreach ($header as $colIndex => $colName) {
                    $rowData[$colName] = $row[$colIndex] ?? null;
                }

                // Validate required fields
                if (empty(trim($rowData['nama'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Name is required";
                    continue;
                }

                if (empty(trim($rowData['nip'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": NIP is required";
                    continue;
                }

                if (empty(trim($rowData['teacher_code'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Teacher code is required";
                    continue;
                }

                if (empty(trim($rowData['position'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Position is required";
                    continue;
                }

                if (empty(trim($rowData['department'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Department is required";
                    continue;
                }

                if (empty(trim($rowData['join_date'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Join date is required";
                    continue;
                }

                // Validate join_date format
                if (!strtotime($rowData['join_date'])) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Invalid join date format (use YYYY-MM-DD)";
                    continue;
                }

                // Check for existing teacher (including soft deleted)
                $existingTeacher = Teacher::withTrashed()
                    ->where('nip', $rowData['nip'])
                    ->orWhere('teacher_code', $rowData['teacher_code'])
                    ->first();

                if ($existingTeacher) {
                    if ($existingTeacher->trashed()) {
                        // Teacher is soft deleted
                        if ($updateExisting) {
                            // Restore and update the teacher
                            $existingTeacher->restore();
                            Log::info('Restoring soft deleted teacher', ['nip' => $rowData['nip']]);
                        } elseif ($skipDuplicates) {
                            $skipped++;
                            continue;
                        } else {
                            $errors[] = "Row " . ($rowIndex + 2) . ": Teacher with NIP '{$rowData['nip']}' or code '{$rowData['teacher_code']}' exists but is deleted. Use 'Update existing teachers' to restore.";
                            continue;
                        }
                    } elseif ($skipDuplicates) {
                        $skipped++;
                        continue;
                    } elseif (!$updateExisting) {
                        $errors[] = "Row " . ($rowIndex + 2) . ": Teacher with NIP '{$rowData['nip']}' or code '{$rowData['teacher_code']}' already exists";
                        continue;
                    }
                }

                // Prepare teacher data
                $teacherData = [
                    'nama' => trim($rowData['nama']),
                    'nip' => trim($rowData['nip']),
                    'teacher_code' => trim($rowData['teacher_code']),
                    'position' => trim($rowData['position']),
                    'department' => trim($rowData['department']),
                    'join_date' => $rowData['join_date'],
                ];

                // Handle optional fields
                if (!empty($rowData['expertise'])) {
                    $teacherData['expertise'] = trim($rowData['expertise']);
                }

                if (!empty($rowData['certification'])) {
                    $teacherData['certification'] = trim($rowData['certification']);
                }

                if (!empty($rowData['status']) && in_array($rowData['status'], ['active', 'inactive', 'retired'])) {
                    $teacherData['status'] = $rowData['status'];
                } else {
                    $teacherData['status'] = 'active'; // Default status
                }

                // Create or update teacher
                if ($existingTeacher && $updateExisting) {
                    $existingTeacher->update($teacherData);
                } else {
                    Teacher::create($teacherData);
                }

                $processed++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => "Import completed. {$processed} teachers processed, {$skipped} skipped, " . count($errors) . " errors."
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
                ['nama', 'nip', 'teacher_code', 'position', 'department', 'join_date', 'expertise', 'certification', 'status'],
                ['John Doe', '123456789', 'T001', 'Guru', 'Matematika', '2023-01-15', 'Matematika', 'S.Pd', 'active'],
                ['Jane Smith', '987654321', 'T002', 'Guru Senior', 'Bahasa Indonesia', '2022-08-20', 'Bahasa Indonesia', 'M.Pd', 'active'],
                ['Bob Johnson', '456789123', 'T003', 'Guru', 'Fisika', '2023-03-10', 'Fisika', 'S.Pd', 'active'],
            ];

            $filename = 'teachers_import_template.csv';

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

    public function restore($id)
    {
        try {
            $teacher = Teacher::findOrFail($id);
            // Note: Restore functionality not available without SoftDeletes

            return redirect()->route('web-teachers.index')->with('success', 'Teacher restored successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to restore teacher: ' . $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        try {
            $teacher = Teacher::findOrFail($id);
            $teacher->delete();

            return redirect()->route('web-teachers.index')->with('success', 'Teacher permanently deleted.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to permanently delete teacher: ' . $e->getMessage());
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            $request->validate([
                'teacher_ids' => 'required|array',
                'teacher_ids.*' => 'required|integer|exists:teachers,id'
            ]);

            $teacherIds = $request->teacher_ids;
            $deletedCount = 0;

            foreach ($teacherIds as $teacherId) {
                $teacher = Teacher::find($teacherId);
                if ($teacher) {
                    $teacher->delete();
                    $deletedCount++;
                }
            }

            $message = $deletedCount > 0
                ? "{$deletedCount} teacher(s) deleted successfully."
                : "No teachers were deleted.";

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deletedCount
                ]);
            }

            return redirect()->route('web-teachers.index')->with('success', $message);
        } catch (\Exception $e) {
            $errorMessage = 'Failed to delete teachers: ' . $e->getMessage();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 500);
            }

            return back()->withErrors($errorMessage);
        }
    }

    public function bulkDeleteAll(Request $request)
    {
        try {
            // Get all teachers except administrators (if any admin role exists)
            $teachersToDelete = Teacher::whereDoesntHave('user', function ($query) {
                $query->where('role', 'admin');
            })->get();

            $deletedCount = 0;
            foreach ($teachersToDelete as $teacher) {
                $teacher->delete();
                $deletedCount++;
            }

            $message = $deletedCount > 0
                ? "All {$deletedCount} teachers deleted successfully."
                : "No teachers were deleted.";

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deletedCount
                ]);
            }

            return redirect()->route('web-teachers.index')->with('success', $message);
        } catch (\Exception $e) {
            $errorMessage = 'Failed to delete all teachers: ' . $e->getMessage();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 500);
            }

            return back()->withErrors($errorMessage);
        }
    }
}
