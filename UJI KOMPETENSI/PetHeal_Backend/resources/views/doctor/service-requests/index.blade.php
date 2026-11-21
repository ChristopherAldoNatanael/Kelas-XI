@extends('layouts.admin')

@section('title', 'Evaluasi Permintaan Layanan — ' . ($doctor->clinic?->name ?? 'PetHeal'))
@section('header', 'Evaluasi Permintaan Layanan')

@php
    use App\Models\ServiceRequest;
@endphp

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        title="{{ __('doctor.requests_to_review') }}"
        eyebrow="{{ __('doctor.portal_eyebrow') }}"
        icon="medical_information"
        description="Permintaan yang ditugaskan admin klinik kepada Anda. Berikan rekomendasi medis; harga dan penawaran tetap disusun admin.">
        <x-slot:actions>
            <form method="GET" action="{{ route('doctor.service-requests.index') }}" class="flex items-center gap-2">
                <select name="status" class="admin-input !w-52 !py-2">
                    <option value="">{{ __('doctor.all_statuses') }}</option>
                    <option value="waiting_doctor_review" @selected($status === 'waiting_doctor_review')>{{ __('doctor.waiting_review') }}</option>
                    <option value="waiting_offer" @selected($status === 'waiting_offer')>{{ __('doctor.reviewed') }}</option>
                    <option value="offered" @selected($status === 'offered')>{{ __('doctor.offer_sent') }}</option>
                </select>
                <button class="admin-btn-secondary !py-2">Filter</button>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    @if($pendingCount > 0)
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-200">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">pending_actions</span>
            <div>
                <p class="font-semibold">{{ $pendingCount }} permintaan menunggu evaluasi medis Anda.</p>
                <p class="mt-0.5 text-xs leading-5">{{ __('doctor.review_helps_admin') }}</p>
            </div>
        </div>
    @endif

    <x-admin.panel title="{{ __('doctor.request_list') }}" :subtitle="$doctor->name" flush>
        @forelse($requests as $item)
            <a href="{{ route('doctor.service-requests.show', $item->id) }}"
               class="flex items-start gap-4 border-b border-slate-100 px-5 py-4 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $item->code }}</p>
                        <x-admin.badge :label="ServiceRequest::statusLabel($item->status)" :tone="ServiceRequest::statusTone($item->status)" dot />
                    </div>
                    <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">
                        {{ $item->pet->name ?? '—' }} · {{ $item->user->name ?? '—' }}
                        <span class="text-xs text-slate-400">· {{ $item->need_type_label }}</span>
                    </p>
                    <p class="mt-0.5 truncate text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($item->complaint, 120) }}</p>
                </div>
                <span class="material-symbols-outlined mt-1 text-slate-400" aria-hidden="true">chevron_right</span>
            </a>
        @empty
            <div class="px-5 py-10">
                <x-admin.empty-state
                    title="{{ __('doctor.no_requests') }}"
                    description="Admin klinik akan menugaskan permintaan layanan yang membutuhkan evaluasi medis."
                    icon="medical_information"
                    tone="slate" />
            </div>
        @endforelse
    </x-admin.panel>

    @if($requests->hasPages())
        <div>{{ $requests->links() }}</div>
    @endif
</div>
@endsection
