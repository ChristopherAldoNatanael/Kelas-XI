<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin · PetHeal</title>
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

        /* ============================
           LEFT: brand panel
           ============================ */
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
        .blob { position: absolute; border-radius: 50%; filter: blur(90px); opacity: 0.35; pointer-events: none; }
        .blob-1 { width: 340px; height: 340px; background: #22C55E; top: -120px; left: -100px; animation: drift 9s ease-in-out infinite alternate; }
        .blob-2 { width: 260px; height: 260px; background: #0EA5A5; bottom: -100px; right: -80px; animation: drift 11s ease-in-out infinite alternate-reverse; }
        @keyframes drift {
            from { transform: translate(0, 0) scale(1); }
            to { transform: translate(30px, 30px) scale(1.08); }
        }

        .brand-logo { display: flex; align-items: center; gap: 0.75rem; position: relative; z-index: 1; }
        .brand-logo .logo-badge {
            width: 40px; height: 40px; border-radius: 12px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            color: #fff; flex-shrink: 0;
        }
        .brand-logo .logo-badge svg { width: 22px; height: 22px; display: block; }
        .brand-logo span { font-size: 1.25rem; font-weight: 800; color: #fff; letter-spacing: -0.02em; }
        .brand-logo small { display: block; font-size: 0.68rem; font-weight: 500; color: #86EFAC; letter-spacing: 0.12em; text-transform: uppercase; }

        .brand-content { position: relative; z-index: 1; max-width: 400px; }
        .brand-content h2 {
            font-size: 2.1rem; font-weight: 800; color: #fff; line-height: 1.2;
            letter-spacing: -0.03em; margin-bottom: 1rem;
        }
        .brand-content h2 span {
            background: linear-gradient(90deg, #86EFAC, #4ADE80);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .brand-content > p { font-size: 0.9rem; color: #9CA3AF; line-height: 1.65; }

        .brand-features { list-style: none; margin-top: 1.6rem; display: flex; flex-direction: column; gap: 0.7rem; }
        .brand-features li {
            display: flex; align-items: center; gap: 0.75rem;
            font-size: 0.85rem; color: #D1D5DB;
        }
        .brand-features .icon-circle {
            width: 32px; height: 32px; border-radius: 10px; flex-shrink: 0;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
        }

        .brand-quote {
            margin-top: 1.6rem; position: relative; z-index: 1;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 1rem 1.1rem;
            backdrop-filter: blur(6px);
        }
        .brand-quote p { font-size: 0.82rem; color: #E5E7EB; line-height: 1.6; font-style: italic; }
        .brand-quote span { display: block; margin-top: 0.5rem; font-size: 0.72rem; color: #86EFAC; font-weight: 600; font-style: normal; }

        .brand-footer { display: flex; gap: 1.5rem; position: relative; z-index: 1; }
        .brand-footer .badge {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.75rem; color: #6B7280; letter-spacing: 0.02em;
        }
        .brand-footer .badge .dot {
            width: 6px; height: 6px; border-radius: 50%; background: #22C55E;
            box-shadow: 0 0 8px #22C55E;
            animation: dotBlink 3s ease-in-out infinite;
        }
        @keyframes dotBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

        /* ============================
           RIGHT: form panel
           ============================ */
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

        .mobile-logo {
            display: none;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }
        .mobile-logo .paw-badge {
            width: 36px; height: 36px; border-radius: 11px; flex-shrink: 0;
            background: #1B2A1E; color: #fff;
            display: flex; align-items: center; justify-content: center;
        }
        .mobile-logo .paw-badge svg { width: 20px; height: 20px; display: block; }
        .mobile-logo span { font-size: 1.1rem; font-weight: 800; color: #1a1a1a; letter-spacing: -0.02em; }

        .form-header { margin-bottom: 1.5rem; }
        .form-header .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #15803D;
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            padding: 0.3rem 0.7rem;
            border-radius: 999px;
            margin-bottom: 0.9rem;
        }
        .form-header h1 {
            font-size: 1.65rem;
            font-weight: 800;
            color: #111;
            letter-spacing: -0.03em;
            line-height: 1.2;
        }
        .form-header p { font-size: 0.875rem; color: #6B7280; margin-top: 0.5rem; line-height: 1.6; }

        /* alerts */
        .success-box {
            background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534;
            padding: 0.8rem 1rem; border-radius: 12px; font-size: 0.8125rem;
            line-height: 1.55; margin-bottom: 1.25rem;
            display: flex; gap: 0.6rem; align-items: flex-start;
        }
        .error-box {
            background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B;
            padding: 0.8rem 1rem; border-radius: 12px; font-size: 0.8125rem;
            line-height: 1.55; margin-bottom: 1.25rem;
        }
        .error-box ul { margin: 0.25rem 0 0 1.1rem; }
        .field-error { font-size: 0.75rem; color: #DC2626; margin-top: 0.35rem; }
        .input-wrap.invalid input { border-color: #FCA5A5; background: #FEF2F2; }
        .input-wrap.invalid input:focus { border-color: #DC2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }

        /* form elements */
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

        /* remember row */
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.4rem;
        }
        .remember-row label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: #6B7280;
            cursor: pointer;
            user-select: none;
        }
        .remember-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #1B2A1E;
            cursor: pointer;
        }

        /* submit */
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
        .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }
        .btn-submit .spinner {
            display: none; width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;
            border-radius: 50%; animation: spin 0.6s linear infinite;
        }
        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* register promo card */
        .join-card {
            margin-top: 1.5rem;
            border: 1.5px dashed #D6D3D1;
            border-radius: 14px;
            padding: 1rem 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.9rem;
            background: #FAFAF9;
            transition: border-color 0.2s, background 0.2s;
        }
        .join-card:hover { border-color: #1B2A1E; background: #F0FDF4; }
        .join-card .join-icon {
            width: 38px; height: 38px; border-radius: 12px; flex-shrink: 0;
            background: #1B2A1E; color: #86EFAC;
            display: flex; align-items: center; justify-content: center;
        }
        .join-card p { font-size: 0.78rem; color: #57534E; line-height: 1.55; }
        .join-card a { color: #1B2A1E; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .join-card a:hover { text-decoration: underline; }

        .form-footer {
            margin-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .form-footer a {
            font-size: 0.8125rem;
            color: #6B7280;
            text-decoration: none;
            transition: color 0.15s;
        }
        .form-footer a:hover { color: #1B2A1E; }
        .form-footer .lock-hint {
            font-size: 0.6875rem;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .form-footer .lock-hint svg { width: 11px; height: 11px; }
        .form-header .eyebrow svg { width: 12px; height: 12px; display: block; }
        .success-box svg { flex-shrink: 0; margin-top: 2px; }

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

    <!-- LEFT: brand -->
    <div class="brand-panel">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>

        <div class="brand-logo">
            <span class="logo-badge" style="background: #fff; border-color: rgba(255,255,255,0.4); padding: 4px 8px; width: auto; height: 40px;"><img src="/logo.png" alt="PetHeal — Veterinary Clinic System" style="height: 30px; width: auto; display: block;"></span>
            <span>PetHeal<small>Veterinary Clinic System</small></span>
        </div>

        <div class="brand-content">
            <h2>Selamat datang kembali, <span>Admin</span></h2>
            <p>Kelola jadwal, rekam medis, dan pembayaran seluruh klinik Anda dari satu dasbor yang aman.</p>

            <ul class="brand-features">
                <li>
                    <span class="icon-circle">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#86EFAC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </span>
                    Jadwal & pengingat booking real-time
                </li>
                <li>
                    <span class="icon-circle">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#86EFAC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </span>
                    Rekam medis digital per pasien
                </li>
                <li>
                    <span class="icon-circle">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#86EFAC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </span>
                    Pelacakan pembayaran terintegrasi
                </li>
            </ul>

            <div class="brand-quote">
                <p>"Sejak pakai dasbor ini, antrian klinik jauh lebih tertib dan catatan pasien tidak pernah hilang lagi."</p>
                <span>— Admin Klinik, Bandung</span>
            </div>
        </div>

        <div class="brand-footer">
            <div class="badge"><div class="dot"></div> Koneksi terenkripsi</div>
            <div class="badge"><div class="dot"></div> Data terisolasi per klinik</div>
        </div>
    </div>

    <!-- RIGHT: form -->
    <div class="form-panel">
        <div class="form-box">

            <div class="mobile-logo">
                <img src="/logo.png" alt="PetHeal — Veterinary Clinic System" style="height: 38px; width: auto; display: block;">
            </div>

            <div class="form-header">
                <span class="eyebrow"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Login Admin</span>
                <h1>Masuk ke dasbor Anda</h1>
                <p>Masukkan kredensial admin klinik untuk melanjutkan.</p>
            </div>

            @if(session('success'))
                <div class="success-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><polyline points="22 4 12 14 9 11"/></svg><span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="error-box">
                    <strong>Login gagal:</strong>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.post') }}" id="loginForm">
                @csrf

                <div class="input-wrap @error('email') invalid @enderror">
                    <label for="email" class="field-label">Email</label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           autocomplete="email"
                           placeholder="nama@email.com">
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="input-wrap @error('password') invalid @enderror">
                    <label for="password" class="field-label">Password</label>
                    <div class="pw-field">
                    <input type="password"
                           id="password"
                           name="password"
                           required
                           autocomplete="current-password"
                           placeholder="Masukkan password Anda"
                           style="padding-right: 2.75rem">
                    <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Tampilkan password">
                        <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                        <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    </div>
                    @error('password')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="remember-row">
                    <label>
                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        Ingat saya
                    </label>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <div class="spinner"></div>
                    <span class="btn-text">Masuk →</span>
                </button>
            </form>

            <div class="join-card">
                <span class="join-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                </span>
                <p>Belum punya akses admin? <a href="{{ route('admin.register') }}">Request gabung klinik</a> — diverifikasi &lt; 24 jam.</p>
            </div>

            <div class="form-footer">
                <a href="{{ url('/') }}">&larr; Kembali ke situs</a>
                <span class="lock-hint"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Akses aman</span>
            </div>

        </div>
    </div>

    <script>
        function togglePw() {
            const el = document.getElementById('password');
            const btn = document.querySelector('.pw-toggle');
            el.type = el.type === 'password' ? 'text' : 'password';
            btn.classList.toggle('active', el.type === 'text');
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
</body>
</html>
