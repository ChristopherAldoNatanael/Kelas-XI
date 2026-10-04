<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Gabung Klinik</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><g fill='%2318C964'><circle cx='5.6' cy='10.2' r='2'/><circle cx='9.4' cy='5.6' r='2.3'/><circle cx='14.6' cy='5.6' r='2.3'/><circle cx='18.4' cy='10.2' r='2'/><path d='M12 11.2c-2.9 0-5.6 2.4-5.6 5.1 0 1.7 1.3 2.9 2.9 2.9 1 0 1.7-.5 2.7-.5s1.7.5 2.7.5c1.6 0 2.9-1.2 2.9-2.9 0-2.7-2.7-5.1-5.6-5.1z'/></g></svg>">
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
           LEFT: form panel
           ============================ */
        .form-panel {
            flex: 1.15;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2.5rem 3rem 3rem;
            background: #fff;
            overflow-y: auto;
            animation: panelSlideIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes panelSlideIn {
            from { opacity: 0; transform: translateX(-24px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .form-box { width: 100%; max-width: 480px; margin: auto 0; }

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
        .form-header .eyebrow svg { width: 12px; height: 12px; display: block; }
        .success-box svg { flex-shrink: 0; margin-top: 2px; }
        .mobile-logo span { font-size: 1.1rem; font-weight: 800; color: #1a1a1a; letter-spacing: -0.02em; }

        /* step progress */
        .steps {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.75rem;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #A8A29E;
        }
        .step .num {
            width: 24px; height: 24px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: #F5F5F4;
            border: 1px solid #E7E5E4;
            font-size: 0.7rem;
            transition: all 0.25s;
        }
        .step.active { color: #1B2A1E; }
        .step.active .num {
            background: #1B2A1E;
            border-color: #1B2A1E;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(27, 42, 30, 0.1);
        }
        .step.done { color: #15803D; }
        .step.done .num { background: #DCFCE7; border-color: #86EFAC; color: #15803D; }
        .step-line { flex: 1; height: 2px; background: #E7E5E4; border-radius: 2px; overflow: hidden; }
        .step-line span { display: block; height: 100%; width: 0; background: #1B2A1E; border-radius: 2px; transition: width 0.4s cubic-bezier(0.22, 1, 0.36, 1); }

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

        /* clinic cards */
        .clinic-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }
        .clinic-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 0.9rem 1rem;
            border: 1.5px solid #E7E5E4;
            border-radius: 14px;
            background: #FAFAF9;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s, transform 0.15s;
        }
        .clinic-card:hover { border-color: #A8A29E; background: #fff; transform: translateY(-1px); }
        .clinic-card input { position: absolute; opacity: 0; pointer-events: none; }
        .clinic-card:has(input:checked), .clinic-card.selected {
            border-color: #1B2A1E;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(27, 42, 30, 0.1), 0 8px 20px -10px rgba(27, 42, 30, 0.25);
        }
        .clinic-card:has(input:focus-visible) { outline: 2px solid #1B2A1E; outline-offset: 2px; }
        .clinic-avatar {
            width: 48px; height: 48px;
            border-radius: 14px;
            position: relative;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; font-weight: 800; color: #fff;
            flex-shrink: 0;
            overflow: hidden;
            box-shadow: inset 0 -2px 6px rgba(0,0,0,0.15);
        }
        .clinic-avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .clinic-info { flex: 1; min-width: 0; }
        .clinic-info .name { font-size: 0.9rem; font-weight: 700; color: #111; letter-spacing: -0.01em; }
        .clinic-info .addr {
            font-size: 0.75rem; color: #78716C; margin-top: 0.15rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .clinic-info .dot-color {
            display: inline-block; width: 8px; height: 8px; border-radius: 50%;
            margin-right: 0.3rem; vertical-align: baseline;
        }
        .clinic-check {
            width: 24px; height: 24px;
            border-radius: 50%;
            border: 2px solid #D6D3D1;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s;
            color: transparent;
        }
        .clinic-card:has(input:checked) .clinic-check, .clinic-card.selected .clinic-check {
            background: #1B2A1E; border-color: #1B2A1E; color: #fff;
        }
        .clinic-hint { font-size: 0.75rem; color: #A8A29E; margin: 0.6rem 0 0; }
        .clinic-hint.shake { color: #DC2626; animation: shake 0.4s; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

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
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
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

        /* password strength */
        .pw-meter { display: flex; gap: 0.3rem; margin-top: 0.5rem; }
        .pw-meter span { flex: 1; height: 4px; border-radius: 2px; background: #E7E5E4; transition: background 0.25s; }
        .pw-meter.l1 span:nth-child(1) { background: #EF4444; }
        .pw-meter.l2 span:nth-child(-n+2) { background: #F97316; }
        .pw-meter.l3 span:nth-child(-n+3) { background: #EAB308; }
        .pw-meter.l4 span:nth-child(-n+4) { background: #22C55E; }
        .pw-meter.l5 span { background: #15803D; }
        .pw-text { font-size: 0.72rem; margin-top: 0.3rem; color: #78716C; min-height: 1rem; }
        .match-ok { border-color: #86EFAC !important; }
        .match-bad { border-color: #FCA5A5 !important; }

        /* chosen clinic summary in step 2 */
        .chosen-clinic {
            display: flex; align-items: center; gap: 0.7rem;
            background: #F0FDF4; border: 1px solid #BBF7D0;
            border-radius: 12px; padding: 0.65rem 0.85rem; margin-bottom: 1.25rem;
            font-size: 0.8rem; color: #166534;
        }
        .chosen-clinic .mini-avatar {
            width: 32px; height: 32px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; color: #fff; font-size: 0.95rem; flex-shrink: 0;
            overflow: hidden;
        }
        .chosen-clinic .mini-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .chosen-clinic button {
            margin-left: auto; background: none; border: none; cursor: pointer;
            color: #15803D; font-weight: 700; font-size: 0.75rem; font-family: inherit;
            text-decoration: underline; text-underline-offset: 2px; white-space: nowrap;
        }

        /* buttons */
        .btn-row { display: flex; gap: 0.75rem; margin-top: 0.25rem; }
        .btn-submit {
            flex: 1; padding: 0.75rem; font-size: 0.9rem; font-weight: 700;
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
        .btn-ghost {
            padding: 0.75rem 1.25rem; font-size: 0.9rem; font-weight: 600;
            font-family: inherit; color: #44403C; background: #F5F5F4;
            border: 1px solid #E7E5E4; border-radius: 12px; cursor: pointer;
            transition: background 0.15s;
        }
        .btn-ghost:hover { background: #E7E5E4; }

        .form-footer {
            margin-top: 1.75rem; padding-top: 1.25rem;
            border-top: 1px solid #E5E7EB; text-align: center;
        }
        .form-footer p { font-size: 0.8125rem; color: #6B7280; }
        .form-footer a { color: #1B2A1E; text-decoration: none; font-weight: 700; }
        .form-footer a:hover { text-decoration: underline; }

        fieldset { border: none; }
        fieldset.hidden { display: none; }
        fieldset.step-enter { animation: stepIn 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
        @keyframes stepIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* ============================
           RIGHT: brand panel
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
            animation: brandSlideIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.1s both;
        }
        @keyframes brandSlideIn {
            from { opacity: 0; transform: translateX(24px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .brand-panel::before {
            content: ''; position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='20' cy='20' r='1'/%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        .blob { position: absolute; border-radius: 50%; filter: blur(90px); opacity: 0.35; pointer-events: none; }
        .blob-1 { width: 340px; height: 340px; background: #22C55E; top: -120px; right: -100px; animation: drift 9s ease-in-out infinite alternate; }
        .blob-2 { width: 260px; height: 260px; background: #0EA5A5; bottom: -100px; left: -80px; animation: drift 11s ease-in-out infinite alternate-reverse; }
        @keyframes drift {
            from { transform: translate(0, 0) scale(1); }
            to { transform: translate(-30px, 30px) scale(1.08); }
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

        .brand-stats {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.7rem;
            margin-top: 1.6rem; position: relative; z-index: 1;
        }
        .stat-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 0.8rem 0.9rem;
            backdrop-filter: blur(6px);
        }
        .stat-card strong { display: block; font-size: 1.15rem; font-weight: 800; color: #fff; letter-spacing: -0.02em; }
        .stat-card span { font-size: 0.68rem; color: #9CA3AF; line-height: 1.4; display: block; margin-top: 0.15rem; }

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

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .brand-panel { display: none; }
            .form-panel { padding: 2rem 1.25rem 3rem; }
            .mobile-logo { display: flex; }
            .row-2 { grid-template-columns: 1fr; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>
    <!-- LEFT: form -->
    <div class="form-panel">
        <div class="form-box">
            <div class="mobile-logo">
                <span class="paw-badge"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5.6" cy="10.2" r="2"/><circle cx="9.4" cy="5.6" r="2.3"/><circle cx="14.6" cy="5.6" r="2.3"/><circle cx="18.4" cy="10.2" r="2"/><path d="M12 11.2c-2.9 0-5.6 2.4-5.6 5.1 0 1.7 1.3 2.9 2.9 2.9 1 0 1.7-.5 2.7-.5s1.7.5 2.7.5c1.6 0 2.9-1.2 2.9-2.9 0-2.7-2.7-5.1-5.6-5.1z"/></svg></span>
                <span>Klinik Hewan</span>
            </div>

            <div class="steps" aria-label="Langkah pendaftaran">
                <div class="step active" id="stepDot1"><span class="num">1</span> Klinik</div>
                <div class="step-line"><span id="stepBar" style="width: 50%"></span></div>
                <div class="step" id="stepDot2"><span class="num">2</span> Data diri</div>
            </div>

            <div class="form-header">
                <span class="eyebrow"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5.6" cy="10.2" r="2"/><circle cx="9.4" cy="5.6" r="2.3"/><circle cx="14.6" cy="5.6" r="2.3"/><circle cx="18.4" cy="10.2" r="2"/><path d="M12 11.2c-2.9 0-5.6 2.4-5.6 5.1 0 1.7 1.3 2.9 2.9 2.9 1 0 1.7-.5 2.7-.5s1.7.5 2.7.5c1.6 0 2.9-1.2 2.9-2.9 0-2.7-2.7-5.1-5.6-5.1z"/></svg> Pendaftaran Admin Klinik</span>
                <h1 id="formTitle">Pilih klinik yang ingin Anda kelola</h1>
                <p id="formSubtitle">Setiap klinik punya data terpisah — dokter, layanan, dan booking tidak tercampur antar klinik.</p>
            </div>

            @if(session('success'))
                <div class="success-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><polyline points="22 4 12 14 9 11"/></svg><span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="error-box">
                    <strong>Periksa kembali isian Anda:</strong>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.register.post') }}" id="joinForm" novalidate>
                @csrf

                <!-- STEP 1: pilih klinik -->
                <fieldset id="step1">
                    <div class="clinic-grid" role="radiogroup" aria-label="Pilih klinik">
                        @forelse($clinics as $clinic)
                            @php $isOld = (string) old('clinic_id') === (string) $clinic->id; @endphp
                            <label class="clinic-card {{ $isOld ? 'selected' : '' }}" data-name="{{ $clinic->name }}" data-color="{{ $clinic->primary_color ?? '#18C964' }}" data-logo="{{ $clinic->logo_url ?? '' }}">
                                <input type="radio" name="clinic_id" value="{{ $clinic->id }}" {{ $isOld ? 'checked' : '' }} required>
                                <span class="clinic-avatar" style="background: linear-gradient(135deg, {{ $clinic->primary_color ?? '#18C964' }}, {{ $clinic->primary_color ?? '#18C964' }}AA)">
                                    {{ strtoupper(substr($clinic->name, 0, 1)) }}
                                    @if($clinic->logo_url)
                                        <img src="{{ $clinic->logo_url }}" alt="Logo {{ $clinic->name }}" loading="lazy" onerror="this.remove()">
                                    @endif
                                </span>
                                <span class="clinic-info">
                                    <span class="name">{{ $clinic->name }}</span>
                                    <span class="addr" style="display:block"><span class="dot-color" style="background: {{ $clinic->primary_color ?? '#18C964' }}"></span>{{ $clinic->address }}</span>
                                </span>
                                <span class="clinic-check">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </span>
                            </label>
                        @empty
                            <div class="error-box">Belum ada klinik aktif. Hubungi super admin terlebih dahulu.</div>
                        @endforelse
                    </div>
                    @error('clinic_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                    <p class="clinic-hint" id="clinicHint">{{ $clinics->count() }} klinik aktif tersedia — permintaan Anda akan ditinjau super admin (biasanya &lt; 24 jam).</p>

                    <div class="btn-row">
                        <button type="button" class="btn-submit" id="toStep2">
                            <span class="btn-text">Lanjut ke data diri →</span>
                        </button>
                    </div>
                </fieldset>

                <!-- STEP 2: data diri -->
                <fieldset id="step2" class="hidden">
                    <div class="chosen-clinic" id="chosenBox" style="display:none">
                        <span class="mini-avatar" id="chosenAvatar">?</span>
                        <span><strong id="chosenName">—</strong><br><span style="font-size:0.72rem; opacity:0.85">Klinik tujuan permintaan</span></span>
                        <button type="button" id="changeClinic">Ganti</button>
                    </div>

                    <div class="row-2">
                        <div class="input-wrap @error('name') invalid @enderror">
                            <label for="name" class="field-label">Nama lengkap</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Nama Anda">
                            @error('name')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="input-wrap @error('phone') invalid @enderror">
                            <label for="phone" class="field-label">Telepon</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="0812xxxx">
                            @error('phone')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="input-wrap @error('email') invalid @enderror">
                        <label for="email" class="field-label">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="nama@email.com">
                        @error('email')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="input-wrap @error('password') invalid @enderror">
                        <label for="password" class="field-label">Password</label>
                        <div class="pw-field">
                        <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="Minimal 8 karakter" style="padding-right: 2.75rem">
                        <button type="button" class="pw-toggle" onclick="togglePw('password', this)" aria-label="Tampilkan password">
                            <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                            <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        </div>
                        <div class="pw-meter" id="pwMeter"><span></span><span></span><span></span><span></span><span></span></div>
                        <div class="pw-text" id="pwText"></div>
                        @error('password')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="input-wrap">
                        <label for="password_confirmation" class="field-label">Konfirmasi password</label>
                        <div class="pw-field">
                        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" placeholder="Ulangi password" style="padding-right: 2.75rem">
                        <button type="button" class="pw-toggle" onclick="togglePw('password_confirmation', this)" aria-label="Tampilkan password">
                            <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                            <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        </div>
                        <div class="pw-text" id="matchText"></div>
                    </div>

                    <div class="btn-row">
                        <button type="button" class="btn-ghost" id="backToStep1">← Kembali</button>
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <div class="spinner"></div>
                            <span class="btn-text">Kirim permintaan</span>
                        </button>
                    </div>
                </fieldset>
            </form>

            <div class="form-footer">
                <p>Sudah punya akun? <a href="{{ route('admin.login') }}">Login di sini</a></p>
            </div>
        </div>
    </div>

    <!-- RIGHT: brand -->
    <div class="brand-panel">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>

        <div class="brand-logo">
            <span class="logo-badge"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5.6" cy="10.2" r="2"/><circle cx="9.4" cy="5.6" r="2.3"/><circle cx="14.6" cy="5.6" r="2.3"/><circle cx="18.4" cy="10.2" r="2"/><path d="M12 11.2c-2.9 0-5.6 2.4-5.6 5.1 0 1.7 1.3 2.9 2.9 2.9 1 0 1.7-.5 2.7-.5s1.7.5 2.7.5c1.6 0 2.9-1.2 2.9-2.9 0-2.7-2.7-5.1-5.6-5.1z"/></svg></span>
            <span>Klinik Hewan<small>Jaringan Klinik</small></span>
        </div>

        <div class="brand-content">
            <h2>Bergabung dengan <span>klinik hewan</span> Anda</h2>
            <p>Pilih klinik yang ingin Anda kelola. Data tiap klinik terisolasi penuh — dokter, layanan, dan booking tidak tercampur.</p>

            <ul class="brand-features">
                <li>
                    <span class="icon-circle">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#86EFAC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-4h6v4"/></svg>
                    </span>
                    Satu akun untuk satu klinik
                </li>
                <li>
                    <span class="icon-circle">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#86EFAC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                    </span>
                    Diverifikasi super admin &lt; 24 jam
                </li>
                <li>
                    <span class="icon-circle">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#86EFAC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </span>
                    Kelola jadwal, rekam medis & pembayaran
                </li>
            </ul>

            <div class="brand-stats">
                <div class="stat-card"><strong>{{ $clinics->count() }}</strong><span>Klinik aktif</span></div>
                <div class="stat-card"><strong>&lt;24j</strong><span>Proses verifikasi</span></div>
                <div class="stat-card"><strong>100%</strong><span>Gratis, tanpa biaya</span></div>
            </div>
        </div>

        <div class="brand-footer">
            <div class="badge"><div class="dot"></div> Koneksi terenkripsi</div>
            <div class="badge"><div class="dot"></div> Data terisolasi per klinik</div>
        </div>
    </div>

    <script>
        // ---------- wizard ----------
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const dot1 = document.getElementById('stepDot1');
        const dot2 = document.getElementById('stepDot2');
        const bar = document.getElementById('stepBar');
        const title = document.getElementById('formTitle');
        const subtitle = document.getElementById('formSubtitle');
        const hint = document.getElementById('clinicHint');

        function showStep(n) {
            const toTwo = n === 2;
            step1.classList.toggle('hidden', toTwo);
            step2.classList.toggle('hidden', !toTwo);
            (toTwo ? step2 : step1).classList.remove('step-enter');
            void (toTwo ? step2 : step1).offsetWidth;
            (toTwo ? step2 : step1).classList.add('step-enter');
            dot1.classList.toggle('active', !toTwo);
            dot1.classList.toggle('done', toTwo);
            dot1.querySelector('.num').innerHTML = toTwo
                ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
                : '1';
            dot2.classList.toggle('active', toTwo);
            bar.style.width = toTwo ? '100%' : '50%';
            title.textContent = toTwo ? 'Lengkapi data diri Anda' : 'Pilih klinik yang ingin Anda kelola';
            subtitle.textContent = toTwo
                ? 'Data ini dipakai super admin untuk memverifikasi permintaan Anda.'
                : 'Setiap klinik punya data terpisah — dokter, layanan, dan booking tidak tercampur antar klinik.';
            if (toTwo) { updateChosen(); document.getElementById('name').focus(); }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.getElementById('toStep2').addEventListener('click', function () {
            if (!document.querySelector('input[name="clinic_id"]:checked')) {
                hint.classList.remove('shake');
                void hint.offsetWidth;
                hint.classList.add('shake');
                hint.textContent = 'Silakan pilih salah satu klinik terlebih dahulu.';
                return;
            }
            showStep(2);
        });
        document.getElementById('backToStep1').addEventListener('click', () => showStep(1));
        document.getElementById('changeClinic').addEventListener('click', () => showStep(1));

        // ---------- clinic card select + summary ----------
        const cards = document.querySelectorAll('.clinic-card');
        function updateChosen() {
            const checked = document.querySelector('input[name="clinic_id"]:checked');
            const box = document.getElementById('chosenBox');
            if (!checked) { box.style.display = 'none'; return; }
            const card = checked.closest('.clinic-card');
            box.style.display = 'flex';
            document.getElementById('chosenName').textContent = card.dataset.name;
            const avatar = document.getElementById('chosenAvatar');
            const logo = card.dataset.logo;
            const color = card.dataset.color || '#18C964';
            if (logo) {
                avatar.innerHTML = '';
                const img = document.createElement('img');
                img.src = logo; img.alt = card.dataset.name;
                avatar.appendChild(img);
                avatar.style.background = '#fff';
            } else {
                avatar.textContent = card.dataset.name.charAt(0).toUpperCase();
                avatar.style.background = 'linear-gradient(135deg,' + color + ',' + color + 'AA)';
            }
        }
        cards.forEach(card => {
            card.addEventListener('click', () => {
                cards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
            });
            card.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.querySelector('input').checked = true; card.click(); }
            });
            card.setAttribute('tabindex', '0');
        });

        // jika server me-render ulang karena error validasi dan sudah ada pilihan / sedang di step 2
        @if(old('clinic_id') || $errors->has('name') || $errors->has('email') || $errors->has('password'))
            showStep(2);
        @endif
        updateChosen();

        // ---------- password toggle ----------
        function togglePw(fieldId, btn) {
            const el = document.getElementById(fieldId);
            el.type = el.type === 'password' ? 'text' : 'password';
            btn.classList.toggle('active', el.type === 'text');
        }

        // ---------- password strength + match ----------
        const pw = document.getElementById('password');
        const meter = document.getElementById('pwMeter');
        const pwText = document.getElementById('pwText');
        const confirm = document.getElementById('password_confirmation');
        const matchText = document.getElementById('matchText');
        const labels = ['', 'Sangat lemah', 'Lemah', 'Cukup', 'Kuat', 'Sangat kuat'];

        function score(v) {
            let s = 0;
            if (v.length >= 8) s++;
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
            if (/\d/.test(v)) s++;
            if (/[^A-Za-z0-9]/.test(v)) s++;
            if (v.length >= 12) s++;
            return Math.min(s, 5);
        }
        function checkMatch() {
            confirm.classList.remove('match-ok', 'match-bad');
            matchText.textContent = '';
            matchText.style.color = '';
            if (!confirm.value) return;
            if (confirm.value === pw.value && pw.value.length >= 8) {
                confirm.classList.add('match-ok');
                matchText.textContent = '✓ Password cocok';
                matchText.style.color = '#15803D';
            } else if (confirm.value.length >= pw.value.length && pw.value) {
                confirm.classList.add('match-bad');
                matchText.textContent = '✗ Password belum sama';
                matchText.style.color = '#DC2626';
            }
        }
        pw.addEventListener('input', () => {
            const v = pw.value, s = score(v);
            meter.className = 'pw-meter' + (v ? ' l' + s : '');
            pwText.textContent = v ? labels[s] : '';
            pwText.style.color = s <= 2 ? '#DC2626' : (s === 3 ? '#CA8A04' : '#15803D');
            checkMatch();
        });
        confirm.addEventListener('input', checkMatch);

        // ---------- submit loading ----------
        document.getElementById('joinForm').addEventListener('submit', function (e) {
            if (!document.querySelector('input[name="clinic_id"]:checked')) {
                e.preventDefault();
                showStep(1);
                hint.classList.add('shake');
                hint.textContent = 'Silakan pilih salah satu klinik terlebih dahulu.';
                return;
            }
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
</body>
</html>
