@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $services */
@endphp
@extends('layouts.admin')

@section('title', 'Services - VCMS Admin')
@section('header', 'Services & Pricelist')

@section('content')

@if(session('success'))
<div id="flash-msg" class="mb-4 flex items-center gap-3 rounded-xl border border-emerald-200/80 bg-white px-4 py-3 text-sm font-medium text-emerald-700 shadow-sm dark:border-emerald-800 dark:bg-slate-900 dark:text-emerald-400">
    <span class="material-symbols-outlined text-[19px]">check_circle</span>
    <span>{{ session('success') }}</span>
</div>
<script>setTimeout(()=>{ const el=document.getElementById('flash-msg'); if(el) el.style.display='none'; }, 3000);</script>
@endif

@if(session('error'))
<div class="mb-4 flex items-center gap-3 rounded-xl border border-red-200/80 bg-white px-4 py-3 text-sm font-medium text-red-700 shadow-sm dark:border-red-800 dark:bg-slate-900 dark:text-red-400">
    <span class="material-symbols-outlined text-[18px]">error</span>
    <span>{{ session('error') }}</span>
</div>
@endif

@if(session('import_summary'))
@php($summary = session('import_summary'))
<div class="mb-5 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                <span class="material-symbols-outlined text-[21px]">task_alt</span>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Service import completed</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Processed {{ $summary['total'] ?? 0 }} rows. Review failed rows before running another import.</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(session('import_has_errors'))
                <a href="{{ route('admin.services.import.error-report', ['format' => 'csv']) }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-300">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    Error CSV
                </a>
                <a href="{{ route('admin.services.import.error-report', ['format' => 'xlsx']) }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <span class="material-symbols-outlined text-[18px]">grid_on</span>
                    Error XLSX
                </a>
            @endif
        </div>
    </div>
    <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 dark:divide-slate-800 md:grid-cols-5 md:divide-y-0">
        <div class="p-4">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Total</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ $summary['total'] ?? 0 }}</p>
        </div>
        <div class="p-4">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Created</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ $summary['success'] ?? 0 }}</p>
        </div>
        <div class="p-4">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Updated</p>
            <p class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ $summary['updated'] ?? 0 }}</p>
        </div>
        <div class="p-4">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Skipped</p>
            <p class="mt-1 text-2xl font-semibold text-slate-700 dark:text-slate-200">{{ $summary['skipped'] ?? 0 }}</p>
        </div>
        <div class="p-4">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Failed</p>
            <p class="mt-1 text-2xl font-semibold {{ ($summary['failed'] ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-700 dark:text-slate-200' }}">{{ $summary['failed'] ?? 0 }}</p>
        </div>
    </div>
</div>
@endif

<div class="glass-card rounded-2xl overflow-hidden">
    <div class="flex flex-col gap-4 border-b border-slate-100 p-6 dark:border-slate-800 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Daftar Layanan</h2>
            <p class="mt-0.5 text-xs text-slate-400">Kelola katalog layanan klinik, harga, dan template import.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="openServicesImportModal()" class="inline-flex h-10 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/35">
                <span class="material-symbols-outlined text-[18px]">upload_file</span>
                Import Layanan
            </button>
            <a href="{{ route('admin.services.create') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Layanan
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Service</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Duration</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Price</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($services as $service)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $service->name }}</p>
                                @if($service->description)
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-xs truncate">{{ $service->description }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($service->category)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                    {{ $service->category }}
                                </span>
                            @else
                                <span class="text-xs text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                            {{ $service->duration ? $service->duration . ' min' : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-900 dark:text-white">
                            Rp {{ number_format($service->price, 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $service->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $service->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.services.edit', $service->id) }}" class="p-2 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50 transition-colors" title="Ubah">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form method="POST" action="{{ route('admin.services.destroy', $service->id) }}" class="inline" onsubmit="return confirm('Yakin ingin menghapus layanan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 transition-colors" title="Hapus">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <span class="material-symbols-outlined text-5xl text-slate-300 dark:text-slate-600 mb-3">medical_services</span>
                                <p class="text-slate-600 dark:text-slate-300 font-medium">Belum ada layanan yang ditampilkan</p>
                                <p class="text-xs text-slate-400 mt-1">Tambahkan layanan pertama atau gunakan fitur import agar katalog klinik segera siap dipakai.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($services->hasPages())
    <div class="p-6 border-t border-slate-100 dark:border-slate-800">
        {{ $services->links() }}
    </div>
    @endif
</div>

<div id="services-import-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm">
    <div class="w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5 dark:border-slate-800">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">Bulk import</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">Import services from file</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use the template, keep the headers exact, and choose how duplicates should behave.</p>
            </div>
            <button type="button" onclick="closeServicesImportModal()" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>

        <div class="grid gap-0 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5 dark:border-slate-800 dark:bg-slate-950/20 lg:border-b-0 lg:border-r">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.services.template.csv') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-symbols-outlined text-[18px]">description</span>
                        Template CSV
                    </a>
                    <a href="{{ route('admin.services.template.xlsx') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-symbols-outlined text-[18px]">table_chart</span>
                        Template XLSX
                    </a>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Import rules</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li class="flex gap-2">
                            <span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Header must be exactly: <span class="font-medium text-slate-900 dark:text-white">name, description, price, duration, status</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Price must be a number. Duration is optional. Status supports <span class="font-medium text-slate-900 dark:text-white">active</span> or <span class="font-medium text-slate-900 dark:text-white">inactive</span>.
                        </li>
                        <li class="flex gap-2">
                            <span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            If a service name already exists, you can update it or skip it.
                        </li>
                    </ul>
                </div>

                @if(session('import_summary'))
                    @php($summary = session('import_summary'))
                    <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Last import result</p>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Total</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $summary['total'] ?? 0 }}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Failed</p>
                                <p class="mt-1 font-semibold {{ ($summary['failed'] ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">{{ $summary['failed'] ?? 0 }}</p>
                            </div>
                        </div>
                        @if(session('import_has_errors'))
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('admin.services.import.error-report', ['format' => 'csv']) }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-300">
                                    <span class="material-symbols-outlined text-[18px]">download</span>
                                    Error CSV
                                </a>
                                <a href="{{ route('admin.services.import.error-report', ['format' => 'xlsx']) }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                    <span class="material-symbols-outlined text-[18px]">grid_on</span>
                                    Error XLSX
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
                        <label for="service-import-file" class="block text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Import file</label>
                        <label for="service-import-file" class="mt-2 flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 transition hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-700 dark:hover:bg-emerald-900/10">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm ring-1 ring-slate-200 transition dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">
                                    <span class="material-symbols-outlined text-[22px]">upload_file</span>
                                </div>
                                <div class="min-w-0">
                                    <p id="service-import-file-name" class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">Choose CSV or Excel file</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Accepts CSV, XLS, XLSX. Keep the headers unchanged.</p>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">Browse</span>
                        </label>
                        <input id="service-import-file" type="file" name="file" accept=".csv,.txt,.xlsx,.xls" required class="sr-only">
                    </div>

                    <div>
                        <p class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Duplicate handling</p>
                        <div class="space-y-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900">
                                <input type="radio" name="duplicate_strategy" value="update" checked class="mt-1 text-emerald-600 focus:ring-emerald-500">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Update existing services</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">Use this when the template is a refresh of the current catalog.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900">
                                <input type="radio" name="duplicate_strategy" value="skip" class="mt-1 text-emerald-600 focus:ring-emerald-500">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Skip duplicates</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">Keep current values and only add new services.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeServicesImportModal()" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">
                            <span class="material-symbols-outlined text-[18px]">publish</span>
                            Run Import
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    document.getElementById('service-import-file')?.addEventListener('change', function () {
        const fileName = this.files?.[0]?.name || 'Choose service import file';
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
