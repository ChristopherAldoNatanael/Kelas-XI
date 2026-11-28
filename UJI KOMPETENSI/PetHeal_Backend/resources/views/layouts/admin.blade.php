@php
    $clinic = currentClinic();
    $clinicName = $clinic?->name ?? 'PetHeal';
    $clinicLogo = $clinic?->logo_url;
    $clinicColor = \App\Support\AccessibleColor::primary($clinic?->primary_color);
    $isSuperAdmin = isSuperAdmin();
    // Satu layout untuk admin & portal dokter. Varian ditentukan oleh route,
    // bukan oleh role saja, supaya tautan admin yang dibuka dokter tetap tampil
    // sebagai halaman admin (middleware yang mengarahkan, bukan layout).
    $isDoctorPortal = auth()->check()
        && auth()->user()->role === 'doctor'
        && request()->routeIs('doctor.*');
    $homeRoute = $isDoctorPortal ? route('doctor.dashboard') : route('admin.dashboard');
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
    <a href="#main-content" class="skip-link">{{ __('admin.skip_to_content') }}</a>
    <div id="toast-container" aria-live="polite" aria-atomic="false"></div>

    {{-- Satu sumber data untuk modul JavaScript konsol admin: pesan flash,
         label terjemahan, dan URL yang dipakai command palette. Blade tidak
         lagi menulis <script> berisi @json(__('...')) di setiap halaman. --}}
    <script type="application/json" id="admin-boot">
        {!! json_encode([
            'flashes' => array_values(array_filter([
                session('success') ? ['type' => 'success', 'message' => session('success')] : null,
                session('error') ? ['type' => 'error', 'message' => session('error')] : null,
                session('warning') ? ['type' => 'warning', 'message' => session('warning')] : null,
                session('info') ? ['type' => 'info', 'message' => session('info')] : null,
            ])),
            'labels' => [
                'close_notification' => __('admin.close_notification'),
                'untitled' => __('admin.untitled'),
                'try_again' => __('admin.try_again'),
                'command_quick_actions' => __('admin.command_quick_actions'),
                'command_min_chars' => __('admin.command_min_chars'),
                'command_board_title' => __('admin.command_board_title'),
                'command_board_desc' => __('admin.command_board_desc'),
                'command_list_title' => __('admin.command_list_title'),
                'command_list_desc' => __('admin.command_list_desc'),
                'command_payments_title' => __('admin.command_payments_title'),
                'command_payments_desc' => __('admin.command_payments_desc'),
                'command_type_booking' => __('admin.command_type_booking'),
                'command_type_pet' => __('admin.command_type_pet'),
                'command_type_owner' => __('admin.command_type_owner'),
                'command_type_action' => __('admin.command_type_action'),
                'command_no_results_title' => __('admin.command_no_results_title'),
                'command_no_results_desc' => __('admin.command_no_results_desc'),
                'command_select_clinic_title' => __('admin.command_select_clinic_title'),
                'command_search_failed_title' => __('admin.command_search_failed_title'),
                'command_search_failed_desc' => __('admin.command_search_failed_desc'),
            ],
            'routes' => [
                'search' => route('admin.search'),
                'board' => route('admin.bookings.schedule'),
                'bookings' => route('admin.bookings.index'),
                'payments' => route('admin.payments.index'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <div id="sidebar-backdrop" class="fixed inset-0 z-50 hidden bg-slate-950/50 backdrop-blur-sm lg:hidden"></div>

    <div class="flex h-full">
        <!-- Sidebar -->
        <aside id="sidebar"
               class="app-sidebar flex-shrink-0 flex flex-col h-full overflow-hidden"
               aria-label="{{ __('admin.main_navigation') }}">
            <div class="sidebar-header p-4 pb-4 flex items-center gap-3 flex-shrink-0">
                <div class="logo-text-wrap flex items-center gap-3 flex-1 min-w-0">
                    <a href="{{ $homeRoute }}" class="logo-link bg-white p-1.5 rounded-xl block transition-colors flex-shrink-0">
                        @if($clinicLogo)
                            <img src="{{ $clinicLogo }}" alt="{{ $clinicName }}" class="h-7 w-auto block rounded" fetchpriority="high" width="158" height="56">
                        @else
                            <img src="/logo-320.png" alt="{{ $clinicName }}" class="h-7 w-auto block rounded" fetchpriority="high" width="158" height="56">
                        @endif
                    </a>
                    <div class="sidebar-text min-w-0 leading-tight">
                        <p class="sidebar-eyebrow">{{ $isDoctorPortal ? 'Portal Dokter' : ($isSuperAdmin ? 'Super Admin' : 'Ruang Klinik') }}</p>
                        <p class="sidebar-brand-name font-semibold tracking-tight text-white">{{ $clinicName }}</p>
                    </div>
                </div>

                {{-- Desktop: collapse rail. Mobile: close drawer. --}}
                <button id="sidebar-toggle" class="sidebar-toggle hidden lg:flex text-slate-400 hover:text-white transition-all p-1.5 rounded-lg hover:bg-white/10 flex-shrink-0"
                        title="{{ __('admin.collapse_sidebar') }}" aria-label="{{ __('admin.collapse_sidebar') }}" aria-expanded="true">
                    <span class="material-symbols-outlined sidebar-toggle-icon text-xl" aria-hidden="true">chevron_left</span>
                </button>
                <button id="sidebar-close" class="lg:hidden text-slate-400 hover:text-white transition-all p-1.5 rounded-lg hover:bg-white/10 flex-shrink-0"
                        title="{{ __('admin.close_menu') }}" aria-label="{{ __('admin.close_menu') }}">
                    <span class="material-symbols-outlined text-xl" aria-hidden="true">close</span>
                </button>
            </div>

            {{-- Konteks klinik untuk layar kecil: pemilih ini hanya ada di topbar
                 pada layar >= md, sehingga tanpa blok ini super admin di ponsel
                 tidak tahu klinik mana yang sedang aktif. --}}
            <div class="lg:hidden px-3 pb-3">
                @if($isSuperAdmin)
                    <form method="POST" action="{{ route('admin.switch-clinic') }}" class="flex items-center gap-2 rounded-xl border border-emerald-900/10 bg-emerald-900/[0.04] px-3 py-2">
                        @csrf
                        <span class="material-symbols-outlined text-base text-emerald-700" aria-hidden="true">swap_horiz</span>
                        <label for="clinic-switcher-mobile" class="sr-only">{{ __('admin.choose_clinic') }}</label>
                        <select name="clinic_id" id="clinic-switcher-mobile" onchange="this.form.submit()"
                                class="w-full bg-transparent text-xs font-semibold text-emerald-950 outline-none [&>option]:text-slate-900">
                            <option value="" {{ session('current_clinic_id') ? '' : 'selected' }}>— {{ __('menu.system_overview') }} —</option>
                            @foreach(($allClinics ?? collect()) as $c)
                                <option value="{{ $c->id }}" {{ ($clinic?->id === $c->id) ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @else
                    <div class="flex items-center gap-2 rounded-xl border border-emerald-900/10 bg-emerald-900/[0.04] px-3 py-2 text-xs font-semibold text-emerald-950/80">
                        <span class="material-symbols-outlined text-base text-emerald-700" aria-hidden="true">local_hospital</span>
                        <span class="truncate">{{ $clinicName }}</span>
                    </div>
                @endif
            </div>

            <nav class="flex-1 px-3 space-y-0.5 overflow-y-auto custom-scrollbar pb-4">
                @if($isDoctorPortal)
                {{-- Navigasi portal dokter: hanya area praktik milik sendiri. --}}
                @if($isDoctorPortal)
                <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-4 mt-2">Praktik Hari Ini</p>
                @endif
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ request()->routeIs('doctor.dashboard') ? 'active-nav-item' : 'font-medium' }} relative"
                   href="{{ route('doctor.dashboard') }}" @if(request()->routeIs('doctor.dashboard')) aria-current="page" @endif>
                    @if(request()->routeIs('doctor.dashboard'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">dashboard</span>
                    <span class="sidebar-text text-sm">{{ __('doctor.nav_today') }}</span>
                </a>
                <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-2 mt-4">Praktik</p>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('doctor.bookings.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('doctor.bookings.index') }}" @if(request()->routeIs('doctor.bookings.*')) aria-current="page" @endif>
                    @if(request()->routeIs('doctor.bookings.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">calendar_month</span>
                    <span class="sidebar-text">{{ __('doctor.nav_bookings') }}</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('doctor.queue.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('doctor.queue.index') }}" @if(request()->routeIs('doctor.queue.*')) aria-current="page" @endif>
                    @if(request()->routeIs('doctor.queue.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">groups</span>
                    <span class="sidebar-text">{{ __('doctor.nav_queue') }}</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('doctor.service-requests.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('doctor.service-requests.index') }}" @if(request()->routeIs('doctor.service-requests.*')) aria-current="page" @endif>
                    @if(request()->routeIs('doctor.service-requests.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">medical_information</span>
                    <span class="sidebar-text">{{ __('doctor.nav_service_requests') }}</span>
                </a>
                <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-2 mt-4">Dokumentasi</p>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('doctor.medical-records.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('doctor.medical-records.index') }}" @if(request()->routeIs('doctor.medical-records.*')) aria-current="page" @endif>
                    @if(request()->routeIs('doctor.medical-records.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">clinical_notes</span>
                    <span class="sidebar-text">{{ __('doctor.nav_records') }}</span>
                </a>
                <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-2 mt-4">Jadwal</p>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('doctor.schedule.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('doctor.schedule.index') }}" @if(request()->routeIs('doctor.schedule.*')) aria-current="page" @endif>
                    @if(request()->routeIs('doctor.schedule.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">event_busy</span>
                    <span class="sidebar-text">{{ __('doctor.nav_schedule') }}</span>
                </a>
                <div class="pt-8">
                    <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-4">{{ __('doctor.account_section') }}</p>
                    <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('doctor.profile.*') ? 'active-nav-item' : '' }} relative"
                       href="{{ route('doctor.profile.edit') }}" @if(request()->routeIs('doctor.profile.*')) aria-current="page" @endif>
                        @if(request()->routeIs('doctor.profile.*'))
                        <div class="active-indicator" aria-hidden="true"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">manage_accounts</span>
                        <span class="sidebar-text">{{ __('doctor.nav_profile') }}</span>
                    </a>
                    <form method="POST" action="{{ route('doctor.logout') }}">
                        @csrf
                        <button type="submit" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium w-full text-left">
                            <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">logout</span>
                            <span class="sidebar-text">{{ __('menu.logout') }}</span>
                        </button>
                    </form>
                </div>
                @else
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
                <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-2 mt-4">Ikhtisar</p>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ request()->routeIs('admin.dashboard') ? 'active-nav-item' : 'font-medium' }} relative"
                   href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.dashboard'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">grid_view</span>
                    <span class="sidebar-text text-sm">{{ __('menu.overview') }}</span>
                </a>
                <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-2 mt-4">Operasional</p>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.queue.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.queue.index') }}" @if(request()->routeIs('admin.queue.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.queue.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">groups</span>
                    <span class="sidebar-text">{{ __('menu.queue') }}</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.walk-ins.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.walk-ins.index') }}" @if(request()->routeIs('admin.walk-ins.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.walk-ins.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">health_and_safety</span>
                    <span class="sidebar-text">Walk-in & Darurat</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.service-requests.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.service-requests.index') }}" @if(request()->routeIs('admin.service-requests.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.service-requests.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">support_agent</span>
                    <span class="sidebar-text">Permintaan Layanan</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.users.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.users.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">group</span>
                    <span class="sidebar-text">{{ __('menu.users') }}</span>
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
                {{-- Stok obat adalah satu area kerja: daftar obat, monitoring
                     kedaluwarsa, dan mutasi stok hidup di bawah satu parent agar
                     tidak terlihat sebagai tiga menu yang saling terpisah. --}}
                @php
                    $inventoryActive = request()->routeIs('admin.medicines.*');
                    $inventoryListActive = $inventoryActive
                        && !request()->routeIs('admin.medicines.expiry', 'admin.medicines.mutations.*');
                @endphp
                <div class="sidebar-nav-group" data-nav-group>
                    <button type="button"
                            class="nav-link flex w-full items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ $inventoryActive ? 'active-nav-item' : '' }} relative"
                            data-nav-group-toggle
                            aria-expanded="{{ $inventoryActive ? 'true' : 'false' }}"
                            aria-controls="nav-inventory">
                        @if($inventoryActive)
                        <div class="active-indicator" aria-hidden="true"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">medication</span>
                        <span class="sidebar-text flex-1 text-left text-sm">{{ __('menu.medicines') }}</span>
                        <span class="sidebar-text material-symbols-outlined nav-group-chevron text-[18px] {{ $inventoryActive ? 'is-open' : '' }}" aria-hidden="true">expand_more</span>
                    </button>
                    <div id="nav-inventory" class="sidebar-submenu {{ $inventoryActive ? '' : 'hidden' }}">
                        <a href="{{ route('admin.medicines.index') }}"
                           class="sidebar-sublink {{ $inventoryListActive ? 'is-active' : '' }}"
                           @if($inventoryListActive) aria-current="page" @endif>
                            {{ __('menu.medicines_list') }}
                        </a>
                        <a href="{{ route('admin.medicines.expiry') }}"
                           class="sidebar-sublink {{ request()->routeIs('admin.medicines.expiry') ? 'is-active' : '' }}"
                           @if(request()->routeIs('admin.medicines.expiry')) aria-current="page" @endif>
                            {{ __('menu.medicines_expiry') }}
                        </a>
                        <a href="{{ route('admin.medicines.mutations.index') }}"
                           class="sidebar-sublink {{ request()->routeIs('admin.medicines.mutations.*') ? 'is-active' : '' }}"
                           @if(request()->routeIs('admin.medicines.mutations.*')) aria-current="page" @endif>
                            {{ __('menu.medicine_mutations') }}
                        </a>
                    </div>
                </div>
                <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-2 mt-4">Klinis</p>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.medical-records.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.medical-records.index') }}" @if(request()->routeIs('admin.medical-records.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.medical-records.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">clinical_notes</span>
                    <span class="sidebar-text">{{ __('menu.medical_records') }}</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.clinical.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.clinical.index') }}" @if(request()->routeIs('admin.clinical.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.clinical.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">science</span>
                    <span class="sidebar-text">Ruang Klinis</span>
                </a>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.privacy-requests.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.privacy-requests.index') }}" @if(request()->routeIs('admin.privacy-requests.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.privacy-requests.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">shield_person</span>
                    <span class="sidebar-text">Permintaan Privasi</span>
                </a>
                <p class="section-label sidebar-text px-3 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400 mb-2 mt-4">Keuangan &amp; Penjualan</p>
                <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.payments.*') ? 'active-nav-item' : '' }} relative"
                   href="{{ route('admin.payments.index') }}" @if(request()->routeIs('admin.payments.*')) aria-current="page" @endif>
                    @if(request()->routeIs('admin.payments.*'))
                    <div class="active-indicator" aria-hidden="true"></div>
                    @endif
                    <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">payments</span>
                    <span class="sidebar-text">{{ __('menu.payments') }}</span>
                </a>
                @endif

                {{-- Pet Shop: domain penjualan produk, terpisah dari layanan
                     medis & stok obat. Satu parent agar navigasi tetap ringkas. --}}
                @php($petShopActive = request()->routeIs('admin.pet-shop.*'))
                <div class="sidebar-nav-group" data-nav-group>
                    <button type="button"
                            class="nav-link flex w-full items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ $petShopActive ? 'active-nav-item' : '' }} relative"
                            data-nav-group-toggle
                            aria-expanded="{{ $petShopActive ? 'true' : 'false' }}"
                            aria-controls="nav-petshop">
                        @if($petShopActive)
                        <div class="active-indicator" aria-hidden="true"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">shopping_bag</span>
                        <span class="sidebar-text flex-1 text-left text-sm">{{ __('menu.pet_shop') }}</span>
                        <span class="sidebar-text material-symbols-outlined nav-group-chevron text-[18px] {{ $petShopActive ? 'is-open' : '' }}" aria-hidden="true">expand_more</span>
                    </button>
                    <div id="nav-petshop" class="sidebar-submenu {{ $petShopActive ? '' : 'hidden' }}">
                        <a href="{{ route('admin.pet-shop.dashboard') }}"
                           class="sidebar-sublink {{ request()->routeIs('admin.pet-shop.dashboard') ? 'is-active' : '' }}"
                           @if(request()->routeIs('admin.pet-shop.dashboard')) aria-current="page" @endif>
                            {{ __('menu.pet_shop_dashboard') }}
                        </a>
                        <a href="{{ route('admin.pet-shop.products.index') }}"
                           class="sidebar-sublink {{ request()->routeIs('admin.pet-shop.products.*') ? 'is-active' : '' }}"
                           @if(request()->routeIs('admin.pet-shop.products.*')) aria-current="page" @endif>
                            {{ __('menu.pet_shop_products') }}
                        </a>
                        <a href="{{ route('admin.pet-shop.orders.index') }}"
                           class="sidebar-sublink {{ request()->routeIs('admin.pet-shop.orders.*') ? 'is-active' : '' }}"
                           @if(request()->routeIs('admin.pet-shop.orders.*')) aria-current="page" @endif>
                            {{ __('menu.pet_shop_orders') }}
                        </a>
                        <a href="{{ route('admin.pet-shop.pickups.index') }}"
                           class="sidebar-sublink {{ request()->routeIs('admin.pet-shop.pickups.*') ? 'is-active' : '' }}"
                           @if(request()->routeIs('admin.pet-shop.pickups.*')) aria-current="page" @endif>
                            {{ __('menu.pet_shop_pickups') }}
                        </a>
                        <a href="{{ route('admin.pet-shop.sales.index') }}"
                           class="sidebar-sublink {{ request()->routeIs('admin.pet-shop.sales.*') ? 'is-active' : '' }}"
                           @if(request()->routeIs('admin.pet-shop.sales.*')) aria-current="page" @endif>
                            {{ __('menu.pet_shop_sales') }}
                        </a>
                        @if($isSuperAdmin)
                            <a href="{{ route('admin.pet-shop.categories.index') }}"
                               class="sidebar-sublink {{ request()->routeIs('admin.pet-shop.categories.*') ? 'is-active' : '' }}"
                               @if(request()->routeIs('admin.pet-shop.categories.*')) aria-current="page" @endif>
                                {{ __('menu.pet_shop_categories') }}
                            </a>
                            <a href="{{ route('admin.pet-shop.merchants.index') }}"
                               class="sidebar-sublink {{ request()->routeIs('admin.pet-shop.merchants.*') ? 'is-active' : '' }}"
                               @if(request()->routeIs('admin.pet-shop.merchants.*')) aria-current="page" @endif>
                                {{ __('menu.pet_shop_merchants') }}
                            </a>
                        @endif
                    </div>
                </div>

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
                    <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.service-categories.*') ? 'active-nav-item' : '' }} relative"
                       href="{{ route('admin.service-categories.index') }}" @if(request()->routeIs('admin.service-categories.*')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.service-categories.*'))
                        <div class="active-indicator" aria-hidden="true"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] shrink-0" aria-hidden="true">category</span>
                        <span class="sidebar-text">{{ __('clinics.service_categories') }}</span>
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
                    <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.payment-settings.*') ? 'active-nav-item' : '' }} relative"
                       href="{{ route('admin.payment-settings.index') }}" @if(request()->routeIs('admin.payment-settings.*')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.payment-settings.*'))
                        <div class="active-indicator" aria-hidden="true"></div>
                        @endif
                        <span class="material-symbols-outlined text-[20px] flex-shrink-0" aria-hidden="true">account_balance_wallet</span>
                        <span class="sidebar-text">{{ __('menu.payment_settings') }}</span>
                    </a>
                    <a class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium {{ request()->routeIs('admin.settings') ? 'active-nav-item' : '' }} relative"
                       href="{{ route('admin.settings') }}" @if(request()->routeIs('admin.settings')) aria-current="page" @endif>
                        @if(request()->routeIs('admin.settings'))
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
                @endif
            </nav>

            <div class="p-4 flex-shrink-0 border-t border-white/5">
                <div class="user-info-card bg-white/5 border border-white/5 p-3 rounded-xl flex items-center gap-3">
                    <div class="h-8 w-8 rounded-full bg-primary flex items-center justify-center text-white text-sm font-semibold flex-shrink-0" aria-hidden="true">
                        {{ \Illuminate\Support\Str::substr(Auth::user()->name ?? 'A', 0, 1) }}
                    </div>
                    <div class="user-info-text flex-1 min-w-0">
                        <p class="text-xs font-medium text-white/90 truncate">{{ Auth::user()->name ?? 'Admin' }}</p>
                        <p class="text-[9px] text-slate-400 truncate uppercase tracking-widest font-bold">
                            @if($isSuperAdmin)
                                Super Admin
                            @elseif($isDoctorPortal)
                                Dokter
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
                            aria-label="{{ __('admin.open_menu') }}" aria-controls="sidebar" aria-expanded="false">
                        <span class="material-symbols-outlined text-xl" aria-hidden="true">menu</span>
                    </button>
                    <h1 class="text-base font-semibold text-slate-900 dark:text-white tracking-tight truncate">@yield('header', __('dashboard.executive_dashboard'))</h1>
                </div>
                <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0">
                    @unless($isDoctorPortal)
                    <button id="commandPaletteTrigger" type="button"
                            class="flex items-center gap-2 h-9 px-2.5 sm:px-3 rounded-lg text-slate-500 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700 hover:text-slate-700 dark:hover:text-white hover:border-slate-300 dark:hover:border-slate-500 transition-colors"
                            aria-label="{{ __('admin.search_short') }} Ctrl K — {{ __('admin.open_global_search') }}" aria-haspopup="dialog" aria-controls="commandPalette">
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">search</span>
                        <span class="hidden md:inline text-xs font-semibold">{{ __('admin.search_short') }}</span>
                        <kbd class="hidden lg:inline-flex items-center px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-500 text-[9px] font-bold text-slate-500 dark:text-slate-300">Ctrl K</kbd>
                    </button>
                    @endunless
                    @if($isSuperAdmin)
                    <form method="POST" action="{{ route('admin.switch-clinic') }}" id="clinic-switcher-form" class="hidden md:flex items-center gap-2">
                        @csrf
                        <span class="material-symbols-outlined text-sm text-slate-400" aria-hidden="true">swap_horiz</span>
                        <select name="clinic_id" id="clinic-switcher" onchange="document.getElementById('clinic-switcher-form').submit()"
                                aria-label="{{ __('admin.choose_clinic') }}"
                                class="bg-slate-50 dark:bg-slate-800 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-400 border border-slate-200/60 dark:border-slate-700 cursor-pointer outline-none">
                            <option value="" {{ session('current_clinic_id') ? '' : 'selected' }}>— {{ __('menu.system_overview') }} —</option>
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
                        <span>{{ now()->locale(app()->getLocale())->translatedFormat('d M Y') }}</span>
                    </div>
                    {{-- Language switcher preserves the current query string (filters, dates). --}}
                    <div class="flex items-center gap-1" aria-label="{{ __('admin.choose_language') }}">
                        <a href="{{ request()->fullUrlWithQuery(['lang' => 'id']) }}"
                           class="px-2 py-1 rounded text-[10px] font-bold ring-1 transition-all {{ app()->getLocale() === 'id' ? 'bg-slate-900 text-white ring-primary dark:bg-white dark:text-slate-900' : 'text-slate-500 ring-transparent hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}"
                           @if(app()->getLocale() === 'id') aria-current="true" @endif>ID</a>
                        <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}"
                           class="px-2 py-1 rounded text-[10px] font-bold ring-1 transition-all {{ app()->getLocale() === 'en' ? 'bg-slate-900 text-white ring-primary dark:bg-white dark:text-slate-900' : 'text-slate-500 ring-transparent hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}"
                           @if(app()->getLocale() === 'en') aria-current="true" @endif>EN</a>
                    </div>
                    <button id="darkModeToggle" class="flex items-center justify-center w-9 h-9 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all"
                            title="{{ __('admin.toggle_dark_mode') }}" aria-label="{{ __('admin.toggle_dark_mode') }}">
                        <span class="material-symbols-outlined text-lg" id="darkModeIcon" aria-hidden="true">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                {{-- Satu-satunya tempat error validasi ditampilkan. Halaman tidak
                     lagi merender bloknya sendiri agar tidak ada pesan ganda. --}}
                <x-admin.validation-errors />
                @yield('content')
            </div>
        </main>
    </div>

    {{-- Global command palette: booking, pet, owner search scoped by clinic. --}}
    <div id="commandPalette" class="fixed inset-0 z-[80] hidden bg-slate-950/50 backdrop-blur-sm p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="commandPaletteLabel" aria-hidden="true">
        <div class="mx-auto mt-[8vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200/70 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-2xl">
            <div class="flex items-center gap-3 px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-slate-800">
                <span class="material-symbols-outlined text-slate-400" aria-hidden="true">search</span>
                <label id="commandPaletteLabel" for="commandPaletteInput" class="sr-only">{{ __('admin.global_search') }}</label>
                <input id="commandPaletteInput" type="text" autocomplete="off" spellcheck="false"
                       placeholder="{{ __('admin.global_search_placeholder') }}"
                       class="flex-1 bg-transparent text-sm text-slate-900 dark:text-white placeholder:text-slate-400 outline-none">
                <span id="commandPaletteSpinner" class="hidden">
                    <span class="block h-4 w-4 rounded-full border-2 border-slate-200 border-t-primary animate-spin" aria-label="{{ __('admin.searching') }}"></span>
                </span>
                <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-600 text-[9px] font-bold text-slate-400">ESC</kbd>
            </div>

            <div id="commandPaletteBody" class="max-h-[420px] overflow-y-auto custom-scrollbar p-2"></div>

            <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-t border-slate-100 dark:border-slate-800 text-[10px] text-slate-400">
                <span class="hidden sm:inline">{{ __('admin.command_keyboard_hint') }}</span>
                <span class="sm:hidden">{{ __('admin.command_tap_hint') }}</span>
                <span class="font-semibold">{{ __('admin.command_scope_hint') }}</span>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
