@extends('layouts.admin')

@section('title', 'Profil Saya — ' . ($doctor->clinic?->name ?? 'PetHeal'))
@section('header', 'Profil Saya')

@php
    $dayLabels = [
        'monday' => 'Senin', 'tuesday' => 'Selasa', 'wednesday' => 'Rabu',
        'thursday' => 'Kamis', 'friday' => 'Jumat', 'saturday' => 'Sabtu', 'sunday' => 'Minggu',
    ];
    $days = collect($doctor->available_days ?? [])
        ->map(fn ($day) => $dayLabels[strtolower((string) $day)] ?? ucfirst((string) $day))
        ->filter()
        ->values();
    $phoneDisplay = preg_match('/^08\d{3,}$/', (string) $doctor->phone)
        ? substr($doctor->phone, 0, 4) . ' •••• ' . substr($doctor->phone, -3)
        : $doctor->phone;
@endphp

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        title="Profil Saya"
        eyebrow="Akun"
        icon="manage_accounts"
        description="Perbarui nama, nomor telepon, dan foto profil. Email serta jadwal mingguan dikelola admin klinik agar data praktik tetap konsisten.">
        <x-slot:actions>
            <a href="{{ route('doctor.dashboard') }}" class="admin-btn-ghost">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Kembali ke Ringkasan
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_1.15fr]">
        <div class="space-y-6">
            <x-admin.panel title="Identitas praktik" :subtitle="($doctor->clinic->name ?? null) ? ('Tampil sebagai dokter di ' . $doctor->clinic->name) : null">
                <div class="flex items-center gap-4">
                    @if($doctor->photo_url)
                        <img id="avatarPreview" src="{{ $doctor->photo_url }}?t={{ time() }}" alt="{{ $doctor->name }}"
                             class="h-20 w-20 flex-shrink-0 rounded-2xl object-cover ring-2 ring-slate-100 dark:ring-slate-700">
                    @else
                        <span id="avatarPreview" class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 ring-2 ring-slate-100 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-slate-700">
                            <span class="material-symbols-outlined text-[34px]" aria-hidden="true">stethoscope</span>
                        </span>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-lg font-bold text-slate-900 dark:text-white">{{ $doctor->name }}</p>
                        <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $doctor->specialization ?: 'Dokter hewan' }}</p>
                        <span class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <span class="material-symbols-outlined text-[12px]" aria-hidden="true">verified</span>
                            Akun portal aktif
                        </span>
                    </div>
                </div>

                <dl class="mt-6 divide-y divide-slate-100 border-t border-slate-100 text-sm dark:divide-slate-800 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">call</span>
                            Telepon
                        </dt>
                        <dd class="text-right font-semibold text-slate-800 dark:text-slate-200">
                            {{ $phoneDisplay ?: 'Belum diisi' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">calendar_month</span>
                            Hari praktik
                        </dt>
                        <dd class="text-right">
                            @if($days->isNotEmpty())
                                <div class="flex flex-wrap justify-end gap-1">
                                    @foreach($days as $day)
                                        <span class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">{{ $day }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">schedule</span>
                            Jam praktik
                        </dt>
                        <dd class="text-right font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                            {{ substr((string) $doctor->start_time, 0, 5) }} – {{ substr((string) $doctor->end_time, 0, 5) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">history</span>
                            Akun dibuat
                        </dt>
                        <dd class="text-right font-semibold text-slate-800 dark:text-slate-200">
                            {{ optional($user->created_at)->locale(app()->getLocale())->translatedFormat('d M Y') ?? '—' }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 flex items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] leading-5 text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">lock</span>
                    <p>Email, spesialisasi, dan jadwal mingguan hanya dapat diubah admin klinik. Hubungi admin bila ada yang perlu diperbarui.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel title="Ganti kata sandi" :subtitle="($user->email ?? null) ? ('Untuk akun ' . $user->email) : null">
                <x-slot:actions>
                    <x-admin.badge label="Verifikasi email" tone="sky" />
                </x-slot:actions>

                @if($passwordOtp)
                    {{-- Langkah 2: kode verifikasi --}}
                    <div class="mb-4 flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[11px] leading-5 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-300">
                        <span class="material-symbols-outlined text-[17px]" aria-hidden="true">mark_email_read</span>
                        <p>
                            Kode 6 digit dikirim ke <span class="font-semibold">{{ $user->email }}</span>.
                            Berlaku sampai {{ $passwordOtp['expires_at']->timezone(config('app.timezone'))->format('H:i') }}.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('doctor.profile.password') }}" class="space-y-4" id="passwordForm">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="doctor-otp-digit-1" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Kode verifikasi</label>
                            <div class="flex gap-2" data-doctor-otp>
                                @for($i = 0; $i < 6; $i++)
                                    <input id="doctor-otp-digit-{{ $i + 1 }}"
                                           type="text" name="code[]" value="{{ old('code.' . $i) }}"
                                           inputmode="numeric" pattern="[0-9]*" maxlength="1"
                                           autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                                           aria-label="Digit {{ $i + 1 }} dari 6"
                                           class="h-12 w-full max-w-[48px] rounded-xl border bg-slate-50 text-center font-mono text-lg font-bold text-slate-900 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:bg-slate-800 dark:text-white {{ $errors->has('code') || $errors->has('code.*') ? 'border-rose-300 dark:border-rose-800' : 'border-slate-200 dark:border-slate-700' }}">
                                @endfor
                            </div>
                            @error('code')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
                            @error('code.*')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex items-start gap-2 rounded-lg bg-slate-50 px-3.5 py-2.5 text-[11px] leading-5 text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <span class="material-symbols-outlined text-[15px]" aria-hidden="true">info</span>
                            <p>Kata sandi baru sudah disiapkan dan hanya tersimpan setelah kode di atas diverifikasi.</p>
                        </div>

                        <button type="submit" class="admin-btn-primary w-full justify-center" id="passwordSubmit">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">lock_reset</span>
                            Simpan Kata Sandi
                        </button>
                    </form>

                    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-slate-100 pt-3 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <span>Belum menerima kode?</span>
                        <form method="POST" action="{{ route('doctor.profile.password.otp') }}" class="inline">
                            @csrf
                            <button type="submit" id="passwordResend" data-wait="{{ $passwordOtp['resend_wait'] }}"
                                    class="font-semibold text-primary hover:underline disabled:cursor-default disabled:text-slate-400 disabled:no-underline">
                                {{ $passwordOtp['resend_wait'] > 0
                                    ? 'Kirim ulang dalam ' . $passwordOtp['resend_wait'] . ' detik'
                                    : 'Kirim ulang kode' }}
                            </button>
                        </form>
                        <span aria-hidden="true">·</span>
                        <form method="POST" action="{{ route('doctor.profile.password.otp.cancel') }}" class="inline">
                            @csrf
                            <button type="submit" class="font-semibold text-slate-500 transition hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                                Batalkan
                            </button>
                        </form>
                    </div>
                @else
                    {{-- Langkah 1: siapkan kata sandi baru, minta kode --}}
                    @if($errors->has('code'))
                        <div class="mb-4 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-[11px] leading-5 text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-300">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>
                            <p>{{ $errors->first('code') }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('doctor.profile.password.otp') }}" class="space-y-4" id="passwordForm">
                        @csrf

                        <div class="flex items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] leading-5 text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">shield</span>
                            <p>Kata sandi saat ini tidak perlu dimasukkan. Sebagai gantinya, kami mengirim kode 6 digit ke email akun ini untuk memastikan hanya Anda yang mengganti kata sandi.</p>
                        </div>

                        <div>
                            <label for="password" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Kata sandi baru</label>
                            <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8"
                                   class="admin-input @error('password') admin-input-error @enderror">
                            @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Ulangi kata sandi baru</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                                   class="admin-input @error('password_confirmation') admin-input-error @enderror">
                            @error('password_confirmation')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex items-start gap-2 rounded-lg bg-slate-50 px-3.5 py-2.5 text-[11px] leading-5 text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <span class="material-symbols-outlined text-[15px]" aria-hidden="true">tips_and_updates</span>
                            <p>Minimal 8 karakter — gabungkan huruf, angka, dan simbol. Hindari sandi yang dipakai di layanan lain.</p>
                        </div>

                        <button type="submit" class="admin-btn-primary w-full justify-center" id="passwordSubmit">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">mark_email_read</span>
                            Kirim Kode Verifikasi
                        </button>
                    </form>
                @endif
            </x-admin.panel>
        </div>

        <x-admin.panel title="Ubah data profil" subtitle="Nama dan foto tampil pada rekam medis pasien">
            <form method="POST" action="{{ route('doctor.profile.update') }}" enctype="multipart/form-data" class="space-y-5" id="profileForm">
                @csrf
                @method('PUT')

                <div>
                    <label for="photo" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Foto profil</label>
                    <div class="flex items-center gap-4 rounded-xl border border-dashed border-slate-300 bg-slate-50/60 p-4 dark:border-slate-600 dark:bg-slate-800/30">
                        <label for="photo" class="group relative cursor-pointer">
                            @if($doctor->photo_url)
                                <img id="photoPreview" src="{{ $doctor->photo_url }}?t={{ time() }}" alt="Foto saat ini"
                                     class="h-16 w-16 flex-shrink-0 rounded-xl object-cover ring-1 ring-slate-200 dark:ring-slate-700">
                            @else
                                <span id="photoPreview" class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                                    <span class="material-symbols-outlined text-[28px]" aria-hidden="true">add_a_photo</span>
                                </span>
                            @endif
                            <span class="absolute -bottom-1.5 -right-1.5 flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition group-hover:text-emerald-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-400">
                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">edit</span>
                            </span>
                        </label>
                        <div class="min-w-0 flex-1">
                            <input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/webp"
                                   class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border file:border-emerald-200 file:bg-emerald-50 file:px-3 file:py-2 file:text-xs file:font-bold file:text-emerald-700 hover:file:bg-emerald-100 dark:text-slate-300 dark:file:border-emerald-900/50 dark:file:bg-emerald-900/30 dark:file:text-emerald-300">
                            <p class="mt-1.5 text-[11px] text-slate-400">JPG, PNG, atau WEBP. Maksimal 5 MB. Foto dikompres otomatis setelah diunggah.</p>
                            @error('photo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div>
                    <label for="name" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Nama lengkap</label>
                    <input type="text" id="name" name="name" required maxlength="255"
                           value="{{ old('name', $doctor->name) }}"
                           class="admin-input @error('name') admin-input-error @enderror">
                    @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-[11px] text-slate-400">Nama ini juga dipakai pada akun login Anda.</p>
                </div>

                <div>
                    <label for="phone" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Nomor telepon</label>
                    <input type="tel" id="phone" name="phone" maxlength="20"
                           value="{{ old('phone', $doctor->phone) }}"
                           placeholder="08xxxxxxxxxx"
                           inputmode="tel"
                           class="admin-input @error('phone') admin-input-error @enderror">
                    @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-800/40">
                    <div>
                        <label class="mb-1 flex items-center justify-between gap-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            Email
                            <span class="inline-flex items-center gap-0.5 normal-case text-[9px] font-bold text-slate-400">
                                <span class="material-symbols-outlined text-[11px]" aria-hidden="true">lock</span>
                                dikelola admin
                            </span>
                        </label>
                        <input type="email" value="{{ $user->email ?? '' }}" readonly
                               class="admin-input cursor-not-allowed bg-white text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                    </div>
                    <div>
                        <label class="mb-1 flex items-center justify-between gap-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            Spesialisasi
                            <span class="inline-flex items-center gap-0.5 normal-case text-[9px] font-bold text-slate-400">
                                <span class="material-symbols-outlined text-[11px]" aria-hidden="true">lock</span>
                                dikelola admin
                            </span>
                        </label>
                        <input type="text" value="{{ $doctor->specialization ?: '—' }}" readonly
                               class="admin-input cursor-not-allowed bg-white text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="submit" class="admin-btn-primary" id="profileSubmit">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                        Simpan Profil
                    </button>
                </div>
            </form>
        </x-admin.panel>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        // Pratinjau foto langsung setelah file dipilih.
        const photoInput = document.getElementById('photo');
        const preview = document.getElementById('photoPreview');
        if (photoInput && preview) {
            photoInput.addEventListener('change', function () {
                const file = photoInput.files && photoInput.files[0];
                if (!file) return;

                if (!preview.classList.contains('rounded-2xl')) {
                    const clone = preview.cloneNode(false);
                    clone.removeAttribute('class');
                    clone.id = 'photoPreview';
                    clone.className = 'h-16 w-16 rounded-xl object-cover ring-1 ring-slate-200 dark:ring-slate-700';
                    preview.replaceWith(clone);
                }

                const reader = new FileReader();
                reader.onload = (event) => {
                    const img = document.getElementById('photoPreview');
                    if (img.tagName === 'IMG') {
                        img.src = event.target.result;
                    } else {
                        const repl = img.cloneNode(false);
                        repl.removeAttribute('class');
                        repl.id = 'photoPreview';
                        repl.className = 'h-16 w-16 rounded-xl object-cover ring-1 ring-slate-200 dark:ring-slate-700';
                        repl.src = event.target.result;
                        img.replaceWith(repl);
                    }
                };
                reader.readAsDataURL(file);
            });
        }

        // Tombol simpan tidak dobel-klik.
        [['profileForm', 'profileSubmit'], ['passwordForm', 'passwordSubmit']].forEach(([formId, btnId]) => {
            const form = document.getElementById(formId);
            const btn = document.getElementById(btnId);
            if (form && btn) {
                form.addEventListener('submit', function () {
                    btn.disabled = true;
                    btn.style.opacity = '0.8';
                    btn.style.pointerEvents = 'none';
                });
            }
        });

        // Enam kotak kode verifikasi: auto-pindah, backspace, dan tempel.
        const otpRow = document.querySelector('[data-doctor-otp]');
        if (otpRow) {
            const digits = Array.from(otpRow.querySelectorAll('input'));

            digits.forEach(function (input, index) {
                input.addEventListener('input', function () {
                    const value = input.value.replace(/\D/g, '').slice(-1);
                    input.value = value;
                    if (value && index < digits.length - 1) {
                        digits[index + 1].focus();
                        digits[index + 1].select();
                    }
                });

                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Backspace' && input.value === '' && index > 0) {
                        event.preventDefault();
                        digits[index - 1].focus();
                        digits[index - 1].value = '';
                    } else if (event.key === 'ArrowLeft' && index > 0) {
                        event.preventDefault();
                        digits[index - 1].focus();
                    } else if (event.key === 'ArrowRight' && index < digits.length - 1) {
                        event.preventDefault();
                        digits[index + 1].focus();
                    }
                });

                input.addEventListener('paste', function (event) {
                    event.preventDefault();
                    const text = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    if (!text) return;
                    digits.forEach(function (box, i) {
                        if (i >= index && i < index + text.length) {
                            box.value = text.charAt(i - index);
                        }
                    });
                    digits[Math.min(index + text.length, digits.length - 1)].focus();
                });
            });
        }

        // Hitung mundur tombol kirim ulang kode.
        const resend = document.getElementById('passwordResend');
        if (resend) {
            let wait = parseInt(resend.dataset.wait || '0', 10);
            const tick = function () {
                if (wait <= 0) {
                    resend.disabled = false;
                    resend.textContent = 'Kirim ulang kode';
                    return;
                }
                resend.disabled = true;
                resend.textContent = 'Kirim ulang dalam ' + wait + ' detik';
                wait -= 1;
                setTimeout(tick, 1000);
            };
            tick();
        }
    })();
</script>
@endpush
