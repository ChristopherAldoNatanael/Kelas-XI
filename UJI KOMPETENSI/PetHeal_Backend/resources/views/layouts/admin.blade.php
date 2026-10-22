@php
    $clinic = currentClinic();
    $clinicName = $clinic?->name ?? 'PetHeal';
    $clinicLogo = $clinic?->logo_url;
    $clinicColor = $clinic?->primary_color ?? '#10B981';
    $isSuperAdmin = isSuperAdmin();
@endphp
<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      style="--color-primary: {{ $clinicColor }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" type="image/png" href="/favicon.png">
    <title>@yield('title', $clinicName . ' Admin')</title>

    {{-- Apply the saved theme before first paint so the shell never flashes. --}}
    <script>
        (function () {
            try {
                if (localStorage.getItem('dark-mode') === 'true') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                }
            } catch (e) { /* storage can be unavailable in private mode */ }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-100 h-full">
    <a href="#main-content" class="skip-link">Lewati ke konten</a>
    <div id="toast-container" aria-live="polite" aria-atomic="false"></div>
    <div id="sidebar-backdrop" class="fixed inset-0 z-50 hidden bg-slate-950/50 backdrop-blur-sm lg:hidden"></div>

    <div class="flex h-full">
        <!-- Sidebar -->
        <aside id="sidebar"
               class="flex-shrink-0 flex flex-col border-r border-white/5 h-full overflow-hidden bg-gradient-to-b from-emerald-950 via-teal-950 to-slate-950"
               aria-label="Navigasi utama">
            <div class="sidebar-header p-4 pb-4 flex items-center gap-3 flex-shrink-0">
                <div class="logo-text-wrap flex items-center gap-3 flex-1 min-w-0">
                    <a href="{{ route('admin.dashboard') }}" class="logo-link bg-white p-1.5 rounded-lg block hover:bg-slate-200 transition-colors flex-shrink-0">
                        @if($clinicLogo)
                            <img src="{{ $clinicLogo }}" alt="{{ $clinicName }}" class="h-7 w-auto block rounded" fetchpriority="high" width="158" height="56">
                        @else
                            <img src="/logo.png" alt="{{ $clinicName }}" class="h-7 w-auto block rounded" fetchpriority="high" width="158" height="56">
                        @endif
                    </a>
                    <span class="sidebar-text font-semibold text-lg text-white tracking-tight truncate">{{ $clinicName }}</span>
                </div>

                {{-- Desktop: collapse rail. Mobile: close drawer. --}}
                <button id="sidebar-toggle" class="sidebar-toggle hidden lg:flex text-slate-400 hover:text-white transition-all p-1.5 rounded-lg hover:bg-white/10 flex-shrink-0"
                        title="Ciutkan sidebar" aria-label="Ciutkan sidebar" aria-expanded="true">
                    <span class="material-symbols-outlined sidebar-toggle-icon text-xl" aria-hidden="true">chevron_left</span>
                </button>
                <button id="sidebar-close" class="lg:hidden text-slate-400 hover:text-white transition-all p-1.5 rounded-lg hover:bg-white/10 flex-shrink-0"
                        title="Tutup menu" aria-label="Tutup menu">
                    <span class="material-symbols-outlined text-xl" aria-hidden="true">close</span>
                </button>
            </div>

            <nav class="flex-1 px-3 space-y-0.5 overflow-y-auto custom-scrollbar pb-4">
                @if($isSuperAdmin && !$clinic)
                <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-4 mt-2">{{ __('menu.super_admin') }}</p>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ request()->routeIs('admin.dashboard') ? 'active-nav-item' : 'font-medium' }} relative"
                   href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.dashboard'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">dashboard</span>
                    <span class="sidebar-text text-sm">{{ __('menu.system_overview') }}</span>
                </a>
                @else
                <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-4 mt-2">{{ $clinicName }}</p>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ request()->routeIs('admin.dashboard') ? 'active-nav-item' : 'font-medium' }} relative"
                   href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.dashboard'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">grid_view</span>
                    <span class="sidebar-text text-sm">{{ __('menu.overview') }}</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.users.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.users.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">group</span>
                    <span class="sidebar-text">{{ __('menu.patients') }}</span>
                </a>
                {{-- Satu entri untuk booking: daftar dan papan harian ada di
                     dalam halaman yang sama lewat tab, bukan dua menu terpisah. --}}
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.bookings.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.bookings.index') }}" @if(request()->routeIs('admin.bookings.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.bookings.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">calendar_month</span>
                    <span class="sidebar-text">{{ __('menu.appointments_schedule') }}</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.doctors.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.doctors.index') }}" @if(request()->routeIs('admin.doctors.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.doctors.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">medical_services</span>
                    <span class="sidebar-text">{{ __('menu.veterinarians') }}</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.services.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.services.index') }}" @if(request()->routeIs('admin.services.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.services.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">price_change</span>
                    <span class="sidebar-text text-sm">{{ __('menu.services') }}</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.medical-records.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.medical-records.index') }}" @if(request()->routeIs('admin.medical-records.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.medical-records.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">clinical_notes</span>
                    <span class="sidebar-text">{{ __('menu.medical_records') }}</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.payments.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.payments.index') }}" @if(request()->routeIs('admin.payments.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.payments.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">payments</span>
                    <span class="sidebar-text">{{ __('menu.payments') }}</span>
                </a>
                @endif
                <div class="pt-8">
                    <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-4">{{ __('menu.administration') }}</p>
                    @if($isSuperAdmin)
                    <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.clinics.*') ? 'active-nav-item' : '' }} relative"
                       href="{{ route('admin.clinics.index') }}" @if(request()->routeIs('admin.clinics.*')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.clinics.*'))
                        <div class="active-indicator" aria-hidden="true"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">local_hospital</span>
                        <span class="sidebar-text">{{ __('menu.clinics') }}</span>
                    </a>
                    <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.join-requests') ? 'active-nav-item' : '' }} relative"
                       href="{{ route('admin.join-requests') }}" @if(request()->routeIs('admin.join-requests')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.join-requests'))
                        <div class="active-indicator" aria-hidden="true"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">person_add</span>
                        <span class="sidebar-text">{{ __('menu.join_requests') }}</span>
                    </a>
                    @endif
                    {{-- Profil klinik bukan halaman terpisah lagi: hidup sebagai
                         tab di Pengaturan supaya tidak ada dua halaman data klinik. --}}
                    <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.audit-logs') ? 'active-nav-item' : '' }} relative"
                       href="{{ route('admin.audit-logs') }}" @if(request()->routeIs('admin.audit-logs')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.audit-logs'))
                        <div class="active-indicator" aria-hidden="true"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">history</span>
                        <span class="sidebar-text">{{ __('menu.audit_logs') }}</span>
                    </a>
                    <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.settings') || request()->routeIs('admin.clinic-profile') ? 'active-nav-item' : '' }} relative"
                       href="{{ route('admin.settings') }}" @if(request()->routeIs('admin.settings') || request()->routeIs('admin.clinic-profile')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.settings') || request()->routeIs('admin.clinic-profile'))
                        <div class="active-indicator" aria-hidden="true"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">settings</span>
                        <span class="sidebar-text">{{ __('menu.settings') }}</span>
                    </a>
                    {{-- Notification templates are global + super_admin-only; keep the link out of clinic admin sight. --}}
                    @if($isSuperAdmin)
                    <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.notification-settings.*') ? 'active-nav-item' : '' }} relative"
                       href="{{ route('admin.notification-settings.index') }}" @if(request()->routeIs('admin.notification-settings.*')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.notification-settings.*'))
                        <div class="active-indicator" aria-hidden="true"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">notifications_active</span>
                        <span class="sidebar-text">{{ __('menu.notifications') }}</span>
                    </a>
                    @endif
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium w-full text-left">
                            <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">logout</span>
                            <span class="sidebar-text">{{ __('menu.logout') }}</span>
                        </button>
                    </form>
                </div>
            </nav>

            <div class="p-4 flex-shrink-0 border-t border-white/5">
                <div class="user-info-card bg-white/5 border border-white/5 p-3 rounded-xl flex items-center gap-3">
                    <div class="h-8 w-8 rounded-full bg-primary flex items-center justify-center text-white text-sm font-semibold flex-shrink-0" aria-hidden="true">
                        {{ \Illuminate\Support\Str::substr(Auth::user()->name ?? 'A', 0, 1) }}
                    </div>
                    <div class="user-info-text flex-1 min-w-0">
                        <p class="text-xs font-medium text-white/90 truncate">{{ Auth::user()->name ?? 'Admin' }}</p>
                        <p class="text-[9px] text-slate-500 truncate uppercase tracking-widest font-bold">
                            @if($isSuperAdmin)
                                Super Admin
                            @else
                                {{ $clinicName }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main id="main-content" class="flex-1 flex flex-col h-full overflow-hidden min-w-0">
            <header class="h-14 bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border-b border-slate-200/50 dark:border-slate-800 flex items-center justify-between gap-3 px-4 sm:px-6 flex-shrink-0">
                <div class="flex items-center gap-2 min-w-0">
                    <button id="sidebar-open" class="lg:hidden -ml-1 flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                        <span class="material-symbols-outlined text-xl" aria-hidden="true">menu</span>
                    </button>
                    <h1 class="text-base font-semibold text-slate-900 dark:text-white tracking-tight truncate">@yield('header', 'Executive Dashboard')</h1>
                </div>
                <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0">
                    <button id="commandPaletteTrigger" type="button"
                            class="flex items-center gap-2 h-9 px-2.5 sm:px-3 rounded-lg text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300 dark:hover:border-slate-600 transition-colors"
                            aria-label="Buka pencarian global" aria-haspopup="dialog" aria-controls="commandPalette">
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">search</span>
                        <span class="hidden md:inline text-xs font-semibold">Cari booking, hewan, pemilik</span>
                        <kbd class="hidden lg:inline-flex items-center px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-600 text-[9px] font-bold text-slate-400">Ctrl K</kbd>
                    </button>
                    @if($isSuperAdmin)
                    <form method="POST" action="{{ route('admin.switch-clinic') }}" id="clinic-switcher-form" class="hidden md:flex items-center gap-2">
                        @csrf
                        <span class="material-symbols-outlined text-sm text-slate-400" aria-hidden="true">swap_horiz</span>
                        <select name="clinic_id" id="clinic-switcher" onchange="document.getElementById('clinic-switcher-form').submit()"
                                aria-label="Pilih klinik"
                                class="bg-slate-50 dark:bg-slate-800 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-400 border border-slate-200/60 dark:border-slate-700 cursor-pointer outline-none">
                            <option value="" {{ session('current_clinic_id') ? '' : 'selected' }}>— System Overview —</option>
                            @foreach(($allClinics ?? collect()) as $c)
                                <option value="{{ $c->id }}" {{ ($clinic?->id === $c->id) ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </form>
                    @else
                    <div class="hidden md:flex items-center gap-2 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-400 border border-slate-200/60 dark:border-slate-700">
                        <span class="material-symbols-outlined text-sm" aria-hidden="true">local_hospital</span>
                        <span class="max-w-[180px] truncate">{{ $clinicName }}</span>
                    </div>
                    @endif
                    <div class="hidden md:flex items-center gap-2 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-400 border border-slate-200/60 dark:border-slate-700">
                        <span class="material-symbols-outlined text-sm" aria-hidden="true">calendar_month</span>
                        <span>{{ now()->format('d M Y') }}</span>
                    </div>
                    {{-- Language switcher preserves the current query string (filters, dates). --}}
                    <div class="flex items-center gap-1" aria-label="Pilih bahasa">
                        <a href="{{ request()->fullUrlWithQuery(['lang' => 'id']) }}"
                           class="px-2 py-1 rounded text-[10px] font-bold transition-all {{ app()->getLocale() === 'id' ? 'bg-primary text-white' : 'text-slate-400 hover:text-slate-600' }}"
                           @if(app()->getLocale() === 'id') aria-current="true" @endif>ID</a>
                        <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}"
                           class="px-2 py-1 rounded text-[10px] font-bold transition-all {{ app()->getLocale() === 'en' ? 'bg-primary text-white' : 'text-slate-400 hover:text-slate-600' }}"
                           @if(app()->getLocale() === 'en') aria-current="true" @endif>EN</a>
                    </div>
                    <button id="darkModeToggle" class="flex items-center justify-center w-9 h-9 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all"
                            title="Ubah mode gelap" aria-label="Ubah mode gelap">
                        <span class="material-symbols-outlined text-lg" id="darkModeIcon" aria-hidden="true">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                @yield('content')
            </div>
        </main>
    </div>

    {{-- Global command palette: booking, pet, owner search scoped by clinic. --}}
    <div id="commandPalette" class="fixed inset-0 z-[80] hidden bg-slate-950/50 backdrop-blur-sm p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="commandPaletteLabel" aria-hidden="true">
        <div class="mx-auto mt-[8vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200/70 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-2xl">
            <div class="flex items-center gap-3 px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-slate-800">
                <span class="material-symbols-outlined text-slate-400" aria-hidden="true">search</span>
                <label id="commandPaletteLabel" for="commandPaletteInput" class="sr-only">Pencarian global</label>
                <input id="commandPaletteInput" type="text" autocomplete="off" spellcheck="false"
                       placeholder="Cari booking, hewan, atau pemilik..."
                       class="flex-1 bg-transparent text-sm text-slate-900 dark:text-white placeholder:text-slate-400 outline-none">
                <span id="commandPaletteSpinner" class="hidden">
                    <span class="block h-4 w-4 rounded-full border-2 border-slate-200 border-t-primary animate-spin" aria-label="Mencari"></span>
                </span>
                <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-600 text-[9px] font-bold text-slate-400">ESC</kbd>
            </div>

            <div id="commandPaletteBody" class="max-h-[420px] overflow-y-auto custom-scrollbar p-2"></div>

            <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-t border-slate-100 dark:border-slate-800 text-[10px] text-slate-400">
                <span class="hidden sm:inline">Gunakan <kbd class="font-bold">↑</kbd> <kbd class="font-bold">↓</kbd> untuk memilih, <kbd class="font-bold">Enter</kbd> untuk membuka.</span>
                <span class="sm:hidden">Ketuk hasil untuk membuka.</span>
                <span class="font-semibold">Hanya data klinik Anda</span>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            const openBtn = document.getElementById('sidebar-open');
            const closeBtn = document.getElementById('sidebar-close');
            const toggleBtn = document.getElementById('sidebar-toggle');
            if (!sidebar) return;

            const desktop = window.matchMedia('(min-width: 1024px)');

            function setDrawer(open) {
                sidebar.classList.toggle('is-open', open);
                document.body.classList.toggle('sidebar-open', open);
                backdrop.classList.toggle('hidden', !open);
                if (openBtn) openBtn.setAttribute('aria-expanded', String(open));
            }

            function setCollapsed(collapsed) {
                sidebar.classList.toggle('sidebar-collapsed', collapsed);
                if (toggleBtn) toggleBtn.setAttribute('aria-expanded', String(!collapsed));
                try {
                    localStorage.setItem('sidebar-collapsed', String(collapsed));
                } catch (e) { /* ignore */ }
            }

            try {
                setCollapsed(localStorage.getItem('sidebar-collapsed') === 'true');
            } catch (e) { /* ignore */ }

            if (openBtn) openBtn.addEventListener('click', function () { setDrawer(true); });
            if (closeBtn) closeBtn.addEventListener('click', function () { setDrawer(false); });
            if (backdrop) backdrop.addEventListener('click', function () { setDrawer(false); });
            if (toggleBtn) toggleBtn.addEventListener('click', function () {
                setCollapsed(!sidebar.classList.contains('sidebar-collapsed'));
            });

            sidebar.querySelectorAll('a[href]').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (!desktop.matches) setDrawer(false);
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') setDrawer(false);
            });

            desktop.addEventListener('change', function (event) {
                if (event.matches) setDrawer(false);
            });
        })();
    </script>

    <script>
        (function () {
            const html = document.documentElement;
            const toggle = document.getElementById('darkModeToggle');
            const icon = document.getElementById('darkModeIcon');

            function applyDark(isDark) {
                html.classList.toggle('dark', isDark);
                html.classList.toggle('light', !isDark);
                if (icon) icon.textContent = isDark ? 'light_mode' : 'dark_mode';
                try {
                    localStorage.setItem('dark-mode', String(isDark));
                } catch (e) { /* ignore */ }
                window.dispatchEvent(new CustomEvent('petheal:themechange', { detail: { isDark: isDark } }));
            }

            applyDark(html.classList.contains('dark'));

            if (toggle) {
                toggle.addEventListener('click', function () {
                    applyDark(!html.classList.contains('dark'));
                });
            }
        })();
    </script>

    <script>
        function showToast(message, type) {
            type = type || 'success';
            const container = document.getElementById('toast-container');
            if (!container) return;

            const icons = { success: 'check_circle', error: 'error', info: 'info', warning: 'warning' };
            const toast = document.createElement('div');
            toast.className = 'toast toast-' + type;
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

            const icon = document.createElement('span');
            icon.className = 'material-symbols-outlined text-base flex-shrink-0';
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = icons[type] || 'info';

            const text = document.createElement('span');
            text.className = 'flex-1 text-sm';
            text.textContent = message;

            const close = document.createElement('button');
            close.className = 'toast-close material-symbols-outlined';
            close.type = 'button';
            close.setAttribute('aria-label', 'Tutup notifikasi');
            close.textContent = 'close';
            close.addEventListener('click', function () { toast.remove(); });

            toast.append(icon, text, close);
            container.appendChild(toast);
            requestAnimationFrame(function () { toast.classList.add('show'); });
            setTimeout(function () {
                toast.classList.remove('show');
                setTimeout(function () { toast.remove(); }, 350);
            }, 4500);
        }

        document.addEventListener('DOMContentLoaded', function () {
            @if(session('success'))
                showToast(@json(session('success')), 'success');
            @endif
            @if(session('error'))
                showToast(@json(session('error')), 'error');
            @endif
            @if(session('warning'))
                showToast(@json(session('warning')), 'warning');
            @endif
        });
    </script>
    <script>
        (function () {
            const modal = document.getElementById('commandPalette');
            const input = document.getElementById('commandPaletteInput');
            const body = document.getElementById('commandPaletteBody');
            const trigger = document.getElementById('commandPaletteTrigger');
            const spinner = document.getElementById('commandPaletteSpinner');
            if (!modal || !input || !body || !trigger) return;

            const searchUrl = @json(route('admin.search'));
            const quickActions = [
                {
                    type: 'action',
                    title: 'Booking — Papan Jadwal',
                    subtitle: 'Lihat kunjungan hari ini per dokter dan jam',
                    url: @json(route('admin.bookings.schedule')),
                    icon: 'calendar_view_day'
                },
                {
                    type: 'action',
                    title: 'Booking — Daftar',
                    subtitle: 'Cari dan kelola seluruh janji temu',
                    url: @json(route('admin.bookings.index')),
                    icon: 'calendar_today'
                },
                {
                    type: 'action',
                    title: 'Invoice & Pembayaran',
                    subtitle: 'Tagihan, DP, dan bukti pembayaran',
                    url: @json(route('admin.payments.index')),
                    icon: 'receipt_long'
                }
            ];

            let results = [];
            let activeIndex = -1;
            let debounceTimer = null;
            let requestSeq = 0;
            let controller = null;

            function isOpen() {
                return !modal.classList.contains('hidden');
            }

            function openPalette() {
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
                input.value = '';
                renderQuickActions();
                setTimeout(function () { input.focus(); }, 30);
            }

            function closePalette() {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
                if (controller) controller.abort();
                spinner.classList.add('hidden');
                input.value = '';
                results = [];
                activeIndex = -1;
            }

            function navigate(url) {
                if (!url) return;
                window.location.href = url;
            }

            function createHeading(text) {
                const heading = document.createElement('p');
                heading.className = 'px-3 pt-3 pb-1 text-[9px] font-bold uppercase tracking-[0.18em] text-slate-400';
                heading.textContent = text;
                return heading;
            }

            function createResultRow(item, index) {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.commandItem = 'true';
                button.dataset.index = String(index);
                button.className = 'command-result w-full flex items-start gap-3 rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/70';
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');

                const iconWrap = document.createElement('span');
                iconWrap.className = 'w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-300 flex items-center justify-center flex-shrink-0';
                const icon = document.createElement('span');
                icon.className = 'material-symbols-outlined text-[17px]';
                icon.textContent = item.icon || (item.type === 'pet' ? 'pets' : item.type === 'owner' ? 'person' : 'calendar_today');
                iconWrap.appendChild(icon);

                const copy = document.createElement('span');
                copy.className = 'min-w-0 flex-1';
                const title = document.createElement('span');
                title.className = 'block text-sm font-semibold text-slate-800 dark:text-slate-100 truncate';
                title.textContent = item.title || 'Tanpa judul';
                const subtitle = document.createElement('span');
                subtitle.className = 'block text-[11px] text-slate-500 truncate mt-0.5';
                subtitle.textContent = item.subtitle || '';
                copy.append(title, subtitle);

                const type = document.createElement('span');
                type.className = 'text-[9px] font-bold uppercase tracking-wider text-slate-400 flex-shrink-0 mt-1';
                type.textContent = item.type === 'booking' ? 'Booking' : item.type === 'pet' ? 'Hewan' : item.type === 'owner' ? 'Pemilik' : 'Aksi';

                button.append(iconWrap, copy, type);
                button.addEventListener('mouseenter', function () { setActive(index, false); });
                button.addEventListener('click', function () { navigate(item.url); });
                return button;
            }

            function setActive(index, scroll) {
                if (!results.length) return;
                const bounded = Math.max(0, Math.min(index, results.length - 1));
                activeIndex = bounded;
                body.querySelectorAll('[data-command-item]').forEach(function (node) {
                    const isActive = Number(node.dataset.index) === bounded;
                    node.classList.toggle('bg-slate-50', isActive);
                    node.classList.toggle('dark:bg-slate-800/70', isActive);
                    node.classList.toggle('ring-1', isActive);
                    node.classList.toggle('ring-primary/30', isActive);
                    node.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    if (isActive && scroll) node.scrollIntoView({ block: 'nearest' });
                });
            }

            function renderQuickActions() {
                body.replaceChildren();
                results = quickActions;
                activeIndex = -1;
                body.appendChild(createHeading('Aksi cepat'));
                quickActions.forEach(function (item, index) {
                    body.appendChild(createResultRow(item, index));
                });
                const hint = document.createElement('p');
                hint.className = 'px-3 pt-3 pb-2 text-[11px] text-slate-400';
                hint.textContent = 'Ketik minimal 2 karakter untuk mencari booking, hewan, atau pemilik.';
                body.appendChild(hint);
            }

            function renderGroups(payload) {
                const groups = [
                    { label: 'Booking', items: payload.bookings || [] },
                    { label: 'Hewan', items: payload.pets || [] },
                    { label: 'Pemilik', items: payload.owners || [] }
                ];
                const allItems = groups.reduce(function (items, group) { return items.concat(group.items); }, []);
                if (!allItems.length) {
                    renderEmpty();
                    return;
                }

                body.replaceChildren();
                results = allItems;
                activeIndex = -1;
                let index = 0;

                groups.forEach(function (group) {
                    if (!group.items.length) return;
                    body.appendChild(createHeading(group.label));
                    group.items.forEach(function (item) {
                        body.appendChild(createResultRow(item, index));
                        index += 1;
                    });
                });
                setActive(0, false);
            }

            function renderMessage(title, message, withRetry) {
                body.replaceChildren();
                results = [];
                activeIndex = -1;
                const wrap = document.createElement('div');
                wrap.className = 'px-4 py-10 text-center';
                const icon = document.createElement('span');
                icon.className = 'material-symbols-outlined text-slate-300 text-4xl';
                icon.textContent = withRetry ? 'cloud_off' : 'search_off';
                const heading = document.createElement('p');
                heading.className = 'text-sm font-semibold text-slate-700 dark:text-slate-200 mt-2';
                heading.textContent = title;
                const copy = document.createElement('p');
                copy.className = 'text-[11px] text-slate-500 mt-1';
                copy.textContent = message;
                wrap.append(icon, heading, copy);
                if (withRetry) {
                    const retry = document.createElement('button');
                    retry.type = 'button';
                    retry.className = 'mt-4 px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors';
                    retry.textContent = 'Coba lagi';
                    retry.addEventListener('click', function () { runSearch(input.value); });
                    wrap.appendChild(retry);
                }
                body.appendChild(wrap);
            }

            function renderEmpty() {
                renderMessage('Tidak ada hasil', 'Coba nama hewan, nama pemilik, atau nomor booking lain.', false);
            }

            function runSearch(query) {
                const term = (query || '').trim();
                if (term.length < 2) {
                    requestSeq += 1;
                    if (controller) controller.abort();
                    spinner.classList.add('hidden');
                    renderQuickActions();
                    return;
                }

                const seq = ++requestSeq;
                if (controller) controller.abort();
                controller = new AbortController();
                spinner.classList.remove('hidden');

                fetch(searchUrl + '?q=' + encodeURIComponent(term), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Search failed');
                        return response.json();
                    })
                    .then(function (payload) {
                        if (seq !== requestSeq) return;
                        if (payload.message) {
                            renderMessage('Pilih klinik', payload.message, false);
                            return;
                        }
                        renderGroups(payload.data || {});
                    })
                    .catch(function (error) {
                        if (error.name === 'AbortError') return;
                        if (seq !== requestSeq) return;
                        renderMessage('Pencarian gagal', 'Periksa koneksi lalu coba lagi.', true);
                    })
                    .finally(function () {
                        if (seq === requestSeq) spinner.classList.add('hidden');
                    });
            }

            trigger.addEventListener('click', function () {
                if (isOpen()) closePalette();
                else openPalette();
            });

            input.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                const value = input.value;
                debounceTimer = setTimeout(function () { runSearch(value); }, 180);
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    setActive(activeIndex + 1, true);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    setActive(activeIndex - 1, true);
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    if (results.length) {
                        navigate(results[activeIndex >= 0 ? activeIndex : 0].url);
                    }
                }
            });

            document.addEventListener('keydown', function (event) {
                const isShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';
                if (isShortcut) {
                    event.preventDefault();
                    if (isOpen()) closePalette();
                    else openPalette();
                    return;
                }
                if (event.key === 'Escape' && isOpen()) {
                    closePalette();
                }
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) closePalette();
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
