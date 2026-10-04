<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicJoinRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLoginForm()
    {
        if (Auth::check() && in_array(Auth::user()->role, ['super_admin', 'clinic_admin', 'admin'])) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    /**
     * Process login — supports super_admin, clinic_admin, legacy admin
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
            'remember' => 'nullable|boolean',
        ]);

        $user = \App\Models\User::where('email', $credentials['email'])
            ->whereIn('role', ['super_admin', 'clinic_admin', 'admin'])
            ->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            // PHASE 3 (F-03): suspended clinic stays suspended — refuse login
            // when the tenant's clinic is missing/inactive. super_admin exempt.
            if ($user->role !== 'super_admin') {
                $clinic = $user->clinic;
                if (!$user->clinic_id || !$clinic || !$clinic->is_active) {
                    return back()->withErrors([
                        'email' => __('auth.inactive_clinic'),
                    ])->onlyInput('email');
                }
            }

            // PHASE 4: wire the remember-me checkbox (was dead UI — the
            // credential cookie was never set).
            Auth::login($user, (bool) $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'email' => __('auth.invalid_credentials'),
        ])->onlyInput('email');
    }

    /**
     * Show join request form (replaces old register)
     */
    public function showJoinRequestForm()
    {
        if (Auth::check() && in_array(Auth::user()->role, ['super_admin', 'clinic_admin', 'admin'])) {
            return redirect()->route('admin.dashboard');
        }

        $clinics = Clinic::where('is_active', true)->orderBy('name')->get();
        return view('admin.auth.join-request', compact('clinics'));
    }

    /**
     * Submit join request (does NOT create a user)
     */
    public function submitJoinRequest(Request $request)
    {
        $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $clinic = Clinic::where('is_active', true)->findOrFail($request->input('clinic_id'));

        // Check for duplicate pending request
        $existingRequest = ClinicJoinRequest::where('email', $request->input('email'))
            ->where('clinic_id', $clinic->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return back()->withErrors([
                'email' => __('auth.duplicate_request'),
            ])->withInput();
        }

        // Check if email already taken by a user
        $existingUser = \App\Models\User::where('email', $request->input('email'))->first();
        if ($existingUser) {
            return back()->withErrors([
                'email' => __('auth.email_taken'),
            ])->withInput();
        }

        ClinicJoinRequest::create([
            'clinic_id' => $clinic->id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password_hash' => Hash::make($request->input('password')),
            'status' => 'pending',
        ]);

        return redirect()->route('admin.login')->with('success', __('auth.request_submitted'));
    }

    /**
     * Show join requests list — clinic scoped
     */
    public function joinRequests()
    {
        $clinicId = currentClinicId();

        $requests = ClinicJoinRequest::with(['clinic', 'reviewedBy'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.join-requests', compact('requests'));
    }

    /**
     * Approve a join request — clinic scoped
     */
    public function approveJoinRequest(Request $request, $id)
    {
        $clinicId = currentClinicId();

        // PHASE 5 (SA-02): approve + user create must be atomic under a row
        // lock — concurrent double-approvals previously raced past the
        // pending check (one side then hit users.email unique as a 500).
        $joinRequest = \Illuminate\Support\Facades\DB::transaction(function () use ($id, $clinicId) {
            $query = ClinicJoinRequest::where('status', 'pending')->where('id', $id);
            if ($clinicId) {
                $query->where('clinic_id', $clinicId);
            }
            $joinRequest = $query->lockForUpdate()->firstOrFail();

            // Check if email already taken by any user
            if (User::where('email', $joinRequest->email)->exists()) {
                // Auto-reject this request since email is taken
                $joinRequest->update([
                    'status' => 'rejected',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);

                return $joinRequest;
            }

            // Create user as clinic_admin
            User::create([
                'name' => $joinRequest->name,
                'email' => $joinRequest->email,
                'phone' => $joinRequest->phone,
                'password' => $joinRequest->password_hash,
                'role' => 'clinic_admin',
                'clinic_id' => $joinRequest->clinic_id,
            ]);

            $joinRequest->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            return $joinRequest->fresh();
        });

        if ($joinRequest->status === 'rejected') {
            return back()->withErrors(['email' => __('clinics.email_already_used')]);
        }

        return back()->with('success', __('clinics.approved_success', ['name' => $joinRequest->name]));
    }

    /**
     * Reject a join request — clinic scoped
     */
    public function rejectJoinRequest(Request $request, $id)
    {
        $clinicId = currentClinicId();
        $query = ClinicJoinRequest::where('status', 'pending')->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $joinRequest = $query->firstOrFail();

        $joinRequest->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', __('clinics.rejected_success', ['name' => $joinRequest->name]));
    }

    /**
     * Switch active clinic (super_admin only)
     */
    public function switchClinic(Request $request)
    {
        $clinicId = $request->input('clinic_id');

        if (empty($clinicId)) {
            // System Overview — clear clinic context
            $request->session()->forget('current_clinic_id');
            return redirect()->route('admin.dashboard')->with('success', __('clinics.system_overview_switch'));
        }

        $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
        ]);

        $request->session()->put('current_clinic_id', (int) $clinicId);

        return redirect()->route('admin.dashboard')->with('success', __('clinics.switch_success'));
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }

    /**
     * Show settings page
     */
    public function settings()
    {
        $clinic = currentClinic();

        $clinicAdmins = null;
        if ($clinic) {
            $clinicAdmins = User::where('clinic_id', $clinic->id)
                ->whereIn('role', ['clinic_admin', 'super_admin'])
                ->orderBy('name')
                ->get();
        }

        return view('admin.settings', compact('clinic', 'clinicAdmins'));
    }

    /**
     * Update admin profile (name + email)
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
        ]);

        return back()->with('success', __('auth.profile_updated'));
    }

    /**
     * Update admin password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $admin = Auth::user();

        if (!Hash::check($request->input('current_password'), $admin->password)) {
            return back()->withErrors(['current_password' => __('auth.password_incorrect')]);
        }

        $admin->update(['password' => Hash::make($request->input('new_password'))]);

        return back()->with('success', __('auth.password_updated'));
    }
}