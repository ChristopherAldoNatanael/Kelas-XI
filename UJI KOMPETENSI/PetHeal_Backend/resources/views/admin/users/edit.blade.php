@extends('layouts.admin')

@section('title', __('admin.edit_user') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.edit_user'))

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <x-admin.page-header
        :title="$user->name"
        :eyebrow="__('admin.eyebrow_management')"
        icon="group"
        :description="__('admin.edit_user_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.users.show', $user->id) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                {{ __('admin.view_detail') }}
            </a>
            <a href="{{ route('admin.users.index') }}" class="admin-btn-ghost">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.panel :title="__('admin.user_info')">
        <form method="POST" action="{{ route('admin.users.update', $user->id) }}" class="space-y-5">
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

            <div>
                <label for="name" class="admin-label">{{ __('admin.full_name') }} <span class="text-rose-500">*</span></label>
                <input id="name" type="text" name="name" required value="{{ old('name', $user->name) }}" class="admin-input">
                @error('name') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="admin-label">{{ __('common.email') }} <span class="text-rose-500">*</span></label>
                <input id="email" type="email" name="email" required value="{{ old('email', $user->email) }}" class="admin-input">
                @error('email') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="phone" class="admin-label">{{ __('admin.phone_number') }}</label>
                <input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                       placeholder="{{ __('admin.phone_placeholder') }}" class="admin-input">
                @error('phone') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="admin-label">{{ __('common.password') }}</label>
                <input id="password" type="password" name="password" autocomplete="new-password"
                       placeholder="{{ __('admin.password_unchanged_hint') }}" class="admin-input">
                <p class="admin-hint">{{ __('admin.new_password_hint') }}</p>
                @error('password') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
                <a href="{{ route('admin.users.show', $user->id) }}" class="admin-btn-ghost">{{ __('common.cancel') }}</a>
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                    {{ __('admin.save_changes') }}
                </button>
            </div>
        </form>
    </x-admin.panel>
</div>
@endsection
