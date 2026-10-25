<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akun Aktif · Portal Dokter · PetHeal</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F4F1ED;
            color: #1a1a1a;
            /* Auto margin: center saat muat, scroll mulus saat kartu lebih
               tinggi dari layar — tidak ada bagian yang terpotong. */
            display: flex;
            min-height: 100vh;
            padding: 2rem 1.25rem;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            width: 100%; max-width: 480px; margin: auto;
            background: #fff;
            border: 1px solid #E7E5E4;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 48px -24px rgba(27, 42, 30, 0.25);
            animation: cardRise 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes cardRise {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-top {
            padding: 1.85rem 1.9rem 1.55rem;
            display: flex; flex-direction: column; align-items: center; text-align: center;
            border-bottom: 1px solid #F0EEE9;
        }
        .check-badge {
            width: 52px; height: 52px; border-radius: 50%;
            background: #1B2A1E;
            display: flex; align-items: center; justify-content: center;
            color: #86EFAC;
            box-shadow: 0 10px 22px -10px rgba(27, 42, 30, 0.55);
            animation: badgePop 0.45s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
        }
        .check-badge svg { width: 24px; height: 24px; display: block; }
        @keyframes badgePop {
            from { transform: scale(0.6); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }
        .eyebrow {
            margin-top: 0.9rem;
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-size: 0.62rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
            color: #15803D; background: #F0FDF4; border: 1px solid #BBF7D0;
            padding: 0.22rem 0.65rem; border-radius: 999px;
        }
        .card-top h1 {
            margin-top: 0.7rem;
            font-size: 1.35rem; font-weight: 800; color: #111;
            letter-spacing: -0.03em; line-height: 1.25;
        }
        .card-top p { margin-top: 0.45rem; font-size: 0.83rem; color: #6B7280; line-height: 1.55; }
        .card-top p strong { color: #111; font-weight: 700; }

        .card-body { padding: 1.25rem 1.9rem 1.6rem; }

        .detail-rows { border: 1px solid #E7E5E4; border-radius: 12px; overflow: hidden; }
        .detail-row {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            padding: 0.6rem 0.9rem;
            background: #FAFAF9;
        }
        .detail-row + .detail-row { border-top: 1px solid #E7E5E4; }
        .detail-row small {
            display: block; font-size: 0.6rem; font-weight: 700;
            letter-spacing: 0.08em; text-transform: uppercase; color: #9CA3AF; margin-bottom: 0.05rem;
        }
        .detail-row span {
            display: block; font-size: 0.8rem; font-weight: 600; color: #111;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .detail-row .right { min-width: 0; }
        .detail-row .verified-pill {
            flex-shrink: 0;
            display: inline-flex; align-items: center; gap: 0.3rem;
            font-size: 0.58rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
            color: #15803D; background: #F0FDF4; border: 1px solid #BBF7D0;
            padding: 0.18rem 0.5rem; border-radius: 999px;
        }
        .detail-row .verified-pill svg { width: 9px; height: 9px; display: block; }

        .next-label {
            margin-top: 1.1rem;
            font-size: 0.62rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
            color: #9CA3AF;
        }
        .next-grid {
            margin-top: 0.5rem;
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem;
        }
        .next-grid a {
            display: flex; align-items: center; gap: 0.55rem;
            padding: 0.6rem 0.65rem;
            border: 1px solid #E7E5E4; border-radius: 10px;
            text-decoration: none;
            background: #fff;
            transition: background 0.15s, border-color 0.15s;
        }
        .next-grid a:hover { background: #F4F1ED; border-color: #D6D3D1; }
        .next-grid .next-icon {
            width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0;
            background: #F0FDF4; border: 1px solid #DCFCE7;
            display: flex; align-items: center; justify-content: center;
        }
        .next-grid .next-icon svg { width: 14px; height: 14px; display: block; }
        .next-grid p { font-size: 0.75rem; font-weight: 700; color: #111; }

        .btn-submit {
            margin-top: 1.15rem;
            width: 100%; padding: 0.75rem; font-size: 0.875rem; font-weight: 700;
            font-family: inherit; color: #fff; background: #1B2A1E;
            border: none; border-radius: 12px; cursor: pointer;
            text-decoration: none;
            transition: background 0.15s, transform 0.1s, box-shadow 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            box-shadow: 0 10px 24px -12px rgba(27, 42, 30, 0.5);
        }
        .btn-submit:hover { background: #2D4A33; box-shadow: 0 12px 28px -12px rgba(27, 42, 30, 0.6); }
        .btn-submit:active { transform: scale(0.99); }
        .btn-submit svg { width: 15px; height: 15px; display: block; }

        .card-foot {
            margin-top: 1.1rem;
            padding-top: 0.95rem;
            border-top: 1px solid #F0EEE9;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        }
        .card-foot span, .card-foot a { font-size: 0.74rem; color: #9CA3AF; text-decoration: none; }
        .card-foot a:hover { color: #1B2A1E; }
        .card-foot .spent {
            display: inline-flex; align-items: center; gap: 0.35rem;
            font-size: 0.66rem; color: #A8A29E;
        }
        .card-foot .spent svg { width: 11px; height: 11px; display: block; }

        /* Layar pendek (laptop 1366x768): kurangi padatan agar ticker tetap muat. */
        @media (max-height: 720px) {
            body { padding: 1rem 1.25rem; }
            .card-top { padding: 1.35rem 1.9rem 1.15rem; }
            .check-badge { width: 44px; height: 44px; }
            .check-badge svg { width: 19px; height: 19px; }
            .card-top h1 { font-size: 1.2rem; }
            .card-body { padding: 1rem 1.9rem 1.2rem; }
        }
        @media (max-width: 420px) {
            .next-grid { grid-template-columns: 1fr; }
            .card-body { padding: 1.1rem 1.25rem 1.35rem; }
            .card-top { padding: 1.5rem 1.25rem 1.2rem; }
            .card-foot { flex-direction: column; align-items: flex-start; gap: 0.4rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>

    <main class="card">
        <div class="card-top">
            <span class="check-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            </span>
            <span class="eyebrow">Aktivasi selesai</span>
            <h1>Akun dokter Anda sudah aktif</h1>
            <p>Selamat datang, <strong>{{ $name }}</strong>. Portal dokter <strong>{{ $clinicName }}</strong> siap dipakai.</p>
        </div>

        <div class="card-body">
            <div class="detail-rows">
                <div class="detail-row">
                    <div class="right">
                        <small>Akun portal</small>
                        <span>{{ $email }}</span>
                    </div>
                    <span class="verified-pill">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                        Terverifikasi
                    </span>
                </div>
                <div class="detail-row">
                    <div class="right" style="min-width: 0;">
                        <small>Klinik</small>
                        <span>{{ $clinicName }}</span>
                    </div>
                </div>
            </div>

            <p class="next-label">Mulai dari sini</p>
            <nav class="next-grid" aria-label="Pintasan dashboard">
                <a href="{{ route('doctor.bookings.index') }}">
                    <span class="next-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#15803D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </span>
                    <p>Booking</p>
                </a>
                <a href="{{ route('doctor.medical-records.index') }}">
                    <span class="next-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#15803D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </span>
                    <p>Rekam medis</p>
                </a>
                <a href="{{ route('doctor.schedule.index') }}">
                    <span class="next-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#15803D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <p>Jadwal</p>
                </a>
            </nav>

            <a href="{{ route('doctor.dashboard') }}" class="btn-submit">
                Buka Dashboard Dokter
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>

            <div class="card-foot">
                <span class="spent">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Tautan undangan sudah hangus setelah dipakai
                </span>
                <a href="{{ url('/') }}">Kembali ke situs</a>
            </div>
        </div>
    </main>

</body>
</html>
