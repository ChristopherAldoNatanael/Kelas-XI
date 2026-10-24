@extends('layouts.admin')

@section('title', __('admin.edit') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.edit') . ' · ' . $doctor->name)

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="$doctor->name"
        :eyebrow="__('admin.eyebrow_management')"
        icon="medical_services"
        :description="__('admin.edit_doctor_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.doctors.show', $doctor->id) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                {{ __('admin.view_detail') }}
            </a>
            <a href="{{ route('admin.doctors.index') }}" class="admin-btn-ghost">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.panel :title="__('admin.doctor_information')" :subtitle="__('admin.doctor_information_desc')">
        <form method="POST" action="{{ route('admin.doctors.update', $doctor->id) }}" enctype="multipart/form-data" class="space-y-8">
            @csrf
            @method('PUT')

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
                        <label for="name" class="admin-label">{{ __('admin.full_name') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $doctor->name) }}" required class="admin-input">
                        @error('name') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="email" class="admin-label">{{ __('admin.email_address') }}</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $doctor->email) }}" class="admin-input" placeholder="{{ __('admin.email_placeholder') }}">
                        @error('email') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="phone" class="admin-label">{{ __('admin.phone_number') }}</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone', $doctor->phone) }}" class="admin-input" placeholder="{{ __('admin.phone_placeholder') }}">
                        @error('phone') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="specialization" class="admin-label">{{ __('admin.specialization') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="specialization" id="specialization" value="{{ old('specialization', $doctor->specialization) }}" required
                               placeholder="{{ __('admin.specialization_placeholder') }}" class="admin-input">
                        @error('specialization') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="space-y-5">
                    <div>
                        <label for="photo" class="admin-label">{{ __('admin.doctor_photo') }}</label>
                        <div class="flex items-center gap-4">
                            <div class="admin-photo-slot">
                                @if($doctor->photo_url)
                                    <img id="photo-preview" class="h-full w-full object-cover" src="{{ $doctor->photo_url }}?t={{ time() }}" alt="{{ $doctor->name }}">
                                @else
                                    <img id="photo-preview" class="hidden h-full w-full object-cover" alt="">
                                    <span id="photo-placeholder" class="admin-photo-slot__placeholder">
                                        <span class="material-symbols-outlined" aria-hidden="true">person</span>
                                    </span>
                                @endif
                            </div>
                            <div class="flex-1">
                                <input type="file" name="photo" id="photo" accept="image/*"
                                       class="w-full text-sm text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-emerald-700 transition hover:file:bg-emerald-100 dark:file:bg-emerald-900/30 dark:file:text-emerald-300">
                                <p class="admin-hint">{{ __('admin.photo_keep_hint') }}</p>
                                @if($doctor->photo || $doctor->photo_url)
                                    <p class="mt-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">✓ {{ __('admin.photo_stored') }}</p>
                                @endif
                                @error('photo') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <x-admin.day-picker :selected="old('available_days', $doctor->available_days ?? [])" />

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="start_time" class="admin-label">{{ __('admin.start_time') }} <span class="text-rose-500">*</span></label>
                            <input type="time" name="start_time" id="start_time" value="{{ old('start_time', $doctor->start_time) }}" required class="admin-input">
                            @error('start_time') <p class="admin-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="end_time" class="admin-label">{{ __('admin.end_time') }} <span class="text-rose-500">*</span></label>
                            <input type="time" name="end_time" id="end_time" value="{{ old('end_time', $doctor->end_time) }}" required class="admin-input">
                            @error('end_time') <p class="admin-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="flex cursor-pointer items-center gap-3">
                            <input type="checkbox" name="is_active" id="is_active" value="1" {{ $doctor->is_active ? 'checked' : '' }}
                                   class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('admin.active_label') }}</span>
                        </label>
                        <p class="admin-hint">{{ __('admin.inactive_doctor_hint') }}</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6 dark:border-slate-800">
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                    {{ __('admin.update_doctor') }}
                </button>
                <a href="{{ route('admin.doctors.index') }}" class="admin-btn-ghost">{{ __('common.cancel') }}</a>
            </div>
        </form>
    </x-admin.panel>
</div>

<script>
    (function () {
        const input = document.getElementById('photo');
        const preview = document.getElementById('photo-preview');
        const placeholder = document.getElementById('photo-placeholder');

        input.addEventListener('change', function (event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        });
    })();
</script>
@endsection
