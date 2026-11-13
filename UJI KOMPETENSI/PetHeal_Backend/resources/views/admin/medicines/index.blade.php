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

    @include('admin.medicines.partials.section-nav')

    @php
        $medicineStatusFilterActive = request()->boolean('low_stock')
            || request()->boolean('disposed');
        $medicineFiltersActive = request()->filled('q') || $medicineStatusFilterActive;
    @endphp

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
    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Hasil import terakhir pada sesi ini. Angka datang dari importer,
         bukan dihitung ulang di view. --}}
    @if(session('medicine_import_summary'))
        @php
            $sum = session('medicine_import_summary');
        @endphp
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

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
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
            icon="event_available"
            tone="{{ $expiringCount > 0 ? 'amber' : 'slate' }}"
            :hint="__('admin.medicines_expiring_hint')" />
        <x-admin.stat-card
            :label="__('admin.medicines_expired_count')"
            :value="$expiredCount"
            icon="event_busy"
            tone="{{ $expiredCount > 0 ? 'rose' : 'slate' }}"
            :hint="__('admin.medicines_expired_hint')" />
        <x-admin.stat-card
            :label="__('admin.medicines_disposed')"
            :value="$disposedCount"
            icon="delete_forever"
            tone="{{ $disposedCount > 0 ? 'violet' : 'slate' }}"
            :hint="__('admin.medicines_disposed_hint')" />
    </div>

    {{-- Alur penanganan. Bukan dekorasi: ini urutan yang harus diikuti supaya
         "obat mau kedaluwarsa" tidak berhenti pada label saja. --}}
    <x-admin.panel :title="__('admin.medicine_flow_title')" :subtitle="__('admin.medicine_flow_desc')">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            @foreach([
                ['1', __('admin.medicine_flow_step1_title'), __('admin.medicine_flow_step1_desc'), 'event_busy', 'text-amber-600 dark:text-amber-400'],
                ['2', __('admin.medicine_flow_step2_title'), __('admin.medicine_flow_step2_desc'), 'swap_horiz', 'text-sky-600 dark:text-sky-400'],
                ['3', __('admin.medicine_flow_step3_title'), __('admin.medicine_flow_step3_desc'), 'delete_forever', 'text-rose-600 dark:text-rose-400'],
            ] as [$step, $title, $desc, $icon, $iconClass])
                <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $step }}</span>
                        <span class="material-symbols-outlined text-[18px] {{ $iconClass }}" aria-hidden="true">{{ $icon }}</span>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $title }}</p>
                    </div>
                    <p class="mt-1.5 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $desc }}</p>
                </div>
            @endforeach
        </div>

        @if($expiredCount > 0 || $expiringCount > 0)
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                @if($expiredCount > 0)
                    <a href="{{ route('admin.medicines.expiry', ['status' => 'expired']) }}" class="admin-btn-danger !py-2 text-xs">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">event_busy</span>
                        {{ __('admin.medicines_handle_expired', ['count' => $expiredCount]) }}
                    </a>
                @endif
                @if($expiringCount > 0)
                    <a href="{{ route('admin.medicines.expiry', ['status' => 'expiring']) }}" class="admin-btn-secondary !py-2 text-xs">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">event_available</span>
                        {{ __('admin.medicines_handle_expiring', ['count' => $expiringCount]) }}
                    </a>
                @endif
            </div>
        @endif
    </x-admin.panel>

    <x-admin.panel :title="__('admin.medicines_list_panel_title')" :subtitle="__('admin.medicines_list_panel_desc')" flush>
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.medicines.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}"
                       placeholder="{{ __('admin.search_medicine') }}" class="admin-input !w-56 !py-2 text-sm">
                <button type="submit" class="admin-btn-secondary !py-2" aria-label="{{ __('admin.search') }}">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">search</span>
                </button>
                <a href="{{ route('admin.medicines.index', array_filter(['q' => request('q')])) }}"
                   class="admin-filter-chip {{ $medicineStatusFilterActive ? '' : 'admin-filter-chip--active' }}">
                    {{ __('admin.medicines_filter_all') }}
                </a>
                <a href="{{ route('admin.medicines.index', array_filter(['low_stock' => 1, 'q' => request('q')])) }}"
                   class="admin-filter-chip {{ request()->boolean('low_stock') ? 'admin-filter-chip--active' : '' }}">
                    {{ __('admin.medicines_low_stock') }}
                </a>
                <a href="{{ route('admin.medicines.index', array_filter(['disposed' => 1, 'q' => request('q')])) }}"
                   class="admin-filter-chip {{ request()->boolean('disposed') ? 'admin-filter-chip--active' : '' }}">
                    {{ __('admin.medicines_disposed') }}
                </a>
            </form>
        </x-slot:actions>

        {{-- Satu form membungkus tabel: checkbox jadi `ids[]` dan tombol aksi
             massal cukup mengganti `action` sebelum submit. --}}
        <form id="medicine-bulk-form" method="POST" action="{{ route('admin.medicines.bulk-destroy') }}">
            @csrf

            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left md:min-w-[880px]">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="w-10 px-3 py-3">
                                <input type="checkbox" id="medicine-select-all"
                                       class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
                                       aria-label="{{ __('admin.medicine_select_all') }}">
                            </th>
                            <th class="px-5 py-3">{{ __('admin.medicine_name') }}</th>
                            <th class="px-5 py-3">{{ __('admin.medicine_stock') }}</th>
                            <th class="hidden px-5 py-3 md:table-cell">{{ __('admin.medicine_min_stock') }}</th>
                            <th class="hidden px-5 py-3 md:table-cell">{{ __('admin.price') }}</th>
                            <th class="px-5 py-3">{{ __('admin.medicine_expiry') }}</th>
                            <th class="px-5 py-3 text-center">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @forelse($medicines as $medicine)
                            @php
                                $status = $medicine->expiryStatus();
                                $daysLeft = $medicine->expiryDaysLeft();
                            @endphp
                            <tr class="admin-table-row">
                                <td class="px-3 py-3">
                                    <input type="checkbox" name="ids[]" value="{{ $medicine->id }}"
                                           class="medicine-row-check h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
                                           aria-label="{{ __('admin.medicine_select_row', ['name' => $medicine->name]) }}">
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">medication</span>
                                        </span>
                                        <div class="min-w-0 max-w-[180px] sm:max-w-[280px] lg:max-w-none">
                                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $medicine->name }}</p>
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ $medicine->form ?: __('admin.medicine_form_generic') }}
                                                </span>
                                                @unless($medicine->is_active)
                                                    <x-admin.badge :label="__('common.inactive')" tone="slate" />
                                                @endunless
                                                @if($medicine->isDisposed())
                                                    <x-admin.badge :label="__('admin.medicine_disposed_badge')" tone="violet" dot />
                                                @endif
                                            </div>
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
                                <td class="hidden whitespace-nowrap px-5 py-3 text-sm tabular-nums text-slate-600 dark:text-slate-300 md:table-cell">
                                    {{ rtrim(rtrim(number_format((float) $medicine->min_stock, 2, ',', '.'), '0'), ',') }}
                                </td>
                                <td class="hidden whitespace-nowrap px-5 py-3 text-sm tabular-nums text-slate-600 dark:text-slate-300 md:table-cell">
                                    Rp {{ number_format((float) $medicine->price, 0, ',', '.') }}
                                </td>
                                <td class="px-5 py-3 text-sm">
                                    @if($medicine->expiry_date)
                                        <span class="{{ $status === 'expired' ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-slate-600 dark:text-slate-300' }}">
                                            {{ $medicine->expiry_date->format('d M Y') }}
                                        </span>

                                        @if($status === 'expired')
                                            <p class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">
                                                {{ __('admin.medicine_expired_since', ['days' => abs((int) $daysLeft)]) }}
                                            </p>
                                        @elseif($status === 'expiring')
                                            <p class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                                {{ __('admin.medicine_expires_in', ['days' => (int) $daysLeft]) }}
                                            </p>
                                        @endif

                                        @if($medicine->isDisposed())
                                            <p class="text-[11px] text-violet-600 dark:text-violet-400">
                                                {{ __('admin.medicine_disposed_at', ['date' => $medicine->disposed_at->format('d M Y')]) }}
                                                @if($medicine->disposal_method)
                                                    · {{ $medicine->disposal_method_label }}
                                                @endif
                                            </p>
                                        @endif
                                    @else
                                        <span class="text-slate-400">{{ __('admin.medicine_expiry_none') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="{{ route('admin.medicines.edit', $medicine->id) }}" class="admin-icon-btn hover:!text-primary" title="{{ __('admin.edit') }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span>
                                        </a>
                                        <a href="{{ route('admin.medicines.mutations.index', ['medicine_id' => $medicine->id]) }}" class="admin-icon-btn hover:!text-primary" title="{{ __('inventory.action_view_mutations') }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">receipt_long</span>
                                        </a>
                                        <button type="submit"
                                                form="medicine-row-delete-{{ $medicine->id }}"
                                                class="admin-icon-btn hover:!text-rose-600"
                                                title="{{ __('admin.delete') }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-admin.empty-state
                                        :title="$medicineFiltersActive ? __('admin.no_medicine_results_title') : __('admin.no_medicines_title')"
                                        :description="$medicineFiltersActive ? __('admin.no_medicine_results_desc') : __('admin.no_medicines_desc')"
                                        icon="medication"
                                        tone="primary">
                                        <x-slot:actions>
                                            @if($medicineFiltersActive)
                                                <a href="{{ route('admin.medicines.index') }}" class="admin-btn-secondary !py-2 text-xs">
                                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">filter_alt_off</span>
                                                    {{ __('admin.medicines_filter_reset') }}
                                                </a>
                                            @else
                                                <a href="{{ route('admin.medicines.create') }}" class="admin-btn-primary !py-2 text-xs">
                                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>
                                                    {{ __('admin.add_medicine') }}
                                                </a>
                                            @endif
                                        </x-slot:actions>
                                    </x-admin.empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Tabel lebar butuh geser horizontal di layar kecil; beri tahu
                 supaya kolom kedaluwarsa/aksi tidak dianggap hilang. --}}
            <p class="px-5 pb-3 text-[11px] text-slate-400 md:hidden">
                {{ __('admin.medicine_table_scroll_hint') }}
            </p>

            {{-- Bar aksi massal: muncul hanya saat ada baris dicentang. --}}
            <div id="medicine-bulk-bar"
                 class="hidden flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/80 px-5 py-3.5 dark:border-slate-800 dark:bg-slate-900/60">
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                    {{ __('admin.medicine_selected_count', ['count' => 0]) }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" id="medicine-bulk-delete-btn" class="admin-btn-secondary !py-2 text-xs !text-rose-600">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">delete</span>
                        {{ __('admin.medicine_bulk_delete') }}
                    </button>
                    <button type="button" id="medicine-bulk-clear" class="admin-btn-ghost !py-2 text-xs">
                        {{ __('admin.medicine_clear_selection') }}
                    </button>
                </div>
            </div>
        </form>

        {{-- Form hapus per baris diletakkan di luar form bulk: form bersarang
             tidak sah dan tombolnya akan men-submit form yang salah. --}}
        @foreach($medicines as $medicine)
            <form id="medicine-row-delete-{{ $medicine->id }}" method="POST"
                  action="{{ route('admin.medicines.destroy', $medicine->id) }}"
                  onsubmit="return confirm(@json(__('admin.delete_medicine_confirm')))">
                @csrf
                @method('DELETE')
            </form>
        @endforeach

        @if($medicines->hasPages())
            <x-slot:footer>{{ $medicines->links() }}</x-slot:footer>
        @endif
    </x-admin.panel>
</div>

@include('admin.medicines.partials.import-modal')

<script>
    (function () {
        var form = document.getElementById('medicine-bulk-form');
        var bar = document.getElementById('medicine-bulk-bar');
        var counter = bar ? bar.querySelector('p') : null;
        var selectAll = document.getElementById('medicine-select-all');
        var clearBtn = document.getElementById('medicine-bulk-clear');
        var deleteBtn = document.getElementById('medicine-bulk-delete-btn');

        function checks() {
            return Array.prototype.slice.call(document.querySelectorAll('.medicine-row-check'));
        }

        function selected() {
            return checks().filter(function (c) { return c.checked; });
        }

        function labelCount() {
            return @json(__('admin.medicine_selected_count', ['count' => '__COUNT__']));
        }

        function refresh() {
            var n = selected().length;
            if (!bar) return;

            if (n > 0) {
                bar.classList.remove('hidden');
                bar.classList.add('flex');
            } else {
                bar.classList.add('hidden');
                bar.classList.remove('flex');
            }

            if (counter) {
                counter.textContent = labelCount().replace('__COUNT__', n);
            }

            if (selectAll) {
                var all = checks();
                selectAll.checked = n > 0 && n === all.length;
                selectAll.indeterminate = n > 0 && n < all.length;
            }
        }

        selectAll?.addEventListener('change', function () {
            checks().forEach(function (c) { c.checked = selectAll.checked; });
            refresh();
        });

        checks().forEach(function (c) { c.addEventListener('change', refresh); });

        clearBtn?.addEventListener('click', function () {
            checks().forEach(function (c) { c.checked = false; });
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            refresh();
        });

        deleteBtn?.addEventListener('click', function (event) {
            if (selected().length === 0) {
                event.preventDefault();
                return;
            }

            if (!confirm(@json(__('admin.medicine_bulk_delete_confirm')))) {
                event.preventDefault();
                return;
            }

            form.action = @json(route('admin.medicines.bulk-destroy'));
        });

        refresh();
    })();
</script>
@endsection
