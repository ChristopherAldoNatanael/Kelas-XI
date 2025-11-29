@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="min-h-screen">
    <!-- Hero Header with Adaptive Design -->
    <div class="relative overflow-hidden rounded-3xl mx-6 mb-8 hero-header">
        <!-- Background Layers -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900 light:from-blue-50 light:via-indigo-50 light:to-purple-50"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-white/10 dark:from-black/20 dark:to-white/10 light:from-white/80 light:via-white/60 light:to-white/40"></div>

        <!-- Animated Background Elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-500/10 dark:bg-blue-500/10 light:bg-blue-500/5 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-purple-500/10 dark:bg-purple-500/10 light:bg-purple-500/5 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-indigo-500/5 dark:bg-indigo-500/5 light:bg-indigo-500/3 rounded-full blur-2xl animate-pulse" style="animation-delay: 4s;"></div>

        <!-- Adaptive Glass Morphism Overlay -->
        <div class="relative backdrop-blur-xl bg-white/5 dark:bg-white/5 light:bg-white/90 border border-white/10 dark:border-white/10 light:border-gray-200/50 rounded-3xl p-8 md:p-12 shadow-xl">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 dark:bg-white/10 light:bg-green-100/80 backdrop-blur-sm border border-white/20 dark:border-white/20 light:border-green-200/50">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-white dark:text-white light:text-gray-700 text-sm font-medium">System Online</span>
                    </div>

                    <div>
                        <h1 class="text-4xl md:text-5xl font-bold text-white dark:text-white light:text-gray-900 mb-3 tracking-tight">
                            Welcome back, {{ auth()->user()->name }}!
                        </h1>
                        <p class="text-white/70 dark:text-white/70 light:text-gray-600 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Your school management dashboard is ready. Here's a comprehensive overview of your educational ecosystem.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white dark:text-white light:text-gray-900">{{ number_format($stats['total_users']) }}</div>
                            <div class="text-white/70 dark:text-white/70 light:text-gray-600 text-sm">Active Users</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white dark:text-white light:text-gray-900">{{ number_format($stats['total_schedules']) }}</div>
                            <div class="text-white/70 dark:text-white/70 light:text-gray-600 text-sm">Total Schedules</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white dark:text-white light:text-gray-900">{{ number_format($stats['total_teachers']) }}</div>
                            <div class="text-white/70 dark:text-white/70 light:text-gray-600 text-sm">Teachers</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <div class="glass-card-time">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-white dark:text-white light:text-gray-900 mb-1">{{ now()->format('H:i') }}</div>
                            <div class="text-white/70 dark:text-white/70 light:text-gray-600 text-sm">{{ now()->format('l, M d, Y') }}</div>
                            <div class="mt-4 pt-4 border-t border-white/20 dark:border-white/20 light:border-gray-200/50">
                                <div class="text-white/60 dark:text-white/60 light:text-gray-500 text-xs">Last updated</div>
                                <div class="text-white/90 dark:text-white/90 light:text-gray-700 text-sm font-medium">{{ now()->diffForHumans() }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="px-6 space-y-8">
        <!-- Stats Grid with Adaptive Styling -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            <!-- Total Users Card -->
            <div class="adaptive-card group">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 via-blue-600/5 to-indigo-500/10 dark:from-blue-500/10 dark:via-blue-600/5 dark:to-indigo-500/10 light:from-blue-50 light:via-blue-100/50 light:to-indigo-50 opacity-0 group-hover:opacity-100 transition-all duration-500 rounded-2xl"></div>
                <div class="relative p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-blue-600/20 dark:from-blue-500/20 dark:to-blue-600/20 light:from-blue-100 light:to-blue-200 backdrop-blur-sm border border-blue-400/20 dark:border-blue-400/20 light:border-blue-300/50">
                            <i class="fas fa-users text-blue-300 dark:text-blue-300 light:text-blue-600 text-xl"></i>
                        </div>
                        <div class="flex items-center gap-1 px-2 py-1 rounded-full bg-green-500/20 dark:bg-green-500/20 light:bg-green-100 border border-green-400/30 dark:border-green-400/30 light:border-green-300/50">
                            <i class="fas fa-arrow-up text-green-400 dark:text-green-400 light:text-green-600 text-xs"></i>
                            <span class="text-green-300 dark:text-green-300 light:text-green-700 text-xs font-medium">+12%</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900">{{ number_format($stats['total_users']) }}</p>
                        <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">Total Users</p>
                        <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">Active accounts registered</p>
                    </div>
                </div>
            </div>

            <!-- Total Schedules Card -->
            <div class="adaptive-card group">
                <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 via-emerald-600/5 to-teal-500/10 dark:from-emerald-500/10 dark:via-emerald-600/5 dark:to-teal-500/10 light:from-emerald-50 light:via-emerald-100/50 light:to-teal-50 opacity-0 group-hover:opacity-100 transition-all duration-500 rounded-2xl"></div>
                <div class="relative p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 rounded-xl bg-gradient-to-br from-emerald-500/20 to-emerald-600/20 dark:from-emerald-500/20 dark:to-emerald-600/20 light:from-emerald-100 light:to-emerald-200 backdrop-blur-sm border border-emerald-400/20 dark:border-emerald-400/20 light:border-emerald-300/50">
                            <i class="fas fa-calendar text-emerald-300 dark:text-emerald-300 light:text-emerald-600 text-xl"></i>
                        </div>
                        <div class="flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-500/20 dark:bg-emerald-500/20 light:bg-emerald-100 border border-emerald-400/30 dark:border-emerald-400/30 light:border-emerald-300/50">
                            <i class="fas fa-arrow-up text-emerald-400 dark:text-emerald-400 light:text-emerald-600 text-xs"></i>
                            <span class="text-emerald-300 dark:text-emerald-300 light:text-emerald-700 text-xs font-medium">+8%</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900">{{ number_format($stats['total_schedules']) }}</p>
                        <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">Total Schedules</p>
                        <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">Active class schedules</p>
                    </div>
                </div>
            </div>

            <!-- Total Teachers Card -->
            <div class="adaptive-card group">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/10 via-orange-600/5 to-red-500/10 dark:from-amber-500/10 dark:via-orange-600/5 dark:to-red-500/10 light:from-amber-50 light:via-orange-100/50 light:to-red-50 opacity-0 group-hover:opacity-100 transition-all duration-500 rounded-2xl"></div>
                <div class="relative p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-600/20 dark:from-amber-500/20 dark:to-orange-600/20 light:from-amber-100 light:to-orange-200 backdrop-blur-sm border border-amber-400/20 dark:border-amber-400/20 light:border-amber-300/50">
                            <i class="fas fa-chalkboard-teacher text-amber-300 dark:text-amber-300 light:text-amber-600 text-xl"></i>
                        </div>
                        <div class="flex items-center gap-1 px-2 py-1 rounded-full bg-slate-500/20 dark:bg-slate-500/20 light:bg-slate-100 border border-slate-400/30 dark:border-slate-400/30 light:border-slate-300/50">
                            <i class="fas fa-minus text-slate-400 dark:text-slate-400 light:text-slate-600 text-xs"></i>
                            <span class="text-slate-300 dark:text-slate-300 light:text-slate-700 text-xs font-medium">0%</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900">{{ number_format($stats['total_teachers']) }}</p>
                        <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">Total Teachers</p>
                        <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">Teaching staff members</p>
                    </div>
                </div>
            </div>

            <!-- Total Subjects Card -->
            <div class="adaptive-card group">
                <div class="absolute inset-0 bg-gradient-to-br from-rose-500/10 via-pink-600/5 to-purple-500/10 dark:from-rose-500/10 dark:via-pink-600/5 dark:to-purple-500/10 light:from-rose-50 light:via-pink-100/50 light:to-purple-50 opacity-0 group-hover:opacity-100 transition-all duration-500 rounded-2xl"></div>
                <div class="relative p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 rounded-xl bg-gradient-to-br from-rose-500/20 to-pink-600/20 dark:from-rose-500/20 dark:to-pink-600/20 light:from-rose-100 light:to-pink-200 backdrop-blur-sm border border-rose-400/20 dark:border-rose-400/20 light:border-rose-300/50">
                            <i class="fas fa-book text-rose-300 dark:text-rose-300 light:text-rose-600 text-xl"></i>
                        </div>
                        <div class="flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-500/20 dark:bg-emerald-500/20 light:bg-emerald-100 border border-emerald-400/30 dark:border-emerald-400/30 light:border-emerald-300/50">
                            <i class="fas fa-arrow-up text-emerald-400 dark:text-emerald-400 light:text-emerald-600 text-xs"></i>
                            <span class="text-emerald-300 dark:text-emerald-300 light:text-emerald-700 text-xs font-medium">+3%</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900">{{ number_format($stats['total_subjects']) }}</p>
                        <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">Total Subjects</p>
                        <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">Available courses</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="adaptive-card-section">
            <div class="p-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h3 class="text-2xl font-bold text-white dark:text-white light:text-gray-900 mb-2">Quick Actions</h3>
                        <p class="text-slate-300 dark:text-slate-300 light:text-gray-600">Access frequently used features and management tools</p>
                    </div>
                    <div class="hidden md:flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 dark:bg-white/10 light:bg-gray-100 backdrop-blur-sm border border-white/20 dark:border-white/20 light:border-gray-200">
                        <i class="fas fa-bolt text-yellow-400 dark:text-yellow-400 light:text-yellow-600"></i>
                        <span class="text-white dark:text-white light:text-gray-700 text-sm font-medium">Shortcuts</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="{{ route('web-users.index') }}" class="adaptive-action-card group">
                        <div class="flex items-center gap-4">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-blue-600/20 dark:from-blue-500/20 dark:to-blue-600/20 light:from-blue-100 light:to-blue-200 border border-blue-400/20 dark:border-blue-400/20 light:border-blue-300/50">
                                <i class="fas fa-users text-blue-300 dark:text-blue-300 light:text-blue-600 text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-white dark:text-white light:text-gray-900 mb-1">Manage Users</h4>
                                <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-sm">Add, edit, or remove users</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-slate-400 dark:text-slate-400 light:text-gray-400 group-hover:text-white dark:group-hover:text-white light:group-hover:text-gray-900 transition-colors duration-300"></i>
                    </a>

                    <a href="{{ route('web-schedules.index') }}" class="adaptive-action-card group">
                        <div class="flex items-center gap-4">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-emerald-500/20 to-emerald-600/20 dark:from-emerald-500/20 dark:to-emerald-600/20 light:from-emerald-100 light:to-emerald-200 border border-emerald-400/20 dark:border-emerald-400/20 light:border-emerald-300/50">
                                <i class="fas fa-calendar text-emerald-300 dark:text-emerald-300 light:text-emerald-600 text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-white dark:text-white light:text-gray-900 mb-1">View Schedules</h4>
                                <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-sm">Check class schedules</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-slate-400 dark:text-slate-400 light:text-gray-400 group-hover:text-white dark:group-hover:text-white light:group-hover:text-gray-900 transition-colors duration-300"></i>
                    </a>

                    <a href="{{ route('web-teachers.index') }}" class="adaptive-action-card group">
                        <div class="flex items-center gap-4">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-600/20 dark:from-amber-500/20 dark:to-orange-600/20 light:from-amber-100 light:to-orange-200 border border-amber-400/20 dark:border-amber-400/20 light:border-amber-300/50">
                                <i class="fas fa-chalkboard-teacher text-amber-300 dark:text-amber-300 light:text-amber-600 text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-white dark:text-white light:text-gray-900 mb-1">Teachers</h4>
                                <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-sm">Manage teaching staff</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-slate-400 dark:text-slate-400 light:text-gray-400 group-hover:text-white dark:group-hover:text-white light:group-hover:text-gray-900 transition-colors duration-300"></i>
                    </a>

                    <a href="{{ route('web-subjects.index') }}" class="adaptive-action-card group">
                        <div class="flex items-center gap-4">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-rose-500/20 to-pink-600/20 dark:from-rose-500/20 dark:to-pink-600/20 light:from-rose-100 light:to-pink-200 border border-rose-400/20 dark:border-rose-400/20 light:border-rose-300/50">
                                <i class="fas fa-book text-rose-300 dark:text-rose-300 light:text-rose-600 text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-white dark:text-white light:text-gray-900 mb-1">Subjects</h4>
                                <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-sm">Course management</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-slate-400 dark:text-slate-400 light:text-gray-400 group-hover:text-white dark:group-hover:text-white light:group-hover:text-gray-900 transition-colors duration-300"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
/* Adaptive Card Styles for Light/Dark Mode */
.adaptive-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Light mode overrides */
.light .adaptive-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(0, 0, 0, 0.1);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.adaptive-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

/* Light mode hover */
.light .adaptive-card:hover {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.adaptive-card-section {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Light mode section */
.light .adaptive-card-section {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.glass-stat-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 0.75rem;
    padding: 1rem;
    text-align: center;
    min-width: 80px;
}

/* Light mode stat card */
.light .glass-stat-card {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.glass-card-time {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 1rem;
    padding: 1.5rem;
    min-width: 200px;
}

/* Light mode time card */
.light .glass-card-time {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.adaptive-action-card {
    display: flex;
    align-items: center;
    justify-between;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Light mode action card */
.light .adaptive-action-card {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.adaptive-action-card:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    transform: translateX(4px);
}

/* Light mode action card hover */
.light .adaptive-action-card:hover {
    background: rgba(255, 255, 255, 0.9);
    border-color: rgba(0, 0, 0, 0.15);
}

/* Hero Header Styles */
.hero-header {
    position: relative;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

/* Light mode hero */
.light .hero-header {
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
}

/* Smooth animations */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.animate-float {
    animation: float 6s ease-in-out infinite;
}

/* Gradient text effects */
.gradient-text {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Subtle glow effects */
.glow-effect {
    box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .adaptive-card,
    .adaptive-card-section {
        margin: 0 1rem;
    }

    .glass-stat-card {
        min-width: 60px;
        padding: 0.75rem;
    }

    .hero-header {
        margin: 0 1rem;
    }
}

/* Utility classes for light/dark mode detection */
.dark {
    /* Dark mode styles are default */
}

.light {
    /* Light mode overrides */
    color: #1f2937;
}

.light body {
    background-color: #f9fafb;
    color: #1f2937;
}

/* JavaScript for theme detection */
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to apply theme
    function applyTheme(theme) {
        const html = document.documentElement;
        if (theme === 'dark') {
            html.classList.remove('light');
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
            html.classList.add('light');
        }
    }

    // Check for saved theme preference or default to system preference
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        applyTheme(savedTheme);
    } else {
        // Check system preference
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(prefersDark ? 'dark' : 'light');
    }

    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (!localStorage.getItem('theme')) {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });
});
</script>
</style>
@endsection
