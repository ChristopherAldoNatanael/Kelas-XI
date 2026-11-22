<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\PatientScope;
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
        //
        // Definisi "akun klinik" (PatientScope::scopeClinicUsers): staf yang
        // terikat klinik ini ATAU pasien yang pernah booking/berekam medis di
        // sini. Pasien adalah akun global, jadi `users.clinic_id` saja akan
        // menyembunyikan seluruh pasien yang mendaftar dari aplikasi.
        $base = User::query();
        PatientScope::scopeClinicUsers($base, $clinicId);

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
            // Riwayat yang tampil hanya transaksi di klinik ini. Pasien global
            // bisa punya booking di klinik lain, dan itu bukan urusan admin
            // klinik ini (data minimization).
            'bookings' => fn ($query) => $query
                ->when($clinicId, fn ($bookingQuery) => $bookingQuery->where('clinic_id', $clinicId))
                ->with(['pet', 'doctor'])
                ->latest()
                ->limit(10),
        ])->where('id', $id);
        if ($clinicId) {
            PatientScope::scopeClinicUsers($query, $clinicId);
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
            PatientScope::scopeClinicUsers($query, $clinicId);
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
            PatientScope::scopeClinicUsers($query, $clinicId);
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
            PatientScope::scopeClinicUsers($query, $clinicId);
        }
        $user = $query->firstOrFail();

        if (auth()->id() === $user->id) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', __('admin.cannot_delete_self'));
        }

        // Akun staf klinik (`clinic_admin`/`admin`) lahir dari approval join
        // request. Salah approve harus bisa dibatalkan tanpa menghapus klinik
        // beserta seluruh datanya, jadi super admin boleh menghapus AKUN-nya
        // saja; klinik tetap utuh dan bisa dihapus terpisah bila memang perlu.
        // Admin klinik tidak boleh menghapus sesama admin.
        if (in_array($user->role, ['clinic_admin', 'admin'], true)) {
            if (auth()->user()?->role !== 'super_admin') {
                return redirect()
                    ->route('admin.users.index')
                    ->with('error', __('admin.cannot_delete_admin'));
            }

            return $this->destroyClinicAdmin($user);
        }

        if ($user->role === 'super_admin') {
            return redirect()
                ->route('admin.users.index')
                ->with('error', __('admin.cannot_delete_super_admin'));
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

    /**
     * Hapus satu akun admin klinik tanpa menyentuh kliniknya.
     *
     * Dipakai super admin untuk membatalkan approval join request yang salah.
     * Klinik, dokter, layanan, booking, dan rekam medis tidak diubah sama sekali;
     * yang hilang hanya akun login admin tersebut.
     */
    private function destroyClinicAdmin(User $user)
    {
        // Admin tanpa klinik berarti barisnya tidak valid sebagai staf; jangan
        // dihapus diam-diam lewat jalur ini karena bukan itu kasus yang dimaksud.
        if (! $user->clinic_id) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', __('admin.cannot_delete_clinicless_admin'));
        }

        // Akun staf bisa saja juga dipakai sebagai pasien. Kalau punya booking,
        // FK `bookings.user_id` CASCADE akan ikut menghapus riwayat klinis dan
        // nominal pembayaran — persis yang dilarang aturan repo ini.
        if ($user->bookings()->exists()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', __('admin.cannot_delete_user_with_history'));
        }

        $userId = $user->id;
        $email = $user->email;
        $role = $user->role;
        $name = $user->name;
        $clinicId = $user->clinic_id;
        $clinicName = $user->clinic?->name;

        DB::transaction(function () use ($user, $userId, $email, $role, $clinicId, $clinicName) {
            // Ditulis lebih dulu dan dengan `clinic_id` eksplisit: super admin
            // tidak punya konteks klinik, jadi `AuditLog::log()` akan menyimpan
            // null. Identitas akun disimpan di deskripsi karena barisnya segera
            // hilang (FK `audit_logs.user_id` = SET NULL, bukan cascade).
            AuditLog::create([
                'user_id' => auth()->id(),
                'clinic_id' => $clinicId,
                'action' => 'user.delete_admin',
                'model_type' => User::class,
                'model_id' => $userId,
                'description' => sprintf(
                    'Deleted clinic admin #%d (%s, role %s)%s',
                    $userId,
                    $email,
                    $role,
                    $clinicName ? " — clinic: {$clinicName}" : ''
                ),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Cabut sesi & token supaya akun yang dihapus benar-benar tidak
            // bisa masuk lagi lewat browser atau aplikasi yang masih login.
            DB::table('sessions')->where('user_id', $userId)->delete();

            $user->deviceTokens()->delete();
            $user->tokens()->delete();
            $user->delete();
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('admin.clinic_admin_deleted', ['name' => $name]));
    }
}
