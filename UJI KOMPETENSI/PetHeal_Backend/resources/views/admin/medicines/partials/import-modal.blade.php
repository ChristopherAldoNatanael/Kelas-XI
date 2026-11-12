@php
    /*
     * Modal impor obat stok.
     *
     | Bagian  | Perilaku
     |---------|------------------------------------------------------------
     | Header  | tetap di atas, judul boleh melipat
     | Isi     | satu-satunya area yang menggulir
     | Aksi    | selalu terlihat di bawah
     | Lebar   | tetap MODAL (maks. 768px), bukan layar penuh

     Versi sebelumnya memakai tinggi tetap dan lebar 1100px; di layar kecil
     isinya terpotong, di layar besar terasa seperti halaman penuh. Sekarang
     ukurannya dikunci sebagai dialog, dengan margin aman di semua sisi.
     */
@endphp

<div id="medicine-import-modal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6"
     role="dialog" aria-modal="true" aria-labelledby="medicine-import-title">
    <div class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 sm:max-h-[calc(100dvh-3rem)]">

        {{-- Header: tidak ikut bergulir supaya konteks selalu terlihat --}}
        <div class="flex flex-none items-start gap-3 border-b border-slate-100 px-4 py-4 sm:px-6 sm:py-5">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">
                    CSV · XLSX
                </p>
                <h3 id="medicine-import-title" class="mt-1 break-words text-base font-bold leading-snug tracking-tight text-slate-900 dark:text-white sm:text-lg">
                    {{ __('admin.medicine_import_title') }}
                </h3>
                <p class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400 sm:text-sm">
                    {{ __('admin.medicine_import_desc') }}
                </p>
            </div>
            <button type="button" onclick="closeMedicineImportModal()"
                    class="shrink-0 rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    aria-label="{{ __('common.close') }}">
                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">close</span>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.medicines.import') }}" enctype="multipart/form-data"
              class="flex min-h-0 flex-1 flex-col">
            @csrf

            {{-- Isi: satu-satunya area scroll --}}
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-6 sm:py-5">
                <div class="space-y-5">

                    {{-- Form unggah didahulukan: itulah tindakan utamanya --}}
                    <div>
                        <label for="medicine-import-file" class="admin-label">{{ __('admin.medicine_import_file') }}</label>
                        <label for="medicine-import-file"
                               class="mt-2 flex cursor-pointer items-start gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3.5 py-3.5 transition hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-700 dark:hover:bg-emerald-900/10 sm:items-center sm:justify-between sm:px-4 sm:py-4">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 sm:h-10 sm:w-10">
                                    <span class="material-symbols-outlined text-[20px] sm:text-[22px]" aria-hidden="true">upload_file</span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p id="medicine-import-file-name" class="break-all text-[13px] font-semibold leading-snug text-slate-800 dark:text-slate-100 sm:text-sm">
                                        {{ __('admin.medicine_import_choose') }}
                                    </p>
                                    <p class="mt-0.5 break-words text-[11px] text-slate-500 dark:text-slate-400 sm:text-xs">{{ __('admin.medicine_import_accepts') }}</p>
                                </div>
                            </div>
                            <span class="hidden shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 sm:inline-block">
                                {{ __('admin.browse') }}
                            </span>
                        </label>
                        <input id="medicine-import-file" type="file" name="file" accept=".csv,.txt,.xlsx,.xls" required class="sr-only">
                    </div>

                    {{-- Perlakuan duplikat: dua pilihan bersebelahan bila ada ruang --}}
                    <div>
                        <p class="admin-label">{{ __('admin.medicine_import_duplicates') }}</p>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach([
                                ['update', true, __('admin.medicine_import_strategy_update'), __('admin.medicine_import_strategy_update_desc')],
                                ['skip', false, __('admin.medicine_import_strategy_skip'), __('admin.medicine_import_strategy_skip_desc')],
                            ] as [$value, $checked, $title, $desc])
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900">
                                    <input type="radio" name="duplicate_strategy" value="{{ $value }}" @checked($checked)
                                           class="mt-0.5 shrink-0 text-emerald-600 focus:ring-emerald-500">
                                    <span class="min-w-0">
                                        <span class="block break-words text-[13px] font-semibold text-slate-800 dark:text-slate-100">{{ $title }}</span>
                                        <span class="block break-words text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">{{ $desc }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {{-- Template --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 dark:border-slate-800 dark:bg-slate-950/30">
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ __('admin.medicine_import_step_template') }}</p>
                            <p class="mt-0.5 break-words text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">{{ __('admin.medicine_import_step_template_desc') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a href="{{ route('admin.medicines.template.csv') }}"
                                   class="admin-btn-secondary !px-2.5 !py-1.5 text-[11px]">
                                    <span class="material-symbols-outlined text-[15px]">description</span>
                                    {{ __('admin.medicine_import_template_csv') }}
                                </a>
                                <a href="{{ route('admin.medicines.template.xlsx') }}"
                                   class="admin-btn-secondary !px-2.5 !py-1.5 text-[11px]">
                                    <span class="material-symbols-outlined text-[15px]">table_chart</span>
                                    {{ __('admin.medicine_import_template_xlsx') }}
                                </a>
                            </div>
                        </div>

                        {{-- Ekspor data sekarang --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 dark:border-slate-800 dark:bg-slate-950/30">
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ __('admin.medicine_import_export_hint') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a href="{{ route('admin.medicines.export.csv') }}"
                                   class="admin-btn-secondary !px-2.5 !py-1.5 text-[11px]">
                                    <span class="material-symbols-outlined text-[15px]">download</span>
                                    CSV
                                </a>
                                <a href="{{ route('admin.medicines.export.xlsx') }}"
                                   class="admin-btn-secondary !px-2.5 !py-1.5 text-[11px]">
                                    <span class="material-symbols-outlined text-[15px]">download</span>
                                    Excel
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Panduan kolom: dilipat agar modal tidak memanjang --}}
                    <details class="group rounded-xl border border-slate-200 dark:border-slate-800">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-3.5 py-2.5">
                            <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                                {{ __('admin.medicine_import_columns') }}
                            </span>
                            <span class="material-symbols-outlined text-[18px] text-slate-400 transition group-open:rotate-180" aria-hidden="true">expand_more</span>
                        </summary>
                        <dl class="space-y-1.5 px-3.5 pb-3.5">
                            @foreach([
                                ['name', __('admin.medicine_import_column_required'), true],
                                ['unit', __('admin.medicine_import_column_required'), true],
                                ['stock', __('admin.medicine_import_column_stock_new'), false],
                                ['min_stock', __('admin.medicine_min_stock'), false],
                                ['price', __('admin.price'), false],
                                ['expiry_date', __('admin.medicine_import_column_expiry'), false],
                                ['form', __('admin.medicine_form'), false],
                                ['manufacturer', __('admin.medicine_manufacturer'), false],
                                ['status', 'active / inactive', false],
                                ['notes', __('admin.medicine_reason'), false],
                            ] as [$column, $note, $required])
                                <div class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <dt class="break-all font-mono text-[11px] font-semibold text-slate-700 dark:text-slate-200">{{ $column }}</dt>
                                    <span class="shrink-0 rounded px-1.5 text-[9px] font-bold uppercase tracking-wide {{ $required ? 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300' : 'bg-slate-200/70 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $required ? __('admin.medicine_import_column_required') : __('admin.medicine_import_column_optional') }}
                                    </span>
                                    <dd class="min-w-0 break-words text-[11px] text-slate-500 dark:text-slate-400">{{ $note }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </details>
                </div>
            </div>

            {{-- Aksi: menempel di bawah, selalu terlihat --}}
            <div class="flex flex-none flex-col-reverse gap-2 border-t border-slate-100 px-4 py-3 dark:border-slate-800 sm:flex-row sm:justify-end sm:px-6 sm:py-4">
                <button type="button" onclick="closeMedicineImportModal()"
                        class="admin-btn-secondary w-full !justify-center sm:w-auto">
                    {{ __('common.cancel') }}
                </button>
                <button type="submit" class="admin-btn-primary w-full !justify-center sm:w-auto">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">publish</span>
                    {{ __('admin.medicine_import_run') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var modal = document.getElementById('medicine-import-modal');
        var input = document.getElementById('medicine-import-file');
        var label = document.getElementById('medicine-import-file-name');

        input?.addEventListener('change', function () {
            if (!label) return;
            label.textContent = this.files?.[0]?.name || @json(__('admin.medicine_import_choose'));
        });

        // Kunci scroll halaman di belakang: dua area scroll yang bersaing
        // membuat isi modal terasa terpotong di layar pendek.
        function lockBodyScroll(lock) {
            document.documentElement.style.overflow = lock ? 'hidden' : '';
            document.body.style.overflow = lock ? 'hidden' : '';
        }

        window.openMedicineImportModal = function () {
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            lockBodyScroll(true);
            window.setTimeout(function () { input?.focus({ preventScroll: true }); }, 120);
        };

        window.closeMedicineImportModal = function () {
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            lockBodyScroll(false);
        };

        modal?.addEventListener('click', function (event) {
            if (event.target === this) window.closeMedicineImportModal();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                window.closeMedicineImportModal();
            }
        });
    })();
</script>
