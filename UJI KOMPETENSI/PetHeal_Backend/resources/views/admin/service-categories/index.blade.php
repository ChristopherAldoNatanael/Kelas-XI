@extends('layouts.admin')
@section('title', __('clinics.service_categories') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('clinics.service_categories'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('clinics.service_categories')"
        :eyebrow="__('admin.eyebrow_system')"
        icon="category"
        :description="__('clinics.service_categories_desc')" />

    @if($uncategorizedCount > 0)
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-300">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">rule</span>
            <p>{{ __('clinics.category_uncategorized_hint', ['count' => $uncategorizedCount]) }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr,360px]">
        <x-admin.panel :title="__('clinics.service_categories')" flush>
            @if($categories->isEmpty())
                <x-admin.empty-state
                    :title="__('clinics.category_none')"
                    icon="category"
                    :description="__('clinics.service_categories_desc')" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left">
                        <thead class="admin-table-head">
                            <tr>
                                <th>{{ __('clinics.category_name') }}</th>
                                <th>{{ __('clinics.category_slug') }}</th>
                                <th>{{ __('clinics.category_service_count') }}</th>
                                <th class="text-right">{{ __('clinics.category_edit') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($categories as $category)
                                <tr class="admin-table-row">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $category->icon ?: 'category' }}</span>
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $category->name }}</p>
                                                @if($category->description)
                                                    <p class="truncate text-[11px] text-slate-400">{{ $category->description }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="font-mono text-[11px] text-slate-500">{{ $category->slug }}</td>
                                    <td>
                                        <x-admin.badge :label="(string) $category->services_count" tone="slate" />
                                        @unless($category->is_active)
                                            <x-admin.badge :label="__('admin.inactive_service_hint')" tone="amber" />
                                        @endunless
                                    </td>
                                    <td class="text-right">
                                        <details class="inline-block text-left">
                                            <summary class="admin-btn-ghost cursor-pointer list-none !px-3 !py-1.5 text-xs">{{ __('clinics.category_edit') }}</summary>
                                            <div class="mt-3 w-72 rounded-xl border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                                <form method="POST" action="{{ route('admin.service-categories.update', $category) }}" class="space-y-3">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="text" name="name" value="{{ $category->name }}" required class="admin-input" aria-label="{{ __('clinics.category_name') }}">
                                                    <input type="text" name="icon" value="{{ $category->icon }}" class="admin-input font-mono" aria-label="{{ __('clinics.category_icon') }}" placeholder="vaccines">
                                                    <input type="text" name="description" value="{{ $category->description }}" class="admin-input" aria-label="{{ __('clinics.category_description') }}">
                                                    <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                                        <input type="checkbox" name="is_active" value="1" {{ $category->is_active ? 'checked' : '' }} class="h-3.5 w-3.5 rounded border-slate-300 text-primary">
                                                        {{ __('clinics.category_active') }}
                                                    </label>
                                                    <button type="submit" class="admin-btn-primary w-full !py-2 text-xs">{{ __('admin.save_changes') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.service-categories.destroy', $category) }}" class="mt-2">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="w-full rounded-xl border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-900/60 dark:hover:bg-rose-900/20">{{ __('admin.delete') }}</button>
                                                </form>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                    {{ $categories->links() }}
                </div>
            @endif
        </x-admin.panel>

        <x-admin.panel :title="__('clinics.category_add')" :subtitle="__('clinics.service_categories_desc')">
            <form method="POST" action="{{ route('admin.service-categories.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="category_name" class="admin-label">{{ __('clinics.category_name') }} <span class="text-rose-500">*</span></label>
                    <input id="category_name" type="text" name="name" value="{{ old('name') }}" required class="admin-input @error('name') admin-input-error @enderror">
                    @error('name') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="category_slug" class="admin-label">{{ __('clinics.category_slug') }}</label>
                    <input id="category_slug" type="text" name="slug" value="{{ old('slug') }}" class="admin-input font-mono @error('slug') admin-input-error @enderror" placeholder="vaksinasi">
                    <p class="admin-hint">{{ __('admin.password_unchanged_hint') }}</p>
                    @error('slug') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="category_icon" class="admin-label">{{ __('clinics.category_icon') }}</label>
                    <input id="category_icon" type="text" name="icon" value="{{ old('icon') }}" class="admin-input font-mono" placeholder="vaccines">
                    <p class="admin-hint">{{ __('clinics.category_icon_hint') }}</p>
                </div>
                <div>
                    <label for="category_description" class="admin-label">{{ __('clinics.category_description') }}</label>
                    <textarea id="category_description" name="description" rows="2" class="admin-input">{{ old('description') }}</textarea>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-primary">
                    {{ __('clinics.category_active') }}
                </label>
                <button type="submit" class="admin-btn-primary w-full">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                    {{ __('clinics.category_add') }}
                </button>
            </form>
        </x-admin.panel>
    </div>
</div>
@endsection
