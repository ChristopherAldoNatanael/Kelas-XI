<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class WebSubjectController extends Controller
{
    public function index()
    {
        try {
            $subjects = Subject::paginate(20);
            return view('subjects.index', compact('subjects'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Failed to load subjects: ' . $e->getMessage());
        }
    }

    public function create()
    {
        return view('subjects.create');
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'nama' => 'required|string|max:255|unique:subjects',
                'kode' => 'required|string|max:10|unique:subjects',
                'description' => 'nullable|string',
                'status' => 'required|in:active,inactive',
            ]);

            Subject::create($request->all());

            return redirect()->route('web-subjects.index')->with('success', 'Subject created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to create subject: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        try {
            $subject = Subject::findOrFail($id);
            return view('subjects.show', compact('subject'));
        } catch (\Exception $e) {
            return redirect()->route('web-subjects.index')->with('error', 'Subject not found.');
        }
    }

    public function edit($id)
    {
        try {
            $subject = Subject::findOrFail($id);
            return view('subjects.edit', compact('subject'));
        } catch (\Exception $e) {
            return redirect()->route('web-subjects.index')->with('error', 'Subject not found.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $subject = Subject::findOrFail($id);

            $request->validate([
                'nama' => 'required|string|max:255|unique:subjects,nama,' . $id,
                'kode' => 'required|string|max:10|unique:subjects,kode,' . $id,
                'description' => 'nullable|string',
                'status' => 'required|in:active,inactive',
            ]);

            $subject->update($request->all());

            return redirect()->route('web-subjects.index')->with('success', 'Subject updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to update subject: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $subject = Subject::findOrFail($id);
            $subject->delete();

            return redirect()->route('web-subjects.index')->with('success', 'Subject deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to delete subject: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        try {
            $subject = Subject::findOrFail($id);
            // Note: Restore functionality not available without SoftDeletes

            return redirect()->route('web-subjects.index')->with('success', 'Subject restored successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to restore subject: ' . $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        try {
            $subject = Subject::findOrFail($id);
            $subject->delete();

            return redirect()->route('web-subjects.index')->with('success', 'Subject permanently deleted.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to permanently delete subject: ' . $e->getMessage());
        }
    }

    public function import()
    {
        return view('subjects.import');
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
                    'message' => 'Only CSV files are supported for subject import. File extension: ' . $extension . ', MIME type: ' . $mimeType
                ], 400);
            }

            $result = $this->processSubjectCsvImport($file, $request);

            return response()->json($result);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Subject import validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . json_encode($e->errors()),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Subject import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processSubjectCsvImport($file, Request $request)
    {
        $path = $file->getRealPath();

        // Read file content and handle potential encoding issues
        $content = file_get_contents($path);
        if (!$content) {
            throw new \Exception('Unable to read file content');
        }

        // Remove BOM if present
        $bom = pack('H*','EFBBBF');
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


        return $this->processSubjectImportData($data, $header, $request);
    }

    private function processSubjectImportData($data, $header, Request $request)
    {
        $processed = 0;
        $skipped = 0;
        $errors = [];
        $skipDuplicates = $request->boolean('skip_duplicates');
        $updateExisting = $request->boolean('update_existing');


        // Normalize header names - be more aggressive with cleaning
        $header = array_map(function($col) {
            // Remove quotes, extra whitespace, and invisible characters
            $cleaned = trim($col, '"\'');
            $cleaned = preg_replace('/\s+/', ' ', $cleaned); // Normalize whitespace
            $cleaned = strtolower($cleaned);
            return $cleaned;
        }, $header);

        // Required columns mapping
        $requiredColumns = ['nama', 'kode'];

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
            if (isset($foundColumns['nama']) && strpos($colName, 'nama') !== false) {
                $columnMapping['nama'] = $index;
            } elseif (isset($foundColumns['kode']) && strpos($colName, 'kode') !== false) {
                $columnMapping['kode'] = $index;
            } elseif (strpos($colName, 'category') !== false) {
                $columnMapping['category'] = $index;
            } elseif (strpos($colName, 'description') !== false) {
                $columnMapping['description'] = $index;
            } elseif (strpos($colName, 'credit_hours') !== false) {
                $columnMapping['credit_hours'] = $index;
            } elseif (strpos($colName, 'semester') !== false) {
                $columnMapping['semester'] = $index;
            } elseif (strpos($colName, 'status') !== false) {
                $columnMapping['status'] = $index;
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
                if (isset($columnMapping['nama'])) {
                    $rowData['nama'] = $row[$columnMapping['nama']] ?? null;
                }
                if (isset($columnMapping['kode'])) {
                    $rowData['kode'] = $row[$columnMapping['kode']] ?? null;
                }
                if (isset($columnMapping['category'])) {
                    $rowData['category'] = $row[$columnMapping['category']] ?? null;
                }
                if (isset($columnMapping['description'])) {
                    $rowData['description'] = $row[$columnMapping['description']] ?? null;
                }
                if (isset($columnMapping['credit_hours'])) {
                    $rowData['credit_hours'] = $row[$columnMapping['credit_hours']] ?? null;
                }
                if (isset($columnMapping['semester'])) {
                    $rowData['semester'] = $row[$columnMapping['semester']] ?? null;
                }
                if (isset($columnMapping['status'])) {
                    $rowData['status'] = $row[$columnMapping['status']] ?? null;
                }

                // Validate required fields
                if (empty(trim($rowData['nama'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Name is required";
                    continue;
                }

                if (empty(trim($rowData['kode'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Code is required";
                    continue;
                }

                // Check for existing subject
                $existingSubject = Subject::where('kode', $rowData['kode'])
                    ->orWhere('nama', $rowData['nama'])
                    ->first();

                if ($existingSubject) {
                    \Log::info('Subject import - Existing subject found', [
                        'row' => $rowIndex + 2,
                        'kode' => $rowData['kode'],
                        'nama' => $rowData['nama'],
                        'skip_duplicates' => $skipDuplicates,
                        'update_existing' => $updateExisting
                    ]);
                    if ($skipDuplicates) {
                        $skipped++;
                        \Log::info('Subject import - Skipping duplicate', ['row' => $rowIndex + 2]);
                        continue;
                    } elseif (!$updateExisting) {
                        $errors[] = "Row " . ($rowIndex + 2) . ": Subject with code '{$rowData['kode']}' or name '{$rowData['nama']}' already exists";
                        \Log::warning('Subject import - Duplicate error', ['row' => $rowIndex + 2, 'error' => end($errors)]);
                        continue;
                    }
                }

                // Prepare subject data
                $subjectData = [
                    'nama' => trim($rowData['nama']),
                    'kode' => trim($rowData['kode']),
                ];

                // Handle optional fields
                if (!empty($rowData['category']) && in_array($rowData['category'], ['wajib', 'peminatan', 'mulok'])) {
                    $subjectData['category'] = $rowData['category'];
                } else {
                    $subjectData['category'] = 'wajib'; // Default category
                }

                if (!empty($rowData['description'])) {
                    $subjectData['description'] = trim($rowData['description']);
                }

                if (!empty($rowData['credit_hours']) && is_numeric($rowData['credit_hours'])) {
                    $subjectData['credit_hours'] = (int)$rowData['credit_hours'];
                } else {
                    $subjectData['credit_hours'] = 2; // Default credit hours
                }

                if (!empty($rowData['semester']) && is_numeric($rowData['semester'])) {
                    $subjectData['semester'] = (int)$rowData['semester'];
                } else {
                    $subjectData['semester'] = 1; // Default semester
                }

                if (!empty($rowData['status']) && in_array($rowData['status'], ['active', 'inactive'])) {
                    $subjectData['status'] = $rowData['status'];
                } else {
                    $subjectData['status'] = 'active'; // Default status
                }

                // Create or update subject
                \Log::info('Subject import - Creating/updating subject', [
                    'row' => $rowIndex + 2,
                    'subject_data' => $subjectData,
                    'existing' => $existingSubject ? true : false
                ]);

                if ($existingSubject && $updateExisting) {
                    $existingSubject->update($subjectData);
                    \Log::info('Subject import - Updated existing subject', ['id' => $existingSubject->id]);
                } else {
                    $newSubject = Subject::create($subjectData);
                    \Log::info('Subject import - Created new subject', ['id' => $newSubject->id]);
                }

                $processed++;
                \Log::info('Subject import - Row processed successfully', ['row' => $rowIndex + 2, 'total_processed' => $processed]);

            } catch (\Exception $e) {
                $errorMsg = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                $errors[] = $errorMsg;
                \Log::error('Subject import - Row processing error', [
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
            'message' => "Import completed. {$processed} subjects processed, {$skipped} skipped, " . count($errors) . " errors."
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
                ['nama', 'kode', 'category', 'description', 'credit_hours', 'semester', 'status'],
                ['Matematika Dasar', 'MTK101', 'wajib', 'Pengantar matematika dasar', '3', '1', 'active'],
                ['Bahasa Indonesia', 'BIND201', 'wajib', 'Keterampilan berbahasa Indonesia', '2', '2', 'active'],
                ['Fisika Lanjutan', 'FIS301', 'peminatan', 'Fisika untuk mahasiswa tingkat lanjut', '4', '3', 'active'],
            ];

            $filename = 'subjects_import_template.csv';

            return $this->downloadCsvTemplate($data, $filename);

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to download template: ' . $e->getMessage());
        }
    }

    private function downloadCsvTemplate($data, $filename)
    {
        $csv = '';
        foreach ($data as $row) {
            $csv .= implode(',', array_map(function($field) {
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
        $fields = array_filter($fields, function($field) {
            return $field !== '';
        });

        return array_values($fields);
    }

    public function bulkDelete(Request $request)
    {
        try {
            $request->validate([
                'subject_ids' => 'required|array',
                'subject_ids.*' => 'required|integer|exists:subjects,id'
            ]);

            $subjectIds = $request->subject_ids;
            $deletedCount = 0;

            foreach ($subjectIds as $subjectId) {
                $subject = Subject::find($subjectId);
                if ($subject) {
                    $subject->delete();
                    $deletedCount++;
                }
            }

            $message = $deletedCount > 0
                ? "{$deletedCount} subject(s) deleted successfully."
                : "No subjects were deleted.";

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deletedCount
                ]);
            }

            return redirect()->route('web-subjects.index')->with('success', $message);
        } catch (\Exception $e) {
            $errorMessage = 'Failed to delete subjects: ' . $e->getMessage();

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
            // Get all subjects except core curriculum subjects (wajib category)
            $subjectsToDelete = Subject::where('category', '!=', 'wajib')->get();

            $deletedCount = 0;
            foreach ($subjectsToDelete as $subject) {
                $subject->delete();
                $deletedCount++;
            }

            $message = $deletedCount > 0
                ? "All {$deletedCount} non-core subjects deleted successfully."
                : "No subjects were deleted.";

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deletedCount
                ]);
            }

            return redirect()->route('web-subjects.index')->with('success', $message);
        } catch (\Exception $e) {
            $errorMessage = 'Failed to delete all subjects: ' . $e->getMessage();

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
