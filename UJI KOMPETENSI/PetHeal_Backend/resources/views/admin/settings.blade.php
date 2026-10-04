@extends('layouts.admin')
@section('title', 'Settings — ' . ($clinicName ?? 'VCMS') . ' Admin')
@section('header', 'Settings')

@section('content')
<div class="max-w-2xl space-y-6">
    {{-- Clinic Info --}}
    @if($clinic)
    <div class="glass-card rounded-2xl p-8 border border-slate-200/50">
        <h3 class="text-lg font-bold text-slate-900 mb-4">{{ __('auth.clinic_info') }}</h3>
        <div class="flex items-center gap-4 mb-4">
            @if($clinic->logo_url)
                <img src="{{ $clinic->logo_url }}" alt="{{ $clinic->name }}" class="h-12 w-12 rounded-xl object-cover border border-slate-200">
            @else
                <div class="h-12 w-12 rounded-xl flex items-center justify-center text-white font-bold text-lg" style="background-color: {{ $clinic->primary_color }}">
                    {{ strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $clinic->name), 0, 2) ?: 'PH') }}
                </div>
            @endif
            <div>
                <p class="font-semibold text-slate-900">{{ $clinic->name }}</p>
                <p class="text-xs text-slate-500">{{ $clinic->slug }}</p>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <div class="w-4 h-4 rounded-full border border-slate-200" style="background-color: {{ $clinic->primary_color }}"></div>
                <span class="text-xs font-mono text-slate-500">{{ $clinic->primary_color }}</span>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-slate-500">{{ __('common.address') }}:</span> <span class="text-slate-800">{{ $clinic->address }}</span></div>
            <div><span class="text-slate-500">{{ __('common.phone') }}:</span> <span class="text-slate-800">{{ $clinic->phone ?? '—' }}</span></div>
            <div><span class="text-slate-500">{{ __('common.email') }}:</span> <span class="text-slate-800">{{ $clinic->email ?? '—' }}</span></div>
            <div><span class="text-slate-500">{{ __('common.status') }}:</span> <span class="{{ $clinic->is_active ? 'text-emerald-600' : 'text-red-600' }}">{{ $clinic->is_active ? __('common.active') : __('common.inactive') }}</span></div>
        </div>
        @if($isSuperAdmin)
        <div class="mt-4 pt-4 border-t border-slate-100">
            <a href="{{ route('admin.clinics.edit', $clinic) }}" class="text-sm text-primary hover:underline font-medium">{{ __('auth.edit_clinic_details') }}</a>
        </div>
        @endif
    </div>
    @endif

    {{-- Profile --}}
    <div class="glass-card rounded-2xl p-8 border border-slate-200/50">
        <h3 class="text-lg font-bold text-slate-900 mb-6">{{ __('auth.profile') }}</h3>

        @if(session('success') && request()->has('profile_section'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 text-sm">{{ session('success') }}</div>
        @endif
        @if($errors->has('name') || $errors->has('email'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
                @foreach($errors->get('name') as $e) <p>{{ $e }}</p> @endforeach
                @foreach($errors->get('email') as $e) <p>{{ $e }}</p> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.profile') }}?profile_section=1">
            @csrf
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('common.name') }}</label>
                    <input type="text" name="name" value="{{ old('name', Auth::user()->name) }}" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('common.email') }}</label>
                    <input type="email" name="email" value="{{ old('email', Auth::user()->email) }}" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                </div>
                <button type="submit" class="px-6 py-2.5 bg-primary hover:opacity-90 text-white font-semibold rounded-xl transition-all text-sm">{{ __('auth.save_profile') }}</button>
            </div>
        </form>
    </div>

    {{-- Password --}}
    <div class="glass-card rounded-2xl p-8 border border-slate-200/50">
        <h3 class="text-lg font-bold text-slate-900 mb-6">{{ __('auth.change_password') }}</h3>

        @if(session('success') && request()->has('password_section'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 text-sm">{{ session('success') }}</div>
        @endif
        @if($errors->has('current_password') || $errors->has('new_password'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
                @foreach($errors->get('current_password') as $e) <p>{{ $e }}</p> @endforeach
                @foreach($errors->get('new_password') as $e) <p>{{ $e }}</p> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.password') }}?password_section=1">
            @csrf
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('common.current_password') }}</label>
                    <input type="password" name="current_password" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('common.new_password') }}</label>
                    <input type="password" name="new_password" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('common.confirm_password') }}</label>
                    <input type="password" name="new_password_confirmation" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                </div>
                <button type="submit" class="px-6 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-xl transition-all text-sm">{{ __('auth.update_password') }}</button>
            </div>
        </form>
    </div>

    {{-- Admin list for this clinic --}}
    @if(isset($clinicAdmins) && $clinicAdmins->count())
    <div class="glass-card rounded-2xl p-8 border border-slate-200/50">
        <h3 class="text-lg font-bold text-slate-900 mb-4">{{ __('auth.clinic_admins') }}</h3>
        <p class="text-xs text-slate-500 mb-4">{{ __('auth.clinic_admins_desc') }}</p>
        <div class="space-y-3">
            @foreach($clinicAdmins as $adm)
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/30">
                <div class="flex items-center gap-3">
                    <div class="h-8 w-8 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold">
                        {{ substr($adm->name, 0, 1) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $adm->name }}</p>
                        <p class="text-[10px] text-slate-500">{{ $adm->email }}</p>
                    </div>
                </div>
                <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-blue-50 text-blue-600 border-blue-100 uppercase">{{ $adm->role }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection