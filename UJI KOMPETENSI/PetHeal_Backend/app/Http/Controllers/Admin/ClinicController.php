<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use App\Services\ImageService;

class ClinicController extends Controller
{
    public function index()
    {
        $clinics = Clinic::withCount(['doctors', 'services', 'bookings', 'users'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.clinics.index', compact('clinics'));
    }

    public function create()
    {
        return view('admin.clinics.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:clinics,slug|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data = collect($validated)->only(['name', 'slug', 'address', 'phone', 'email', 'primary_color', 'description'])->toArray();
        // PHASE 5 (SA-05): absent checkbox = false. Previously defaulted to
        // true, so unchecking "Aktif" could never deactivate via this form.
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('logo')) {
            $fileName = time() . '_' . uniqid() . '.jpg';
            $data['logo_path'] = ImageService::process(
                $request->file('logo'),
                'clinics/' . $fileName,
                400,
                85
            );
        }

        $clinic = Clinic::create($data);

        // Auto-create default admin for the new clinic.
        // PHASE 5 (SA-04): random initial password instead of predictable
        // 'admin123' (slug is public), and guard the email collision that
        // previously ended in a 500 on users.email unique.
        $defaultEmail = 'admin@' . $clinic->slug . '.com';
        if (!User::where('email', $defaultEmail)->exists()) {
            $defaultPassword = Str::random(12);
            User::create([
                'name' => 'Admin ' . $clinic->name,
                'email' => $defaultEmail,
                'password' => Hash::make($defaultPassword),
                'role' => 'clinic_admin',
                'clinic_id' => $clinic->id,
                'phone' => $clinic->phone,
            ]);

            return redirect()->route('admin.clinics.index')
                ->with('success', __('clinics.created_success'))
                ->with('new_clinic_credentials', [
                    'email' => $defaultEmail,
                    'password' => $defaultPassword,
                    'clinic' => $clinic->name,
                ]);
        }

        return redirect()->route('admin.clinics.index')
            ->with('success', __('clinics.created_success'))
            ->with('error', __('clinics.admin_email_taken', ['email' => $defaultEmail]));
    }

    public function edit(Clinic $clinic)
    {
        return view('admin.clinics.edit', compact('clinic'));
    }

    public function update(Request $request, Clinic $clinic)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:clinics,slug,' . $clinic->id . '|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data = collect($validated)->only(['name', 'slug', 'address', 'phone', 'email', 'primary_color', 'description'])->toArray();
        // PHASE 5 (SA-05): see store().
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($clinic->logo_path && !filter_var($clinic->logo_path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($clinic->logo_path);
            }
            $fileName = time() . '_' . uniqid() . '.jpg';
            $data['logo_path'] = ImageService::process(
                $request->file('logo'),
                'clinics/' . $fileName,
                400,
                85
            );
        }

        $clinic->update($data);

        return redirect()->route('admin.clinics.index')->with('success', __('clinics.updated_success'));
    }

    public function toggleActive(Clinic $clinic)
    {
        $clinic->update(['is_active' => !$clinic->is_active]);
        $status = $clinic->is_active ? __('clinics.activated') : __('clinics.deactivated');
        return redirect()->back()->with('success', __('clinics.toggle_success', ['name' => $clinic->name, 'status' => $status]));
    }

    /**
     * Show the editable profile of the current user's own clinic.
     *
     * Any admin role with a clinic context (clinic_admin, legacy admin,
     * super_admin with a selected clinic) may open this page.
     */
    public function profile()
    {
        $clinic = currentClinic();

        if (!$clinic) {
            // super_admin in system-overview mode has no clinic context.
            return redirect()->route('admin.clinics.index')
                ->with('warning', __('clinics.select_clinic_first'));
        }

        return view('admin.clinics.profile', compact('clinic'));
    }

    /**
     * Update the current user's own clinic (limited fields).
     *
     * Deliberately excludes `slug` (used by the Android app as the tenant
     * identity via X-Clinic-Slug — changing it orphans mobile sessions)
     * and `is_active` (deactivating locks out the whole clinic including
     * the editor). Both stay super_admin-only via update().
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'super_admin') {
            $clinic = currentClinic();

            if (!$clinic) {
                return redirect()->route('admin.clinics.index')
                    ->with('warning', __('clinics.select_clinic_first'));
            }
        } else {
            // Tenant roles may only touch their own clinic — resolved from
            // the authenticated user, never from URL/input parameters.
            $clinic = Clinic::findOrFail(requireTenantClinicId($user));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string',
        ]);

        $data = collect($validated)->only(['name', 'address', 'phone', 'email', 'primary_color', 'description'])->toArray();

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($clinic->logo_path && !filter_var($clinic->logo_path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($clinic->logo_path);
            }
            $fileName = time() . '_' . uniqid() . '.jpg';
            $data['logo_path'] = ImageService::process(
                $request->file('logo'),
                'clinics/' . $fileName,
                400,
                85
            );
        }

        $clinic->update($data);

        return redirect()->route('admin.clinic-profile')->with('success', __('clinics.updated_success'));
    }
}