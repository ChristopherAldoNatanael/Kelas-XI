<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PetHeal Admin')</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style type="text/tailwindcss">
        :root {
            --primary: #10B981;
            --primary-muted: #059669;
            --sidebar-text: #94A3B8;
            --sidebar-active-text: #FFFFFF;
            --glass-bg: rgba(255, 255, 255, 0.8);
            --glass-border: rgba(226, 232, 240, 0.6);
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            letter-spacing: -0.011em;
        }
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04);
        }
        .nav-link {
            color: var(--sidebar-text);
            letter-spacing: 0.025em;
            transition: all 0.2s ease;
        }
        .nav-link:hover {
            color: var(--sidebar-active-text);
            background-color: rgba(255, 255, 255, 0.05);
        }
        .nav-link .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 200, 'GRAD' 0, 'opsz' 24;
            transition: all 0.2s ease;
        }
        .active-nav-item {
            color: var(--sidebar-active-text);
            background-color: rgba(16, 185, 129, 0.08);
            position: relative;
        }
        .active-nav-item .material-symbols-outlined {
            color: var(--primary);
            font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
        }
        .active-indicator {
            position: absolute;
            left: 0;
            top: 25%;
            height: 50%;
            width: 3px;
            background: var(--primary);
            border-radius: 0 2px 2px 0;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 3px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
        }

        /* Sidebar collapse */
        #sidebar {
            width: 16rem;
            transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #sidebar.sidebar-collapsed {
            width: 5rem;
        }

        .sidebar-text {
            transition: opacity 0.2s ease, width 0.2s ease, padding 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
        }
        #sidebar.sidebar-collapsed .sidebar-text {
            opacity: 0;
            width: 0;
            padding: 0;
            transition-delay: 0s;
        }
        #sidebar:not(.sidebar-collapsed) .sidebar-text {
            opacity: 1;
            width: auto;
            transition-delay: 0.12s;
        }

        #sidebar.sidebar-collapsed .nav-link {
            justify-content: center;
            padding: 0.625rem;
            gap: 0;
        }
        #sidebar.sidebar-collapsed .nav-link .material-symbols-outlined {
            font-size: 1.25rem;
        }
        #sidebar.sidebar-collapsed nav {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        #sidebar.sidebar-collapsed .section-label {
            display: none;
        }

        #sidebar.sidebar-collapsed .logo-text-wrap {
            justify-content: center;
            gap: 0;
        }
        #sidebar.sidebar-collapsed .logo-link {
            padding: 0.25rem;
        }
        #sidebar.sidebar-collapsed .logo-link img {
            height: 1.375rem;
        }

        #sidebar.sidebar-collapsed .user-info-text {
            display: none;
        }
        #sidebar.sidebar-collapsed .user-info-card {
            justify-content: center;
            padding: 0.5rem;
            gap: 0;
        }

        #sidebar.sidebar-collapsed .sidebar-header {
            padding: 0.75rem 0.5rem;
            gap: 0.25rem;
        }
        #sidebar.sidebar-collapsed .sidebar-toggle {
            padding: 0.25rem;
        }
        #sidebar.sidebar-collapsed .sidebar-toggle .sidebar-toggle-icon {
            font-size: 1rem;
        }
        #sidebar.sidebar-collapsed .sidebar-toggle-icon {
            transform: rotate(180deg);
        }

        .sidebar-toggle-icon {
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .dark .glass-card {
            background: rgba(15, 23, 42, 0.8);
            border-color: rgba(51, 65, 85, 0.5);
        }

        /* Toast notifications */
        #toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            pointer-events: none;
        }
        .toast {
            pointer-events: auto;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            transform: translateX(120%);
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.35s ease;
            max-width: 420px;
            border: 1px solid;
        }
        .toast.show { transform: translateX(0); }
        .toast.toast-success { background: #ECFDF5; border-color: #A7F3D0; color: #065F46; }
        .toast.toast-error { background: #FEF2F2; border-color: #FCA5A5; color: #991B1B; }
        .toast.toast-info { background: #EFF6FF; border-color: #93C5FD; color: #1E40AF; }
        .toast.toast-warning { background: #FFFBEB; border-color: #FDE68A; color: #92400E; }
        .toast .toast-close {
            margin-left: auto;
            cursor: pointer;
            opacity: 0.4;
            transition: opacity 0.2s;
            background: none;
            border: none;
            display: flex;
            align-items: center;
            padding: 0.125rem;
            color: inherit;
            font-size: 1.125rem;
        }
        .toast .toast-close:hover { opacity: 1; }


    </style>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#10B981",
                        secondary: "#3B82F6",
                        "background-light": "#F8FAFC",
                        "background-dark": "#0B0E11",
                    },
                    borderRadius: {
                        DEFAULT: "10px",
                        'xl': '14px',
                        '2xl': '18px',
                    },
                },
            },
        };
    </script>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-100 h-full">
    <div id="toast-container"></div>
    <div class="flex h-full">
        <!-- Sidebar -->
        <aside id="sidebar" class="flex-shrink-0 flex flex-col border-r border-white/5 h-full overflow-hidden bg-gradient-to-b from-emerald-950 via-teal-950 to-slate-950">
            <div class="sidebar-header p-4 pb-4 flex items-center gap-3 flex-shrink-0">
                <div class="logo-text-wrap flex items-center gap-3 flex-1">
                    <a href="/" class="logo-link bg-white/10 p-1.5 rounded-lg block hover:bg-white/20 transition-colors flex-shrink-0">
                        <img src="/logo.png" alt="PetHeal" class="h-7 w-auto block" fetchpriority="high" width="140" height="28">
                    </a>
                    <span class="sidebar-text font-semibold text-lg text-white tracking-tight">PetHeal</span>
                </div>
                <button id="sidebar-toggle" class="sidebar-toggle text-slate-400 hover:text-white transition-all p-1.5 rounded-lg hover:bg-white/10 flex-shrink-0" title="Toggle sidebar">
                    <span class="material-symbols-outlined sidebar-toggle-icon text-xl">chevron_left</span>
                </button>
            </div>

            <nav class="flex-1 px-3 space-y-0.5 overflow-y-auto custom-scrollbar pb-4">
                <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-4 mt-2">Core Dashboard</p>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ request()->routeIs('admin.dashboard') ? 'active-nav-item' : 'font-medium' }} relative" href="{{ route('admin.dashboard') }}">
                    @if(request()->routeIs('admin.dashboard'))
                    <div class="active-indicator"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0">grid_view</span>
                    <span class="sidebar-text text-sm">Overview</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.users.*') ? 'active-nav-item' : '' }} relative" href="{{ route('admin.users.index') }}">
                    @if(request()->routeIs('admin.users.*'))
                    <div class="active-indicator"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0">group</span>
                    <span class="sidebar-text">Patients</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.bookings.*') ? 'active-nav-item' : '' }} relative" href="{{ route('admin.bookings.index') }}">
                    @if(request()->routeIs('admin.bookings.*'))
                    <div class="active-indicator"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0">calendar_today</span>
                    <span class="sidebar-text">Appointments</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.doctors.*') ? 'active-nav-item' : '' }} relative" href="{{ route('admin.doctors.index') }}">
                    @if(request()->routeIs('admin.doctors.*'))
                    <div class="active-indicator"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0">medical_services</span>
                    <span class="sidebar-text">Veterinarians</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.services.*') ? 'active-nav-item' : '' }} relative" href="{{ route('admin.services.index') }}">
                    @if(request()->routeIs('admin.services.*'))
                    <div class="active-indicator"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0">price_change</span>
                    <span class="sidebar-text text-sm">Services</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.medical-records.*') ? 'active-nav-item' : '' }} relative" href="{{ route('admin.medical-records.index') }}">
                    @if(request()->routeIs('admin.medical-records.*'))
                    <div class="active-indicator"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0">clinical_notes</span>
                    <span class="sidebar-text">Medical Records</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.payments.*') ? 'active-nav-item' : '' }} relative" href="{{ route('admin.payments.index') }}">
                    @if(request()->routeIs('admin.payments.*'))
                    <div class="active-indicator"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0">payments</span>
                    <span class="sidebar-text">Payments</span>
                </a>
                <div class="pt-8">
                    <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-4">Administration</p>
                    <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.audit-logs') ? 'active-nav-item' : '' }} relative" href="{{ route('admin.audit-logs') }}">
                        @if(request()->routeIs('admin.audit-logs'))
                        <div class="active-indicator"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] flex-shrink-0">history</span>
                        <span class="sidebar-text">Audit Logs</span>
                    </a>
                    <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.settings') ? 'active-nav-item' : '' }} relative" href="{{ route('admin.settings') }}">
                        @if(request()->routeIs('admin.settings'))
                        <div class="active-indicator"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] flex-shrink-0">settings</span>
                        <span class="sidebar-text">Settings</span>
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium w-full text-left">
                            <span class="material-symbols-outlined text-[20px] flex-shrink-0">logout</span>
                            <span class="sidebar-text">Logout</span>
                        </button>
                    </form>
                </div>
            </nav>

            <div class="p-4 flex-shrink-0 border-t border-white/5">
                <div class="user-info-card bg-white/5 border border-white/5 p-3 rounded-xl flex items-center gap-3">
                    <div class="h-8 w-8 rounded-full bg-primary flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                        {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                    </div>
                    <div class="user-info-text flex-1 min-w-0">
                        <p class="text-xs font-medium text-white/90 truncate">{{ Auth::user()->name ?? 'Admin' }}</p>
                        <p class="text-[9px] text-slate-500 truncate uppercase tracking-widest font-bold">Administrator</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col h-full overflow-hidden">
            <header class="h-14 bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border-b border-slate-200/50 dark:border-slate-800 flex items-center justify-between px-6 flex-shrink-0">
                <div>
                    <h1 class="text-base font-semibold text-slate-900 dark:text-white tracking-tight">@yield('header', 'Executive Dashboard')</h1>
                </div>
                <div class="flex items-center gap-4">
                    <div class="hidden md:flex items-center gap-2 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-400 border border-slate-200/60 dark:border-slate-700">
                        <span class="material-symbols-outlined text-sm">calendar_month</span>
                        <span>{{ now()->format('M d, Y') }}</span>
                    </div>
                    <button id="darkModeToggle" class="flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" title="Toggle Dark Mode">
                        <span class="material-symbols-outlined text-lg" id="darkModeIcon">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-6">
                @yield('content')
            </div>
        </main>
    </div>

    <script>
        (function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebar-toggle');
            const state = localStorage.getItem('sidebar-collapsed');

            function setCollapsed(collapsed) {
                if (collapsed) {
                    sidebar.classList.add('sidebar-collapsed');
                } else {
                    sidebar.classList.remove('sidebar-collapsed');
                }
                localStorage.setItem('sidebar-collapsed', collapsed);
            }

            if (state === 'true') {
                setCollapsed(true);
            }

            toggleBtn.addEventListener('click', function() {
                const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
                setCollapsed(!isCollapsed);
            });
        })();
    </script>

    <script>
        (function() {
            const html = document.documentElement;
            const toggle = document.getElementById('darkModeToggle');
            const icon = document.getElementById('darkModeIcon');
            const darkMode = localStorage.getItem('dark-mode') === 'true';

            function applyDark(isDark) {
                if (isDark) {
                    html.classList.add('dark');
                    icon.textContent = 'light_mode';
                } else {
                    html.classList.remove('dark');
                    icon.textContent = 'dark_mode';
                }
                localStorage.setItem('dark-mode', isDark);
            }

            applyDark(darkMode);

            if (toggle) {
                toggle.addEventListener('click', function() {
                    applyDark(!html.classList.contains('dark'));
                });
            }
        })();
    </script>

    <script>
        function showToast(message, type) {
            type = type || 'success';
            var container = document.getElementById('toast-container');
            var toast = document.createElement('div');
            toast.className = 'toast toast-' + type;
            var icons = { success: 'check_circle', error: 'error', info: 'info', warning: 'warning' };
            var icon = icons[type] || 'info';
            toast.innerHTML =
                '<span class="material-symbols-outlined text-base flex-shrink-0">' + icon + '</span>' +
                '<span class="flex-1 text-sm">' + message + '</span>' +
                '<button class="toast-close material-symbols-outlined" onclick="this.closest(\'.toast\').remove()">close</button>';
            container.appendChild(toast);
            requestAnimationFrame(function() { toast.classList.add('show'); });
            setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(function() { toast.remove(); }, 350);
            }, 4500);
        }

        document.addEventListener('DOMContentLoaded', function() {
            @if(session('success'))
                showToast('{{ session('success') }}', 'success');
            @endif
            @if(session('error'))
                showToast('{{ session('error') }}', 'error');
            @endif


    </script>
</body>
</html>
