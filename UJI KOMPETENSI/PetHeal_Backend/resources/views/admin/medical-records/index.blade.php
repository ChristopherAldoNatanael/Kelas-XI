@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $records */
$pageItems = $records->count();
$currency = static fn ($amount) => 'Rp ' . number_format((float) $amount, 0, ',', '.');
@endphp

@extends('layouts.admin')

@section('title', __('menu.medical_records') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.medical_records'))

@section('content')
<div class="space-y-6">

    {{-- Header halaman --}}
    <x-admin.page-header
        :title="__('admin.records_title')"
        :eyebrow="__('admin.documentation_clinical')"
        icon="clinical_notes"
        :description="__('admin.records_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.medical-records.export.pdf', request()->query()) }}" target="_blank" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">picture_as_pdf</span>
                {{ __('admin.download_pdf') }}
            </a>
            <a href="{{ route('admin.medical-records.export.csv', request()->query()) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">download</span>
                {{ __('admin.download_csv') }}
            </a>
            <button type="button" onclick="window.print()" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">print</span>
                {{ __('admin.print') }}
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Kartu ringkasan --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.stat-card
            :label="__('admin.stat_total_records')"
            :value="number_format($totalRecords)"
            :hint="__('admin.stat_records_shown', ['count' => number_format($pageItems)])"
            icon="folder_shared"
            tone="primary" />

        <x-admin.stat-card
            :label="__('admin.stat_followup_cost')"
            :value="number_format($extraPendingCount)"
            :hint="__('admin.stat_followup_cost_desc')"
            icon="payments"
            tone="amber" />

        <x-admin.stat-card
            :label="__('admin.stat_treatment_cost')"
            :value="$currency($totalMedicalRevenue)"
            :hint="__('admin.stat_treatment_cost_desc')"
            icon="monitoring"
            tone="sky" />
    </div>

    {{-- Filter --}}
    <x-admin.panel :title="__('admin.filter')" :subtitle="__('admin.filter_desc')">
        <form method="GET" action="{{ route('admin.medical-records.index') }}"
              class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="records-from" class="admin-label">{{ __('admin.filter_from_date') }}</label>
                <input id="records-from" type="date" name="from" value="{{ request('from') }}" class="admin-input">
            </div>

            <div>
                <label for="records-to" class="admin-label">{{ __('admin.filter_to_date') }}</label>
                <input id="records-to" type="date" name="to" value="{{ request('to') }}" class="admin-input">
            </div>

            <div>
                <label for="records-payment-status" class="admin-label">{{ __('admin.filter_extra_payment') }}</label>
                <select id="records-payment-status" name="payment_status" class="admin-input">
                    <option value="">{{ __('admin.all_status') }}</option>
                    @foreach([
                        'not_required' => __('admin.extra_not_required'),
                        'unpaid' => __('common.unpaid'),
                        'pending' => __('common.pending'),
                        'partial' => __('common.partial'),
                        'paid' => __('common.paid'),
                        'failed' => __('common.failed'),
                    ] as $status => $statusLabel)
                        <option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="admin-btn-primary flex-1">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
                    {{ __('admin.apply_filter') }}
                </button>
                <a href="{{ route('admin.medical-records.index') }}" class="admin-btn-ghost">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">restart_alt</span>
                    {{ __('admin.reset_filter') }}
                </a>
            </div>
        </form>

        <p class="admin-hint mt-4">{{ __('admin.records_export_hint') }}</p>
    </x-admin.panel>

    {{-- Tabel rekam medis --}}
    <x-admin.panel :title="__('admin.record_register')" :subtitle="__('admin.record_register_desc')" flush>
        <x-slot:actions>
            <x-admin.badge :label="__('admin.rows_count', ['count' => number_format($pageItems)])" tone="slate" />
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full min-w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('admin.col_patient') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_doctor') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_clinical_summary') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_financials') }}</th>
                        <th class="px-5 py-3">{{ __('admin.col_timeline') }}</th>
                        <th class="px-5 py-3 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($records as $record)
                        @php
                            $totalCost = (float) ($record->total_medical_cost ?? (($record->cost ?? 0) + ($record->treatment_cost ?? 0) + ($record->medicine_cost ?? 0)));
                            $extraStatus = $record->extra_payment_status ?? 'not_required';
                        @endphp
                        <tr class="admin-table-row align-top">
                            <td class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    @if($record->pet?->photo_url)
                                        <img class="h-10 w-10 flex-shrink-0 rounded-xl object-cover ring-1 ring-slate-200"
                                             src="{{ $record->pet->photo_url }}"
                                             alt="{{ $record->pet->name }}">
                                    @else
                                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200/70 dark:bg-emerald-900/30 dark:text-emerald-400"
                                              aria-hidden="true">
                                            <span class="material-symbols-outlined text-[20px]">pets</span>
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $record->pet->name }}</p>
                                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ $record->booking->user->name ?? __('admin.unknown_owner') }}
                                        </p>
                                        <p class="mt-1.5 inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                            <span class="material-symbols-outlined text-[12px]" aria-hidden="true">tag</span>
                                            {{ __('admin.booking_ref', ['id' => $record->booking_id]) }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $record->doctor->name }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ $record->doctor->specialization ?: __('admin.veterinarian') }}
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-semibold leading-6 text-slate-800 dark:text-slate-200">{{ $record->diagnosis }}</p>
                                <p class="mt-1.5 max-w-md text-[11px] leading-5 text-slate-500 dark:text-slate-400">
                                    {{ \Illuminate\Support\Str::limit($record->treatment, 110) }}
                                </p>
                                @if($record->next_visit_date)
                                    <x-admin.badge
                                        class="mt-2"
                                        :label="__('admin.follow_up') . ' ' . \Carbon\Carbon::parse($record->next_visit_date)->locale(app()->getLocale())->translatedFormat('d M Y')"
                                        tone="amber" />
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">{{ $currency($totalCost) }}</p>
                                <p class="mt-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">
                                    {{ __('admin.total_medical_cost') }}
                                </p>
                                <div class="mt-2 space-y-1.5">
                                    <div class="flex items-center justify-between gap-4 text-[11px]">
                                        <span class="text-slate-500 dark:text-slate-400">{{ __('admin.extra_payment') }}</span>
                                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $currency($record->extra_payment_amount ?? 0) }}</span>
                                    </div>
                                    <x-admin.badge
                                        :label="\App\Support\AdminStatus::extraPaymentLabel($extraStatus)"
                                        :tone="\App\Support\AdminStatus::extraPaymentTone($extraStatus)"
                                        dot />
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $record->created_at->locale(app()->getLocale())->translatedFormat('d M Y') }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ $record->created_at->format('H:i') }}</p>
                                @if($record->updated_at->ne($record->created_at))
                                    <p class="mt-2 text-[10px] uppercase tracking-[0.14em] text-slate-400">
                                        {{ __('admin.updated_ago', ['time' => $record->updated_at->diffForHumans()]) }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.medical-records.show', $record->id) }}"
                                       class="admin-icon-btn hover:!text-primary"
                                       title="{{ __('admin.view_detail') }}"
                                       aria-label="{{ __('admin.view_detail') }} #{{ $record->id }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
                                    </a>
                                    <a href="{{ route('admin.medical-records.edit', $record->id) }}"
                                       class="admin-icon-btn hover:!text-sky-600"
                                       title="{{ __('admin.edit_record') }}"
                                       aria-label="{{ __('admin.edit_record') }} #{{ $record->id }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span>
                                    </a>
                                    <form method="POST" action="{{ route('admin.medical-records.destroy', $record->id) }}" class="inline"
                                          onsubmit="return confirm('{{ __('admin.delete_record_confirm') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="admin-icon-btn hover:!text-rose-600"
                                                title="{{ __('admin.delete') }}"
                                                aria-label="{{ __('admin.delete') }} #{{ $record->id }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state
                                    :title="__('admin.records_empty_title')"
                                    :description="__('admin.records_empty_desc')"
                                    icon="clinical_notes"
                                    tone="slate" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            {{ $records->links() }}
        </x-slot:footer>
    </x-admin.panel>
</div>
@endsection
