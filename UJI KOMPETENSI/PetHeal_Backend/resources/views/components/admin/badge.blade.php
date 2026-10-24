@props([
    'label',
    'tone' => 'slate',
    'dot' => false,
])

@php
    $tones = [
        'primary' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/30 dark:text-emerald-300',
        'blue' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/60 dark:bg-blue-900/30 dark:text-blue-300',
        'sky' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-900/30 dark:text-sky-300',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-900/30 dark:text-amber-300',
        'orange' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/60 dark:bg-orange-900/30 dark:text-orange-300',
        'rose' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/30 dark:text-rose-300',
        'violet' => 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900/60 dark:bg-violet-900/30 dark:text-violet-300',
        'slate' => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
    ];
    $badgeTone = $tones[$tone] ?? $tones['slate'];
    $dotColor = [
        'primary' => 'bg-emerald-500',
        'blue' => 'bg-blue-500',
        'sky' => 'bg-sky-500',
        'amber' => 'bg-amber-500',
        'orange' => 'bg-orange-500',
        'rose' => 'bg-rose-500',
        'violet' => 'bg-violet-500',
        'slate' => 'bg-slate-400',
    ][$tone] ?? 'bg-slate-400';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider ' . $badgeTone]) }}>
    @if($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $dotColor }}" aria-hidden="true"></span>
    @endif
    {{ $label }}
</span>
