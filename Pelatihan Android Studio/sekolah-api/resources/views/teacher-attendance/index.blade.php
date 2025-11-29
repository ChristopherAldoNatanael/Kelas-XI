@extends('layouts.app')

@section('title', 'Teacher Attendance Management')

@section('page-header')
<div class="bg-gradient-to-r from-blue-600 to-purple-600 dark:from-blue-800 dark:to-purple-800 rounded-2xl p-6 mb-6 text-white">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold mb-2">Teacher Attendance Management</h1>
            <p class="text-blue-100">Monitor and manage teacher attendance records with advanced filtering and reporting</p>
        </div>
        <div class="hidden md:flex items-center gap-3">
            <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2">
                <i class="fas fa-calendar-check text-green-300"></i>
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
                    <div class="p-3 rounded-xl bg-gradient-to-br from-green-500/20 to-green-600/20 dark:from-green-500/20 dark:to-green-600/20 light:from-green-100 light:to-green-200 backdrop-blur-sm border border-green-400/20 dark:border-green-400/20 light:border-green-300/50">
                        <i class="fas fa-check-circle text-green-300 dark:text-green-300 light:text-green-600 text-xl"></i>
                    </div>
                </div>
                <div class="space-y-1">
                    <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900" id="present-count">0</p>
                    <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">Present Today</p>
                    <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">Teachers on time</p>
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
                    <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900" id="absent-count">0</p>
                    <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">Absent Today</p>
                    <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">Teachers not present</p>
                </div>
            </div>
        </div>

        <div class="adaptive-card group">
            <div class="relative p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-yellow-500/20 to-orange-600/20 dark:from-yellow-500/20 dark:to-orange-600/20 light:from-yellow-100 light:to-orange-200 backdrop-blur-sm border border-yellow-400/20 dark:border-yellow-400/20 light:border-yellow-300/50">
                        <i class="fas fa-clock text-yellow-300 dark:text-yellow-300 light:text-yellow-600 text-xl"></i>
                    </div>
                </div>
                <div class="space-y-1">
                    <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900" id="late-count">0</p>
                    <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">Late Today</p>
                    <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">Teachers running late</p>
                </div>
            </div>
        </div>

        <div class="adaptive-card group">
            <div class="relative p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-600/20 dark:from-blue-500/20 dark:to-indigo-600/20 light:from-blue-100 light:to-indigo-200 backdrop-blur-sm border border-blue-400/20 dark:border-blue-400/20 light:border-blue-300/50">
                        <i class="fas fa-user-friends text-blue-300 dark:text-blue-300 light:text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div class="space-y-1">
                    <p class="text-3xl font-bold text-white dark:text-white light:text-gray-900" id="substitute-count">0</p>
                    <p class="text-slate-300 dark:text-slate-300 light:text-gray-600 text-sm font-medium">On Leave</p>
                    <p class="text-slate-400 dark:text-slate-400 light:text-gray-500 text-xs">Substitute teachers</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Actions -->
    <div class="adaptive-card-section">
        <div class="p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-xl font-bold text-white dark:text-white light:text-gray-900 mb-2">Attendance Records</h3>
                    <p class="text-slate-300 dark:text-slate-300 light:text-gray-600">View and manage teacher attendance data</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button id="export-pdf" class="btn btn-secondary">
                        <i class="fas fa-file-pdf mr-2"></i>Export PDF
                    </button>
                    <button id="export-excel" class="btn btn-secondary">
                        <i class="fas fa-file-excel mr-2"></i>Export Excel
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="form-group">
                    <label class="form-label">Date From</label>
                    <input type="date" id="date_from" class="form-control" value="{{ request('date_from', now()->startOfMonth()->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Date To</label>
                    <input type="date" id="date_to" class="form-control" value="{{ request('date_to', now()->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <select id="subject_id" class="form-control">
                        <option value="">All Subjects</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}" {{ request('subject_id') == $subject->id ? 'selected' : '' }}>
                                {{ $subject->nama_mapel }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Teacher</label>
                    <select id="teacher_id" class="form-control">
                        <option value="">All Teachers</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                {{ $teacher->name }} {{ $teacher->nama ? "({$teacher->nama})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Status Filter -->
            <div class="flex flex-wrap gap-2 mb-6">
                <button class="status-filter-btn active" data-status="">
                    <i class="fas fa-list mr-2"></i>All Records
                </button>
                <button class="status-filter-btn" data-status="present">
                    <i class="fas fa-check-circle mr-2"></i>Present
                </button>
                <button class="status-filter-btn" data-status="absent">
                    <i class="fas fa-times-circle mr-2"></i>Absent
                </button>
                <button class="status-filter-btn" data-status="late">
                    <i class="fas fa-clock mr-2"></i>Late
                </button>
                <button class="status-filter-btn" data-status="on_leave">
                    <i class="fas fa-user-friends mr-2"></i>On Leave
                </button>
            </div>

            <!-- Search -->
            <div class="flex flex-col sm:flex-row gap-4 mb-6">
                <div class="flex-1">
                    <input type="text" id="search" placeholder="Search by teacher name, subject, or class..." class="form-control" value="{{ request('search') }}">
                </div>
                <div class="flex gap-2">
                    <button id="apply-filters" class="btn btn-primary">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <button id="clear-filters" class="btn btn-secondary">
                        <i class="fas fa-times mr-2"></i>Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="adaptive-card-section">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-semibold text-white dark:text-white light:text-gray-900">Attendance Records</h4>
                <div class="text-sm text-slate-400 dark:text-slate-400 light:text-gray-500">
                    Showing <span id="showing-count">0</span> of <span id="total-count">0</span> records
                </div>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Teacher</th>
                            <th>Subject</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Check-in Time</th>
                            <th>Substitute</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="attendance-table-body">
                        <tr>
                            <td colspan="8" class="text-center py-8">
                                <div class="flex items-center justify-center">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                                    <span class="ml-3 text-slate-400 dark:text-slate-400 light:text-gray-500">Loading attendance records...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="pagination-container" class="mt-6 flex items-center justify-between">
                <div class="text-sm text-slate-400 dark:text-slate-400 light:text-gray-500">
                    <span id="pagination-info">No records found</span>
                </div>
                <div id="pagination-links" class="flex gap-2">
                    <!-- Pagination links will be inserted here -->
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

@section('scripts')
<script>
// Global variables
let currentPage = 1;
let currentFilters = {
    date_from: '{{ request("date_from", now()->startOfMonth()->format("Y-m-d")) }}',
    date_to: '{{ request("date_to", now()->format("Y-m-d")) }}',
    subject_id: '{{ request("subject_id", "") }}',
    teacher_id: '{{ request("teacher_id", "") }}',
    status: '{{ request("status", "") }}',
    search: '{{ request("search", "") }}',
    per_page: 15
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    loadAttendanceData();
    setupEventListeners();
});

function setupEventListeners() {
    // Filter buttons
    document.querySelectorAll('.status-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.status-filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilters.status = this.dataset.status;
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

    // Clear filters
    document.getElementById('clear-filters').addEventListener('click', function() {
        clearFilters();
        loadAttendanceData();
    });

    // Search on enter
    document.getElementById('search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            updateFiltersFromForm();
            currentPage = 1;
            loadAttendanceData();
        }
    });

    // Export buttons
    document.getElementById('export-pdf').addEventListener('click', function() {
        exportData('pdf');
    });

    document.getElementById('export-excel').addEventListener('click', function() {
        exportData('excel');
    });

    // Modal close
    document.getElementById('close-modal').addEventListener('click', function() {
        document.getElementById('attendance-modal').classList.add('hidden');
    });

    // Close modal on outside click
    document.getElementById('attendance-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
}

function updateFiltersFromForm() {
    currentFilters.date_from = document.getElementById('date_from').value;
    currentFilters.date_to = document.getElementById('date_to').value;
    currentFilters.subject_id = document.getElementById('subject_id').value;
    currentFilters.teacher_id = document.getElementById('teacher_id').value;
    currentFilters.search = document.getElementById('search').value;
}

function clearFilters() {
    document.getElementById('date_from').value = '{{ now()->startOfMonth()->format("Y-m-d") }}';
    document.getElementById('date_to').value = '{{ now()->format("Y-m-d") }}';
    document.getElementById('subject_id').value = '';
    document.getElementById('teacher_id').value = '';
    document.getElementById('search').value = '';

    document.querySelectorAll('.status-filter-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.status-filter-btn[data-status=""]').classList.add('active');

    currentFilters = {
        date_from: '{{ now()->startOfMonth()->format("Y-m-d") }}',
        date_to: '{{ now()->format("Y-m-d") }}',
        subject_id: '',
        teacher_id: '',
        status: '',
        search: '',
        per_page: 15
    };
    currentPage = 1;
}

function loadAttendanceData() {
    const loadingRow = `
        <tr>
            <td colspan="8" class="text-center py-8">
                <div class="flex items-center justify-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    <span class="ml-3 text-slate-400 dark:text-slate-400 light:text-gray-500">Loading attendance records...</span>
                </div>
            </td>
        </tr>
    `;

    document.getElementById('attendance-table-body').innerHTML = loadingRow;

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
            renderAttendanceTable(data.data);
            renderPagination(data.data);
        } else {
            showError('Failed to load attendance data');
        }
    })
    .catch(error => {
        console.error('Error loading attendance data:', error);
        showError('Network error occurred');
    });
}

function updateStatistics(stats) {
    document.getElementById('present-count').textContent = stats.present || 0;
    document.getElementById('absent-count').textContent = stats.absent || 0;
    document.getElementById('late-count').textContent = stats.late || 0;
    document.getElementById('substitute-count').textContent = stats.on_leave || 0;
}

function renderAttendanceTable(data) {
    const tbody = document.getElementById('attendance-table-body');

    if (!data.data || data.data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-8">
                    <div class="text-slate-400 dark:text-slate-400 light:text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-4 block"></i>
                        <p>No attendance records found</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    const rows = data.data.map(record => {
        const statusBadge = getStatusBadge(record.status);
        const substituteInfo = record.guru_asli ? `<span class="text-xs text-blue-400">Sub for ${record.guru_asli.name}</span>` : '-';

        return `
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 light:hover:bg-gray-50 transition-colors">
                <td class="font-medium">${formatDate(record.tanggal)}</td>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 light:bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-user text-blue-600 dark:text-blue-400 light:text-blue-600 text-xs"></i>
                        </div>
                        <div>
                            <div class="font-medium text-white dark:text-white light:text-gray-900">${record.guru.name}</div>
                            <div class="text-xs text-slate-400 dark:text-slate-400 light:text-gray-500">${record.guru.nama || ''}</div>
                        </div>
                    </div>
                </td>
                <td>${record.schedule?.subject?.nama_mapel || '-'}</td>
                <td>${record.schedule?.class_model?.nama_kelas || '-'}</td>
                <td>${statusBadge}</td>
                <td>${record.jam_masuk ? formatTime(record.jam_masuk) : '-'}</td>
                <td>${substituteInfo}</td>
                <td>
                    <button onclick="showAttendanceDetail(${record.id})" class="btn btn-sm btn-secondary">
                        <i class="fas fa-eye mr-1"></i>View
                    </button>
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

function showError(message) {
    // Simple error display - you can enhance this
    const tbody = document.getElementById('attendance-table-body');
    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-8">
                <div class="text-red-500">
                    <i class="fas fa-exclamation-triangle text-4xl mb-4 block"></i>
                    <p>${message}</p>
                </div>
            </td>
        </tr>
    `;
}

// Auto-refresh every 5 minutes
setInterval(() => {
    loadAttendanceData();
}, 300000);
</script>
@endsection
