@extends('layouts.app')

@section('title', 'Teacher Leave Management')

@section('page-header')
<div class="bg-gradient-to-r from-purple-600 to-pink-600 dark:from-purple-800 dark:to-pink-800 rounded-2xl p-6 mb-6 text-white">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold mb-2">Teacher Leave Management</h1>
            <p class="text-purple-100">Manage teacher leave requests, approvals, and substitute assignments</p>
        </div>
        <div class="hidden md:flex items-center gap-3">
            <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2">
                <i class="fas fa-calendar-alt text-yellow-300"></i>
                <span class="text-sm font-medium">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>
    </div>
</div>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="adaptive-card group">
            <div class="relative p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-yellow-500/20 to-orange-600/20 dark:from-yellow-500/20 dark:to-orange-600/20 light:from-yellow-100 light:to-orange-200 backdrop-blur-sm border border-yellow-400/20 dark:border-yellow-400/20 light:border-yellow-300/50">
                        <i class="fas fa-clock text-yellow-300 dark:text-yellow-300 light:text-yellow-600 text-xl"></i>
                    </div>
                </div>
                <div class="space-y-1">
                    <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900" id="pending-count">0</p>
                    <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">Pending</p>
                    <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">Awaiting approval</p>
                </div>
            </div>
        </div>

        <div class="adaptive-card group">
            <div class="relative p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-green-500/20 to-green-600/20 dark:from-green-500/20 dark:to-green-600/20 light:from-green-100 light:to-green-200 backdrop-blur-sm border border-green-400/20 dark:border-green-400/20 light:border-green-300/50">
                        <i class="fas fa-check-circle text-green-300 dark:text-green-300 light:text-green-600 text-xl"></i>
                    </div>
                </div>
                <div class="space-y-1">
                    <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900" id="approved-count">0</p>
                    <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">Approved</p>
                    <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">Leave granted</p>
                </div>
            </div>
        </div>

        <div class="adaptive-card group">
            <div class="relative p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-red-500/20 to-red-600/20 dark:from-red-500/20 dark:to-red-600/20 light:from-red-100 light:to-red-200 backdrop-blur-sm border border-red-400/20 dark:border-red-400/20 light:border-red-300/50">
                        <i class="fas fa-times-circle text-red-300 dark:text-red-300 light:text-red-600 text-xl"></i>
                    </div>
                </div>
                <div class="space-y-1">
                    <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900" id="rejected-count">0</p>
                    <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">Rejected</p>
                    <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">Leave denied</p>
                </div>
            </div>
        </div>

        <div class="adaptive-card group">
            <div class="relative p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-600/20 dark:from-blue-500/20 dark:to-indigo-600/20 light:from-blue-100 light:to-indigo-200 backdrop-blur-sm border border-blue-400/20 dark:border-blue-400/20 light:border-blue-300/50">
                        <i class="fas fa-list text-blue-300 dark:text-blue-300 light:text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div class="space-y-1">
                    <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900" id="total-count">0</p>
                    <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">Total</p>
                    <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">All requests</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions and Filters -->
    <div class="adaptive-card-section">
        <div class="p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-xl font-bold text-white dark:text-white light:text-gray-900 mb-2">Leave Requests</h3>
                    <p class="text-slate-300 dark:text-slate-300 light:text-gray-600">Review and manage teacher leave applications</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('teacher-leaves.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i>Add Leave Request
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="status-filter" class="form-control">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Teacher</label>
                    <select id="teacher-filter" class="form-control">
                        <option value="">All Teachers</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                {{ $teacher->name }} {{ $teacher->nama ? "({$teacher->nama})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Search</label>
                    <input type="text" id="search-filter" placeholder="Search by teacher or reason..." class="form-control" value="{{ request('search') }}">
                </div>
            </div>

            <!-- Status Filter Buttons -->
            <div class="flex flex-wrap gap-2 mb-6">
                <button class="status-filter-btn active" data-status="">
                    <i class="fas fa-list mr-2"></i>All Requests
                </button>
                <button class="status-filter-btn" data-status="pending">
                    <i class="fas fa-clock mr-2"></i>Pending
                </button>
                <button class="status-filter-btn" data-status="approved">
                    <i class="fas fa-check-circle mr-2"></i>Approved
                </button>
                <button class="status-filter-btn" data-status="rejected">
                    <i class="fas fa-times-circle mr-2"></i>Rejected
                </button>
            </div>

            <!-- Apply Filters -->
            <div class="flex gap-2 mb-6">
                <button id="apply-filters" class="btn btn-primary">
                    <i class="fas fa-search mr-2"></i>Apply Filters
                </button>
                <button id="clear-filters" class="btn btn-secondary">
                    <i class="fas fa-times mr-2"></i>Clear Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Leave Requests Table -->
    <div class="adaptive-card-section">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-semibold text-white dark:text-white light:text-gray-900">Leave Requests</h4>
                <div class="text-sm text-slate-400 dark:text-slate-400 light:text-gray-500">
                    Showing <span id="showing-count">0</span> of <span id="total-count">0</span> requests
                </div>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Reason</th>
                            <th>Duration</th>
                            <th>Substitute</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="leave-table-body">
                        <tr>
                            <td colspan="7" class="text-center py-8">
                                <div class="flex items-center justify-center">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                                    <span class="ml-3 text-slate-400 dark:text-slate-400 light:text-gray-500">Loading leave requests...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="pagination-container" class="mt-6 flex items-center justify-between">
                <div class="text-sm text-slate-400 dark:text-slate-400 light:text-gray-500">
                    <span id="pagination-info">No requests found</span>
                </div>
                <div id="pagination-links" class="flex gap-2">
                    <!-- Pagination links will be inserted here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leave Detail Modal -->
<div id="leave-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Leave Request Details</h3>
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

@section('scripts')
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
    loadLeaveData();
    setupEventListeners();
});

function setupEventListeners() {
    // Status filter buttons
    document.querySelectorAll('.status-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.status-filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilters.status = this.dataset.status;
            currentPage = 1;
            loadLeaveData();
        });
    });

    // Apply filters
    document.getElementById('apply-filters').addEventListener('click', function() {
        updateFiltersFromForm();
        currentPage = 1;
        loadLeaveData();
    });

    // Clear filters
    document.getElementById('clear-filters').addEventListener('click', function() {
        clearFilters();
        loadLeaveData();
    });

    // Search on enter
    document.getElementById('search-filter').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            updateFiltersFromForm();
            currentPage = 1;
            loadLeaveData();
        }
    });

    // Modal close
    document.getElementById('close-modal').addEventListener('click', function() {
        document.getElementById('leave-modal').classList.add('hidden');
    });

    // Close modal on outside click
    document.getElementById('leave-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
}

function updateFiltersFromForm() {
    currentFilters.status = document.getElementById('status-filter').value;
    currentFilters.teacher_id = document.getElementById('teacher-filter').value;
    currentFilters.search = document.getElementById('search-filter').value;
}

function clearFilters() {
    document.getElementById('status-filter').value = '';
    document.getElementById('teacher-filter').value = '';
    document.getElementById('search-filter').value = '';

    document.querySelectorAll('.status-filter-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.status-filter-btn[data-status=""]').classList.add('active');

    currentFilters = {
        status: '',
        teacher_id: '',
        search: '',
        per_page: 15
    };
    currentPage = 1;
}

function loadLeaveData() {
    const loadingRow = `
        <tr>
            <td colspan="7" class="text-center py-8">
                <div class="flex items-center justify-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    <span class="ml-3 text-slate-400 dark:text-slate-400 light:text-gray-500">Loading leave requests...</span>
                </div>
            </td>
        </tr>
    `;

    document.getElementById('leave-table-body').innerHTML = loadingRow;

    const params = new URLSearchParams({
        ...currentFilters,
        page: currentPage
    });

    fetch(`{{ route('teacher-leaves.data') }}?${params}`, {
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
            renderLeaveTable(data.data);
            renderPagination(data.data);
        } else {
            showError('Failed to load leave data');
        }
    })
    .catch(error => {
        console.error('Error loading leave data:', error);
        showError('Network error occurred');
    });
}

function updateStatistics(stats) {
    document.getElementById('pending-count').textContent = stats.pending || 0;
    document.getElementById('approved-count').textContent = stats.approved || 0;
    document.getElementById('rejected-count').textContent = stats.rejected || 0;
    document.getElementById('total-count').textContent = stats.total || 0;
}

function renderLeaveTable(data) {
    const tbody = document.getElementById('leave-table-body');

    if (!data.data || data.data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-8">
                    <div class="text-slate-400 dark:text-slate-400 light:text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-4 block"></i>
                        <p>No leave requests found</p>
                    </div>
                </td>
            </td>
        `;
        return;
    }

    const rows = data.data.map(request => {
        const statusBadge = getStatusBadge(request.status);
        const duration = calculateDuration(request.start_date, request.end_date);
        const substituteInfo = request.substitute_teacher ?
            `<div class="text-xs text-blue-400">${request.substitute_teacher.name}</div>` : '-';

        return `
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 light:hover:bg-gray-50 transition-colors">
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 light:bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-user text-blue-600 dark:text-blue-400 light:text-blue-600 text-xs"></i>
                        </div>
                        <div>
                            <div class="font-medium text-white dark:text-white light:text-gray-900">${request.teacher.name}</div>
                            <div class="text-xs text-slate-400 dark:text-slate-400 light:text-gray-500">${request.teacher.nama || ''}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="font-medium">${getReasonText(request.reason)}</div>
                    ${request.custom_reason ? `<div class="text-xs text-slate-400 dark:text-slate-400 light:text-gray-500">${request.custom_reason}</div>` : ''}
                </td>
                <td>
                    <div class="font-medium">${duration} days</div>
                    <div class="text-xs text-slate-400 dark:text-slate-400 light:text-gray-500">
                        ${formatDate(request.start_date)} - ${formatDate(request.end_date)}
                    </div>
                </td>
                <td>${substituteInfo}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="text-sm">${formatDate(request.created_at)}</div>
                    <div class="text-xs text-slate-400 dark:text-slate-400 light:text-gray-500">
                        ${formatTime(request.created_at)}
                    </div>
                </td>
                <td>
                    <div class="flex gap-2">
                        <button onclick="showLeaveDetail(${request.id})" class="btn btn-sm btn-secondary" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${request.status === 'pending' ? `
                            <button onclick="approveLeave(${request.id})" class="btn btn-sm btn-success" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                            <button onclick="rejectLeave(${request.id})" class="btn btn-sm btn-danger" title="Reject">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : ''}
                        ${request.status === 'pending' ? `
                            <button onclick="editLeave(${request.id})" class="btn btn-sm btn-secondary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteLeave(${request.id})" class="btn btn-sm btn-danger" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    tbody.innerHTML = rows;
}

function renderPagination(data) {
    const container = document.getElementById('pagination-container');
    const info = document.getElementById('pagination-info');
    const links = document.getElementById('pagination-links');

    // Update info
    const from = (data.current_page - 1) * data.per_page + 1;
    const to = Math.min(data.current_page * data.per_page, data.total);
    info.textContent = `Showing ${from} to ${to} of ${data.total} entries`;

    // Simple pagination (you can enhance this)
    links.innerHTML = '';

    if (data.last_page > 1) {
        // Previous button
        if (data.current_page > 1) {
            const prevBtn = document.createElement('button');
            prevBtn.className = 'btn btn-sm btn-secondary';
            prevBtn.innerHTML = '<i class="fas fa-chevron-left mr-1"></i>Previous';
            prevBtn.onclick = () => changePage(data.current_page - 1);
            links.appendChild(prevBtn);
        }

        // Page numbers (simplified)
        const startPage = Math.max(1, data.current_page - 2);
        const endPage = Math.min(data.last_page, data.current_page + 2);

        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `btn btn-sm ${i === data.current_page ? 'btn-primary' : 'btn-secondary'}`;
            pageBtn.textContent = i;
            pageBtn.onclick = () => changePage(i);
            links.appendChild(pageBtn);
        }

        // Next button
        if (data.current_page < data.last_page) {
            const nextBtn = document.createElement('button');
            nextBtn.className = 'btn btn-sm btn-secondary';
            nextBtn.innerHTML = 'Next<i class="fas fa-chevron-right ml-1"></i>';
            nextBtn.onclick = () => changePage(data.current_page + 1);
            links.appendChild(nextBtn);
        }
    }
}

function changePage(page) {
    currentPage = page;
    loadLeaveData();
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge badge-warning"><i class="fas fa-clock mr-1"></i>Pending</span>',
        'approved': '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Approved</span>',
        'rejected': '<span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i>Rejected</span>'
    };

    return badges[status] || `<span class="badge badge-secondary">${status}</span>`;
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

function formatDate(date) {
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatTime(datetime) {
    return new Date(datetime).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showLeaveDetail(id) {
    const modal = document.getElementById('leave-modal');
    const content = document.getElementById('modal-content');

    content.innerHTML = `
        <div class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            <span class="ml-3 text-slate-400 dark:text-slate-400 light:text-gray-500">Loading details...</span>
        </div>
    `;

    modal.classList.remove('hidden');

    fetch(`{{ route('teacher-leaves.show', ':id') }}`.replace(':id', id), {
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

function approveLeave(id) {
    if (!confirm('Are you sure you want to approve this leave request?')) return;

    fetch(`{{ route('teacher-leaves.approve', ':id') }}`.replace(':id', id), {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            loadLeaveData();
        } else {
            showError(data.message || 'Failed to approve leave');
        }
    })
    .catch(error => {
        console.error('Error approving leave:', error);
        showError('Network error occurred');
    });
}

function rejectLeave(id) {
    const reason = prompt('Please provide a reason for rejection:');
    if (!reason) return;

    const formData = new FormData();
    formData.append('rejection_reason', reason);

    fetch(`{{ route('teacher-leaves.reject', ':id') }}`.replace(':id', id), {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            loadLeaveData();
        } else {
            showError(data.message || 'Failed to reject leave');
        }
    })
    .catch(error => {
        console.error('Error rejecting leave:', error);
        showError('Network error occurred');
    });
}

function editLeave(id) {
    window.location.href = `{{ route('teacher-leaves.edit', ':id') }}`.replace(':id', id);
}

function deleteLeave(id) {
    if (!confirm('Are you sure you want to delete this leave request? This action cannot be undone.')) return;

    fetch(`{{ route('teacher-leaves.destroy', ':id') }}`.replace(':id', id), {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            loadLeaveData();
        } else {
            showError(data.message || 'Failed to delete leave');
        }
    })
    .catch(error => {
        console.error('Error deleting leave:', error);
        showError('Network error occurred');
    });
}

function showSuccess(message) {
    // Simple success display - you can enhance this
    const alert = document.createElement('div');
    alert.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
    alert.innerHTML = `<i class="fas fa-check-circle mr-2"></i>${message}`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 3000);
}

function showError(message) {
    // Simple error display - you can enhance this
    const alert = document.createElement('div');
    alert.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
    alert.innerHTML = `<i class="fas fa-exclamation-triangle mr-2"></i>${message}`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 3000);
}

function showError(message) {
    const tbody = document.getElementById('leave-table-body');
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-8">
                <div class="text-red-500">
                    <i class="fas fa-exclamation-triangle text-4xl mb-4 block"></i>
                    <p>${message}</p>
                </div>
            </td>
        </tr>
    `;
}

// Auto-refresh every 2 minutes
setInterval(() => {
    loadLeaveData();
}, 120000);
</script>
@endsection
