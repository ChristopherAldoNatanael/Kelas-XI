<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use Illuminate\Http\Request;

class WebClassController extends Controller
{
    public function index()
    {
        try {
            $classes = ClassModel::paginate(20);
            return view('classes.index', compact('classes'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Failed to load classes: ' . $e->getMessage());
        }
    }

    public function create()
    {
        return view('classes.create');
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'nama_kelas' => 'required|string|max:255',
                'kode_kelas' => 'required|string|max:255|unique:classes,kode_kelas',
                'tingkat_kelas' => 'required|integer|min:10|max:12',
                'tahun_ajaran' => 'required|string|max:20',
                'major' => 'nullable|string|max:255',
                'capacity' => 'nullable|integer|min:1|max:100',
                'homeroom_teacher_id' => 'nullable|exists:teachers,id',
                'status' => 'required|in:active,inactive',
            ]);

            ClassModel::create([
                'nama_kelas' => $request->nama_kelas,
                'kode_kelas' => $request->kode_kelas,
                'level' => $request->tingkat_kelas,
                'academic_year' => $request->tahun_ajaran,
                'major' => $request->major,
                'capacity' => $request->capacity,
                'homeroom_teacher_id' => $request->homeroom_teacher_id,
                'status' => $request->status,
            ]);

            return redirect()->route('web-classes.index')->with('success', 'Class created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to create class: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        try {
            $class = ClassModel::findOrFail($id);
            return view('classes.show', compact('class'));
        } catch (\Exception $e) {
            return redirect()->route('web-classes.index')->with('error', 'Class not found.');
        }
    }

    public function edit($id)
    {
        try {
            $class = ClassModel::findOrFail($id);
            return view('classes.edit', compact('class'));
        } catch (\Exception $e) {
            return redirect()->route('web-classes.index')->with('error', 'Class not found.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $class = ClassModel::findOrFail($id);

            $request->validate([
                'nama_kelas' => 'required|string|max:255',
                'kode_kelas' => 'required|string|max:255|unique:classes,kode_kelas,' . $id,
                'tingkat_kelas' => 'required|integer|min:10|max:12',
                'tahun_ajaran' => 'required|string|max:20',
                'major' => 'nullable|string|max:255',
                'capacity' => 'nullable|integer|min:1|max:100',
                'homeroom_teacher_id' => 'nullable|exists:teachers,id',
                'status' => 'required|in:active,inactive',
            ]);

            $class->update([
                'nama_kelas' => $request->nama_kelas,
                'kode_kelas' => $request->kode_kelas,
                'level' => $request->tingkat_kelas,
                'academic_year' => $request->tahun_ajaran,
                'major' => $request->major,
                'capacity' => $request->capacity,
                'homeroom_teacher_id' => $request->homeroom_teacher_id,
                'status' => $request->status,
            ]);

            return redirect()->route('web-classes.index')->with('success', 'Class updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to update class: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $class = ClassModel::findOrFail($id);
            $class->delete();

            return redirect()->route('web-classes.index')->with('success', 'Class deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to delete class: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        try {
            // Soft deletes not enabled for classes, redirect to index
            return redirect()->route('web-classes.index')->with('error', 'Restore function not available for classes.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to restore class: ' . $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        try {
            $class = ClassModel::findOrFail($id);
            $class->delete(); // Regular delete since no soft deletes

            return redirect()->route('web-classes.index')->with('success', 'Class permanently deleted.');
        } catch (\Exception $e) {
            return back()->withErrors('Failed to permanently delete class: ' . $e->getMessage());
        }
    }
}
