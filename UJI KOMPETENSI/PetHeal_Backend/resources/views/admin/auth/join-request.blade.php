<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Klinik Hewan</title>
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
        .input-wrap.invalid input, .input-wrap.invalid textarea { border-color: #FCA5A5; background: #FEF2F2; }
        .input-wrap.invalid input:focus, .input-wrap.invalid textarea:focus { border-color: #DC2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }
        .mode-tabs.hidden { display: none; }

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
        .input-wrap textarea {
            width: 100%; padding: 0.7rem 0.9rem; font-size: 0.9rem;
            font-family: inherit; color: #111; background: #FAFAF9;
            border: 1px solid #D6D3D1; border-radius: 10px; outline: none;
            resize: vertical; min-height: 74px; line-height: 1.55;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        }
        .input-wrap textarea::placeholder { color: #A8A29E; }
        .input-wrap textarea:focus {
            border-color: #1B2A1E; box-shadow: 0 0 0 3px rgba(27, 42, 30, 0.1); background: #fff;
        }
        .input-wrap input[type="color"] {
            padding: 0.2rem; height: 44px; cursor: pointer;
        }
        .field-hint { font-size: 0.72rem; color: #78716C; margin-top: 0.3rem; line-height: 1.5; }
        .mode-tabs {
            display: grid; grid-template-columns: 1fr 1fr; gap: 0.3rem;
            background: #F5F5F4; border: 1px solid #E7E5E4;
            border-radius: 12px; padding: 0.3rem; margin-bottom: 1.35rem;
        }
        .mode-tabs label {
            position: relative; display: flex; align-items: center; justify-content: center;
            padding: 0.55rem 0.4rem; border-radius: 9px; text-align: center;
            font-size: 0.78rem; font-weight: 700; color: #78716C; cursor: pointer;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .mode-tabs input { position: absolute; opacity: 0; pointer-events: none; }
        .mode-tabs label:has(input:checked) {
            background: #fff; color: #1B2A1E;
            box-shadow: 0 2px 8px -4px rgba(0, 0, 0, 0.25);
        }
        .mode-tabs label:has(input:focus-visible) { outline: 2px solid #1B2A1E; outline-offset: 2px; }
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

        /* verification step */
        .warning-box {
            background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E;
            padding: 0.8rem 1rem; border-radius: 12px; font-size: 0.8125rem;
            line-height: 1.55; margin-bottom: 1.25rem;
        }

        /* status permintaan klinik baru */
        .status-icon {
            width: 62px; height: 62px; border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.1rem;
        }
        .status-icon.pending { background: #FFFBEB; border: 1px solid #FDE68A; color: #B45309; }
        .status-icon.approved { background: #F0FDF4; border: 1px solid #BBF7D0; color: #15803D; }
        .status-icon.rejected { background: #FEF2F2; border: 1px solid #FECACA; color: #B91C1C; }
        .status-summary {
            display: flex; align-items: center; gap: 0.7rem; text-align: left;
            background: #FAFAF9; border: 1px solid #E7E5E4;
            border-radius: 12px; padding: 0.8rem 0.9rem; margin-bottom: 1rem;
        }
        .status-summary .mini-avatar {
            width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; color: #fff; font-size: 1rem; overflow: hidden;
        }
        .status-summary strong { display: block; font-size: 0.87rem; color: #111; }
        .status-summary small { display: block; font-size: 0.72rem; color: #78716C; margin-top: 0.12rem; word-break: break-all; }
        .status-note {
            background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px;
            padding: 0.75rem 0.9rem; font-size: 0.8rem; line-height: 1.6;
            color: #475569; text-align: left; margin-bottom: 1.25rem;
        }
        .status-note.rose { background: #FEF2F2; border-color: #FECACA; color: #991B1B; }
        a.btn-submit { text-decoration: none; }
        .status-footnote { text-align: center; font-size: 0.75rem; color: #A8A29E; margin-top: 0.9rem; }
        .otp-intro {
            background: #FAFAF9; border: 1px solid #E7E5E4; border-radius: 12px;
            padding: 0.9rem 1rem; margin-bottom: 1.25rem;
        }
        .otp-intro p { font-size: 0.8125rem; line-height: 1.65; color: #57534E; }
        .otp-intro strong { color: #1B2A1E; font-weight: 700; word-break: break-all; }
        .otp-row { display: flex; gap: 0.5rem; margin: 0.35rem 0 0.4rem; }
        .otp-row input {
            flex: 1; min-width: 0; height: 58px; text-align: center;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 1.35rem; font-weight: 700; color: #111;
            background: #FAFAF9; border: 1.5px solid #D6D3D1; border-radius: 12px;
            outline: none; transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
            -moz-appearance: textfield;
        }
        .otp-row input::-webkit-outer-spin-button,
        .otp-row input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .otp-row input:focus {
            border-color: #1B2A1E; background: #fff;
            box-shadow: 0 0 0 3px rgba(27, 42, 30, 0.1);
        }
        .otp-row input.filled { border-color: #1B2A1E; background: #fff; }
        .otp-row input.invalid { border-color: #FCA5A5; background: #FEF2F2; }
        .otp-meta { font-size: 0.75rem; line-height: 1.6; color: #78716C; margin-top: 0.5rem; }
        .resend-row {
            display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem;
            margin-top: 1.1rem; font-size: 0.8125rem; color: #6B7280;
        }
        .resend-row form { display: inline; margin: 0; }
        .link-btn {
            background: none; border: none; padding: 0; cursor: pointer;
            font-family: inherit; font-size: 0.8125rem; font-weight: 700;
            color: #1B2A1E; text-decoration: underline; text-underline-offset: 2px;
        }
        .link-btn:disabled { color: #A8A29E; cursor: default; text-decoration: none; }
        .link-btn:hover:not(:disabled) { color: #15803D; }
        .step.done .num svg { display: block; }

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

            @php
                $pending = $pending ?? null;
                $submitted = $submitted ?? null;
                $mode = $mode ?? 'join';
                $initialMode = old('registration_type', $mode) === 'new_clinic' ? 'new_clinic' : 'join';
                $allDone = $pending || $submitted;
            @endphp
            <div class="steps" aria-label="Langkah pendaftaran">
                <div class="step {{ $allDone ? 'done' : 'active' }}" id="stepDot1"><span class="num">{{ $allDone ? '✓' : '1' }}</span> <span id="stepLabel1">{{ $initialMode === 'new_clinic' ? 'Data klinik' : 'Klinik' }}</span></div>
                <div class="step-line"><span id="stepBar" style="width: {{ $allDone ? '100%' : '0%' }}"></span></div>
                <div class="step {{ $allDone ? 'done' : '' }}" id="stepDot2"><span class="num">{{ $allDone ? '✓' : '2' }}</span> <span id="stepLabel2">{{ $initialMode === 'new_clinic' ? 'Data pemilik' : 'Data diri' }}</span></div>
                <div class="step-line"><span id="stepBar2" style="width: {{ $allDone ? '100%' : '0%' }}"></span></div>
                <div class="step {{ $pending ? 'active' : ($submitted ? 'done' : '') }}" id="stepDot3"><span class="num">{{ $submitted ? '✓' : '3' }}</span> Verifikasi</div>
            </div>

            <div class="form-header">
                <span class="eyebrow"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5.6" cy="10.2" r="2"/><circle cx="9.4" cy="5.6" r="2.3"/><circle cx="14.6" cy="5.6" r="2.3"/><circle cx="18.4" cy="10.2" r="2"/><path d="M12 11.2c-2.9 0-5.6 2.4-5.6 5.1 0 1.7 1.3 2.9 2.9 2.9 1 0 1.7-.5 2.7-.5s1.7.5 2.7.5c1.6 0 2.9-1.2 2.9-2.9 0-2.7-2.7-5.1-5.6-5.1z"/></svg> Pendaftaran Admin Klinik</span>
                <h1 id="formTitle">
                    @if($submitted)
                        {{ $submitted['status'] === 'approved' ? 'Klinik Anda sudah aktif' : ($submitted['status'] === 'rejected' ? 'Pendaftaran belum disetujui' : 'Permintaan sedang ditinjau') }}
                    @elseif($pending)
                        Verifikasi email admin
                    @else
                        {{ $initialMode === 'new_clinic' ? 'Daftarkan klinik baru Anda' : 'Pilih klinik yang ingin Anda kelola' }}
                    @endif
                </h1>
                <p id="formSubtitle">
                    @if($submitted)
                        {{ $submitted['status'] === 'pending'
                            ? 'Tidak ada yang perlu Anda lakukan lagi. Super admin akan meninjau permintaan ini.'
                            : ($submitted['status'] === 'approved'
                                ? 'Akun admin sudah dibuat. Silakan masuk memakai email dan kata sandi Anda.'
                                : 'Tidak ada klinik atau akun yang dibuat dari permintaan ini.') }}
                    @elseif($pending)
                        Satu langkah lagi sebelum permintaan Anda diteruskan ke super admin.
                    @else
                        {{ $initialMode === 'new_clinic'
                            ? 'Isi data klinik lalu data pemilik. Klinik baru dibuat setelah super admin menyetujui.'
                            : 'Setiap klinik punya data terpisah — dokter, layanan, dan booking tidak tercampur antar klinik.' }}
                    @endif
                </p>
            </div>

            @if(session('success'))
                <div class="success-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><polyline points="22 4 12 14 9 11"/></svg><span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('warning'))
                <div class="warning-box">{{ session('warning') }}</div>
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

            @if($submitted)
                {{-- Status permintaan klinik baru. Halaman yang sama dengan form,
                     supaya tidak ada halaman status kedua yang membingungkan. --}}
                <div class="status-icon {{ $submitted['status'] }}">
                    @if($submitted['status'] === 'approved')
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><polyline points="22 4 12 14 9 11"/></svg>
                    @elseif($submitted['status'] === 'rejected')
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
                    @else
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    @endif
                </div>

                <div class="status-summary">
                    <span class="mini-avatar" style="background: {{ $submitted['clinic_color'] }}">
                        {{ strtoupper(substr($submitted['clinic_name'], 0, 1)) }}
                    </span>
                    <span style="min-width:0">
                        <strong>{{ $submitted['clinic_name'] }}</strong>
                        <small>{{ $submitted['email'] }} · dikirim {{ $submitted['submitted_at']?->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</small>
                    </span>
                </div>

                @if($submitted['status'] === 'pending')
                    <div class="status-note">
                        Permintaan Anda sudah masuk ke super admin. Setelah disetujui, kami mengirim email berisi tautan login ke alamat di atas.
                        Tidak perlu mengirim ulang formulir atau menghubungi super admin.
                    </div>
                @elseif($submitted['status'] === 'approved')
                    <div class="status-note">
                        Klinik dan akun admin sudah dibuat. Masuk memakai email <strong>{{ $submitted['email'] }}</strong> dan kata sandi yang Anda buat saat mendaftar.
                    </div>
                @else
                    <div class="status-note rose">
                        <strong>Permintaan ini ditolak.</strong>
                        @if(filled($submitted['review_note']))
                            <br>{{ $submitted['review_note'] }}
                        @endif
                    </div>
                @endif

                <div class="btn-row" style="margin-top:0.25rem">
                    @if($submitted['status'] === 'rejected')
                        <form method="POST" action="{{ route('admin.register.cancel') }}" style="flex:1">
                            @csrf
                            <button type="submit" class="btn-submit" style="width:100%">
                                <span class="btn-text">Daftar ulang</span>
                            </button>
                        </form>
                        <a href="{{ route('admin.login') }}" class="btn-ghost">Ke halaman login</a>
                    @else
                        <a href="{{ route('admin.login') }}" class="btn-submit">
                            <span class="btn-text">Ke halaman login</span>
                        </a>
                    @endif
                </div>
                <p class="status-footnote">Gunakan email yang sama saat login. Hubungi super admin jika status tidak berubah setelah 1×24 jam.</p>
            @elseif($pending)
                {{-- Langkah 3: kode akses admin. Permintaan join belum dikirim. --}}
                <div class="chosen-clinic">
                    <span class="mini-avatar" style="background: {{ $pending['clinic_color'] }}">
                        {{ strtoupper(substr($pending['clinic_name'], 0, 1)) }}
                    </span>
                    <span>
                        <strong>{{ $pending['clinic_name'] }}</strong><br>
                        <span style="font-size:0.72rem; opacity:0.85">Diajukan oleh {{ $pending['name'] ?: $pending['email'] }}</span>
                    </span>
                </div>

                <div class="otp-intro">
                    <p>
                        Kode akses 6 digit dikirim ke <strong>{{ $pending['email'] }}</strong>.
                        Masukkan kode tersebut untuk memastikan alamat email ini benar-benar milik Anda.
                        Permintaan baru diteruskan ke super admin setelah kode diverifikasi.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.register.verify') }}" id="otpForm" novalidate>
                    @csrf
                    <label class="field-label" for="otpDigit1">Kode akses admin</label>
                    <div class="otp-row" data-otp>
                        @for($i = 0; $i < 6; $i++)
                            <input
                                id="otpDigit{{ $i + 1 }}"
                                type="text"
                                name="code[]"
                                value="{{ old('code.' . $i) }}"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="1"
                                autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                                aria-label="Digit {{ $i + 1 }} dari 6"
                                @if($i === 0) autofocus @endif
                                @error('code') aria-invalid="true" class="invalid" @enderror
                            >
                        @endfor
                    </div>
                    @error('code')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                    <p class="otp-meta">
                        Kode berlaku {{ $pending['ttl'] }} menit dan hanya bisa dipakai sekali.
                        Tidak menemukannya? Cek folder Spam atau Promosi.
                    </p>

                    <div class="btn-row">
                        <button type="submit" class="btn-submit" id="otpSubmit">
                            <div class="spinner"></div>
                            <span class="btn-text">Verifikasi &amp; kirim permintaan</span>
                        </button>
                    </div>
                </form>

                <div class="resend-row">
                    <span>Belum menerima kode?</span>
                    <form method="POST" action="{{ route('admin.register.resend') }}">
                        @csrf
                        <button type="submit" class="link-btn" id="resendBtn"
                                data-wait="{{ $pending['resend_wait'] }}"
                                @if($pending['resend_wait'] > 0) disabled @endif>
                            {{ $pending['resend_wait'] > 0 ? 'Kirim ulang dalam ' . $pending['resend_wait'] . ' dtk' : 'Kirim ulang kode' }}
                        </button>
                    </form>
                    <span aria-hidden="true">·</span>
                    <form method="POST" action="{{ route('admin.register.cancel') }}">
                        @csrf
                        <button type="submit" class="link-btn">Ganti email</button>
                    </form>
                </div>
            @else
            <form method="POST" action="{{ route('admin.register.post') }}" id="joinForm" novalidate>
                @csrf

                <div class="mode-tabs" id="modeTabs" role="radiogroup" aria-label="Jenis pendaftaran">
                    <label>
                        <input type="radio" name="registration_type" value="join" {{ $initialMode === 'join' ? 'checked' : '' }}>
                        Gabung klinik
                    </label>
                    <label>
                        <input type="radio" name="registration_type" value="new_clinic" {{ $initialMode === 'new_clinic' ? 'checked' : '' }}>
                        Daftarkan klinik baru
                    </label>
                </div>

                <!-- STEP 1 (mode join): pilih klinik -->
                <fieldset id="step1" data-mode="join">
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

                <!-- STEP 1 (mode new_clinic): data klinik yang didaftarkan -->
                <fieldset id="step1New" class="hidden" data-mode="new_clinic">
                    <div class="input-wrap @error('clinic_name') invalid @enderror">
                        <label for="clinic_name" class="field-label">Nama klinik</label>
                        <input type="text" id="clinic_name" name="clinic_name" value="{{ old('clinic_name') }}"
                               placeholder="contoh: Klinik Hewan Sehat Bersama" autocomplete="organization">
                        <p class="field-hint">Tulis nama resmi klinik — muncul di aplikasi PetHeal.</p>
                        @error('clinic_name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="input-wrap @error('clinic_slug') invalid @enderror">
                        <label for="clinic_slug" class="field-label">Slug klinik</label>
                        <input type="text" id="clinic_slug" name="clinic_slug" value="{{ old('clinic_slug') }}"
                               placeholder="klinik-hewan-sehat" autocomplete="off" spellcheck="false">
                        <p class="field-hint" id="slugHint">Dibuat otomatis dari nama, boleh diubah. Hanya huruf kecil, angka, dan tanda hubung.</p>
                        @error('clinic_slug')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="input-wrap @error('clinic_address') invalid @enderror">
                        <label for="clinic_address" class="field-label">Alamat klinik</label>
                        <textarea id="clinic_address" name="clinic_address" rows="2"
                                  placeholder="Jalan, nomor, kota" autocomplete="street-address">{{ old('clinic_address') }}</textarea>
                        @error('clinic_address')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="row-2">
                        <div class="input-wrap @error('clinic_phone') invalid @enderror">
                            <label for="clinic_phone" class="field-label">Telepon klinik</label>
                            <input type="tel" id="clinic_phone" name="clinic_phone" value="{{ old('clinic_phone') }}"
                                   placeholder="021-1234567" autocomplete="tel">
                            @error('clinic_phone')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="input-wrap @error('clinic_email') invalid @enderror">
                            <label for="clinic_email" class="field-label">Email klinik <span style="font-weight:500;color:#A8A29E">(opsional)</span></label>
                            <input type="email" id="clinic_email" name="clinic_email" value="{{ old('clinic_email') }}"
                                   placeholder="info@klinik.com" autocomplete="email">
                            @error('clinic_email')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="input-wrap @error('clinic_primary_color') invalid @enderror">
                        <label for="clinic_primary_color" class="field-label">Warna utama</label>
                        <input type="color" id="clinic_primary_color" name="clinic_primary_color"
                               value="{{ old('clinic_primary_color', '#18C964') }}">
                        <p class="field-hint">Dipakai untuk aksen dashboard klinik dan aplikasi.</p>
                        @error('clinic_primary_color')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="input-wrap @error('clinic_description') invalid @enderror">
                        <label for="clinic_description" class="field-label">Deskripsi <span style="font-weight:500;color:#A8A29E">(opsional)</span></label>
                        <textarea id="clinic_description" name="clinic_description" rows="2"
                                  placeholder="Ceritakan singkat layanan klinik Anda">{{ old('clinic_description') }}</textarea>
                        @error('clinic_description')<div class="field-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="btn-row">
                        <button type="button" class="btn-submit" id="toStep2New">
                            <span class="btn-text">Lanjut ke data pemilik →</span>
                        </button>
                    </div>
                    <p class="clinic-hint">Logo diunggah setelah login dari Pengaturan → Klinik.</p>
                </fieldset>

                <!-- STEP 2: data pemilik -->
                <fieldset id="step2" class="hidden">
                    <div class="chosen-clinic" id="chosenBox" style="display:none">
                        <span class="mini-avatar" id="chosenAvatar">?</span>
                        <span><strong id="chosenName">—</strong><br><span id="chosenCaption" style="font-size:0.72rem; opacity:0.85">Klinik tujuan permintaan</span></span>
                        <button type="button" id="changeClinic">Ganti</button>
                    </div>

                    <div class="row-2">
                        <div class="input-wrap @error('name') invalid @enderror">
                            <label for="name" class="field-label">Nama lengkap pemilik</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Nama Anda">
                            @error('name')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="input-wrap @error('phone') invalid @enderror">
                            <label for="phone" class="field-label">WhatsApp / telepon</label>
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
                            <span class="btn-text">Kirim kode verifikasi</span>
                        </button>
                    </div>

                    <p class="otp-meta">
                        Kami mengirim kode 6 digit ke email di atas untuk memastikan alamatnya aktif.
                        Permintaan Anda baru masuk ke super admin setelah kode diverifikasi.
                    </p>
                </fieldset>
            </form>
            @endif

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
            <h2>Kelola <span>klinik hewan</span> Anda</h2>
            <p>Gabung ke klinik yang sudah ada, atau daftarkan klinik baru. Data tiap klinik terisolasi penuh — dokter, layanan, dan booking tidak tercampur.</p>

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

    @if(!$pending && !$submitted)
    <script>
        // ---------- wizard ----------
        const step1 = document.getElementById('step1');
        const step1New = document.getElementById('step1New');
        const step2 = document.getElementById('step2');
        const modeTabs = document.getElementById('modeTabs');
        const dot1 = document.getElementById('stepDot1');
        const dot2 = document.getElementById('stepDot2');
        const bar = document.getElementById('stepBar');
        const bar2 = document.getElementById('stepBar2');
        const title = document.getElementById('formTitle');
        const subtitle = document.getElementById('formSubtitle');
        const hint = document.getElementById('clinicHint');
        const stepLabel1 = document.getElementById('stepLabel1');
        const stepLabel2 = document.getElementById('stepLabel2');
        let currentStep = 1;

        function currentMode() {
            const checked = document.querySelector('input[name="registration_type"]:checked');
            return checked ? checked.value : 'join';
        }

        // Satu wizard, dua mode. Fieldset yang tidak dipakai di-disable supaya
        // atribut required-nya tidak memblokir submit.
        function syncMode() {
            const mode = currentMode();
            const onStep2 = currentStep === 2;

            // Field step 1 tetap dikirim saat berada di step 2 — hanya mode yang
            // tidak aktif yang di-disable (required-nya tidak ikut validasi).
            step1.querySelectorAll('input').forEach(el => { el.disabled = mode !== 'join'; });
            step1New.querySelectorAll('input, textarea').forEach(el => { el.disabled = mode !== 'new_clinic'; });

            step1.classList.toggle('hidden', mode !== 'join' || onStep2);
            step1New.classList.toggle('hidden', mode !== 'new_clinic' || onStep2);
            step2.classList.toggle('hidden', !onStep2);
            modeTabs.classList.toggle('hidden', onStep2);

            if (stepLabel1) stepLabel1.textContent = mode === 'new_clinic' ? 'Data klinik' : 'Klinik';
            if (stepLabel2) stepLabel2.textContent = mode === 'new_clinic' ? 'Data pemilik' : 'Data diri';

            if (!onStep2) {
                title.textContent = mode === 'new_clinic' ? 'Daftarkan klinik baru Anda' : 'Pilih klinik yang ingin Anda kelola';
                subtitle.textContent = mode === 'new_clinic'
                    ? 'Isi data klinik lalu data pemilik. Klinik baru dibuat setelah super admin menyetujui.'
                    : 'Setiap klinik punya data terpisah — dokter, layanan, dan booking tidak tercampur antar klinik.';
            }
        }

        function showStep(n) {
            currentStep = n;
            syncMode();

            const toTwo = n === 2;
            const panel = toTwo ? step2 : (currentMode() === 'new_clinic' ? step1New : step1);
            panel.classList.remove('step-enter');
            void panel.offsetWidth;
            panel.classList.add('step-enter');

            dot1.classList.toggle('active', !toTwo);
            dot1.classList.toggle('done', toTwo);
            dot1.querySelector('.num').innerHTML = toTwo
                ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
                : '1';
            dot2.classList.toggle('active', toTwo);
            bar.style.width = toTwo ? '100%' : '0%';
            if (bar2) bar2.style.width = '0%';

            if (toTwo) {
                title.textContent = currentMode() === 'new_clinic' ? 'Lengkapi data pemilik klinik' : 'Lengkapi data diri Anda';
                subtitle.textContent = currentMode() === 'new_clinic'
                    ? 'Data pemilik dipakai untuk membuat akun admin setelah klinik disetujui.'
                    : 'Data ini dipakai super admin untuk memverifikasi permintaan Anda. Setelah dikirim, kami akan mengirim kode ke email Anda.';
                updateChosen();
                document.getElementById('name').focus();
            }
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

        document.getElementById('toStep2New').addEventListener('click', function () {
            if (!validateClinicFields()) return;
            showStep(2);
        });

        document.getElementById('backToStep1').addEventListener('click', () => showStep(1));
        document.getElementById('changeClinic').addEventListener('click', () => showStep(1));

        document.querySelectorAll('input[name="registration_type"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                currentStep = 1;
                showStep(1);
                updateChosen();
            });
        });

        // ---------- slug otomatis dari nama klinik ----------
        const clinicNameInput = document.getElementById('clinic_name');
        const clinicSlugInput = document.getElementById('clinic_slug');
        const colorInput = document.getElementById('clinic_primary_color');
        let slugTouched = clinicSlugInput.value.trim() !== '';

        function slugify(value) {
            return value.toLowerCase().trim()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
        clinicSlugInput.addEventListener('input', function () {
            slugTouched = true;
            clinicSlugInput.value = slugify(clinicSlugInput.value);
        });
        clinicNameInput.addEventListener('input', function () {
            if (slugTouched) return;
            clinicSlugInput.value = slugify(clinicNameInput.value);
        });

        function validateClinicFields() {
            const required = [
                ['clinic_name', 'Nama klinik wajib diisi.'],
                ['clinic_slug', 'Slug klinik wajib diisi.'],
                ['clinic_address', 'Alamat klinik wajib diisi.'],
                ['clinic_phone', 'Telepon klinik wajib diisi.'],
            ];
            for (const [id, message] of required) {
                const el = document.getElementById(id);
                if (!el.value.trim()) {
                    el.focus();
                    const wrap = el.closest('.input-wrap');
                    wrap.classList.add('invalid');
                    let err = wrap.querySelector('.field-error.js');
                    if (!err) {
                        err = document.createElement('div');
                        err.className = 'field-error js';
                        wrap.appendChild(err);
                    }
                    err.textContent = message;
                    return false;
                }
            }
            return true;
        }

        // ---------- clinic card select + summary ----------
        const cards = document.querySelectorAll('.clinic-card');
        function updateChosen() {
            const box = document.getElementById('chosenBox');
            if (!box) return;

            if (currentMode() === 'new_clinic') {
                const name = clinicNameInput.value.trim() || 'Klinik baru';
                const color = colorInput.value || '#18C964';
                box.style.display = 'flex';
                document.getElementById('chosenName').textContent = name;
                const caption = document.getElementById('chosenCaption');
                if (caption) caption.textContent = 'Klinik yang didaftarkan';
                const avatar = document.getElementById('chosenAvatar');
                avatar.innerHTML = '';
                avatar.textContent = name.charAt(0).toUpperCase();
                avatar.style.background = color;
                return;
            }

            const checked = document.querySelector('input[name="clinic_id"]:checked');
            if (!checked) { box.style.display = 'none'; return; }
            const card = checked.closest('.clinic-card');
            box.style.display = 'flex';
            document.getElementById('chosenName').textContent = card.dataset.name;
            const caption = document.getElementById('chosenCaption');
            if (caption) caption.textContent = 'Klinik tujuan permintaan';
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

        showStep(1);

        // Jika server me-render ulang karena error validasi, buka langkah yang
        // relevan: error data pemilik -> step 2, error data klinik -> step 1.
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
            if (currentMode() === 'join' && !document.querySelector('input[name="clinic_id"]:checked')) {
                e.preventDefault();
                showStep(1);
                hint.classList.add('shake');
                hint.textContent = 'Silakan pilih salah satu klinik terlebih dahulu.';
                return;
            }
            if (currentMode() === 'new_clinic' && !validateClinicFields()) {
                e.preventDefault();
                showStep(1);
                return;
            }
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
    @endif

    @if($pending)
    <script>
        (function () {
            // ---------- input kode: auto-maju, backspace, paste ----------
            const row = document.querySelector('[data-otp]');
            const form = document.getElementById('otpForm');
            const submit = document.getElementById('otpSubmit');
            if (!row) return;

            const digits = Array.from(row.querySelectorAll('input'));

            digits.forEach(function (input, index) {
                input.addEventListener('input', function () {
                    const value = input.value.replace(/\D/g, '').slice(-1);
                    input.value = value;
                    input.classList.toggle('filled', value !== '');
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
                        digits[index - 1].classList.remove('filled');
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
                            box.classList.add('filled');
                        }
                    });
                    const next = Math.min(index + text.length, digits.length - 1);
                    digits[next].focus();
                });
            });

            form.addEventListener('submit', function (event) {
                const code = digits.map(function (d) { return d.value; }).join('');
                if (!/^\d{6}$/.test(code)) {
                    event.preventDefault();
                    digits.forEach(function (d) {
                        if (d.value === '') d.classList.add('invalid');
                    });
                    digits[0].focus();
                    return;
                }
                submit.classList.add('loading');
                submit.disabled = true;
            });

            // ---------- hitung mundur kirim ulang ----------
            const resend = document.getElementById('resendBtn');
            if (!resend) return;

            let wait = parseInt(resend.dataset.wait || '0', 10);
            const tick = function () {
                if (wait <= 0) {
                    resend.disabled = false;
                    resend.textContent = 'Kirim ulang kode';
                    return;
                }
                resend.disabled = true;
                resend.textContent = 'Kirim ulang dalam ' + wait + ' dtk';
                wait -= 1;
                setTimeout(tick, 1000);
            };
            tick();
        })();
    </script>
    @endif
</body>
</html>
