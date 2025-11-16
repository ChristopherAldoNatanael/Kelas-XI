<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;

class WebTeacherController extends Controller
{
    public function index()
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $teachers = Teacher::paginate(20);
            return view('teachers.index', compact('teachers'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Failed to load teachers: ' . $e->getMessage());
        }
    }

    public function create()
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            return view('teachers.create');
        } catch (\Exception $e) {
            return redirect()->route('teachers.index')->with('error', 'Failed to load create form: ' . $e->getMessage());
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
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:teachers',
                'password' => 'required|string|min:8',
                'mata_pelajaran' => 'required|string|max:255',
            ]);

            Teacher::create($request->all());

            return redirect()->route('teachers.index')->with('success', 'Teacher created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to create teacher: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $teacher = Teacher::findOrFail($id);
            return view('teachers.show', compact('teacher'));
        } catch (\Exception $e) {
            return redirect()->route('teachers.index')->with('error', 'Teacher not found.');
        }
    }

    public function edit($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $teacher = Teacher::withTrashed()->findOrFail($id);
            $users = User::where('role', 'teacher')->get();
            return view('teachers.edit', compact('teacher', 'users'));
        } catch (\Exception $e) {
            return redirect()->route('teachers.index')->with('error', 'Teacher not found.');
        }
    }

    public function update(Request $request, $id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $teacher = Teacher::withTrashed()->findOrFail($id);

            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'nip' => 'required|string|max:255|unique:teachers,nip,' . $id,
                'nama' => 'required|string|max:255',
                'alamat' => 'nullable|string',
                'telepon' => 'nullable|string|max:20',
                'tanggal_lahir' => 'nullable|date',
                'jenis_kelamin' => 'required|in:L,P',
                'status' => 'required|in:active,inactive',
            ]);

            $teacher->update($request->all());

            return redirect()->route('teachers.index')->with('success', 'Teacher updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to update teacher: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $teacher = Teacher::findOrFail($id);
            $teacher->delete();

            return redirect()->route('teachers.index')->with('success', 'Teacher deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to delete teacher: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $teacher = Teacher::withTrashed()->findOrFail($id);
            $teacher->restore();

            return redirect()->route('teachers.index')->with('success', 'Teacher restored successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to restore teacher: ' . $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $teacher = Teacher::withTrashed()->findOrFail($id);
            $teacher->forceDelete();

            return redirect()->route('teachers.index')->with('success', 'Teacher permanently deleted.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to permanently delete teacher: ' . $e->getMessage());
        }
    }
}
