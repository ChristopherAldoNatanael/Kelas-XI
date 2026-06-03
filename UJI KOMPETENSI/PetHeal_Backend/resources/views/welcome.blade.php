<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PetHeal — Sistem Manajemen Klinik Hewan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        display: ['"Plus Jakarta Sans"', 'Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        },
                        surface: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            150: '#eef2f6',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    },
                    animation: {
                        'fade-up': 'fadeUp 0.8s ease-out forwards',
                        'fade-in': 'fadeIn 0.6s ease-out forwards',
                        'float': 'float 3s ease-in-out infinite',
                        'pulse-glow': 'pulseGlow 2s ease-in-out infinite',
                    },
                    keyframes: {
                        fadeUp: {
                            '0%': { opacity: '0', transform: 'translateY(30px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-12px)' },
                        },
                        pulseGlow: {
                            '0%, 100%': { boxShadow: '0 0 20px rgba(16, 185, 129, 0.15)' },
                            '50%': { boxShadow: '0 0 40px rgba(16, 185, 129, 0.3)' },
                        },
                    },
                }
            }
        }
    </script>
    <style>
        html { scroll-behavior: smooth; }

        .hero-gradient {
            background: linear-gradient(135deg, #0f172a 0%, #1a2a3a 40%, #064e3b 100%);
            position: relative;
            overflow: hidden;
        }

        .hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-gradient::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.05) 0%, transparent 70%);
            pointer-events: none;
        }

        .grid-pattern {
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .stat-card:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15);
        }

        .feature-card {
            transition: all 0.4s ease;
            position: relative;
        }

        .feature-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .feature-card:hover::after {
            opacity: 1;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .endpoint-badge {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .nav-blur {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .shimmer {
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.06), transparent);
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
    </style>
</head>
<body class="font-sans antialiased bg-surface-50 text-surface-900">

    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/70 nav-blur border-b border-surface-100/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 lg:h-20">
                <a href="/" class="flex items-center gap-2.5 group">
                    <img src="/logo.png" alt="PetHeal" class="h-9 w-auto" fetchpriority="high" width="180" height="36">
                    <span class="text-xl font-display font-bold text-surface-900 tracking-tight">PetHeal</span>
                </a>

                <div class="flex items-center gap-3 sm:gap-5">
                    <a href="/admin" class="hidden sm:inline-flex items-center gap-1.5 text-sm font-medium text-surface-500 hover:text-surface-900 transition-colors duration-200">
                        Admin Panel
                    </a>
                    <a href="/admin"
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl transition-all duration-200 shadow-sm hover:shadow-md hover:shadow-brand-500/25">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-gradient min-h-[90vh] flex items-center pt-20">
        <div class="grid-pattern absolute inset-0 opacity-40"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-32">
            <div class="lg:grid lg:grid-cols-12 lg:gap-16 items-center">
                <div class="lg:col-span-7">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/5 border border-white/10 text-brand-300 text-sm font-medium mb-6" style="animation: fadeIn 0.6s ease-out forwards;">
                        <span class="w-2 h-2 rounded-full bg-brand-400 animate-pulse"></span>
                        Sistem siap digunakan
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-display font-extrabold text-white leading-[1.1] tracking-tight" style="animation: fadeUp 0.8s ease-out forwards;">
                        Manajemen Klinik
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-300 to-brand-400">Hewan Modern</span>
                    </h1>

                    <p class="mt-6 text-lg sm:text-xl text-surface-300/80 max-w-xl leading-relaxed" style="animation: fadeUp 0.8s ease-out 0.15s forwards; opacity: 0; animation-fill-mode: forwards;">
                        Platform terpadu untuk booking online, rekam medis digital, notifikasi otomatis, dan analitik klinik — dirancang untuk era digital.
                    </p>

                    <div class="mt-10 flex flex-wrap gap-4" style="animation: fadeUp 0.8s ease-out 0.3s forwards; opacity: 0; animation-fill-mode: forwards;">
                        <a href="/admin"
                           class="inline-flex items-center gap-2 px-7 py-3.5 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg shadow-brand-500/20 hover:shadow-xl hover:shadow-brand-500/30 text-base">
                            Buka Dashboard
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                        <a href="#api-docs"
                           class="inline-flex items-center gap-2 px-7 py-3.5 bg-white/5 hover:bg-white/10 text-white/90 hover:text-white font-medium rounded-xl border border-white/10 hover:border-white/20 transition-all duration-200 text-base">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
            Lihat API
                        </a>
                    </div>
                </div>

                <div class="hidden lg:block lg:col-span-5" style="animation: fadeIn 1s ease-out 0.4s forwards; opacity: 0; animation-fill-mode: forwards;">
                    <div class="relative">
                        <div class="absolute inset-0 bg-gradient-to-tr from-brand-500/10 to-transparent rounded-3xl"></div>
                        <div class="relative p-8">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3 p-4 rounded-2xl bg-white/5 border border-white/5">
                                    <div class="w-10 h-10 rounded-xl bg-brand-500/20 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-white/90 text-sm font-medium">Keamanan Data</p>
                                        <p class="text-white/50 text-xs">Enkripsi end-to-end</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-4 rounded-2xl bg-white/5 border border-white/5">
                                    <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-white/90 text-sm font-medium">Respon Cepat</p>
                                        <p class="text-white/50 text-xs">Notifikasi real-time</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-4 rounded-2xl bg-white/5 border border-white/5">
                                    <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-white/90 text-sm font-medium">Performa Tinggi</p>
                                        <p class="text-white/50 text-xs">Optimasi maksimal</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="relative -mt-12 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-surface-100 cursor-default">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-brand-50 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-surface-400">Dokter</p>
                            <p class="text-2xl font-bold text-surface-900 font-display">{{ \App\Models\Doctor::count() }}</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-surface-100 cursor-default">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-surface-400">Booking Hari Ini</p>
                            <p class="text-2xl font-bold text-surface-900 font-display">{{ \App\Models\Booking::whereDate('booking_date', today())->count() }}</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-surface-100 cursor-default">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-surface-400">Pengguna</p>
                            <p class="text-2xl font-bold text-surface-900 font-display">{{ \App\Models\User::count() }}</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-surface-100 cursor-default">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-surface-400">Rekam Medis</p>
                            <p class="text-2xl font-bold text-surface-900 font-display">{{ \App\Models\MedicalRecord::count() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-24 lg:py-32 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto text-center reveal">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-50 text-brand-700 text-sm font-medium mb-4">Fitur Unggulan</span>
                <h2 class="text-3xl sm:text-4xl font-display font-bold text-surface-900 tracking-tight">
                    Semua yang Anda Butuhkan dalam Satu Platform
                </h2>
                <p class="mt-4 text-surface-500 text-lg leading-relaxed">
                    Dari booking hingga rekam medis, kelola klinik hewan Anda dengan efisien.
                </p>
            </div>

            <div class="mt-16 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                <!-- Booking Online -->
                <div class="feature-card bg-white rounded-2xl p-8 border border-surface-100 shadow-sm">
                    <div class="w-14 h-14 rounded-2xl bg-brand-50 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-display font-semibold text-surface-900 mb-2">Booking Online</h3>
                    <p class="text-surface-500 leading-relaxed text-sm">
                        Pilih dokter dan jadwal favorit Anda secara real-time. Dilengkapi notifikasi otomatis ke pemilik hewan.
                    </p>
                </div>

                <!-- Rekam Medis -->
                <div class="feature-card bg-white rounded-2xl p-8 border border-surface-100 shadow-sm">
                    <div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-display font-semibold text-surface-900 mb-2">Rekam Medis Digital</h3>
                    <p class="text-surface-500 leading-relaxed text-sm">
                        Riwayat kesehatan terdokumentasi rapi. Akses kapan saja, dari mana saja dengan aman.
                    </p>
                </div>

                <!-- Notifikasi -->
                <div class="feature-card bg-white rounded-2xl p-8 border border-surface-100 shadow-sm">
                    <div class="w-14 h-14 rounded-2xl bg-purple-50 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-display font-semibold text-surface-900 mb-2">Notifikasi Otomatis</h3>
                    <p class="text-surface-500 leading-relaxed text-sm">
                        Push notification untuk pengingat vaksin, kontrol, dan jadwal melalui Firebase Cloud Messaging.
                    </p>
                </div>

                <!-- Keamanan -->
                <div class="feature-card bg-white rounded-2xl p-8 border border-surface-100 shadow-sm">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-display font-semibold text-surface-900 mb-2">Autentikasi Aman</h3>
                    <p class="text-surface-500 leading-relaxed text-sm">
                        Firebase Authentication dengan Email/Password & Google Sign-In. Enkripsi data tingkat tinggi.
                    </p>
                </div>

                <!-- Analytics -->
                <div class="feature-card bg-white rounded-2xl p-8 border border-surface-100 shadow-sm">
                    <div class="w-14 h-14 rounded-2xl bg-rose-50 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-display font-semibold text-surface-900 mb-2">Dashboard Analytics</h3>
                    <p class="text-surface-500 leading-relaxed text-sm">
                        Pantau performa klinik dengan grafik interaktif dan laporan real-time yang informatif.
                    </p>
                </div>

                <!-- API Mobile -->
                <div class="feature-card bg-white rounded-2xl p-8 border border-surface-100 shadow-sm">
                    <div class="w-14 h-14 rounded-2xl bg-cyan-50 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-display font-semibold text-surface-900 mb-2">Mobile Ready</h3>
                    <p class="text-surface-500 leading-relaxed text-sm">
                        REST API lengkap untuk integrasi dengan aplikasi Android berbasis Jetpack Compose.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- API Documentation Section -->
    <section id="api-docs" class="py-24 lg:py-32 bg-surface-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto text-center reveal">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-sm font-medium mb-4">API Reference</span>
                <h2 class="text-3xl sm:text-4xl font-display font-bold text-surface-900 tracking-tight">
                    REST API Endpoints
                </h2>
                <p class="mt-4 text-surface-500 text-lg leading-relaxed">
                    Base URL: <code class="px-2.5 py-1 bg-surface-100 rounded-lg text-brand-600 font-mono text-sm">{{ config('app.url') }}/api</code>
                </p>
            </div>

            <div class="mt-12 max-w-3xl mx-auto space-y-3 reveal">
                <div class="bg-white rounded-2xl p-5 border border-surface-100 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center gap-4">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-brand-50 text-brand-700">POST</span>
                        <code class="text-sm font-mono text-surface-800 font-medium">/auth/firebase-login</code>
                        <span class="text-xs text-surface-400 ml-auto">Login dengan Firebase ID Token</span>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-surface-100 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center gap-4">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-blue-50 text-blue-700">GET</span>
                        <code class="text-sm font-mono text-surface-800 font-medium">/doctors</code>
                        <span class="text-xs text-surface-400 ml-auto">Daftar semua dokter</span>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-surface-100 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center gap-4">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-brand-50 text-brand-700">POST</span>
                        <code class="text-sm font-mono text-surface-800 font-medium">/bookings</code>
                        <span class="text-xs text-surface-400 ml-auto">Buat booking baru</span>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-surface-100 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center gap-4">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-blue-50 text-blue-700">GET</span>
                        <code class="text-sm font-mono text-surface-800 font-medium">/pets</code>
                        <span class="text-xs text-surface-400 ml-auto">Daftar hewan peliharaan user</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 lg:py-32 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center reveal">
            <div class="bg-gradient-to-br from-brand-900 via-brand-800 to-surface-900 rounded-3xl p-10 sm:p-16 relative overflow-hidden">
                <div class="absolute inset-0 grid-pattern opacity-20"></div>
                <div class="relative">
                    <h2 class="text-3xl sm:text-4xl font-display font-bold text-white tracking-tight">
                        Siap Mengelola Klinik Lebih Efisien?
                    </h2>
                    <p class="mt-4 text-brand-100/80 text-lg max-w-xl mx-auto leading-relaxed">
                        Akses dashboard admin sekarang dan nikmati kemudahan mengelola klinik hewan Anda.
                    </p>
                    <div class="mt-8 flex flex-wrap justify-center gap-4">
                        <a href="/admin"
                           class="inline-flex items-center gap-2 px-7 py-3.5 bg-white hover:bg-brand-50 text-brand-700 font-semibold rounded-xl transition-all duration-200 shadow-lg shadow-black/10 text-base">
                            Masuk ke Dashboard
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-surface-900 border-t border-surface-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                <div class="lg:col-span-1">
                    <a href="/" class="flex items-center gap-2.5 group">
                        <img src="/logo.png" alt="PetHeal" class="h-9 w-auto" fetchpriority="high" width="180" height="36">
                        <span class="text-xl font-display font-bold text-white">PetHeal</span>
                    </a>
                    <p class="mt-4 text-surface-400 text-sm leading-relaxed max-w-xs">
                        Sistem manajemen klinik hewan modern untuk era digital. Cepat, aman, dan terpercaya.
                    </p>
                </div>

                <div class="lg:col-span-2">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-8">
                        <div>
                            <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Platform</h3>
                            <ul class="mt-4 space-y-3">
                                <li><a href="/admin" class="text-sm text-surface-400 hover:text-white transition-colors">Dashboard</a></li>
                                <li><a href="#api-docs" class="text-sm text-surface-400 hover:text-white transition-colors">API</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Fitur</h3>
                            <ul class="mt-4 space-y-3">
                                <li><span class="text-sm text-surface-400">Booking Online</span></li>
                                <li><span class="text-sm text-surface-400">Rekam Medis</span></li>
                                <li><span class="text-sm text-surface-400">Notifikasi</span></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Status</h3>
                            <ul class="mt-4 space-y-3">
                                <li class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-brand-400"></span>
                                    <span class="text-sm text-surface-400">API: Online</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-brand-400"></span>
                                    <span class="text-sm text-surface-400">Server: Active</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-12 pt-8 border-t border-surface-800 flex flex-col sm:flex-row justify-between items-center gap-4">
                <p class="text-sm text-surface-500">
                    &copy; {{ date('Y') }} PetHeal. All rights reserved.
                </p>
                <p class="text-sm text-surface-600">
                    Dibuat dengan <span class="text-brand-400">&hearts;</span> untuk hewan peliharaan
                </p>
            </div>
        </div>
    </footer>

    <!-- Scroll Reveal Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
        });
    </script>

</body>
</html>