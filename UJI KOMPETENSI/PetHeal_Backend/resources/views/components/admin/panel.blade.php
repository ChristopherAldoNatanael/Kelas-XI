@props([
    'title' => null,
    'subtitle' => null,
    'flush' => false,
])

{{--
    Panel standar untuk konsol admin: kartu putih (gelap di mode gelap) dengan
    header opsional, isi, dan footer opsional.

    @example
    <x-admin.panel title="Daftar booking" subtitle="12 booking minggu ini" flush>
        <x-slot:actions>…</x-slot:actions>
        …isi tabel…
        <x-slot:footer>{{ $bookings->links() }}</x-slot:footer>
    </x-admin.panel>
--}}
<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900']) }}>
    @if($title || isset($actions))
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div class="min-w-0">
                @if($title)
                    <h3 class="text-sm font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="{{ $flush ? '' : 'p-5' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="border-t border-slate-100 px-5 py-3.5 dark:border-slate-800">
            {{ $footer }}
        </footer>
    @endisset
</section>
