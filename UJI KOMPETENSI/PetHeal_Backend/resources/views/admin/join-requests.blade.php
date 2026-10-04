@extends('layouts.admin')
@section('title', 'Join Requests — ' . ($clinicName ?? 'VCMS') . ' Admin')
@section('header', __('menu.join_requests'))

@section('content')
@if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 text-sm">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
        @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
    </div>
@endif

<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 dark:border-slate-800">
        <p class="text-sm text-slate-500">{{ __('clinics.join_requests_desc') }}</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-slate-800/30 text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <th class="px-6 py-3">{{ __('clinics.request_date') }}</th>
                    <th class="px-6 py-3">{{ __('clinics.request_name') }}</th>
                    <th class="px-6 py-3">{{ __('clinics.request_email') }}</th>
                    <th class="px-6 py-3">{{ __('clinics.request_phone') }}</th>
                    <th class="px-6 py-3">{{ __('clinics.request_clinic') }}</th>
                    <th class="px-6 py-3">{{ __('clinics.request_status') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('clinics.request_action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800/50">
                @forelse($requests as $req)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/10 transition-colors">
                    <td class="px-6 py-4 text-xs text-slate-600">{{ $req->created_at->format('d M Y H:i') }}</td>
                    <td class="px-6 py-4">
                        <p class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ $req->name }}</p>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-600">{{ $req->email }}</td>
                    <td class="px-6 py-4 text-xs text-slate-600">{{ $req->phone ?? '—' }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded-full border border-slate-200" style="background-color: {{ $req->clinic->primary_color }}"></div>
                            <span class="text-xs font-medium text-slate-700">{{ $req->clinic->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @if($req->status === 'pending')
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-yellow-50 text-yellow-600 border-yellow-100">{{ __('common.pending') }}</span>
                        @elseif($req->status === 'approved')
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-emerald-50 text-emerald-600 border-emerald-100">{{ __('common.approved') }}</span>
                        @else
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-red-50 text-red-600 border-red-100">{{ __('common.rejected') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($req->status === 'pending')
                            <div class="flex items-center justify-center gap-1">
                                <form method="POST" action="{{ route('admin.join-requests.approve', $req->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-[10px] font-bold rounded-lg transition-all inline-flex items-center gap-1" title="{{ __('common.approve') }}"
                                        onclick="return confirm('{{ __('clinics.approve_confirm', ['name' => $req->name]) }}')">
                                        <span class="material-symbols-outlined text-sm">check</span> {{ __('common.approve') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.join-requests.reject', $req->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-[10px] font-bold rounded-lg transition-all inline-flex items-center gap-1" title="{{ __('common.reject') }}"
                                        onclick="return confirm('{{ __('clinics.reject_confirm', ['name' => $req->name]) }}')">
                                        <span class="material-symbols-outlined text-sm">close</span> {{ __('common.reject') }}
                                    </button>
                                </form>
                            </div>
                        @else
                            @if($req->reviewedBy)
                                <p class="text-[10px] text-slate-500">{{ $req->reviewedBy->name }}</p>
                                <p class="text-[9px] text-slate-400">{{ $req->reviewed_at?->format('d M Y H:i') }}</p>
                            @else
                                <span class="text-[10px] text-slate-400">—</span>
                            @endif
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center">
                        <span class="material-symbols-outlined text-4xl text-slate-300 block mb-2">inbox</span>
                        <p class="text-sm text-slate-400">{{ __('clinics.no_requests') }}</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $requests->links() }}</div>
@endsection