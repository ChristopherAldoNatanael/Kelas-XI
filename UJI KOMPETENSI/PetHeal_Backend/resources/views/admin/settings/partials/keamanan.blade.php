@php
    $me = auth()->user();
    $passwordOtp = $passwordOtp ?? null;
    $codeErrors = $errors->get('code');
    $passwordErrors = array_merge($errors->get('current_password'), $errors->get('new_password'));
@endphp

<x-admin.panel :title="__('admin.security_title')" :subtitle="__('admin.security_desc')">
    <x-slot:actions>
        <x-admin.badge :label="__('admin.two_step_badge')" tone="sky" />
    </x-slot:actions>

    @if(session('warning'))
        <div class="mb-5 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-300">
            <span class="material-symbols-outlined text-[18px] shrink-0" aria-hidden="true">warning</span>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    @if($passwordErrors !== [])
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-300">
            @foreach($passwordErrors as $e) <p>{{ $e }}</p> @endforeach
        </div>
    @endif

    @if($codeErrors !== [])
        <div class="mb-5 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-300">
            <span class="material-symbols-outlined text-[18px] shrink-0" aria-hidden="true">error</span>
            <div>
                @foreach($codeErrors as $e) <p>{{ $e }}</p> @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr,240px]">
        <div>
            @if(!$passwordOtp)
                {{-- Langkah 1: minta kode --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-700 dark:bg-slate-800/40">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">{{ __('admin.step_1_of_2') }}</p>
                    <h4 class="mt-1.5 text-sm font-bold text-slate-900 dark:text-white">{{ __('admin.request_access_code') }}</h4>
                    <p class="mt-1.5 text-xs leading-6 text-slate-500 dark:text-slate-400">
                        {{ __('admin.request_access_code_desc') }}
                    </p>

                    <div class="mt-4 flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <span class="material-symbols-outlined text-[18px] text-slate-400" aria-hidden="true">mail</span>
                        <span class="truncate text-sm font-medium text-slate-700 dark:text-slate-300">{{ $me->email }}</span>
                        <span class="ml-auto rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">{{ __('admin.account_email_label') }}</span>
                    </div>

                    <form method="POST" action="{{ route('admin.settings.password.otp') }}?tab=keamanan" class="mt-4">
                        @csrf
                        <button type="submit" class="admin-btn-primary">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">send</span>
                            {{ __('admin.send_access_code') }}
                        </button>
                    </form>
                </div>
            @else
                {{-- Langkah 2: kode + password baru --}}
                <div class="mb-4 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs leading-5 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-300">
                    <span class="material-symbols-outlined text-[17px] shrink-0" aria-hidden="true">mark_email_read</span>
                    <p>
                        {{ __('admin.code_sent_to', [
                            'email' => $me->email,
                            'time' => $passwordOtp['expires_at']->timezone(config('app.timezone'))->format('H:i'),
                        ]) }}
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.settings.password') }}?tab=keamanan" class="space-y-5" id="password-otp-form">
                    @csrf

                    <div>
                        <label for="admin-otp-digit-1" class="admin-label">{{ __('admin.admin_otp_label') }}</label>
                        <div class="flex gap-2" data-admin-otp>
                            @for($i = 0; $i < 6; $i++)
                                <input
                                    id="admin-otp-digit-{{ $i + 1 }}"
                                    type="text"
                                    name="code[]"
                                    value="{{ old('code.' . $i) }}"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    maxlength="1"
                                    autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                                    aria-label="{{ __('admin.digit_n_of_total', ['n' => $i + 1, 'total' => 6]) }}"
                                    class="h-12 w-full max-w-[52px] rounded-xl border border-slate-200 bg-slate-50 text-center font-mono text-lg font-bold text-slate-900 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                            @endfor
                        </div>
                    </div>

                    <div class="space-y-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <div>
                            <label for="current_password" class="admin-label">{{ __('common.current_password') }}</label>
                            <input id="current_password" type="password" name="current_password" required autocomplete="current-password"
                                   class="admin-input @error('current_password') admin-input-error @enderror">
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="new_password" class="admin-label">{{ __('common.new_password') }}</label>
                                <input id="new_password" type="password" name="new_password" required autocomplete="new-password"
                                       placeholder="{{ __('admin.password_min_8') }}"
                                       class="admin-input @error('new_password') admin-input-error @enderror">
                            </div>
                            <div>
                                <label for="new_password_confirmation" class="admin-label">{{ __('common.confirm_password') }}</label>
                                <input id="new_password_confirmation" type="password" name="new_password_confirmation" required autocomplete="new-password"
                                       class="admin-input">
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5 dark:border-slate-800">
                        <button type="submit" class="admin-btn-primary">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">lock_reset</span>
                            {{ __('admin.save_new_password') }}
                        </button>
                        <span class="text-xs text-slate-400">{{ __('admin.password_active_after_code') }}</span>
                    </div>
                </form>

                <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-slate-100 pt-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <span>{{ __('admin.not_received_code') }}</span>
                    <form method="POST" action="{{ route('admin.settings.password.otp') }}?tab=keamanan" class="inline">
                        @csrf
                        <button type="submit" id="admin-otp-resend" data-wait="{{ $passwordOtp['resend_wait'] }}"
                                class="font-semibold text-primary hover:underline disabled:cursor-default disabled:text-slate-400 disabled:no-underline">
                            {{ $passwordOtp['resend_wait'] > 0
                                ? __('admin.resend_in_seconds', ['seconds' => $passwordOtp['resend_wait']])
                                : __('admin.resend_code') }}
                        </button>
                    </form>
                    <span aria-hidden="true">·</span>
                    <form method="POST" action="{{ route('admin.settings.password.otp.cancel') }}?tab=keamanan" class="inline">
                        @csrf
                        <button type="submit" class="font-semibold text-slate-500 transition hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                            {{ __('admin.cancel_code') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <aside class="h-fit rounded-xl border border-slate-200/70 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-800/40">
            <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">shield</span>
                {{ __('admin.why_code_title') }}
            </p>
            <ul class="mt-3 space-y-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                <li>{{ __('admin.why_code_1') }}</li>
                <li>{{ __('admin.why_code_2') }}</li>
                <li>{{ __('admin.why_code_3') }}</li>
            </ul>
            <p class="mt-3 border-t border-slate-200/70 pt-3 text-[11px] leading-5 text-slate-400 dark:border-slate-700">
                {{ __('admin.never_ask_code') }}
            </p>
        </aside>
    </div>
</x-admin.panel>

@if($passwordOtp)
<script>
    (function () {
        const row = document.querySelector('[data-admin-otp]');
        const form = document.getElementById('password-otp-form');
        if (!row || !form) return;

        const RESEND_LABEL = @json(__('admin.resend_code'));
        const RESEND_COUNTDOWN = @json(__('admin.resend_in_seconds', ['seconds' => ':seconds']));

        const digits = Array.from(row.querySelectorAll('input'));

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

        const resend = document.getElementById('admin-otp-resend');
        if (resend) {
            let wait = parseInt(resend.dataset.wait || '0', 10);
            const tick = function () {
                if (wait <= 0) {
                    resend.disabled = false;
                    resend.textContent = RESEND_LABEL;
                    return;
                }
                resend.disabled = true;
                resend.textContent = RESEND_COUNTDOWN.replace(':seconds', String(wait));
                wait -= 1;
                setTimeout(tick, 1000);
            };
            tick();
        }
    })();
</script>
@endif
