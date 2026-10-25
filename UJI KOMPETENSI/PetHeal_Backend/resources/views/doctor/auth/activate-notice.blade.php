<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panduan Aktivasi · Portal Dokter · PetHeal</title>
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
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem;
            -webkit-font-smoothing: antialiased;
        }
        .card {
            width: 100%; max-width: 440px;
            background: #fff;
            border: 1px solid #E7E5E4;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 48px -24px rgba(27, 42, 30, 0.25);
        }
        .card-top { padding: 2rem 2rem 1.6rem; border-bottom: 1px solid #F0EEE9; }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-size: 0.65rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
            color: #15803D; background: #F0FDF4; border: 1px solid #BBF7D0;
            padding: 0.25rem 0.7rem; border-radius: 999px;
        }
        .eyebrow svg { width: 11px; height: 11px; display: block; }
        .card-top h1 { margin-top: 0.9rem; font-size: 1.4rem; font-weight: 800; color: #111; letter-spacing: -0.03em; line-height: 1.25; }
        .card-top p { margin-top: 0.55rem; font-size: 0.875rem; color: #6B7280; line-height: 1.6; }

        .card-body { padding: 1.4rem 2rem 2rem; }
        .steps { border: 1px solid #E7E5E4; border-radius: 12px; overflow: hidden; }
        .step { display: flex; gap: 0.75rem; align-items: flex-start; padding: 0.85rem 0.95rem; background: #FAFAF9; }
        .step + .step { border-top: 1px solid #E7E5E4; }
        .step .num {
            width: 24px; height: 24px; border-radius: 50%; flex-shrink: 0;
            background: #1B2A1E; color: #fff;
            font-size: 0.68rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .step small {
            display: block; font-size: 0.65rem; font-weight: 700;
            letter-spacing: 0.08em; text-transform: uppercase; color: #15803D; margin-bottom: 0.15rem;
        }
        .step p { font-size: 0.8125rem; color: #44403C; line-height: 1.6; }
        .step p strong { color: #111; }

        .error-box {
            background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B;
            padding: 0.8rem 1rem; border-radius: 12px; font-size: 0.8125rem;
            line-height: 1.55; margin-top: 1.05rem;
            display: flex; gap: 0.6rem; align-items: flex-start;
        }
        .error-box svg { flex-shrink: 0; margin-top: 2px; }

        .hint {
            margin-top: 1.05rem;
            background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E;
            padding: 0.7rem 0.95rem; border-radius: 12px;
            font-size: 0.78rem; line-height: 1.6;
        }

        .btn-back {
            margin-top: 1.5rem;
            width: 100%; padding: 0.75rem; font-size: 0.875rem; font-weight: 700;
            font-family: inherit; color: #fff; background: #1B2A1E;
            border: none; border-radius: 12px; cursor: pointer;
            text-decoration: none; text-align: center;
            transition: background 0.15s, transform 0.1s;
            box-shadow: 0 10px 24px -12px rgba(27, 42, 30, 0.5);
        }
        .btn-back:hover { background: #2D4A33; }
        .btn-back:active { transform: scale(0.99); }

        .foot {
            margin-top: 1.35rem; padding-top: 1.1rem; border-top: 1px solid #F0EEE9;
            display: flex; justify-content: space-between; align-items: center;
        }
        .foot a { font-size: 0.8125rem; color: #6B7280; text-decoration: none; }
        .foot a:hover { color: #1B2A1E; }
        .foot a.strong { color: #1B2A1E; font-weight: 700; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>

    <main class="card">
        <div class="card-top">
            <span class="eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Aktivasi undangan
            </span>
            <h1>Buka tautan di email undangan</h1>
            <p>Halaman aktivasi hanya bisa dibuka dari tautan yang dikirim admin klinik. Tautan itu sekaligus membuktikan email Anda — tidak ada kode tambahan.</p>
        </div>

        <div class="card-body">
            <div class="steps">
                <div class="step">
                    <span class="num">1</span>
                    <div>
                        <small>Langkah 1</small>
                        <p>Buka email <strong>"Aktivasi akun dokter"</strong> dari PetHeal. Cek folder Spam/Promosi bila belum terlihat.</p>
                    </div>
                </div>
                <div class="step">
                    <span class="num">2</span>
                    <div>
                        <small>Langkah 2</small>
                        <p>Klik tombol <strong>Buat Kata Sandi Portal Dokter</strong>. Halaman aktivasi terbuka dengan email Anda sudah terisi.</p>
                    </div>
                </div>
                <div class="step">
                    <span class="num">3</span>
                    <div>
                        <small>Langkah 3</small>
                        <p>Tentukan kata sandi, lalu masuk langsung ke dashboard dokter.</p>
                    </div>
                </div>
            </div>

            @if($errors->any())
                <div class="error-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>
                        <strong>Tautan tidak bisa dipakai:</strong>
                        <ul style="margin: 0.25rem 0 0 1.1rem;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </span>
                </div>
            @endif

            <div class="hint">
                Tautan berlaku 60 menit dan hanya sekali pakai. Sudah kedaluwarsa? Minta admin klinik mengirim ulang undangan dari halaman profil dokter Anda — tautan lama otomatis tidak berlaku.
            </div>

            <a href="{{ route('doctor.login') }}" class="btn-back">Ke halaman masuk dokter</a>

            <div class="foot">
                <a href="{{ url('/') }}" class="strong">&larr; Kembali ke situs</a>
                <a href="{{ route('admin.login') }}">Login admin</a>
            </div>
        </div>
    </main>

</body>
</html>
