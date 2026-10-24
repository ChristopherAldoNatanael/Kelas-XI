@if ($paginator->hasPages())
    @php
        // Laravel sudah mengirim `$elements` ke view ini (LengthAwarePaginator::render()).
        // Jangan memanggil $paginator->elements() — method itu protected, dan
        // __call-nya meneruskan ke Koleksi sehingga melempar BadMethodCallException.
        $elements = collect($elements)->values();
    @endphp

    <nav role="navigation" aria-label="{{ __('admin.pagination_label') }}" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ __('common.showing') }}
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $paginator->firstItem() ?? 0 }}</span>
            &ndash;
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $paginator->lastItem() ?? 0 }}</span>
            {{ __('common.of') }}
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $paginator->total() }}</span>
            {{ __('common.results') }}
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-300 dark:border-slate-800 dark:text-slate-600" aria-disabled="true">
                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">chevron_left</span>
                    <span class="hidden sm:inline">{{ __('common.previous') }}</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600">
                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">chevron_left</span>
                    <span class="hidden sm:inline">{{ __('common.previous') }}</span>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="flex h-8 w-8 items-center justify-center text-xs font-semibold text-slate-400">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="flex h-8 min-w-8 items-center justify-center rounded-lg bg-primary px-2 text-xs font-bold text-white shadow-sm">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600">
                    <span class="hidden sm:inline">{{ __('common.next') }}</span>
                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">chevron_right</span>
                </a>
            @else
                <span class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-300 dark:border-slate-800 dark:text-slate-600" aria-disabled="true">
                    <span class="hidden sm:inline">{{ __('common.next') }}</span>
                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">chevron_right</span>
                </span>
            @endif
        </div>
    </nav>
@endif
