@extends('layouts.admin')

@section('title', __('petshop.categories_title') . ' — Super Admin')
@section('header', __('petshop.categories_title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('petshop.categories_title')"
        :eyebrow="__('menu.pet_shop')"
        icon="category"
        :description="__('petshop.categories_desc')" />

    @if(session('success'))
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
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

    <div class="grid gap-4 lg:grid-cols-3">
        <x-admin.panel :title="__('petshop.add_category')" class="lg:col-span-1">
            <form method="POST" action="{{ route('admin.pet-shop.categories.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="admin-label" for="new-name">{{ __('petshop.category_name') }}</label>
                    <input id="new-name" name="name" required maxlength="120" class="admin-input" value="{{ old('name') }}">
                </div>
                <div>
                    <label class="admin-label" for="new-icon">{{ __('petshop.category_icon') }}</label>
                    <input id="new-icon" name="icon" maxlength="80" class="admin-input" placeholder="pets" value="{{ old('icon') }}">
                </div>
                <div>
                    <label class="admin-label" for="new-sort">{{ __('petshop.sort_order') }}</label>
                    <input id="new-sort" type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 0) }}" class="admin-input">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    {{ __('petshop.status_active') }}
                </label>
                <button type="submit" class="admin-btn-primary w-full">{{ __('petshop.save') }}</button>
            </form>
        </x-admin.panel>

        <x-admin.panel :title="__('petshop.categories_title')" :subtitle="__('petshop.categories_desc')" class="lg:col-span-2" flush>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($categories as $category)
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                <span class="material-symbols-outlined text-[19px]" aria-hidden="true">{{ $category->icon ?: 'category' }}</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $category->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $category->products_count }} {{ __('petshop.product_count') }} · #{{ $category->sort_order }}</p>
                            </div>
                            <x-admin.badge
                                :label="$category->is_active ? __('petshop.status_active') : __('petshop.status_inactive')"
                                :tone="$category->is_active ? 'primary' : 'slate'" />
                            <span class="material-symbols-outlined text-[18px] text-slate-400 transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span>
                        </summary>

                        <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4 dark:border-slate-800 dark:bg-slate-800/30">
                            <form method="POST" action="{{ route('admin.pet-shop.categories.update', $category) }}" class="grid gap-3 sm:grid-cols-2">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="admin-label">{{ __('petshop.category_name') }}</label>
                                    <input name="name" value="{{ $category->name }}" required maxlength="120" class="admin-input">
                                </div>
                                <div>
                                    <label class="admin-label">{{ __('petshop.category_icon') }}</label>
                                    <input name="icon" value="{{ $category->icon }}" maxlength="80" class="admin-input">
                                </div>
                                <div>
                                    <label class="admin-label">{{ __('petshop.sort_order') }}</label>
                                    <input type="number" name="sort_order" value="{{ $category->sort_order }}" min="0" max="9999" class="admin-input">
                                </div>
                                <div class="flex items-end gap-2">
                                    <label class="flex items-center gap-2 pb-2 text-sm text-slate-600 dark:text-slate-300">
                                        <input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        {{ __('petshop.status_active') }}
                                    </label>
                                </div>
                                <div class="sm:col-span-2 flex items-center justify-between gap-2">
                                    <button type="submit" class="admin-btn-primary">{{ __('petshop.save') }}</button>
                            </form>
                                    <form method="POST" action="{{ route('admin.pet-shop.categories.destroy', $category) }}"
                                          onsubmit="return confirm('{{ __('petshop.delete') }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-btn-danger" @disabled($category->products_count > 0)>
                                            {{ __('petshop.delete') }}
                                        </button>
                                    </form>
                                </div>
                        </div>
                    </details>
                @empty
                    <x-admin.empty-state icon="category" :title="__('petshop.no_categories')" />
                @endforelse
            </div>
            @if($categories->hasPages())
                <div class="border-t border-slate-100 px-5 py-3.5 dark:border-slate-800">{{ $categories->links() }}</div>
            @endif
        </x-admin.panel>
    </div>
</div>
@endsection
