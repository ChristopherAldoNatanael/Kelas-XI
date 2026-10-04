@extends('layouts.admin')
@section('title', __('dashboard.system_overview') . ' — ' . ($clinicName ?? 'VCMS') . ' Admin')
@section('header', __('dashboard.system_overview'))

@section('content')
<!-- System Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="glass-card p-6 rounded-2xl flex items-center gap-4">
        <div class="w-10 h-10 bg-emerald-500/10 text-emerald-600 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-[20px]">local_hospital</span>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('dashboard.total_clinics') }}</p>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $totalClinics }} <span class="text-xs font-normal text-slate-400">({{ $activeClinics }} {{ __('dashboard.active') }})</span></h3>
        </div>
    </div>
    <div class="glass-card p-6 rounded-2xl flex items-center gap-4">
        <div class="w-10 h-10 bg-blue-500/10 text-blue-600 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-[20px]">medical_services</span>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('dashboard.total_doctors') }}</p>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $totalDoctors }}</h3>
        </div>
    </div>
    <div class="glass-card p-6 rounded-2xl flex items-center gap-4">
        <div class="w-10 h-10 bg-indigo-500/10 text-indigo-600 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-[20px]">group</span>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('dashboard.total_patients') }}</p>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $totalUsers }}</h3>
        </div>
    </div>
    <div class="glass-card p-6 rounded-2xl flex items-center gap-4">
        <div class="w-10 h-10 bg-purple-500/10 text-purple-600 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-[20px]">calendar_month</span>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('dashboard.total_bookings') }}</p>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $totalBookings }}</h3>
        </div>
    </div>
</div>

<!-- Quick Actions -->
@if($pendingJoinRequests > 0)
<div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-5 mb-8 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <span class="material-symbols-outlined text-yellow-600 text-xl">person_add</span>
        <div>
            <p class="text-sm font-semibold text-yellow-800">{{ $pendingJoinRequests }} {{ __('dashboard.pending_requests') }}</p>
            <p class="text-xs text-yellow-600">{{ __('dashboard.pending_requests_desc') }}</p>
        </div>
    </div>
    <a href="{{ route('admin.join-requests') }}" class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-semibold rounded-xl transition-all">
        {{ __('dashboard.review_now') }}
    </a>
</div>
@endif

<!-- Clinics Overview -->
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div>
            <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">{{ __('dashboard.all_clinics') }}</h3>
            <p class="text-xs text-slate-400 mt-0.5">{{ __('dashboard.all_clinics_desc') }}</p>
        </div>
        <a href="{{ route('admin.clinics.index') }}" class="text-xs text-primary hover:opacity-80 font-medium">{{ __('dashboard.manage_clinics') }}</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-slate-800/30 text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <th class="px-6 py-3">{{ __('clinics.clinic_name') }}</th>
                    <th class="px-6 py-3">{{ __('dashboard.location') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('dashboard.doctors') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('dashboard.services_count') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('dashboard.bookings') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('dashboard.users') }}</th>
                    <th class="px-6 py-3">{{ __('common.color') }}</th>
                    <th class="px-6 py-3">{{ __('common.status') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800/50">
                @forelse($clinics as $c)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/10 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            @if($c->logo_url)
                                <img src="{{ $c->logo_url }}" alt="{{ $c->name }}" class="h-8 w-8 rounded-lg object-cover border border-slate-200">
                            @else
                                <div class="h-8 w-8 rounded-lg flex items-center justify-center text-white text-xs font-bold" style="background-color: {{ $c->primary_color }}">
                                    {{ substr($c->name, 0, 2) }}
                                </div>
                            @endif
                            <div>
                                <p class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ $c->name }}</p>
                                <p class="text-[10px] text-slate-400">{{ $c->slug }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-600 dark:text-slate-400 max-w-[200px] truncate">{{ $c->address }}</td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $c->doctors_count }}</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $c->services_count }}</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $c->bookings_count }}</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $c->users_count }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded-full border border-slate-200" style="background-color: {{ $c->primary_color }}"></div>
                            <span class="text-[10px] font-mono text-slate-500">{{ $c->primary_color }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @if($c->is_active)
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-emerald-50 text-emerald-600 border-emerald-100">{{ __('common.active') }}</span>
                        @else
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-red-50 text-red-600 border-red-100">{{ __('common.inactive') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        <form method="POST" action="{{ route('admin.switch-clinic') }}" class="inline">
                            @csrf
                            <input type="hidden" name="clinic_id" value="{{ $c->id }}">
                            <button type="submit" class="px-3 py-1.5 text-[10px] font-bold rounded-lg transition-all
                                {{ $c->is_active ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border border-emerald-200' : 'bg-slate-50 text-slate-400 border border-slate-200' }}">
                                {{ __('dashboard.enter_clinic') }}
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-8 text-center text-sm text-slate-400">{{ __('common.no_data') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- System Summary -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
    <div class="glass-card p-6 rounded-2xl border border-slate-200/50">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">{{ __('dashboard.summary') }}</h3>
        <div class="space-y-3 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-500">{{ __('dashboard.total_services') }}</span>
                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $totalServices }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">{{ __('dashboard.total_bookings') }}</span>
                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $totalBookings }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">{{ __('dashboard.total_revenue') }}</span>
                <span class="font-semibold text-emerald-600">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
    <div class="glass-card p-6 rounded-2xl border border-slate-200/50">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">{{ __('dashboard.quick_links') }}</h3>
        <div class="space-y-2">
            <a href="{{ route('admin.clinics.create') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <span class="material-symbols-outlined text-primary text-lg">add_circle</span>
                <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('dashboard.add_new_clinic') }}</span>
            </a>
            <a href="{{ route('admin.join-requests') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <span class="material-symbols-outlined text-yellow-500 text-lg">person_add</span>
                <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('dashboard.review_join_requests') }}</span>
                @if($pendingJoinRequests > 0)
                    <span class="ml-auto px-2 py-0.5 text-[9px] font-bold rounded-full bg-yellow-100 text-yellow-700">{{ $pendingJoinRequests }}</span>
                @endif
            </a>
            <a href="{{ route('admin.settings') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <span class="material-symbols-outlined text-slate-400 text-lg">settings</span>
                <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('menu.settings') }}</span>
            </a>
        </div>
    </div>
</div>
@endsection