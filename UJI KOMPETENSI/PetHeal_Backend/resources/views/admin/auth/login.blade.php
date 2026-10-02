<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — PetHeal Admin</title>
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

        /* ---- LEFT SIDE: brand panel ---- */
        .brand-panel {
            flex: 1;
            background: #1B2A1E;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }
        .brand-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='20' cy='20' r='1'/%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
        }
        .brand-logo img {
            height: 32px;
            width: auto;
        }
        .brand-logo span {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.02em;
        }
        .brand-content {
            position: relative;
            max-width: 380px;
        }
        .brand-content h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.25;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
        }
        .brand-content h2 span {
            color: #86EFAC;
        }
        .brand-content p {
            font-size: 0.9rem;
            color: #9CA3AF;
            line-height: 1.6;
        }
        .brand-footer {
            display: flex;
            gap: 1.5rem;
            position: relative;
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
        }

        /* ---- RIGHT SIDE: form panel ---- */
        .form-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            background: #fff;
        }
        .form-box {
            width: 100%;
            max-width: 380px;
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
            margin-bottom: 1.25rem;
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

        /* remember row */
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .remember-row label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: #6B7280;
            cursor: pointer;
        }
        .remember-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #1B2A1E;
            border-radius: 4px;
            cursor: pointer;
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
        .btn-submit:hover {
            background: #2D4A33;
        }
        .btn-submit:active {
            transform: scale(0.99);
        }
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
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
        }

        /* ---- Responsive ---- */
        @media (max-width: 860px) {
            body { flex-direction: column; }
            .brand-panel { display: none; }
            .form-panel { flex: 1; }
            .mobile-logo { display: flex; }
        }
    </style>
</head>
<body>

    <!-- Left: brand -->
    <div class="brand-panel">
        <div class="brand-logo">
            <img src="/logo.png" alt="PetHeal" width="140" height="28">
            <span>PetHeal</span>
        </div>

        <div class="brand-content">
            <h2>Welcome back,<br><span>Admin</span></h2>
            <p>Manage your clinic's appointments, medical records, and patient care from one place.</p>
        </div>

        <div class="brand-footer">
            <div class="badge"><div class="dot"></div> Secure access</div>
            <div class="badge"><div class="dot"></div> Encrypted connection</div>
        </div>
    </div>

    <!-- Right: form -->
    <div class="form-panel">
        <div class="form-box">

            <div class="mobile-logo">
                <img src="/logo.png" alt="PetHeal" width="140" height="28">
                <span>PetHeal</span>
            </div>

            <div class="form-header">
                <h1>Sign in</h1>
                <p>Enter your credentials to continue.</p>
            </div>

            @if($errors->any())
                <div class="error-box">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.post') }}" id="loginForm">
                @csrf

                <div class="input-wrap">
                    <label for="email" class="field-label">Email</label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           autocomplete="email"
                           placeholder="admin@petheal.com">
                </div>

                <div class="input-wrap">
                    <label for="password" class="field-label">Password</label>
                    <input type="password"
                           id="password"
                           name="password"
                           required
                           autocomplete="current-password"
                           placeholder="Enter your password">
                    <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Toggle password visibility">
                        <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                        <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>

                <div class="remember-row">
                    <label>
                        <input type="checkbox" name="remember">
                        Remember me
                    </label>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <div class="spinner"></div>
                    <span class="btn-text">Sign in</span>
                </button>
            </form>

            <div class="form-footer">
                <a href="{{ url('/') }}">&larr; Back to site</a>
                <a href="{{ route('admin.register') }}">Create account</a>
            </div>

        </div>
    </div>

    <script>
        function togglePw() {
            const el = document.getElementById('password');
            const btn = document.querySelector('.pw-toggle');
            if (el.type === 'password') {
                el.type = 'text';
                btn.classList.add('active');
            } else {
                el.type = 'password';
                btn.classList.remove('active');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
</body>
</html>