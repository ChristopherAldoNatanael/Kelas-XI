@extends('layouts.app')

@section('title', 'Class Details - ' . $class->nama_kelas)

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
                            Class Profile
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Detailed information for <span class="text-cyan-300 font-semibold">{{ $class->nama_kelas }}</span>.
                            View comprehensive class data and enrolled students.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $class->users->count() }}</div>
                            <div class="text-white/70 text-sm">Enrolled Students</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ ucfirst($class->status) }}</div>
                            <div class="text-white/70 text-sm">Status</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $class->capacity ?: 'N/A' }}</div>
                            <div class="text-white/70 text-sm">Capacity</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0 flex flex-wrap gap-3">
                    <a href="{{ route('web-classes.edit', $class->id) }}" class="glass-action-button group">
                        <div class="p-4 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 border border-blue-400/20">
                            <i class="fas fa-edit text-blue-300 text-2xl"></i>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Edit Class</div>
                            <div class="text-slate-300 text-sm">Modify details</div>
                        </div>
                        <i class="fas fa-arrow-right text-slate-400 group-hover:text-white transition-colors duration-300"></i>
                    </a>
                    <a href="{{ route('web-classes.index') }}" class="glass-action-button group">
                        <div class="p-4 rounded-xl bg-gradient-to-br from-slate-500/20 to-slate-600/20 border border-slate-400/20">
                            <i class="fas fa-arrow-left text-slate-300 text-2xl"></i>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Back to Classes</div>
                            <div class="text-slate-300 text-sm">Return to list</div>
                        </div>
                        <i class="fas fa-arrow-left text-slate-400 group-hover:text-white transition-colors duration-300"></i>
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

    <!-- Class Details with Glass Morphism -->
    <div class="px-6">
        <div class="max-w-7xl mx-auto">
            <!-- Class Overview Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Class Icon & Quick Info -->
                <div class="lg:col-span-1">
                    <div class="glass-morphism-card text-center p-8 space-y-6">
                        <div class="relative">
                            <div class="w-32 h-32 mx-auto bg-gradient-to-br from-cyan-500 to-blue-600 rounded-full flex items-center justify-center text-white text-4xl font-bold shadow-xl">
                                <i class="fas fa-users-class"></i>
                            </div>
                            <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center">
                                <i class="fas fa-check text-white text-sm"></i>
                            </div>
                        </div>

                        <div>
                            <h2 class="text-2xl font-bold text-white mb-2">{{ $class->nama_kelas }}</h2>
                            <p class="text-slate-300 font-medium">{{ $class->kode_kelas ?: 'No Code' }}</p>
                            <div class="mt-3 inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/20 border border-cyan-400/30">
                                <i class="fas fa-check-circle text-cyan-400"></i>
                                <span class="text-cyan-300 text-sm font-medium">{{ ucfirst($class->status) }}</span>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="grid grid-cols-2 gap-4 pt-6 border-t border-white/10">
                            <div class="glass-stat-mini">
                                <div class="text-lg font-bold text-white">{{ $class->users->count() }}</div>
                                <div class="text-xs text-slate-400">Students</div>
                            </div>
                            <div class="glass-stat-mini">
                                <div class="text-lg font-bold text-white">{{ $class->capacity ?: 'N/A' }}</div>
                                <div class="text-xs text-slate-400">Capacity</div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="glass-morphism-card mt-6 p-6 space-y-4">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <i class="fas fa-cogs text-slate-400 mr-2"></i>
                            Actions
                        </h3>

                        <form method="POST" action="{{ route('web-classes.destroy', $class->id) }}" class="w-full">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-full glass-action-btn glass-action-danger"
                                    onclick="return confirm('Are you sure you want to delete this class? This action cannot be undone.')">
                                <i class="fas fa-trash mr-2"></i>Delete Class
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Detailed Information -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Information -->
                    <div class="glass-morphism-card p-8">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 border border-blue-400/20">
                                <i class="fas fa-info-circle text-blue-300 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-white mb-1">Basic Information</h3>
                                <p class="text-slate-300">Core class identification and details</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wide">Class ID</div>
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-slate-500/20 border border-slate-400/30">
                                        <i class="fas fa-hashtag text-slate-300"></i>
                                    </div>
                                    <span class="text-white font-mono text-lg">#{{ $class->id }}</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wide">Class Name</div>
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-cyan-500/20 border border-cyan-400/30">
                                        <i class="fas fa-users-class text-cyan-300"></i>
                                    </div>
                                    <span class="text-white font-semibold text-lg">{{ $class->nama_kelas }}</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wide">Class Code</div>
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-blue-500/20 border border-blue-400/30">
                                        <i class="fas fa-tag text-blue-300"></i>
                                    </div>
                                    <span class="text-white font-medium">{{ $class->kode_kelas ?: 'Not specified' }}</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wide">Status</div>
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-green-500/20 border border-green-400/30">
                                        <i class="fas fa-toggle-on text-green-300"></i>
                                    </div>
                                    <span class="text-white font-medium">{{ ucfirst($class->status) }}</span>
                                </div>
                            </div>
                        </div>

                        @if($class->deskripsi)
                            <div class="mt-6 pt-6 border-t border-white/10">
                                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-3">Description</div>
                                <div class="bg-slate-500/10 border border-slate-400/20 rounded-lg p-4">
                                    <p class="text-slate-200 leading-relaxed">{{ $class->deskripsi }}</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Academic Information -->
                    <div class="glass-morphism-card p-8">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-green-500/20 to-emerald-500/20 border border-green-400/20">
                                <i class="fas fa-graduation-cap text-green-300 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-white mb-1">Academic Information</h3>
                                <p class="text-slate-300">Grade level, academic year, and specialization details</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="glass-stat-card">
                                <div class="text-3xl font-bold text-white mb-1">{{ $class->tingkat_kelas }}</div>
                                <div class="text-slate-300 text-sm">Grade Level</div>
                                <div class="text-slate-400 text-xs mt-1">Academic year level</div>
                            </div>
                            <div class="glass-stat-card">
                                <div class="text-3xl font-bold text-white mb-1">{{ $class->tahun_ajaran }}</div>
                                <div class="text-slate-300 text-sm">Academic Year</div>
                                <div class="text-slate-400 text-xs mt-1">School year</div>
                            </div>
                        </div>

                        @if($class->major)
                            <div class="mt-6">
                                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-3">Major/Specialization</div>
                                <div class="inline-flex items-center gap-3 px-4 py-2 rounded-lg bg-purple-500/20 border border-purple-400/30">
                                    <i class="fas fa-cogs text-purple-300"></i>
                                    <span class="text-purple-300 font-medium">{{ $class->major }}</span>
                                </div>
                            </div>
                        @endif

                        @if($class->homeroomTeacher)
                            <div class="mt-6">
                                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-3">Homeroom Teacher</div>
                                <div class="inline-flex items-center gap-3 px-4 py-2 rounded-lg bg-indigo-500/20 border border-indigo-400/30">
                                    <i class="fas fa-chalkboard-teacher text-indigo-300"></i>
                                    <span class="text-indigo-300 font-medium">{{ $class->homeroomTeacher->nama }} ({{ $class->homeroomTeacher->nip }})</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Student Information -->
                    <div class="glass-morphism-card p-8">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-orange-500/20 to-amber-500/20 border border-orange-400/20">
                                <i class="fas fa-users text-orange-300 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-white mb-1">Student Information</h3>
                                <p class="text-slate-300">Enrolled students and capacity details</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="glass-stat-card">
                                <div class="text-3xl font-bold text-white mb-1">{{ $class->users->count() }}</div>
                                <div class="text-slate-300 text-sm">Enrolled Students</div>
                                <div class="text-slate-400 text-xs mt-1">Currently enrolled</div>
                            </div>
                            <div class="glass-stat-card">
                                <div class="text-3xl font-bold text-white mb-1">{{ $class->capacity ?: 'N/A' }}</div>
                                <div class="text-slate-300 text-sm">Class Capacity</div>
                                <div class="text-slate-400 text-xs mt-1">Maximum capacity</div>
                            </div>
                            <div class="glass-stat-card">
                                <div class="text-3xl font-bold text-white mb-1">{{ $class->capacity ? max(0, $class->capacity - $class->users->count()) : 'N/A' }}</div>
                                <div class="text-slate-300 text-sm">Available Slots</div>
                                <div class="text-slate-400 text-xs mt-1">Remaining capacity</div>
                            </div>
                        </div>

                        @if($class->users->count() > 0)
                            <div class="mt-6">
                                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-3">Recent Students</div>
                                <div class="space-y-3">
                                    @foreach($class->users->take(5) as $student)
                                        <div class="flex items-center gap-3 p-3 rounded-lg bg-slate-500/10 border border-slate-400/20">
                                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                                {{ strtoupper(substr($student->nama, 0, 1)) }}
                                            </div>
                                            <div class="flex-1">
                                                <div class="text-white font-medium">{{ $student->nama }}</div>
                                                <div class="text-slate-400 text-sm">{{ $student->email }}</div>
                                            </div>
                                            <div class="text-slate-400 text-sm">{{ $student->role }}</div>
                                        </div>
                                    @endforeach
                                    @if($class->users->count() > 5)
                                        <div class="text-center pt-2">
                                            <span class="text-slate-400 text-sm">And {{ $class->users->count() - 5 }} more students...</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- System Information -->
                    <div class="glass-morphism-card p-8">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-gray-500/20 to-slate-500/20 border border-gray-400/20">
                                <i class="fas fa-database text-gray-300 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-white mb-1">System Information</h3>
                                <p class="text-slate-300">Record creation and modification details</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wide">Record ID</div>
                                <div class="text-white font-mono text-lg">#{{ $class->id }}</div>
                            </div>
                            <div class="space-y-2">
                                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wide">Created</div>
                                <div class="text-white">{{ $class->created_at->format('M d, Y') }}</div>
                                <div class="text-slate-400 text-sm">{{ $class->created_at->diffForHumans() }}</div>
                            </div>
                            <div class="space-y-2">
                                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wide">Last Updated</div>
                                <div class="text-white">{{ $class->updated_at->format('M d, Y') }}</div>
                                <div class="text-slate-400 text-sm">{{ $class->updated_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Related Information -->
                    <div class="glass-morphism-card p-8">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-cyan-500/20 to-blue-500/20 border border-cyan-400/20">
                                <i class="fas fa-link text-cyan-300 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-white mb-1">Related Information</h3>
                                <p class="text-slate-300">Connected records and relationships</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="glass-morphism-card p-6 bg-slate-500/10 border-slate-400/20">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 border border-blue-400/20">
                                        <i class="fas fa-chalkboard-teacher text-blue-300"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-white font-semibold mb-1">Schedules</h4>
                                        <p class="text-slate-400 text-sm mb-3">Class schedules and timetables</p>
                                        <a href="{{ route('web-schedules.index') }}?class={{ $class->id }}" class="glass-action-btn glass-action-secondary text-sm">
                                            <i class="fas fa-eye mr-2"></i>View Schedules
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="glass-morphism-card p-6 bg-slate-500/10 border-slate-400/20">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 rounded-xl bg-gradient-to-br from-green-500/20 to-emerald-500/20 border border-green-400/20">
                                        <i class="fas fa-user-graduate text-green-300"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-white font-semibold mb-1">All Students</h4>
                                        <p class="text-slate-400 text-sm mb-3">Complete student roster</p>
                                        <a href="{{ route('web-users.index') }}?class={{ $class->id }}" class="glass-action-btn glass-action-secondary text-sm">
                                            <i class="fas fa-eye mr-2"></i>View Students
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

    // Add loading state to delete button
    const deleteButton = document.querySelector('form button[type="submit"]');
    if (deleteButton) {
        deleteButton.closest('form').addEventListener('submit', function(e) {
            if (!deleteButton.onclick || deleteButton.onclick()) {
                deleteButton.disabled = true;
                const originalText = deleteButton.innerHTML;
                deleteButton.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-3 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Deleting...
                `;

                // Re-enable after 5 seconds if still on page
                setTimeout(() => {
                    if (deleteButton.disabled) {
                        deleteButton.disabled = false;
                        deleteButton.innerHTML = originalText;
                    }
                }, 5000);
            }
        });
    }

    // Animate stat cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.glass-morphism-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease-out';
        observer.observe(card);
    });

    // Add hover effects
    const detailItems = document.querySelectorAll('.space-y-2');
    detailItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(4px)';
        });

        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
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

/* Action Buttons */
.glass-action-btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-decoration: none;
    color: #e2e8f0;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.glass-action-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.glass-action-secondary {
    background: rgba(148, 163, 184, 0.1);
    border-color: rgba(148, 163, 184, 0.3);
    color: #94a3b8;
}

.glass-action-secondary:hover {
    background: rgba(148, 163, 184, 0.2);
    border-color: rgba(148, 163, 184, 0.4);
    color: #cbd5e1;
}

.glass-action-danger {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.glass-action-danger:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.4);
    color: #f87171;
}

.glass-action-success {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.glass-action-success:hover {
    background: rgba(34, 197, 94, 0.2);
    border-color: rgba(34, 197, 94, 0.4);
    color: #16a34a;
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

/* Stat Cards */
.glass-stat-card {
    padding: 1.5rem;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-align: center;
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

/* Responsive Design */
@media (max-width: 768px) {
    .glass-morphism-card {
        margin: 0 1rem;
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
</style>
@endsection
