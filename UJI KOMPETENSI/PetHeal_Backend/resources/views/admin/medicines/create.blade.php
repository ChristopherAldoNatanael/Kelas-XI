@extends('layouts.admin')

@section('title', __('admin.add_medicine') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.add_medicine'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('admin.add_medicine')"
        :eyebrow="__('admin.eyebrow_management')"
        icon="medication"
        :description="__('admin.add_medicine_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.medicines.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.panel :title="__('admin.medicine_information')" :subtitle="__('admin.medicine_information_desc')">
        <form method="POST" action="{{ route('admin.medicines.store') }}" class="space-y-8">
            @csrf

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <div class="space-y-5">
                    <div>
                        <label for="name" class="admin-label">{{ __('admin.medicine_name') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required class="admin-input"
                               placeholder="Amoxicillin 250 mg">
                        @error('name') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="unit" class="admin-label">{{ __('admin.medicine_unit') }} <span class="text-rose-500">*</span></label>
                            <input type="text" name="unit" id="unit" value="{{ old('unit', 'tablet') }}" required class="admin-input">
                            @error('unit') <p class="admin-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="form" class="admin-label">{{ __('admin.medicine_form') }}</label>
                            <input type="text" name="form" id="form" value="{{ old('form') }}" class="admin-input"
                                   placeholder="tablet / sirup / injeksi">
                            @error('form') <p class="admin-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="initial_stock" class="admin-label">{{ __('admin.medicine_initial_stock') }}</label>
                        <input type="number" name="initial_stock" id="initial_stock" min="0" step="0.01"
                               value="{{ old('initial_stock', 0) }}" class="admin-input">
                        <p class="admin-hint">{{ __('admin.medicine_initial_stock_hint') }}</p>
                        @error('initial_stock') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="min_stock" class="admin-label">{{ __('admin.medicine_min_stock') }}</label>
                        <input type="number" name="min_stock" id="min_stock" min="0" step="0.01"
                               value="{{ old('min_stock', 0) }}" class="admin-input">
                        <p class="admin-hint">{{ __('admin.medicine_min_stock_hint') }}</p>
                        @error('min_stock') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="space-y-5">
                    <div>
                        <label for="price" class="admin-label">{{ __('admin.medicine_price') }}</label>
                        <input type="number" name="price" id="price" min="0" step="0.01"
                               value="{{ old('price', 0) }}" class="admin-input">
                        <p class="admin-hint">{{ __('admin.medicine_price_hint') }}</p>
                        @error('price') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="expiry_date" class="admin-label">{{ __('admin.medicine_expiry') }}</label>
                        <input type="date" name="expiry_date" id="expiry_date"
                               value="{{ old('expiry_date') }}" class="admin-input">
                        <p class="admin-hint">{{ __('admin.medicine_expiry_hint') }}</p>
                        @error('expiry_date') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="manufacturer" class="admin-label">{{ __('admin.medicine_manufacturer') }}</label>
                        <input type="text" name="manufacturer" id="manufacturer" value="{{ old('manufacturer') }}" class="admin-input">
                        @error('manufacturer') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="notes" class="admin-label">{{ __('admin.notes') }}</label>
                        <textarea name="notes" id="notes" rows="3" class="admin-input">{{ old('notes') }}</textarea>
                        @error('notes') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))
                               class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('admin.medicine_active') }}</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6 dark:border-slate-800">
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                    {{ __('admin.save_medicine') }}
                </button>
                <a href="{{ route('admin.medicines.index') }}" class="admin-btn-ghost">{{ __('common.cancel') }}</a>
            </div>
        </form>
    </x-admin.panel>
</div>
@endsection
