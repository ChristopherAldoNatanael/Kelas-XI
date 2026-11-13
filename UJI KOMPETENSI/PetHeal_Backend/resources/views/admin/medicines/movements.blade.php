@extends('layouts.admin')

@section('title', 'Mutasi stok ' . $medicine->name . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.medicines_movements'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('admin.medicines_movements') . ' — ' . $medicine->name"
        :eyebrow="__('admin.eyebrow_management')"
        icon="receipt_long"
        :description="__('admin.medicines_movements_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.medicines.edit', $medicine->id) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back_to_medicine') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.stat-card
            :label="__('admin.medicine_stock_current')"
            :value="rtrim(rtrim(number_format((float) $medicine->stock, 2, ',', '.'), '0'), ',') . ' ' . $medicine->unit"
            icon="inventory_2"
            tone="{{ $medicine->isLowStock() ? 'rose' : 'primary' }}" />
        <x-admin.stat-card
            :label="__('admin.medicine_min_stock')"
            :value="rtrim(rtrim(number_format((float) $medicine->min_stock, 2, ',', '.'), '0'), ',') . ' ' . $medicine->unit"
            icon="rule" tone="slate" />
        <x-admin.stat-card
            :label="__('admin.medicine_expiry')"
            :value="$medicine->expiry_date?->format('d M Y') ?? '—'"
            icon="event_busy"
            tone="{{ $medicine->isExpired() ? 'rose' : ($medicine->expiresWithin(60) ? 'amber' : 'slate') }}" />
    </div>

    <x-admin.panel :title="__('admin.medicines_movements')" :subtitle="__('admin.medicines_movements_desc')" flush>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('common.date') }}</th>
                        <th class="px-5 py-3">{{ __('admin.medicine_movement_type') }}</th>
                        <th class="px-5 py-3">{{ __('admin.medicine_quantity') }}</th>
                        <th class="px-5 py-3">{{ __('admin.medicine_stock_after') }}</th>
                        <th class="px-5 py-3">{{ __('admin.medicine_reason') }}</th>
                        <th class="px-5 py-3">{{ __('admin.medicine_movement_by') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($movements as $movement)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $movement->created_at?->format('d M Y H:i') }}
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="$movement->type_label"
                                    :tone="(float) $movement->quantity >= 0 ? 'primary' : 'amber'"
                                    dot />
                            </td>
                            <td class="px-5 py-3 text-sm font-bold tabular-nums {{ (float) $movement->quantity >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ (float) $movement->quantity >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $movement->quantity, 2, ',', '.'), '0'), ',') }}
                            </td>
                            <td class="px-5 py-3 text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                {{ rtrim(rtrim(number_format((float) $movement->stock_after, 2, ',', '.'), '0'), ',') }} {{ $medicine->unit }}
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $movement->reason ?: '—' }}
                                @if($movement->medical_record_id)
                                    <a href="{{ route('admin.medical-records.show', $movement->medical_record_id) }}"
                                       class="text-primary hover:underline">#{{ $movement->medical_record_id }}</a>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $movement->user?->name ?? 'Sistem' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state
                                    :title="__('admin.medicines_movements_empty')"
                                    icon="receipt_long"
                                    tone="slate" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($movements->hasPages())
            <x-slot:footer>{{ $movements->links() }}</x-slot:footer>
        @endif
    </x-admin.panel>
</div>
@endsection
