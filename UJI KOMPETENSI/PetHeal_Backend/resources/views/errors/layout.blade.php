<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Terjadi kesalahan') — PetHeal</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --brand: #10b981;
            --brand-deep: #047857;
            --paper: #f8fafc;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            display: grid;
            place-items: center;
            min-height: 100%;
            padding: 24px;
            background:
                radial-gradient(900px 500px at 15% -10%, rgba(16, 185, 129, 0.12), transparent 60%),
                radial-gradient(700px 420px at 95% 0%, rgba(59, 130, 246, 0.08), transparent 60%),
                var(--paper);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            letter-spacing: -0.011em;
        }
        .card {
            width: 100%;
            max-width: 560px;
            background: rgba(255, 255, 255, 0.86);
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 24px;
            box-shadow: 0 30px 80px -40px rgba(15, 23, 42, 0.35);
            padding: 40px;
            backdrop-filter: blur(14px);
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--brand-deep);
        }
        .paw {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 12px;
            background: rgba(16, 185, 129, 0.12);
            color: var(--brand-deep);
            font-size: 18px;
        }
        .code {
            margin: 28px 0 6px;
            font-size: clamp(56px, 12vw, 76px);
            line-height: 0.95;
            font-weight: 800;
            letter-spacing: -0.06em;
            color: var(--ink);
        }
        h1 {
            margin: 0 0 10px;
            font-size: 22px;
            line-height: 1.25;
            letter-spacing: -0.03em;
        }
        p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.65;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 26px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 12px;
            padding: 11px 18px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.15s ease, background 0.15s ease, border-color 0.15s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary {
            background: var(--brand);
            color: #fff;
            border: 1px solid var(--brand);
        }
        .btn-primary:hover { background: var(--brand-deep); border-color: var(--brand-deep); }
        .btn-ghost {
            background: #fff;
            color: #334155;
            border: 1px solid var(--line);
        }
        .btn-ghost:hover { border-color: #cbd5e1; background: #f8fafc; }
        .meta {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            display: flex;
            flex-wrap: wrap;
            gap: 8px 18px;
            font-size: 11px;
            color: #94a3b8;
        }
        .dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--brand);
            margin-right: 6px;
            vertical-align: middle;
        }
        @media (max-width: 520px) {
            .card { padding: 28px 22px; border-radius: 20px; }
        }
        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }
    </style>
</head>
<body>
    <main class="card" role="main">
        <span class="brand">
            <span class="paw" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" role="img" aria-hidden="true">
                    <path d="M12 13.5c-2.6 0-5 1.6-5 3.8 0 1.6 1.3 2.7 3 2.7 1 0 1.4-.4 2-.4s1 .4 2 .4c1.7 0 3-1.1 3-2.7 0-2.2-2.4-3.8-5-3.8Zm-5.6-2.2c1.2-.4 1.8-1.9 1.4-3.4S6.3 5.2 5.1 5.6 3.3 7.5 3.7 9s1.1 2.7 2.7 2.3Zm3.1-1.6c1.2 0 2.2-1.3 2.2-2.9S10.7 4 9.5 4 7.3 5.3 7.3 6.9s1 2.8 2.2 2.8Zm5 0c1.2 0 2.2-1.3 2.2-2.9S15.7 4 14.5 4s-2.2 1.3-2.2 2.9 1 2.8 2.2 2.8Zm3.1 1.6c1.6.4 2.7-.8 3.1-2.3s-.6-3-1.8-3.4-2.7.8-3.1 2.3.6 3 1.8 3.4Z"/>
                </svg>
            </span>
            PetHeal
        </span>
        <div class="code" aria-hidden="true">@yield('code', '500')</div>
        <h1>@yield('heading', 'Terjadi kesalahan')</h1>
        <p>@yield('message')</p>

        <div class="actions">
            @yield('actions')
            <a class="btn btn-ghost" href="{{ url('/admin') }}">Buka Admin</a>
        </div>

        <div class="meta">
            <span><span class="dot"></span>@yield('status', 'Sistem aktif')</span>
            <span>@yield('hint', 'Jika masalah berulang, hubungi admin klinik.')</span>
        </div>
    </main>
</body>
</html>
