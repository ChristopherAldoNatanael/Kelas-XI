@extends('layouts.admin')

@section('title', __('petshop.merchants_title') . ' — Super Admin')
@section('header', __('petshop.merchants_title'))

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="__('petshop.merchants_title')"
        :eyebrow="__('menu.pet_shop')"
        icon="account_balance"
        :description="__('petshop.merchants_desc')" />

    @if(session('success'))
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless($partnerReady)
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3.5 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">key_off</span>
            <span>{{ __('petshop.partner_not_ready') }}</span>
        </div>
    @endunless

    <x-admin.panel :title="__('petshop.merchants_title')" :subtitle="__('petshop.commission_hint')">
        <div class="space-y-3">
            @foreach($clinics as $clinic)
                @php
                    $config = $configs->get($clinic->id);
                    $ready = $config?->isReadyForOnlinePayment() ?? false;
                    $reason = $config?->onlinePaymentBlockReason();
                @endphp
                <details class="rounded-2xl border border-slate-200 dark:border-slate-800">
                    <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 px-4 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">local_hospital</span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $clinic->name }}</p>
                            <p class="truncate font-mono text-[11px] text-slate-400">{{ $config?->merchant_id ?: __('petshop.not_configured') }}</p>
                        </div>
                        <x-admin.badge :label="$config?->onboarding_status ?: __('petshop.not_configured')" tone="slate" />
                        <x-admin.badge
                            :label="$ready ? __('petshop.ready') : __('petshop.not_ready')"
                            :tone="$ready ? 'primary' : 'amber'"
                            dot />
                        <span class="material-symbols-outlined text-[18px] text-slate-400" aria-hidden="true">expand_more</span>
                    </summary>

                    <form method="POST" action="{{ route('admin.pet-shop.merchants.update', $clinic) }}"
                          class="grid gap-3 border-t border-slate-100 bg-slate-50/60 px-4 py-4 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-800/30">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="admin-label">{{ __('petshop.merchant_id') }}</label>
                            <input name="merchant_id" value="{{ $config?->merchant_id }}" maxlength="64" class="admin-input font-mono" placeholder="G000002">
                        </div>
                        <div>
                            <label class="admin-label">{{ __('petshop.partner_id') }}</label>
                            <input name="partner_id" value="{{ $config?->partner_id }}" maxlength="64" class="admin-input font-mono">
                        </div>
                        <div>
                            <label class="admin-label">{{ __('petshop.environment') }}</label>
                            <select name="environment" class="admin-input">
                                <option value="sandbox" @selected(($config?->environment ?? 'sandbox') === 'sandbox')>sandbox</option>
                                <option value="production" @selected($config?->environment === 'production')>production</option>
                            </select>
                        </div>
                        <div>
                            <label class="admin-label">{{ __('petshop.onboarding_status') }}</label>
                            <select name="onboarding_status" class="admin-input">
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" @selected(($config?->onboarding_status ?? 'not_started') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="admin-label">{{ __('petshop.commission_percent') }}</label>
                            <input type="number" step="0.01" min="0" max="100" name="commission_percent"
                                   value="{{ $config?->commission_percent ?? 0 }}" class="admin-input">
                        </div>
                        <div class="flex flex-col justify-end gap-2">
                            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <input type="checkbox" name="is_online_payment_enabled" value="1"
                                       @checked($config?->is_online_payment_enabled)
                                       class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                {{ __('petshop.online_payment_enabled') }}
                            </label>
                            <p class="text-[11px] text-slate-400">
                                {{ __('petshop.verified_at') }}:
                                {{ $config?->verified_at?->format('d M Y H:i') ?? __('petshop.not_verified') }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="admin-label">{{ __('petshop.notes') }}</label>
                            <textarea name="notes" rows="2" maxlength="1000" class="admin-input">{{ $config?->notes }}</textarea>
                        </div>
                        @if($reason)
                            <p class="sm:col-span-2 text-xs text-amber-600 dark:text-amber-400">
                                {{ __('petshop.not_ready') }}: {{ __('petshop.reason_' . $reason) }}
                            </p>
                        @endif
                        <div class="sm:col-span-2 flex justify-end">
                            <button type="submit" class="admin-btn-primary">{{ __('petshop.save') }}</button>
                        </div>
                    </form>
                </details>
            @endforeach
        </div>
    </x-admin.panel>
</div>
@endsection
