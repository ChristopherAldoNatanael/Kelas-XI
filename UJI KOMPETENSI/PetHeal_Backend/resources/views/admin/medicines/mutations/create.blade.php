@extends('layouts.admin')

@section('title', __('inventory.create_title') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('inventory.create_title'))

@section('content')
@php
    $selectedType = old('type', request('type', $source
        ? \App\Models\MedicineStockTransaction::TYPE_RETURN_SUPPLIER
        : \App\Models\MedicineStockTransaction::TYPE_QUARANTINE));

    // Tindak lanjut karantina hanya boleh retur atau pemusnahan.
    if ($source && !in_array($selectedType, [
        \App\Models\MedicineStockTransaction::TYPE_RETURN_SUPPLIER,
        \App\Models\MedicineStockTransaction::TYPE_DISPOSAL,
    ], true)) {
        $selectedType = \App\Models\MedicineStockTransaction::TYPE_RETURN_SUPPLIER;
    }
    $selectedMedicine = old('medicine_id', $source?->medicine_id ?? request('medicine_id'));
    $selectedBatch = old('batch_id', $source?->batch_id ?? request('batch_id'));
    $selectedFromQuarantine = old('from_quarantine', $source ? 1 : 0);
@endphp

<div class="space-y-6">
    <x-admin.page-header
        :title="__('inventory.create_title')"
        :eyebrow="__('inventory.eyebrow')"
        icon="swap_horiz"
        :description="__('inventory.create_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.medicines.mutations.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.medicines.partials.section-nav')

    @if(session('error'))
        <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm font-medium text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($source)
        <div class="flex items-start gap-3 rounded-2xl border border-orange-200 bg-orange-50 px-5 py-3.5 text-sm text-orange-800 dark:border-orange-900/60 dark:bg-orange-900/20 dark:text-orange-300">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">inventory_2</span>
            <div>
                <p class="font-semibold">{{ __('inventory.source_quarantine', ['reference' => $source->reference]) }}</p>
                <p class="mt-0.5 text-xs">
                    {{ __('inventory.source_quarantine_hint', [
                        'qty' => rtrim(rtrim(number_format($source->remainingFollowUpQuantity(), 2, ',', '.'), '0'), ','),
                        'unit' => $source->medicine?->unit,
                    ]) }}
                </p>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.medicines.mutations.store') }}"
          enctype="multipart/form-data" id="mutation-form" class="grid grid-cols-1 gap-6 xl:grid-cols-[1.5fr_1fr]">
        @csrf
        <input type="hidden" name="from_quarantine" id="from_quarantine" value="{{ $selectedFromQuarantine ? '1' : '0' }}">
        @if($source)
            <input type="hidden" name="source_transaction_id" value="{{ $source->id }}">
        @endif

        <div class="space-y-6">
            <x-admin.panel :title="__('inventory.section_target')">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="type" class="admin-label">{{ __('inventory.field_type') }} <span class="text-rose-500">*</span></label>
                        <select name="type" id="type" required class="admin-input" @if($source) disabled @endif>
                            @foreach(\App\Models\MedicineStockTransaction::TYPES as $typeOption)
                                @continue($source && !in_array($typeOption, [
                                    \App\Models\MedicineStockTransaction::TYPE_RETURN_SUPPLIER,
                                    \App\Models\MedicineStockTransaction::TYPE_DISPOSAL,
                                ], true))
                                <option value="{{ $typeOption }}" @selected($selectedType === $typeOption)>
                                    {{ __('inventory.type_' . $typeOption) }}
                                </option>
                            @endforeach
                        </select>
                        @if($source)
                            {{-- Select disabled agar nilainya tidak hilang saat submit. --}}
                            <input type="hidden" name="type" value="{{ $selectedType }}">
                        @endif
                        <p class="admin-hint" id="type-hint"></p>
                    </div>

                    <div>
                        <label for="medicine_id" class="admin-label">{{ __('inventory.field_medicine') }} <span class="text-rose-500">*</span></label>
                        <select name="medicine_id" id="medicine_id" required class="admin-input">
                            <option value="">{{ __('inventory.select_medicine') }}</option>
                            @foreach($medicines as $medicine)
                                <option value="{{ $medicine->id }}" @selected((string) $selectedMedicine === (string) $medicine->id)>
                                    {{ $medicine->name }} ({{ $medicine->unit }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="batch_id" class="admin-label">{{ __('inventory.field_batch') }} <span class="text-rose-500">*</span></label>
                        <select name="batch_id" id="batch_id" required class="admin-input">
                            <option value="">{{ __('inventory.select_batch') }}</option>
                        </select>
                        <p class="admin-hint hidden" id="no-batch-hint">{{ __('inventory.no_batch') }}</p>
                    </div>

                    <div>
                        <label for="quantity" class="admin-label">{{ __('inventory.field_quantity') }} <span class="text-rose-500">*</span></label>
                        <input type="number" name="quantity" id="quantity" min="0.01" step="0.01" required
                               value="{{ old('quantity') }}" class="admin-input">
                        <p class="admin-hint" id="quantity-hint"></p>
                    </div>

                    <div>
                        <label for="destination" class="admin-label">{{ __('inventory.field_destination') }}</label>
                        <input type="text" name="destination" id="destination"
                               value="{{ old('destination') }}" class="admin-input"
                               placeholder="{{ __('inventory.field_destination_placeholder') }}">
                    </div>

                    <div class="md:col-span-2">
                        <label for="reason" class="admin-label">{{ __('inventory.field_reason') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="reason" id="reason" required maxlength="255"
                               value="{{ old('reason') }}" class="admin-input"
                               placeholder="{{ __('inventory.field_reason_placeholder') }}">
                    </div>

                    <div class="md:col-span-2">
                        <label for="notes" class="admin-label">{{ __('inventory.field_notes') }}</label>
                        <textarea name="notes" id="notes" rows="3" class="admin-input">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </x-admin.panel>

            {{-- Retur supplier --}}
            <x-admin.panel :title="__('inventory.section_return')" id="section-return" class="hidden">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="supplier_name" class="admin-label">{{ __('inventory.field_supplier') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="supplier_name" id="supplier_name" maxlength="150"
                               value="{{ old('supplier_name', $source?->supplier_name) }}" class="admin-input">
                    </div>
                    <div>
                        <label for="return_date" class="admin-label">{{ __('inventory.field_return_date') }} <span class="text-rose-500">*</span></label>
                        <input type="date" name="return_date" id="return_date"
                               value="{{ old('return_date', now()->format('Y-m-d')) }}" class="admin-input">
                    </div>
                    <div class="md:col-span-2">
                        <label for="document" class="admin-label">{{ __('inventory.field_document') }}</label>
                        <input type="file" name="document" id="document" accept=".pdf,.jpg,.jpeg,.png,.webp" class="admin-input">
                        <p class="admin-hint">{{ __('inventory.document_hint', ['size' => (int) config('medicines.document_max_kb', 4096) / 1024]) }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex cursor-pointer items-center gap-3">
                            <input type="checkbox" id="from_quarantine_check" value="1"
                                   @checked($selectedFromQuarantine) @disabled($source)
                                   class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('inventory.from_quarantine') }}</span>
                        </label>
                    </div>
                </div>
            </x-admin.panel>

            {{-- Pemusnahan --}}
            <x-admin.panel :title="__('inventory.section_disposal')" id="section-disposal" class="hidden">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="destruction_date" class="admin-label">{{ __('inventory.field_destruction_date') }} <span class="text-rose-500">*</span></label>
                        <input type="date" name="destruction_date" id="destruction_date"
                               value="{{ old('destruction_date', now()->format('Y-m-d')) }}" class="admin-input">
                    </div>
                    <div>
                        <label for="destruction_method" class="admin-label">{{ __('inventory.field_destruction_method') }} <span class="text-rose-500">*</span></label>
                        <select name="destruction_method" id="destruction_method" class="admin-input">
                            @foreach($destructionMethods as $method)
                                <option value="{{ $method }}" @selected(old('destruction_method') === $method)>
                                    {{ __('inventory.method_' . $method) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="witness_name" class="admin-label">{{ __('inventory.field_witness') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="witness_name" id="witness_name" maxlength="150"
                               value="{{ old('witness_name') }}" class="admin-input">
                    </div>
                    <div>
                        <label for="minutes" class="admin-label">{{ __('inventory.field_minutes') }}</label>
                        <input type="file" name="minutes" id="minutes" accept=".pdf,.jpg,.jpeg,.png,.webp" class="admin-input">
                        <p class="admin-hint">{{ __('inventory.document_hint', ['size' => (int) config('medicines.document_max_kb', 4096) / 1024]) }}</p>
                    </div>
                </div>
                <p class="admin-hint mt-3">{{ __('inventory.type_hint_disposal') }}</p>
            </x-admin.panel>

            {{-- Karantina --}}
            <x-admin.panel :title="__('inventory.section_quarantine')" id="section-quarantine" class="hidden">
                <div>
                    <label for="quarantine_location" class="admin-label">{{ __('inventory.field_quarantine_location') }} <span class="text-rose-500">*</span></label>
                    <input type="text" name="quarantine_location" id="quarantine_location" maxlength="100"
                           value="{{ old('quarantine_location') }}" class="admin-input"
                           placeholder="{{ __('inventory.field_quarantine_location_placeholder') }}">
                </div>
                <p class="admin-hint mt-3">{{ __('inventory.type_hint_quarantine') }}</p>
            </x-admin.panel>

            {{-- Penyesuaian --}}
            <x-admin.panel :title="__('inventory.section_adjustment')" id="section-adjustment" class="hidden">
                <div>
                    <label for="direction" class="admin-label">{{ __('inventory.field_direction') }} <span class="text-rose-500">*</span></label>
                    <select name="direction" id="direction" class="admin-input">
                        <option value="out" @selected(old('direction') === 'out')>{{ __('inventory.direction_out') }}</option>
                        <option value="in" @selected(old('direction') === 'in')>{{ __('inventory.direction_in') }}</option>
                    </select>
                </div>
                <p class="admin-hint mt-3">{{ __('inventory.type_hint_adjustment') }}</p>
            </x-admin.panel>
        </div>

        {{-- Pratinjau + submit --}}
        <div class="space-y-6">
            <x-admin.panel :title="__('inventory.preview_title')">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('inventory.field_type') }}</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-200" id="preview-type">—</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('inventory.field_batch') }}</dt>
                        <dd class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-200" id="preview-batch">—</dd>
                    </div>
                    <div class="border-t border-slate-100 pt-3 dark:border-slate-800">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('inventory.preview_available') }}</dt>
                            <dd class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                                <span id="preview-available-before">0</span> → <span id="preview-available-after" class="text-primary">0</span>
                            </dd>
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('inventory.preview_quarantine') }}</dt>
                            <dd class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                                <span id="preview-quarantine-before">0</span> → <span id="preview-quarantine-after" class="text-orange-500">0</span>
                            </dd>
                        </div>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3.5 py-3 text-xs leading-5 text-slate-600 dark:bg-slate-800/60 dark:text-slate-300" id="preview-effect">
                        —
                    </div>
                </dl>
            </x-admin.panel>

            <x-admin.panel>
                <div class="space-y-3">
                    <button type="submit" id="mutation-submit" class="admin-btn-primary w-full !justify-center">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                        <span id="mutation-submit-label">{{ __('inventory.save_mutation') }}</span>
                    </button>
                    <a href="{{ route('admin.medicines.mutations.index') }}" class="admin-btn-ghost w-full !justify-center">
                        {{ __('admin.cancel') }}
                    </a>
                </div>
            </x-admin.panel>
        </div>
    </form>
</div>

{{-- Dialog konfirmasi. Aksi stok penting tidak boleh terjadi karena salah klik. --}}
<div id="mutation-confirm-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">warning</span>
            </span>
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('inventory.confirm_title') }}</h3>
                <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('inventory.confirm_text') }}</p>
            </div>
        </div>
        <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm dark:bg-slate-800/60" id="confirm-summary"></div>
        <div class="mt-5 flex items-center justify-end gap-2">
            <button type="button" class="admin-btn-secondary" onclick="closeMutationConfirm()">{{ __('admin.cancel') }}</button>
            <button type="button" class="admin-btn-primary" id="mutation-confirm-btn">{{ __('inventory.confirm_submit') }}</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const batches = @json($batchesData);
        const units = @json($medicines->mapWithKeys(fn ($m) => [$m->id => $m->unit]));
        const sourceId = @json($source?->id);

        const form = document.getElementById('mutation-form');
        const typeEl = document.getElementById('type');
        const medicineEl = document.getElementById('medicine_id');
        const batchEl = document.getElementById('batch_id');
        const quantityEl = document.getElementById('quantity');
        const fromQuarantineHidden = document.getElementById('from_quarantine');
        const fromQuarantineCheck = document.getElementById('from_quarantine_check');
        const noBatchHint = document.getElementById('no-batch-hint');
        let batchValue = @json($selectedBatch);
        const batchOptionLabel = @json(__('inventory.batch_option'));
        const batchOptionQuarantineLabel = @json(__('inventory.batch_option_quarantine'));

        const sections = {
            return_supplier: document.getElementById('section-return'),
            disposal: document.getElementById('section-disposal'),
            quarantine: document.getElementById('section-quarantine'),
            damaged: null,
            adjustment: document.getElementById('section-adjustment'),
        };

        const hints = {
            return_supplier: @json(__('inventory.type_hint_return_supplier')),
            disposal: @json(__('inventory.type_hint_disposal')),
            quarantine: @json(__('inventory.type_hint_quarantine')),
            damaged: @json(__('inventory.type_hint_damaged')),
            adjustment: @json(__('inventory.type_hint_adjustment')),
        };

        const effects = {
            quarantine: @json(__('inventory.preview_effect_quarantine')),
            return_available: @json(__('inventory.preview_effect_return_available')),
            return_quarantine: @json(__('inventory.preview_effect_return_quarantine')),
            disposal: @json(__('inventory.preview_effect_disposal')),
            damaged: @json(__('inventory.preview_effect_damaged')),
            adjustment_in: @json(__('inventory.preview_effect_adjustment_in')),
            adjustment_out: @json(__('inventory.preview_effect_adjustment_out')),
        };

        const format = (value) => {
            const number = Number(value) || 0;
            return number.toLocaleString('id-ID', { maximumFractionDigits: 2 });
        };

        function batchOptions() {
            const medicineId = medicineEl.value;
            const type = typeEl.value;
            const matching = batches.filter((batch) => String(batch.medicine_id) === String(medicineId));

            batchEl.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = @json(__('inventory.select_batch'));
            batchEl.appendChild(placeholder);

            matching.forEach((batch) => {
                const option = document.createElement('option');
                const useQuarantine = type === 'disposal'
                    || (type === 'return_supplier' && fromQuarantineHidden.value === '1');
                const stock = useQuarantine ? batch.quarantined_stock : batch.stock;
                const labelTemplate = useQuarantine ? batchOptionQuarantineLabel : batchOptionLabel;

                option.value = batch.id;
                option.dataset.stock = batch.stock;
                option.dataset.quarantined = batch.quarantined_stock;
                option.dataset.batchNumber = batch.batch_number;
                option.dataset.expiry = batch.expiry_date || '—';
                option.dataset.expiryStatus = batch.expiry_status;
                option.textContent = labelTemplate
                    .replace(':batch', batch.batch_number)
                    .replace(':expiry', batch.expiry_date || '—')
                    .replace(':stock', format(stock))
                    .replace(':unit', units[medicineId] || '');
                batchEl.appendChild(option);
            });

            if (matching.length === 0) {
                noBatchHint.classList.remove('hidden');
            } else {
                noBatchHint.classList.add('hidden');
            }

            if (batchValue && matching.some((batch) => String(batch.id) === String(batchValue))) {
                batchEl.value = batchValue;
            }

            updatePreview();
        }

        function selectedBatch() {
            return batches.find((batch) => String(batch.id) === String(batchEl.value));
        }

        function updateSections() {
            const type = typeEl.value;
            Object.entries(sections).forEach(([key, section]) => {
                if (!section) return;
                section.classList.toggle('hidden', key !== type);
            });

            document.getElementById('type-hint').textContent = hints[type] || '';

            if (type === 'disposal') {
                fromQuarantineHidden.value = '1';
            } else if (type === 'return_supplier') {
                fromQuarantineHidden.value = fromQuarantineCheck && fromQuarantineCheck.checked ? '1' : '0';
            } else {
                fromQuarantineHidden.value = '0';
            }

            document.getElementById('preview-type').textContent = typeEl.options[typeEl.selectedIndex]?.textContent.trim() || '—';
        }

        function updatePreview() {
            const type = typeEl.value;
            const batch = selectedBatch();
            const quantity = Number(quantityEl.value) || 0;
            const unit = units[medicineEl.value] || '';

            const availableBefore = batch ? Number(batch.stock) : 0;
            const quarantineBefore = batch ? Number(batch.quarantined_stock) : 0;
            let availableAfter = availableBefore;
            let quarantineAfter = quarantineBefore;
            let effect = '—';

            if (type === 'quarantine') {
                availableAfter -= quantity;
                quarantineAfter += quantity;
                effect = effects.quarantine.replace(':qty', format(quantity)).replace(':unit', unit);
            } else if (type === 'return_supplier') {
                if (fromQuarantineHidden.value === '1') {
                    quarantineAfter -= quantity;
                    effect = effects.return_quarantine.replace(':qty', format(quantity)).replace(':unit', unit);
                } else {
                    availableAfter -= quantity;
                    effect = effects.return_available.replace(':qty', format(quantity)).replace(':unit', unit);
                }
            } else if (type === 'disposal') {
                quarantineAfter -= quantity;
                effect = effects.disposal.replace(':qty', format(quantity)).replace(':unit', unit);
            } else if (type === 'damaged') {
                availableAfter -= quantity;
                effect = effects.damaged.replace(':qty', format(quantity)).replace(':unit', unit);
            } else if (type === 'adjustment') {
                if (document.getElementById('direction').value === 'in') {
                    availableAfter += quantity;
                    effect = effects.adjustment_in.replace(':qty', format(quantity)).replace(':unit', unit);
                } else {
                    availableAfter -= quantity;
                    effect = effects.adjustment_out.replace(':qty', format(quantity)).replace(':unit', unit);
                }
            }

            document.getElementById('preview-batch').textContent = batch ? batch.batch_number : '—';
            document.getElementById('preview-available-before').textContent = format(availableBefore);
            document.getElementById('preview-available-after').textContent = format(Math.max(0, availableAfter));
            document.getElementById('preview-quarantine-before').textContent = format(quarantineBefore);
            document.getElementById('preview-quarantine-after').textContent = format(Math.max(0, quarantineAfter));
            document.getElementById('preview-effect').textContent = effect;

            const quantityHint = document.getElementById('quantity-hint');
            if (batch) {
                quantityHint.textContent = @json(__('inventory.preview_available')) + ': ' + format(availableBefore)
                    + ' ' + unit + ' · ' + @json(__('inventory.preview_quarantine')) + ': ' + format(quarantineBefore) + ' ' + unit;
            } else {
                quantityHint.textContent = '';
            }
        }

        function openMutationConfirm() {
            const type = typeEl.value;
            const batch = selectedBatch();
            const quantity = Number(quantityEl.value) || 0;
            const unit = units[medicineEl.value] || '';

            document.getElementById('confirm-summary').textContent =
                (typeEl.options[typeEl.selectedIndex]?.textContent.trim() || '') + ' · '
                + (batch ? batch.batch_number : '—') + ' · '
                + format(quantity) + ' ' + unit;

            const modal = document.getElementById('mutation-confirm-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.getElementById('mutation-confirm-btn').focus();
        }

        window.closeMutationConfirm = function () {
            const modal = document.getElementById('mutation-confirm-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            openMutationConfirm();
        });

        document.getElementById('mutation-confirm-btn').addEventListener('click', function () {
            const button = document.getElementById('mutation-submit');
            const label = document.getElementById('mutation-submit-label');
            button.disabled = true;
            button.classList.add('opacity-60', 'cursor-wait');
            label.textContent = @json(__('inventory.processing'));
            closeMutationConfirm();
            form.submit();
        });

        medicineEl.addEventListener('change', function () {
            batchValue = null;
            batchOptions();
        });
        typeEl.addEventListener('change', function () {
            updateSections();
            batchOptions();
        });
        batchEl.addEventListener('change', updatePreview);
        quantityEl.addEventListener('input', updatePreview);
        document.getElementById('direction').addEventListener('change', updatePreview);
        if (fromQuarantineCheck) {
            fromQuarantineCheck.addEventListener('change', function () {
                fromQuarantineHidden.value = this.checked ? '1' : '0';
                batchOptions();
            });
        }

        updateSections();
        batchOptions();
    })();
</script>
@endpush
