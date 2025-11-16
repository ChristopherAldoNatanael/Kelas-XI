<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use Illuminate\Http\Request;

class WebClassController extends Controller
{
    public function index()
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $classes = ClassModel::withTrashed()->paginate(20);
            return view('classes.index', compact('classes'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Failed to load classes: ' . $e->getMessage());
        }
    }

    public function create()
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        return view('classes.create');
    }

    public function store(Request $request)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:classes',
                'grade_level' => 'required|integer|min:1|max:12',
                'capacity' => 'required|integer|min:1',
                'academic_year' => 'required|string|max:20',
                'status' => 'required|in:active,inactive',
            ]);

            ClassModel::create($request->all());

            return redirect()->route('classes.index')->with('success', 'Class created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to create class: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $class = ClassModel::withTrashed()->findOrFail($id);
            return view('classes.show', compact('class'));
        } catch (\Exception $e) {
            return redirect()->route('classes.index')->with('error', 'Class not found.');
        }
    }

    public function edit($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $class = ClassModel::withTrashed()->findOrFail($id);
            return view('classes.edit', compact('class'));
        } catch (\Exception $e) {
            return redirect()->route('classes.index')->with('error', 'Class not found.');
        }
    }

    public function update(Request $request, $id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $class = ClassModel::withTrashed()->findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255|unique:classes,name,' . $id,
                'grade_level' => 'required|integer|min:1|max:12',
                'capacity' => 'required|integer|min:1',
                'academic_year' => 'required|string|max:20',
                'status' => 'required|in:active,inactive',
            ]);

            $class->update($request->all());

            return redirect()->route('classes.index')->with('success', 'Class updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to update class: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $class = ClassModel::findOrFail($id);
            $class->delete();

            return redirect()->route('classes.index')->with('success', 'Class deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to delete class: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $class = ClassModel::withTrashed()->findOrFail($id);
            $class->restore();

            return redirect()->route('classes.index')->with('success', 'Class restored successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to restore class: ' . $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        $token = session('api_token');
        if (!$token) {
            return redirect()->route('login');
        }

        try {
            $class = ClassModel::withTrashed()->findOrFail($id);
            $class->forceDelete();

            return redirect()->route('classes.index')->with('success', 'Class permanently deleted.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to permanently delete class: ' . $e->getMessage());
        }
    }
}
