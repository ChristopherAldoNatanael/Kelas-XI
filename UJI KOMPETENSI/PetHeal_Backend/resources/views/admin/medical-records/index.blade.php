@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $records */
$totalRecords = $records->total();
$pageItems = $records->count();
$extraPendingCount = $records->getCollection()->filter(fn ($record) => in_array($record->extra_payment_status, ['unpaid', 'pending', 'partial'], true))->count();
$totalMedicalRevenue = $records->getCollection()->sum(fn ($record) => (float) ($record->total_medical_cost ?? (($record->cost ?? 0) + ($record->treatment_cost ?? 0) + ($record->medicine_cost ?? 0))));
$currency = static fn ($amount) => 'Rp ' . number_format((float) $amount, 0, ',', '.');
@endphp

@extends('layouts.admin')

@section('title', 'Medical Records - PetHeal Admin')
@section('header', 'Medical Records Management')

@section('content')
<div class="space-y-6">
    <section class="glass-card rounded-[28px] border border-slate-200/60 overflow-hidden">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_34%),radial-gradient(circle_at_right,_rgba(14,165,233,0.12),_transparent_28%)]"></div>
            <div class="relative px-6 py-6 md:px-8 md:py-8">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200/70 bg-white/70 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-700">
                            <span class="material-symbols-outlined text-[15px]">clinical_notes</span>
                            Clinical Documentation
                        </div>
                        <h2 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900 md:text-[2rem]">Medical records with billing context that is easy to scan.</h2>
                        <p class="mt-3 max-w-xl text-sm leading-6 text-slate-500">Monitor diagnosis, treatment cost, follow-up schedule, and extra payment status from a single workspace without losing operational clarity.</p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('admin.medical-records.export.pdf', request()->query()) }}" target="_blank" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-red-200 bg-white/80 px-4 text-sm font-semibold text-red-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-red-50">
                            <span class="material-symbols-outlined text-[18px]">picture_as_pdf</span>
                            Export PDF
                        </a>
                        <a href="{{ route('admin.medical-records.export.csv', request()->query()) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-emerald-200 bg-white/80 px-4 text-sm font-semibold text-emerald-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-50">
                            <span class="material-symbols-outlined text-[18px]">download</span>
                            Export CSV
                        </a>
                        <button type="button" onclick="window.print()" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-slate-200 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-800">
                            <span class="material-symbols-outlined text-[18px]">print</span>
                            Print View
                        </button>
                    </div>
                </div>

                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <div class="rounded-3xl border border-white/70 bg-white/75 p-5 shadow-[0_12px_30px_-20px_rgba(15,23,42,0.45)] backdrop-blur">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Records in system</p>
                        <div class="mt-3 flex items-end justify-between">
                            <div>
                                <p class="text-3xl font-semibold tracking-tight text-slate-900">{{ number_format($totalRecords) }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ number_format($pageItems) }} shown on this page</p>
                            </div>
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                                <span class="material-symbols-outlined text-[24px]">folder_shared</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/70 bg-white/75 p-5 shadow-[0_12px_30px_-20px_rgba(15,23,42,0.45)] backdrop-blur">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Extra payment attention</p>
                        <div class="mt-3 flex items-end justify-between">
                            <div>
                                <p class="text-3xl font-semibold tracking-tight text-slate-900">{{ number_format($extraPendingCount) }}</p>
                                <p class="mt-1 text-sm text-slate-500">Pending, unpaid, or partial on this page</p>
                            </div>
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                                <span class="material-symbols-outlined text-[24px]">payments</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/70 bg-white/75 p-5 shadow-[0_12px_30px_-20px_rgba(15,23,42,0.45)] backdrop-blur">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Visible medical revenue</p>
                        <div class="mt-3 flex items-end justify-between">
                            <div>
                                <p class="text-3xl font-semibold tracking-tight text-slate-900">{{ $currency($totalMedicalRevenue) }}</p>
                                <p class="mt-1 text-sm text-slate-500">Accumulated from current result set</p>
                            </div>
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                                <span class="material-symbols-outlined text-[24px]">monitoring</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 rounded-3xl border border-emerald-100/80 bg-white/80 px-5 py-4 text-sm leading-6 text-slate-600 shadow-[0_12px_30px_-24px_rgba(15,23,42,0.35)]">
                    Export mengikuti filter aktif pada workspace ini. Untuk dataset besar, tentukan rentang tanggal dan status pembayaran tambahan lebih dulu agar file tetap cepat diproses dan lebih relevan untuk operasional.
                </div>
            </div>
        </div>
    </section>

    <section class="glass-card rounded-[28px] border border-slate-200/60 overflow-hidden">
        <form method="GET" action="{{ route('admin.medical-records.index') }}" class="border-b border-slate-200/70 px-6 py-6 md:px-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">Filter Workspace</p>
                    <h3 class="mt-2 text-lg font-semibold tracking-tight text-slate-900">Refine records by date and payment state</h3>
                    <p class="mt-1 text-sm text-slate-500">Keep the queue clean before export or follow-up.</p>
                </div>

                <div class="grid flex-1 gap-4 md:grid-cols-2 xl:max-w-3xl xl:grid-cols-4">
                    <div>
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">From date</label>
                        <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-2xl border border-slate-200 bg-white/80 px-4 py-3 text-sm text-slate-700 shadow-sm transition focus:border-emerald-400 focus:ring-emerald-200">
                    </div>
                    <div>
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">To date</label>
                        <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-2xl border border-slate-200 bg-white/80 px-4 py-3 text-sm text-slate-700 shadow-sm transition focus:border-emerald-400 focus:ring-emerald-200">
                    </div>
                    <div>
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Extra payment</label>
                        <select name="payment_status" class="w-full rounded-2xl border border-slate-200 bg-white/80 px-4 py-3 text-sm text-slate-700 shadow-sm transition focus:border-emerald-400 focus:ring-emerald-200">
                            <option value="">All statuses</option>
                            @foreach(['not_required', 'unpaid', 'pending', 'partial', 'paid', 'failed'] as $status)
                                <option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <button type="submit" class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                            <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                            Apply
                        </button>
                        <a href="{{ route('admin.medical-records.index') }}" class="inline-flex h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white/80 px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                            Reset
                        </a>
                    </div>
                </div>
            </div>
        </form>

        <div class="px-6 py-5 md:px-8">
            <div class="overflow-hidden rounded-[26px] border border-slate-200/70 bg-white/80 shadow-[0_18px_50px_-35px_rgba(15,23,42,0.45)]">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Record Register</h3>
                        <p class="mt-1 text-xs text-slate-500">Detailed treatment and billing history for each completed booking.</p>
                    </div>
                    <div class="hidden items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 md:inline-flex">
                        <span class="material-symbols-outlined text-[14px]">database</span>
                        {{ number_format($pageItems) }} rows
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-slate-50/90">
                            <tr>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Patient</th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Doctor</th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Clinical summary</th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Financials</th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Timeline</th>
                                <th class="px-5 py-4 text-right text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($records as $record)
                                @php
                                    $totalCost = (float) ($record->total_medical_cost ?? (($record->cost ?? 0) + ($record->treatment_cost ?? 0) + ($record->medicine_cost ?? 0)));
                                    $extraStatus = $record->extra_payment_status ?? 'not_required';
                                    $extraStatusClasses = match ($extraStatus) {
                                        'paid' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                        'partial' => 'border-sky-200 bg-sky-50 text-sky-700',
                                        'unpaid', 'pending' => 'border-amber-200 bg-amber-50 text-amber-700',
                                        'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                        default => 'border-slate-200 bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <tr class="align-top transition hover:bg-slate-50/70">
                                    <td class="px-5 py-5">
                                        <div class="flex items-start gap-3">
                                            @if($record->pet->photo)
                                                <img class="h-11 w-11 rounded-2xl object-cover ring-1 ring-slate-200"
                                                     src="{{ asset('storage/' . $record->pet->photo) }}"
                                                     alt="{{ $record->pet->name }}">
                                            @else
                                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200/80">
                                                    <span class="material-symbols-outlined text-[20px]">pets</span>
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-900">{{ $record->pet->name }}</p>
                                                <p class="mt-0.5 text-sm text-slate-500">{{ $record->booking->user->name ?? 'Unknown owner' }}</p>
                                                <div class="mt-2 inline-flex items-center gap-2 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-500">
                                                    <span class="material-symbols-outlined text-[13px]">tag</span>
                                                    Booking #{{ $record->booking_id }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-5">
                                        <p class="text-sm font-semibold text-slate-800">{{ $record->doctor->name }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $record->doctor->specialization ?? 'Veterinarian' }}</p>
                                    </td>
                                    <td class="px-5 py-5">
                                        <p class="text-sm font-semibold leading-6 text-slate-800">{{ $record->diagnosis }}</p>
                                        <p class="mt-2 max-w-md text-sm leading-6 text-slate-500">{{ \Illuminate\Support\Str::limit($record->treatment, 110) }}</p>
                                        @if($record->next_visit_date)
                                            <div class="mt-3 inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-700">
                                                <span class="material-symbols-outlined text-[14px]">event_upcoming</span>
                                                Follow-up {{ \Carbon\Carbon::parse($record->next_visit_date)->format('d M Y') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-5">
                                        <p class="text-sm font-semibold text-slate-900">{{ $currency($totalCost) }}</p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-400">Total medical cost</p>
                                        <div class="mt-3 space-y-2">
                                            <div class="flex items-center justify-between gap-4 text-sm">
                                                <span class="text-slate-500">Extra payment</span>
                                                <span class="font-medium text-slate-700">{{ $currency($record->extra_payment_amount ?? 0) }}</span>
                                            </div>
                                            <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $extraStatusClasses }}">
                                                <span class="material-symbols-outlined text-[14px]">payments</span>
                                                {{ str_replace('_', ' ', $extraStatus) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-5">
                                        <p class="text-sm font-semibold text-slate-800">{{ $record->created_at->format('d M Y') }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $record->created_at->format('H:i') }}</p>
                                        @if($record->updated_at->ne($record->created_at))
                                            <p class="mt-3 text-xs uppercase tracking-[0.16em] text-slate-400">Updated {{ $record->updated_at->diffForHumans() }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-5">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.medical-records.show', $record->id) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-emerald-200 hover:text-emerald-600" title="View details">
                                                <span class="material-symbols-outlined text-[19px]">visibility</span>
                                            </a>
                                            <a href="{{ route('admin.medical-records.edit', $record->id) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-sky-200 hover:text-sky-600" title="Edit record">
                                                <span class="material-symbols-outlined text-[19px]">edit</span>
                                            </a>
                                            <form method="POST" action="{{ route('admin.medical-records.destroy', $record->id) }}" class="inline" onsubmit="return confirm('Delete this medical record? This action cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-rose-200 hover:text-rose-600" title="Delete record">
                                                    <span class="material-symbols-outlined text-[19px]">delete</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-16">
                                        <div class="mx-auto flex max-w-md flex-col items-center text-center">
                                            <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-slate-100 text-slate-400">
                                                <span class="material-symbols-outlined text-[30px]">clinical_notes</span>
                                            </div>
                                            <h3 class="mt-5 text-lg font-semibold tracking-tight text-slate-900">Belum ada rekam medis yang sesuai</h3>
                                            <p class="mt-2 text-sm leading-6 text-slate-500">Sesuaikan filter tanggal atau status pembayaran tambahan, atau buat rekam medis baru dari booking yang sudah selesai.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-200/70 px-6 py-5 md:px-8">
            {{ $records->links() }}
        </div>
    </section>
</div>
@endsection
