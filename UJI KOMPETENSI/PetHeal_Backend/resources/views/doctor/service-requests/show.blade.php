@extends('layouts.admin')

@section('title', $request->code . ' — Evaluasi Medis')
@section('header', 'Evaluasi Permintaan Layanan')

@php
    use App\Models\ServiceRequest;
    $canEvaluate = $request->status === ServiceRequest::STATUS_WAITING_DOCTOR;
@endphp

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="$request->code"
        eyebrow="{{ __('doctor.portal_eyebrow') }}"
        icon="medical_information"
        :description="ServiceRequest::statusLabel($request->status) . ' · ' . $request->need_type_label">
        <x-slot:actions>
            <a href="{{ route('doctor.service-requests.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Daftar Permintaan
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @foreach(['success' => 'emerald', 'error' => 'rose'] as $flash => $tone)
        @if(session($flash))
            <div class="flex items-center gap-3 rounded-2xl border border-{{ $tone }}-200 bg-{{ $tone }}-50 px-5 py-3.5 text-sm font-medium text-{{ $tone }}-700 dark:border-{{ $tone }}-900/60 dark:bg-{{ $tone }}-900/20">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $flash === 'success' ? 'check_circle' : 'error' }}</span>
                <span>{{ session($flash) }}</span>
            </div>
        @endif
    @endforeach

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.3fr_1fr]">
        <div class="space-y-6">
            <x-admin.panel title="{{ __('doctor.owner_complaint') }}" :subtitle="($request->pet->name ?? '—') . ' · ' . ($request->pet->species ?? '')">
                <p class="whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $request->complaint }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <dt class="font-bold uppercase tracking-wider text-slate-400">Pemilik</dt>
                        <dd class="text-slate-700 dark:text-slate-300">{{ $request->user->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-bold uppercase tracking-wider text-slate-400">{{ __('doctor.schedule_preference') }}</dt>
                        <dd class="text-slate-700 dark:text-slate-300">
                            {{ $request->preferred_date ?? 'Fleksibel' }}
                            {{ $request->preferred_time ? '· ' . $request->preferred_time : '' }}
                        </dd>
                    </div>
                </dl>
            </x-admin.panel>

            @if($request->medicalRecord)
                <x-admin.panel title="{{ __('doctor.referral_records') }}" flush>
                    <a href="{{ route('doctor.medical-records.show', $request->medicalRecord->id) }}"
                       class="flex items-center justify-between px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                Rekam medis #{{ $request->medicalRecord->id }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ format_tanggal($request->medicalRecord->created_at) }}
                                · {{ $request->medicalRecord->diagnosis }}
                            </p>
                        </div>
                        <span class="material-symbols-outlined text-slate-400" aria-hidden="true">chevron_right</span>
                    </a>
                </x-admin.panel>
            @endif

            <x-admin.panel title="{{ __('doctor.exam_history') }}" subtitle="{{ __('doctor.exam_history_hint') }}" flush>
                @forelse($history as $record)
                    <a href="{{ route('doctor.medical-records.show', $record->id) }}"
                       class="flex items-center justify-between border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-slate-700 dark:text-slate-300">{{ $record->diagnosis }}</p>
                            <p class="text-[11px] text-slate-400">
                                {{ format_tanggal($record->created_at) }} · {{ $record->doctor->name ?? '—' }}
                            </p>
                        </div>
                        <span class="material-symbols-outlined text-slate-400" aria-hidden="true">chevron_right</span>
                    </a>
                @empty
                    <div class="px-5 py-6">
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('doctor.no_exam_history') }}</p>
                    </div>
                @endforelse
            </x-admin.panel>

            @if($request->admin_notes)
                <x-admin.panel title="{{ __('doctor.admin_notes') }}" flush>
                    <p class="whitespace-pre-line px-5 py-4 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $request->admin_notes }}</p>
                </x-admin.panel>
            @endif
        </div>

        <div class="space-y-6">
            @if($canEvaluate)
                <x-admin.panel title="{{ __('doctor.medical_recommendation') }}" subtitle="{{ __('doctor.medical_recommendation_hint') }}">
                    <form method="POST" action="{{ route('doctor.service-requests.recommend', $request->id) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('doctor.service_recommendation') }}</label>
                            <textarea name="doctor_recommendation" rows="4" class="admin-input" required
                                      placeholder="{{ __('doctor.service_recommendation_placeholder') }}">{{ old('doctor_recommendation', $request->doctor_recommendation) }}</textarea>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('doctor.extra_medical_notes') }}</label>
                            <textarea name="doctor_notes" rows="3" class="admin-input" placeholder="{{ __('doctor.extra_medical_notes_placeholder') }}">{{ old('doctor_notes', $request->doctor_notes) }}</textarea>
                        </div>
                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                            <input type="checkbox" name="needs_follow_up" value="1" @checked($request->needs_follow_up) class="rounded border-slate-300">
                            Kontrol lanjutan diperlukan
                        </label>
                        <button class="admin-btn-primary w-full">{{ __('doctor.save_review') }}</button>
                    </form>
                </x-admin.panel>
            @else
                <x-admin.panel title="{{ __('doctor.review_sent') }}" flush>
                    <div class="space-y-2 px-5 py-4">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $request->doctor_recommendation }}</p>
                        @if($request->doctor_notes)
                            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $request->doctor_notes }}</p>
                        @endif
                        <p class="text-xs text-slate-400">Status: {{ ServiceRequest::statusLabel($request->status) }}</p>
                    </div>
                </x-admin.panel>
            @endif

            @if($request->offered_at)
                <x-admin.panel title="{{ __('doctor.clinic_offer') }}" flush>
                    <div class="space-y-1 px-5 py-4 text-sm text-slate-700 dark:text-slate-300">
                        <p class="font-semibold">{{ $request->offer_service_name }}</p>
                        <p>{{ format_rupiah((float) $request->offer_price) }}
                            <span class="text-[10px] font-bold uppercase text-slate-400">{{ $request->offer_price_type }}</span>
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $request->offerDoctor->name ?? '—' }} ·
                            {{ $request->offer_schedule_date }} {{ $request->offer_schedule_time }}
                        </p>
                    </div>
                </x-admin.panel>
            @endif
        </div>
    </div>
</div>
@endsection
