@extends('layouts.admin')

@section('title', __('petshop.products_title') . ' — ' . (currentClinic()?->name ?? 'PetHeal') . ' Admin')
@section('header', __('petshop.products_title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('petshop.products_title')"
        :eyebrow="__('menu.pet_shop')"
        icon="inventory_2"
        :description="__('petshop.products_desc')">
        <x-slot:actions>
            @if(currentClinicId())
                <button type="button" onclick="openPetshopProductImport()" class="admin-btn-secondary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">upload_file</span>
                    {{ __('petshop.import_button') }}
                </button>
                <a href="{{ route('admin.pet-shop.products.create') }}" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                    {{ __('petshop.add_product') }}
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm font-medium text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('petshop_product_import_summary'))
        @php
            $sum = session('petshop_product_import_summary');
            $importErrors = session('petshop_products_import_error_report', []);
        @endphp
        <x-admin.panel
            :title="__('petshop.import_result_title')"
            :subtitle="__('petshop.import_result_desc')">
            <x-slot:actions>
                @if(session('petshop_product_import_has_errors'))
                    <a href="{{ route('admin.pet-shop.products.import.error-report', ['format' => 'csv']) }}"
                       class="admin-btn-secondary !px-2.5 !py-1.5 text-[11px]">
                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">download</span>
                        {{ __('petshop.import_error_report') }} (CSV)
                    </a>
                    <a href="{{ route('admin.pet-shop.products.import.error-report', ['format' => 'xlsx']) }}"
                       class="admin-btn-secondary !px-2.5 !py-1.5 text-[11px]">
                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">download</span>
                        Excel
                    </a>
                @endif
            </x-slot:actions>

            <dl class="grid grid-cols-2 gap-x-5 gap-y-4 sm:grid-cols-3 lg:grid-cols-6">
                @foreach([
                    [__('petshop.import_rows'), $sum['total'] ?? 0, 'text-slate-900 dark:text-white'],
                    [__('petshop.import_products_created'), $sum['products_created'] ?? 0, 'text-slate-900 dark:text-white'],
                    [__('petshop.import_new_variants'), $sum['success'] ?? 0, 'text-emerald-600 dark:text-emerald-400'],
                    [__('petshop.import_updated_variants'), $sum['updated'] ?? 0, 'text-blue-600 dark:text-blue-400'],
                    [__('petshop.import_skipped'), $sum['skipped'] ?? 0, 'text-slate-500 dark:text-slate-400'],
                    [__('petshop.import_failed_rows'), $sum['failed'] ?? 0, ($sum['failed'] ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400'],
                ] as [$label, $value, $tone])
                    <div class="min-w-0">
                        <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">{{ $label }}</dt>
                        <dd class="mt-1 text-xl font-bold tabular-nums {{ $tone }}">{{ number_format((int) $value, 0, ',', '.') }}</dd>
                    </div>
                @endforeach
            </dl>

            @if(($sum['categories_created'] ?? 0) > 0)
                <p class="mt-4 flex items-start gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <span class="material-symbols-outlined text-[16px] text-emerald-600 dark:text-emerald-400" aria-hidden="true">category</span>
                    <span>{{ __('petshop.import_categories_created', ['names' => implode(', ', $sum['category_names_created'] ?? [])]) }}</span>
                </p>
            @endif

            @if(!empty($importErrors))
                <div class="mt-4 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('petshop.import_error_preview_title') }}</p>
                    <ul class="mt-2 space-y-1.5">
                        @foreach(array_slice($importErrors, 0, 3) as $row)
                            <li class="flex gap-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                <span class="shrink-0 font-semibold tabular-nums text-amber-600 dark:text-amber-400">#{{ $row['row_number'] ?? '?' }}</span>
                                <span class="min-w-0">{{ $row['error_message'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('petshop.import_error_report_hint') }}</p>
                </div>
            @endif
        </x-admin.panel>
    @endif

    <x-admin.panel flush>
        <form method="GET" class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center dark:border-slate-800">
            <div class="relative flex-1">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-[18px] text-slate-400" aria-hidden="true">search</span>
                <input type="search" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('petshop.search_product') }}" class="admin-input pl-10">
            </div>
            <select name="category" class="admin-input sm:w-48">
                <option value="">{{ __('petshop.all_categories') }}</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="status" class="admin-input sm:w-40">
                <option value="">{{ __('petshop.all_statuses') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('petshop.status_active') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('petshop.status_inactive') }}</option>
            </select>
            <button type="submit" class="admin-btn-secondary">{{ __('petshop.search') }}</button>
            @if(request()->hasAny(['search','category','status']))
                <a href="{{ route('admin.pet-shop.products.index') }}" class="admin-btn-ghost">{{ __('petshop.reset') }}</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('petshop.product_name') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.category') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.variants_count') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.price_range') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.stock_total') }}</th>
                        <th class="px-5 py-3">{{ __('common.status') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('petshop.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($products as $product)
                        @php
                            $activeVariants = $product->variants->where('is_active', true);
                            $prices = $activeVariants->map(fn ($v) => $v->effectivePrice())->filter();
                            $stock = $activeVariants->sum(fn ($v) => $v->availableStock());
                        @endphp
                        <tr class="admin-table-row">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                                        @if($product->image_url)
                                            <img src="{{ $product->image_url }}" alt="" class="h-full w-full object-cover">
                                        @else
                                            <span class="material-symbols-outlined text-[20px] text-slate-400" aria-hidden="true">shopping_bag</span>
                                        @endif
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $product->name }}</p>
                                        <p class="truncate text-[11px] text-slate-400">{{ $product->brand ?: '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge :label="$product->category?->name ?? '—'" tone="slate" />
                            </td>
                            <td class="px-5 py-3 text-sm tabular-nums text-slate-500 dark:text-slate-400">{{ $product->variants->count() }}</td>
                            <td class="px-5 py-3 text-sm tabular-nums text-slate-700 dark:text-slate-300">
                                @if($prices->isEmpty())
                                    —
                                @elseif($prices->min() === $prices->max())
                                    Rp {{ number_format($prices->min(), 0, ',', '.') }}
                                @else
                                    Rp {{ number_format($prices->min(), 0, ',', '.') }} – {{ number_format($prices->max(), 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-sm font-semibold tabular-nums {{ $stock > 0 ? 'text-slate-700 dark:text-slate-300' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ number_format($stock, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="$product->is_active ? __('petshop.status_active') : __('petshop.status_inactive')"
                                    :tone="$product->is_active ? 'primary' : 'slate'"
                                    dot />
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('admin.pet-shop.products.show', $product) }}" class="admin-btn-ghost !p-2" title="{{ __('petshop.detail') }}">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                                    </a>
                                    <a href="{{ route('admin.pet-shop.products.edit', $product) }}" class="admin-btn-ghost !p-2" title="{{ __('petshop.edit') }}">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                                    </a>
                                    <form method="POST" action="{{ route('admin.pet-shop.products.toggle', $product) }}">
                                        @csrf
                                        <button type="submit" class="admin-btn-ghost !p-2" title="{{ $product->is_active ? __('petshop.status_inactive') : __('petshop.status_active') }}">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $product->is_active ? 'toggle_on' : 'toggle_off' }}</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state
                                    icon="inventory_2"
                                    :title="request()->hasAny(['search','category','status']) ? __('petshop.no_products_filter') : __('petshop.no_products')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="border-t border-slate-100 px-5 py-3.5 dark:border-slate-800">{{ $products->links() }}</div>
        @endif
    </x-admin.panel>
</div>

@include('admin.pet-shop.products.partials.import-modal')
@endsection
