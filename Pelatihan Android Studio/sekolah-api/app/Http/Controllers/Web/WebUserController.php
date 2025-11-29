<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class WebUserController extends Controller
{
    public function index()
    {
        try {
            $users = User::paginate(20);
            return view('users.index', compact('users'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Failed to load users: ' . $e->getMessage());
        }
    }

    public function create()
    {
        // Get all RPL classes (X RPL, XI RPL, XII RPL and their variants)
        $classes = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
            ->where('status', 'active')
            ->orderBy('level')
            ->orderBy('nama_kelas')
            ->get();

        return view('users.create', compact('classes'));
    }

    public function store(Request $request)
    {
        try {
            // Log incoming request data for debugging
            Log::info('User creation attempt', [
                'data' => $request->except('password'),
                'has_password' => $request->filled('password'),
            ]);

            $validationRules = [
                'nama' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|in:admin,kurikulum,siswa,kepala_sekolah',
                'status' => 'sometimes|in:active,inactive,suspended',
            ];

            // Add class_id validation if role is siswa
            if ($request->role === 'siswa') {
                $validationRules['class_id'] = 'required|exists:classes,id';
            }

            $validated = $request->validate($validationRules);

            Log::info('Validation passed', ['validated' => $validated]);

            $userData = [
                'nama' => $request->nama,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'status' => $request->get('status', 'active'),
            ];

            // Add class_id if role is siswa
            if ($request->role === 'siswa' && $request->filled('class_id')) {
                $userData['class_id'] = $request->class_id;
                Log::info('Adding class_id for siswa', ['class_id' => $request->class_id]);
            }

            $user = User::create($userData);

            Log::info('User created successfully', [
                'user_id' => $user->id,
                'nama' => $user->nama,
                'role' => $user->role
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User created successfully: ' . $user->nama,
                    'user' => $user
                ]);
            }

            return redirect()->route('web-users.index')
                ->with('success', 'User created successfully: ' . $user->nama);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', [
                'errors' => $e->errors(),
                'input' => $request->except('password')
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
            Log::error('User creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except('password')
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create user: ' . $e->getMessage()
                ], 500);
            }

            return back()
                ->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function show($id)
    {
        try {
            $user = User::findOrFail($id);
            return view('users.show', compact('user'));
        } catch (\Exception $e) {
            return redirect()->route('web-users.index')->with('error', 'User not found.');
        }
    }

    public function edit($id)
    {
        try {
            $user = User::findOrFail($id);

            // Get all RPL classes (X RPL, XI RPL, XII RPL and their variants)
            $classes = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
                ->where('status', 'active')
                ->orderBy('level')
                ->orderBy('nama_kelas')
                ->get();

            return view('users.edit', compact('user', 'classes'));
        } catch (\Exception $e) {
            return redirect()->route('web-users.index')->with('error', 'User not found.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            Log::info('User update attempt', [
                'user_id' => $id,
                'data' => $request->except('password'),
            ]);

            $validationRules = [
                'nama' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $id,
                'password' => 'nullable|string|min:8|confirmed',
                'role' => 'required|in:admin,kurikulum,siswa,kepala_sekolah',
                'status' => 'sometimes|in:active,inactive,suspended',
            ];

            // Add class_id validation if role is siswa
            if ($request->role === 'siswa') {
                $validationRules['class_id'] = 'required|exists:classes,id';
            }

            $validated = $request->validate($validationRules);

            Log::info('Update validation passed', ['validated' => $validated]);

            $updateData = [
                'nama' => $request->nama,
                'email' => $request->email,
                'role' => $request->role,
                'status' => $request->get('status', $user->status),
            ];

            // Handle class_id based on role
            if ($request->role === 'siswa' && $request->filled('class_id')) {
                $updateData['class_id'] = $request->class_id;
                \Log::info('Updating class_id for siswa', ['class_id' => $request->class_id]);
            } elseif ($request->role !== 'siswa') {
                // If role is not siswa, remove class_id
                $updateData['class_id'] = null;
                \Log::info('Removing class_id (role is not siswa)');
            }

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
                \Log::info('Password will be updated');
            }

            $user->update($updateData);

            \Log::info('User updated successfully', [
                'user_id' => $user->id,
                'nama' => $user->nama,
                'role' => $user->role
            ]);

            return redirect()->route('web-users.index')
                ->with('success', 'User updated successfully: ' . $user->nama);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Update validation failed', [
                'errors' => $e->errors(),
                'input' => $request->except('password')
            ]);
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            \Log::error('User update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except('password')
            ]);
            return back()
                ->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
            }

            return redirect()->route('web-users.index')->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Failed to delete user: ' . $e->getMessage()], 500);
            }
            return back()->withErrors('Failed to delete user: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        try {
            $user = User::findOrFail($id);
            // Note: Restore functionality not available without SoftDeletes

            return redirect()->route('web-users.index')->with('success', 'User restored successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to restore user: ' . $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return redirect()->route('web-users.index')->with('success', 'User permanently deleted.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to permanently delete user: ' . $e->getMessage());
        }
    }

    public function import()
    {
        return view('users.import');
    }

    public function importStore(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
                'skip_duplicates' => 'sometimes|boolean',
                'update_existing' => 'sometimes|boolean',
            ]);

            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());

            // Process the file based on extension
            if ($extension === 'csv') {
                $result = $this->processCsvImport($file, $request);
            } elseif (in_array($extension, ['xlsx', 'xls'])) {
                $result = $this->processExcelImport($file, $request);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported file format. Please use CSV or Excel (.xlsx, .xls) files.'
                ], 400);
            }

            return response()->json($result);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processCsvImport($file, Request $request)
    {
        $path = $file->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $header = array_shift($data);

        return $this->processImportData($data, $header, $request);
    }

    private function processExcelImport($file, Request $request)
    {
        // Check if Laravel Excel is available
        if (!class_exists('\Maatwebsite\Excel\Facades\Excel')) {
            throw new \Exception('Excel import requires the maatwebsite/excel package. Please install it with: composer require maatwebsite/excel, or convert your Excel file to CSV format for import.');
        }

        $data = \Maatwebsite\Excel\Facades\Excel::toArray([], $file)[0];
        $header = array_shift($data);

        return $this->processImportData($data, $header, $request);
    }

    private function processImportData($data, $header, Request $request)
    {
        $processed = 0;
        $skipped = 0;
        $errors = [];
        $skipDuplicates = $request->boolean('skip_duplicates');
        $updateExisting = $request->boolean('update_existing');

        // Normalize header names
        $header = array_map(function($col) {
            return strtolower(trim($col));
        }, $header);

        // Required columns mapping
        $requiredColumns = ['nama', 'email', 'role'];
        $optionalColumns = ['password', 'class_id', 'is_banned'];

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

                if (empty(trim($rowData['email'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Email is required";
                    continue;
                }

                if (empty(trim($rowData['role'] ?? ''))) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Role is required";
                    continue;
                }

                // Validate email format
                if (!filter_var($rowData['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": Invalid email format";
                    continue;
                }

                // Validate role
                $validRoles = ['admin', 'kurikulum', 'siswa', 'kepala_sekolah'];
                \Log::info('Import role validation', [
                    'row' => $rowIndex + 2,
                    'role_from_csv' => $rowData['role'],
                    'valid_roles' => $validRoles,
                    'is_valid' => in_array($rowData['role'], $validRoles)
                ]);
                if (!in_array($rowData['role'], $validRoles)) {
                    \Log::warning('Import role validation failed', [
                        'row' => $rowIndex + 2,
                        'invalid_role' => $rowData['role'],
                        'expected_roles' => $validRoles
                    ]);
                    $errors[] = "Row " . ($rowIndex + 2) . ": Invalid role. Must be one of: " . implode(', ', $validRoles);
                    continue;
                }

                // Check for existing user (including soft deleted)
                $existingUser = User::withTrashed()->where('email', $rowData['email'])->first();

                if ($existingUser) {
                    if ($existingUser->trashed()) {
                        // User is soft deleted
                        if ($updateExisting) {
                            // Restore and update the user
                            $existingUser->restore();
                            \Log::info('Restoring soft deleted user', ['email' => $rowData['email']]);
                        } elseif ($skipDuplicates) {
                            $skipped++;
                            continue;
                        } else {
                            $errors[] = "Row " . ($rowIndex + 2) . ": User with email '{$rowData['email']}' exists but is deleted. Use 'Update existing users' to restore.";
                            continue;
                        }
                    } elseif ($skipDuplicates) {
                        $skipped++;
                        continue;
                    } elseif (!$updateExisting) {
                        $errors[] = "Row " . ($rowIndex + 2) . ": User with email '{$rowData['email']}' already exists";
                        continue;
                    }
                }

                // Prepare user data
                $userData = [
                    'nama' => trim($rowData['nama']),
                    'email' => trim($rowData['email']),
                    'role' => $rowData['role'],
                ];

                // Handle password
                if (!empty($rowData['password'])) {
                    $userData['password'] = Hash::make($rowData['password']);
                } elseif (!$existingUser || $updateExisting) {
                    // Generate random password for new users or when updating without password
                    $userData['password'] = Hash::make('password123'); // Default password
                }

                // Handle class_id for students
                if ($rowData['role'] === 'siswa') {
                    if (!empty($rowData['class_id'])) {
                        // Validate class exists
                        if (!\App\Models\ClassModel::find($rowData['class_id'])) {
                            $errors[] = "Row " . ($rowIndex + 2) . ": Invalid class_id '{$rowData['class_id']}'";
                            continue;
                        }
                        $userData['class_id'] = $rowData['class_id'];
                    } else {
                        $errors[] = "Row " . ($rowIndex + 2) . ": class_id is required for students";
                        continue;
                    }
                }

                // Handle is_banned
                if (isset($rowData['is_banned'])) {
                    $userData['is_banned'] = (bool)$rowData['is_banned'];
                }

                // Create or update user
                if ($existingUser && $updateExisting) {
                    $existingUser->update($userData);
                } else {
                    User::create($userData);
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
            'message' => "Import completed. {$processed} users processed, {$skipped} skipped, " . count($errors) . " errors."
        ];
    }

    public function downloadTemplate($format)
    {
        try {
            $format = strtolower($format);

            if (!in_array($format, ['csv', 'xlsx'])) {
                abort(404);
            }

            // Sample data
            $data = [
                ['nama', 'email', 'role', 'password', 'class_id', 'is_banned'],
                ['John Doe', 'john@example.com', 'admin', 'password123', '', '0'],
                ['Jane Smith', 'jane@example.com', 'siswa', 'password123', '1', '0'],
                ['Bob Johnson', 'bob@example.com', 'kurikulum', 'password123', '', '0'],
            ];

            $filename = 'users_import_template.' . $format;

            if ($format === 'csv') {
                return $this->downloadCsvTemplate($data, $filename);
            } else {
                return $this->downloadExcelTemplate($data, $filename);
            }

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

    private function downloadExcelTemplate($data, $filename)
    {
        if (!class_exists('\Maatwebsite\Excel\Facades\Excel')) {
            // Fallback to CSV if Excel package not available
            return $this->downloadCsvTemplate($data, str_replace('.xlsx', '.csv', $filename));
        }

        // Create Excel file
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_template');
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add data
        foreach ($data as $rowIndex => $row) {
            foreach ($row as $colIndex => $cell) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $cell);
            }
        }

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Save and return
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    public function bulkDelete(Request $request)
    {
        try {
            $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'required|integer|exists:users,id',
            ]);

            $userIds = $request->user_ids;
            $deletedCount = 0;
            $errors = [];

            foreach ($userIds as $userId) {
                try {
                    $user = User::findOrFail($userId);

                    // Prevent deletion of current authenticated user
                    if ($user->id === auth()->id()) {
                        $errors[] = "Cannot delete your own account (ID: {$userId})";
                        continue;
                    }

                    // Prevent deletion of admin users (optional security measure)
                    if ($user->role === 'admin') {
                        $errors[] = "Cannot delete admin user: {$user->nama} (ID: {$userId})";
                        continue;
                    }

                    $user->delete(); // Soft delete
                    $deletedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to delete user ID {$userId}: " . $e->getMessage();
                }
            }

            $message = "{$deletedCount} user(s) deleted successfully.";
            if (!empty($errors)) {
                $message .= " Errors: " . implode(', ', $errors);
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deletedCount,
                    'errors' => $errors
                ]);
            }

            return redirect()->route('web-users.index')->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bulk deletion failed: ' . $e->getMessage()
                ], 500);
            }
            return back()->withErrors('Bulk deletion failed: ' . $e->getMessage());
        }
    }

    public function bulkDeleteAll(Request $request)
    {
        try {
            // Get all users except current authenticated user and admins
            $usersToDelete = User::where('id', '!=', auth()->id())
                ->where('role', '!=', 'admin')
                ->get();

            if ($usersToDelete->isEmpty()) {
                $message = 'No users available for deletion.';
            } else {
                $deletedCount = 0;
                foreach ($usersToDelete as $user) {
                    $user->delete(); // Soft delete
                    $deletedCount++;
                }
                $message = "All {$deletedCount} eligible user(s) deleted successfully.";
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deletedCount ?? 0
                ]);
            }

            return redirect()->route('web-users.index')->with('success', $message);
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bulk delete all failed: ' . $e->getMessage()
                ], 500);
            }
            return back()->withErrors('Bulk delete all failed: ' . $e->getMessage());
        }
    }

}
