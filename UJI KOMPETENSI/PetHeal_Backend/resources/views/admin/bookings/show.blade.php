@php
    /** @var \App\Models\Booking $booking */
    $hasPayment = (bool) ($booking->payment_type || $booking->total_amount > 0);
    $progress = $booking->total_amount > 0 ? min(100, round(($booking->paid_amount / $booking->total_amount) * 100)) : 0;
    $canFinish = in_array($booking->status, ['pending', 'confirmed'], true);
    $currency = static fn ($amount) => 'Rp ' . number_format((float) $amount, 0, ',', '.');
    $date = \Carbon\Carbon::parse($booking->booking_date);
@endphp

@extends('layouts.admin')

@section('title', __('admin.booking_detail_title') . ' #' . $booking->id . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.booking_detail_title'))

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        :title="__('admin.booking_detail_title') . ' #' . $booking->id"
        :eyebrow="__('admin.eyebrow_operations')"
        icon="event"
        :description="__('admin.booking_detail_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.bookings.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Aksi status booking --}}
    <x-admin.panel>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <x-admin.badge :label="\App\Support\AdminStatus::bookingLabel($booking->status)" :tone="\App\Support\AdminStatus::bookingTone($booking->status)" dot />
                <x-admin.badge :label="\App\Support\AdminStatus::paymentLabel($booking->payment_status)" :tone="\App\Support\AdminStatus::paymentTone($booking->payment_status)" />
                @if($booking->service?->name)
                    <span class="text-sm text-slate-500 dark:text-slate-400">· {{ $booking->service->name }}</span>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($booking->status === 'pending')
                    <form method="POST" action="{{ route('admin.bookings.confirm', $booking->id) }}">
                        @csrf
                        <button type="submit" class="admin-btn-primary">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check_circle</span>
                            {{ __('admin.confirm_booking_action') }}
                        </button>
                    </form>
                @endif

                @if($canFinish)
                    <button type="button" onclick="document.getElementById('reminder-modal').style.display='flex'"
                            class="admin-btn-secondary">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">notifications</span>
                        {{ __('admin.remind_action') }}
                    </button>

                    @if(!$booking->medicalRecord)
                        <a href="{{ route('admin.medical-records.create', $booking->id) }}" class="admin-btn-secondary">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">clinical_notes</span>
                            {{ __('admin.create_medical_record') }}
                        </a>
                    @endif

                    <form method="POST" action="{{ route('admin.bookings.complete', $booking->id) }}">
                        @csrf
                        <button type="submit" class="admin-btn-primary">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">task_alt</span>
                            {{ __('admin.finish_booking') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-admin.panel>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.6fr_0.95fr]">
        <div class="space-y-6">

            {{-- Ringkasan kunjungan --}}
            <x-admin.panel :title="__('admin.visit_summary')">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.pet') }}</p>
                        <div class="mt-2 flex items-center gap-3">
                            @if($booking->pet?->photo_url)
                                <img src="{{ $booking->pet->photo_url }}" alt="" class="h-12 w-12 rounded-full object-cover">
                            @else
                                <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30">
                                    <span class="material-symbols-outlined text-[22px]" aria-hidden="true">pets</span>
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $booking->pet->name ?? __('admin.pet_placeholder') }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $booking->pet->species ?? '—' }}{{ $booking->pet?->breed ? ' · ' . $booking->pet->breed : '' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.owner') }}</p>
                        <div class="mt-2 flex items-center gap-3">
                            <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300" aria-hidden="true">
                                {{ mb_substr($booking->user->name ?? 'P', 0, 1) }}
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('admin.users.show', $booking->user_id) }}"
                                   class="block truncate text-sm font-bold text-slate-900 transition hover:text-primary dark:text-white">
                                    {{ $booking->user->name ?? __('admin.owner_placeholder') }}
                                </a>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $booking->user->phone ?? $booking->user->email }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.doctor') }}</p>
                        <p class="mt-2 text-sm font-bold text-slate-900 dark:text-white">{{ $booking->doctor->name ?? '—' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $booking->doctor->specialization ?? __('admin.veterinarian') }}</p>
                    </div>

                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.booking_date_time') }}</p>
                        <p class="mt-2 text-sm font-bold text-slate-900 dark:text-white">
                            {{ $date->locale(app()->getLocale())->translatedFormat('l, d F Y') }}
                        </p>
                        <p class="text-xs tabular-nums text-slate-500 dark:text-slate-400">
                            {{ __('admin.at_time', ['time' => \Carbon\Carbon::parse($booking->booking_time)->format('H:i')]) }}
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.service_label') }}</p>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800/50">
                            <span class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                {{ $booking->service?->name ?? __('admin.service_not_selected') }}
                            </span>
                            @if($booking->service?->price)
                                <span class="text-sm font-bold tabular-nums text-slate-900 dark:text-white">{{ $currency($booking->service->price) }}</span>
                            @endif
                        </div>
                    </div>

                    @if($booking->notes)
                        <div class="sm:col-span-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.owner_notes') }}</p>
                            <p class="mt-2 rounded-xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-700 dark:bg-slate-800/50 dark:text-slate-300">{{ $booking->notes }}</p>
                        </div>
                    @endif

                    @if($booking->cancellation_reason)
                        <div class="sm:col-span-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500">{{ __('admin.cancellation_reason') }}</p>
                            <p class="mt-2 rounded-xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-300">
                                {{ $booking->cancellation_reason }}
                            </p>
                        </div>
                    @endif
                </div>
            </x-admin.panel>

            {{-- Pembayaran --}}
            <x-admin.panel id="pembayaran" :title="__('admin.payment_section')">
                <x-slot:actions>
                    <x-admin.badge
                        :label="\App\Support\AdminStatus::paymentLabel($booking->payment_status)"
                        :tone="\App\Support\AdminStatus::paymentTone($booking->payment_status)" />
                </x-slot:actions>

                @if($hasPayment)
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <x-admin.stat-card :label="__('admin.total_bill')" :value="$currency($booking->total_amount)" icon="receipt_long" tone="slate" />
                        <x-admin.stat-card :label="__('admin.col_paid')" :value="$currency($booking->paid_amount)" icon="paid" tone="primary" />
                        <x-admin.stat-card :label="__('admin.remaining_label')" :value="$currency($booking->remaining_amount)" icon="hourglass_top" tone="amber" />
                        <x-admin.stat-card
                            :label="__('admin.col_type')"
                            :value="$booking->payment_type === 'dp' ? __('admin.type_dp') : ($booking->payment_type ? __('admin.type_full') : '—')"
                            icon="category"
                            tone="blue" />
                    </div>

                    <div class="mt-5">
                        <div class="mb-1.5 flex items-center justify-between text-[11px]">
                            <span class="font-semibold text-slate-500 dark:text-slate-400">{{ __('admin.payment_progress') }}</span>
                            <span class="font-bold tabular-nums text-primary">{{ $progress }}%</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 text-xs dark:border-slate-800">
                        <div>
                            <dt class="text-slate-400">{{ __('admin.col_method') }}</dt>
                            <dd class="mt-0.5 font-semibold capitalize text-slate-700 dark:text-slate-300">
                                {{ $booking->payment_method ?: __('admin.service_not_selected') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">{{ __('admin.payment_date_label') }}</dt>
                            <dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-300">
                                {{ $booking->payment_date ? \Carbon\Carbon::parse($booking->payment_date)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') : '—' }}
                            </dd>
                        </div>
                        @if($booking->payment_type === 'dp' && $booking->dp_amount > 0)
                            <div>
                                <dt class="text-slate-400">{{ __('admin.dp_required') }}</dt>
                                <dd class="mt-0.5 font-semibold tabular-nums text-slate-700 dark:text-slate-300">{{ $currency($booking->dp_amount) }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-slate-400">{{ __('admin.invoice_number') }}</dt>
                            <dd class="mt-0.5 font-mono font-semibold text-slate-700 dark:text-slate-300">
                                INV-{{ str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT) }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
                        <a href="{{ route('admin.payments.receipt', $booking->id) }}" class="admin-btn-secondary">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">download</span>
                            {{ __('admin.download_invoice_btn') }}
                        </a>

                        @if($booking->payment_status !== 'paid' && $booking->remaining_amount > 0)
                            <form method="POST" action="{{ route('admin.payments.send-reminder', $booking->id) }}">
                                @csrf
                                <button type="submit" class="admin-btn-secondary">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">send</span>
                                    {{ __('admin.send_invoice_btn') }}
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('admin.payments.index', ['search' => $booking->id]) }}"
                           class="ml-auto text-xs font-semibold text-slate-500 transition hover:text-primary dark:text-slate-400">
                            {{ __('admin.view_in_payment_list') }}
                        </a>
                    </div>

                    @if($booking->payment_status !== 'paid' && $booking->remaining_amount > 0)
                        <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/40">
                            <summary class="cursor-pointer text-xs font-bold text-slate-600 dark:text-slate-300">
                                {{ __('admin.record_payment_manual') }}
                            </summary>
                            <form method="POST" action="{{ route('admin.payments.confirm', $booking->id) }}" class="mt-4 space-y-3">
                                @csrf
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="payment-amount" class="admin-label">{{ __('admin.amount_paid_label') }}</label>
                                        <input id="payment-amount" type="number" name="amount"
                                               value="{{ (int) $booking->remaining_amount }}" min="0" max="{{ (int) $booking->remaining_amount }}" required
                                               class="admin-input tabular-nums">
                                    </div>
                                    <div>
                                        <label for="payment-notes" class="admin-label">{{ __('admin.payment_notes_optional') }}</label>
                                        <input id="payment-notes" type="text" name="notes" maxlength="500"
                                               placeholder="{{ __('admin.payment_notes_placeholder') }}" class="admin-input">
                                    </div>
                                </div>
                                <button type="submit" class="admin-btn-primary">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check_circle</span>
                                    {{ __('admin.save_payment') }}
                                </button>
                            </form>
                        </details>
                    @endif
                @else
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('admin.no_bill_yet') }}</p>
                @endif
            </x-admin.panel>

            {{-- Rekam medis --}}
            @if($booking->medicalRecord)
                <x-admin.panel :title="__('admin.medical_record_section')">
                    <x-slot:actions>
                        <a href="{{ route('admin.medical-records.show', $booking->medicalRecord->id) }}"
                           class="text-xs font-semibold text-primary hover:underline">{{ __('admin.open_full_record') }}</a>
                    </x-slot:actions>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.diagnosis') }}</p>
                            <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $booking->medicalRecord->diagnosis }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.treatment') }}</p>
                            <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $booking->medicalRecord->treatment }}</p>
                        </div>
                        @if($booking->medicalRecord->medicine)
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.medicine') }}</p>
                                <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $booking->medicalRecord->medicine }}</p>
                            </div>
                        @endif
                        @if($booking->medicalRecord->next_visit_date)
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.follow_up') }}</p>
                                <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">
                                    {{ \Carbon\Carbon::parse($booking->medicalRecord->next_visit_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                    @if($booking->medicalRecord->next_visit_time)
                                        · {{ __('admin.at_time', ['time' => \Carbon\Carbon::parse($booking->medicalRecord->next_visit_time)->format('H:i')]) }}
                                    @endif
                                </p>
                            </div>
                        @endif
                        @if($booking->medicalRecord->notes)
                            <div class="sm:col-span-2">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.booking_notes') }}</p>
                                <p class="mt-1.5 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $booking->medicalRecord->notes }}</p>
                            </div>
                        @endif
                    </div>
                </x-admin.panel>
            @endif
        </div>

        {{-- Sisi kanan --}}
        <aside class="space-y-6">
            <x-admin.panel :title="__('admin.status_history')">
                <ol class="space-y-4">
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-slate-300" aria-hidden="true"></span>
                        <div>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('admin.status_created') }}</p>
                            <p class="text-[11px] text-slate-400">{{ $booking->created_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                    </li>
                    @if($booking->confirmed_at)
                        <li class="flex gap-3">
                            <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-sky-400" aria-hidden="true"></span>
                            <div>
                                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('admin.status_confirmed') }}</p>
                                <p class="text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($booking->confirmed_at)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}</p>
                            </div>
                        </li>
                    @endif
                    @if($booking->completed_at)
                        <li class="flex gap-3">
                            <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-emerald-400" aria-hidden="true"></span>
                            <div>
                                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('admin.status_completed') }}</p>
                                <p class="text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($booking->completed_at)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}</p>
                            </div>
                        </li>
                    @endif
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-amber-300" aria-hidden="true"></span>
                        <div>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('admin.last_reminded') }}</p>
                            <p class="text-[11px] text-slate-400">
                                {{ $booking->last_reminder_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i') ?? __('admin.not_reminded_yet') }}
                            </p>
                        </div>
                    </li>
                </ol>
            </x-admin.panel>

            @if($canFinish)
                <x-admin.panel :title="__('admin.cancel_booking_title')">
                    <p class="text-[11px] leading-5 text-slate-500 dark:text-slate-400">{{ __('admin.cancel_booking_notice') }}</p>
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs font-bold text-rose-600 dark:text-rose-400">
                            {{ __('admin.cancel_booking_fill_reason') }}
                        </summary>
                        <form method="POST" action="{{ route('admin.bookings.cancel', $booking->id) }}" class="mt-3 space-y-2">
                            @csrf
                            <textarea name="reason" rows="3" required
                                      placeholder="{{ __('admin.cancel_reason_placeholder') }}"
                                      class="admin-input resize-none focus:border-rose-400 focus:ring-rose-500/20"></textarea>
                            <button type="submit" class="admin-btn-danger w-full justify-center">
                                {{ __('admin.cancel_booking_action') }}
                            </button>
                        </form>
                    </details>
                </x-admin.panel>
            @endif
        </aside>
    </div>
</div>

{{-- Modal pengingat --}}
<div id="reminder-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm" style="display:none" role="dialog" aria-modal="true" aria-labelledby="reminder-modal-title">
    <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-500" aria-hidden="true">notifications</span>
                <div>
                    <h3 id="reminder-modal-title" class="text-sm font-bold text-slate-900 dark:text-white">{{ __('admin.reminder_modal_title') }}</h3>
                    <p class="text-[11px] text-slate-400">{{ __('admin.reminder_to', ['name' => $booking->user->name ?? __('admin.owner_placeholder')]) }}</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('reminder-modal').style.display='none'"
                    class="text-slate-400 transition hover:text-slate-600" aria-label="{{ __('common.close') }}">
                <span class="material-symbols-outlined text-xl" aria-hidden="true">close</span>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.bookings.send-reminder', $booking->id) }}" class="p-5">
            @csrf
            <div class="mb-4 flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30">
                    <span class="material-symbols-outlined" aria-hidden="true">pets</span>
                </span>
                <div class="min-w-0 text-xs">
                    <p class="truncate font-semibold text-slate-800 dark:text-slate-200">{{ $booking->pet->name ?? __('admin.pet_placeholder') }}</p>
                    <p class="text-slate-500 dark:text-slate-400">
                        {{ $date->locale(app()->getLocale())->translatedFormat('d M Y') }} · {{ $booking->booking_time }} WIB
                    </p>
                </div>
            </div>

            <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ __('admin.reminder_type') }}</p>
            <div class="space-y-2">
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-amber-400 dark:border-slate-700">
                    <input type="radio" name="reminder_type" value="1_hour" class="accent-amber-500" onchange="toggleCustomMsg(this)">
                    <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('admin.reminder_1_hour') }}</span>
                        <span class="block text-[11px] text-slate-500">{{ __('admin.reminder_1_hour_desc') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-amber-400 dark:border-slate-700">
                    <input type="radio" name="reminder_type" value="tomorrow" checked class="accent-amber-500" onchange="toggleCustomMsg(this)">
                    <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('admin.reminder_tomorrow') }}</span>
                        <span class="block text-[11px] text-slate-500">{{ __('admin.reminder_tomorrow_desc') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-amber-400 dark:border-slate-700">
                    <input type="radio" name="reminder_type" value="custom" class="accent-amber-500" onchange="toggleCustomMsg(this)">
                    <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('admin.reminder_custom') }}</span>
                        <span class="block text-[11px] text-slate-500">{{ __('admin.reminder_custom_desc') }}</span>
                    </span>
                </label>
            </div>

            <div id="custom-msg-wrap" class="mt-3" style="display:none">
                <label for="custom_message" class="admin-label">{{ __('admin.custom_message') }}</label>
                <textarea id="custom_message" name="custom_message" rows="3"
                          placeholder="{{ __('admin.custom_message_placeholder') }}"
                          class="admin-input resize-none"></textarea>
            </div>

            <div class="mt-4 flex gap-2">
                <button type="button" onclick="document.getElementById('reminder-modal').style.display='none'"
                        class="admin-btn-ghost flex-1 justify-center">
                    {{ __('common.cancel') }}
                </button>
                <button type="submit" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600">
                    {{ __('admin.send_now') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleCustomMsg(radio) {
        document.getElementById('custom-msg-wrap').style.display = radio.value === 'custom' ? 'block' : 'none';
    }
    document.getElementById('reminder-modal').addEventListener('click', function (event) {
        if (event.target === this) this.style.display = 'none';
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') document.getElementById('reminder-modal').style.display = 'none';
    });
</script>
@endsection
