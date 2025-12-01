@extends('layouts.app')

@section('title', 'Teacher Leave Management')

@section('content')
<div class="min-h-screen">
    <!-- Hero Header with Glass Morphism -->
    <div class="relative overflow-hidden rounded-3xl mx-6 mb-8" style="background: var(--card-bg);">
        <!-- Background Layers -->
        <div class="absolute inset-0 opacity-50" style="background: linear-gradient(135deg, var(--primary-dark) 0%, var(--secondary-dark) 100%);"></div>
        <div class="absolute inset-0 bg-linear-to-t from-black/20 via-transparent to-white/10"></div>

        <!-- Animated Background Elements -->
        <div class="absolute top-0 right-0 w-96 h-96 rounded-full blur-3xl animate-pulse" style="background: var(--primary-dark); opacity: 0.1;"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 rounded-full blur-3xl animate-pulse" style="background: var(--secondary-dark); opacity: 0.1; animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 rounded-full blur-2xl animate-pulse" style="background: var(--primary-dark); opacity: 0.05; animation-delay: 4s;"></div>

        <!-- Glass Morphism Overlay -->
        <div class="relative backdrop-blur-xl bg-white/5 border border-white/10 rounded-3xl p-8 md:p-12">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/20">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span style="color: var(--text-primary); opacity: 0.9;" class="text-sm font-medium">System Online</span>
                    </div>

                    <div>
                        <h1 class="text-4xl md:text-5xl font-bold mb-3 tracking-tight" style="color: var(--text-primary);">
                            Teacher Leave Management
                        </h1>
                        <p class="text-lg md:text-xl leading-relaxed max-w-2xl" style="color: var(--text-secondary);">
                            Comprehensive leave request system. Manage teacher absences, approvals, and substitute assignments with advanced tracking.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $pendingCount ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Pending</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $approvedCount ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Approved</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $rejectedCount ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Rejected</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <div class="flex gap-4">
                        <a href="{{ route('teacher-leaves.create') }}" class="glass-action-button group">
                            <div class="p-4 rounded-xl bg-linear-to-br from-blue-500/20 to-blue-600/20 border border-blue-400/20">
                                <i class="fas fa-plus text-blue-300 text-2xl"></i>
                            </div>
                            <div>
                                <div class="font-semibold" style="color: var(--text-primary);">Add Leave Request</div>
                                <div class="text-sm" style="color: var(--text-secondary);">Submit new request</div>
                            </div>
                            <i class="fas fa-arrow-right transition-colors duration-300" style="color: var(--text-secondary);"></i>
                        </a>
                    </div>
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
                <span style="color: var(--text-primary);">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mx-6 mb-6 glass-notification glass-notification-error">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-red-500/20">
                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                </div>
                <span style="color: var(--text-primary);">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    @if(session('info'))
        <div class="mx-6 mb-6 glass-notification glass-notification-info">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-blue-500/20">
                    <i class="fas fa-info-circle text-blue-400"></i>
                </div>
                <span style="color: var(--text-primary);">{{ session('info') }}</span>
            </div>
        </div>
    @endif

    <!-- Leave Requests Directory with Glass Morphism -->
    <div class="px-6 space-y-6">
        <!-- Search and Filters Header -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div>
                        <h3 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">Leave Requests Directory</h3>
                        <p style="color: var(--text-secondary);">Browse and manage all teacher leave applications in your system</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search leave requests..."
                                   class="glass-search-input">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);"></i>
                        </div>

                        <div class="flex gap-2">
                            <button class="glass-filter-btn active" data-filter="all">
                                <i class="fas fa-list"></i>
                                All
                            </button>
                            <button class="glass-filter-btn" data-filter="pending">
                                <i class="fas fa-clock"></i>
                                Pending
                            </button>
                            <button class="glass-filter-btn" data-filter="approved">
                                <i class="fas fa-check-circle"></i>
                                Approved
                            </button>
                            <button class="glass-filter-btn" data-filter="rejected">
                                <i class="fas fa-times-circle"></i>
                                Rejected
                            </button>
                        </div>

                        <div class="flex gap-2">
                            <a href="{{ route('teacher-leaves.export.pdf', request()->query()) }}"
                               class="glass-export-btn glass-export-pdf">
                                <i class="fas fa-file-pdf"></i>
                                <span>Export PDF</span>
                            </a>
                            <a href="{{ route('teacher-leaves.export.excel', request()->query()) }}"
                               class="glass-export-btn glass-export-excel">
                                <i class="fas fa-file-excel"></i>
                                <span>Export Excel</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leave Requests Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="leaveRequestsGrid">
            @forelse($leaves ?? [] as $leave)
                <div class="leave-card-modern" data-status="{{ $leave->status }}" data-leave-id="{{ $leave->id }}">
                    <!-- Card Header with Gradient -->
                    @php
                        $statusGradients = [
                            'pending' => 'from-yellow-500/20 via-orange-500/15 to-transparent',
                            'approved' => 'from-green-500/20 via-emerald-500/15 to-transparent',
                            'rejected' => 'from-red-500/20 via-rose-500/15 to-transparent'
                        ];
                        $statusIcons = [
                            'pending' => 'fa-clock',
                            'approved' => 'fa-check-circle',
                            'rejected' => 'fa-times-circle'
                        ];
                        $statusIconColors = [
                            'pending' => 'text-yellow-400',
                            'approved' => 'text-green-400',
                            'rejected' => 'text-red-400'
                        ];
                        $gradient = $statusGradients[$leave->status] ?? 'from-gray-500/20 via-slate-500/15 to-transparent';
                        $statusIcon = $statusIcons[$leave->status] ?? 'fa-question-circle';
                        $iconColor = $statusIconColors[$leave->status] ?? 'text-gray-400';
                    @endphp

                    <div class="card-header-gradient bg-gradient-to-b {{ $gradient }}">
                        <!-- Status Badge -->
                        <div class="absolute top-3 right-3 z-10">
                            <span class="status-badge-modern status-{{ $leave->status }}">
                                <i class="fas {{ $statusIcon }}"></i>
                                <span>{{ ucfirst($leave->status) }}</span>
                            </span>
                        </div>

                        <!-- Teacher Avatar Section -->
                        <div class="text-center pt-8 pb-4">
                            <div class="leave-avatar-professional mx-auto mb-3 relative">
                                <div class="avatar-ring {{ $iconColor }}"></div>
                                <div class="avatar-content">
                                    <span class="avatar-letter">{{ $leave->teacher ? strtoupper(substr($leave->teacher->nama, 0, 1)) : '?' }}</span>
                                </div>
                                <div class="avatar-role-icon {{ $iconColor }}">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                            </div>

                            <h4 class="leave-teacher-name" title="{{ $leave->teacher ? $leave->teacher->nama : 'Unknown Teacher' }}">{{ $leave->teacher ? $leave->teacher->nama : 'Unknown Teacher' }}</h4>
                            <p class="leave-reason" title="{{ $leave->reason }}">
                                <i class="fas fa-calendar-alt mr-1.5"></i>
                                {{ ucfirst(str_replace('_', ' ', $leave->reason)) }}
                            </p>
                            <span class="leave-id">#{{ str_pad($leave->id, 4, '0', STR_PAD_LEFT) }}</span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body-content">
                        <!-- Duration and Dates Section -->
                        <div class="info-section">
                            <div class="duration-box">
                                <i class="fas fa-calendar-week"></i>
                                <div>
                                    <div class="duration-days">{{ \Carbon\Carbon::parse($leave->start_date)->diffInDays(\Carbon\Carbon::parse($leave->end_date)) + 1 }} days</div>
                                    <div class="duration-range">{{ $leave->start_date }} - {{ $leave->end_date }}</div>
                                </div>
                            </div>

                            @if($leave->substituteTeacher)
                                <div class="substitute-box">
                                    <i class="fas fa-user-friends"></i>
                                    <span>{{ $leave->substituteTeacher->name }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Stats Grid -->
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-plus"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-label">Submitted</div>
                                    <div class="stat-value">{{ $leave->created_at ? $leave->created_at->diffForHumans() : 'N/A' }}</div>
                                </div>
                            </div>
                            @if($leave->approved_at)
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-check-double"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-label">Processed</div>
                                        <div class="stat-value">{{ $leave->approved_at->diffForHumans() }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons-grid">
                            <a href="#" onclick="showLeaveDetail({{ $leave->id }})" class="action-btn action-btn-view" title="View Details">
                                <i class="fas fa-eye"></i>
                                <span>View</span>
                            </a>
                            @if($leave->status === 'pending')
                                <a href="#" onclick="approveLeave({{ $leave->id }})" class="action-btn action-btn-approve" title="Approve Leave">
                                    <i class="fas fa-check"></i>
                                    <span>Approve</span>
                                </a>
                                <a href="#" onclick="rejectLeave({{ $leave->id }})" class="action-btn action-btn-reject" title="Reject Leave">
                                    <i class="fas fa-times"></i>
                                    <span>Reject</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <div class="glass-morphism-card">
                        <div class="p-12 text-center">
                            <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-linear-to-br from-slate-500/20 to-slate-600/20 flex items-center justify-center">
                                <i class="fas fa-calendar-times text-4xl" style="color: var(--text-secondary);"></i>
                            </div>
                            <h3 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">No Leave Requests Found</h3>
                            <p class="mb-8 max-w-md mx-auto" style="color: var(--text-secondary);">Get started by creating the first teacher leave request to begin managing absences in your educational platform.</p>
                            <a href="{{ route('teacher-leaves.create') }}" class="glass-action-button group inline-flex">
                                <div class="p-3 rounded-xl bg-linear-to-br from-blue-500/20 to-blue-600/20 border border-blue-400/20">
                                    <i class="fas fa-plus text-blue-300 text-xl"></i>
                                </div>
                                <div>
                                    <div class="font-semibold" style="color: var(--text-primary);">Create First Request</div>
                                    <div class="text-sm" style="color: var(--text-secondary);">Submit leave application</div>
                                </div>
                                <i class="fas fa-arrow-right transition-colors duration-300" style="color: var(--text-secondary);"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Modern Pagination -->
        @if(isset($leaves) && $leaves->hasPages())
            <div class="glass-morphism-card">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle text-blue-400"></i>
                            <span>Showing <span class="font-semibold" style="color: var(--text-primary);">{{ $leaves->firstItem() }}</span> to
                            <span class="font-semibold" style="color: var(--text-primary);">{{ $leaves->lastItem() }}</span> of
                            <span class="font-semibold" style="color: var(--text-primary);">{{ $leaves->total() }}</span> leave requests</span>
                        </div>
                        <div class="pagination-controls">
                            {{ $leaves->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Leave Detail Modal -->
<div id="leave-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="glass-morphism-card rounded-2xl shadow-2xl max-w-5xl w-full max-h-[95vh] overflow-hidden">
            <!-- Modal Header -->
            <div class="p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="p-3 rounded-xl bg-blue-100 dark:bg-blue-900/50">
                            <i class="fas fa-calendar-alt text-blue-600 dark:text-blue-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Leave Request Details</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Comprehensive leave request information</p>
                        </div>
                    </div>
                    <button id="close-modal" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-times text-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <div id="modal-content" class="p-6 overflow-y-auto max-h-[calc(95vh-120px)]">
                <!-- Modal content will be loaded here -->
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<style>
/* Glass Morphism Components */
.glass-morphism-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.glass-stat-card {
    padding: 1rem;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-align: center;
    min-width: 120px;
}

.glass-action-button {
    padding: 1rem;
    border-radius: 0.75rem;
    background: var(--card-bg);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-decoration: none;
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

.glass-search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 3rem;
    border-radius: 0.75rem;
    background: var(--input-bg);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    color: var(--text-primary);
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.glass-search-input:focus {
    outline: none;
    border-color: var(--primary-dark);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.glass-search-input::placeholder {
    color: var(--text-secondary);
}

.glass-filter-btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-secondary);
    background: var(--card-bg);
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
    color: var(--text-primary);
}

.glass-filter-btn.active {
    background: rgba(59, 130, 246, 0.2);
    border-color: rgba(59, 130, 246, 0.4);
    color: #3b82f6;
}

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
}

.glass-notification-error {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
}

.glass-notification-info {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
}

.glass-export-btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-primary);
    background: var(--card-bg);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.glass-export-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.glass-export-pdf {
    color: #dc2626;
}

.glass-export-pdf:hover {
    background: rgba(220, 38, 38, 0.1);
    border-color: rgba(220, 38, 38, 0.3);
}

.glass-export-excel {
    color: #16a34a;
}

.glass-export-excel:hover {
    background: rgba(22, 163, 74, 0.1);
    border-color: rgba(22, 163, 74, 0.3);
}

/* Leave Card Styles */
.leave-card-modern {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    min-height: 320px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.leave-card-modern:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.3);
}

.card-header-gradient {
    position: relative;
    padding: 1.5rem;
    color: white;
}

.status-badge-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid;
}

.status-pending {
    background: rgba(251, 191, 36, 0.1);
    border-color: rgba(251, 191, 36, 0.3);
    color: #fbbf24;
}

.status-approved {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.status-rejected {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.leave-avatar-professional {
    width: 70px;
    height: 70px;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    font-weight: bold;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    position: relative;
}

.avatar-ring {
    position: absolute;
    inset: -2px;
    border-radius: 1rem;
    background: linear-gradient(135deg, currentColor 0%, transparent 100%);
    opacity: 0.2;
}

.avatar-content {
    width: 70px;
    height: 70px;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    z-index: 1;
}

.avatar-letter {
    font-size: 1.75rem;
    font-weight: bold;
    color: var(--text-primary);
}

.avatar-role-icon {
    position: absolute;
    bottom: -5px;
    right: -5px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    background: var(--card-bg);
    border: 2px solid var(--card-bg);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.leave-teacher-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.leave-reason {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.leave-id {
    font-size: 0.75rem;
    color: var(--text-secondary);
    opacity: 0.7;
}

.card-body-content {
    padding: 1.5rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.info-section {
    margin-bottom: 1rem;
}

.duration-box, .substitute-box {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
}

.duration-box {
    color: var(--text-primary);
}

.substitute-box {
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.2);
}

.duration-days {
    font-weight: 700;
}

.duration-range {
    opacity: 0.8;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-bottom: auto;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.2s ease;
}

.stat-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
}

.stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(37, 99, 235, 0.15) 100%);
    color: #3b82f6;
}

.stat-content {
    flex: 1;
    min-width: 0;
}

.stat-label {
    font-size: 0.65rem;
    font-weight: 500;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-value {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.action-buttons-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-top: auto;
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    padding: 0.75rem 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: var(--card-bg);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    text-decoration: none;
}

.action-btn i {
    font-size: 1rem;
}

.action-btn span {
    font-size: 0.7rem;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.action-btn-view {
    color: #3b82f6;
}

.action-btn-view:hover {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
}

.action-btn-approve {
    color: #10b981;
}

.action-btn-approve:hover {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
}

.action-btn-reject {
    color: #ef4444;
}

.action-btn-reject:hover {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
    .glass-morphism-card {
        margin: 0 1rem;
    }

    .leave-card-modern {
        margin: 0 0.5rem;
        min-height: 280px;
    }

    .leave-avatar-professional {
        width: 60px;
        height: 60px;
    }

    .avatar-content {
        width: 60px;
        height: 60px;
    }

    .avatar-letter {
        font-size: 1.5rem;
    }

    .avatar-role-icon {
        width: 24px;
        height: 24px;
        font-size: 0.625rem;
    }

    .leave-teacher-name {
        font-size: 1rem;
    }

    .leave-reason {
        font-size: 0.8rem;
    }

    .action-btn {
        padding: 0.625rem 0.375rem;
    }

    .action-btn i {
        font-size: 0.875rem;
    }

    .action-btn span {
        font-size: 0.65rem;
    }

    .glass-search-input {
        font-size: 16px;
    }

    .glass-filter-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }

    .grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
}

@media (min-width: 640px) and (max-width: 1023px) {
    .grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
<script>
// Global variables
let currentPage = 1;
let currentFilters = {
    status: '{{ request("status", "") }}',
    teacher_id: '{{ request("teacher_id", "") }}',
    search: '{{ request("search", "") }}',
    per_page: 15
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    // Filter buttons
    document.querySelectorAll('.glass-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.glass-filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilters.status = this.dataset.filter === 'all' ? '' : this.dataset.filter;
            currentPage = 1;
            loadLeaveData();
        });
    });

    // Search input
    document.getElementById('searchInput').addEventListener('input', function() {
        currentFilters.search = this.value;
        currentPage = 1;
        loadLeaveData();
    });

    // Modal close
    document.getElementById('close-modal').addEventListener('click', function() {
        document.getElementById('leave-modal').classList.add('hidden');
    });

    document.getElementById('leave-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
}

// Load leave data with client-side filtering
function loadLeaveData() {
    const selectedFilter = currentFilters.status;
    const searchTerm = currentFilters.search.toLowerCase();

    // Get all leave cards
    const leaveCards = document.querySelectorAll('.leave-card-modern');
    let visibleCount = 0;

    leaveCards.forEach(card => {
        const leaveId = card.getAttribute('data-leave-id');
        const leaveStatus = card.getAttribute('data-status');
        const teacherName = card.querySelector('.leave-teacher-name')?.textContent?.toLowerCase() || '';
        const leaveReason = card.querySelector('.leave-reason')?.textContent.toLowerCase() || '';

        // Check status filter
        const statusMatch = selectedFilter === '' || selectedFilter === 'all' || leaveStatus === selectedFilter;

        // Check search filter
        const searchMatch = searchTerm === '' ||
            teacherName.includes(searchTerm) ||
            leaveReason.includes(searchTerm) ||
            leaveId.includes(searchTerm);

        // Show/hide card based on filters
        if (statusMatch && searchMatch) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Update pagination info if needed
    updateVisibleCount(visibleCount);

    console.log(`Filtered ${visibleCount} out of ${leaveCards.length} leave requests`);
}

// Update visible count display
function updateVisibleCount(visibleCount) {
    // You can add a counter display here if needed
    console.log(`Showing ${visibleCount} filtered results`);
}

// Show leave detail modal
function showLeaveDetail(id) {
    const modal = document.getElementById('leave-modal');
    const content = document.getElementById('modal-content');

    content.innerHTML = `
        <div class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            <span class="ml-3 text-slate-400 dark:text-slate-400 light:text-gray-500">Loading leave details...</span>
        </div>
    `;

    modal.classList.remove('hidden');

    // Make AJAX call to load leave details
    fetch(`{{ route('teacher-leaves.show', ':id') }}`.replace(':id', id), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const leave = data.data;
            const duration = calculateDuration(leave.start_date, leave.end_date);

            content.innerHTML = `
                <div class="space-y-6">
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Leave Request Details</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Request ID: #${String(leave.id).padStart(4, '0')}</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-sm font-medium ${getStatusBadgeClass(leave.status)}">
                            ${getStatusText(leave.status)}
                        </span>
                    </div>

                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Request Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Teacher</label>
                                    <p class="text-gray-900 dark:text-white font-medium">${leave.teacher?.nama || 'Unknown Teacher'}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Reason</label>
                                    <p class="text-gray-900 dark:text-white">${getReasonText(leave.reason)}</p>
                                    ${leave.custom_reason ? `<p class="text-sm text-gray-600 dark:text-gray-400 mt-1">${leave.custom_reason}</p>` : ''}
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Duration</label>
                                    <p class="text-gray-900 dark:text-white">${duration} days</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">${formatDate(leave.start_date)} - ${formatDate(leave.end_date)}</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Assignment Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Substitute Teacher</label>
                                    <p class="text-gray-900 dark:text-white">${leave.substitute_teacher?.name || 'Not assigned'}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Submitted By</label>
                                    <p class="text-gray-900 dark:text-white">${leave.created_by_user?.name || 'N/A'}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">${leave.created_at ? formatDateTime(leave.created_at) : 'N/A'}</p>
                                </div>
                                ${leave.approved_at ? `
                                <div>
                                    <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Processed By</label>
                                    <p class="text-gray-900 dark:text-white">${leave.approved_by_user?.name || 'N/A'}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">${formatDateTime(leave.approved_at)}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>

                    <!-- Attachment -->
                    ${leave.attachment ? `
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Attachment</h4>
                        <div class="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <i class="fas fa-file text-blue-500 text-xl"></i>
                            <div class="flex-1">
                                <p class="text-gray-900 dark:text-white font-medium">Supporting Document</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Click to download</p>
                            </div>
                            <a href="{{ url('storage') }}/${leave.attachment}" target="_blank" class="btn btn-primary btn-sm">
                                <i class="fas fa-download mr-1"></i>Download
                            </a>
                        </div>
                    </div>
                    ` : ''}

                    <!-- Rejection Reason (if rejected) -->
                    ${leave.status === 'rejected' && leave.rejection_reason ? `
                    <div>
                        <h4 class="text-lg font-semibold text-red-600 dark:text-red-400 mb-4">Rejection Reason</h4>
                        <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                            <p class="text-red-800 dark:text-red-200">${leave.rejection_reason}</p>
                        </div>
                    </div>
                    ` : ''}

                    <!-- Notes (if any) -->
                    ${leave.notes ? `
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Additional Notes</h4>
                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <p class="text-gray-900 dark:text-white whitespace-pre-line">${leave.notes}</p>
                        </div>
                    </div>
                    ` : ''}

                    <!-- Action Buttons (for pending requests) -->
                    ${leave.status === 'pending' ? `
                    <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button onclick="approveLeave(${leave.id})" class="btn btn-success flex-1">
                            <i class="fas fa-check mr-2"></i>Approve Leave
                        </button>
                        <button onclick="rejectLeave(${leave.id})" class="btn btn-danger flex-1">
                            <i class="fas fa-times mr-2"></i>Reject Leave
                        </button>
                    </div>
                    ` : ''}
                </div>
            `;
        } else {
            content.innerHTML = `
                <div class="text-center py-8">
                    <div class="text-red-500 mb-4">
                        <i class="fas fa-exclamation-triangle text-4xl"></i>
                    </div>
                    <p class="text-slate-400 dark:text-slate-400 light:text-gray-500">Failed to load leave details</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading leave detail:', error);
        content.innerHTML = `
            <div class="text-center py-8">
                <div class="text-red-500 mb-4">
                    <i class="fas fa-exclamation-triangle text-4xl"></i>
                </div>
                <p class="text-slate-400 dark:text-slate-400 light:text-gray-500">Failed to load leave details</p>
            </div>
        `;
    });
}

// Approve leave
function approveLeave(id) {
    if (!confirm('Are you sure you want to approve this leave request?')) return;

    // Show loading state
    const button = event.target.closest('a') || event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    button.disabled = true;

    const url = `{{ route('teacher-leaves.approve', ':id') }}`.replace(':id', id);
    console.log('Approving leave:', url);

    fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({})
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            alert('Leave request approved successfully!');
            location.reload(); // Reload to show updated data
        } else {
            alert('Failed to approve leave request: ' + (data.message || 'Unknown error'));
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error approving leave:', error);
        alert('An error occurred while approving the leave request.');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Reject leave
function rejectLeave(id) {
    const reason = prompt('Please provide a reason for rejection:');
    if (!reason || !reason.trim()) {
        alert('Rejection reason is required.');
        return;
    }

    // Show loading state
    const button = event.target.closest('a') || event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    button.disabled = true;

    const url = `{{ route('teacher-leaves.reject', ':id') }}`.replace(':id', id);
    console.log('Rejecting leave:', url, 'Reason:', reason.trim());

    fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            rejection_reason: reason.trim()
        })
    })
    .then(response => {
        console.log('Reject response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Reject response data:', data);
        if (data.success) {
            alert('Leave request rejected successfully!');
            location.reload(); // Reload to show updated data
        } else {
            alert('Failed to reject leave request: ' + (data.message || 'Unknown error'));
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error rejecting leave:', error);
        alert('An error occurred while rejecting the leave request.');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Helper functions
function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400 dark:border-yellow-800',
        'approved': 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800',
        'rejected': 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800'
    };

    return classes[status] || 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/20 dark:text-gray-400 dark:border-gray-800';
}

function getStatusText(status) {
    const texts = {
        'pending': 'Pending Approval',
        'approved': 'Approved',
        'rejected': 'Rejected'
    };

    return texts[status] || status;
}

function getReasonText(reason) {
    const reasons = {
        'sakit': 'Sakit',
        'cuti_tahunan': 'Cuti Tahunan',
        'urusan_keluarga': 'Urusan Keluarga',
        'acara_resmi': 'Acara Resmi',
        'lainnya': 'Lainnya'
    };

    return reasons[reason] || reason;
}

function calculateDuration(startDate, endDate) {
    const start = new Date(startDate);
    const end = new Date(endDate);
    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    return diffDays;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
</script>
@endsection
