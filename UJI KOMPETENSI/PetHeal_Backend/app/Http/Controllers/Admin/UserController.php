<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * List all users — clinic scoped
     */
    public function index(Request $request)
    {
        $clinicId = currentClinicId();

        $query = User::query();

        // Clinic isolation: show only users belonging to the clinic
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        } else {
            // If no clinic context (super_admin with no clinic selected), show non-admin users only
            // Actually, super_admin without clinic can see all
        }
        
        // Search functionality
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('name', 'like', "{$search}%")
                  ->orWhere('email', 'like', "{$search}%")
                  ->orWhere('phone', 'like', "{$search}%");
            });
        }
        
        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show user details — clinic scoped
     */
    public function show($id)
    {
        $clinicId = currentClinicId();
        $query = User::with([
            'pets',
            'bookings' => fn ($query) => $query->with(['pet', 'doctor'])->latest()->limit(10),
        ])->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $user = $query->firstOrFail();
        $firebaseData = null;

        return view('admin.users.show', compact('user', 'firebaseData'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * Store new user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
        ]);

        // PHASE 3 (F-06 orphan guard): refuse clinic-less rows in overview mode.
        $contextClinicId = currentClinicId();
        if (!$contextClinicId) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Pilih klinik terlebih dahulu sebelum menambah user.');
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'role' => 'user',
            'clinic_id' => $contextClinicId,
            'firebase_uid' => 'local_' . Str::uuid()->toString(),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User berhasil ditambahkan.');
    }

    /**
     * Show edit form — clinic scoped
     */
    public function edit($id)
    {
        $clinicId = currentClinicId();
        $query = User::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $user = $query->firstOrFail();
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update user — clinic scoped
     */
    public function update(Request $request, $id)
    {
        $clinicId = currentClinicId();
        $query = User::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $user = $query->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
        ]);

        $data = $request->only(['name', 'email', 'phone']);

        if ($request->has('password') && $request->input('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Delete user — clinic scoped
     */
    public function destroy($id)
    {
        $clinicId = currentClinicId();
        $query = User::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $user = $query->firstOrFail();

        if (auth()->id() === $user->id) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'You cannot delete your own admin account.');
        }

        // Cascade delete related data to respect foreign key constraints
        \App\Models\MedicalRecord::whereHas('booking', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->delete();

        \App\Models\Booking::where('user_id', $user->id)->delete();
        $user->pets()->delete();
        $user->deviceTokens()->delete();
        $user->tokens()->delete();

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User berhasil dihapus.');
    }
}
