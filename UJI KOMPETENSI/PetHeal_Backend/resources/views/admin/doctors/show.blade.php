@extends('layouts.admin')

@section('title', __('admin.doctor_profile') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('admin.doctor_profile'))

@section('content')
@php
    $availableDays = is_array($doctor->available_days)
        ? $doctor->available_days
        : (json_decode($doctor->available_days, true) ?? []);
    $availableDayLabels = collect($availableDays)
        ->map(fn ($day) => __('admin.' . strtolower($day)))
        ->filter(fn ($label) => $label !== 'admin.' . strtolower($label))
        ->values();
@endphp

<div class="space-y-6">
    <x-admin.page-header
        :title="$doctor->name"
        :eyebrow="__('admin.eyebrow_management')"
        icon="medical_services"
        :description="__('admin.doctor_profile_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.doctors.schedule', $doctor->id) }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">event_busy</span>
                {{ __('admin.schedule_leave') }}
            </a>
            <a href="{{ route('admin.doctors.edit', $doctor->id) }}" class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                {{ __('admin.edit') }}
            </a>
            <a href="{{ route('admin.doctors.index') }}" class="admin-btn-ghost">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('admin.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Kartu identitas --}}
        <div class="space-y-6">
            <x-admin.panel>
                <div class="text-center">
                    @if($doctor->photo_url)
                        <img class="mx-auto h-32 w-32 rounded-full object-cover" src="{{ $doctor->photo_url }}?t={{ time() }}" alt="{{ $doctor->name }}">
                    @else
                        <span class="mx-auto flex h-32 w-32 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                            <span class="material-symbols-outlined text-5xl" aria-hidden="true">person</span>
                        </span>
                    @endif

                    <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ $doctor->name }}</h3>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $doctor->specialization }}</p>

                    <div class="mt-3 flex justify-center">
                        <x-admin.badge
                            :label="$doctor->is_active ? __('common.active') : __('common.inactive')"
                            :tone="$doctor->is_active ? 'primary' : 'slate'"
                            dot />
                    </div>

                    <div class="mt-5 flex items-center justify-center gap-2 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-900/20">
                        <span class="material-symbols-outlined text-[18px] text-amber-500" aria-hidden="true">star</span>
                        <span class="text-sm font-bold text-slate-900 dark:text-white">
                            {{ $doctor->reviews_count > 0 ? number_format((float) $doctor->reviews_avg_rating, 1) : '0.0' }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('admin.from_reviews', ['count' => $doctor->reviews_count]) }}
                        </span>
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel :title="__('admin.contact_information')">
                <div class="space-y-3">
                    @if($doctor->email)
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-900/20">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">email</span>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('common.email') }}</p>
                                <p class="truncate text-sm text-slate-700 dark:text-slate-300">{{ $doctor->email }}</p>
                            </div>
                        </div>
                    @endif

                    @if($doctor->phone)
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">phone</span>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('common.phone') }}</p>
                                <p class="truncate text-sm text-slate-700 dark:text-slate-300">{{ $doctor->phone }}</p>
                            </div>
                        </div>
                    @endif

                    @if(!$doctor->email && !$doctor->phone)
                        <p class="text-sm text-slate-400">{{ __('admin.email_empty') }}</p>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel title="Akun Portal Dokter" subtitle="Akses masuk ke /doctor">
                @if($doctor->user)
                    <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3 dark:border-emerald-900/60 dark:bg-emerald-900/20">
                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-white text-emerald-600 dark:bg-slate-900 dark:text-emerald-400">
                            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">verified_user</span>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-900 dark:text-white">Akun aktif</p>
                            <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $doctor->user->email }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                Dibuat {{ $doctor->user->created_at?->locale(app()->getLocale())->translatedFormat('d M Y') ?? '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-2">
                        <a href="{{ route('doctor.login') }}" class="admin-btn-secondary justify-center">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">open_in_new</span>
                            Buka halaman login dokter
                        </a>

                        <form method="POST" action="{{ route('admin.doctors.revoke-account', $doctor->id) }}"
                              onsubmit="return confirm('Cabut akses portal dokter ini? Profil praktik, jadwal, dan rekam medis tetap tersimpan, tetapi dokter tidak bisa masuk lagi sampai diundang ulang.')">
                            @csrf
                            <button type="submit" class="w-full rounded-xl border border-rose-200 px-3 py-2.5 text-xs font-bold text-rose-700 transition hover:bg-rose-50 dark:border-rose-900/60 dark:text-rose-300 dark:hover:bg-rose-900/20">
                                Cabut Akses Portal
                            </button>
                        </form>
                    </div>

                    <p class="mt-3 text-[11px] leading-5 text-slate-400">
                        Cabut akses hanya menghapus akun login. Riwayat medis dan jadwal dokter tetap utuh.
                    </p>
                @else
                    <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-white text-slate-400 dark:bg-slate-900">
                            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">person_off</span>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-200">Belum ada akun portal</p>
                            <p class="mt-0.5 text-[11px] leading-5 text-slate-500 dark:text-slate-400">
                                Dokter tetap tampil di aplikasi pet owner, tetapi belum bisa masuk ke portal.
                            </p>
                        </div>
                    </div>

                    @if($doctor->email)
                        @php($inviteActive = $doctor->invite_token && $doctor->invite_token_expires_at && $doctor->invite_token_expires_at->isFuture())

                        <form method="POST" action="{{ route('admin.doctors.invite', $doctor->id) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="admin-btn-primary w-full justify-center">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">forward_to_inbox</span>
                                {{ $inviteActive ? 'Kirim Ulang Undangan' : 'Kirim Undangan' }} ke {{ $doctor->email }}
                            </button>
                        </form>

                        @if($inviteActive)
                            <div class="mt-3 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50/60 px-3 py-2.5 text-[11px] leading-5 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-200">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">schedule</span>
                                <span>
                                    Tautan undangan aktif sampai
                                    <span class="font-bold">{{ $doctor->invite_token_expires_at->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}</span>.
                                    Kirim ulang untuk membuat tautan baru (tautan lama otomatis tidak berlaku).
                                </span>
                            </div>
                        @endif

                        <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Setelah undangan dikirim</p>
                            <ol class="mt-2 space-y-2 text-[11px] leading-5 text-slate-500 dark:text-slate-400">
                                <li class="flex gap-2">
                                    <span class="mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[9px] font-bold text-white">1</span>
                                    <span>Dokter membuka email <span class="font-semibold text-slate-600 dark:text-slate-300">"Aktivasi akun dokter"</span> dari PetHeal, lalu mengeklik tombol <span class="font-semibold text-slate-600 dark:text-slate-300">Buat Kata Sandi Portal Dokter</span>.</span>
                                </li>
                                <li class="flex gap-2">
                                    <span class="mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[9px] font-bold text-white">2</span>
                                    <span>Halaman aktivasi terbuka dengan email sudah terisi. Dokter langsung membuat kata sandi — <span class="font-semibold text-slate-600 dark:text-slate-300">tanpa kode OTP</span>.</span>
                                </li>
                                <li class="flex gap-2">
                                    <span class="mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[9px] font-bold text-white">3</span>
                                    <span>Akun aktif dan dokter bisa masuk lewat halaman login dokter. Tautan berlaku 60 menit dan hanya sekali pakai.</span>
                                </li>
                            </ol>
                        </div>
                    @else
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-[11px] leading-5 text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-200">
                            Isi email dokter terlebih dahulu lewat tombol
                            <a href="{{ route('admin.doctors.edit', $doctor->id) }}" class="font-bold underline">Edit</a>
                            sebelum mengirim undangan.
                        </div>
                    @endif
                @endif
            </x-admin.panel>

            <x-admin.panel :title="__('admin.schedule_tab')">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.available_days') }}</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                            {{ $availableDayLabels->isNotEmpty() ? $availableDayLabels->implode(', ') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('admin.working_hours') }}</dt>
                        <dd class="mt-1 text-sm tabular-nums text-slate-700 dark:text-slate-300">
                            {{ $doctor->start_time }} - {{ $doctor->end_time }}
                        </dd>
                    </div>
                </dl>
            </x-admin.panel>
        </div>

        {{-- Statistik, ulasan, booking --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <x-admin.stat-card :label="__('admin.total_bookings')" :value="$doctor->bookings_count" icon="event" tone="blue" />
                <x-admin.stat-card :label="__('admin.completed')" :value="$doctor->completed_bookings_count" icon="check_circle" tone="primary" />
                <x-admin.stat-card :label="__('admin.pending')" :value="$doctor->pending_bookings_count" icon="hourglass_top" tone="amber" />
                <x-admin.stat-card :label="__('admin.review_volume')" :value="$doctor->reviews_count" icon="reviews" tone="violet" />
            </div>

            <x-admin.panel :title="__('admin.recent_reviews')" :subtitle="__('admin.recent_reviews_desc')">
                <x-slot:actions>
                    <div class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">star</span>
                        {{ $doctor->reviews_count > 0 ? number_format((float) $doctor->reviews_avg_rating, 1) : '0.0' }}
                    </div>
                </x-slot:actions>

                @if($doctor->reviews->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($doctor->reviews as $review)
                            <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                            {{ $review->user->name ?? __('common.none') }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-400">
                                            {{ optional($review->booking)?->booking_date ? \Carbon\Carbon::parse($review->booking->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') : __('admin.completed_booking') }}
                                            @if(optional($review->booking)?->pet?->name)
                                                • {{ $review->booking->pet->name }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="inline-flex flex-shrink-0 items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">star</span>
                                        {{ $review->rating }}
                                    </div>
                                </div>
                                @if(!empty($review->review))
                                    <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $review->review }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-admin.empty-state
                        :title="__('admin.no_reviews')"
                        icon="reviews"
                        tone="amber" />
                @endif
            </x-admin.panel>

            <x-admin.panel :title="__('admin.recent_bookings')" flush>
                @if($doctor->bookings->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-left">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="px-5 py-3">{{ __('admin.col_patient') }}</th>
                                    <th class="px-5 py-3">{{ __('common.date') }}</th>
                                    <th class="px-5 py-3">{{ __('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                                @foreach($doctor->bookings->take(5) as $booking)
                                    <tr class="admin-table-row">
                                        <td class="px-5 py-3 text-sm text-slate-700 dark:text-slate-300">
                                            {{ $booking->pet->name ?? __('admin.pet_placeholder') }}
                                        </td>
                                        <td class="px-5 py-3 text-sm text-slate-500">
                                            {{ \Carbon\Carbon::parse($booking->booking_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <x-admin.badge
                                                :label="\App\Support\AdminStatus::bookingLabel($booking->status)"
                                                :tone="\App\Support\AdminStatus::bookingTone($booking->status)" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-admin.empty-state
                        :title="__('admin.no_visits')"
                        :description="__('admin.no_visits_desc')"
                        icon="event_busy"
                        tone="slate" />
                @endif
            </x-admin.panel>
        </div>
    </div>
</div>
@endsection
