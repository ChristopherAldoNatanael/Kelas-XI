<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — PetHeal Admin</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🐾</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', sans-serif;
            letter-spacing: -0.011em;
        }

        /* Animated gradient background */
        .bg-animated {
            background: linear-gradient(135deg, #022C22 0%, #064E3B 30%, #0F172A 70%, #020617 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Floating orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
        }
        .orb-1 {
            width: 400px; height: 400px;
            background: rgba(16, 185, 129, 0.15);
            top: -10%; left: -5%;
            animation: floatOrb 20s ease-in-out infinite;
        }
        .orb-2 {
            width: 300px; height: 300px;
            background: rgba(6, 78, 59, 0.25);
            bottom: -5%; right: -5%;
            animation: floatOrb 25s ease-in-out infinite reverse;
        }
        .orb-3 {
            width: 250px; height: 250px;
            background: rgba(16, 185, 129, 0.08);
            top: 40%; left: 60%;
            animation: floatOrb 18s ease-in-out infinite 3s;
        }
        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -40px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }

        /* Grid pattern overlay */
        .grid-overlay {
            background-image:
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* Left panel decorative dots pattern */
        .dots-pattern {
            background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        /* Glass card for form */
        .login-glass {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(24px);
            box-shadow:
                0 25px 60px -12px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.06);
        }

        /* Form input styling */
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            background: #F8FAFC;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            font-size: 0.9375rem;
            color: #0F172A;
            transition: all 0.2s ease;
            outline: none;
        }
        .form-input:hover {
            border-color: #CBD5E1;
            background: #F1F5F9;
        }
        .form-input:focus {
            border-color: #10B981;
            background: white;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.08);
        }
        .form-input::placeholder {
            color: #94A3B8;
        }
        .form-input.error {
            border-color: #EF4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.08);
        }

        .input-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 1.25rem;
            pointer-events: none;
            transition: color 0.2s ease;
        }
        .form-group:focus-within .input-icon {
            color: #10B981;
        }

        /* Submit button */
        .btn-primary {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            font-weight: 600;
            font-size: 0.9375rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.25s ease;
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #059669, #047857);
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(16, 185, 129, 0.4);
        }
        .btn-primary:hover::before {
            opacity: 1;
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .btn-primary span { position: relative; z-index: 1; }
        .btn-primary .spinner {
            display: none;
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        .btn-primary.loading .spinner { display: block; }
        .btn-primary.loading .btn-label { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Entrance animations */
        .fade-up {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.6s ease forwards;
        }
        .fade-up:nth-child(1) { animation-delay: 0.1s; }
        .fade-up:nth-child(2) { animation-delay: 0.2s; }
        .fade-up:nth-child(3) { animation-delay: 0.3s; }
        .fade-up:nth-child(4) { animation-delay: 0.4s; }
        .fade-up:nth-child(5) { animation-delay: 0.5s; }
        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }

        /* Error banner */
        .error-banner {
            background: #FEF2F2;
            border: 1px solid #FCA5A5;
            color: #DC2626;
            padding: 0.875rem 1rem;
            border-radius: 12px;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
        }
        .error-banner .material-symbols-outlined {
            font-size: 1.25rem;
            flex-shrink: 0;
            margin-top: 0.0625rem;
        }

        /* Password toggle */
        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94A3B8;
            cursor: pointer;
            padding: 0.375rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .password-toggle:hover {
            background: #F1F5F9;
            color: #475569;
        }
        .password-toggle .material-symbols-outlined {
            font-size: 1.25rem;
        }

        /* Checkbox custom */
        .custom-checkbox {
            appearance: none;
            width: 1.125rem;
            height: 1.125rem;
            border: 2px solid #CBD5E1;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
            position: relative;
            background: white;
        }
        .custom-checkbox:checked {
            background: #10B981;
            border-color: #10B981;
        }
        .custom-checkbox:checked::after {
            content: 'check';
            font-family: 'Material Symbols Outlined';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.75rem;
            color: white;
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 20;
        }
        .custom-checkbox:focus {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        @media (max-width: 768px) {
            .login-glass {
                margin: 1rem;
            }
        }
    </style>
</head>
<body class="bg-animated relative overflow-hidden">
    <!-- Floating orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- Grid overlay -->
    <div class="grid-overlay absolute inset-0"></div>

    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="flex w-full max-w-[1000px] min-h-[580px] rounded-3xl overflow-hidden shadow-2xl">
            <!-- Left Panel - Brand -->
            <div class="hidden md:flex w-[45%] bg-gradient-to-br from-emerald-950 via-teal-950 to-slate-950 relative overflow-hidden">
                <div class="dots-pattern absolute inset-0"></div>
                <div class="relative z-10 flex flex-col justify-between p-10 w-full">
                    <div class="flex items-center gap-3">
                        <div class="bg-white p-2.5 rounded-xl shadow-lg shadow-black/10">
                            <img src="/logo.png" alt="PetHeal" class="h-7 w-auto block" fetchpriority="high" width="140" height="28">
                        </div>
                        <span class="font-bold text-xl text-white tracking-tight">PetHeal</span>
                    </div>

                    <div class="space-y-4">
                        <h2 class="text-3xl font-bold text-white leading-tight tracking-tight">
                            Welcome back,<br>
                            <span class="text-emerald-400">Admin</span>
                        </h2>
                        <p class="text-slate-400 text-sm leading-relaxed max-w-[280px]">
                            Access your clinic dashboard to manage appointments, medical records, and patient care.
                        </p>
                    </div>

                    <div class="flex items-center gap-6 text-slate-500">
                        <div class="flex items-center gap-2 text-xs">
                            <span class="material-symbols-outlined text-emerald-400 text-base">verified</span>
                            Secure Access
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="material-symbols-outlined text-emerald-400 text-base">shield</span>
                            Encrypted
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Form -->
            <div class="w-full md:w-[55%] login-glass flex items-center justify-center p-8 md:p-12">
                <div class="w-full max-w-sm">
                    <!-- Mobile logo (visible only on small screens) -->
                    <div class="flex md:hidden items-center gap-3 mb-8">
                        <div class="bg-emerald-100 p-2 rounded-xl">
                            <img src="/logo.png" alt="PetHeal" class="h-7 w-auto block" fetchpriority="high" width="140" height="28">
                        </div>
                        <span class="font-bold text-lg text-slate-900 tracking-tight">PetHeal</span>
                    </div>

                    <!-- Form Header -->
                    <div class="fade-up mb-8">
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Sign In</h1>
                        <p class="text-slate-500 text-sm mt-1.5">Enter your credentials to access the dashboard.</p>
                    </div>

                    <!-- Error Messages -->
                    <div class="fade-up">
                        @if($errors->any())
                            <div class="error-banner mb-6">
                                <span class="material-symbols-outlined">error</span>
                                <div>
                                    @foreach($errors->all() as $error)
                                        <p class="mb-0.5 last:mb-0">{{ $error }}</p>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Login Form -->
                    <form method="POST" action="{{ route('admin.login.post') }}" id="loginForm">
                        @csrf

                        <div class="fade-up space-y-5">
                            <!-- Email -->
                            <div class="form-group">
                                <label for="email" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address</label>
                                <div class="relative">
                                    <span class="material-symbols-outlined input-icon">mail</span>
                                    <input type="email"
                                           id="email"
                                           name="email"
                                           value="{{ old('email') }}"
                                           required
                                           autofocus
                                           autocomplete="email"
                                           class="form-input"
                                           placeholder="admin@petheal.com">
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="form-group">
                                <label for="password" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Password</label>
                                <div class="relative">
                                    <span class="material-symbols-outlined input-icon">lock</span>
                                    <input type="password"
                                           id="password"
                                           name="password"
                                           required
                                           autocomplete="current-password"
                                           class="form-input"
                                           placeholder="Enter your password">
                                    <button type="button"
                                            onclick="togglePassword()"
                                            class="password-toggle"
                                            aria-label="Toggle password visibility">
                                        <span class="material-symbols-outlined" id="eyeIcon">visibility_off</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Remember Me & Submit -->
                        <div class="fade-up mt-6 space-y-5">
                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                <input type="checkbox" id="remember" name="remember" class="custom-checkbox">
                                <span class="text-sm text-slate-600 group-hover:text-slate-700 transition-colors">Remember me for 30 days</span>
                            </label>

                            <button type="submit" class="btn-primary" id="submitBtn">
                                <span class="spinner"></span>
                                <span class="btn-label flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">login</span>
                                    Sign In
                                </span>
                            </button>
                        </div>
                    </form>

                    <!-- Footer -->
                    <div class="fade-up mt-8 pt-6 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <a href="{{ url('/') }}" class="text-xs text-slate-400 hover:text-emerald-600 transition-colors flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">arrow_back</span>
                                Back to Homepage
                            </a>
                            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-xs">lock</span>
                                Restricted Area
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            if (password.type === 'password') {
                password.type = 'text';
                eyeIcon.textContent = 'visibility';
            } else {
                password.type = 'password';
                eyeIcon.textContent = 'visibility_off';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
</body>
</html>
