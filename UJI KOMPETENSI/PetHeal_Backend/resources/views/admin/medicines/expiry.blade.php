@extends('layouts.admin')

@section('title', __('inventory.expiry_title') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('inventory.expiry_title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('inventory.expiry_title')"
        :eyebrow="__('inventory.eyebrow')"
        icon="event_busy"
        :description="__('inventory.expiry_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.medicines.mutations.create') }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                {{ __('inventory.mutations_create') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.medicines.partials.section-nav')

    @php
        $expiryFiltersActive = $status !== '' || request()->filled('q');
    @endphp

    @if(session('success'))
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm font-medium text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card
            :label="__('inventory.expiry_batches_expiring')"
            :value="$expiringCount"
            icon="event_available"
            :tone="$expiringCount > 0 ? 'amber' : 'slate'"
            :hint="__('inventory.expiry_batches_expiring_hint', ['days' => $warningDays])" />
        <x-admin.stat-card
            :label="__('inventory.expiry_batches_expired')"
            :value="$expiredCount"
            icon="event_busy"
            :tone="$expiredCount > 0 ? 'rose' : 'slate'"
            :hint="__('inventory.expiry_batches_expired_hint')" />
        <x-admin.stat-card
            :label="__('inventory.expiry_quarantine_stock')"
            :value="rtrim(rtrim(number_format($quarantineQuantity, 2, ',', '.'), '0'), ',')"
            icon="inventory_2"
            :tone="$quarantineQuantity > 0 ? 'orange' : 'slate'"
            :hint="__('inventory.expiry_quarantine_hint', ['count' => $quarantineBatchCount])" />
        <x-admin.stat-card
            :label="__('inventory.expiry_open_mutations')"
            :value="$openMutationCount"
            icon="pending_actions"
            :tone="$openMutationCount > 0 ? 'sky' : 'slate'"
            :hint="__('inventory.expiry_open_mutations_hint')" />
    </div>

    <x-admin.panel :title="__('inventory.batches_panel_title')" :subtitle="__('inventory.batches_panel_desc')" flush>
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.medicines.expiry') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}"
                       placeholder="{{ __('inventory.search_batch') }}" class="admin-input !w-56 !py-2 text-sm">
                <select name="sort" class="admin-input !w-auto !py-2 text-sm" onchange="this.form.submit()">
                    <option value="fefo" @selected($sort === 'fefo')>{{ __('inventory.sort_fefo') }}</option>
                    <option value="expiry_desc" @selected($sort === 'expiry_desc')>{{ __('inventory.sort_expiry_desc') }}</option>
                    <option value="name" @selected($sort === 'name')>{{ __('inventory.sort_name') }}</option>
                </select>
                @if($status !== '')
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <button type="submit" class="admin-btn-secondary !py-2" aria-label="{{ __('admin.search') }}">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">search</span>
                </button>
            </form>
        </x-slot:actions>

        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
            @foreach([
                '' => __('inventory.filter_all'),
                'expired' => __('inventory.filter_expired'),
                'expiring' => __('inventory.filter_expiring'),
                'quarantine' => __('inventory.filter_quarantine'),
                'safe' => __('inventory.filter_safe'),
            ] as $value => $label)
                <a href="{{ route('admin.medicines.expiry', array_filter(['status' => $value, 'q' => request('q'), 'sort' => $sort])) }}"
                   class="admin-filter-chip {{ $status === $value ? 'admin-filter-chip--active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('inventory.col_medicine') }}</th>
                        <th class="px-5 py-3">{{ __('inventory.col_batch') }}</th>
                        <th class="px-5 py-3">{{ __('inventory.col_expiry') }}</th>
                        <th class="px-5 py-3">{{ __('inventory.col_quantity') }}</th>
                        <th class="px-5 py-3">{{ __('inventory.field_status') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('inventory.col_action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($batches as $batch)
                        @php
                            $expiryStatus = $batch->expiryStatus();
                            $daysLeft = $batch->expiryDaysLeft();
                            $statusTone = match ($expiryStatus) {
                                'expired' => 'rose',
                                'expiring' => 'amber',
                                'safe' => 'primary',
                                default => 'slate',
                            };
                            $statusLabel = match ($expiryStatus) {
                                'expired' => __('inventory.status_expired'),
                                'expiring' => __('inventory.status_expiring'),
                                'safe' => __('inventory.status_safe'),
                                default => __('inventory.status_none'),
                            };
                        @endphp
                        <tr class="admin-table-row">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">medication</span>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">
                                            {{ $batch->medicine?->name ?? '—' }}
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $batch->medicine?->form ?: __('admin.medicine_form_generic') }}
                                            · {{ $batch->medicine?->unit }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="font-mono text-xs font-semibold text-slate-700 dark:text-slate-300">{{ $batch->batch_number }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm">
                                @if($batch->expiry_date)
                                    <span class="{{ $expiryStatus === 'expired' ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-slate-600 dark:text-slate-300' }}">
                                        {{ $batch->expiry_date->format('d M Y') }}
                                    </span>
                                    @if($daysLeft !== null && $daysLeft < 0)
                                        <p class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">
                                            {{ __('inventory.expiry_days_ago', ['days' => abs($daysLeft)]) }}
                                        </p>
                                    @elseif($daysLeft !== null && $expiryStatus === 'expiring')
                                        <p class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                            {{ __('inventory.expiry_days_left', ['days' => $daysLeft]) }}
                                        </p>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">
                                    {{ rtrim(rtrim(number_format((float) $batch->stock, 2, ',', '.'), '0'), ',') }}
                                    <span class="text-xs font-medium text-slate-500">{{ $batch->medicine?->unit }}</span>
                                </p>
                                @if((float) $batch->quarantined_stock > 0)
                                    <p class="text-[11px] font-semibold text-orange-600 dark:text-orange-400">
                                        {{ __('inventory.stock_state_quarantine') }}:
                                        {{ rtrim(rtrim(number_format((float) $batch->quarantined_stock, 2, ',', '.'), '0'), ',') }}
                                        {{ $batch->medicine?->unit }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <x-admin.badge :label="$statusLabel" :tone="$statusTone" dot />
                                    @if((float) $batch->quarantined_stock > 0)
                                        <x-admin.badge :label="__('inventory.filter_quarantine')" tone="orange" />
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    @if((float) $batch->stock > 0 && in_array($expiryStatus, ['expired', 'expiring'], true))
                                        <a href="{{ route('admin.medicines.mutations.create', [
                                                'medicine_id' => $batch->medicine_id,
                                                'batch_id' => $batch->id,
                                                'type' => \App\Models\MedicineStockTransaction::TYPE_QUARANTINE,
                                            ]) }}"
                                           class="admin-btn-secondary !px-3 !py-1.5 !text-[11px]">
                                            <span class="material-symbols-outlined text-[15px]" aria-hidden="true">inventory_2</span>
                                            {{ __('inventory.action_quarantine') }}
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.medicines.edit', $batch->medicine_id) }}"
                                       class="admin-icon-btn hover:!text-primary"
                                       title="{{ __('admin.edit') }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state
                                    :title="__('inventory.empty_expiry_title')"
                                    :description="__('inventory.empty_expiry_desc')"
                                    icon="event_available"
                                    tone="slate">
                                    @if($expiryFiltersActive)
                                        <x-slot:actions>
                                            <a href="{{ route('admin.medicines.expiry') }}" class="admin-btn-secondary !py-2 text-xs">
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">filter_alt_off</span>
                                                {{ __('inventory.filter_reset') }}
                                            </a>
                                        </x-slot:actions>
                                    @endif
                                </x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            {{ $batches->links() }}
        </x-slot:footer>
    </x-admin.panel>
</div>
@endsection
