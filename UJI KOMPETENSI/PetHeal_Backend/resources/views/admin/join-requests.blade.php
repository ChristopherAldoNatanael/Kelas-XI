@extends('layouts.admin')

@section('title', __('menu.join_requests') . ' — PetHeal Admin')
@section('header', __('menu.join_requests'))

@section('content')
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
                        @endphp
                        <tr class="admin-table-row">
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-600 dark:text-slate-400">
                                {{ $request->created_at->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $request->name }}</p>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-600 dark:text-slate-400">{{ $request->email }}</td>
                            <td class="px-5 py-3 text-xs text-slate-600 dark:text-slate-400">{{ $request->phone ?: '—' }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-4 w-4 rounded-full border border-slate-200 dark:border-slate-700"
                                          style="background-color: {{ $request->clinic->primary_color }}"></span>
                                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $request->clinic->name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="__('common.' . $request->status)"
                                    :tone="$statusTones[$request->status] ?? 'slate'"
                                    dot />
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if($request->status === 'pending')
                                    <div class="flex items-center justify-center gap-1.5">
                                        <form method="POST" action="{{ route('admin.join-requests.approve', $request->id) }}" class="inline"
                                              onsubmit="return confirm('{{ __('clinics.approve_confirm', ['name' => $request->name]) }}')">
                                            @csrf
                                            <button type="submit" class="admin-btn-primary !px-3 !py-1.5 !text-[11px]">
                                                <span class="material-symbols-outlined text-[15px]" aria-hidden="true">check</span>
                                                {{ __('common.approve') }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.join-requests.reject', $request->id) }}" class="inline"
                                              onsubmit="return confirm('{{ __('clinics.reject_confirm', ['name' => $request->name]) }}')">
                                            @csrf
                                            <button type="submit" class="admin-btn-danger !px-3 !py-1.5 !text-[11px]">
                                                <span class="material-symbols-outlined text-[15px]" aria-hidden="true">close</span>
                                                {{ __('common.reject') }}
                                            </button>
                                        </form>
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
                            <td colspan="7">
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
