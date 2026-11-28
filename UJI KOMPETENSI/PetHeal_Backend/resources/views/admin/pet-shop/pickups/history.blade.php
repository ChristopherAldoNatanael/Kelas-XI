@extends('layouts.admin')

@section('title', __('petshop.pickup_history_title') . ' — ' . (currentClinic()?->name ?? 'PetHeal') . ' Admin')
@section('header', __('petshop.pickup_history_title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('petshop.pickup_history_title')"
        :eyebrow="__('menu.pet_shop')"
        icon="history"
        :description="__('petshop.pickup_history_desc')" />

    <a href="{{ route('admin.pet-shop.pickups.index') }}" class="admin-btn-ghost w-fit">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
        {{ __('petshop.pickup_back') }}
    </a>

    <x-admin.panel flush>
        @if($handovers->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ __('petshop.pickup_history_empty') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('petshop.pickup_handed_at') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.order_number') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.pickup_customer') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.pickup_items') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.pickup_payment') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.pickup_handed_by') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.pickup_method') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($handovers as $handover)
                            <tr class="border-t border-slate-100 align-top dark:border-slate-800">
                                <td class="px-5 py-3 text-xs text-slate-500">
                                    {{ $handover->handed_over_at?->translatedFormat('d M Y, H:i') }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs font-semibold text-slate-900 dark:text-white">{{ $handover->order?->order_number }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ $handover->order?->customer_name }}</div>
                                    <div class="text-xs text-slate-500">{{ $handover->order?->customer_phone }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <ul class="space-y-0.5 text-xs text-slate-600 dark:text-slate-300">
                                        @foreach(($handover->items_snapshot ?? []) as $item)
                                            <li>{{ $item['quantity'] }}× {{ $item['product_name'] }} <span class="text-slate-500">({{ $item['variant_name'] }})</span></li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="px-5 py-3 text-xs">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ format_rupiah((int) ($handover->order?->total_amount ?? 0)) }}</div>
                                    <div class="text-slate-500">{{ __('petshop.payment_status.' . ($handover->order?->payment_status ?? 'unpaid')) }}</div>
                                </td>
                                <td class="px-5 py-3 text-sm">{{ $handover->staff?->name ?? '-' }}</td>
                                <td class="px-5 py-3 text-xs uppercase tracking-wide text-slate-500">{{ $handover->verification_method }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                {{ $handovers->links() }}
            </div>
        @endif
    </x-admin.panel>
</div>
@endsection
