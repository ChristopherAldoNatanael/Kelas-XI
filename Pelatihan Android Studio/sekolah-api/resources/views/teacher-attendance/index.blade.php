@extends('layouts.app')

@section('title', 'Teacher Attendance Management')

@section('content')
<div class="min-h-screen">
    <!-- Professional Header Section -->
    <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700 rounded-2xl p-8 mb-8 text-white shadow-2xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">Teacher Attendance</h1>
                        <p class="text-blue-100">Monitor and manage teacher attendance records</p>
                    </div>
                </div>

                <!-- Key Statistics -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold mb-1" id="total-records">0</div>
                        <div class="text-sm text-blue-100">Total Records</div>
                    </div>
                    <div class="bg-green-500/20 backdrop-blur-sm rounded-lg p-4 text-center border border-green-400/30">
                        <div class="text-2xl font-bold mb-1" id="present-count">0</div>
                        <div class="text-sm text-green-100">Present</div>
                    </div>
                    <div class="bg-red-500/20 backdrop-blur-sm rounded-lg p-4 text-center border border-red-400/30">
                        <div class="text-2xl font-bold mb-1" id="absent-count">0</div>
                        <div class="text-sm text-red-100">Absent</div>
                    </div>
                    <div class="bg-yellow-500/20 backdrop-blur-sm rounded-lg p-4 text-center border border-yellow-400/30">
                        <div class="text-2xl font-bold mb-1" id="leave-count">0</div>
                        <div class="text-sm text-yellow-100">On Leave</div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <a href="{{ route('web-teachers.index') }}" class="inline-flex items-center gap-3 px-6 py-3 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-lg border border-white/20 transition-all duration-200 group">
                    <i class="fas fa-users text-xl"></i>
                    <span class="font-medium">Manage Teachers</span>
                    <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                </a>

                <a href="{{ route('teacher-leaves.index') }}" class="inline-flex items-center gap-3 px-6 py-3 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-lg border border-white/20 transition-all duration-200 group">
                    <i class="fas fa-calendar-times text-xl"></i>
                    <span class="font-medium">Leave Management</span>
                    <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Attendance Records with Glass Morphism -->
    <div class="px-6 space-y-6">
        <!-- Search and Filters Header -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div>
                        <h3 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">Attendance Records</h3>
                        <p style="color: var(--text-secondary);">Browse and manage all teacher attendance records</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search attendance records..."
                                   class="glass-search-input">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);"></i>
                        </div>

                        <div class="flex gap-2">
                            <button class="glass-filter-btn active" data-filter="all">
                                <i class="fas fa-list"></i>
                                All
                            </button>
                            <button class="glass-filter-btn" data-filter="present">
                                <i class="fas fa-check-circle"></i>
                                Present
                            </button>
                            <button class="glass-filter-btn" data-filter="absent">
                                <i class="fas fa-times-circle"></i>
                                Absent
                            </button>
                            <button class="glass-filter-btn" data-filter="leave">
                                <i class="fas fa-user-friends"></i>
                                On Leave
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Filters Bar -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Date Range</label>
                        <div class="flex gap-2">
                            <input type="date" id="date_from" class="glass-search-input flex-1" placeholder="From date">
                            <span class="self-center" style="color: var(--text-secondary);">to</span>
                            <input type="date" id="date_to" class="glass-search-input flex-1" placeholder="To date">
                        </div>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Teacher</label>
                        <select id="teacher_id" class="glass-search-input">
                            <option value="">All Teachers</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2 self-end">
                        <button id="apply-filters" class="glass-action-btn glass-action-primary">
                            <i class="fas fa-search mr-2"></i>
                            Apply
                        </button>
                        <button id="export-pdf" class="glass-action-btn">
                            <i class="fas fa-file-pdf mr-2"></i>
                            PDF
                        </button>
                        <button id="export-excel" class="glass-action-btn">
                            <i class="fas fa-file-excel mr-2"></i>
                            Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Professional Attendance Records Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="attendanceGrid">
            <!-- Loading skeleton cards - optimized for performance -->
            @for($i = 0; $i < 6; $i++)
                <div class="glass-morphism-card p-6 animate-pulse">
                    <div class="flex items-start gap-4 mb-4">
                        <div class="w-12 h-12 bg-slate-200 rounded-full"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                            <div class="h-3 bg-slate-200 rounded w-1/2"></div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="h-6 bg-slate-200 rounded w-20"></div>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <div class="h-3 bg-slate-200 rounded w-16"></div>
                                <div class="h-3 bg-slate-200 rounded w-20"></div>
                            </div>
                            <div class="flex justify-between">
                                <div class="h-3 bg-slate-200 rounded w-12"></div>
                                <div class="h-3 bg-slate-200 rounded w-24"></div>
                            </div>
                        </div>
                    </div>
                </div>
            @endfor
        </div>

        <!-- Modern Pagination -->
        <div class="glass-morphism-card" id="paginationContainer" style="display: none;">
            <div class="p-6">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-2" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle text-blue-400"></i>
                        <span>Showing <span class="font-semibold" style="color: var(--text-primary);" id="showingCount">0</span> to
                        <span class="font-semibold" style="color: var(--text-primary);" id="showingTo">0</span> of
                        <span class="font-semibold" style="color: var(--text-primary);" id="totalCount">0</span> records</span>
                    </div>
                    <div class="pagination-controls" id="paginationLinks">
                        <!-- Pagination links will be inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Detail Modal -->
<div id="attendance-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Attendance Details</h3>
                    <button id="close-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div id="modal-content" class="p-6">
                <!-- Modal content will be loaded here -->
            </div>
        </div>
    </div>
</div>

@endsection

@section('styles')
<style>
/* Glass Morphism Components */
.glass-morphism-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    position: relative;
    overflow: visible;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.glass-morphism-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
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

.glass-action-btn {
    padding: 0.5rem 1rem;
    height: auto;
    width: auto;
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
    border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-decoration: none;
    cursor: pointer;
}

.glass-action-btn:hover:not(:disabled) {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.4);
    color: #f87171;
}

.glass-action-primary {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

.glass-action-primary:hover:not(:disabled) {
    background: rgba(59, 130, 246, 0.2);
    border-color: rgba(59, 130, 246, 0.4);
    color: #60a5fa;
}

/* Attendance Card Styles */
.attendance-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    display: flex;
    flex-direction: column;
}

.attendance-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    border-color: rgba(255, 255, 255, 0.15);
}

.card-header {
    padding: 1.5rem;
    padding-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.avatar-section {
    flex-shrink: 0;
}

.avatar-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.8) 0%, rgba(37, 99, 235, 0.9) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid var(--card-bg);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

.avatar-letter {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.info-section {
    flex: 1;
    min-width: 0;
}

.teacher-name {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.subject-info {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.card-body {
    padding: 1.5rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.status-section {
    display: flex;
    justify-content: center;
}

.details-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.detail-item i {
    width: 16px;
    text-align: center;
}

.actions-section {
    margin-top: auto;
    display: flex;
    justify-content: center;
}

.action-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #3b82f6;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
}

.action-btn:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: rgba(59, 130, 246, 0.4);
    color: #60a5fa;
    transform: translateY(-1px);
}

/* Skeleton Loading */
.attendance-card-skeleton {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.card-header-skeleton {
    padding: 1.5rem;
    padding-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.avatar-skeleton {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    animation: pulse 2s infinite;
}

.info-skeleton {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.name-skeleton {
    height: 1.25rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 0.25rem;
    animation: pulse 2s infinite;
    animation-delay: 0.1s;
}

.subject-skeleton {
    height: 1rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 0.25rem;
    animation: pulse 2s infinite;
    animation-delay: 0.2s;
    width: 70%;
}

.card-body-skeleton {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.status-skeleton {
    height: 2rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    animation: pulse 2s infinite;
    animation-delay: 0.3s;
    width: 120px;
    margin: 0 auto;
}

.details-skeleton {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-skeleton {
    height: 1.25rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 0.25rem;
    animation: pulse 2s infinite;
    animation-delay: 0.4s;
}

/* Status Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid;
    width: fit-content;
}

.badge-success {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.badge-warning {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
    color: #f59e0b;
}

.badge-danger {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.badge-info {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

.badge-secondary {
    background: rgba(148, 163, 184, 0.1);
    border-color: rgba(148, 163, 184, 0.3);
    color: #94a3b8;
}

/* Animations */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .glass-morphism-card {
        margin: 0 1rem;
    }

    .attendance-card,
    .attendance-card-skeleton {
        margin: 0 0.5rem;
    }

    .avatar-circle {
        width: 50px;
        height: 50px;
    }

    .avatar-letter {
        font-size: 1.25rem;
    }

    .teacher-name {
        font-size: 1rem;
    }

    .subject-info {
        font-size: 0.8rem;
    }

    .action-btn {
        padding: 0.625rem 1.25rem;
        font-size: 0.8rem;
    }
}
</style>
@endsection

@section('scripts')
<script>
// Global variables - OPTIMIZED for card design
let currentPage = 1;
let currentFilters = {
    date_from: '', // Start with no date filtering to show all records
    date_to: '',
    teacher_id: '',
    status: 'all',
    search: '',
    per_page: 12
};

let autoRefreshInterval = null;
let isLoading = false;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    loadAttendanceData();
    setupEventListeners();
    startOptimizedAutoRefresh();
});

function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const filter = this.value.toLowerCase().trim();

            searchTimeout = setTimeout(() => {
                currentFilters.search = filter;
                currentPage = 1;
                loadAttendanceData();
            }, 500);
        });
    }

    // Filter buttons
    document.querySelectorAll('.glass-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.glass-filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilters.status = this.dataset.filter;
            currentPage = 1;
            loadAttendanceData();
        });
    });

    // Apply filters
    document.getElementById('apply-filters').addEventListener('click', function() {
        updateFiltersFromForm();
        currentPage = 1;
        loadAttendanceData();
    });

    // Export buttons
    document.getElementById('export-pdf').addEventListener('click', function() {
        exportData('pdf');
    });

    document.getElementById('export-excel').addEventListener('click', function() {
        exportData('excel');
    });

    // Modal close
    const closeModalBtn = document.getElementById('close-modal');
    const modal = document.getElementById('attendance-modal');

    if (closeModalBtn && modal) {
        closeModalBtn.addEventListener('click', function() {
            modal.classList.add('hidden');
        });

        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    }

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal) {
            modal.classList.add('hidden');
        }
    });
}

function updateFiltersFromForm() {
    currentFilters.date_from = document.getElementById('date_from').value;
    currentFilters.date_to = document.getElementById('date_to').value;
    currentFilters.teacher_id = document.getElementById('teacher_id').value;
}

function updateStatistics(stats) {
    // Update header statistics
    const totalRecordsEl = document.getElementById('total-records');
    const presentCountEl = document.getElementById('present-count');
    const absentCountEl = document.getElementById('absent-count');
    const leaveCountEl = document.getElementById('leave-count');

    if (totalRecordsEl) totalRecordsEl.textContent = stats.total || 0;
    if (presentCountEl) presentCountEl.textContent = stats.present || 0;
    if (absentCountEl) absentCountEl.textContent = stats.absent || 0;
    if (leaveCountEl) leaveCountEl.textContent = stats.on_leave || 0;
}

function loadAttendanceData() {
    if (isLoading) return;
    isLoading = true;

    // Show skeleton loading
    showGridSkeleton();

    const params = new URLSearchParams({
        ...currentFilters,
        page: currentPage
    });

    fetch(`{{ route('teacher-attendance.data') }}?${params}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateStatistics(data.stats);
            renderAttendanceGrid(data.data);
            renderPagination(data.data);
        } else {
            showError('Failed to load attendance data');
        }
    })
    .catch(error => {
        console.error('Error loading attendance data:', error);
        showError('Network error occurred');
    })
    .finally(() => {
        isLoading = false;
    });
}

function showGridSkeleton() {
    const grid = document.getElementById('attendanceGrid');
    const skeletonCards = grid.querySelectorAll('.attendance-card-skeleton');
    skeletonCards.forEach(card => card.style.display = 'block');

    // Hide real cards
    const realCards = grid.querySelectorAll('.attendance-card:not(.attendance-card-skeleton)');
    realCards.forEach(card => card.style.display = 'none');
}

function hideGridSkeleton() {
    const skeletonCards = document.querySelectorAll('.attendance-card-skeleton');
    skeletonCards.forEach(card => card.style.display = 'none');
}

function startOptimizedAutoRefresh() {
    // Auto-refresh every 15 minutes instead of 5, and only if page is visible
    autoRefreshInterval = setInterval(() => {
        if (!document.hidden && !isLoading) {
            loadAttendanceData();
        }
    }, 15 * 60 * 1000); // 15 minutes

    // Pause auto-refresh when page is not visible
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        } else if (!autoRefreshInterval) {
            startOptimizedAutoRefresh();
        }
    });
}

function updateStatistics(stats) {
    // Update header statistics - simplified for current HTML structure
    const totalRecordsEl = document.getElementById('total-records');
    const presentCountEl = document.getElementById('present-count');
    const absentCountEl = document.getElementById('absent-count');
    const leaveCountEl = document.getElementById('leave-count');

    if (totalRecordsEl) totalRecordsEl.textContent = stats.total || 0;
    if (presentCountEl) presentCountEl.textContent = stats.present || 0;
    if (absentCountEl) absentCountEl.textContent = stats.absent || 0;
    if (leaveCountEl) leaveCountEl.textContent = stats.on_leave || 0;
}

function renderAttendanceGrid(data) {
    const grid = document.getElementById('attendanceGrid');

    // Hide skeleton loading
    hideGridSkeleton();

    if (!data.data || data.data.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full">
                <div class="glass-morphism-card">
                    <div class="p-12 text-center">
                        <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center">
                            <i class="fas fa-calendar-times text-4xl text-slate-500"></i>
                        </div>
                        <h3 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">No Attendance Records</h3>
                        <p class="mb-8 max-w-md mx-auto" style="color: var(--text-secondary);">No attendance records found for the selected filters. Try adjusting your search criteria or clearing filters.</p>
                        <button onclick="clearFilters()" class="glass-action-btn glass-action-primary">
                            <i class="fas fa-filter mr-2"></i>
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>
        `;
        return;
    }

    // Use document fragment for better performance
    const fragment = document.createDocumentFragment();

    data.data.forEach(record => {
        const card = document.createElement('div');
        card.className = 'glass-morphism-card p-6 hover:scale-[1.02] transition-all duration-300 cursor-pointer group';

        // Handle data safely with fallbacks
        const teacherName = record.guru?.name || 'Unknown Teacher';
        const subjectName = record.schedule?.mata_pelajaran || 'Subject N/A';
        const className = record.schedule?.kelas || 'Class N/A';
        const formattedDate = formatDateSafely(record.tanggal);
        const checkInTime = formatCheckInTime(record.jam_masuk);
        const statusBadge = getStatusBadge(record.status);
        const substituteInfo = record.guru_asli ? `<div class="mt-3 p-2 rounded-lg bg-blue-50 border border-blue-200"><span class="text-xs font-medium text-blue-700">Substitute for ${record.guru_asli.name}</span></div>` : '';

        card.onclick = () => showAttendanceDetail(record.id);

        card.innerHTML = `
            <div class="flex items-start gap-4 mb-4">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-lg shadow-lg">
                    ${getInitials(teacherName)}
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-semibold text-lg truncate group-hover:text-blue-600 transition-colors" style="color: var(--text-primary);">
                        ${teacherName}
                    </h4>
                    <p class="text-sm truncate" style="color: var(--text-secondary);">
                        ${subjectName} • ${className}
                    </p>
                </div>
                <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                    <i class="fas fa-eye text-blue-500"></i>
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50/50">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Status</span>
                    ${statusBadge}
                </div>

                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50/50">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Date</span>
                    <span class="text-sm font-mono font-medium" style="color: var(--text-primary);">${formattedDate}</span>
                </div>

                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50/50">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Check-in Time</span>
                    <span class="text-sm font-mono font-medium" style="color: var(--text-primary);">${checkInTime}</span>
                </div>

                ${substituteInfo}
            </div>
        `;

        fragment.appendChild(card);
    });

    // Clear existing content and append new cards
    grid.innerHTML = '';
    grid.appendChild(fragment);
}

function renderPagination(data) {
    const container = document.getElementById('paginationContainer');
    const showingCount = document.getElementById('showingCount');
    const showingTo = document.getElementById('showingTo');
    const totalCount = document.getElementById('totalCount');
    const links = document.getElementById('paginationLinks');

    if (!container || !showingCount || !showingTo || !totalCount || !links) {
        console.warn('Pagination elements not found');
        return;
    }

    // Update info
    const from = (data.current_page - 1) * data.per_page + 1;
    const to = Math.min(data.current_page * data.per_page, data.total);

    showingCount.textContent = from;
    showingTo.textContent = to;
    totalCount.textContent = data.total;

    // Show/hide pagination container
    if (data.last_page > 1) {
        container.style.display = 'block';

        // Clear existing links
        links.innerHTML = '';

        // Previous button
        if (data.current_page > 1) {
            const prevBtn = document.createElement('button');
            prevBtn.className = 'glass-action-btn';
            prevBtn.innerHTML = '<i class="fas fa-chevron-left mr-2"></i>Previous';
            prevBtn.onclick = () => changePage(data.current_page - 1);
            links.appendChild(prevBtn);
        }

        // Page numbers (simplified)
        const startPage = Math.max(1, data.current_page - 2);
        const endPage = Math.min(data.last_page, data.current_page + 2);

        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `glass-action-btn ${i === data.current_page ? 'glass-action-primary' : ''}`;
            pageBtn.textContent = i;
            pageBtn.onclick = () => changePage(i);
            links.appendChild(pageBtn);
        }

        // Next button
        if (data.current_page < data.last_page) {
            const nextBtn = document.createElement('button');
            nextBtn.className = 'glass-action-btn';
            nextBtn.innerHTML = 'Next<i class="fas fa-chevron-right ml-2"></i>';
            nextBtn.onclick = () => changePage(data.current_page + 1);
            links.appendChild(nextBtn);
        }
    } else {
        container.style.display = 'none';
    }
}

function changePage(page) {
    currentPage = page;
    loadAttendanceData();
}

function getStatusBadge(status) {
    const badges = {
        'hadir': '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Present</span>',
        'telat': '<span class="badge badge-warning"><i class="fas fa-clock mr-1"></i>Late</span>',
        'tidak_hadir': '<span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i>Absent</span>',
        'diganti': '<span class="badge badge-info"><i class="fas fa-user-friends mr-1"></i>Substitute</span>'
    };

    return badges[status] || `<span class="badge badge-secondary">${status}</span>`;
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatTime(time) {
    return new Date(time).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Safe date formatting with fallback for invalid dates
function formatDateSafely(date) {
    try {
        if (!date) return '<span class="text-slate-400 italic">Invalid Date</span>';

        const dateObj = new Date(date);
        if (isNaN(dateObj.getTime())) {
            return '<span class="text-slate-400 italic">Invalid Date</span>';
        }

        return dateObj.toLocaleDateString('id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (error) {
        console.warn('Error formatting date:', date, error);
        return '<span class="text-slate-400 italic">Invalid Date</span>';
    }
}

// Professional check-in time formatting with "Not Recorded" handling
function formatCheckInTime(time) {
    try {
        if (!time || time === null || time === '') {
            return '<span class="text-slate-400 italic font-medium">Not Recorded</span>';
        }

        const timeObj = new Date(time);
        if (isNaN(timeObj.getTime())) {
            return '<span class="text-slate-400 italic font-medium">Not Recorded</span>';
        }

        return timeObj.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    } catch (error) {
        console.warn('Error formatting check-in time:', time, error);
        return '<span class="text-slate-400 italic font-medium">Not Recorded</span>';
    }
}

// Safe initials extraction
function getInitials(name) {
    try {
        if (!name || typeof name !== 'string') return 'U';

        const parts = name.trim().split(' ').filter(part => part.length > 0);
        if (parts.length === 0) return 'U';

        if (parts.length === 1) {
            return parts[0].charAt(0).toUpperCase();
        }

        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    } catch (error) {
        console.warn('Error getting initials for name:', name, error);
        return 'U';
    }
}

function showAttendanceDetail(id) {
    const modal = document.getElementById('attendance-modal');
    const content = document.getElementById('modal-content');

    content.innerHTML = `
        <div class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            <span class="ml-3 text-slate-400 dark:text-slate-400 light:text-gray-500">Loading details...</span>
        </div>
    `;

    modal.classList.remove('hidden');

    fetch(`{{ route('teacher-attendance.show', ':id') }}`.replace(':id', id), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        }
    })
    .then(response => response.text())
    .then(html => {
        content.innerHTML = html;
    })
    .catch(error => {
        console.error('Error loading attendance detail:', error);
        content.innerHTML = `
            <div class="text-center py-8">
                <div class="text-red-500 mb-4">
                    <i class="fas fa-exclamation-triangle text-4xl"></i>
                </div>
                <p class="text-slate-400 dark:text-slate-400 light:text-gray-500">Failed to load attendance details</p>
            </div>
        `;
    });
}

function exportData(type) {
    const params = new URLSearchParams(currentFilters);
    let url;

    if (type === 'pdf') {
        url = `{{ route('teacher-attendance.export.pdf') }}?${params}`;
    } else {
        url = `{{ route('teacher-attendance.export.excel') }}?${params}`;
    }

    // Create a temporary link and click it
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function clearFilters() {
    // Reset all filter inputs
    document.getElementById('date_from').value = '';
    document.getElementById('date_to').value = '';
    document.getElementById('teacher_id').value = '';
    document.getElementById('searchInput').value = '';

    // Reset filter buttons
    document.querySelectorAll('.glass-filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector('.glass-filter-btn[data-filter="all"]').classList.add('active');

    // Reset current filters
    currentFilters = {
        date_from: '',
        date_to: '',
        teacher_id: '',
        status: 'all',
        search: '',
        per_page: 12
    };

    currentPage = 1;
    loadAttendanceData();
}

function showError(message) {
    // Display error in the attendance grid
    const grid = document.getElementById('attendanceGrid');
    if (grid) {
        grid.innerHTML = `
            <div class="col-span-full">
                <div class="glass-morphism-card">
                    <div class="p-12 text-center">
                        <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-red-500/20 flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-4xl text-red-400"></i>
                        </div>
                        <h3 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">Error Loading Data</h3>
                        <p class="mb-8 max-w-md mx-auto" style="color: var(--text-secondary);">${message}</p>
                        <button onclick="loadAttendanceData()" class="glass-action-btn glass-action-primary">
                            <i class="fas fa-redo mr-2"></i>
                            Try Again
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
}

// Auto-refresh is now handled by startOptimizedAutoRefresh() function
</script>
@endsection
