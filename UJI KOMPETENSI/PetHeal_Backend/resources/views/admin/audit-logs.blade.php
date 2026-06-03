@extends('layouts.admin')

@section('title', 'Audit Logs - PetHeal Admin')
@section('header', 'Audit Logs')

@section('content')
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h2 class="text-md font-bold text-slate-900 dark:text-white">Audit Logs</h2>
            <p class="text-xs text-slate-400 mt-0.5">Track all administrative actions in the system</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50/50 border-b border-slate-100">
                <tr>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400">Timestamp</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400">Admin</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400">Action</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400">Description</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100/50">
                @forelse($logs as $log)
                    <tr class="hover:bg-slate-50/30 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                            {{ $log->created_at->format('M d, Y H:i:s') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                            {{ $log->user->name ?? 'System' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded border bg-blue-100 text-blue-700 border-blue-200">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500 max-w-md">
                            {{ $log->description }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400 font-mono">
                            {{ $log->ip_address }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 rounded-full mb-3">
                                <span class="material-symbols-outlined text-3xl text-slate-400">history</span>
                            </div>
                            <p class="text-sm text-slate-400">No audit logs found</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($logs, 'hasPages') && $logs->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection