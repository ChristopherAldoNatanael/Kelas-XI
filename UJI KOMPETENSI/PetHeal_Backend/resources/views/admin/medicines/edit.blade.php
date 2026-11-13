@extends('layouts.admin')

@section('title', __('admin.edit_medicine') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.edit_medicine'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="$medicine->name"
        :eyebrow="__('admin.edit_medicine')"
        icon="medication"
        :description="__('admin.edit_medicine_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.medicines.expiry') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">event_busy</span>
                {{ __('inventory.action_view_expiry') }}
            </a>
            <a href="{{ route('admin.medicines.mutations.index', ['medicine_id' => $medicine->id]) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">swap_horiz</span>
                {{ __('inventory.action_view_mutations') }}
            </a>
            <a href="{{ route('admin.medicines.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.4fr_1fr]">
        <x-admin.panel :title="__('admin.medicine_information')" :subtitle="__('admin.medicine_information_desc')">
            <form method="POST" action="{{ route('admin.medicines.update', $medicine->id) }}" class="space-y-8">
                @csrf
                @method('PUT')

                @if($errors->any())
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label for="name" class="admin-label">{{ __('admin.medicine_name') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $medicine->name) }}" required class="admin-input">
                        @error('name') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="unit" class="admin-label">{{ __('admin.medicine_unit') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="unit" id="unit" value="{{ old('unit', $medicine->unit) }}" required class="admin-input">
                        @error('unit') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="form" class="admin-label">{{ __('admin.medicine_form') }}</label>
                        <input type="text" name="form" id="form" value="{{ old('form', $medicine->form) }}" class="admin-input">
                    </div>
                    <div>
                        <label for="min_stock" class="admin-label">{{ __('admin.medicine_min_stock') }}</label>
                        <input type="number" name="min_stock" id="min_stock" min="0" step="0.01"
                               value="{{ old('min_stock', $medicine->min_stock) }}" class="admin-input">
                        <p class="admin-hint">{{ __('admin.medicine_min_stock_hint') }}</p>
                    </div>
                    <div>
                        <label for="price" class="admin-label">{{ __('admin.medicine_price') }}</label>
                        <input type="number" name="price" id="price" min="0" step="0.01"
                               value="{{ old('price', $medicine->price) }}" class="admin-input">
                    </div>
                    <div>
                        <label for="expiry_date" class="admin-label">{{ __('admin.medicine_expiry') }}</label>
                        <input type="date" name="expiry_date" id="expiry_date"
                               value="{{ old('expiry_date', $medicine->expiry_date?->format('Y-m-d')) }}" class="admin-input">
                    </div>
                    <div class="md:col-span-2">
                        <label for="manufacturer" class="admin-label">{{ __('admin.medicine_manufacturer') }}</label>
                        <input type="text" name="manufacturer" id="manufacturer"
                               value="{{ old('manufacturer', $medicine->manufacturer) }}" class="admin-input">
                    </div>
                    <div class="md:col-span-2">
                        <label for="notes" class="admin-label">{{ __('admin.notes') }}</label>
                        <textarea name="notes" id="notes" rows="3" class="admin-input">{{ old('notes', $medicine->notes) }}</textarea>
                    </div>
                </div>

                <label class="flex cursor-pointer items-center gap-3">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $medicine->is_active))
                           class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('admin.medicine_active') }}</span>
                </label>

                <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6 dark:border-slate-800">
                    <button type="submit" class="admin-btn-primary">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                        {{ __('admin.save_medicine') }}
                    </button>
                    <a href="{{ route('admin.medicines.index') }}" class="admin-btn-ghost">{{ __('common.cancel') }}</a>
                </div>
            </form>
        </x-admin.panel>

        <div class="space-y-6">
            {{-- Stok tidak diedit di form: diubah lewat dua aksi yang tercatat. --}}
            <x-admin.panel :title="__('admin.medicine_stock')" :subtitle="__('admin.medicine_stock_desc')">
                <div class="rounded-2xl border border-slate-200/70 p-5 dark:border-slate-700">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.medicine_stock_current') }}</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums {{ $medicine->isLowStock() ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white' }}">
                        {{ rtrim(rtrim(number_format((float) $medicine->stock, 2, ',', '.'), '0'), ',') }}
                        <span class="text-sm font-medium text-slate-500">{{ $medicine->unit }}</span>
                    </p>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('inventory.medicine_available_stock') }}</p>
                    @if((float) $medicine->quarantined_stock > 0)
                        <div class="mt-3 flex items-center justify-between rounded-xl border border-orange-200 bg-orange-50 px-3.5 py-2.5 dark:border-orange-900/60 dark:bg-orange-900/20">
                            <span class="text-xs font-semibold text-orange-800 dark:text-orange-300">{{ __('inventory.medicine_quarantine_stock') }}</span>
                            <span class="text-sm font-bold tabular-nums text-orange-800 dark:text-orange-300">
                                {{ rtrim(rtrim(number_format((float) $medicine->quarantined_stock, 2, ',', '.'), '0'), ',') }}
                                {{ $medicine->unit }}
                            </span>
                        </div>
                    @endif
                    @if($medicine->isLowStock())
                        <p class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">{{ __('admin.medicines_low_stock') }}</p>
                    @endif
                </div>

                {{-- Status kedaluwarsa + aksi penanganannya. --}}
                @php
                    $expiryStatus = $medicine->expiryStatus();
                    $daysLeft = $medicine->expiryDaysLeft();
                @endphp

                @if($expiryStatus !== 'none')
                    <div class="mt-4 rounded-2xl border p-4
                        {{ $expiryStatus === 'expired'
                            ? 'border-rose-200 bg-rose-50 dark:border-rose-900/60 dark:bg-rose-900/20'
                            : ($expiryStatus === 'expiring'
                                ? 'border-amber-200 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-900/20'
                                : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/60') }}">
                        <div class="flex items-start gap-2.5">
                            <span class="material-symbols-outlined text-[20px] {{ $expiryStatus === 'expired' ? 'text-rose-600 dark:text-rose-400' : ($expiryStatus === 'expiring' ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400') }}" aria-hidden="true">event_busy</span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold {{ $expiryStatus === 'expired' ? 'text-rose-700 dark:text-rose-300' : ($expiryStatus === 'expiring' ? 'text-amber-800 dark:text-amber-300' : 'text-slate-700 dark:text-slate-300') }}">
                                    @if($expiryStatus === 'expired')
                                        {{ __('admin.medicine_expired_since', ['days' => abs((int) $daysLeft)]) }}
                                    @elseif($expiryStatus === 'expiring')
                                        {{ __('admin.medicine_expires_in', ['days' => (int) $daysLeft]) }}
                                    @else
                                        {{ __('admin.medicine_expiry_safe') }}
                                    @endif
                                </p>
                                <p class="mt-1 text-xs leading-5 {{ $expiryStatus === 'expired' ? 'text-rose-700/80 dark:text-rose-300/80' : 'text-slate-500 dark:text-slate-400' }}">
                                    {{ $expiryStatus === 'expired' ? __('admin.medicine_expired_action_hint') : __('admin.medicine_expiring_action_hint') }}
                                </p>

                                @if($medicine->isDisposed())
                                    <p class="mt-2 text-xs font-semibold text-violet-700 dark:text-violet-300">
                                        {{ __('admin.medicine_disposed_at', ['date' => $medicine->disposed_at->format('d M Y H:i')]) }}
                                        @if($medicine->disposal_method)
                                            · {{ $medicine->disposal_method_label }}
                                        @endif
                                        @if($medicine->disposed_quantity)
                                            · {{ rtrim(rtrim(number_format((float) $medicine->disposed_quantity, 2, ',', '.'), '0'), ',') }} {{ $medicine->unit }}
                                        @endif
                                    </p>
                                    @if($medicine->disposal_reason)
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $medicine->disposal_reason }}</p>
                                    @endif
                                @endif
                            </div>
                        </div>

                        @if(!$medicine->isDisposed() && (float) $medicine->stock > 0)
                            <form method="POST" action="{{ route('admin.medicines.dispose', $medicine->id) }}"
                                  class="mt-4 space-y-2.5 border-t border-black/5 pt-3.5 dark:border-white/10"
                                  onsubmit="return confirm(@json(__('admin.medicine_dispose_confirm')))">
                                @csrf
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div>
                                        <label for="dispose_quantity" class="admin-label">{{ __('admin.medicine_disposal_quantity') }}</label>
                                        <input type="number" name="quantity" id="dispose_quantity" min="0.01" step="0.01"
                                               max="{{ (float) $medicine->stock }}"
                                               value="{{ (float) $medicine->stock }}" class="admin-input">
                                        <p class="admin-hint">{{ __('admin.medicine_disposal_quantity_hint_short') }}</p>
                                    </div>
                                    <div>
                                        <label for="dispose_method" class="admin-label">{{ __('admin.medicine_disposal_method') }}</label>
                                        <select name="method" id="dispose_method" required class="admin-input">
                                            <option value="destroyed">{{ __('admin.medicine_disposal_method_destroyed') }}</option>
                                            <option value="returned_supplier">{{ __('admin.medicine_disposal_method_returned') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label for="dispose_reason" class="admin-label">{{ __('admin.medicine_disposal_reason') }} <span class="text-rose-500">*</span></label>
                                    <input type="text" name="reason" id="dispose_reason" required class="admin-input"
                                           placeholder="{{ __('admin.medicine_disposal_reason_placeholder') }}">
                                </div>
                                <button type="submit" class="admin-btn-danger w-full !justify-center">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete_forever</span>
                                    {{ __('admin.medicine_dispose_action') }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.medicines.stock-in', $medicine->id) }}" class="mt-5 space-y-3">
                    @csrf
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('admin.medicine_stock_in') }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="quantity" class="admin-label">{{ __('admin.medicine_quantity') }}</label>
                            <input type="number" name="quantity" id="quantity" min="0.01" step="0.01" required class="admin-input">
                        </div>
                        <div>
                            <label for="reason" class="admin-label">{{ __('admin.medicine_reason') }}</label>
                            <input type="text" name="reason" id="reason" class="admin-input" placeholder="{{ __('admin.medicine_reason_placeholder_in') }}">
                        </div>
                        <div>
                            <label for="batch_number" class="admin-label">{{ __('inventory.stock_in_batch_number') }}</label>
                            <input type="text" name="batch_number" id="batch_number" maxlength="80" class="admin-input">
                            <p class="admin-hint">{{ __('inventory.stock_in_batch_number_hint') }}</p>
                        </div>
                        <div>
                            <label for="batch_expiry" class="admin-label">{{ __('inventory.stock_in_expiry') }}</label>
                            <input type="date" name="expiry_date" id="batch_expiry" class="admin-input">
                            <p class="admin-hint">{{ __('inventory.stock_in_expiry_hint') }}</p>
                        </div>
                    </div>
                    <button type="submit" class="admin-btn-secondary w-full justify-center">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_box</span>
                        {{ __('admin.medicine_stock_in_action') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.medicines.adjust', $medicine->id) }}"
                      class="mt-6 space-y-3 border-t border-slate-100 pt-5 dark:border-slate-800">
                    @csrf
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('admin.medicine_adjust') }}</p>
                    <p class="admin-hint">{{ __('admin.medicine_adjust_hint') }}</p>
                    <div>
                        <label for="target_stock" class="admin-label">{{ __('admin.medicine_stock_target') }}</label>
                        <input type="number" name="target_stock" id="target_stock" min="0" step="0.01"
                               value="{{ old('target_stock', $medicine->stock) }}" required class="admin-input">
                    </div>
                    <div>
                        <label for="adjust_reason" class="admin-label">{{ __('admin.medicine_adjust_reason') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="reason" id="adjust_reason" required class="admin-input"
                               placeholder="Hasil hitung fisik 10 Nov: 42">
                    </div>
                    <button type="submit" class="admin-btn-secondary !w-full !justify-center">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">published_with_changes</span>
                        {{ __('admin.medicine_adjust_save') }}
                    </button>
                </form>
            </x-admin.panel>

            {{-- Batch adalah sumber kebenaran stok; saldo obat hanya totalnya. --}}
            <x-admin.panel :title="__('inventory.batches_title')" :subtitle="__('inventory.batches_desc')">
                <x-slot:actions>
                    <a href="{{ route('admin.medicines.mutations.create', ['medicine_id' => $medicine->id]) }}"
                       class="admin-btn-secondary !px-3 !py-1.5 !text-[11px]">
                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">add</span>
                        {{ __('inventory.mutations_create') }}
                    </a>
                </x-slot:actions>

                @forelse($batches as $batch)
                    @php
                        $batchStatus = $batch->expiryStatus();
                        $batchDaysLeft = $batch->expiryDaysLeft();
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 py-3 last:border-0 dark:border-slate-800">
                        <div class="min-w-0">
                            <p class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $batch->batch_number }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('admin.medicine_expiry') }}: {{ $batch->expiryDateLabel() }}
                                @if($batchDaysLeft !== null && $batchDaysLeft < 0)
                                    · <span class="font-semibold text-rose-600 dark:text-rose-400">{{ __('inventory.expiry_days_ago', ['days' => abs($batchDaysLeft)]) }}</span>
                                @elseif($batchStatus === 'expiring')
                                    · <span class="font-semibold text-amber-600 dark:text-amber-400">{{ __('inventory.expiry_days_left', ['days' => (int) $batchDaysLeft]) }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="text-right">
                                <p class="text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">
                                    {{ rtrim(rtrim(number_format((float) $batch->stock, 2, ',', '.'), '0'), ',') }}
                                    <span class="text-xs font-medium text-slate-500">{{ $medicine->unit }}</span>
                                </p>
                                @if((float) $batch->quarantined_stock > 0)
                                    <p class="text-[11px] font-semibold text-orange-600 dark:text-orange-400">
                                        {{ __('inventory.stock_state_quarantine') }}:
                                        {{ rtrim(rtrim(number_format((float) $batch->quarantined_stock, 2, ',', '.'), '0'), ',') }}
                                    </p>
                                @endif
                            </div>
                            @if((float) $batch->stock > 0)
                                <a href="{{ route('admin.medicines.mutations.create', [
                                        'medicine_id' => $medicine->id,
                                        'batch_id' => $batch->id,
                                        'type' => \App\Models\MedicineStockTransaction::TYPE_QUARANTINE,
                                    ]) }}"
                                   class="admin-icon-btn hover:!text-orange-600"
                                   title="{{ __('inventory.action_quarantine') }}">
                                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">inventory_2</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-admin.empty-state
                        :title="__('inventory.empty_batch_title')"
                        :description="__('inventory.empty_batch_desc')"
                        icon="inventory_2"
                        tone="slate" />
                @endforelse
            </x-admin.panel>

            <x-admin.panel :title="__('admin.medicines_movements')" :subtitle="__('admin.medicines_movements_desc')">
                <x-slot:actions>
                    <a href="{{ route('admin.medicines.movements', $medicine->id) }}"
                       class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                        {{ __('admin.medicines_movements_view_all') }}
                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">arrow_forward</span>
                    </a>
                </x-slot:actions>

                @forelse($movements as $movement)
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 py-2.5 last:border-0 dark:border-slate-800">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $movement->type_label }}</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ __('inventory.movement_batch') }}: {{ $movement->batch?->batch_number ?? '—' }}
                                · {{ $movement->stockStateLabel() }}
                            </p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $movement->reason ?: '—' }}</p>
                            <p class="text-[11px] text-slate-400">{{ $movement->created_at?->format('d M Y H:i') }}</p>
                        </div>
                        <p class="whitespace-nowrap text-sm font-bold tabular-nums {{ (float) $movement->quantity >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ (float) $movement->quantity >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $movement->quantity, 2, ',', '.'), '0'), ',') }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('admin.medicines_movements_empty') }}</p>
                @endforelse
            </x-admin.panel>
        </div>
    </div>
</div>
@endsection
