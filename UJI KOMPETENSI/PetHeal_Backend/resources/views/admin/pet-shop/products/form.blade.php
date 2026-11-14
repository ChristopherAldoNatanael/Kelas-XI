@extends('layouts.admin')

@php
    $isEdit = $product->exists;
    $rows = old('variants', $variants->map(fn ($variant) => [
        'id' => $variant->id,
        'sku' => $variant->sku,
        'name' => $variant->name,
        'price' => $variant->price,
        'discount_price' => $variant->discount_price,
        'stock_on_hand' => $variant->stock_on_hand,
        'low_stock_threshold' => $variant->low_stock_threshold,
        'is_active' => $variant->is_active,
    ])->values()->all());

    if (empty($rows)) {
        $rows = [[
            'id' => null,
            'sku' => '',
            'name' => '',
            'price' => '',
            'discount_price' => '',
            'stock_on_hand' => 0,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]];
    }
@endphp

@section('title', ($isEdit ? __('petshop.edit_product') : __('petshop.add_product')) . ' — ' . (currentClinic()?->name ?? 'PetHeal') . ' Admin')
@section('header', $isEdit ? __('petshop.edit_product') : __('petshop.add_product'))

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <x-admin.page-header
        :title="$isEdit ? __('petshop.edit_product') : __('petshop.add_product')"
        :eyebrow="__('menu.pet_shop')"
        icon="inventory_2"
        :description="__('petshop.products_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.pet-shop.products.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('petshop.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $isEdit ? route('admin.pet-shop.products.update', $product) : route('admin.pet-shop.products.store') }}"
          enctype="multipart/form-data" class="space-y-5">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <x-admin.panel :title="__('petshop.product_detail')" :subtitle="__('petshop.products_desc')">
            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="admin-label" for="name">{{ __('petshop.product_name') }}</label>
                    <input id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="160" class="admin-input">
                </div>

                <div>
                    <label class="admin-label" for="product_category_id">{{ __('petshop.category') }}</label>
                    <select id="product_category_id" name="product_category_id" required class="admin-input">
                        <option value="">—</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('product_category_id', $product->product_category_id) === (string) $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="admin-label" for="brand">{{ __('petshop.brand') }} <span class="font-normal text-slate-400">({{ __('petshop.optional') }})</span></label>
                    <input id="brand" name="brand" value="{{ old('brand', $product->brand) }}" maxlength="120" class="admin-input">
                </div>

                <div class="md:col-span-2">
                    <label class="admin-label" for="description">{{ __('petshop.description') }}</label>
                    <textarea id="description" name="description" rows="4" maxlength="5000" class="admin-input">{{ old('description', $product->description) }}</textarea>
                </div>

                <div>
                    <label class="admin-label" for="image">{{ __('petshop.image') }}</label>
                    <input id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="admin-input file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold dark:file:bg-slate-800">
                    <p class="mt-1.5 text-[11px] text-slate-400">
                        {{ $isEdit ? __('petshop.image_change_hint') : __('petshop.image_hint') }}
                    </p>
                    @if($isEdit && $product->image_url)
                        <img src="{{ $product->image_url }}" alt="" class="mt-3 h-24 w-24 rounded-xl border border-slate-200 object-cover dark:border-slate-700">
                    @endif
                </div>

                <div class="flex flex-col justify-end gap-3">
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="is_active" value="1"
                               @checked(old('is_active', $product->is_active ?? true))
                               class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        {{ __('petshop.status_active') }}
                    </label>
                </div>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('petshop.variants_section')" :subtitle="__('petshop.variants_hint')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[840px] text-left">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-3 py-2">{{ __('petshop.sku') }}</th>
                            <th class="px-3 py-2">{{ __('petshop.variant_name') }}</th>
                            <th class="px-3 py-2">{{ __('petshop.price') }}</th>
                            <th class="px-3 py-2">{{ __('petshop.discount_price') }}</th>
                            <th class="px-3 py-2">{{ __('petshop.stock_on_hand') }}</th>
                            <th class="px-3 py-2">{{ __('petshop.low_stock_threshold') }}</th>
                            <th class="px-3 py-2 text-center">{{ __('petshop.status_active') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="variant-rows" class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @foreach($rows as $index => $row)
                            <tr class="variant-row">
                                <td class="px-3 py-2">
                                    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
                                    <input name="variants[{{ $index }}][sku]" value="{{ $row['sku'] ?? '' }}" required maxlength="64"
                                           class="admin-input sku-input uppercase" placeholder="RC-400">
                                </td>
                                <td class="px-3 py-2">
                                    <input name="variants[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" required maxlength="120"
                                           class="admin-input" placeholder="400 gram">
                                </td>
                                <td class="px-3 py-2">
                                    <input name="variants[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}" required type="number" min="0" step="1"
                                           class="admin-input" placeholder="45000">
                                </td>
                                <td class="px-3 py-2">
                                    <input name="variants[{{ $index }}][discount_price]" value="{{ $row['discount_price'] ?? '' }}" type="number" min="0" step="1"
                                           class="admin-input" placeholder="{{ __('petshop.optional') }}">
                                </td>
                                <td class="px-3 py-2">
                                    <input name="variants[{{ $index }}][stock_on_hand]" value="{{ $row['stock_on_hand'] ?? 0 }}" required type="number" min="0" step="1"
                                           class="admin-input">
                                </td>
                                <td class="px-3 py-2">
                                    <input name="variants[{{ $index }}][low_stock_threshold]" value="{{ $row['low_stock_threshold'] ?? 5 }}" type="number" min="0" step="1"
                                           class="admin-input">
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <input type="checkbox" name="variants[{{ $index }}][is_active]" value="1"
                                           @checked($row['is_active'] ?? true)
                                           class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="admin-btn-ghost !p-2 remove-variant" title="{{ __('petshop.remove_variant') }}">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <template id="variant-template">
                <tr class="variant-row">
                    <td class="px-3 py-2">
                        <input type="hidden" data-name="id" value="">
                        <input data-name="sku" required maxlength="64" class="admin-input sku-input uppercase" placeholder="SKU">
                    </td>
                    <td class="px-3 py-2"><input data-name="name" required maxlength="120" class="admin-input" placeholder="Nama varian"></td>
                    <td class="px-3 py-2"><input data-name="price" required type="number" min="0" step="1" class="admin-input" placeholder="0"></td>
                    <td class="px-3 py-2"><input data-name="discount_price" type="number" min="0" step="1" class="admin-input" placeholder="{{ __('petshop.optional') }}"></td>
                    <td class="px-3 py-2"><input data-name="stock_on_hand" required type="number" min="0" step="1" value="0" class="admin-input"></td>
                    <td class="px-3 py-2"><input data-name="low_stock_threshold" type="number" min="0" step="1" value="5" class="admin-input"></td>
                    <td class="px-3 py-2 text-center"><input type="checkbox" data-name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"></td>
                    <td class="px-3 py-2 text-right">
                        <button type="button" class="admin-btn-ghost !p-2 remove-variant" title="{{ __('petshop.remove_variant') }}">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                        </button>
                    </td>
                </tr>
            </template>

            <div class="mt-4 flex items-center justify-between gap-3">
                <button type="button" id="add-variant" class="admin-btn-secondary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                    {{ __('petshop.add_variant') }}
                </button>
                <p class="text-[11px] text-slate-400">{{ __('petshop.variants_hint') }}</p>
            </div>
        </x-admin.panel>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('admin.pet-shop.products.index') }}" class="admin-btn-secondary">{{ __('petshop.cancel') }}</a>
            <button type="submit" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                {{ __('petshop.save') }}
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const rows = document.getElementById('variant-rows');
        const template = document.getElementById('variant-template');
        const addButton = document.getElementById('add-variant');

        function reindex() {
            rows.querySelectorAll('.variant-row').forEach(function (row, index) {
                row.querySelectorAll('[name], [data-name]').forEach(function (input) {
                    const key = input.dataset.name || (input.name.match(/\[(.*?)\]$/) || [])[1];
                    if (!key) return;
                    if (input.dataset.name) {
                        input.name = 'variants[' + index + '][' + key + ']';
                    } else {
                        input.name = input.name.replace(/variants\[\d+\]/, 'variants[' + index + ']');
                    }
                });
            });
        }

        addButton?.addEventListener('click', function () {
            const fragment = template.content.cloneNode(true);
            rows.appendChild(fragment);
            reindex();
        });

        rows.addEventListener('click', function (event) {
            const button = event.target.closest('.remove-variant');
            if (!button) return;
            if (rows.querySelectorAll('.variant-row').length <= 1) return;
            button.closest('.variant-row').remove();
            reindex();
        });

        rows.addEventListener('input', function (event) {
            if (event.target.classList.contains('sku-input')) {
                event.target.value = event.target.value.toUpperCase();
            }
        });
    })();
</script>
@endpush
