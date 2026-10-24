@props([
    'label',
    'value',
    'icon' => 'insights',
    'hint' => null,
    'tone' => 'primary',
])

@php
    $tones = [
        'primary' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
        'blue' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        'sky' => 'bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
        'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400',
        'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400',
        'slate' => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300',
    ];
    $iconTone = $tones[$tone] ?? $tones['primary'];
@endphp

<div class="rounded-2xl border border-slate-200/70 bg-white p-5 transition-shadow hover:shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ $label }}</p>
            <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight text-slate-900 dark:text-white">{{ $value }}</p>
        </div>
        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $iconTone }}">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $icon }}</span>
        </span>
    </div>

    @if($hint)
        <p class="mt-2 text-[11px] leading-5 text-slate-400">{{ $hint }}</p>
    @endif

    @isset($footer)
        <div class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">{{ $footer }}</div>
    @endisset
</div>
