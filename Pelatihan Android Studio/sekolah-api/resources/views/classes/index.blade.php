@extends('layouts.app')

@section('title', 'Classes Management')

@section('content')
<div class="min-h-screen">
    <!-- Hero Header with Glass Morphism -->
    <div class="relative overflow-hidden rounded-3xl mx-6 mb-8">
        <!-- Background Layers -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-white/10"></div>

        <!-- Animated Background Elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-cyan-500/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-indigo-500/5 rounded-full blur-2xl animate-pulse" style="animation-delay: 4s;"></div>

        <!-- Glass Morphism Overlay -->
        <div class="relative backdrop-blur-xl bg-white/5 border border-white/10 rounded-3xl p-8 md:p-12">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/20">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-white/90 text-sm font-medium">System Online</span>
                    </div>

                    <div>
                        <h1 class="text-4xl md:text-5xl font-bold text-white mb-3 tracking-tight">
                            Classes Management
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Comprehensive class administration system. Manage student groups, academic levels, and classroom organization across your institution.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ number_format($classes->total()) }}</div>
                            <div class="text-white/70 text-sm">Total Classes</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $classes->where('status', 'active')->count() }}</div>
                            <div class="text-white/70 text-sm">Active Classes</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $classes->sum('capacity') }}</div>
                            <div class="text-white/70 text-sm">Total Capacity</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <a href="{{ route('web-classes.create') }}" class="glass-action-button group">
                        <div class="p-4 rounded-xl bg-gradient-to-br from-cyan-500/20 to-blue-500/20 border border-cyan-400/20">
                            <i class="fas fa-plus text-cyan-300 text-2xl"></i>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Add New Class</div>
                            <div class="text-slate-300 text-sm">Create student group</div>
                        </div>
                        <i class="fas fa-arrow-right text-slate-400 group-hover:text-white transition-colors duration-300"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="mx-6 mb-6 glass-notification glass-notification-success">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-green-500/20">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <span class="text-white">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mx-6 mb-6 glass-notification glass-notification-error">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-red-500/20">
                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                </div>
                <span class="text-white">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Classes Directory with Glass Morphism -->
    <div class="px-6 space-y-6">
        <!-- Search and Filters Header -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-2">Classes Directory</h3>
                        <p class="text-slate-300">Browse and manage all student classes in your institution</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search classes..."
                                    class="glass-search-input">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        </div>

                        <div class="flex gap-2">
                            <button class="glass-filter-btn" data-filter="all">
                                <i class="fas fa-users-class"></i>
                                All
                            </button>
                            <button class="glass-filter-btn" data-filter="active">
                                <i class="fas fa-check-circle"></i>
                                Active
                            </button>
                            <button class="glass-filter-btn" data-filter="inactive">
                                <i class="fas fa-pause-circle"></i>
                                Inactive
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Classes Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="classesGrid">
            @forelse($classes as $class)
                <div class="class-card glass-morphism-card group" data-status="{{ $class->status }}" data-class-id="{{ $class->id }}">
                    <div class="relative p-6">

                        <!-- Class Avatar and Basic Info -->
                        <div class="flex items-start justify-between mb-4 pr-12">
                            <div class="flex items-center gap-4">
                                <div class="class-avatar-large bg-gradient-to-br from-cyan-500 to-blue-600">
                                    <span class="text-white font-bold text-lg">
                                        {{ strtoupper(substr($class->nama_kelas, 0, 1)) }}
                                    </span>
                                </div>
                                <div>
                                    <h4 class="text-xl font-bold text-white mb-1">{{ $class->nama_kelas }}</h4>
                                    <p class="text-slate-300 text-sm flex items-center">
                                        <i class="fas fa-hashtag mr-2"></i>
                                        {{ $class->kode_kelas ?: 'No Code' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <div class="flex flex-col items-end gap-2">
                                @if($class->status === 'active')
                                    <span class="glass-status-badge glass-status-active">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Active
                                    </span>
                                @else
                                    <span class="glass-status-badge glass-status-inactive">
                                        <i class="fas fa-pause-circle mr-2"></i>
                                        Inactive
                                    </span>
                                @endif

                                <!-- Class ID -->
                                <span class="text-xs text-slate-400 font-mono">#{{ $class->id }}</span>
                            </div>
                        </div>

                        <!-- Level and Major Info -->
                        <div class="space-y-3 mb-6">
                            @php
                                $levelColors = [
                                    'X' => 'from-cyan-500/20 to-blue-500/20 border-cyan-400/30',
                                    'XI' => 'from-blue-500/20 to-indigo-500/20 border-blue-400/30',
                                    'XII' => 'from-indigo-500/20 to-purple-500/20 border-indigo-400/30'
                                ];
                                $levelColor = $levelColors[$class->level] ?? 'from-slate-500/20 to-slate-600/20 border-slate-400/30';
                            @endphp

                            <div class="glass-level-badge bg-gradient-to-r {{ $levelColor }}">
                                <i class="fas fa-graduation-cap mr-2"></i>
                                Level {{ $class->level }}
                            </div>

                            @if($class->major)
                                <div class="glass-major-info">
                                    <i class="fas fa-cogs mr-2"></i>
                                    {{ $class->major }}
                                </div>
                            @endif
                        </div>

                        <!-- Class Stats -->
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="glass-stat-mini">
                                <div class="text-lg font-bold text-white">{{ $class->users->count() }}</div>
                                <div class="text-xs text-slate-400">Students</div>
                            </div>
                            <div class="glass-stat-mini">
                                <div class="text-lg font-bold text-white">{{ $class->capacity ?: 'N/A' }}</div>
                                <div class="text-xs text-slate-400">Capacity</div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-between pt-4 border-t border-white/10">
                            <div class="flex gap-2">
                                <a href="{{ route('web-classes.show', $class->id) }}" class="glass-action-btn glass-action-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('web-classes.edit', $class->id) }}" class="glass-action-btn glass-action-edit" title="Edit Class">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>

                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('web-classes.destroy', $class->id) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="glass-action-btn glass-action-delete" title="Delete Class"
                                            onclick="return confirm('Are you sure you want to delete this class?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <div class="glass-morphism-card">
                        <div class="p-12 text-center">
                            <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-cyan-500/20 to-blue-500/20 flex items-center justify-center">
                                <i class="fas fa-users-class text-4xl text-cyan-400"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-3">No Classes Found</h3>
                            <p class="text-slate-300 mb-8 max-w-md mx-auto">Get started by creating your first student class to begin organizing your academic groups.</p>
                            <a href="{{ route('web-classes.create') }}" class="glass-action-button group inline-flex">
                                <div class="p-3 rounded-xl bg-gradient-to-br from-cyan-500/20 to-blue-500/20 border border-cyan-400/20">
                                    <i class="fas fa-plus text-cyan-300 text-xl"></i>
                                </div>
                                <div>
                                    <div class="text-white font-semibold">Add New Class</div>
                                    <div class="text-slate-300 text-sm">Create student group</div>
                                </div>
                                <i class="fas fa-arrow-right text-slate-400 group-hover:text-white transition-colors duration-300"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Modern Pagination -->
        @if($classes->hasPages())
            <div class="glass-morphism-card">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-2 text-slate-300">
                            <i class="fas fa-info-circle text-cyan-400"></i>
                            <span>Showing <span class="font-semibold text-white">{{ $classes->firstItem() }}</span> to
                            <span class="font-semibold text-white">{{ $classes->lastItem() }}</span> of
                            <span class="font-semibold text-white">{{ $classes->total() }}</span> classes</span>
                        </div>
                        <div class="pagination-controls">
                            {{ $classes->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Theme Detection
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

    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        applyTheme(savedTheme);
    } else {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(prefersDark ? 'dark' : 'light');
    }

    // Enhanced Search Functionality
    const searchInput = document.getElementById('searchInput');
    const classesGrid = document.getElementById('classesGrid');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const filter = this.value.toLowerCase().trim();

        // Add loading state
        this.classList.add('loading');

        searchTimeout = setTimeout(() => {
            const classCards = classesGrid.querySelectorAll('.class-card');
            let visibleCount = 0;

            classCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                const shouldShow = !filter || text.includes(filter);
                card.style.display = shouldShow ? '' : 'none';

                if (shouldShow) {
                    visibleCount++;
                    // Add smooth animation
                    card.style.animation = 'fadeInUp 0.4s ease-out forwards';
                }
            });

            // Update search results info
            updateSearchInfo(visibleCount, filter);
            this.classList.remove('loading');
        }, 300);
    });

    // Filter Buttons
    const filterButtons = document.querySelectorAll('.glass-filter-btn');
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.dataset.filter;

            // Update active state
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            // Filter cards
            const classCards = classesGrid.querySelectorAll('.class-card');
            classCards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = '';
                    card.style.animation = 'fadeInUp 0.3s ease-out forwards';
                } else {
                    const cardStatus = card.dataset.status;
                    const shouldShow = cardStatus === filter;
                    card.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) {
                        card.style.animation = 'fadeInUp 0.3s ease-out forwards';
                    }
                }
            });
        });
    });

    // Enhanced Action Buttons
    const actionButtons = document.querySelectorAll('.glass-action-btn');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.closest('form')) {
                const form = this.closest('form');
                const originalContent = this.innerHTML;

                form.addEventListener('submit', function(e) {
                    button.disabled = true;
                    button.classList.add('loading');
                    button.innerHTML = '<i class="fas fa-spinner fa-spin text-white"></i>';

                    // Restore button after 3 seconds if still on page
                    setTimeout(() => {
                        if (button) {
                            button.disabled = false;
                            button.classList.remove('loading');
                            button.innerHTML = originalContent;
                        }
                    }, 3000);
                });
            }
        });
    });

    // Search info update
    function updateSearchInfo(count, filter) {
        let infoElement = document.querySelector('.search-info');
        if (!infoElement) {
            infoElement = document.createElement('div');
            infoElement.className = 'search-info text-sm text-slate-400 mt-4 px-4 py-2 bg-white/5 rounded-lg backdrop-blur-sm border border-white/10';
            searchInput.parentNode.appendChild(infoElement);
        }

        if (filter) {
            infoElement.textContent = `Found ${count} class${count !== 1 ? 'es' : ''} matching "${filter}"`;
            infoElement.style.display = 'block';
        } else {
            infoElement.style.display = 'none';
        }
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+F to focus search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            searchInput.focus();
        }

        // Escape to clear search
        if (e.key === 'Escape' && searchInput === document.activeElement) {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
        }
    });

    // Smooth scroll for pagination
    const paginationLinks = document.querySelectorAll('.pagination-controls a');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Smooth scroll to top when paginating
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
});
</script>

<style>
/* Glass Morphism Components */
.glass-morphism-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.glass-morphism-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

/* Class Cards */
.class-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.class-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
}

/* Class Avatar */
.class-avatar-large {
    width: 60px;
    height: 60px;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    color: white;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

/* Status Badges */
.glass-status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.glass-status-active {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.glass-status-inactive {
    background: rgba(156, 163, 175, 0.1);
    border-color: rgba(156, 163, 175, 0.3);
    color: #6b7280;
}

/* Level Badges */
.glass-level-badge {
    padding: 0.5rem 1rem;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: white;
    backdrop-filter: blur(10px);
    border: 1px solid;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    width: fit-content;
}

/* Major Info */
.glass-major-info {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: #e2e8f0;
    background: rgba(148, 163, 184, 0.1);
    border: 1px solid rgba(148, 163, 184, 0.2);
    backdrop-filter: blur(10px);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Mini Stats */
.glass-stat-mini {
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-align: center;
}

/* Action Buttons */
.glass-action-btn {
    width: 40px;
    height: 40px;
    border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-decoration: none;
    color: #e2e8f0;
}

.glass-action-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.glass-action-view:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

.glass-action-edit:hover {
    background: rgba(245, 158, 11, 0.2);
    border-color: rgba(245, 158, 11, 0.3);
    color: #f59e0b;
}

.glass-action-delete:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Search Input */
.glass-search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 3rem;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    color: white;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.glass-search-input:focus {
    outline: none;
    border-color: rgba(59, 130, 246, 0.5);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.glass-search-input::placeholder {
    color: #94a3b8;
}

/* Filter Buttons */
.glass-filter-btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #e2e8f0;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    transition: all 0.2s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.glass-filter-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    color: white;
}

.glass-filter-btn.active {
    background: rgba(59, 130, 246, 0.2);
    border-color: rgba(59, 130, 246, 0.4);
    color: #3b82f6;
}

/* Action Button (CTA) */
.glass-action-button {
    padding: 1rem;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-decoration: none;
    color: white;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 1rem;
}

.glass-action-button:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    transform: translateX(4px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

/* Notifications */
.glass-notification {
    padding: 1rem;
    border-radius: 0.75rem;
    backdrop-filter: blur(10px);
    border: 1px solid;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.glass-notification-success {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.glass-notification-error {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Stat Cards */
.glass-stat-card {
    padding: 1rem;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-align: center;
    min-width: 120px;
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Loading States */
.loading {
    position: relative;
}

.loading::after {
    content: '';
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border: 2px solid #e2e8f0;
    border-top: 2px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: translateY(-50%) rotate(0deg); }
    100% { transform: translateY(-50%) rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .glass-morphism-card {
        margin: 0 1rem;
    }

    .class-card {
        margin: 0 0.5rem;
    }

    .glass-search-input {
        font-size: 16px; /* Prevent zoom on iOS */
    }

    .glass-filter-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
}

/* Theme Detection */
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

/* Print Styles */
@media print {
    .glass-morphism-card,
    .class-card {
        background: white !important;
        border: 1px solid #e5e7eb !important;
        box-shadow: none !important;
    }
}
</style>
@endsection
