@extends('layouts.admin')

@section('title', __('inventory.mutations_title') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('inventory.mutations_title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('inventory.mutations_title')"
        :eyebrow="__('inventory.eyebrow')"
        icon="swap_horiz"
        :description="__('inventory.mutations_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.medicines.mutations.create') }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                {{ __('inventory.mutations_create') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.medicines.partials.section-nav')

    @php
        $mutationFiltersActive = request()->hasAny(['status', 'type', 'medicine_id', 'from', 'to', 'q']);
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

    <x-admin.panel :title="__('inventory.mutations_history_title')" :subtitle="__('inventory.mutations_history_desc')" flush>
        <x-slot:actions>
            <x-admin.badge :label="__('inventory.open_mutations') . ': ' . $openCount" :tone="$openCount > 0 ? 'amber' : 'slate'" dot />
        </x-slot:actions>

        <form method="GET" action="{{ route('admin.medicines.mutations.index') }}"
              class="flex flex-wrap items-end gap-2 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="{{ __('inventory.search_mutation') }}" class="admin-input !w-52 !py-2 text-sm">

            <select name="status" class="admin-input !w-auto !py-2 text-sm">
                <option value="">{{ __('inventory.filter_status') }}</option>
                @foreach(\App\Models\MedicineStockTransaction::STATUSES as $statusOption)
                    <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>
                        {{ __('inventory.status_' . $statusOption) }}
                    </option>
                @endforeach
            </select>

            <select name="type" class="admin-input !w-auto !py-2 text-sm">
                <option value="">{{ __('inventory.filter_type') }}</option>
                @foreach(\App\Models\MedicineStockTransaction::TYPES as $typeOption)
                    <option value="{{ $typeOption }}" @selected(request('type') === $typeOption)>
                        {{ __('inventory.type_' . $typeOption) }}
                    </option>
                @endforeach
            </select>

            <select name="medicine_id" class="admin-input !w-auto !py-2 text-sm">
                <option value="">{{ __('inventory.filter_medicine') }}</option>
                @foreach($medicines as $medicine)
                    <option value="{{ $medicine->id }}" @selected((string) request('medicine_id') === (string) $medicine->id)>
                        {{ $medicine->name }}
                    </option>
                @endforeach
            </select>

            <input type="date" name="from" value="{{ request('from') }}"
                   class="admin-input !w-auto !py-2 text-sm" aria-label="{{ __('inventory.filter_from') }}">
            <input type="date" name="to" value="{{ request('to') }}"
                   class="admin-input !w-auto !py-2 text-sm" aria-label="{{ __('inventory.filter_to') }}">

            <button type="submit" class="admin-btn-secondary !py-2 text-xs">{{ __('inventory.filter_apply') }}</button>
            <a href="{{ route('admin.medicines.mutations.index') }}" class="admin-btn-ghost !py-2 text-xs">{{ __('inventory.filter_reset') }}</a>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('inventory.col_reference') }}</th>
                        <th class="px-5 py-3">{{ __('inventory.col_medicine') }}</th>
                        <th class="px-5 py-3">{{ __('inventory.col_type') }}</th>
                        <th class="px-5 py-3">{{ __('inventory.col_quantity') }}</th>
                        <th class="hidden px-5 py-3 md:table-cell">{{ __('inventory.col_officer') }}</th>
                        <th class="px-5 py-3">{{ __('inventory.col_date') }}</th>
                        <th class="px-5 py-3">{{ __('inventory.col_status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($transactions as $transaction)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.medicines.mutations.show', $transaction->id) }}"
                                   class="font-mono text-xs font-bold text-primary hover:underline">
                                    {{ $transaction->reference }}
                                </a>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $transaction->medicine?->name ?? '—' }}
                                </p>
                                <p class="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ $transaction->batch?->batch_number ?? '—' }}
                                </p>
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge :label="$transaction->typeLabel()" :tone="$transaction->typeTone()" />
                                @if($transaction->from_quarantine)
                                    <p class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-orange-500">
                                        {{ __('inventory.stock_state_quarantine') }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">
                                {{ rtrim(rtrim(number_format((float) $transaction->quantity, 2, ',', '.'), '0'), ',') }}
                                <span class="text-xs font-medium text-slate-500">{{ $transaction->medicine?->unit }}</span>
                            </td>
                            <td class="hidden px-5 py-3 text-sm text-slate-600 dark:text-slate-300 md:table-cell">
                                {{ $transaction->creator?->name ?? __('inventory.officer_system') }}
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                                {{ $transaction->created_at?->format('d M Y H:i') }}
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge :label="$transaction->statusLabel()" :tone="$transaction->statusTone()" dot />
                                @if($transaction->needsFollowUp())
                                    <p class="mt-1">
                                        <x-admin.badge :label="__('inventory.needs_follow_up')" tone="orange" />
                                    </p>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state
                                    :title="$mutationFiltersActive ? __('inventory.empty_filter_title') : __('inventory.empty_mutations_title')"
                                    :description="$mutationFiltersActive ? __('inventory.empty_filter_desc') : __('inventory.empty_mutations_desc')"
                                    icon="swap_horiz"
                                    tone="slate">
                                    @if($mutationFiltersActive)
                                        <x-slot:actions>
                                            <a href="{{ route('admin.medicines.mutations.index') }}" class="admin-btn-secondary !py-2 text-xs">
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
            {{ $transactions->links() }}
        </x-slot:footer>
    </x-admin.panel>
</div>
@endsection
