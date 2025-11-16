<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class WebSubjectController extends Controller
{
    public function index()
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $subjects = Subject::with(['teachers.user'])->withTrashed()->paginate(20);
            return view('subjects.index', compact('subjects'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Failed to load subjects: ' . $e->getMessage());
        }
    }

    public function create()
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        return view('subjects.create');
    }

    public function store(Request $request)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $request->validate([
                'nama' => 'required|string|max:255|unique:subjects',
                'kode' => 'required|string|max:10|unique:subjects',
                'description' => 'nullable|string',
                'status' => 'required|in:active,inactive',
            ]);

            Subject::create($request->all());

            return redirect()->route('subjects.index')->with('success', 'Subject created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to create subject: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $subject = Subject::withTrashed()->findOrFail($id);
            return view('subjects.show', compact('subject'));
        } catch (\Exception $e) {
            return redirect()->route('subjects.index')->with('error', 'Subject not found.');
        }
    }

    public function edit($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $subject = Subject::withTrashed()->findOrFail($id);
            return view('subjects.edit', compact('subject'));
        } catch (\Exception $e) {
            return redirect()->route('subjects.index')->with('error', 'Subject not found.');
        }
    }

    public function update(Request $request, $id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $subject = Subject::withTrashed()->findOrFail($id);

            $request->validate([
                'nama' => 'required|string|max:255|unique:subjects,nama,' . $id,
                'kode' => 'required|string|max:10|unique:subjects,kode,' . $id,
                'description' => 'nullable|string',
                'status' => 'required|in:active,inactive',
            ]);

            $subject->update($request->all());

            return redirect()->route('subjects.index')->with('success', 'Subject updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to update subject: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $subject = Subject::findOrFail($id);
            $subject->delete();

            return redirect()->route('subjects.index')->with('success', 'Subject deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to delete subject: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $subject = Subject::withTrashed()->findOrFail($id);
            $subject->restore();

            return redirect()->route('subjects.index')->with('success', 'Subject restored successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to restore subject: ' . $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $subject = Subject::withTrashed()->findOrFail($id);
            $subject->forceDelete();

            return redirect()->route('subjects.index')->with('success', 'Subject permanently deleted.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to permanently delete subject: ' . $e->getMessage());
        }
    }
}
