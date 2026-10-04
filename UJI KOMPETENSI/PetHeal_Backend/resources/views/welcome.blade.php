<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PetHeal — Booking Klinik Hewan (Android + Web Admin)</title>
    <meta name="description" content="PetHeal: aplikasi booking dokter hewan multi-klinik. Satu APK Android untuk pemilik hewan, satu panel web untuk admin klinik. Booking, pembayaran Midtrans, rekam medis digital, dan pengingat otomatis.">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        serif: ['Fraunces', 'Georgia', 'serif'],
                        mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
                    },
                    colors: {
                        paper: '#FAF6EF',
                        cream: '#F3EDE0',
                        ink: '#1C1917',
                        pine: { 800: '#14532D', 900: '#0E3B22', 950: '#0A2C1A' },
                        clay: '#C2571B',
                    }
                }
            }
        }
    </script>
    <style>
        html { scroll-behavior: smooth; }
        body { background: #FAF6EF; color: #1C1917; -webkit-font-smoothing: antialiased; }
        .dotgrid {
            background-image: radial-gradient(rgba(28,25,23,.10) 1px, transparent 1px);
            background-size: 22px 22px;
        }
        .dotgrid-light {
            background-image: radial-gradient(rgba(255,255,255,.14) 1px, transparent 1px);
            background-size: 22px 22px;
        }
        .eyebrow { letter-spacing: .18em; }
        .card { border: 1px solid #E7DFCF; }
        .tick { font-variant-numeric: tabular-nums; }
        .reveal { opacity: 0; transform: translateY(26px); transition: opacity .7s ease, transform .7s ease; }
        .reveal.visible { opacity: 1; transform: none; }
        .navlink { position: relative; }
        .navlink::after {
            content: ''; position: absolute; left: 0; bottom: -4px; height: 2px; width: 0;
            background: #14532D; transition: width .25s ease;
        }
        .navlink:hover::after { width: 100%; }
        ::selection { background: #14532D; color: #fff; }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="font-sans">

@php
    $safeCount = function ($class) {
        try { return $class::count(); } catch (\Throwable $e) { return '—'; }
    };
    $todayBookings = '—';
    try { $todayBookings = \App\Models\Booking::whereDate('booking_date', today())->count(); } catch (\Throwable $e) {}
    $doctors = $safeCount(\App\Models\Doctor::class);
    $users = $safeCount(\App\Models\User::class);
    $records = $safeCount(\App\Models\MedicalRecord::class);
    $clinics = class_exists(\App\Models\Clinic::class) ? $safeCount(\App\Models\Clinic::class) : '—';
    $services = class_exists(\App\Models\Service::class) ? $safeCount(\App\Models\Service::class) : '—';
    $baseUrl = rtrim(config('app.url'), '/') . '/api';
@endphp

<!-- Pita status atas: jujur, bukan hype -->
<div class="bg-pine-950 text-stone-200 text-[12.5px]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-2 flex flex-wrap items-center gap-x-4 gap-y-1">
        <span class="inline-flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="font-medium text-white">Server demo menyala</span>
        </span>
        <span class="hidden sm:inline text-stone-400">·</span>
        <span class="font-mono text-stone-300">{{ $baseUrl }}/health</span>
        <span class="ml-auto text-stone-400">Dikerjakan untuk <span class="text-stone-200 font-medium">Uji Kompetensi XI RPL</span> — Android + Laravel, 100% stack gratis</span>
    </div>
</div>

<!-- Navigasi -->
<header class="sticky top-0 z-50 bg-paper/90 backdrop-blur border-b border-[#E7DFCF]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center gap-6">
        <a href="/" class="flex items-center">
            <img src="/logo.png" alt="PetHeal — Veterinary Clinic System" class="h-10 w-auto bg-white rounded-xl px-2 py-1 border border-[#E7DFCF]" fetchpriority="high">
        </a>
        <nav class="hidden md:flex items-center gap-6 text-[14px] font-medium text-stone-600 ml-4">
            <a href="#alur" class="navlink hover:text-ink">Alur</a>
            <a href="#untuk-siapa" class="navlink hover:text-ink">Untuk siapa</a>
            <a href="#fitur" class="navlink hover:text-ink">Fitur</a>
            <a href="#bayar" class="navlink hover:text-ink">Pembayaran</a>
            <a href="#teknis" class="navlink hover:text-ink">Teknis</a>
        </nav>
        <div class="ml-auto flex items-center gap-2.5">
            <a href="/admin/login" class="hidden sm:inline-flex text-[14px] font-medium text-stone-600 hover:text-ink px-3 py-2">Masuk admin</a>
            <a href="/admin" class="inline-flex items-center gap-2 bg-ink text-white text-[14px] font-semibold px-4 py-2.5 rounded-xl hover:bg-pine-800 transition-colors">
                Buka dashboard
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
        </div>
    </div>
</header>

<!-- HERO -->
<section class="relative overflow-hidden">
    <div class="dotgrid absolute inset-0 opacity-60 pointer-events-none"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-12 pb-10 sm:pt-16 sm:pb-14 grid lg:grid-cols-12 gap-10 items-start">
        <div class="lg:col-span-7">
            <p class="eyebrow text-[11.5px] font-bold uppercase text-pine-800">Aplikasi booking klinik hewan · Android & Web</p>
            <h1 class="font-serif font-semibold tracking-tight text-[34px] leading-[1.08] sm:text-[52px] mt-4">
                Booking dokter hewan,<br>
                bayar, dan pantau kesehatan<br class="hidden sm:block">
                hewan — <span class="italic font-medium text-pine-800">satu alur yang nyambung.</span>
            </h1>
            <p class="mt-5 text-[16.5px] leading-relaxed text-stone-600 max-w-xl">
                PetHeal menghubungkan <strong class="text-ink font-semibold">pemilik hewan</strong> (aplikasi Android)
                dengan <strong class="text-ink font-semibold">admin klinik</strong> (panel web).
                Satu APK bisa melayani <strong class="text-ink font-semibold">banyak klinik</strong> — pemilik tinggal pilih
                klinik, lalu daftar dokter, slot, dan layanannya menyesuaikan otomatis.
            </p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="/admin" class="inline-flex items-center gap-2 bg-pine-800 text-white font-semibold px-5 py-3 rounded-xl hover:bg-pine-900 transition-colors text-[15px]">
                    Lihat panel admin
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
                <a href="#alur" class="inline-flex items-center gap-2 bg-white card font-semibold px-5 py-3 rounded-xl hover:border-stone-400 transition-colors text-[15px]">
                    Pahami alurnya dulu
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </a>
            </div>
            <dl class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-px bg-[#E7DFCF] rounded-2xl overflow-hidden card max-w-xl">
                <div class="bg-white px-4 py-3.5"><dt class="text-[11px] font-semibold uppercase tracking-widest text-stone-500">Dokter</dt><dd class="tick font-serif text-2xl font-semibold mt-0.5">{{ $doctors }}</dd></div>
                <div class="bg-white px-4 py-3.5"><dt class="text-[11px] font-semibold uppercase tracking-widest text-stone-500">Booking hari ini</dt><dd class="tick font-serif text-2xl font-semibold mt-0.5">{{ $todayBookings }}</dd></div>
                <div class="bg-white px-4 py-3.5"><dt class="text-[11px] font-semibold uppercase tracking-widest text-stone-500">Pengguna</dt><dd class="tick font-serif text-2xl font-semibold mt-0.5">{{ $users }}</dd></div>
                <div class="bg-white px-4 py-3.5"><dt class="text-[11px] font-semibold uppercase tracking-widest text-stone-500">Rekam medis</dt><dd class="tick font-serif text-2xl font-semibold mt-0.5">{{ $records }}</dd></div>
            </dl>
            <p class="mt-3 text-[12.5px] text-stone-500">Angka di atas angka asli dari database demo — bukan angka ilustrasi. Klinik: <span class="font-semibold text-stone-700">{{ $clinics }}</span> · Layanan: <span class="font-semibold text-stone-700">{{ $services }}</span>.</p>
        </div>

        <!-- Kartu contoh booking: konkret, bukan mockup generik -->
        <div class="lg:col-span-5 reveal">
            <div class="bg-white card rounded-2xl shadow-[0_24px_60px_-30px_rgba(28,25,23,.35)] overflow-hidden">
                <div class="px-5 py-4 border-b border-stone-100 flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-cream flex items-center justify-center font-serif font-semibold">B</span>
                    <div>
                        <p class="text-[13px] font-semibold leading-tight">Contoh booking yang lewat sistem</p>
                        <p class="text-[12px] text-stone-500 font-mono">BOOKING-12-1719849600 · DP 50%</p>
                    </div>
                    <span class="ml-auto text-[11px] font-bold uppercase tracking-widest bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 rounded-full">Confirmed</span>
                </div>
                <ol class="px-5 py-4 space-y-3.5 text-[13.5px]">
                    <li class="flex gap-3"><span class="mt-0.5 w-5 h-5 rounded-full bg-pine-800 text-white text-[11px] font-bold flex items-center justify-center shrink-0">✓</span><span><strong class="font-semibold">Pemilik</strong> pilih klinik “Petheal Pusat”, dokter, layanan <em>Vaksinasi + cek umum</em>, slot <span class="font-mono">Selasa 10:00</span> (slot 30 menit, anti-bentrok).</span></li>
                    <li class="flex gap-3"><span class="mt-0.5 w-5 h-5 rounded-full bg-pine-800 text-white text-[11px] font-bold flex items-center justify-center shrink-0">✓</span><span><strong class="font-semibold">Bayar DP</strong> via Midtrans Snap. Sisa dibayar belakangan — status pembayaran naik terus, tidak pernah turun.</span></li>
                    <li class="flex gap-3"><span class="mt-0.5 w-5 h-5 rounded-full bg-pine-800 text-white text-[11px] font-bold flex items-center justify-center shrink-0">✓</span><span><strong class="font-semibold">Admin konfirmasi</strong> → pemilik otomatis dapat push notification (FCM v1).</span></li>
                    <li class="flex gap-3"><span class="mt-0.5 w-5 h-5 rounded-full bg-cream border border-[#E7DFCF] text-[11px] font-bold flex items-center justify-center shrink-0 text-stone-500">4</span><span class="text-stone-600">Selesai diperiksa → <strong class="font-semibold text-ink">rekam medis terbit</strong>. Kalau ada biaya tambahan, detailnya terbuka setelah dilunasi.</span></li>
                    <li class="flex gap-3"><span class="mt-0.5 w-5 h-5 rounded-full bg-cream border border-[#E7DFCF] text-[11px] font-bold flex items-center justify-center shrink-0 text-stone-500">5</span><span class="text-stone-600"><strong class="font-semibold text-ink">Berat & vaksin tercatat</strong> — grafik berat dan jadwal vaksin berikutnya terpantau di HP.</span></li>
                </ol>
                <div class="px-5 py-3.5 bg-[#FBF9F4] border-t border-stone-100 flex items-center gap-2 text-[12px] text-stone-500">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Pengingat H-1 dikirim otomatis — pemilik tidak perlu di-WA satu per satu.
                </div>
            </div>
            <p class="mt-3 text-[12.5px] text-stone-500 leading-relaxed">Stack yang dipakai: <span class="font-mono text-stone-700">Laravel 12 · Kotlin + Jetpack Compose · MySQL · Firebase Auth + FCM · Midtrans Sandbox</span>.</p>
        </div>
    </div>
</section>

<!-- MASALAH vs SOLUSI: tabel jujur -->
<section class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
    <div class="reveal">
        <p class="eyebrow text-[11.5px] font-bold uppercase text-clay">Kenapa aplikasi ini dibuat</p>
        <h2 class="font-serif font-semibold tracking-tight text-[28px] sm:text-[36px] mt-3 max-w-2xl leading-tight">Pencatatan klinik yang biasanya tercecer, dibuat rapi dari awal.</h2>
    </div>
    <div class="mt-7 grid md:grid-cols-2 gap-4">
        <div class="bg-white card rounded-2xl p-6">
            <p class="text-[12px] font-bold uppercase tracking-widest text-stone-400">Cara yang sering terjadi</p>
            <ul class="mt-4 space-y-3.5 text-[14.5px] text-stone-600">
                <li class="flex gap-3"><span class="text-stone-300 font-serif text-lg leading-none">×</span>Jadwal ditulis di buku / chat, gampang bentrok dan kelewat.</li>
                <li class="flex gap-3"><span class="text-stone-300 font-serif text-lg leading-none">×</span>Pengingat kontrol dikirim manual lewat WA satu per satu.</li>
                <li class="flex gap-3"><span class="text-stone-300 font-serif text-lg leading-none">×</span>Riwayat berobat menumpuk di kertas — hilang saat dibutuhkan.</li>
                <li class="flex gap-3"><span class="text-stone-300 font-serif text-lg leading-none">×</span>Uang muka dicatat terpisah, sisa pembayaran mudah lupa.</li>
            </ul>
        </div>
        <div class="bg-pine-950 text-stone-100 rounded-2xl p-6 relative overflow-hidden">
            <div class="dotgrid-light absolute inset-0 opacity-40 pointer-events-none"></div>
            <div class="relative">
                <p class="text-[12px] font-bold uppercase tracking-widest text-emerald-300/80">Yang dikerjakan PetHeal</p>
                <ul class="mt-4 space-y-3.5 text-[14.5px] text-stone-200">
                    <li class="flex gap-3"><span class="text-emerald-300 font-bold">✓</span>Slot 30 menit dikunci per dokter + tanggal (<span class="font-mono text-[13px]">lockForUpdate</span>) — jadwal ganda tertolak otomatis (409).</li>
                    <li class="flex gap-3"><span class="text-emerald-300 font-bold">✓</span>Konfirmasi, pengingat vaksin, dan kontrol dikirim sebagai push notification.</li>
                    <li class="flex gap-3"><span class="text-emerald-300 font-bold">✓</span>Setiap kunjungan jadi rekam medis digital per hewan, lengkap dengan berat & vaksinasi.</li>
                    <li class="flex gap-3"><span class="text-emerald-300 font-bold">✓</span>Skema DP / lunas / sisa tercatat di satu tempat, terhubung ke Midtrans.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ALUR -->
<section id="alur" class="max-w-6xl mx-auto px-4 sm:px-6 py-10 scroll-mt-20">
    <div class="reveal flex flex-wrap items-end gap-4">
        <div>
            <p class="eyebrow text-[11.5px] font-bold uppercase text-pine-800">Alur kerja · 5 langkah</p>
            <h2 class="font-serif font-semibold tracking-tight text-[28px] sm:text-[36px] mt-3">Dari daftar sampai kontrol berikutnya</h2>
        </div>
        <p class="ml-auto text-[13.5px] text-stone-500 max-w-sm">Alur ini sama persis di aplikasi Android dan panel admin — statusnya tersinkron dua arah.</p>
    </div>
    <ol class="mt-7 grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
        @php
            $steps = [
                ['n' => '01', 't' => 'Daftar & pilih klinik', 'd' => 'Login email atau Google (Firebase). 1 akun terikat 1 klinik — pindah klinik tinggal ganti slug, tanpa install ulang.'],
                ['n' => '02', 't' => 'Pilih dokter + slot', 'd' => 'Slot harian 30 menit, dihitung dari jadwal praktik. Tanggal libur otomatis kosong.'],
                ['n' => '03', 't' => 'Bayar DP / lunas', 'd' => 'Snap token Midtrans. Order ID jelas: BOOKING-{id}-{waktu}. Sisa bisa dibayar menyusul.'],
                ['n' => '04', 't' => 'Datang & diperiksa', 'd' => 'Admin konfirmasi → selesai. Pemilik dapat notifikasi di tiap perubahan status.'],
                ['n' => '05', 't' => 'Pantau dari HP', 'd' => 'Rekam medis, grafik berat, jadwal vaksin, dan rating dokter tersimpan per hewan.'],
            ];
        @endphp
        @foreach($steps as $i => $s)
        <li class="reveal bg-white card rounded-2xl p-5 flex flex-col" style="transition-delay: {{ $i * 60 }}ms">
            <span class="font-mono text-[12px] font-medium text-clay">{{ $s['n'] }}</span>
            <p class="font-serif font-semibold text-[17px] mt-1.5 leading-snug">{{ $s['t'] }}</p>
            <p class="text-[13.5px] text-stone-600 leading-relaxed mt-2">{{ $s['d'] }}</p>
        </li>
        @endforeach
    </ol>
</section>

<!-- UNTUK SIAPA -->
<section id="untuk-siapa" class="bg-white border-y border-[#E7DFCF] mt-6 scroll-mt-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-14">
        <div class="reveal max-w-2xl">
            <p class="eyebrow text-[11.5px] font-bold uppercase text-pine-800">Untuk siapa</p>
            <h2 class="font-serif font-semibold tracking-tight text-[28px] sm:text-[36px] mt-3">Tiga peran, satu database yang sama</h2>
            <p class="mt-3 text-stone-600 text-[15.5px] leading-relaxed">Tidak ada input ulang. Apa yang diisi pemilik di HP langsung terbaca admin — dan sebaliknya.</p>
        </div>
        <div class="mt-8 grid md:grid-cols-3 gap-4">
            <div class="reveal card rounded-2xl p-6 bg-paper">
                <p class="text-[12px] font-bold uppercase tracking-widest text-pine-800">Pemilik hewan · Android</p>
                <h3 class="font-serif font-semibold text-[20px] mt-2">Urus hewan dari HP</h3>
                <ul class="mt-4 space-y-2.5 text-[14px] text-stone-700 leading-relaxed">
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Data banyak hewan + foto (kamera / galeri), riwayat berat 0,1–200 kg.</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Jadwal vaksin + tanggal berikutnya, dengan pengingat.</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Booking, reschedule (selagi <em>pending</em>), dan batal dengan alasan.</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Beri rating 1–5 + ulasan, satu kali per booking selesai.</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Notifikasi: konfirmasi, selesai, H-1 vaksin & kontrol.</li>
                </ul>
            </div>
            <div class="reveal card rounded-2xl p-6 bg-paper" style="transition-delay:80ms">
                <p class="text-[12px] font-bold uppercase tracking-widest text-pine-800">Admin klinik · Web</p>
                <h3 class="font-serif font-semibold text-[20px] mt-2">Operasional harian</h3>
                <ul class="mt-4 space-y-2.5 text-[14px] text-stone-700 leading-relaxed">
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Dashboard + grafik, daftar booking hari ini.</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Konfirmasi / selesaikan / batalkan + kirim pengingat manual.</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Kelola dokter, layanan (import CSV/XLSX), dan tarif.</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Terbitkan rekam medis + biaya tambahan bila ada.</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Ekspor PDF/CSV, jejak audit tiap aksi penting.</li>
                </ul>
            </div>
            <div class="reveal card rounded-2xl p-6 bg-paper" style="transition-delay:160ms">
                <p class="text-[12px] font-bold uppercase tracking-widest text-pine-800">Super admin · Web</p>
                <h3 class="font-serif font-semibold text-[20px] mt-2">Banyak klinik, tetap tertib</h3>
                <ul class="mt-4 space-y-2.5 text-[14px] text-stone-700 leading-relaxed">
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Tambah klinik (slug, logo, warna tema sendiri).</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Setujui / tolak pengajuan gabung klinik.</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Intip tiap klinik tanpa campur datanya (isolasi <span class="font-mono text-[13px]">clinic_id</span>).</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Kelola template notifikasi global.</li>
                    <li class="flex gap-2.5"><span class="text-pine-800 font-bold">·</span>Akun tanpa klinik otomatis ditolak (403) — gagal tertutup, bukan bocor.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- FITUR DETAIL -->
<section id="fitur" class="max-w-6xl mx-auto px-4 sm:px-6 py-14 scroll-mt-20">
    <div class="reveal max-w-2xl">
        <p class="eyebrow text-[11.5px] font-bold uppercase text-pine-800">Fitur · yang benar-benar jalan</p>
        <h2 class="font-serif font-semibold tracking-tight text-[28px] sm:text-[36px] mt-3">Bukan daftar janji — ini yang sudah bisa didemo</h2>
    </div>
    <div class="mt-8 grid md:grid-cols-2 gap-4">
        <div class="reveal bg-white card rounded-2xl p-6">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-pine-800 text-white flex items-center justify-center font-serif font-semibold">1</span>
                <h3 class="font-serif font-semibold text-[19px]">Booking yang anti-bentrok</h3>
            </div>
            <ul class="mt-4 space-y-2 text-[14px] text-stone-600 leading-relaxed">
                <li>— Slot dihitung dari jadwal praktik dokter, interval 30 menit; hari libur mengembalikan list kosong.</li>
                <li>— Cek ganda memakai database lock — dua orang klik jam yang sama, satu ditolak dengan 409.</li>
                <li>— Reschedule hanya untuk status <span class="font-mono text-[13px]">pending</span>; hapus hanya untuk <span class="font-mono text-[13px]">pending/cancelled</span>.</li>
            </ul>
            <p class="mt-4 font-mono text-[12px] bg-[#F6F1E6] border border-[#E7DFCF] rounded-lg px-3 py-2 text-stone-700">GET /doctors/{id}/slots?date=2026-10-06 → [{time, available}]</p>
        </div>
        <div class="reveal bg-white card rounded-2xl p-6" style="transition-delay:60ms">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-pine-800 text-white flex items-center justify-center font-serif font-semibold">2</span>
                <h3 class="font-serif font-semibold text-[19px]">Rekam medis + tumbuh kembang</h3>
            </div>
            <ul class="mt-4 space-y-2 text-[14px] text-stone-600 leading-relaxed">
                <li>— Diagnosis, tindakan, obat, catatan, dan jadwal kontrol berikutnya per kunjungan.</li>
                <li>— Grafik berat badan + riwayat vaksinasi per hewan, dengan daftar “segera jatuh tempo”.</li>
                <li>— Detail sensitif disamarkan sampai biaya tambahan lunas — adil untuk kedua sisi.</li>
            </ul>
            <p class="mt-4 font-mono text-[12px] bg-[#F6F1E6] border border-[#E7DFCF] rounded-lg px-3 py-2 text-stone-700">GET /pets/{id}/weight-history · GET /pets/{id}/vaccinations</p>
        </div>
        <div class="reveal bg-white card rounded-2xl p-6">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-pine-800 text-white flex items-center justify-center font-serif font-semibold">3</span>
                <h3 class="font-serif font-semibold text-[19px]">Dokter, layanan & ulasan</h3>
            </div>
            <ul class="mt-4 space-y-2 text-[14px] text-stone-600 leading-relaxed">
                <li>— Profil dokter + foto, spesialisasi, jadwal, dan rata-rata rating.</li>
                <li>— Katalog layanan per klinik + metode pembayaran global; admin bisa import massal.</li>
                <li>— Ulasan terikat booking selesai milik sendiri — tidak bisa asal menilai.</li>
            </ul>
            <p class="mt-4 font-mono text-[12px] bg-[#F6F1E6] border border-[#E7DFCF] rounded-lg px-3 py-2 text-stone-700">POST /doctors/{id}/reviews {booking_id, rating 1–5}</p>
        </div>
        <div class="reveal bg-white card rounded-2xl p-6" style="transition-delay:60ms">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-pine-800 text-white flex items-center justify-center font-serif font-semibold">4</span>
                <h3 class="font-serif font-semibold text-[19px]">Notifikasi yang tepat waktu</h3>
            </div>
            <ul class="mt-4 space-y-2 text-[14px] text-stone-600 leading-relaxed">
                <li>— FCM v1 (JWT OAuth2): konfirmasi booking, booking selesai, H-1 vaksin & kontrol.</li>
                <li>— Admin bisa kirim ulang pengingat dari detail booking bila pemilik belum datang.</li>
                <li>— Token per perangkat didaftarkan saat login, dihapus saat logout.</li>
            </ul>
            <p class="mt-4 font-mono text-[12px] bg-[#F6F1E6] border border-[#E7DFCF] rounded-lg px-3 py-2 text-stone-700">POST /device-token {token, device_type}</p>
        </div>
    </div>
</section>

<!-- PEMBAYARAN -->
<section id="bayar" class="bg-pine-950 text-stone-100 scroll-mt-20 relative overflow-hidden">
    <div class="dotgrid-light absolute inset-0 opacity-30 pointer-events-none"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 py-14 grid lg:grid-cols-2 gap-10">
        <div class="reveal">
            <p class="eyebrow text-[11.5px] font-bold uppercase text-emerald-300">Pembayaran · Midtrans Sandbox</p>
            <h2 class="font-serif font-semibold tracking-tight text-[28px] sm:text-[36px] mt-3 text-white">DP dulu boleh, pelunasan menyusul — tercatat semua.</h2>
            <p class="mt-4 text-stone-300 text-[15px] leading-relaxed">Banyak klinik membolehkan bayar sebagian. PetHeal mencatat skema <span class="font-mono text-[13.5px] text-white">dp / full</span>, menerbitkan Snap token, memverifikasi webhook (SHA-512), lalu menyinkronkan status dari server Midtrans — bukan dari tebakan client.</p>
            <ul class="mt-6 space-y-3 text-[14.5px] text-stone-200">
                <li class="flex gap-3"><span class="text-emerald-300 font-bold">✓</span>Status pembayaran hanya maju (monotonik) — tidak bisa mundur diam-diam.</li>
                <li class="flex gap-3"><span class="text-emerald-300 font-bold">✓</span>Biaya tambahan rekam medis punya order & webhook sendiri.</li>
                <li class="flex gap-3"><span class="text-emerald-300 font-bold">✓</span>Ada <span class="font-mono text-[13px]">preflight</span> diagnostik — sebelum demo pembayaran, cek kesiapan dulu.</li>
            </ul>
        </div>
        <div class="reveal bg-white/[.06] border border-white/10 rounded-2xl p-5 font-mono text-[12.5px] leading-relaxed">
            <p class="text-stone-400">// Contoh yang benar-benar dipakai</p>
            <p class="mt-3 text-emerald-200">POST /api/payment/snap-token</p>
            <pre class="mt-2 text-stone-200 whitespace-pre-wrap">{
  "transaction_details": {
    "order_id": "BOOKING-12-1719849600",
    "gross_amount": 75000
  }
}</pre>
            <div class="mt-4 border-t border-white/10 pt-4 space-y-2 text-stone-300">
                <p><span class="text-white">BOOKING-{id}-{waktu}</span> — pembayaran awal</p>
                <p><span class="text-white">BOOKING-{id}-REMAINING-{waktu}</span> — pelunasan sisa</p>
                <p><span class="text-white">MEDREC-{id}-{waktu}</span> — biaya tambahan rekam medis</p>
            </div>
            <p class="mt-4 text-stone-400">Webhook → verifikasi signature → <span class="text-stone-200">sync-status</span> dari Midtrans → notifikasi ke pemilik.</p>
        </div>
    </div>
</section>

<!-- TEKNIS -->
<section id="teknis" class="max-w-6xl mx-auto px-4 sm:px-6 py-14 scroll-mt-20">
    <div class="reveal max-w-2xl">
        <p class="eyebrow text-[11.5px] font-bold uppercase text-pine-800">Di balik layar</p>
        <h2 class="font-serif font-semibold tracking-tight text-[28px] sm:text-[36px] mt-3">Kelebihannya bukan tempelan — ada di keputusan teknisnya</h2>
    </div>
    <div class="mt-8 grid md:grid-cols-3 gap-4">
        <div class="reveal bg-white card rounded-2xl p-6">
            <h3 class="font-serif font-semibold text-[18px]">Satu APK untuk banyak klinik</h3>
            <p class="mt-2.5 text-[14px] text-stone-600 leading-relaxed">Database bersama + kolom <span class="font-mono text-[13px]">clinic_id</span>. Aplikasi mengirim <span class="font-mono text-[13px]">X-Clinic-Slug</span>; server menolak data klinik lain (404/422) dan menolak akun tanpa klinik (403). Tidak perlu build APK per klinik.</p>
        </div>
        <div class="reveal bg-white card rounded-2xl p-6" style="transition-delay:60ms">
            <h3 class="font-serif font-semibold text-[18px]">Kontrak API yang dikunci</h3>
            <p class="mt-2.5 text-[14px] text-stone-600 leading-relaxed">64 endpoint, format respons diseragamkan (<span class="font-mono text-[13px]">success / message / data / pagination</span>), dan dijaga 97 integration test. Android tidak tiba-tiba rusak karena backend berubah.</p>
        </div>
        <div class="reveal bg-white card rounded-2xl p-6" style="transition-delay:120ms">
            <h3 class="font-serif font-semibold text-[18px]">Aman & hemat sejak awal</h3>
            <p class="mt-2.5 text-[14px] text-stone-600 leading-relaxed">Login Firebase + token Sanctum, rate-limit per fitur (auth 8/mnt, payment 10/mnt), audit log tiap aksi admin, cache katalog, ekspor menghormati filter. Semua 100% layanan gratis.</p>
        </div>
    </div>

    <div class="mt-4 bg-white card rounded-2xl p-6 reveal">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h3 class="font-serif font-semibold text-[18px]">Mau coba API-nya langsung?</h3>
                <p class="text-[13.5px] text-stone-500 mt-1">Base URL demo (akhiri dengan <span class="font-mono">/api/</span> di Android):</p>
            </div>
            <div class="ml-auto flex items-center gap-2 w-full sm:w-auto">
                <code id="baseUrl" class="flex-1 sm:flex-none font-mono text-[12.5px] bg-[#F6F1E6] border border-[#E7DFCF] rounded-lg px-3 py-2.5 text-stone-700 truncate">{{ $baseUrl }}/</code>
                <button onclick="navigator.clipboard.writeText(document.getElementById('baseUrl').innerText);this.innerText='Disalin ✓';setTimeout(()=>this.innerText='Salin',1500)" class="shrink-0 text-[13px] font-semibold bg-ink text-white px-4 py-2.5 rounded-lg hover:bg-pine-800 transition-colors">Salin</button>
            </div>
        </div>
        <div class="mt-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-2.5 font-mono text-[12px]">
            <div class="border border-[#E7DFCF] rounded-lg px-3 py-2.5"><span class="font-bold text-emerald-700">GET</span> <span class="text-stone-700">/health</span><span class="block text-stone-400 mt-0.5 font-sans text-[12px]">Cek database, FCM, Midtrans</span></div>
            <div class="border border-[#E7DFCF] rounded-lg px-3 py-2.5"><span class="font-bold text-emerald-700">GET</span> <span class="text-stone-700">/public/clinics</span><span class="block text-stone-400 mt-0.5 font-sans text-[12px]">Daftar klinik (publik)</span></div>
            <div class="border border-[#E7DFCF] rounded-lg px-3 py-2.5"><span class="font-bold text-emerald-700">GET</span> <span class="text-stone-700">/doctors?limit=20</span><span class="block text-stone-400 mt-0.5 font-sans text-[12px]">Per klinik user / slug</span></div>
            <div class="border border-[#E7DFCF] rounded-lg px-3 py-2.5"><span class="font-bold text-emerald-700">GET</span> <span class="text-stone-700">/dashboard</span><span class="block text-stone-400 mt-0.5 font-sans text-[12px]">Ringkasan + vaksin terdekat</span></div>
        </div>
        <p class="mt-3 text-[12.5px] text-stone-500">Header wajib untuk endpoint privat: <code class="font-mono bg-[#F6F1E6] px-1.5 py-0.5 rounded border border-[#E7DFCF]">Authorization: Bearer &lt;token&gt;</code> · Dokumentasi lengkap 64 route ada di berkas <code class="font-mono">API_DOCUMENTATION.md</code> repo.</p>
    </div>
</section>

<!-- FAQ + DEMO -->
<section class="max-w-6xl mx-auto px-4 sm:px-6 pb-14">
    <div class="grid lg:grid-cols-5 gap-4">
        <div class="lg:col-span-3 bg-white card rounded-2xl p-6 sm:p-8 reveal">
            <h2 class="font-serif font-semibold text-[24px] tracking-tight">Pertanyaan yang sering muncul saat demo</h2>
            <div class="mt-5 space-y-3">
                <details class="border border-[#E7DFCF] rounded-xl px-4 py-3.5 group">
                    <summary class="font-semibold text-[14.5px] cursor-pointer flex items-center gap-2">Apakah ini project sungguhan atau sekadar tampilan? <span class="ml-auto text-stone-400 group-open:rotate-45 transition-transform text-lg leading-none">+</span></summary>
                    <p class="mt-2 text-[14px] text-stone-600 leading-relaxed">Sungguhan dan bisa didemo end-to-end: daftar di HP → booking → bayar (sandbox) → konfirmasi di web → rekam medis terbit → notifikasi masuk. Angka statistik di atas halaman ini diambil langsung dari database.</p>
                </details>
                <details class="border border-[#E7DFCF] rounded-xl px-4 py-3.5 group">
                    <summary class="font-semibold text-[14.5px] cursor-pointer flex items-center gap-2">Bagaimana demo multi-kliniknya? <span class="ml-auto text-stone-400 group-open:rotate-45 transition-transform text-lg leading-none">+</span></summary>
                    <p class="mt-2 text-[14px] text-stone-600 leading-relaxed">Buka <span class="font-mono text-[13px]">GET /public/clinics</span>, pilih satu slug, lalu login dengan akun klinik itu. Daftar dokter, layanan, dan booking langsung berganti mengikuti klinik — tanpa ganti APK.</p>
                </details>
                <details class="border border-[#E7DFCF] rounded-xl px-4 py-3.5 group">
                    <summary class="font-semibold text-[14.5px] cursor-pointer flex items-center gap-2">Apakah bayar sesuatu untuk menjalankannya? <span class="ml-auto text-stone-400 group-open:rotate-45 transition-transform text-lg leading-none">+</span></summary>
                    <p class="mt-2 text-[14px] text-stone-600 leading-relaxed">Tidak. MySQL lokal, ngrok free, Firebase Spark, dan Midtrans sandbox — semuanya gratis. Itu keputusan desain yang disengaja agar bisa direplikasi di sekolah.</p>
                </details>
                <details class="border border-[#E7DFCF] rounded-xl px-4 py-3.5 group">
                    <summary class="font-semibold text-[14.5px] cursor-pointer flex items-center gap-2">Akun apa yang dipakai untuk demo admin? <span class="ml-auto text-stone-400 group-open:rotate-45 transition-transform text-lg leading-none">+</span></summary>
                    <p class="mt-2 text-[14px] text-stone-600 leading-relaxed">Lihat kartu “Coba sekarang” di samping — email dan kata sandi demonya tertulis di sana. Cukup login, tidak perlu registrasi ulang.</p>
                </details>
            </div>
        </div>
        <div class="lg:col-span-2 bg-ink text-stone-100 rounded-2xl p-6 sm:p-8 reveal relative overflow-hidden" style="transition-delay:80ms">
            <div class="dotgrid-light absolute inset-0 opacity-25 pointer-events-none"></div>
            <div class="relative">
                <p class="eyebrow text-[11px] font-bold uppercase text-emerald-300">Coba sekarang</p>
                <h2 class="font-serif font-semibold text-[24px] tracking-tight mt-2 text-white">Masuk sebagai admin demo</h2>
                <div class="mt-5 bg-white/[.07] border border-white/10 rounded-xl p-4 font-mono text-[13px] space-y-1.5">
                    <p><span class="text-stone-400">email</span> <span class="text-white">admin@petheal.com</span></p>
                    <p><span class="text-stone-400">password</span> <span class="text-white">admin123</span></p>
                </div>
                <div class="mt-5 grid gap-2.5">
                    <a href="/admin/login" class="text-center bg-white text-ink font-semibold px-5 py-3 rounded-xl hover:bg-emerald-50 transition-colors text-[14.5px]">Login panel admin</a>
                    <a href="/admin/register" class="text-center border border-white/15 font-semibold px-5 py-3 rounded-xl hover:bg-white/5 transition-colors text-[14.5px]">Ajukan klinik bergabung →</a>
                </div>
                <p class="mt-4 text-[12.5px] text-stone-400 leading-relaxed">URL demo memakai ngrok free-tier — kalau suatu saat berubah, ganti <span class="font-mono">BACKEND_BASE_URL</span> di <span class="font-mono">local.properties</span> Android lalu rebuild.</p>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="border-t border-[#E7DFCF] bg-[#F3EDE0]/60">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 grid md:grid-cols-4 gap-8">
        <div class="md:col-span-2">
            <div class="flex items-center">
                <img src="/logo.png" alt="PetHeal — Veterinary Clinic System" class="h-9 w-auto bg-white rounded-lg px-2 py-1 border border-[#E7DFCF]" loading="lazy">
            </div>
            <p class="mt-3 text-[13.5px] text-stone-600 leading-relaxed max-w-sm">Sistem booking klinik hewan multi-klinik: aplikasi Android (Kotlin, Jetpack Compose) + backend Laravel + panel admin web. Dibuat sebagai bahan Uji Kompetensi — didokumentasikan, diuji, dan bisa didemo.</p>
            <p class="mt-4 font-mono text-[12px] text-stone-500">{{ $baseUrl }}/ · /admin · /api/health</p>
        </div>
        <div>
            <p class="text-[12px] font-bold uppercase tracking-widest text-stone-500">Jelajah</p>
            <ul class="mt-3 space-y-2 text-[14px] font-medium">
                <li><a href="#alur" class="hover:text-pine-800">Alur kerja</a></li>
                <li><a href="#fitur" class="hover:text-pine-800">Fitur</a></li>
                <li><a href="#bayar" class="hover:text-pine-800">Pembayaran</a></li>
                <li><a href="#teknis" class="hover:text-pine-800">Teknis</a></li>
            </ul>
        </div>
        <div>
            <p class="text-[12px] font-bold uppercase tracking-widest text-stone-500">Status demo</p>
            <ul class="mt-3 space-y-2 text-[14px] text-stone-600">
                <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>API: menyala</li>
                <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Database: {{ $users }} pengguna</li>
                <li class="mt-3"><a href="/admin/login" class="font-semibold text-pine-800 hover:underline">admin@petheal.com / admin123</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-[#E7DFCF]">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-5 flex flex-col sm:flex-row gap-2 items-center text-[12.5px] text-stone-500">
            <p>© {{ date('Y') }} PetHeal — Veterinary Booking System. Laravel 12 · Firebase · Midtrans Sandbox.</p>
            <p class="sm:ml-auto">Ditulis tangan untuk demo, bukan template generik.</p>
        </div>
    </div>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); }
            });
        }, { threshold: 0.12 });
        document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
    });
</script>

</body>
</html>
