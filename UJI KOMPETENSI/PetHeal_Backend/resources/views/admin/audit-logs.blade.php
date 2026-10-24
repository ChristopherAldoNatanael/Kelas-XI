@extends('layouts.admin')

@section('title', __('admin.audit_title') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.audit_logs'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('admin.audit_title')"
        :eyebrow="__('admin.eyebrow_security')"
        icon="history"
        :description="__('admin.audit_desc')" />

    <x-admin.panel :title="__('admin.audit_title')" :subtitle="__('admin.audit_desc')" flush>
        <x-slot:actions>
            <x-admin.badge :label="__('admin.total') . ' · ' . $logs->total()" tone="slate" />
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('admin.audit_col_time') }}</th>
                        <th class="px-5 py-3">{{ __('crud.user_col') }}</th>
                        <th class="px-5 py-3">{{ __('admin.audit_col_action') }}</th>
                        <th class="px-5 py-3">{{ __('crud.description_col') }}</th>
                        <th class="px-5 py-3">{{ __('admin.audit_col_ip') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($logs as $log)
                        <tr class="admin-table-row">
                            <td class="whitespace-nowrap px-5 py-3 text-sm tabular-nums text-slate-500">
                                {{ optional($log->created_at)->locale(app()->getLocale())->translatedFormat('d M Y H:i:s') }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-sm font-medium text-slate-800 dark:text-slate-200">
                                {{ $log->user->name ?? __('common.none') }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <x-admin.badge :label="\App\Support\AdminStatus::auditActionLabel($log->action)" tone="blue" />
                            </td>
                            <td class="max-w-md px-5 py-3 text-sm text-slate-500">{{ $log->description }}</td>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-400">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-admin.empty-state
                                    :title="__('admin.no_audit_title')"
                                    :description="__('admin.no_audit_desc')"
                                    icon="history"
                                    tone="slate" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            {{ $logs->links() }}
        </x-slot:footer>
    </x-admin.panel>
</div>
@endsection
