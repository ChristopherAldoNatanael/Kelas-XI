@extends('layouts.admin')

@section('title', $product->name . ' — ' . __('petshop.product_detail'))
@section('header', __('petshop.product_detail'))

@php
    $activeVariants = $product->variants->where('is_active', true);
    $totalAvailable = $activeVariants->sum(fn ($variant) => $variant->availableStock());
@endphp

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="$product->name"
        :eyebrow="__('petshop.product_detail')"
        icon="inventory_2"
        :description="$product->brand ?: __('petshop.products_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.pet-shop.products.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('petshop.back') }}
            </a>
            <a href="{{ route('admin.pet-shop.products.edit', $product) }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                {{ __('petshop.edit') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm font-medium text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <x-admin.panel :title="__('petshop.product_detail')" class="lg:col-span-2">
            <div class="flex flex-col gap-5 sm:flex-row">
                <div class="h-40 w-40 flex-shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                    @if($product->image_url)
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full items-center justify-center">
                            <span class="material-symbols-outlined text-[36px] text-slate-300" aria-hidden="true">shopping_bag</span>
                        </div>
                    @endif
                </div>
                <dl class="grid flex-1 grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('petshop.category') }}</dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ $product->category?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('petshop.brand') }}</dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ $product->brand ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('petshop.variants_count') }}</dt>
                        <dd class="mt-1 tabular-nums text-slate-700 dark:text-slate-300">{{ $product->variants->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('petshop.available_stock') }}</dt>
                        <dd class="mt-1 font-semibold tabular-nums {{ $totalAvailable > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">{{ number_format($totalAvailable, 0, ',', '.') }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('petshop.description') }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-slate-600 dark:text-slate-400">{{ $product->description ?: '—' }}</dd>
                    </div>
                </dl>
            </div>
        </x-admin.panel>

        <x-admin.panel :title="__('common.status')">
            <div class="space-y-3">
                <x-admin.badge
                    :label="$product->is_active ? __('petshop.status_active') : __('petshop.status_inactive')"
                    :tone="$product->is_active ? 'primary' : 'slate'"
                    dot />
                <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">{{ __('petshop.products_desc') }}</p>

                <form method="POST" action="{{ route('admin.pet-shop.products.toggle', $product) }}" class="pt-2">
                    @csrf
                    <button type="submit" class="admin-btn-secondary w-full">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $product->is_active ? 'toggle_off' : 'toggle_on' }}</span>
                        {{ $product->is_active ? __('petshop.status_inactive') : __('petshop.status_active') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.pet-shop.products.destroy', $product) }}"
                      onsubmit="return confirm('{{ __('petshop.archive_confirm') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="admin-btn-danger w-full">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">archive</span>
                        {{ __('petshop.archive_product') }}
                    </button>
                </form>
            </div>
        </x-admin.panel>
    </div>

    <x-admin.panel :title="__('petshop.variants_section')" :subtitle="__('petshop.variants_hint')" flush>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('petshop.variant_name') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.sku') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.price') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.stock_on_hand') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.reserved_stock') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.available_stock') }}</th>
                        <th class="px-5 py-3">{{ __('common.status') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('petshop.adjust_stock') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @foreach($product->variants as $variant)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $variant->name }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $variant->sku }}</td>
                            <td class="px-5 py-3 text-sm tabular-nums text-slate-700 dark:text-slate-300">
                                @if($variant->hasDiscount())
                                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($variant->effectivePrice(), 0, ',', '.') }}</span>
                                    <span class="block text-[10px] text-slate-400 line-through">Rp {{ number_format($variant->price, 0, ',', '.') }}</span>
                                @else
                                    Rp {{ number_format($variant->price, 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-sm tabular-nums text-slate-700 dark:text-slate-300">{{ $variant->stock_on_hand }}</td>
                            <td class="px-5 py-3 text-sm tabular-nums text-slate-500 dark:text-slate-400">{{ $variant->stock_reserved }}</td>
                            <td class="px-5 py-3 text-sm font-semibold tabular-nums {{ $variant->availableStock() > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $variant->availableStock() }}
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge :label="$variant->is_active ? __('petshop.status_active') : __('petshop.status_inactive')" :tone="$variant->is_active ? 'primary' : 'slate'" />
                            </td>
                            <td class="px-5 py-3">
                                <details class="relative">
                                    <summary class="admin-btn-secondary !px-2.5 !py-1.5 !text-[11px] cursor-pointer list-none">
                                        {{ __('petshop.adjust_stock') }}
                                    </summary>
                                    <form method="POST" action="{{ route('admin.pet-shop.variants.stock', $variant) }}"
                                          class="absolute right-0 z-20 mt-2 w-72 space-y-2 rounded-xl border border-slate-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                                        @csrf
                                        <p class="text-[11px] leading-4 text-slate-500 dark:text-slate-400">{{ __('petshop.adjust_stock_hint') }}</p>
                                        <input name="quantity" type="number" step="1" required class="admin-input" placeholder="-5 / +10">
                                        <input name="note" required maxlength="200" class="admin-input" placeholder="{{ __('petshop.stock_note') }}">
                                        <button type="submit" class="admin-btn-primary w-full !py-1.5 !text-[11px]">{{ __('petshop.save') }}</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.panel>

    <x-admin.panel :title="__('petshop.stock_movements')" :subtitle="__('petshop.adjust_stock_hint')" flush>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('petshop.date') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.variant_name') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.movement_type') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.movement_quantity') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.movement_stock_after') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.movement_reserved_after') }}</th>
                        <th class="px-5 py-3">{{ __('petshop.notes') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($movements as $movement)
                        <tr class="admin-table-row">
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $movement->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-5 py-3 text-sm text-slate-700 dark:text-slate-300">{{ $movement->variant?->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <x-admin.badge :label="__('petshop.movement_types.' . $movement->type)" tone="slate" />
                            </td>
                            <td class="px-5 py-3 text-sm font-semibold tabular-nums {{ $movement->quantity > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                            </td>
                            <td class="px-5 py-3 text-sm tabular-nums text-slate-700 dark:text-slate-300">{{ $movement->stock_after }}</td>
                            <td class="px-5 py-3 text-sm tabular-nums text-slate-500 dark:text-slate-400">{{ $movement->reserved_after }}</td>
                            <td class="max-w-xs truncate px-5 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $movement->note ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="history" :title="__('petshop.no_movements')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($movements->hasPages())
            <div class="border-t border-slate-100 px-5 py-3.5 dark:border-slate-800">{{ $movements->links() }}</div>
        @endif
    </x-admin.panel>
</div>
@endsection
