@extends('layouts.admin')

@section('title', __('admin.add_service') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.add_service'))

@section('content')
@php
    $categories = ['Medical Checkup', 'Vaccination', 'Grooming', 'Surgery', 'Dental', 'Lab Test', 'Others'];
@endphp

<div class="space-y-6">
    <x-admin.page-header
        :title="__('admin.add_service')"
        :eyebrow="__('admin.eyebrow_management')"
        icon="medical_services"
        :description="__('admin.add_service_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.services.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.panel :title="__('admin.service_information')" :subtitle="__('admin.service_information_desc')">
        <form method="POST" action="{{ route('admin.services.store') }}" class="space-y-8">
            @csrf

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <div class="space-y-5">
                    <div>
                        <label for="name" class="admin-label">{{ __('admin.service_name') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required class="admin-input">
                        @error('name') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="description" class="admin-label">{{ __('common.description') }}</label>
                        <textarea name="description" id="description" rows="4" class="admin-input">{{ old('description') }}</textarea>
                        @error('description') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="price" class="admin-label">{{ __('admin.price') }} <span class="text-rose-500">*</span></label>
                        <input type="number" name="price" id="price" value="{{ old('price') }}" required min="0" step="0.01" class="admin-input">
                        @error('price') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="duration" class="admin-label">{{ __('admin.duration') }}</label>
                        <input type="number" name="duration" id="duration" value="{{ old('duration') }}" min="1" class="admin-input">
                        @error('duration') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="space-y-5">
                    <div>
                        <label for="category" class="admin-label">{{ __('admin.category') }}</label>
                        <select name="category" id="category" class="admin-input">
                            <option value="">{{ __('admin.select_category') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category }}" {{ old('category') === $category ? 'selected' : '' }}>{{ $category }}</option>
                            @endforeach
                        </select>
                        @error('category') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="flex cursor-pointer items-center gap-3">
                            <input type="checkbox" name="is_active" id="is_active" value="1" checked
                                   class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('admin.active_label') }}</span>
                        </label>
                        <p class="admin-hint">{{ __('admin.inactive_service_hint') }}</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6 dark:border-slate-800">
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                    {{ __('admin.save_service') }}
                </button>
                <a href="{{ route('admin.services.index') }}" class="admin-btn-ghost">{{ __('common.cancel') }}</a>
            </div>
        </form>
    </x-admin.panel>
</div>
@endsection
