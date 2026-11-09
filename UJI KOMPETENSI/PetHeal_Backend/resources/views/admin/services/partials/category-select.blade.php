@php
    /*
     | Select kategori layanan standar (service_categories).
     | Kategori adalah dasar perbandingan harga antar klinik: dua layanan hanya
     | dibandingkan bila `service_category_id` sama. Tanpa kategori, layanan
     | tetap tersimpan tetapi ditandai "belum bisa dibandingkan".
     */
    $categories = \App\Models\ServiceCategory::active()->orderBy('name')->get();
    $selected = old('service_category_id', $service->service_category_id ?? null);
@endphp

<div>
    <label for="service_category_id" class="admin-label">{{ __('clinics.service_categories') }}</label>
    <select name="service_category_id" id="service_category_id" class="admin-input">
        <option value="">{{ __('clinics.category_uncategorized') }}</option>
        @foreach($categories as $category)
            <option value="{{ $category->id }}" {{ (string) $selected === (string) $category->id ? 'selected' : '' }}>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
    <p class="admin-hint">{{ __('clinics.service_category_hint') }}</p>
    @error('service_category_id') <p class="admin-error">{{ $message }}</p> @enderror
</div>
