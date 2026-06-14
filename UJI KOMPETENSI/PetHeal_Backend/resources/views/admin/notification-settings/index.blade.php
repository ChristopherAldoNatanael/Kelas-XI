@extends('layouts.admin')

@section('title', 'Notification Settings - PetHeal Admin')
@section('header', 'Notification Settings')

@section('content')
<div class="space-y-6">
    <section class="glass-card rounded-[28px] border border-slate-200/60 overflow-hidden">
        <div class="relative overflow-hidden px-6 py-7 md:px-8">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_30%),radial-gradient(circle_at_right,_rgba(59,130,246,0.14),_transparent_32%)]"></div>
            <div class="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200/70 bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-700">
                        <span class="material-symbols-outlined text-[15px]">notifications_active</span>
                        Push Template Studio
                    </div>
                    <h2 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900 md:text-[2rem]">Atur kata-kata notifikasi yang muncul di HP user tanpa menyentuh kode.</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">Setiap template di bawah akan dipakai sebagai default saat admin mengirim reminder, konfirmasi booking, pembatalan, atau pengingat vaksinasi. Placeholder seperti <span class="font-semibold text-slate-700">{pet_name}</span> akan diganti otomatis saat notifikasi dikirim.</p>
                </div>
                <div class="rounded-3xl border border-white/70 bg-white/85 p-4 shadow-[0_14px_35px_-22px_rgba(15,23,42,0.38)]">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Placeholder yang didukung</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach(['{pet_name}','{doctor_name}','{date}','{time}','{next_visit}','{remaining_amount}','{status}'] as $token)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $token }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700 shadow-sm">
            <p class="font-semibold">Notification template update failed.</p>
            <ul class="mt-2 space-y-1 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.notification-settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="grid gap-5 xl:grid-cols-2">
            @foreach($templates as $template)
                @php
                    $accentMap = [
                        'emerald' => 'from-emerald-500/15 to-emerald-50 text-emerald-700 border-emerald-200',
                        'amber' => 'from-amber-500/15 to-amber-50 text-amber-700 border-amber-200',
                        'sky' => 'from-sky-500/15 to-sky-50 text-sky-700 border-sky-200',
                        'blue' => 'from-blue-500/15 to-blue-50 text-blue-700 border-blue-200',
                        'rose' => 'from-rose-500/15 to-rose-50 text-rose-700 border-rose-200',
                        'slate' => 'from-slate-500/15 to-slate-50 text-slate-700 border-slate-200',
                        'violet' => 'from-violet-500/15 to-violet-50 text-violet-700 border-violet-200',
                    ];
                    $accentClass = $accentMap[$template['accent']] ?? $accentMap['slate'];
                @endphp
                <article class="glass-card rounded-[26px] border border-slate-200/60 overflow-hidden">
                    <div class="border-b border-slate-200/70 px-5 py-5">
                        <div class="flex items-start gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl border bg-gradient-to-br {{ $accentClass }}">
                                <span class="material-symbols-outlined text-[24px]">{{ $template['icon'] }}</span>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Notification Preview</p>
                                <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ $template['label'] }}</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-500">{{ $template['description'] }}</p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-3xl border border-slate-200 bg-white p-4 shadow-[0_18px_45px_-35px_rgba(15,23,42,0.45)]">
                            <div class="flex items-start gap-3">
                                <div class="mt-1 flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-900 text-white">
                                    <span class="material-symbols-outlined text-[18px]">pets</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">{{ old("templates.{$template['key']}.title", $template['stored_title']) }}</p>
                                    <p class="mt-1 text-sm leading-6 text-slate-500">{{ old("templates.{$template['key']}.body", $template['stored_body']) }}</p>
                                    <p class="mt-3 text-[11px] uppercase tracking-[0.18em] text-slate-400">Default preview on user device</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 px-5 py-5">
                        <div>
                            <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Title</label>
                            <input
                                type="text"
                                name="templates[{{ $template['key'] }}][title]"
                                value="{{ old("templates.{$template['key']}.title", $template['stored_title']) }}"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm transition focus:border-emerald-400 focus:ring-emerald-200"
                            >
                        </div>
                        <div>
                            <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Body</label>
                            <textarea
                                name="templates[{{ $template['key'] }}][body]"
                                rows="4"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm transition focus:border-emerald-400 focus:ring-emerald-200"
                            >{{ old("templates.{$template['key']}.body", $template['stored_body']) }}</textarea>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <div class="sticky bottom-4 z-20 flex justify-end">
            <div class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur">
                <p class="hidden text-sm text-slate-500 md:block">Simpan agar template baru dipakai untuk reminder berikutnya.</p>
                <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-primary px-5 text-sm font-semibold text-white transition hover:bg-emerald-600">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Save Notification Settings
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
