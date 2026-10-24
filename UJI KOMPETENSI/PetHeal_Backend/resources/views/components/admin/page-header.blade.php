@props([
    'title',
    'eyebrow' => null,
    'icon' => null,
    'description' => null,
])

{{--
    Header halaman untuk seluruh konsol admin. Semua halaman memakai pola yang
    sama: label kecil (eyebrow) → judul → penjelasan singkat, dengan aksi
    utama diletakkan di kanan.

    @example
    <x-admin.page-header title="Invoice & Pembayaran" eyebrow="Keuangan" icon="payments"
                         description="Semua tagihan berasal dari booking.">
        <x-slot:actions>…</x-slot:actions>
    </x-admin.page-header>
--}}
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @if($eyebrow)
            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">
                @if($icon)
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $icon }}</span>
                @endif
                <span class="truncate">{{ $eyebrow }}</span>
            </p>
        @endif

        <h2 class="mt-1.5 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h2>

        @if($description)
            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
            {{ $actions }}
        </div>
    @endisset
</div>
