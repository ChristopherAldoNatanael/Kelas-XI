<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kategori layanan STANDAR (global, lintas klinik) — hanya super_admin.
 *
 * Kategori ini adalah dasar perbandingan harga: dua layanan hanya dapat
 * dibandingkan jika `services.service_category_id` sama. Karena global,
 * clinic_admin TIDAK boleh mengubahnya (lihat routes/web.php: middleware
 * `super_admin`).
 */
class ServiceCategoryController extends Controller
{
    public function index()
    {
        $categories = ServiceCategory::withCount([
                'services',
                'services as active_services_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('name')
            ->paginate(30);

        // Layanan yang belum punya kategori standar: tidak bisa dibandingkan.
        $uncategorizedCount = \App\Models\Service::whereNull('service_category_id')->count();

        return view('admin.service-categories.index', compact('categories', 'uncategorizedCount'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:120|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|unique:service_categories,slug',
            'icon' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        if (ServiceCategory::where('slug', $validated['slug'])->exists()) {
            return back()->withErrors(['slug' => __('clinics.slug_taken')])->withInput();
        }
        $validated['is_active'] = $request->boolean('is_active', true);

        $category = ServiceCategory::create($validated);
        $this->flushCaches();
        AuditLog::log('service_category.create', "Created service category {$category->name}", $category);

        return redirect()->route('admin.service-categories.index')
            ->with('success', __('clinics.category_created'));
    }

    public function update(Request $request, ServiceCategory $serviceCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:120|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|unique:service_categories,slug,' . $serviceCategory->id,
            'icon' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?: $serviceCategory->slug;
        $validated['is_active'] = $request->boolean('is_active');

        $serviceCategory->update($validated);
        $this->flushCaches();
        AuditLog::log('service_category.update', "Updated service category {$serviceCategory->name}", $serviceCategory);

        return redirect()->route('admin.service-categories.index')
            ->with('success', __('clinics.category_updated'));
    }

    /**
     * Hapus hanya jika tidak dipakai. Kategori yang sudah dipakai layanan
     * dinonaktifkan saja supaya riwayat & perbandingan tetap konsisten.
     */
    public function destroy(ServiceCategory $serviceCategory)
    {
        $usedBy = $serviceCategory->services()->count();

        if ($usedBy > 0) {
            $serviceCategory->update(['is_active' => false]);
            $this->flushCaches();

            return redirect()->route('admin.service-categories.index')
                ->with('warning', __('clinics.category_in_use', ['count' => $usedBy]));
        }

        $name = $serviceCategory->name;
        $serviceCategory->delete();
        $this->flushCaches();
        AuditLog::log('service_category.delete', "Deleted service category {$name}", null);

        return redirect()->route('admin.service-categories.index')
            ->with('success', __('clinics.category_deleted'));
    }

    private function flushCaches(): void
    {
        Cache::forever('services_version', now()->timestamp);
    }
}
