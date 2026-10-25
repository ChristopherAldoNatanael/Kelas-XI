<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Satu query dasar untuk daftar + statistik, supaya angka di kartu
        // tidak pernah dihitung dari sebagian halaman saja.
        $base = User::query()->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId));

        $stats = [
            'total' => (clone $base)->count(),
            'patients' => (clone $base)->where('role', 'user')->count(),
            'admins' => (clone $base)->whereIn('role', ['clinic_admin', 'admin', 'super_admin'])->count(),
            'new_this_month' => (clone $base)->where('role', 'user')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];

        $query = (clone $base)->withCount('pets');

        // Filter peran: halaman ini menampilkan pasien sekaligus akun admin
        // klinik, jadi peran harus bisa dipersempit tanpa halaman lain.
        $role = (string) $request->query('role', 'all');
        if ($role === 'patient') {
            $query->where('role', 'user');
        } elseif ($role === 'admin') {
            $query->whereIn('role', ['clinic_admin', 'admin', 'super_admin']);
        } else {
            $role = 'all';
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('name', 'like', "{$search}%")
                  ->orWhere('email', 'like', "{$search}%")
                  ->orWhere('phone', 'like', "{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users', 'stats', 'role'));
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
            'password' => 'required|string|min:8',
        ]);

        // PHASE 3 (F-06 orphan guard): refuse clinic-less rows in overview mode.
        $contextClinicId = currentClinicId();
        if (!$contextClinicId) {
            return redirect()->route('admin.users.index')
                ->with('error', __('clinics.select_clinic_first'));
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

        AuditLog::log('user.create', "Created patient account #{$user->id} ({$user->email})", $user);

        return redirect()->route('admin.users.index')->with('success', __('admin.user_created'));
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

        if (! $this->canManageAccount($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', __('admin.cannot_edit_staff'));
        }

        return view('admin.users.edit', compact('user'));
    }

    /**
     * Only patient rows are editable from this page. Staff rows
     * (clinic_admin/admin/super_admin) and doctor portal accounts (role=doctor)
     * hold login credentials — a clinic admin must never be able to change
     * another staff member's email or password. Self-service belongs in
     * admin.settings; doctor accounts are managed by invite/revoke.
     */
    private function canManageAccount(User $user): bool
    {
        if ($user->role === 'user') {
            return true;
        }

        return auth()->id() === $user->id || isSuperAdmin();
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

        if (! $this->canManageAccount($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', __('admin.cannot_edit_staff'));
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
        ]);

        $data = $request->only(['name', 'email', 'phone']);

        if ($request->has('password') && $request->input('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);
        AuditLog::log('user.update', "Updated account #{$user->id} ({$user->email})", $user);

        return redirect()->route('admin.users.index')->with('success', __('admin.user_updated'));
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
                ->with('error', __('admin.cannot_delete_self'));
        }

        if ($user->role !== 'user') {
            return redirect()
                ->route('admin.users.index')
                ->with('error', __('admin.cannot_delete_admin'));
        }

        // Never cascade-delete clinical/financial history. A patient with
        // bookings must be retained so invoices, medical records, and audit
        // trails stay reproducible.
        if ($user->bookings()->exists()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', __('admin.cannot_delete_user_with_history'));
        }

        DB::transaction(function () use ($user) {
            AuditLog::log(
                'user.delete',
                "Deleted patient account #{$user->id} ({$user->email}) — no booking history",
                $user
            );

            $user->pets()->delete();
            $user->deviceTokens()->delete();
            $user->tokens()->delete();
            $user->delete();
        });

        return redirect()->route('admin.users.index')->with('success', __('admin.user_deleted'));
    }
}
