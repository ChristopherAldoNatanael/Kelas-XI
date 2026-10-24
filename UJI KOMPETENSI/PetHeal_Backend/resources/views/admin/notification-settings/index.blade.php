@extends('layouts.admin')

@section('title', __('admin.notifications_title') . ' — PetHeal Admin')
@section('header', __('admin.notifications_title'))

@section('content')
<div class="space-y-6">

    {{-- Header halaman --}}
    <x-admin.page-header
        :title="__('admin.notifications_title')"
        :eyebrow="__('admin.eyebrow_system')"
        icon="notifications_active"
        :description="__('admin.notifications_desc')">
        <x-slot:actions>
            <x-admin.badge :label="__('admin.notifications_badge')" tone="primary" />
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Placeholder yang didukung --}}
    <x-admin.panel :title="__('admin.placeholders_title')" :subtitle="__('admin.notification_placeholders_desc')">
        <div class="flex flex-wrap gap-2">
            @foreach(['{pet_name}', '{doctor_name}', '{date}', '{time}', '{next_visit}', '{remaining_amount}', '{status}'] as $token)
                <span class="rounded-full bg-slate-100 px-3 py-1 font-mono text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ $token }}
                </span>
            @endforeach
        </div>
    </x-admin.panel>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <p class="font-semibold">{{ __('admin.templates_failed') }}</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.notification-settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @foreach($templates as $template)
                @php
                    $accentMap = [
                        'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
                        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
                        'sky' => 'bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400',
                        'blue' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
                        'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400',
                        'slate' => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300',
                        'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400',
                    ];
                    $accentClass = $accentMap[$template['accent']] ?? $accentMap['slate'];
                @endphp

                <x-admin.panel :title="$template['label']" :subtitle="$template['description']" flush>
                    <x-slot:actions>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $accentClass }}">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $template['icon'] }}</span>
                        </span>
                    </x-slot:actions>

                    <div class="space-y-4 p-5">
                        {{-- Pratinjau pada perangkat user --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                            <p class="mb-3 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">
                                {{ __('admin.preview_label') }}
                            </p>

                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-slate-900 text-white dark:bg-white dark:text-slate-900">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">pets</span>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ old("templates.{$template['key']}.title", $template['stored_title']) }}
                                    </p>
                                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                        {{ old("templates.{$template['key']}.body", $template['stored_body']) }}
                                    </p>
                                </div>
                            </div>

                            <p class="mt-3 text-[11px] text-slate-400">{{ __('admin.notification_preview_desc') }}</p>
                        </div>

                        <div>
                            <label for="template-{{ $template['key'] }}-title" class="admin-label">
                                {{ __('admin.notification_title_label') }}
                            </label>
                            <input
                                id="template-{{ $template['key'] }}-title"
                                type="text"
                                name="templates[{{ $template['key'] }}][title]"
                                value="{{ old("templates.{$template['key']}.title", $template['stored_title']) }}"
                                class="admin-input @error("templates.{$template['key']}.title") admin-input-error @enderror"
                            >
                            @error("templates.{$template['key']}.title")
                                <p class="admin-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="template-{{ $template['key'] }}-body" class="admin-label">
                                {{ __('admin.notification_body_label') }}
                            </label>
                            <textarea
                                id="template-{{ $template['key'] }}-body"
                                name="templates[{{ $template['key'] }}][body]"
                                rows="4"
                                class="admin-input @error("templates.{$template['key']}.body") admin-input-error @enderror"
                            >{{ old("templates.{$template['key']}.body", $template['stored_body']) }}</textarea>
                            @error("templates.{$template['key']}.body")
                                <p class="admin-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </x-admin.panel>
            @endforeach
        </div>

        {{-- Bilah simpan --}}
        <div class="sticky bottom-4 z-20 flex justify-end">
            <div class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
                <p class="hidden text-xs text-slate-500 md:block dark:text-slate-400">{{ __('admin.notification_save_hint') }}</p>
                <button type="submit" class="admin-btn-primary">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                    {{ __('admin.notification_save') }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
