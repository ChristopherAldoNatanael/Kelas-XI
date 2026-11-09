@extends('layouts.admin')

@section('title', __('menu.join_requests') . ' — PetHeal Admin')
@section('header', __('menu.join_requests'))

@section('content')
@php
    $counts = $counts ?? [];
    $type = $type ?? 'all';
    $filters = [
        'all' => __('clinics.all_requests'),
        'join' => __('clinics.type_join'),
        'new_clinic' => __('clinics.type_new_clinic'),
    ];
@endphp
<div class="space-y-6">

    {{-- Header halaman --}}
    <x-admin.page-header
        :title="__('menu.join_requests')"
        :eyebrow="__('admin.eyebrow_management')"
        icon="person_add"
        :description="__('clinics.join_requests_desc')" />

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Filter jenis permintaan --}}
    <div class="flex flex-wrap items-center gap-2">
        @foreach($filters as $key => $label)
            @php $isActive = $type === $key; @endphp
            <a href="{{ $key === 'all' ? route('admin.join-requests') : route('admin.join-requests', ['type' => $key]) }}"
               class="inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-semibold transition
                      {{ $isActive
                          ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-700 dark:bg-slate-700'
                          : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300' }}">
                {{ $label }}
                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold
                             {{ $isActive ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                    {{ $counts[$key] ?? 0 }}
                </span>
            </a>
        @endforeach

        @if(($counts['pending'] ?? 0) > 0)
            <span class="ml-auto inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-300">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                {{ $counts['pending'] }} {{ __('common.pending') }}
            </span>
        @endif
    </div>

    {{-- Tabel permintaan --}}
    <x-admin.panel :title="__('clinics.request_status')" :subtitle="__('clinics.join_requests_desc')" flush>
        <x-slot:actions>
            <x-admin.badge :label="__('admin.total') . ' · ' . $requests->total()" tone="slate" />
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('clinics.request_date') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.request_type') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.request_name') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.request_email') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.request_phone') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.request_clinic') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.request_status') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('clinics.request_action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($requests as $request)
                        @php
                            $statusTones = [
                                'pending' => 'amber',
                                'approved' => 'primary',
                                'rejected' => 'rose',
                            ];
                            $isNewClinic = $request->isNewClinic();
                        @endphp
                        <tr class="admin-table-row">
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-600 dark:text-slate-400">
                                {{ $request->created_at->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="$isNewClinic ? __('clinics.type_new_clinic') : __('clinics.type_join')"
                                    :tone="$isNewClinic ? 'primary' : 'slate'" />
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $request->name }}</p>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-600 dark:text-slate-400">{{ $request->email }}</td>
                            <td class="px-5 py-3 text-xs text-slate-600 dark:text-slate-400">{{ $request->phone ?: '—' }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-4 w-4 rounded-full border border-slate-200 dark:border-slate-700"
                                          style="background-color: {{ $request->displayClinicColor() }}"></span>
                                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">
                                        {{ $request->displayClinicName() }}
                                    </span>
                                </div>

                                @if($isNewClinic)
                                    <details class="mt-1.5 group">
                                        <summary class="cursor-pointer list-none text-[11px] font-semibold text-emerald-700 underline underline-offset-2 dark:text-emerald-300">
                                            {{ __('clinics.requested_clinic_data') }}
                                        </summary>
                                        <div class="mt-1.5 space-y-0.5 rounded-lg bg-slate-50 px-2.5 py-2 text-[11px] leading-relaxed text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                            <p>
                                                <span class="font-semibold text-slate-600 dark:text-slate-300">{{ __('clinics.request_slug') }}:</span>
                                                <code class="rounded bg-white px-1 py-0.5 text-[10px] dark:bg-slate-900">{{ $request->requested_clinic_slug }}</code>
                                            </p>
                                            <p>{{ $request->requested_clinic_address }}</p>
                                            <p>
                                                {{ $request->requested_clinic_phone }}
                                                @if(filled($request->requested_clinic_email))
                                                    · {{ $request->requested_clinic_email }}
                                                @endif
                                            </p>
                                            @if(filled($request->requested_description))
                                                <p class="pt-0.5 italic">{{ \Illuminate\Support\Str::limit($request->requested_description, 140) }}</p>
                                            @endif
                                        </div>
                                    </details>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="__('common.' . $request->status)"
                                    :tone="$statusTones[$request->status] ?? 'slate'"
                                    dot />
                                @if($request->status === 'rejected' && filled($request->review_note))
                                    <p class="mt-1 max-w-[220px] text-[10px] italic leading-snug text-rose-600 dark:text-rose-300">
                                        {{ $request->review_note }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if($request->status === 'pending')
                                    <div class="flex flex-col items-center justify-center gap-1.5">
                                        <form method="POST" action="{{ route('admin.join-requests.approve', $request->id) }}" class="inline"
                                              onsubmit="return confirm('{{ __('clinics.approve_confirm', ['name' => $request->name]) }}')">
                                            @csrf
                                            <button type="submit" class="admin-btn-primary !px-3 !py-1.5 !text-[11px]">
                                                <span class="material-symbols-outlined text-[15px]" aria-hidden="true">check</span>
                                                {{ __('common.approve') }}
                                            </button>
                                        </form>

                                        <details class="w-full text-left">
                                            <summary class="admin-btn-danger !px-3 !py-1.5 !text-[11px] justify-center cursor-pointer list-none">
                                                <span class="material-symbols-outlined text-[15px]" aria-hidden="true">close</span>
                                                {{ __('common.reject') }}
                                            </summary>
                                            <form method="POST" action="{{ route('admin.join-requests.reject', $request->id) }}"
                                                  class="mt-1.5 flex min-w-[190px] flex-col gap-1.5 rounded-lg border border-rose-200 bg-rose-50 p-2 dark:border-rose-900/60 dark:bg-rose-900/20">
                                                @csrf
                                                <input type="text" name="review_note" maxlength="1000"
                                                       class="admin-input !py-1.5 !text-[11px]"
                                                       placeholder="{{ __('clinics.request_reject_placeholder') }}"
                                                       aria-label="{{ __('clinics.request_reject_reason') }}">
                                                <button type="submit" class="admin-btn-danger !px-3 !py-1.5 !text-[11px] justify-center"
                                                        onclick="return confirm('{{ __('clinics.reject_confirm', ['name' => $request->name]) }}')">
                                                    {{ __('clinics.request_reject_reason') }}
                                                </button>
                                            </form>
                                        </details>
                                    </div>
                                @elseif($request->reviewedBy)
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $request->reviewedBy->name }}</p>
                                    <p class="text-[10px] text-slate-400">{{ $request->reviewed_at?->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</p>
                                @else
                                    <span class="text-[11px] text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state
                                    :title="__('admin.join_requests_empty_title')"
                                    :description="__('admin.join_requests_empty_desc')"
                                    icon="inbox"
                                    tone="slate" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            {{ $requests->links() }}
        </x-slot:footer>
    </x-admin.panel>
</div>
@endsection
