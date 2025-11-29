@extends('layouts.app')

@section('title', 'Schedule Details')

@section('content')
<div class="min-h-screen">
    <!-- Hero Header with Glass Morphism -->
    <div class="relative overflow-hidden rounded-3xl mx-6 mb-8">
        <!-- Background Layers -->
        <div class="absolute inset-0 bg-gradient-to-br from-purple-900 via-purple-800 to-purple-900"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-white/10"></div>

        <!-- Animated Background Elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-violet-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-purple-500/5 rounded-full blur-2xl animate-pulse" style="animation-delay: 4s;"></div>

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
                            Schedule Details
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Complete information about this class schedule and academic session details.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $schedule->mata_pelajaran }}</div>
                            <div class="text-white/70 text-sm">Subject</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $schedule->hari }}</div>
                            <div class="text-white/70 text-sm">Day</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $schedule->jam_mulai }} - {{ $schedule->jam_selesai }}</div>
                            <div class="text-white/70 text-sm">Time</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <div class="flex gap-3">
                        <a href="{{ route('web-schedules.edit', $schedule->id) }}" class="glass-action-button group">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-cyan-500/20 border border-blue-400/20">
                                <i class="fas fa-edit text-blue-300 text-xl"></i>
                            </div>
                            <div>
                                <div class="text-white font-semibold">Edit Schedule</div>
                                <div class="text-slate-300 text-sm">Modify details</div>
                            </div>
                        </a>
                        <a href="{{ route('web-schedules.index') }}" class="glass-action-button group">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-slate-500/20 to-gray-500/20 border border-slate-400/20">
                                <i class="fas fa-arrow-left text-slate-300 text-xl"></i>
                            </div>
                            <div>
                                <div class="text-white font-semibold">Back to List</div>
                                <div class="text-slate-300 text-sm">All schedules</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if(session('success') && session('highlight_new'))
        <div class="mx-6 mb-6 glass-notification glass-notification-success">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-green-500/20">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <span class="text-white">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Schedule Information Cards -->
    <div class="px-6 space-y-6">
        <!-- Quick Info Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Subject Card -->
            <div class="glass-morphism-card p-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-green-500/20 to-emerald-500/20 border border-green-400/20">
                        <i class="fas fa-book text-green-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Subject</h3>
                        <p class="text-slate-300 font-semibold">{{ $schedule->mata_pelajaran }}</p>
                        <p class="text-slate-400 text-sm">{{ $schedule->subject->nama ?? 'No additional info' }}</p>
                    </div>
                </div>
            </div>

            <!-- Teacher Card -->
            <div class="glass-morphism-card p-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20 border border-purple-400/20">
                        <i class="fas fa-chalkboard-teacher text-purple-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Teacher</h3>
                        <p class="text-slate-300 font-semibold">{{ $schedule->guru->name ?? 'N/A' }}</p>
                        <p class="text-slate-400 text-sm">ID: {{ $schedule->guru_id }}</p>
                    </div>
                </div>
            </div>

            <!-- Classroom Card -->
            <div class="glass-morphism-card p-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-orange-500/20 to-amber-500/20 border border-orange-400/20">
                        <i class="fas fa-map-marker-alt text-orange-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Room</h3>
                        <p class="text-slate-300 font-semibold">{{ $schedule->ruang ?? 'N/A' }}</p>
                        <p class="text-slate-400 text-sm">Classroom location</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Information Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Schedule Details -->
            <div class="glass-morphism-card p-6">
                <h3 class="text-xl font-bold text-white mb-6 flex items-center">
                    <i class="fas fa-calendar-alt mr-3 text-purple-400"></i>
                    Schedule Information
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-3 border-b border-white/10">
                        <span class="text-slate-300">Schedule ID</span>
                        <span class="text-white font-semibold">#{{ $schedule->id }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-white/10">
                        <span class="text-slate-300">Day</span>
                        <span class="text-white font-semibold">{{ $schedule->hari }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-white/10">
                        <span class="text-slate-300">Class</span>
                        <span class="text-white font-semibold">{{ $schedule->kelas }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-white/10">
                        <span class="text-slate-300">Start Time</span>
                        <span class="text-white font-semibold">{{ $schedule->jam_mulai }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3">
                        <span class="text-slate-300">End Time</span>
                        <span class="text-white font-semibold">{{ $schedule->jam_selesai }}</span>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="glass-morphism-card p-6">
                <h3 class="text-xl font-bold text-white mb-6 flex items-center">
                    <i class="fas fa-info-circle mr-3 text-blue-400"></i>
                    Additional Details
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-3 border-b border-white/10">
                        <span class="text-slate-300">Status</span>
                        <span class="glass-status-badge glass-status-active">Active</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-white/10">
                        <span class="text-slate-300">Created</span>
                        <span class="text-white font-semibold">{{ $schedule->created_at ? $schedule->created_at->format('M d, Y') : 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3">
                        <span class="text-slate-300">Last Updated</span>
                        <span class="text-white font-semibold">{{ $schedule->updated_at ? $schedule->updated_at->format('M d, Y') : 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row justify-center gap-4 pt-6">
            <a href="{{ route('web-schedules.edit', $schedule->id) }}" class="glass-action-btn glass-action-primary">
                <i class="fas fa-edit mr-2"></i>
                Edit Schedule
            </a>
            <a href="{{ route('web-schedules.index') }}" class="glass-action-btn glass-action-secondary">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Schedules
            </a>
            <form method="POST" action="{{ route('web-schedules.destroy', $schedule->id) }}" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="glass-action-btn glass-action-danger"
                        onclick="return confirm('Are you sure you want to delete this schedule?')">
                    <i class="fas fa-trash-alt mr-2"></i>
                    Delete Schedule
                </button>
            </form>
        </div>
    </div>
</div>

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

/* Action Buttons */
.glass-action-btn {
    padding: 0.75rem 1.5rem;
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
    justify-content: center;
    gap: 0.5rem;
}

.glass-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.glass-action-primary {
    background: rgba(147, 51, 234, 0.1);
    border-color: rgba(147, 51, 234, 0.3);
    color: #c084fc;
}

.glass-action-primary:hover {
    background: rgba(147, 51, 234, 0.2);
    border-color: rgba(147, 51, 234, 0.4);
    color: #ddd6fe;
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

/* Print Styles */
@media print {
    .glass-morphism-card {
        background: white !important;
        border: 1px solid #e5e7eb !important;
        box-shadow: none !important;
    }
}
</style>
@endsection
