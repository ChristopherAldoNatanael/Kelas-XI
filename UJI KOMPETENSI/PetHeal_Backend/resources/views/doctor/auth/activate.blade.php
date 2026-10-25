<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Kata Sandi · Portal Dokter · PetHeal</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F4F1ED;
            color: #1a1a1a;
            display: flex;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        .brand-panel {
            flex: 1;
            background: radial-gradient(120% 100% at 80% 0%, #23402C 0%, #1B2A1E 45%, #101B13 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem;
            position: relative;
            overflow: hidden;
            animation: brandSlideIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes brandSlideIn {
            from { opacity: 0; transform: translateX(-24px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .brand-panel::before {
            content: ''; position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='20' cy='20' r='1'/%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }

        .steps { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 0; }
        .step {
            display: flex; align-items: flex-start; gap: 0.9rem;
            padding: 0.9rem 0;
        }
        .step + .step { border-top: 1px solid rgba(255,255,255,0.08); }
        .step .step-dot {
            width: 26px; height: 26px; border-radius: 50%;
            flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; font-weight: 700;
            border: 1px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.55);
        }
        .step.done .step-dot {
            background: #fff; border-color: #fff; color: #1B2A1E;
        }
        .step.current .step-dot {
            border-color: #86EFAC; color: #86EFAC;
            box-shadow: 0 0 0 3px rgba(134, 239, 172, 0.15);
        }
        .step .step-body small {
            display: block; font-size: 0.68rem; font-weight: 600;
            letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.15rem;
        }
        .step.done .step-body small { color: #86EFAC; }
        .step.current .step-body small { color: #fff; }
        .step.pending .step-body small { color: rgba(255,255,255,0.4); }
        .step .step-body p { font-size: 0.85rem; line-height: 1.5; }
        .step.done .step-body p { color: #E5E7EB; }
        .step.current .step-body p { color: #fff; }
        .step.pending .step-body p { color: rgba(255,255,255,0.4); }

        .brand-note {
            position: relative; z-index: 1;
            display: flex; gap: 0.8rem; align-items: flex-start;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 1rem 1.1rem;
            max-width: 400px;
        }
        .brand-note svg { flex-shrink: 0; margin-top: 1px; }
        .brand-note p { font-size: 0.8rem; color: #D1D5DB; line-height: 1.65; }
        .brand-note strong { color: #fff; font-weight: 600; }

        .brand-logo { display: flex; align-items: center; gap: 0.75rem; position: relative; z-index: 1; margin-bottom: 2.5rem; }
        .brand-logo .logo-badge {
            border-radius: 12px; background: #fff; padding: 4px 8px; height: 40px;
            display: flex; align-items: center; justify-content: center;
        }
        .brand-logo img { height: 30px; width: auto; display: block; }
        .brand-logo span { font-size: 1.25rem; font-weight: 800; color: #fff; letter-spacing: -0.02em; }
        .brand-logo small { display: block; font-size: 0.68rem; font-weight: 500; color: #86EFAC; letter-spacing: 0.12em; text-transform: uppercase; }

        .brand-content { position: relative; z-index: 1; max-width: 400px; margin-bottom: 2.5rem; }
        .brand-content h2 { font-size: 2.1rem; font-weight: 800; color: #fff; line-height: 1.2; letter-spacing: -0.03em; margin-bottom: 1rem; }
        .brand-content h2 span { color: #86EFAC; }
        .brand-content > p { font-size: 0.9rem; color: #9CA3AF; line-height: 1.65; }

        .brand-footer { display: flex; gap: 1.5rem; position: relative; z-index: 1; }
        .brand-footer .badge { display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: #6B7280; letter-spacing: 0.02em; }
        .brand-footer .badge .dot { width: 6px; height: 6px; border-radius: 50%; background: #22C55E; box-shadow: 0 0 8px #22C55E; }

        .form-panel {
            flex: 1.15;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 3rem;
            background: #fff;
            overflow-y: auto;
            animation: panelSlideIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) 0.1s both;
        }
        @keyframes panelSlideIn {
            from { opacity: 0; transform: translateX(24px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .form-box { width: 100%; max-width: 400px; margin: auto 0; }

        .mobile-logo { display: none; align-items: center; margin-bottom: 1.75rem; }
        .mobile-logo img { height: 38px; width: auto; display: block; }

        .form-header { margin-bottom: 1.5rem; }
        .form-header .eyebrow {
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            color: #15803D; background: #F0FDF4; border: 1px solid #BBF7D0;
            padding: 0.3rem 0.7rem; border-radius: 999px; margin-bottom: 0.9rem;
        }
        .form-header .eyebrow svg { width: 12px; height: 12px; display: block; }
        .form-header h1 { font-size: 1.65rem; font-weight: 800; color: #111; letter-spacing: -0.03em; line-height: 1.2; }
        .form-header p { font-size: 0.875rem; color: #6B7280; margin-top: 0.5rem; line-height: 1.6; }

        .error-box {
            background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B;
            padding: 0.8rem 1rem; border-radius: 12px; font-size: 0.8125rem;
            line-height: 1.55; margin-bottom: 1.25rem;
            display: flex; gap: 0.6rem; align-items: flex-start;
        }
        .error-box svg { flex-shrink: 0; margin-top: 2px; }
        .field-error { font-size: 0.75rem; color: #DC2626; margin-top: 0.35rem; }
        .input-wrap.invalid input { border-color: #FCA5A5; background: #FEF2F2; }
        .input-wrap.invalid input:focus { border-color: #DC2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }

        .locked-field {
            display: flex; align-items: center; gap: 0.75rem;
            border: 1px solid #D1D5DB; border-radius: 10px;
            background: #F9FAF8;
            padding: 0.65rem 0.9rem; margin-bottom: 1.05rem;
        }
        .locked-field .locked-icon {
            width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
            background: #F0FDF4; border: 1px solid #DCFCE7;
            display: flex; align-items: center; justify-content: center;
        }
        .locked-field .locked-icon svg { width: 16px; height: 16px; display: block; }
        .locked-field .locked-body { min-width: 0; flex: 1; }
        .locked-field .locked-body small {
            display: block; font-size: 0.65rem; font-weight: 700;
            letter-spacing: 0.08em; text-transform: uppercase; color: #9CA3AF; margin-bottom: 0.1rem;
        }
        .locked-field .locked-body span {
            display: block; font-size: 0.9rem; font-weight: 700; color: #111;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .locked-field .locked-badge {
            flex-shrink: 0;
            font-size: 0.65rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
            color: #15803D; background: #F0FDF4; border: 1px solid #BBF7D0;
            padding: 0.25rem 0.6rem; border-radius: 999px;
        }
        .locked-hint { font-size: 0.75rem; color: #9CA3AF; margin: -0.55rem 0 1.05rem; line-height: 1.55; }

        label.field-label {
            display: block; font-size: 0.8125rem; font-weight: 600;
            color: #374151; margin-bottom: 0.375rem;
        }
        .input-wrap { position: relative; margin-bottom: 1.05rem; }
        .input-wrap input {
            width: 100%; padding: 0.7rem 0.9rem; font-size: 0.9rem;
            font-family: inherit; color: #111; background: #FAFAF9;
            border: 1px solid #D6D3D1; border-radius: 10px; outline: none;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        }
        .input-wrap input::placeholder { color: #A8A29E; }
        .input-wrap input:focus {
            border-color: #1B2A1E; box-shadow: 0 0 0 3px rgba(27, 42, 30, 0.1); background: #fff;
        }
        .field-hint { font-size: 0.75rem; color: #9CA3AF; margin: -0.55rem 0 1.05rem; line-height: 1.5; }
        .pw-field { position: relative; }
        .pw-toggle {
            position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #9CA3AF;
            padding: 0.25rem; border-radius: 6px; transition: color 0.15s;
            display: flex; align-items: center; justify-content: center;
        }
        .pw-toggle:hover { color: #374151; }
        .pw-toggle .icon-eye { display: none; }
        .pw-toggle.active .icon-eye-off { display: none; }
        .pw-toggle.active .icon-eye { display: block; }

        .btn-submit {
            width: 100%; padding: 0.75rem; font-size: 0.9rem; font-weight: 700;
            font-family: inherit; color: #fff; background: #1B2A1E;
            border: none; border-radius: 12px; cursor: pointer;
            transition: background 0.15s, transform 0.1s, box-shadow 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            box-shadow: 0 10px 24px -12px rgba(27, 42, 30, 0.5);
        }
        .btn-submit:hover { background: #2D4A33; box-shadow: 0 12px 28px -12px rgba(27, 42, 30, 0.6); }
        .btn-submit:active { transform: scale(0.99); }
        .btn-submit:disabled { opacity: 0.75; cursor: not-allowed; }
        .btn-submit .spinner {
            display: none; width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;
            border-radius: 50%; animation: spin 0.6s linear infinite;
        }
        .btn-submit .btn-loading { display: none; }
        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading .btn-text { display: none; }
        .btn-submit.loading .btn-loading { display: inline; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .after-note {
            margin-top: 1.1rem;
            font-size: 0.78rem; color: #9CA3AF; text-align: center; line-height: 1.55;
        }

        .form-footer {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid #E7E5E4;
            display: flex; justify-content: space-between; align-items: center;
        }
        .form-footer a { font-size: 0.8125rem; color: #6B7280; text-decoration: none; transition: color 0.15s; }
        .form-footer a:hover { color: #1B2A1E; }
        .form-footer a.strong { color: #1B2A1E; font-weight: 700; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .brand-panel { display: none; }
            .form-panel { padding: 2rem 1.25rem 3rem; }
            .mobile-logo { display: flex; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>

    <div class="brand-panel">
        <div>
            <div class="brand-logo">
                <span class="logo-badge"><img src="/logo.png" alt="PetHeal — Veterinary Clinic System"></span>
                <span>PetHeal<small>Portal Dokter</small></span>
            </div>
            <div class="brand-content">
                <h2>Satu langkah terakhir,<br><span>lalu akun siap.</span></h2>
                <p>Tautan undangan sudah membuktikan email Anda. Sekarang cukup tentukan kata sandi — selesai.</p>
            </div>
            <div class="steps">
                <div class="step done">
                    <span class="step-dot">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <div class="step-body">
                        <small>Selesai</small>
                        <p>Buka tautan undangan dari email klinik</p>
                    </div>
                </div>
                <div class="step current">
                    <span class="step-dot">2</span>
                    <div class="step-body">
                        <small>Halaman ini</small>
                        <p>Tentukan kata sandi portal dokter</p>
                    </div>
                </div>
                <div class="step pending">
                    <span class="step-dot">3</span>
                    <div class="step-body">
                        <small>Berikutnya</small>
                        <p>Masuk langsung ke dashboard dokter</p>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="brand-note">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#86EFAC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <p>Tautan undangan berlaku <strong>60 menit</strong> dan hanya bisa dipakai <strong>sekali</strong>. Jika kedaluwarsa, minta admin klinik mengirim ulang undangan.</p>
            </div>
            <div class="brand-footer" style="margin-top: 1.5rem;">
                <div class="badge"><div class="dot"></div> Data terisolasi per klinik</div>
            </div>
        </div>
    </div>

    <div class="form-panel">
        <div class="form-box">

            <div class="mobile-logo">
                <img src="/logo.png" alt="PetHeal — Veterinary Clinic System">
            </div>

            <div class="form-header">
                <span class="eyebrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    Aktivasi undangan
                </span>
                <h1>Buat kata sandi portal dokter</h1>
                <p>Email Anda sudah terverifikasi lewat tautan undangan. Tinggal setel kata sandi untuk masuk ke portal.</p>
            </div>

            @if($errors->any())
                <div class="error-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>
                        <strong>Aktivasi belum bisa diproses:</strong>
                        <ul style="margin: 0.25rem 0 0 1.1rem;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </span>
                </div>
            @endif

            <form method="POST" action="{{ route('doctor.activate.post', ['token' => $token]) }}" id="activationForm">
                @csrf

                <div class="locked-field">
                    <span class="locked-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#15803D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><polyline points="22 4 12 14 9 11"/></svg>
                    </span>
                    <div class="locked-body">
                        <small>Email dari undangan</small>
                        <span>{{ $doctor->email }}</span>
                    </div>
                    <span class="locked-badge">Terverifikasi</span>
                </div>
                <p class="locked-hint">Email ini terikat pada tautan undangan Anda dan tidak bisa diganti. Salah email? Hubungi admin klinik.</p>

                <div class="input-wrap @error('password') invalid @enderror">
                    <label for="password" class="field-label">Kata sandi baru</label>
                    <div class="pw-field">
                        <input type="password"
                               id="password"
                               name="password"
                               required
                               autofocus
                               autocomplete="new-password"
                               placeholder="Minimal 8 karakter"
                               style="padding-right: 2.75rem">
                        <button type="button" class="pw-toggle" data-target="password" aria-label="Tampilkan kata sandi">
                            <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                            <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    @error('password')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="input-wrap @error('password_confirmation') invalid @enderror">
                    <label for="password_confirmation" class="field-label">Ulangi kata sandi</label>
                    <div class="pw-field">
                        <input type="password"
                               id="password_confirmation"
                               name="password_confirmation"
                               required
                               autocomplete="new-password"
                               placeholder="Ulangi kata sandi yang sama"
                               style="padding-right: 2.75rem">
                        <button type="button" class="pw-toggle" data-target="password_confirmation" aria-label="Tampilkan kata sandi">
                            <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                            <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    @error('password_confirmation')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <div class="spinner"></div>
                    <svg class="btn-text" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <span class="btn-text">Aktivasi Akun &amp; Masuk Dashboard</span>
                    <span class="btn-loading">Mengaktifkan akun…</span>
                </button>
            </form>

            <p class="after-note">Kata sandi bisa diubah kapan saja lewat menu Profil di dashboard.</p>

            <div class="form-footer">
                <a href="{{ route('doctor.login') }}" class="strong">&larr; Sudah punya akun? Masuk</a>
                <a href="{{ url('/') }}">Kembali ke situs</a>
            </div>

        </div>
    </div>

    <script>
        document.querySelectorAll('.pw-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;
                input.type = input.type === 'password' ? 'text' : 'password';
                btn.classList.toggle('active', input.type === 'text');
            });
        });

        document.getElementById('activationForm').addEventListener('submit', function (event) {
            const form = event.target;
            const password = document.getElementById('password');
            const confirmation = document.getElementById('password_confirmation');

            // Submit event only fires setelah validasi HTML5 lolos, jadi sisa
            // cek di sini hanya kecocokan kedua sandi.
            if (password.value !== confirmation.value) {
                event.preventDefault();

                const wrap = confirmation.closest('.input-wrap');
                wrap.classList.add('invalid');
                const existing = wrap.querySelector('.field-error');
                if (existing) existing.remove();

                const msg = document.createElement('div');
                msg.className = 'field-error';
                msg.textContent = 'Kedua kolom kata sandi belum sama.';
                confirmation.after(msg);

                confirmation.value = '';
                password.focus();
                return;
            }

            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
</body>
</html>
