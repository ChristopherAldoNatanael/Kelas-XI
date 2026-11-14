<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kategori produk bersifat GLOBAL (dipakai semua klinik) sehingga hanya
 * super_admin yang boleh mengubah. clinic_admin hanya membacanya.
 */
class PetShopCategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::withCount('products')
            ->ordered()
            ->paginate(20);

        return view('admin.pet-shop.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'icon' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ]);

        $category = ProductCategory::create([
            'name' => $validated['name'],
            'slug' => ProductCategory::makeUniqueSlug($validated['name']),
            'icon' => $validated['icon'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLog::log('pet_shop.category.created', 'Kategori Pet Shop dibuat: ' . $category->name, $category);

        return back()->with('success', __('petshop.category_created'));
    }

    public function update(Request $request, ProductCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'icon' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update([
            'name' => $validated['name'],
            // Slug lama dipertahankan agar URL publik tidak berubah tanpa
            // alasan; hanya dibuat ulang bila kategori belum punya slug.
            'slug' => $category->slug ?: Str::slug($validated['name']),
            'icon' => $validated['icon'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLog::log('pet_shop.category.updated', 'Kategori Pet Shop diperbarui: ' . $category->name, $category);

        return back()->with('success', __('petshop.category_updated'));
    }

    public function destroy(ProductCategory $category)
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('petshop.category_in_use'),
            ]);
        }

        $category->delete();

        AuditLog::log('pet_shop.category.deleted', 'Kategori Pet Shop dihapus: ' . $category->name, $category);

        return back()->with('success', __('petshop.category_deleted'));
    }
}
