@extends('layouts.admin')
@section('title', __('clinics.clinic_management') . ' — ' . ($clinicName ?? 'VCMS') . ' Admin')
@section('header', __('clinics.clinic_management'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('clinics.manage_all_clinics') }}</p>
    <a href="{{ route('admin.clinics.create') }}" class="bg-primary hover:opacity-90 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all inline-flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">add</span> {{ __('clinics.add_clinic') }}
    </a>
</div>

@if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 text-sm">{{ session('success') }}</div>
@endif

@if(session('new_clinic_credentials'))
    @php $cred = session('new_clinic_credentials'); @endphp
    <div class="bg-blue-50 border border-blue-200 text-blue-800 px-5 py-4 rounded-xl mb-6 text-sm relative" id="cred-alert">
        <button onclick="document.getElementById('cred-alert').remove()" class="absolute top-2 right-3 text-blue-400 hover:text-blue-600 text-lg">&times;</button>
        <p class="font-bold mb-2">{{ __('clinics.admin_account') }} {{ $cred['clinic'] }}</p>
        <div class="flex items-center gap-4">
            <div>
                <span class="text-blue-600 text-xs">{{ __('clinics.cred_email') }}:</span>
                <code class="bg-blue-100 px-2 py-0.5 rounded text-xs font-mono">{{ $cred['email'] }}</code>
            </div>
            <div>
                <span class="text-blue-600 text-xs">{{ __('clinics.cred_password') }}:</span>
                <code class="bg-blue-100 px-2 py-0.5 rounded text-xs font-mono">{{ $cred['password'] }}</code>
            </div>
        </div>
        <p class="text-[10px] text-blue-500 mt-2">{{ __('clinics.save_credentials') }}</p>
    </div>
@endif

<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-slate-800/30 text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <th class="px-6 py-3">{{ __('clinics.th_clinic') }}</th>
                    <th class="px-6 py-3">{{ __('clinics.contact') }}</th>
                    <th class="px-6 py-3">{{ __('clinics.stats') }}</th>
                    <th class="px-6 py-3">{{ __('clinics.color') }}</th>
                    <th class="px-6 py-3">{{ __('clinics.th_status') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('clinics.th_actions') }}</th>
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
                    <td class="px-6 py-4">
                        <p class="text-xs text-slate-600 dark:text-slate-400">{{ $c->phone ?? '—' }}</p>
                        <p class="text-[10px] text-slate-400">{{ $c->email ?? '—' }}</p>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex gap-3 text-[10px] font-bold text-slate-500">
                            <span>{{ $c->doctors_count }} dr</span>
                            <span>{{ $c->services_count }} svc</span>
                            <span>{{ $c->users_count }} usr</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 rounded-full border border-slate-200" style="background-color: {{ $c->primary_color }}"></div>
                            <span class="text-[10px] font-mono text-slate-500">{{ $c->primary_color }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @if($c->is_active)
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-emerald-50 text-emerald-600 border-emerald-100">{{ __('clinics.status_active') }}</span>
                        @else
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-red-50 text-red-600 border-red-100">{{ __('clinics.status_inactive') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-1">
                            <a href="{{ route('admin.clinics.edit', $c) }}" class="w-7 h-7 rounded-lg text-slate-400 hover:text-primary transition-all inline-flex items-center justify-center" title="Edit">
                                <span class="material-symbols-outlined text-md">edit</span>
                            </a>
                            <form method="POST" action="{{ route('admin.clinics.toggle-active', $c) }}" class="inline" onsubmit="return confirm('{{ __('clinics.toggle_confirm', ['name' => $c->name]) }}')">
                                @csrf
                                <button type="submit" class="w-7 h-7 rounded-lg text-slate-400 hover:text-orange-500 transition-all inline-flex items-center justify-center" title="{{ $c->is_active ? 'Deactivate' : 'Activate' }}">
                                    <span class="material-symbols-outlined text-md">{{ $c->is_active ? 'block' : 'check_circle' }}</span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-slate-400">{{ __('clinics.no_clinics') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $clinics->links() }}</div>
@endsection