<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — PetHeal Admin</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🐾</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F4F1ED;
            color: #1a1a1a;
            display: flex;
            min-height: 100vh;
        }

        /* ============================
           LEFT: form panel
           ============================ */
        .form-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            background: #fff;
            /* entrance animation */
            animation: panelSlideIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes panelSlideIn {
            from { opacity: 0; transform: translateX(-24px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .form-box {
            width: 100%;
            max-width: 400px;
        }

        /* mobile logo */
        .mobile-logo {
            display: none;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2.5rem;
        }
        .mobile-logo img { height: 28px; }
        .mobile-logo span {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a1a1a;
        }

        .form-header {
            margin-bottom: 2rem;
        }
        .form-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111;
            letter-spacing: -0.03em;
        }
        .form-header p {
            font-size: 0.875rem;
            color: #6B7280;
            margin-top: 0.375rem;
        }

        /* error */
        .error-box {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.8125rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }

        /* form elements */
        label.field-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.375rem;
        }
        .input-wrap {
            position: relative;
            margin-bottom: 1.125rem;
        }
        .input-wrap input {
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 0.9rem;
            font-family: inherit;
            color: #111;
            background: #FAFAF9;
            border: 1px solid #D6D3D1;
            border-radius: 8px;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .input-wrap input::placeholder {
            color: #A8A29E;
        }
        .input-wrap input:focus {
            border-color: #1B2A1E;
            box-shadow: 0 0 0 2px rgba(27, 42, 30, 0.08);
            background: #fff;
        }

        /* two-column row */
        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        /* password toggle */
        .pw-toggle {
            position: absolute;
            right: 0.625rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9CA3AF;
            padding: 0.25rem;
            border-radius: 4px;
            transition: color 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pw-toggle:hover { color: #374151; }
        .pw-toggle svg { display: block; }
        .pw-toggle .icon-eye-off { display: block; }
        .pw-toggle .icon-eye    { display: none; }
        .pw-toggle.active .icon-eye-off { display: none; }
        .pw-toggle.active .icon-eye     { display: block; }

        /* terms */
        .terms-row {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.8125rem;
            color: #6B7280;
            line-height: 1.5;
        }
        .terms-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #1B2A1E;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .terms-row a {
            color: #1B2A1E;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        /* submit */
        .btn-submit {
            width: 100%;
            padding: 0.7rem;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: inherit;
            color: #fff;
            background: #1B2A1E;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-submit:hover { background: #2D4A33; }
        .btn-submit:active { transform: scale(0.99); }
        .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }
        .btn-submit .spinner {
            display: none;
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* footer */
        .form-footer {
            margin-top: 2rem;
            padding-top: 1.25rem;
            border-top: 1px solid #E5E7EB;
            text-align: center;
        }
        .form-footer p {
            font-size: 0.8125rem;
            color: #6B7280;
        }
        .form-footer a {
            color: #1B2A1E;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.15s;
        }
        .form-footer a:hover { color: #2D4A33; }

        /* ============================
           RIGHT: brand panel
           ============================ */
        .brand-panel {
            flex: 1;
            background: #1B2A1E;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem;
            position: relative;
            overflow: hidden;
            /* entrance animation */
            animation: brandSlideIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.1s both;
        }
        @keyframes brandSlideIn {
            from { opacity: 0; transform: translateX(24px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* subtle dot texture */
        .brand-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='20' cy='20' r='1'/%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }

        /* decorative accent bar */
        .brand-panel::after {
            content: '';
            position: absolute;
            top: -20%;
            right: -2px;
            width: 4px;
            height: 40%;
            background: linear-gradient(180deg, transparent, #22C55E, transparent);
            border-radius: 2px;
            opacity: 0.4;
            animation: accentPulse 4s ease-in-out infinite;
        }
        @keyframes accentPulse {
            0%, 100% { opacity: 0.25; top: -20%; }
            50%      { opacity: 0.5;  top: 20%; }
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            animation: fadeFloat 0.6s ease 0.3s both;
        }
        .brand-logo img { height: 32px; width: auto; }
        .brand-logo span {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.02em;
        }

        .brand-content {
            position: relative;
            max-width: 380px;
            animation: fadeFloat 0.6s ease 0.4s both;
        }
        @keyframes fadeFloat {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .brand-content h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.25;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
        }
        .brand-content h2 span { color: #86EFAC; }
        .brand-content p {
            font-size: 0.9rem;
            color: #9CA3AF;
            line-height: 1.6;
        }

        /* feature list */
        .brand-features {
            list-style: none;
            margin-top: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            position: relative;
        }
        .brand-features li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: #D1D5DB;
            animation: fadeFloat 0.5s ease both;
        }
        .brand-features li:nth-child(1) { animation-delay: 0.5s; }
        .brand-features li:nth-child(2) { animation-delay: 0.6s; }
        .brand-features li:nth-child(3) { animation-delay: 0.7s; }
        .brand-features li .icon-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.8rem;
        }

        .brand-footer {
            display: flex;
            gap: 1.5rem;
            position: relative;
            animation: fadeFloat 0.5s ease 0.8s both;
        }
        .brand-footer .badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: #6B7280;
            letter-spacing: 0.02em;
        }
        .brand-footer .badge .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #22C55E;
            animation: dotBlink 3s ease-in-out infinite;
        }
        .brand-footer .badge:nth-child(2) .dot {
            animation-delay: 1.5s;
        }
        @keyframes dotBlink {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.3; }
        }

        /* ============================
           Responsive
           ============================ */
        @media (max-width: 860px) {
            body { flex-direction: column; }
            .brand-panel { display: none; }
            .form-panel { flex: 1; }
            .mobile-logo { display: flex; }
        }
    </style>
</head>
<body>

    <!-- LEFT: form -->
    <div class="form-panel">
        <div class="form-box">

            <div class="mobile-logo">
                <img src="/logo.png" alt="PetHeal" width="140" height="28">
                <span>PetHeal</span>
            </div>

            <div class="form-header">
                <h1>Create an account</h1>
                <p>Set up your admin access to get started.</p>
            </div>

            @if($errors->any())
                <div class="error-box">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.register.post') }}" id="registerForm">
                @csrf

                <div class="row-2">
                    <div class="input-wrap">
                        <label for="name" class="field-label">Full name</label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               autofocus
                               autocomplete="name"
                               placeholder="John Doe">
                    </div>
                    <div class="input-wrap">
                        <label for="phone" class="field-label">Phone</label>
                        <input type="tel"
                               id="phone"
                               name="phone"
                               value="{{ old('phone') }}"
                               autocomplete="tel"
                               placeholder="0812xxxx">
                    </div>
                </div>

                <div class="input-wrap">
                    <label for="email" class="field-label">Email</label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autocomplete="email"
                           placeholder="admin@petheal.com">
                </div>

                <div class="input-wrap">
                    <label for="password" class="field-label">Password</label>
                    <input type="password"
                           id="password"
                           name="password"
                           required
                           autocomplete="new-password"
                           placeholder="Minimum 8 characters">
                    <button type="button" class="pw-toggle" onclick="togglePw('password', this)" aria-label="Toggle password visibility">
                        <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                        <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>

                <div class="input-wrap">
                    <label for="password_confirmation" class="field-label">Confirm password</label>
                    <input type="password"
                           id="password_confirmation"
                           name="password_confirmation"
                           required
                           autocomplete="new-password"
                           placeholder="Re-enter your password">
                    <button type="button" class="pw-toggle" onclick="togglePw('password_confirmation', this)" aria-label="Toggle password visibility">
                        <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                        <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>

                <div class="terms-row">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <div class="spinner"></div>
                    <span class="btn-text">Create account</span>
                </button>
            </form>

            <div class="form-footer">
                <p>Already have an account? <a href="{{ route('admin.login') }}">Sign in</a></p>
            </div>

        </div>
    </div>

    <!-- RIGHT: brand -->
    <div class="brand-panel">
        <div class="brand-logo">
            <img src="/logo.png" alt="PetHeal" width="140" height="28">
            <span>PetHeal</span>
        </div>

        <div class="brand-content">
            <h2>Start managing<br>your <span>clinic</span></h2>
            <p>Create your admin account to manage appointments, track medical records, and oversee patient care.</p>

            <ul class="brand-features">
                <li>
                    <span class="icon-circle">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#86EFAC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </span>
                    Appointment scheduling & reminders
                </li>
                <li>
                    <span class="icon-circle">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#86EFAC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </span>
                    Digital medical records
                </li>
                <li>
                    <span class="icon-circle">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#86EFAC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </span>
                    Integrated payment tracking
                </li>
            </ul>
        </div>

        <div class="brand-footer">
            <div class="badge"><div class="dot"></div> Secure access</div>
            <div class="badge"><div class="dot"></div> Encrypted connection</div>
        </div>
    </div>

    <script>
        function togglePw(fieldId, btn) {
            const el = document.getElementById(fieldId);
            if (el.type === 'password') {
                el.type = 'text';
                btn.classList.add('active');
            } else {
                el.type = 'password';
                btn.classList.remove('active');
            }
        }

        document.getElementById('registerForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
</body>
</html>