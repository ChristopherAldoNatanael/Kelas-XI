@php
    $extraStatus = $record->extra_payment_status ?? 'not_required';
    $extraStatusClasses = match ($extraStatus) {
        'paid' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'partial' => 'border-sky-200 bg-sky-50 text-sky-700',
        'unpaid', 'pending' => 'border-amber-200 bg-amber-50 text-amber-700',
        'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-100 text-slate-600',
    };
    $currency = static fn ($amount) => 'Rp ' . number_format((float) $amount, 0, ',', '.');
    $owner = $record->booking->user;
    $bookingDate = \Carbon\Carbon::parse($record->booking->booking_date);
@endphp

@extends('layouts.admin')

@section('title', 'Medical Record Details - VCMS Admin')
@section('header', 'Medical Record Details')

@section('content')
<div class="space-y-6">
    <section class="glass-card rounded-[30px] border border-slate-200/60 overflow-hidden">
        <div class="relative overflow-hidden px-6 py-6 md:px-8 md:py-8">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_34%),radial-gradient(circle_at_right,_rgba(59,130,246,0.12),_transparent_28%)]"></div>
            <div class="relative flex flex-col gap-8 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200/70 bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-700">
                            <span class="material-symbols-outlined text-[15px]">clinical_notes</span>
                            Medical record
                        </div>
                        <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $extraStatusClasses }}">
                            <span class="material-symbols-outlined text-[14px]">payments</span>
                            {{ str_replace('_', ' ', $extraStatus) }}
                        </div>
                    </div>

                    <h2 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900 md:text-[2rem]">Record #{{ $record->id }} for {{ $record->pet->name }}</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">Clinical note, treatment plan, billing composition, and follow-up schedule are presented in one consolidated view for faster review.</p>

                    <div class="mt-6 grid gap-4 md:grid-cols-3">
                        <div class="rounded-3xl border border-white/70 bg-white/80 p-5 shadow-[0_16px_40px_-28px_rgba(15,23,42,0.45)]">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Total medical cost</p>
                            <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-900">{{ $currency($record->total_medical_cost ?? 0) }}</p>
                            <p class="mt-1 text-sm text-slate-500">Consultation, treatment, and medicine combined.</p>
                        </div>
                        <div class="rounded-3xl border border-white/70 bg-white/80 p-5 shadow-[0_16px_40px_-28px_rgba(15,23,42,0.45)]">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Extra payment required</p>
                            <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-900">{{ $currency($record->extra_payment_amount ?? 0) }}</p>
                            <p class="mt-1 text-sm text-slate-500">Remaining amount beyond the booking payment.</p>
                        </div>
                        <div class="rounded-3xl border border-white/70 bg-white/80 p-5 shadow-[0_16px_40px_-28px_rgba(15,23,42,0.45)]">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Recorded on</p>
                            <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-900">{{ $record->created_at->format('d M Y') }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $record->created_at->format('H:i') }} WIB</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 xl:justify-end">
                    <a href="{{ route('admin.medical-records.index') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-slate-200 bg-white/80 px-4 text-sm font-semibold text-slate-600 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        Back to records
                    </a>
                    <a href="{{ route('admin.medical-records.edit', $record->id) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-primary px-4 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-600">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                        Edit record
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[1.6fr_0.95fr]">
        <section class="space-y-6">
            <div class="glass-card rounded-[28px] border border-slate-200/60 overflow-hidden">
                <div class="border-b border-slate-200/70 px-6 py-5 md:px-8">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Clinical Summary</p>
                    <h3 class="mt-2 text-lg font-semibold tracking-tight text-slate-900">Diagnosis and treatment narrative</h3>
                </div>

                <div class="space-y-5 px-6 py-6 md:px-8">
                    <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-5 shadow-[0_18px_40px_-32px_rgba(15,23,42,0.45)]">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Diagnosis</p>
                        <p class="mt-3 text-sm leading-7 text-slate-700">{{ $record->diagnosis }}</p>
                    </article>

                    <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-5 shadow-[0_18px_40px_-32px_rgba(15,23,42,0.45)]">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Treatment</p>
                        <p class="mt-3 text-sm leading-7 text-slate-700">{{ $record->treatment }}</p>
                    </article>

                    @if($record->medicine)
                        <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-5 shadow-[0_18px_40px_-32px_rgba(15,23,42,0.45)]">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Medicine</p>
                            <p class="mt-3 text-sm leading-7 text-slate-700">{{ $record->medicine }}</p>
                        </article>
                    @endif

                    @if($record->notes)
                        <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-5 shadow-[0_18px_40px_-32px_rgba(15,23,42,0.45)]">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Additional notes</p>
                            <p class="mt-3 text-sm leading-7 text-slate-700">{{ $record->notes }}</p>
                        </article>
                    @endif
                </div>
            </div>

            <div class="glass-card rounded-[28px] border border-slate-200/60 overflow-hidden">
                <div class="border-b border-slate-200/70 px-6 py-5 md:px-8">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Billing Breakdown</p>
                    <h3 class="mt-2 text-lg font-semibold tracking-tight text-slate-900">Cost composition and payment readiness</h3>
                </div>

                <div class="px-6 py-6 md:px-8">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-3xl border border-slate-200/70 bg-white/80 p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Consultation</p>
                            <p class="mt-3 text-xl font-semibold text-slate-900">{{ $currency($record->cost ?? 0) }}</p>
                        </div>
                        <div class="rounded-3xl border border-slate-200/70 bg-white/80 p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Treatment</p>
                            <p class="mt-3 text-xl font-semibold text-slate-900">{{ $currency($record->treatment_cost ?? 0) }}</p>
                        </div>
                        <div class="rounded-3xl border border-slate-200/70 bg-white/80 p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Medicine</p>
                            <p class="mt-3 text-xl font-semibold text-slate-900">{{ $currency($record->medicine_cost ?? 0) }}</p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-[28px] border border-emerald-200/70 bg-gradient-to-br from-emerald-50 via-white to-sky-50 p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-700">Financial result</p>
                                <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ $currency($record->total_medical_cost ?? 0) }}</p>
                                <p class="mt-2 text-sm text-slate-500">This is the complete amount recorded by the clinic for the consultation cycle.</p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-white/80 bg-white/80 px-4 py-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Extra payment</p>
                                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $currency($record->extra_payment_amount ?? 0) }}</p>
                                </div>
                                <div class="rounded-2xl border border-white/80 bg-white/80 px-4 py-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Extra paid</p>
                                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $currency($record->extra_payment_paid_amount ?? 0) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <div class="glass-card rounded-[28px] border border-slate-200/60 overflow-hidden">
                <div class="border-b border-slate-200/70 px-6 py-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Case Participants</p>
                    <h3 class="mt-2 text-lg font-semibold tracking-tight text-slate-900">People and pet involved</h3>
                </div>

                <div class="space-y-4 px-6 py-6">
                    <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-4">
                        <div class="flex items-center gap-3">
                            @if($record->pet->photo)
                                <img class="h-14 w-14 rounded-2xl object-cover ring-1 ring-slate-200"
                                     src="{{ asset('storage/' . $record->pet->photo) }}"
                                     alt="{{ $record->pet->name }}">
                            @else
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                                    <span class="material-symbols-outlined text-[24px]">pets</span>
                                </div>
                            @endif
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Pet</p>
                                <p class="mt-1 text-base font-semibold text-slate-900">{{ $record->pet->name }}</p>
                                <p class="text-sm text-slate-500">{{ $record->pet->species ?? 'Pet' }}{{ $record->pet->breed ? ' · ' . $record->pet->breed : '' }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                                <span class="material-symbols-outlined text-[24px]">person</span>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Owner</p>
                                <p class="mt-1 text-base font-semibold text-slate-900">{{ $owner->name ?? 'Unknown owner' }}</p>
                                <p class="text-sm text-slate-500">{{ $owner->email ?? '-' }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-4">
                        <div class="flex items-center gap-3">
                            @if($record->doctor->photo)
                                <img class="h-14 w-14 rounded-2xl object-cover ring-1 ring-slate-200"
                                     src="{{ asset('storage/' . $record->doctor->photo) }}"
                                     alt="{{ $record->doctor->name }}">
                            @else
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-100 text-violet-700">
                                    <span class="material-symbols-outlined text-[24px]">stethoscope</span>
                                </div>
                            @endif
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Doctor</p>
                                <p class="mt-1 text-base font-semibold text-slate-900">{{ $record->doctor->name }}</p>
                                <p class="text-sm text-slate-500">{{ $record->doctor->specialization ?? 'Veterinarian' }}</p>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div class="glass-card rounded-[28px] border border-slate-200/60 overflow-hidden">
                <div class="border-b border-slate-200/70 px-6 py-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Visit Timeline</p>
                    <h3 class="mt-2 text-lg font-semibold tracking-tight text-slate-900">Appointment and follow-up</h3>
                </div>

                <div class="space-y-4 px-6 py-6">
                    <div class="rounded-3xl border border-slate-200/70 bg-white/80 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Booking date</p>
                        <p class="mt-2 text-base font-semibold text-slate-900">{{ $bookingDate->format('d M Y') }}</p>
                        <p class="text-sm text-slate-500">at {{ \Carbon\Carbon::parse($record->booking->booking_time)->format('H:i') }} WIB</p>
                    </div>

                    @if($record->next_visit_date)
                        <div class="rounded-3xl border border-amber-200/70 bg-amber-50/80 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">Next visit</p>
                            <p class="mt-2 text-base font-semibold text-slate-900">{{ \Carbon\Carbon::parse($record->next_visit_date)->format('d M Y') }}</p>
                            <p class="text-sm text-amber-700/80">
                                @if($record->next_visit_time)
                                    {{ \Carbon\Carbon::parse($record->next_visit_time)->format('H:i') }} WIB
                                @else
                                    Schedule pending exact time
                                @endif
                            </p>
                        </div>
                    @endif

                    <div class="rounded-3xl border border-slate-200/70 bg-white/80 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Audit trail</p>
                        <p class="mt-2 text-sm text-slate-600">Created {{ $record->created_at->format('d M Y, H:i') }}</p>
                        <p class="mt-1 text-sm text-slate-600">Updated {{ $record->updated_at->format('d M Y, H:i') }}</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
