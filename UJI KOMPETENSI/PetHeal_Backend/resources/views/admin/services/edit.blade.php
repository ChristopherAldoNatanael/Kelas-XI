@extends('layouts.admin')

@section('title', 'Edit Service - PetHeal Admin')
@section('header', 'Edit Service')

@section('content')
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div>
            <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Service Information</h3>
            <p class="text-xs text-slate-400 mt-0.5">Update service details and pricing</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.services.update', $service->id) }}" class="p-8">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="space-y-6">
                <div>
                    <label for="name" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Service Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $service->name) }}" required
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">
                </div>

                <div>
                    <label for="description" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label>
                    <textarea name="description" id="description" rows="4"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">{{ old('description', $service->description) }}</textarea>
                </div>

                <div>
                    <label for="price" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Price (Rp)</label>
                    <input type="number" name="price" id="price" value="{{ old('price', $service->price) }}" required min="0" step="0.01"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <label for="category" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category</label>
                    <select name="category" id="category"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">
                        <option value="">Select Category</option>
                        <option value="Medical Checkup" {{ old('category', $service->category) == 'Medical Checkup' ? 'selected' : '' }}>Medical Checkup</option>
                        <option value="Vaccination" {{ old('category', $service->category) == 'Vaccination' ? 'selected' : '' }}>Vaccination</option>
                        <option value="Grooming" {{ old('category', $service->category) == 'Grooming' ? 'selected' : '' }}>Grooming</option>
                        <option value="Surgery" {{ old('category', $service->category) == 'Surgery' ? 'selected' : '' }}>Surgery</option>
                        <option value="Dental" {{ old('category', $service->category) == 'Dental' ? 'selected' : '' }}>Dental</option>
                        <option value="Lab Test" {{ old('category', $service->category) == 'Lab Test' ? 'selected' : '' }}>Lab Test</option>
                        <option value="Others" {{ old('category', $service->category) == 'Others' ? 'selected' : '' }}>Others</option>
                    </select>
                </div>

                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ $service->is_active ? 'checked' : '' }}
                            class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Active</span>
                    </label>
                    <p class="text-[10px] text-slate-400 mt-1">Inactive services won't appear in booking options</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 mt-8 pt-6 border-t border-slate-100 dark:border-slate-800">
            <button type="submit" class="bg-primary hover:bg-emerald-600 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">save</span>
                Update Service
            </button>
            <a href="{{ route('admin.services.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-6 py-2.5 rounded-xl text-sm font-semibold transition-all">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
