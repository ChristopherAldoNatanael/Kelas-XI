@extends('layouts.admin')

@section('title', __('inventory.show_title') . ' — ' . $transaction->reference)
@section('header', __('inventory.show_title'))

@section('content')
@php
    $unit = $transaction->medicine?->unit;
    $quantity = (float) $transaction->quantity;
    $badgeTone = $transaction->statusTone();
@endphp

<div class="space-y-6">
    <x-admin.page-header
        :title="__('inventory.show_title') . ' · ' . $transaction->reference"
        :eyebrow="__('inventory.eyebrow')"
        icon="receipt_long"
        :description="__('inventory.show_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.medicines.mutations.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('inventory.mutations_title') }}
            </a>
            <a href="{{ route('admin.medicines.edit', $transaction->medicine_id) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">medication</span>
                {{ __('inventory.action_detail_medicine') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.medicines.partials.section-nav')

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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.5fr_1fr]">
        <div class="space-y-6">
            <x-admin.panel :title="__('inventory.section_transaction')">
                <x-slot:actions>
                    <x-admin.badge :label="$transaction->typeLabel()" :tone="$transaction->typeTone()" />
                    <x-admin.badge :label="$transaction->statusLabel()" :tone="$badgeTone" dot />
                    @if($transaction->needsFollowUp())
                        <x-admin.badge :label="__('inventory.needs_follow_up')" tone="orange" dot />
                    @endif
                </x-slot:actions>

                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.col_medicine') }}</dt>
                        <dd class="mt-1 font-semibold text-slate-800 dark:text-slate-200">{{ $transaction->medicine?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.col_batch') }}</dt>
                        <dd class="mt-1 font-mono text-xs font-semibold text-slate-800 dark:text-slate-200">
                            {{ $transaction->batch?->batch_number ?? '—' }}
                            <span class="font-sans font-normal text-slate-500">
                                · {{ __('inventory.col_expiry') }} {{ $transaction->batch?->expiryDateLabel() }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.col_quantity') }}</dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums text-slate-900 dark:text-white">
                            {{ rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ',') }}
                            <span class="text-xs font-medium text-slate-500">{{ $unit }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.field_destination') }}</dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ $transaction->destination ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.field_reason') }}</dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ $transaction->reason }}</dd>
                    </div>
                    @if($transaction->notes)
                        <div class="sm:col-span-2">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.field_notes') }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-slate-700 dark:text-slate-300">{{ $transaction->notes }}</dd>
                        </div>
                    @endif
                    @if($transaction->from_quarantine)
                        <div class="sm:col-span-2">
                            <x-admin.badge :label="__('inventory.stock_state_quarantine')" tone="orange" dot />
                        </div>
                    @endif
                </dl>
            </x-admin.panel>

            <x-admin.panel :title="__('inventory.section_support')">
                <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    @if($transaction->type === \App\Models\MedicineStockTransaction::TYPE_RETURN_SUPPLIER)
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.field_supplier') }}</p>
                            <p class="mt-1 text-slate-700 dark:text-slate-300">{{ $transaction->supplier_name ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.field_return_date') }}</p>
                            <p class="mt-1 text-slate-700 dark:text-slate-300">{{ $transaction->return_date?->format('d M Y') ?? '—' }}</p>
                        </div>
                        @if($transaction->document_path)
                            <div class="sm:col-span-2">
                                <a href="{{ route('admin.medicines.mutations.document', [$transaction->id, 'document_path']) }}"
                                   class="admin-btn-secondary !py-2 text-xs">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">download</span>
                                    {{ __('inventory.download_document') }}
                                </a>
                            </div>
                        @endif
                    @endif

                    @if($transaction->type === \App\Models\MedicineStockTransaction::TYPE_DISPOSAL)
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.field_destruction_date') }}</p>
                            <p class="mt-1 text-slate-700 dark:text-slate-300">{{ $transaction->destruction_date?->format('d M Y') ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.field_destruction_method') }}</p>
                            <p class="mt-1 text-slate-700 dark:text-slate-300">
                                {{ $transaction->destruction_method ? __('inventory.method_' . $transaction->destruction_method) : '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.field_witness') }}</p>
                            <p class="mt-1 text-slate-700 dark:text-slate-300">{{ $transaction->witness_name ?: '—' }}</p>
                        </div>
                        @if($transaction->minutes_path)
                            <div>
                                <a href="{{ route('admin.medicines.mutations.document', [$transaction->id, 'minutes_path']) }}"
                                   class="admin-btn-secondary !py-2 text-xs">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">download</span>
                                    {{ __('inventory.download_minutes') }}
                                </a>
                            </div>
                        @endif
                    @endif

                    @if($transaction->type === \App\Models\MedicineStockTransaction::TYPE_QUARANTINE)
                        <div class="sm:col-span-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.field_quarantine_location') }}</p>
                            <p class="mt-1 text-slate-700 dark:text-slate-300">{{ $transaction->quarantine_location ?: '—' }}</p>
                        </div>
                    @endif

                    @if($transaction->type === \App\Models\MedicineStockTransaction::TYPE_ADJUSTMENT)
                        <div class="sm:col-span-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.field_direction') }}</p>
                            <p class="mt-1 text-slate-700 dark:text-slate-300">
                                {{ $transaction->direction === 'in' ? __('inventory.direction_in') : __('inventory.direction_out') }}
                            </p>
                        </div>
                    @endif
                </div>

                @if($transaction->sourceTransaction)
                    <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <a href="{{ route('admin.medicines.mutations.show', $transaction->sourceTransaction->id) }}"
                           class="text-xs font-semibold text-primary hover:underline">
                            {{ __('inventory.source_quarantine', ['reference' => $transaction->sourceTransaction->reference]) }}
                        </a>
                    </div>
                @endif

            </x-admin.panel>

            <x-admin.panel :title="__('inventory.section_movements')" flush>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] text-left">
                        <thead class="admin-table-head">
                            <tr>
                                <th class="px-5 py-3">{{ __('inventory.movement_batch') }}</th>
                                <th class="px-5 py-3">{{ __('inventory.movement_state') }}</th>
                                <th class="px-5 py-3">{{ __('inventory.col_quantity') }}</th>
                                <th class="px-5 py-3">{{ __('inventory.preview_stock_after') }}</th>
                                <th class="px-5 py-3">{{ __('inventory.col_date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                            @forelse($movements as $movement)
                                <tr class="admin-table-row">
                                    <td class="px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                                        {{ $movement->batch?->batch_number ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-xs text-slate-600 dark:text-slate-300">
                                        {{ $movement->stockStateLabel() }}
                                    </td>
                                    <td class="px-5 py-3 text-sm font-bold tabular-nums {{ (float) $movement->quantity >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                        {{ (float) $movement->quantity >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $movement->quantity, 2, ',', '.'), '0'), ',') }}
                                    </td>
                                    <td class="px-5 py-3 text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                        {{ rtrim(rtrim(number_format((float) $movement->stock_after, 2, ',', '.'), '0'), ',') }}
                                    </td>
                                    <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $movement->created_at?->format('d M Y H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">
                                        {{ __('inventory.no_movements') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>

            @if($followUps->isNotEmpty())
                <x-admin.panel :title="__('inventory.action_follow_up')" :subtitle="__('inventory.needs_follow_up')">
                    <div class="space-y-3">
                        @foreach($followUps as $followUp)
                            <a href="{{ route('admin.medicines.mutations.show', $followUp->id) }}"
                               class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm transition hover:border-primary/40 dark:border-slate-700">
                                <div class="min-w-0">
                                    <p class="font-mono text-xs font-bold text-primary">{{ $followUp->reference }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $followUp->typeLabel() }} ·
                                        {{ rtrim(rtrim(number_format((float) $followUp->quantity, 2, ',', '.'), '0'), ',') }} {{ $unit }}
                                    </p>
                                </div>
                                <x-admin.badge :label="$followUp->statusLabel()" :tone="$followUp->statusTone()" />
                            </a>
                        @endforeach
                    </div>
                </x-admin.panel>
            @endif
        </div>

        <div class="space-y-6">
            <x-admin.panel :title="__('inventory.section_timeline')">
                <ol class="relative space-y-5 border-l border-slate-200 pl-5 dark:border-slate-700">
                    <li>
                        <span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full bg-primary"></span>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('inventory.timeline_created') }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $transaction->created_at?->format('d M Y H:i') }}
                            · {{ $transaction->creator?->name ?? __('inventory.officer_system') }}
                        </p>
                    </li>
                    <li>
                        <span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full {{ $transaction->processed_at ? 'bg-sky-500' : 'bg-slate-300 dark:bg-slate-600' }}"></span>
                        <p class="text-sm font-semibold {{ $transaction->processed_at ? 'text-slate-800 dark:text-slate-200' : 'text-slate-400' }}">{{ __('inventory.timeline_processed') }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $transaction->processed_at?->format('d M Y H:i') ?? __('inventory.timeline_waiting') }}
                            @if($transaction->processor)
                                · {{ $transaction->processor->name }}
                            @endif
                        </p>
                    </li>
                    @if($transaction->status === \App\Models\MedicineStockTransaction::STATUS_CANCELLED)
                        <li>
                            <span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full bg-slate-400"></span>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('inventory.timeline_cancelled') }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $transaction->cancelled_at?->format('d M Y H:i') }}
                                · {{ $transaction->canceller?->name ?? __('inventory.officer_system') }}
                            </p>
                            @if($transaction->cancel_reason)
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $transaction->cancel_reason }}</p>
                            @endif
                        </li>
                    @else
                        <li>
                            <span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full {{ $transaction->completed_at ? 'bg-primary' : 'bg-slate-300 dark:bg-slate-600' }}"></span>
                            <p class="text-sm font-semibold {{ $transaction->completed_at ? 'text-slate-800 dark:text-slate-200' : 'text-slate-400' }}">{{ __('inventory.timeline_completed') }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $transaction->completed_at?->format('d M Y H:i') ?? __('inventory.timeline_waiting') }}
                                @if($transaction->completer)
                                    · {{ $transaction->completer->name }}
                                @endif
                            </p>
                        </li>
                    @endif
                </ol>
            </x-admin.panel>

            <x-admin.panel :title="__('inventory.col_action')">
                <div class="space-y-2.5">
                    @if($transaction->canProcess())
                        <form method="POST" action="{{ route('admin.medicines.mutations.process', $transaction->id) }}"
                              onsubmit="return confirm(@json(__('inventory.confirm_process')))">
                            @csrf
                            <button type="submit" class="admin-btn-secondary w-full !justify-center">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">play_arrow</span>
                                {{ __('inventory.action_process') }}
                            </button>
                        </form>
                    @endif

                    @if($transaction->canComplete())
                        <form method="POST" action="{{ route('admin.medicines.mutations.complete', $transaction->id) }}"
                              onsubmit="return confirm(@json(__('inventory.confirm_complete')))">
                            @csrf
                            <button type="submit" class="admin-btn-primary w-full !justify-center">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check</span>
                                {{ __('inventory.action_complete') }}
                            </button>
                        </form>
                    @endif

                    @if($transaction->needsFollowUp())
                        <div class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-xs leading-5 text-orange-800 dark:border-orange-900/60 dark:bg-orange-900/20 dark:text-orange-300">
                            {{ __('inventory.follow_up_hint', [
                                'qty' => rtrim(rtrim(number_format($transaction->remainingFollowUpQuantity(), 2, ',', '.'), '0'), ','),
                                'unit' => $unit,
                            ]) }}
                        </div>
                        <a href="{{ route('admin.medicines.mutations.create', [
                                'source_transaction_id' => $transaction->id,
                                'type' => \App\Models\MedicineStockTransaction::TYPE_RETURN_SUPPLIER,
                            ]) }}"
                           class="admin-btn-secondary w-full !justify-center">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">assignment_return</span>
                            {{ __('inventory.action_follow_up') }} — {{ __('inventory.type_return_supplier') }}
                        </a>
                        <a href="{{ route('admin.medicines.mutations.create', [
                                'source_transaction_id' => $transaction->id,
                                'type' => \App\Models\MedicineStockTransaction::TYPE_DISPOSAL,
                            ]) }}"
                           class="admin-btn-danger w-full !justify-center">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete_forever</span>
                            {{ __('inventory.action_follow_up') }} — {{ __('inventory.type_disposal') }}
                        </a>
                    @endif

                    @if($transaction->canCancel())
                        <button type="button" onclick="openCancelModal()" class="admin-btn-ghost w-full !justify-center !text-rose-600">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">cancel</span>
                            {{ __('inventory.action_cancel') }}
                        </button>
                    @endif

                    @if($transaction->canReverse())
                        <button type="button" onclick="openReverseModal()" class="admin-btn-ghost w-full !justify-center">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">undo</span>
                            {{ __('inventory.action_reverse') }}
                        </button>
                    @endif
                </div>
            </x-admin.panel>
        </div>
    </div>
</div>

{{-- Modal pembatalan --}}
<div id="cancel-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('inventory.cancel_modal_title') }}</h3>
        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('inventory.cancel_modal_desc') }}</p>
        <form method="POST" action="{{ route('admin.medicines.mutations.cancel', $transaction->id) }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label for="cancel_reason" class="admin-label">{{ __('inventory.field_cancel_reason') }} <span class="text-rose-500">*</span></label>
                <textarea name="cancel_reason" id="cancel_reason" rows="3" required class="admin-input"></textarea>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" class="admin-btn-secondary" onclick="closeCancelModal()">{{ __('admin.cancel') }}</button>
                <button type="submit" class="admin-btn-danger">{{ __('inventory.action_cancel') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal pembalik --}}
<div id="reverse-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('inventory.reverse_modal_title') }}</h3>
        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('inventory.reverse_modal_desc') }}</p>
        <form method="POST" action="{{ route('admin.medicines.mutations.reverse', $transaction->id) }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label for="reverse_reason" class="admin-label">{{ __('inventory.field_reason') }} <span class="text-rose-500">*</span></label>
                <textarea name="reverse_reason" id="reverse_reason" rows="3" required class="admin-input"></textarea>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" class="admin-btn-secondary" onclick="closeReverseModal()">{{ __('admin.cancel') }}</button>
                <button type="submit" class="admin-btn-primary">{{ __('inventory.action_reverse') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openCancelModal() {
        document.getElementById('cancel-modal').classList.replace('hidden', 'flex');
    }
    function closeCancelModal() {
        document.getElementById('cancel-modal').classList.replace('flex', 'hidden');
    }
    function openReverseModal() {
        document.getElementById('reverse-modal').classList.replace('hidden', 'flex');
    }
    function closeReverseModal() {
        document.getElementById('reverse-modal').classList.replace('flex', 'hidden');
    }
</script>
@endpush
