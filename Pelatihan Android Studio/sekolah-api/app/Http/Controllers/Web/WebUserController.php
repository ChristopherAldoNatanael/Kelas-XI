<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class WebUserController extends Controller
{
    public function index()
    {
        try {
            $users = User::withTrashed()
                ->with('class') // Load class relationship
                ->paginate(20);
            return view('users.index', compact('users'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Failed to load users: ' . $e->getMessage());
        }
    }

    public function create()
    {
        // Get only RPL classes (X RPL, XI RPL, XII RPL)
        $classes = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
            ->where('status', 'active')
            ->whereIn('name', ['X RPL', 'XI RPL', 'XII RPL'])
            ->orderBy('level')
            ->get();

        return view('users.create', compact('classes'));
    }

    public function store(Request $request)
    {
        try {
            // Log incoming request data for debugging
            \Log::info('User creation attempt', [
                'data' => $request->except('password'),
                'has_password' => $request->filled('password'),
            ]);

            $validationRules = [
                'nama' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|in:admin,kurikulum,siswa,kepala-sekolah',
                'status' => 'sometimes|in:active,inactive,suspended',
            ];

            // Add class_id validation if role is siswa
            if ($request->role === 'siswa') {
                $validationRules['class_id'] = 'required|exists:classes,id';
            }

            $validated = $request->validate($validationRules);

            \Log::info('Validation passed', ['validated' => $validated]);

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
                \Log::info('Adding class_id for siswa', ['class_id' => $request->class_id]);
            }

            $user = User::create($userData);

            \Log::info('User created successfully', [
                'user_id' => $user->id,
                'nama' => $user->nama,
                'role' => $user->role,
                'class_id' => $user->class_id
            ]);

            return redirect()->route('web-users.index')
                ->with('success', 'User created successfully: ' . $user->nama);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed', [
                'errors' => $e->errors(),
                'input' => $request->except('password')
            ]);
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            \Log::error('User creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except('password')
            ]);
            return back()
                ->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function show($id)
    {
        try {
            $user = User::withTrashed()->findOrFail($id);
            return view('users.show', compact('user'));
        } catch (\Exception $e) {
            return redirect()->route('users.index')->with('error', 'User not found.');
        }
    }

    public function edit($id)
    {
        try {
            $user = User::withTrashed()->findOrFail($id);

            // Get only RPL classes (X RPL, XI RPL, XII RPL)
            $classes = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
                ->where('status', 'active')
                ->whereIn('name', ['X RPL', 'XI RPL', 'XII RPL'])
                ->orderBy('level')
                ->get();

            return view('users.edit', compact('user', 'classes'));
        } catch (\Exception $e) {
            return redirect()->route('web-users.index')->with('error', 'User not found.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::withTrashed()->findOrFail($id);

            \Log::info('User update attempt', [
                'user_id' => $id,
                'data' => $request->except('password'),
            ]);

            $validationRules = [
                'nama' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $id,
                'password' => 'nullable|string|min:8|confirmed',
                'role' => 'required|in:admin,kurikulum,siswa,kepala-sekolah',
                'status' => 'sometimes|in:active,inactive,suspended',
            ];

            // Add class_id validation if role is siswa
            if ($request->role === 'siswa') {
                $validationRules['class_id'] = 'required|exists:classes,id';
            }

            $validated = $request->validate($validationRules);

            \Log::info('Update validation passed', ['validated' => $validated]);

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
                'role' => $user->role,
                'class_id' => $user->class_id
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

    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return redirect()->route('users.index')->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to delete user: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        try {
            $user = User::withTrashed()->findOrFail($id);
            $user->restore();

            return redirect()->route('users.index')->with('success', 'User restored successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to restore user: ' . $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        try {
            $user = User::withTrashed()->findOrFail($id);
            $user->forceDelete();

            return redirect()->route('users.index')->with('success', 'User permanently deleted.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to permanently delete user: ' . $e->getMessage());
        }
    }
}
