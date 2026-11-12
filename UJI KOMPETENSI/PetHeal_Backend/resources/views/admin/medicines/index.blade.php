@extends('layouts.admin')

@section('title', __('admin.medicines_title') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.medicines_title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('admin.medicines_title')"
        :eyebrow="__('admin.eyebrow_management')"
        icon="medication"
        :description="__('admin.medicines_desc')">
        <x-slot:actions>
            <button type="button" onclick="openMedicineImportModal()" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">upload_file</span>
                {{ __('admin.import_medicines') }}
            </button>
            <a href="{{ route('admin.medicines.create') }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                {{ __('admin.add_medicine') }}
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
            <span class="material-symbols-outlined" aria-hidden="true">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if(session('warning'))
        <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3.5 text-sm font-medium text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-300">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">warning</span>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    {{-- Hasil import terakhir pada sesi ini. Angka datang dari importer,
         bukan dihitung ulang di view. --}}
    @if(session('medicine_import_summary'))
        @php($sum = session('medicine_import_summary'))
        <x-admin.panel :title="__('admin.medicine_import_result_title')" :subtitle="__('admin.medicine_import_result_desc')">
            <x-slot:actions>
                @if(session('medicine_import_has_errors'))
                    <a href="{{ route('admin.medicines.import.error-report', ['format' => 'csv']) }}"
                       class="admin-btn-secondary !px-3 !py-1.5 !text-[11px]">
                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">download</span>
                        CSV
                    </a>
                    <a href="{{ route('admin.medicines.import.error-report', ['format' => 'xlsx']) }}"
                       class="admin-btn-secondary !px-3 !py-1.5 !text-[11px]">
                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">grid_on</span>
                        Excel
                    </a>
                @endif
            </x-slot:actions>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                @foreach([
                    ['label' => __('admin.medicine_import_rows'), 'value' => $sum['total'] ?? 0, 'class' => 'text-slate-900 dark:text-white'],
                    ['label' => __('admin.medicine_import_new'), 'value' => $sum['success'] ?? 0, 'class' => 'text-emerald-600 dark:text-emerald-400'],
                    ['label' => __('admin.medicine_import_updated'), 'value' => $sum['updated'] ?? 0, 'class' => 'text-blue-600 dark:text-blue-400'],
                    ['label' => __('admin.medicine_import_skipped'), 'value' => $sum['skipped'] ?? 0, 'class' => 'text-slate-700 dark:text-slate-200'],
                    ['label' => __('admin.medicine_import_failed'), 'value' => $sum['failed'] ?? 0, 'class' => ($sum['failed'] ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-700 dark:text-slate-200'],
                ] as $tile)
                    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-800">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ $tile['label'] }}</p>
                        <p class="mt-1.5 text-2xl font-bold tabular-nums {{ $tile['class'] }}">{{ $tile['value'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-admin.panel>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.stat-card
            :label="__('admin.medicines_total')"
            :value="$medicines->total()"
            icon="medication"
            tone="primary" />
        <x-admin.stat-card
            :label="__('admin.medicines_low_stock')"
            :value="$lowStockCount"
            icon="inventory_2"
            tone="{{ $lowStockCount > 0 ? 'rose' : 'slate' }}"
            :hint="__('admin.medicines_low_stock_hint')" />
        <x-admin.stat-card
            :label="__('admin.medicines_expiring')"
            :value="$expiringCount"
            icon="event_busy"
            tone="{{ $expiringCount > 0 ? 'amber' : 'slate' }}"
            :hint="__('admin.medicines_expiring_hint')" />
    </div>

    <x-admin.panel :title="__('admin.medicines_title')" :subtitle="__('admin.medicines_desc')" flush>
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.medicines.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}"
                       placeholder="{{ __('admin.search_medicine') }}" class="admin-input !w-56 !py-2 text-sm">
                <button type="submit" class="admin-btn-secondary !py-2">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">search</span>
                </button>
                <a href="{{ route('admin.medicines.index', ['low_stock' => 1]) }}"
                   class="{{ request()->boolean('low_stock') ? 'admin-btn-primary' : 'admin-btn-secondary' }} !py-2 text-xs">
                    {{ __('admin.medicines_low_stock') }}
                </a>
                <a href="{{ route('admin.medicines.index', ['expiring' => 1]) }}"
                   class="{{ request()->boolean('expiring') ? 'admin-btn-primary' : 'admin-btn-secondary' }} !py-2 text-xs">
                    {{ __('admin.medicines_expiring') }}
                </a>
            </form>
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('admin.medicine_name') }}</th>
                        <th class="px-5 py-3">{{ __('admin.medicine_stock') }}</th>
                        <th class="px-5 py-3">{{ __('admin.medicine_min_stock') }}</th>
                        <th class="px-5 py-3">{{ __('admin.price') }}</th>
                        <th class="px-5 py-3">{{ __('admin.medicine_expiry') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($medicines as $medicine)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">medication</span>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $medicine->name }}</p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                            {{ $medicine->form ?: __('admin.medicine_form_generic') }}
                                            @unless($medicine->is_active)
                                                · {{ __('common.inactive') }}
                                            @endunless
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-sm font-bold tabular-nums {{ $medicine->isLowStock() ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-200' }}">
                                    {{ rtrim(rtrim(number_format((float) $medicine->stock, 2, ',', '.'), '0'), ',') }}
                                    <span class="text-xs font-medium text-slate-500">{{ $medicine->unit }}</span>
                                </p>
                                @if($medicine->isLowStock())
                                    <x-admin.badge :label="__('admin.medicines_low_stock')" tone="rose" dot />
                                @endif
                            </td>
                            <td class="px-5 py-3 text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                {{ rtrim(rtrim(number_format((float) $medicine->min_stock, 2, ',', '.'), '0'), ',') }}
                            </td>
                            <td class="px-5 py-3 text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                Rp {{ number_format((float) $medicine->price, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-sm">
                                @if($medicine->expiry_date)
                                    <span class="{{ $medicine->isExpired() ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-slate-600 dark:text-slate-300' }}">
                                        {{ $medicine->expiry_date->format('d M Y') }}
                                    </span>
                                    @if($medicine->isExpired())
                                        <p class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">{{ __('admin.medicines_expired') }}</p>
                                    @elseif($medicine->expiresWithin(60))
                                        <p class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">{{ __('admin.medicines_expiring') }}</p>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('admin.medicines.edit', $medicine->id) }}" class="admin-icon-btn hover:!text-primary" title="{{ __('admin.edit') }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span>
                                    </a>
                                    <a href="{{ route('admin.medicines.movements', $medicine->id) }}" class="admin-icon-btn hover:!text-primary" title="{{ __('admin.medicines_movements') }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">receipt_long</span>
                                    </a>
                                    <form method="POST" action="{{ route('admin.medicines.destroy', $medicine->id) }}" class="inline"
                                          onsubmit="return confirm('{{ __('admin.delete_medicine_confirm') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-icon-btn hover:!text-rose-600" title="{{ __('admin.delete') }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state
                                    :title="__('admin.no_medicines_title')"
                                    :description="__('admin.no_medicines_desc')"
                                    icon="medication"
                                    tone="primary" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($medicines->hasPages())
            <x-slot:footer>{{ $medicines->links() }}</x-slot:footer>
        @endif
    </x-admin.panel>
</div>

@include('admin.medicines.partials.import-modal')
@endsection
