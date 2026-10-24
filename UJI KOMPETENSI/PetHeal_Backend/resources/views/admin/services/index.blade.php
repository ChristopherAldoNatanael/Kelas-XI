@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $services */
@endphp
@extends('layouts.admin')

@section('title', __('menu.services') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.services'))

@section('content')
<div class="space-y-6">

    {{-- Header halaman --}}
    <x-admin.page-header
        :title="__('admin.services_title')"
        :eyebrow="__('admin.eyebrow_management')"
        icon="price_change"
        :description="__('admin.services_desc')">
        <x-slot:actions>
            <button type="button" onclick="openServicesImportModal()" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">upload_file</span>
                {{ __('admin.import_services') }}
            </button>
            <a href="{{ route('admin.services.create') }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                {{ __('admin.add_service') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <div id="flash-msg" class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
        <script>setTimeout(() => { const el = document.getElementById('flash-msg'); if (el) el.style.display = 'none'; }, 3000);</script>
    @endif

    @if(session('error'))
        <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm font-medium text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Ringkasan import --}}
    @if(session('import_summary'))
        @php($summary = session('import_summary'))
        <x-admin.panel :title="__('admin.import_done_title')" :subtitle="__('admin.import_done_desc', ['total' => $summary['total'] ?? 0])">
            <x-slot:actions>
                @if(session('import_has_errors'))
                    <a href="{{ route('admin.services.import.error-report', ['format' => 'csv']) }}" class="admin-btn-secondary !px-3 !py-1.5 !text-[11px]">
                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">download</span>
                        {{ __('admin.error_csv') }}
                    </a>
                    <a href="{{ route('admin.services.import.error-report', ['format' => 'xlsx']) }}" class="admin-btn-secondary !px-3 !py-1.5 !text-[11px]">
                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">grid_on</span>
                        {{ __('admin.error_xlsx') }}
                    </a>
                @endif
            </x-slot:actions>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                @foreach([
                    ['label' => __('admin.total'), 'value' => $summary['total'] ?? 0, 'class' => 'text-slate-900 dark:text-white'],
                    ['label' => __('admin.created'), 'value' => $summary['success'] ?? 0, 'class' => 'text-emerald-600 dark:text-emerald-400'],
                    ['label' => __('admin.updated'), 'value' => $summary['updated'] ?? 0, 'class' => 'text-blue-600 dark:text-blue-400'],
                    ['label' => __('admin.skipped'), 'value' => $summary['skipped'] ?? 0, 'class' => 'text-slate-700 dark:text-slate-200'],
                    ['label' => __('admin.failed'), 'value' => $summary['failed'] ?? 0, 'class' => ($summary['failed'] ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-700 dark:text-slate-200'],
                ] as $tile)
                    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-800">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ $tile['label'] }}</p>
                        <p class="mt-1.5 text-2xl font-bold tabular-nums {{ $tile['class'] }}">{{ $tile['value'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-admin.panel>
    @endif

    {{-- Tabel layanan --}}
    <x-admin.panel :title="__('admin.services_title')" :subtitle="__('admin.services_desc')" flush>
        <x-slot:actions>
            <x-admin.badge :label="__('admin.total') . ' · ' . $services->total()" tone="slate" />
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('admin.col_service') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_category') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_duration') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_price') }}</th>
                        <th class="px-5 py-3">{{ __('common.status') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($services as $service)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $service->name }}</p>
                                @if($service->description)
                                    <p class="mt-0.5 max-w-xs truncate text-[11px] text-slate-400">{{ $service->description }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if($service->category)
                                    <x-admin.badge :label="$service->category" tone="blue" />
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">
                                {{ $service->duration ? $service->duration . ' ' . __('admin.minutes') : '—' }}
                            </td>
                            <td class="px-5 py-3 text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">
                                Rp {{ number_format($service->price, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="$service->is_active ? __('common.active') : __('common.inactive')"
                                    :tone="$service->is_active ? 'primary' : 'slate'"
                                    dot />
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('admin.services.edit', $service->id) }}"
                                       class="admin-icon-btn hover:!text-primary"
                                       title="{{ __('admin.edit') }}"
                                       aria-label="{{ __('admin.edit') }} {{ $service->name }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span>
                                    </a>

                                    <form method="POST" action="{{ route('admin.services.destroy', $service->id) }}" class="inline"
                                          onsubmit="return confirm('{{ __('admin.delete_service_confirm') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="admin-icon-btn hover:!text-rose-600"
                                                title="{{ __('admin.delete') }}"
                                                aria-label="{{ __('admin.delete') }} {{ $service->name }}">
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
                                    :title="__('admin.no_services_title')"
                                    :description="__('admin.no_services_desc')"
                                    icon="medical_services"
                                    tone="primary">
                                    <x-slot:actions>
                                        <button type="button" onclick="openServicesImportModal()" class="admin-btn-secondary">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">upload_file</span>
                                            {{ __('admin.import_services') }}
                                        </button>
                                        <a href="{{ route('admin.services.create') }}" class="admin-btn-primary">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                                            {{ __('admin.add_service') }}
                                        </a>
                                    </x-slot:actions>
                                </x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($services->hasPages())
            <x-slot:footer>
                {{ $services->links() }}
            </x-slot:footer>
        @endif
    </x-admin.panel>
</div>

{{-- Modal import layanan --}}
<div id="services-import-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="services-import-title">
    <div class="w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5 dark:border-slate-800">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">{{ __('admin.bulk_import') }}</p>
                <h3 id="services-import-title" class="mt-1 text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __('admin.import_modal_title') }}
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('admin.import_modal_desc') }}</p>
            </div>
            <button type="button" onclick="closeServicesImportModal()"
                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    aria-label="{{ __('common.close') }}">
                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">close</span>
            </button>
        </div>

        <div class="grid gap-0 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5 dark:border-slate-800 dark:bg-slate-950/20 lg:border-b-0 lg:border-r">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.services.template.csv') }}" class="admin-btn-secondary !h-9 !px-3 !text-xs">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">description</span>
                        {{ __('admin.template_csv') }}
                    </a>
                    <a href="{{ route('admin.services.template.xlsx') }}" class="admin-btn-secondary !h-9 !px-3 !text-xs">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">table_chart</span>
                        {{ __('admin.template_xlsx') }}
                    </a>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ __('admin.import_rules') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li class="flex gap-2">
                            <span class="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                            <span>{{ __('admin.import_rule_header', ['header' => 'name, description, price, duration, status']) }}</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                            <span>{{ __('admin.import_rule_price', ['status' => 'active / inactive']) }}</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                            <span>{{ __('admin.import_rule_duplicate') }}</span>
                        </li>
                    </ul>
                </div>

                @if(session('import_summary'))
                    @php($summary = session('import_summary'))
                    <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ __('admin.last_import_result') }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ __('admin.total') }}</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $summary['total'] ?? 0 }}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ __('admin.failed') }}</p>
                                <p class="mt-1 font-semibold {{ ($summary['failed'] ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">
                                    {{ $summary['failed'] ?? 0 }}
                                </p>
                            </div>
                        </div>

                        @if(session('import_has_errors'))
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('admin.services.import.error-report', ['format' => 'csv']) }}" class="admin-btn-secondary !h-9 !px-3 !text-xs">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">download</span>
                                    {{ __('admin.error_csv') }}
                                </a>
                                <a href="{{ route('admin.services.import.error-report', ['format' => 'xlsx']) }}" class="admin-btn-secondary !h-9 !px-3 !text-xs">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">grid_on</span>
                                    {{ __('admin.error_xlsx') }}
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.services.import') }}" enctype="multipart/form-data" class="px-6 py-5">
                @csrf
                <div class="space-y-5">
                    <div>
                        <label for="service-import-file" class="admin-label">{{ __('admin.import_file_label') }}</label>
                        <label for="service-import-file" class="mt-2 flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 transition hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-700 dark:hover:bg-emerald-900/10">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm ring-1 ring-slate-200 transition dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">
                                    <span class="material-symbols-outlined text-[22px]" aria-hidden="true">upload_file</span>
                                </span>
                                <div class="min-w-0">
                                    <p id="service-import-file-name" class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                                        {{ __('admin.choose_file') }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('admin.file_accepts') }}</p>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                                {{ __('admin.browse') }}
                            </span>
                        </label>
                        <input id="service-import-file" type="file" name="file" accept=".csv,.txt,.xlsx,.xls" required class="sr-only">
                    </div>

                    <div>
                        <p class="admin-label">{{ __('admin.duplicate_handling') }}</p>
                        <div class="space-y-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900">
                                <input type="radio" name="duplicate_strategy" value="update" checked class="mt-1 text-emerald-600 focus:ring-emerald-500">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('admin.update_existing') }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('admin.update_existing_desc') }}</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900">
                                <input type="radio" name="duplicate_strategy" value="skip" class="mt-1 text-emerald-600 focus:ring-emerald-500">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('admin.skip_duplicates') }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('admin.skip_duplicates_desc') }}</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeServicesImportModal()" class="admin-btn-secondary">
                            {{ __('common.cancel') }}
                        </button>
                        <button type="submit" class="admin-btn-primary">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">publish</span>
                            {{ __('admin.run_import') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('service-import-file')?.addEventListener('change', function () {
        const fileName = this.files?.[0]?.name || @json(__('admin.choose_file'));
        const label = document.getElementById('service-import-file-name');
        if (label) label.textContent = fileName;
    });

    function openServicesImportModal() {
        const modal = document.getElementById('services-import-modal');
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeServicesImportModal() {
        const modal = document.getElementById('services-import-modal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.getElementById('services-import-modal')?.addEventListener('click', function (event) {
        if (event.target === this) {
            closeServicesImportModal();
        }
    });
</script>
@endsection
