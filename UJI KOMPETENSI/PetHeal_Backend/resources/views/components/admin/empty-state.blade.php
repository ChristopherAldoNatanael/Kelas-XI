@props([
    'title',
    'icon' => 'inbox',
    'description' => null,
    'tone' => 'slate',
])

@php
    $tones = [
        'primary' => 'bg-emerald-50 text-emerald-500 dark:bg-emerald-900/30 dark:text-emerald-400',
        'blue' => 'bg-blue-50 text-blue-500 dark:bg-blue-900/30 dark:text-blue-400',
        'sky' => 'bg-sky-50 text-sky-500 dark:bg-sky-900/30 dark:text-sky-400',
        'amber' => 'bg-amber-50 text-amber-500 dark:bg-amber-900/30 dark:text-amber-400',
        'rose' => 'bg-rose-50 text-rose-500 dark:bg-rose-900/30 dark:text-rose-400',
        'violet' => 'bg-violet-50 text-violet-500 dark:bg-violet-900/30 dark:text-violet-400',
        'slate' => 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500',
    ];
    $iconTone = $tones[$tone] ?? $tones['slate'];
@endphp

<div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    <span class="flex h-14 w-14 items-center justify-center rounded-2xl {{ $iconTone }}">
        <span class="material-symbols-outlined text-[26px]" aria-hidden="true">{{ $icon }}</span>
    </span>

    <p class="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $title }}</p>

    @if($description)
        <p class="mt-1.5 max-w-sm text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @endif

    @isset($actions)
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
