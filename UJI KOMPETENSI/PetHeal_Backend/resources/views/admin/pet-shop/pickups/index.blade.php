@extends('layouts.admin')

@section('title', __('petshop.pickup_title') . ' â€” ' . (currentClinic()?->name ?? 'PetHeal') . ' Admin')
@section('header', __('petshop.pickup_title'))

@section('content')
@php
    $verifiedOrder = $verified['order'] ?? null;
    $verifiedPass = $verified['pass'] ?? null;
    $currency = fn ($value) => 'Rp ' . number_format((int) $value, 0, ',', '.');

    // Teks untuk modul pemindai QR (resources/js/pickup-scanner.js). Diteruskan
    // lewat data attribute supaya tidak ada logika bisnis di dalam JavaScript.
    $scanStrings = [
        'idle' => __('petshop.pickup_scan_idle'),
        'live' => __('petshop.pickup_scan_live'),
        'ready' => __('petshop.pickup_scan_ready'),
        'success' => __('petshop.pickup_scan_success'),
        'nodetect' => __('petshop.pickup_scan_nodetect'),
        'nodetect_hint' => __('petshop.pickup_scan_nodetect_hint'),
        'failed' => __('petshop.pickup_scan_failed'),
        'unsupported' => __('petshop.pickup_scan_unsupported'),
        'denied' => __('petshop.pickup_scan_denied'),
        'notfound' => __('petshop.pickup_scan_notfound'),
        'busy' => __('petshop.pickup_scan_busy'),
        'insecure' => __('petshop.pickup_scan_insecure'),
        'start' => __('petshop.pickup_scan_activate'),
        'retry' => __('petshop.pickup_scan_retry'),
        'stop' => __('petshop.pickup_scan_stop'),
        'torch' => __('petshop.pickup_scan_torch'),
        'torch_off' => __('petshop.pickup_scan_torch_off'),
        'privacy' => __('petshop.pickup_scan_privacy'),
    ];
@endphp

<div class="space-y-6">
    <x-admin.page-header
        :title="__('petshop.pickup_title')"
        :eyebrow="__('menu.pet_shop')"
        icon="qr_code_scanner"
        :description="__('petshop.pickup_desc')" />

    @if(session('success'))
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('info'))
        <div class="flex items-center gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-5 py-3.5 text-sm font-medium text-sky-700 dark:border-sky-900/60 dark:bg-sky-900/20 dark:text-sky-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">info</span>
            <span>{{ session('info') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm font-medium text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <p class="font-semibold">{{ __('petshop.pickup_verify_failed') }}</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Pemindai QR: jalur utama. Kamera otomatis hidup; kode manual
             hanya muncul sebagai cadangan saat kamera tidak bisa dipakai. --}}
        <x-admin.panel flush>
            <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">qr_code_scanner</span>
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('petshop.pickup_scan_heading') }}</h2>
                    <p class="admin-hint mt-1">{{ __('petshop.pickup_scan_heading_hint') }}</p>
                </div>
            </div>

            <div class="px-5 py-4" data-scan-root data-scan-strings="{{ json_encode($scanStrings) }}">
                <div class="pickup-scan">
                    <div class="pickup-scan__viewport">
                        <video data-scan-video class="pickup-scan__video" playsinline muted></video>

                        {{-- Keadaan diam / gagal: pesan + tombol kamera. --}}
                        <div data-scan-idle class="absolute inset-0 flex flex-col items-center justify-center gap-2.5 px-6 text-center">
                            <span data-scan-idle-icon class="material-symbols-outlined text-[40px] text-slate-600" aria-hidden="true">qr_code_scanner</span>
                            <p data-scan-idle-title class="text-sm font-semibold text-white">{{ __('petshop.pickup_scan_idle') }}</p>
                            <p data-scan-idle-hint class="max-w-[19rem] text-xs leading-5 text-slate-400">{{ __('petshop.pickup_scan_privacy') }}</p>
                            <button type="button" data-scan-start class="admin-btn-primary mt-1.5">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">photo_camera</span>
                                <span data-scan-start-text>{{ __('petshop.pickup_scan_activate') }}</span>
                            </button>
                        </div>

                        {{-- Bingkai bidik (hanya saat kamera hidup). --}}
                        <div data-scan-frame class="pickup-scan__frame is-hidden">
                            <span class="pickup-scan__corner pickup-scan__corner--tl"></span>
                            <span class="pickup-scan__corner pickup-scan__corner--tr"></span>
                            <span class="pickup-scan__corner pickup-scan__corner--bl"></span>
                            <span class="pickup-scan__corner pickup-scan__corner--br"></span>
                            <span data-scan-beam class="pickup-scan__beam"></span>
                        </div>

                        {{-- Label status & senter (lampu) untuk layar gelap. --}}
                        <div class="absolute inset-x-3 top-3 flex items-start justify-between gap-2">
                            <span data-scan-chip class="pickup-scan__chip is-hidden">
                                <span data-scan-dot class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                <span data-scan-chip-text>{{ __('petshop.pickup_scan_live') }}</span>
                            </span>
                            <button type="button" data-scan-torch
                                    class="pickup-scan__chip is-hidden h-9 w-9 justify-center p-0 transition hover:bg-slate-900"
                                    title="{{ __('petshop.pickup_scan_torch') }}"
                                    aria-label="{{ __('petshop.pickup_scan_torch') }}"
                                    aria-pressed="false">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">flashlight_on</span>
                            </button>
                        </div>

                        {{-- Tombol hentikan kamera (kamera tetap hidup sampai
                             QR ditemukan atau petugas menghentikannya). --}}
                        <button type="button" data-scan-stop
                                class="pickup-scan__chip is-hidden absolute bottom-3 left-1/2 -translate-x-1/2 transition hover:bg-slate-900">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">videocam_off</span>
                            {{ __('petshop.pickup_scan_stop') }}
                        </button>
                    </div>
                </div>

                <p data-scan-status role="status" aria-live="polite" class="admin-hint mt-2"></p>

                {{-- Jalur cadangan: kode manual / token QR. Terbuka otomatis
                     saat kamera tidak bisa dipakai atau QR tidak terbaca. --}}
                <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" data-scan-fallback-toggle
                            class="flex w-full items-center justify-between gap-2 text-left text-sm font-semibold text-slate-600 transition hover:text-primary dark:text-slate-300"
                            aria-expanded="false" aria-controls="pickup_manual_form">
                        <span class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">keyboard</span>
                            {{ __('petshop.pickup_fallback_toggle') }}
                        </span>
                        <span data-scan-fallback-chevron class="material-symbols-outlined text-[18px] transition-transform" aria-hidden="true">expand_more</span>
                    </button>

                    <form id="pickup_manual_form" method="GET" action="{{ route('admin.pet-shop.pickups.index') }}"
                          class="mt-3 space-y-3 {{ $verificationError ? '' : 'is-hidden' }}" data-scan-form>
                        <input type="hidden" name="via" value="manual" data-scan-via>
                        <label for="pickup_code" class="admin-label">{{ __('petshop.pickup_manual_label') }}</label>
                        <input type="text" name="code" id="pickup_code" value="{{ $code }}"
                               autocomplete="off" autocapitalize="characters" spellcheck="false"
                               placeholder="{{ __('petshop.pickup_manual_placeholder') }}"
                               class="admin-input font-mono uppercase tracking-[0.2em] {{ $verificationError ? 'admin-input-error' : '' }}"
                               @if($verificationError) aria-invalid="true" aria-describedby="pickup_code_error" @endif>
                        @if($verificationError)
                            <p id="pickup_code_error" class="admin-error">{{ $verificationError }}</p>
                        @endif
                        <p class="admin-hint">{{ __('petshop.pickup_fallback_hint') }}</p>
                        <button type="submit" class="admin-btn-primary w-full justify-center">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">verified_user</span>
                            {{ __('petshop.pickup_manual_verify') }}
                        </button>

                        {{-- Cadangan terakhir: potret layar pelanggan lalu
                             dekode foto itu di perangkat ini. --}}
                        <div class="border-t border-slate-100 pt-3 dark:border-slate-800">
                            <label for="pickup_qr_photo" class="admin-label">{{ __('petshop.pickup_photo_label') }}</label>
                            <input type="file" id="pickup_qr_photo" accept="image/*" data-scan-file
                                   class="block w-full cursor-pointer rounded-xl border border-slate-200 bg-white text-xs text-slate-600 file:mr-3 file:cursor-pointer file:rounded-l-xl file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-xs file:font-semibold file:text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">
                            <p class="admin-hint">{{ __('petshop.pickup_photo_hint') }}</p>
                        </div>
                    </form>
                </div>
            </div>
        </x-admin.panel>

        {{-- Hasil verifikasi --}}
        <x-admin.panel>
            <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">fact_check</span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('petshop.pickup_result_title') }}</h2>
                    <p class="admin-hint mt-1">{{ __('petshop.pickup_result_hint') }}</p>
                </div>
                @if($verifiedOrder)
                    <a href="{{ route('admin.pet-shop.pickups.index') }}" class="admin-btn-ghost shrink-0">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">qr_code_scanner</span>
                        {{ __('petshop.pickup_scan_again') }}
                    </a>
                @endif
            </div>

            @if($verifiedOrder)
                <div class="space-y-4 px-5 py-4">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-200">
                        <p class="font-semibold">{{ $via === 'qr' ? __('petshop.pickup_verified_via_qr') : __('petshop.pickup_verified_via_manual') }}</p>
                        <p class="mt-1 text-xs">{{ __('petshop.pickup_verified_hint') }}</p>
                    </div>

                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="admin-hint">{{ __('petshop.pickup_customer') }}</dt>
                            <dd class="font-semibold text-slate-900 dark:text-white">{{ $verifiedOrder->customer_name }}</dd>
                            <dd class="text-xs text-slate-500">{{ $verifiedOrder->customer_phone }}</dd>
                        </div>
                        <div>
                            <dt class="admin-hint">{{ __('petshop.order_number') }}</dt>
                            <dd class="font-mono font-semibold text-slate-900 dark:text-white">{{ $verifiedOrder->order_number }}</dd>
                            @if($verifiedPass)
                                <dd class="text-xs text-slate-500">{{ __('petshop.pickup_code_short') }}: <span class="font-mono">{{ substr($verifiedPass->manual_code, 0, 4) }}-{{ substr($verifiedPass->manual_code, 4) }}</span></dd>
                            @endif
                        </div>
                        <div>
                            <dt class="admin-hint">{{ __('petshop.payment_status_label') }}</dt>
                            <dd>
                                @if($verifiedOrder->isPaid())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">check_circle</span>
                                        {{ __('petshop.pickup_paid') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">schedule</span>
                                        {{ __('petshop.pickup_unpaid') }}
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="admin-hint">{{ __('petshop.total') }}</dt>
                            <dd class="font-semibold text-slate-900 dark:text-white">{{ $currency($verifiedOrder->total_amount) }}</dd>
                        </div>
                    </dl>

                    <div class="rounded-xl border border-slate-200 dark:border-slate-700">
                        <table class="w-full text-left text-sm">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="px-4 py-2">{{ __('petshop.product') }}</th>
                                    <th class="px-4 py-2">{{ __('petshop.variant') }}</th>
                                    <th class="px-4 py-2 text-right">{{ __('petshop.quantity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($verifiedOrder->items as $item)
                                    <tr class="border-t border-slate-100 dark:border-slate-800">
                                        <td class="px-4 py-2">{{ $item->product_name }}</td>
                                        <td class="px-4 py-2 text-slate-500">{{ $item->variant_name }}</td>
                                        <td class="px-4 py-2 text-right">{{ (int) $item->quantity }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if(! $verifiedOrder->isPaid())
                        <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900/60 dark:bg-amber-900/10">
                            <p class="mb-3 text-sm font-semibold text-amber-800 dark:text-amber-300">{{ __('petshop.pickup_record_payment_title') }}</p>
                            @include('admin.pet-shop.partials.offline-payment-form', ['order' => $verifiedOrder])
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.pet-shop.pickups.confirm', $verifiedOrder) }}"
                          data-confirm-handover>
                        @csrf
                        <input type="hidden" name="code" value="{{ $code }}">
                        <input type="hidden" name="method" value="{{ $via === 'qr' ? 'qr' : 'manual' }}">
                        <button type="submit" class="admin-btn-primary w-full justify-center"
                                @if(! $verifiedOrder->isPaid()) disabled title="{{ __('petshop.pickup_blocked_unpaid') }}" @endif>
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">inventory_2</span>
                            {{ __('petshop.pickup_confirm_handover') }}
                        </button>
                        @if(! $verifiedOrder->isPaid())
                            <p class="admin-hint mt-2">{{ __('petshop.pickup_blocked_unpaid') }}</p>
                        @endif
                    </form>
                </div>
            @else
                <div class="px-5 py-10 text-center">
                    <span class="material-symbols-outlined text-[40px] text-slate-300 dark:text-slate-600" aria-hidden="true">qr_code_2</span>
                    <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('petshop.pickup_empty') }}</p>
                    <p class="admin-hint mt-1">{{ __('petshop.pickup_empty_hint') }}</p>
                </div>
            @endif
        </x-admin.panel>
    </div>

    {{-- Pesanan yang sedang disiapkan / siap diambil --}}
    <x-admin.panel flush>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('petshop.pickup_ready_orders') }}</h2>
                <p class="admin-hint mt-1">{{ __('petshop.pickup_ready_hint') }}</p>
            </div>
            <form method="GET" action="{{ route('admin.pet-shop.pickups.index') }}" class="flex items-center gap-2">
                <input type="search" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('petshop.search_order') }}" class="admin-input">
                <button type="submit" class="admin-btn-secondary">{{ __('petshop.search') }}</button>
            </form>
        </div>

        @if($readyOrders->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ __('petshop.pickup_no_ready_orders') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('petshop.order_number') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.customer') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.order_status') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.payment_status_label') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.total') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('petshop.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($readyOrders as $readyOrder)
                            <tr class="border-t border-slate-100 align-top dark:border-slate-800">
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs font-semibold text-slate-900 dark:text-white">{{ $readyOrder->order_number }}</span>
                                    @if($readyOrder->pickupPass && $readyOrder->pickupPass->isUsable())
                                        <div class="admin-hint">{{ substr($readyOrder->pickupPass->manual_code, 0, 4) }}-{{ substr($readyOrder->pickupPass->manual_code, 4) }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ $readyOrder->customer_name }}</div>
                                    <div class="text-xs text-slate-500">{{ $readyOrder->customer_phone }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <x-admin.badge
                                        :label="__('petshop.status.' . $readyOrder->status)"
                                        :tone="$readyOrder->status === 'ready_for_pickup' ? 'primary' : 'amber'" />
                                </td>
                                <td class="px-5 py-3">
                                    <x-admin.badge
                                        :label="__('petshop.payment_status.' . $readyOrder->payment_status)"
                                        :tone="$readyOrder->isPaid() ? 'primary' : 'amber'" />
                                </td>
                                <td class="px-5 py-3">{{ $currency($readyOrder->total_amount) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.pet-shop.orders.show', $readyOrder) }}" class="admin-btn-ghost">{{ __('petshop.detail') }}</a>
                                    @if($readyOrder->pickupPass && $readyOrder->pickupPass->isUsable())
                                        <a href="{{ route('admin.pet-shop.pickups.index', ['code' => substr($readyOrder->pickupPass->manual_code, 0, 4) . '-' . substr($readyOrder->pickupPass->manual_code, 4), 'via' => 'manual']) }}"
                                           class="admin-btn-secondary">{{ __('petshop.pickup_verify') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.panel>

    {{-- Riwayat singkat --}}
    <x-admin.panel flush>
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('petshop.pickup_recent') }}</h2>
            <a href="{{ route('admin.pet-shop.pickups.history') }}" class="admin-btn-ghost">{{ __('petshop.pickup_history_all') }}</a>
        </div>

        @if($handovers->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('petshop.pickup_history_empty') }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('petshop.pickup_handed_at') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.order_number') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.pickup_customer') }}</th>
                            <th class="px-5 py-3">{{ __('petshop.pickup_handed_by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($handovers as $handover)
                            <tr class="border-t border-slate-100 dark:border-slate-800">
                                <td class="px-5 py-3 text-xs text-slate-500">{{ $handover->handed_over_at?->translatedFormat('d M Y, H:i') }}</td>
                                <td class="px-5 py-3 font-mono text-xs">{{ $handover->order?->order_number }}</td>
                                <td class="px-5 py-3">{{ $handover->order?->customer_name }}</td>
                                <td class="px-5 py-3">{{ $handover->staff?->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.panel>
</div>

@endsection
