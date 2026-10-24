@php
    $me = auth()->user();
    $initial = \Illuminate\Support\Str::substr($me->name ?? 'A', 0, 1);
    $roleLabel = match ($me->role ?? '') {
        'super_admin' => __('admin.role_label_super_admin'),
        'clinic_admin' => __('admin.role_label_clinic_admin'),
        'admin' => __('admin.role_label_admin'),
        default => ucfirst((string) ($me->role ?? __('admin.role_label_admin'))),
    };
@endphp

<x-admin.panel :title="__('admin.account_info_title')" :subtitle="__('admin.account_info_desc')">
    <div class="mb-6 flex items-center gap-4 rounded-xl border border-slate-200/70 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/40">
        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-primary text-lg font-semibold text-white" aria-hidden="true">
            {{ $initial }}
        </div>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $me->name }}</p>
            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $me->email }}</p>
        </div>
        <span class="ml-auto flex-shrink-0 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
            {{ $roleLabel }}
        </span>
    </div>

    <form method="POST" action="{{ route('admin.settings.profile') }}" class="space-y-5">
        @csrf
        <div>
            <label for="settings-name" class="admin-label">{{ __('auth.full_name') }}</label>
            <input id="settings-name" type="text" name="name" value="{{ old('name', $me->name) }}" required autocomplete="name"
                   class="admin-input @error('name') admin-input-error @enderror">
            @error('name') <p class="admin-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="settings-email" class="admin-label">{{ __('auth.email') }}</label>
            <input id="settings-email" type="email" name="email" value="{{ old('email', $me->email) }}" required autocomplete="email"
                   class="admin-input @error('email') admin-input-error @enderror">
            <p class="mt-1.5 text-[11px] text-slate-400">{{ __('admin.email_login_hint') }}</p>
            @error('email') <p class="admin-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5 dark:border-slate-800">
            <button type="submit" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                {{ __('admin.save_changes') }}
            </button>
            <a href="{{ route('admin.settings', ['tab' => 'keamanan']) }}" class="text-sm font-medium text-slate-500 transition hover:text-primary dark:text-slate-400">
                {{ __('admin.change_password_link') }}
            </a>
        </div>
    </form>
</x-admin.panel>
