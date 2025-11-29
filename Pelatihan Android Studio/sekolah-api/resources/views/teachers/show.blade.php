@extends('layouts.app')

@section('title', 'Teacher Details - ' . $teacher->nama)

@section('content')
<div class="min-h-screen">
    <!-- Hero Header with Glass Morphism -->
    <div class="relative overflow-hidden rounded-3xl mx-6 mb-8">
        <!-- Background Layers -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-white/10"></div>

        <!-- Animated Background Elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-purple-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-indigo-500/5 rounded-full blur-2xl animate-pulse" style="animation-delay: 4s;"></div>

        <!-- Glass Morphism Overlay -->
        <div class="relative backdrop-blur-xl bg-white/5 border border-white/10 rounded-3xl p-8 md:p-12">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/20">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-white/90 text-sm font-medium">Viewing Teacher Profile</span>
                    </div>

                    <div>
                        <h1 class="text-4xl md:text-5xl font-bold text-white mb-3 tracking-tight">
                            {{ $teacher->nama }}
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Complete teacher profile and professional information. Monitor their status and manage their teaching assignments.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $teacher->id }}</div>
                            <div class="text-white/70 text-sm">Teacher ID</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $teacher->nip }}</div>
                            <div class="text-white/70 text-sm">NIP</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ ucfirst($teacher->status) }}</div>
                            <div class="text-white/70 text-sm">Status</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $teacher->created_at->diffForHumans() }}</div>
                            <div class="text-white/70 text-sm">Joined</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <!-- Gradient Avatar -->
                    <div class="flex flex-col gap-6">
                        <div class="relative">
                            <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-blue-500 via-purple-500 to-pink-500 flex items-center justify-center shadow-2xl">
                                <span class="text-white text-4xl font-bold">
                                    {{ strtoupper(substr($teacher->nama, 0, 2)) }}
                                </span>
                            </div>
                            <!-- Animated border -->
                            <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-blue-400 via-purple-400 to-pink-400 animate-spin opacity-75 blur-sm" style="animation-duration: 8s;"></div>
                        </div>

                        <div class="flex flex-col gap-3">
                            <a href="{{ route('web-teachers.edit', $teacher->id) }}" class="glass-action-button group">
                                <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-400/20">
                                    <i class="fas fa-edit text-blue-300 text-lg"></i>
                                </div>
                                <div>
                                    <div class="text-white font-semibold">Edit Profile</div>
                                    <div class="text-slate-300 text-sm">Modify details</div>
                                </div>
                            </a>

                            <a href="{{ route('web-teachers.index') }}" class="glass-action-button group">
                                <div class="p-3 rounded-xl bg-gradient-to-br from-slate-500/20 to-slate-600/20 border border-slate-400/20">
                                    <i class="fas fa-arrow-left text-slate-300 text-lg"></i>
                                </div>
                                <div>
                                    <div class="text-white font-semibold">Back to Teachers</div>
                                    <div class="text-slate-300 text-sm">View all teachers</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Container -->
    <div class="px-6 space-y-8">
        <!-- Information Cards Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Professional Information Card -->
            <div class="lg:col-span-2">
                <div class="glass-morphism-card">
                    <div class="p-8">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="p-4 rounded-xl bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-400/20">
                                <i class="fas fa-chalkboard-teacher text-blue-300 text-3xl"></i>
                            </div>
                            <div>
                                <h2 class="text-3xl font-bold text-white mb-2">Professional Information</h2>
                                <p class="text-slate-300 text-lg">Complete teacher profile and professional details</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Full Name -->
                            <div class="glass-info-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="p-2 rounded-lg bg-blue-500/20">
                                        <i class="fas fa-user text-blue-400"></i>
                                    </div>
                                    <div class="text-slate-400 text-sm font-medium">Full Name</div>
                                </div>
                                <div class="text-white font-bold text-xl">{{ $teacher->nama }}</div>
                            </div>

                            <!-- NIP -->
                            <div class="glass-info-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="p-2 rounded-lg bg-green-500/20">
                                        <i class="fas fa-id-card text-green-400"></i>
                                    </div>
                                    <div class="text-slate-400 text-sm font-medium">NIP</div>
                                </div>
                                <div class="text-white font-bold text-lg">{{ $teacher->nip }}</div>
                            </div>

                            <!-- Teacher Code -->
                            <div class="glass-info-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="p-2 rounded-lg bg-purple-500/20">
                                        <i class="fas fa-hashtag text-purple-400"></i>
                                    </div>
                                    <div class="text-slate-400 text-sm font-medium">Teacher Code</div>
                                </div>
                                <div class="text-white font-bold text-lg">{{ $teacher->teacher_code }}</div>
                            </div>

                            <!-- Position -->
                            <div class="glass-info-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="p-2 rounded-lg bg-indigo-500/20">
                                        <i class="fas fa-briefcase text-indigo-400"></i>
                                    </div>
                                    <div class="text-slate-400 text-sm font-medium">Position</div>
                                </div>
                                <div class="text-white font-bold text-lg">{{ $teacher->position }}</div>
                            </div>

                            <!-- Department -->
                            <div class="glass-info-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="p-2 rounded-lg bg-teal-500/20">
                                        <i class="fas fa-building text-teal-400"></i>
                                    </div>
                                    <div class="text-slate-400 text-sm font-medium">Department</div>
                                </div>
                                <div class="text-white font-bold text-lg">{{ $teacher->department }}</div>
                            </div>

                            <!-- Join Date -->
                            <div class="glass-info-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="p-2 rounded-lg bg-orange-500/20">
                                        <i class="fas fa-calendar-plus text-orange-400"></i>
                                    </div>
                                    <div class="text-slate-400 text-sm font-medium">Join Date</div>
                                </div>
                                <div class="text-white font-bold text-lg">{{ $teacher->join_date->format('M d, Y') }}</div>
                                <div class="text-slate-400 text-sm">{{ $teacher->join_date->diffForHumans() }}</div>
                            </div>

                            <!-- Expertise -->
                            @if($teacher->expertise)
                            <div class="glass-info-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="p-2 rounded-lg bg-yellow-500/20">
                                        <i class="fas fa-star text-yellow-400"></i>
                                    </div>
                                    <div class="text-slate-400 text-sm font-medium">Expertise</div>
                                </div>
                                <div class="text-white font-bold text-lg">{{ $teacher->expertise }}</div>
                            </div>
                            @endif

                            <!-- Certification -->
                            @if($teacher->certification)
                            <div class="glass-info-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="p-2 rounded-lg bg-cyan-500/20">
                                        <i class="fas fa-certificate text-cyan-400"></i>
                                    </div>
                                    <div class="text-slate-400 text-sm font-medium">Certification</div>
                                </div>
                                <div class="text-white font-bold text-lg">{{ $teacher->certification }}</div>
                            </div>
                            @endif

                            <!-- Account Created -->
                            <div class="glass-info-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="p-2 rounded-lg bg-pink-500/20">
                                        <i class="fas fa-plus-circle text-pink-400"></i>
                                    </div>
                                    <div class="text-slate-400 text-sm font-medium">Account Created</div>
                                </div>
                                <div class="text-white font-bold text-lg">{{ $teacher->created_at->format('M d, Y') }}</div>
                                <div class="text-slate-400 text-sm">{{ $teacher->created_at->format('H:i') }} • {{ $teacher->created_at->diffForHumans() }}</div>
                            </div>

                            <!-- Last Updated -->
                            @if($teacher->updated_at != $teacher->created_at)
                            <div class="glass-info-card">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="p-2 rounded-lg bg-emerald-500/20">
                                        <i class="fas fa-clock text-emerald-400"></i>
                                    </div>
                                    <div class="text-slate-400 text-sm font-medium">Last Updated</div>
                                </div>
                                <div class="text-white font-bold text-lg">{{ $teacher->updated_at->format('M d, Y') }}</div>
                                <div class="text-slate-400 text-sm">{{ $teacher->updated_at->format('H:i') }} • {{ $teacher->updated_at->diffForHumans() }}</div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status & Actions Sidebar -->
            <div class="space-y-6">
                <!-- Account Status Card -->
                <div class="glass-morphism-card">
                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-emerald-500/20 to-emerald-600/20 border border-emerald-400/20">
                                <i class="fas fa-shield-alt text-emerald-300 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Employment Status</h3>
                                <p class="text-slate-300 text-sm">Current teaching status</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-center mb-6">
                            @if($teacher->status === 'active')
                                <div class="glass-status-indicator glass-status-active">
                                    <i class="fas fa-check-circle text-2xl"></i>
                                    <span class="ml-3 text-lg font-bold">Active Teacher</span>
                                </div>
                            @elseif($teacher->status === 'inactive')
                                <div class="glass-status-indicator glass-status-inactive">
                                    <i class="fas fa-pause-circle text-2xl"></i>
                                    <span class="ml-3 text-lg font-bold">Inactive</span>
                                </div>
                            @else
                                <div class="glass-status-indicator glass-status-retired">
                                    <i class="fas fa-user-clock text-2xl"></i>
                                    <span class="ml-3 text-lg font-bold">Retired</span>
                                </div>
                            @endif
                        </div>

                        @if($teacher->status !== 'active')
                            <div class="glass-alert glass-alert-warning">
                                <i class="fas fa-exclamation-triangle text-lg"></i>
                                <span class="ml-2">
                                    @if($teacher->status === 'inactive')
                                        This teacher is currently inactive
                                    @else
                                        This teacher has retired
                                    @endif
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="glass-morphism-card">
                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-violet-500/20 to-violet-600/20 border border-violet-400/20">
                                <i class="fas fa-bolt text-violet-300 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Quick Actions</h3>
                                <p class="text-slate-300 text-sm">Manage this teacher</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <a href="{{ route('web-teachers.edit', $teacher->id) }}" class="glass-action-button glass-action-primary w-full justify-center">
                                <i class="fas fa-edit mr-3 text-lg"></i>
                                <span class="font-semibold">Edit Teacher</span>
                            </a>

                            @if($teacher->status === 'active')
                                <form method="POST" action="{{ route('web-teachers.update', $teacher->id) }}" class="w-full">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="inactive">
                                    <button type="submit" class="glass-action-button glass-action-warning w-full justify-center" onclick="return confirm('Are you sure you want to set this teacher as inactive?')">
                                        <i class="fas fa-pause-circle mr-3 text-lg"></i>
                                        <span class="font-semibold">Set Inactive</span>
                                    </button>
                                </form>
                            @elseif($teacher->status === 'inactive')
                                <form method="POST" action="{{ route('web-teachers.update', $teacher->id) }}" class="w-full">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="active">
                                    <button type="submit" class="glass-action-button glass-action-success w-full justify-center">
                                        <i class="fas fa-play-circle mr-3 text-lg"></i>
                                        <span class="font-semibold">Reactivate</span>
                                    </button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('web-teachers.destroy', $teacher->id) }}" class="w-full">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="glass-action-button glass-action-danger w-full justify-center" onclick="return confirm('Are you sure you want to delete this teacher?')">
                                    <i class="fas fa-trash mr-3 text-lg"></i>
                                    <span class="font-semibold">Delete Teacher</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
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
}

.glass-morphism-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.glass-stat-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 0.75rem;
    padding: 1rem;
    text-align: center;
    transition: all 0.3s ease;
}

.glass-stat-card:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
}

.glass-info-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.75rem;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.glass-info-card:hover {
    background: rgba(255, 255, 255, 0.06);
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.12);
}

.glass-status-indicator {
    display: inline-flex;
    align-items: center;
    padding: 1rem 2rem;
    border-radius: 3rem;
    font-weight: 600;
    font-size: 1rem;
    backdrop-filter: blur(15px);
    border: 2px solid;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.glass-status-active {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2) 0%, rgba(22, 163, 74, 0.1) 100%);
    border-color: rgba(34, 197, 94, 0.4);
    color: #22c55e;
    box-shadow: 0 0 20px rgba(34, 197, 94, 0.2);
}

.glass-status-inactive {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(217, 119, 6, 0.1) 100%);
    border-color: rgba(245, 158, 11, 0.4);
    color: #f59e0b;
    box-shadow: 0 0 20px rgba(245, 158, 11, 0.2);
}

.glass-status-retired {
    background: linear-gradient(135deg, rgba(107, 114, 128, 0.2) 0%, rgba(75, 85, 99, 0.1) 100%);
    border-color: rgba(107, 114, 128, 0.4);
    color: #6b7280;
    box-shadow: 0 0 20px rgba(107, 114, 128, 0.2);
}

.glass-alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    backdrop-filter: blur(15px);
    border: 1px solid;
}

.glass-alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
    color: #f59e0b;
}

.glass-action-button {
    padding: 1rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.glass-action-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border-color: rgba(59, 130, 246, 0.3);
    color: white;
}

.glass-action-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
}

.glass-action-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-color: rgba(16, 185, 129, 0.3);
    color: white;
}

.glass-action-success:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
}

.glass-action-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-color: rgba(245, 158, 11, 0.3);
    color: white;
}

.glass-action-warning:hover {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
}

.glass-action-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border-color: rgba(239, 68, 68, 0.3);
    color: white;
}

.glass-action-danger:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
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

@keyframes pulse {
    0%, 100% {
        opacity: 0.5;
    }
    50% {
        opacity: 1;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .glass-morphism-card {
        margin: 0 0.5rem;
    }

    .glass-info-card {
        padding: 1rem;
    }

    .glass-action-button {
        width: 100%;
        justify-content: center;
        margin-bottom: 0.5rem;
    }
}

/* Theme Support */
.dark {
    /* Dark mode styles are default */
}

.light {
    /* Light mode overrides */
    .glass-morphism-card {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .glass-info-card {
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }
}
</style>
@endsection

@section('scripts')
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

    // Smooth animations for cards
    const cards = document.querySelectorAll('.glass-morphism-card, .glass-info-card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.animation = 'fadeInUp 0.6s ease-out forwards';
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        }, index * 100);
    });

    // Add loading states to action buttons
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i><span class="font-semibold">Processing...</span>';
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');

                // Re-enable if form submission fails
                setTimeout(() => {
                    if (submitBtn.classList.contains('loading')) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                    }
                }, 5000);
            }
        });
    });

    // Add hover effects to stat cards
    const statCards = document.querySelectorAll('.glass-stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px) scale(1.02)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Add interactive effects to info cards
    const infoCards = document.querySelectorAll('.glass-info-card');
    infoCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.15)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });
});
</script>
@endsection
