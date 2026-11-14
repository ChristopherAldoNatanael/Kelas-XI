{{--
    Modal impor produk Pet Shop.

    Struktur: header tetap, isi menggulir, aksi menempel di bawah. Lebar
    dikunci sebagai dialog (bukan halaman penuh) supaya tetap nyaman di
    layar kecil maupun besar.
--}}
<div id="petshop-product-import-modal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6"
     role="dialog" aria-modal="true" aria-labelledby="petshop-product-import-title">
    <div class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 sm:max-h-[calc(100dvh-3rem)]">

        {{-- Header --}}
        <div class="flex flex-none items-start gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-6 sm:py-5">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">
                    CSV · XLSX
                </p>
                <h3 id="petshop-product-import-title" class="mt-1 break-words text-base font-bold leading-snug tracking-tight text-slate-900 dark:text-white sm:text-lg">
                    {{ __('petshop.import_title') }}
                </h3>
                <p class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400 sm:text-sm">
                    {{ __('petshop.import_desc') }}
                </p>
            </div>
            <button type="button" onclick="closePetshopProductImport()"
                    class="shrink-0 rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    aria-label="{{ __('common.close') }}">
                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">close</span>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.pet-shop.products.import') }}" enctype="multipart/form-data"
              class="flex min-h-0 flex-1 flex-col">
            @csrf

            {{-- Isi --}}
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-6 sm:py-5">
                <div class="space-y-5">

                    @if($errors->any())
                        <div class="flex gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-3 text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
                            <span class="material-symbols-outlined shrink-0 text-[18px]" aria-hidden="true">error</span>
                            <ul class="min-w-0 list-inside list-disc space-y-1 text-[12px] leading-relaxed">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div>
                        <label for="petshop-product-import-file" class="admin-label">{{ __('petshop.import_file') }}</label>
                        <label for="petshop-product-import-file"
                               class="mt-2 flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3.5 py-3.5 transition hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-700 dark:hover:bg-emerald-900/10 sm:justify-between sm:px-4 sm:py-4">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 sm:h-10 sm:w-10">
                                    <span class="material-symbols-outlined text-[20px] sm:text-[22px]" aria-hidden="true">upload_file</span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p id="petshop-product-import-file-name" class="break-all text-[13px] font-semibold leading-snug text-slate-800 dark:text-slate-100 sm:text-sm">
                                        {{ __('petshop.import_choose') }}
                                    </p>
                                    <p class="mt-0.5 break-words text-[11px] text-slate-500 dark:text-slate-400 sm:text-xs">{{ __('petshop.import_accepts') }}</p>
                                </div>
                            </div>
                            <span class="hidden shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 sm:inline-block">
                                {{ __('admin.browse') }}
                            </span>
                        </label>
                        <input id="petshop-product-import-file" type="file" name="file" accept=".csv,.txt,.xlsx,.xls" required class="sr-only">
                    </div>

                    <div>
                        <p class="admin-label">{{ __('petshop.import_duplicates') }}</p>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach([
                                ['update', __('petshop.import_strategy_update'), __('petshop.import_strategy_update_desc')],
                                ['skip', __('petshop.import_strategy_skip'), __('petshop.import_strategy_skip_desc')],
                            ] as [$value, $title, $desc])
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900">
                                    <input type="radio" name="duplicate_strategy" value="{{ $value }}" @checked(old('duplicate_strategy', 'update') === $value)
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
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 dark:border-slate-800 dark:bg-slate-950/30">
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ __('petshop.import_step_template') }}</p>
                            <p class="mt-0.5 break-words text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">{{ __('petshop.import_step_template_desc') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a href="{{ route('admin.pet-shop.products.template.csv') }}"
                                   class="admin-btn-secondary !px-2.5 !py-1.5 text-[11px]">
                                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">description</span>
                                    {{ __('petshop.import_template_csv') }}
                                </a>
                                <a href="{{ route('admin.pet-shop.products.template.xlsx') }}"
                                   class="admin-btn-secondary !px-2.5 !py-1.5 text-[11px]">
                                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">table_chart</span>
                                    {{ __('petshop.import_template_xlsx') }}
                                </a>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 dark:border-slate-800 dark:bg-slate-950/30">
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ __('petshop.import_export_hint') }}</p>
                            <p class="mt-0.5 break-words text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">{{ __('petshop.import_export_hint_desc') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a href="{{ route('admin.pet-shop.products.export.csv') }}"
                                   class="admin-btn-secondary !px-2.5 !py-1.5 text-[11px]">
                                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">download</span>
                                    CSV
                                </a>
                                <a href="{{ route('admin.pet-shop.products.export.xlsx') }}"
                                   class="admin-btn-secondary !px-2.5 !py-1.5 text-[11px]">
                                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">download</span>
                                    Excel
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2.5 rounded-xl bg-slate-50 px-3.5 py-3 dark:bg-slate-950/40">
                        <span class="material-symbols-outlined shrink-0 text-[18px] text-slate-400" aria-hidden="true">info</span>
                        <p class="min-w-0 break-words text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">
                            {{ __('petshop.import_note_stock') }}
                        </p>
                    </div>

                    <details class="group rounded-xl border border-slate-200 dark:border-slate-800">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-3.5 py-2.5">
                            <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                                {{ __('petshop.import_columns') }}
                            </span>
                            <span class="material-symbols-outlined text-[18px] text-slate-400 transition group-open:rotate-180" aria-hidden="true">expand_more</span>
                        </summary>
                        <dl class="space-y-2 px-3.5 pb-3.5">
                            @foreach([
                                ['category', __('petshop.import_column_category'), true],
                                ['product_name', __('petshop.import_column_product'), true],
                                ['sku', __('petshop.import_column_sku'), true],
                                ['variant_name', __('petshop.import_column_variant'), true],
                                ['price', __('petshop.import_column_price'), true],
                                ['discount_price', __('petshop.import_column_discount'), false],
                                ['stock', __('petshop.import_column_stock'), false],
                                ['min_stock', __('petshop.import_column_min_stock'), false],
                                ['status', __('petshop.import_column_status'), false],
                                ['brand', __('petshop.brand'), false],
                                ['description', __('petshop.description'), false],
                            ] as [$column, $note, $required])
                                <div class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <dt class="break-all font-mono text-[11px] font-semibold text-slate-700 dark:text-slate-200">{{ $column }}</dt>
                                    <span class="shrink-0 rounded px-1.5 text-[9px] font-bold uppercase tracking-wide {{ $required ? 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300' : 'bg-slate-200/70 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $required ? __('petshop.import_column_required') : __('petshop.import_column_optional') }}
                                    </span>
                                    <dd class="min-w-0 break-words text-[11px] text-slate-500 dark:text-slate-400">{{ $note }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </details>
                </div>
            </div>

            {{-- Aksi --}}
            <div class="flex flex-none flex-col-reverse gap-2 border-t border-slate-100 px-4 py-3 dark:border-slate-800 sm:flex-row sm:justify-end sm:px-6 sm:py-4">
                <button type="button" onclick="closePetshopProductImport()"
                        class="admin-btn-secondary w-full !justify-center sm:w-auto">
                    {{ __('common.cancel') }}
                </button>
                <button type="submit" class="admin-btn-primary w-full !justify-center sm:w-auto">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">publish</span>
                    {{ __('petshop.import_run') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var modal = document.getElementById('petshop-product-import-modal');
        var input = document.getElementById('petshop-product-import-file');
        var label = document.getElementById('petshop-product-import-file-name');

        input?.addEventListener('change', function () {
            if (!label) return;
            label.textContent = this.files?.[0]?.name || @json(__('petshop.import_choose'));
        });

        function lockBodyScroll(lock) {
            document.documentElement.style.overflow = lock ? 'hidden' : '';
            document.body.style.overflow = lock ? 'hidden' : '';
        }

        window.openPetshopProductImport = function () {
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            lockBodyScroll(true);
            window.setTimeout(function () { input?.focus({ preventScroll: true }); }, 120);
        };

        window.closePetshopProductImport = function () {
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            lockBodyScroll(false);
        };

        modal?.addEventListener('click', function (event) {
            if (event.target === this) window.closePetshopProductImport();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                window.closePetshopProductImport();
            }
        });

        // Validasi gagal = modal dibuka lagi supaya pesan error terlihat di
        // tempat aksi terjadi, bukan tertinggal di halaman di belakangnya.
        @if($errors->any())
            window.openPetshopProductImport();
        @endif
    })();
</script>
