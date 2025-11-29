@extends('layouts.app')

@section('content')
<div class="min-h-screen">
    <!-- Hero Header with Glass Morphism -->
    <div class="relative overflow-hidden rounded-3xl mx-6 mb-8">
        <!-- Background Layers -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-white/10"></div>

        <!-- Animated Background Elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-orange-500/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-yellow-500/5 rounded-full blur-2xl animate-pulse" style="animation-delay: 4s;"></div>

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
                            Teachers Management
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Comprehensive academic staff administration. Manage teaching personnel, subjects, and educational excellence across your institution.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ number_format($teachers->total()) }}</div>
                            <div class="text-white/70 text-sm">Total Teachers</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $teachers->whereNotNull('expertise')->count() }}</div>
                            <div class="text-white/70 text-sm">Subject Specialists</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $teachers->where('status', 'active')->count() }}</div>
                            <div class="text-white/70 text-sm">Active Staff</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0 flex gap-4">
                    <a href="{{ route('web-teachers.import') }}" class="glass-action-button group">
                        <div class="p-4 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 border border-blue-400/20">
                            <i class="fas fa-upload text-blue-300 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Import Teachers</div>
                            <div class="text-slate-300 text-sm">Bulk upload CSV</div>
                        </div>
                        <i class="fas fa-arrow-right text-slate-400 group-hover:text-white transition-colors duration-300"></i>
                    </a>

                    <a href="{{ route('web-teachers.create') }}" class="glass-action-button group">
                        <div class="p-4 rounded-xl bg-gradient-to-br from-orange-500/20 to-amber-500/20 border border-orange-400/20">
                            <i class="fas fa-plus text-orange-300 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Add New Teacher</div>
                            <div class="text-slate-300 text-sm">Create staff account</div>
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

    <!-- Teachers Directory with Glass Morphism -->
    <div class="px-6 space-y-6">
        <!-- Search and Filters Header -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-2">Teachers Directory</h3>
                        <p class="text-slate-300">Browse and manage all teaching staff in your institution</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search teachers..."
                                    class="glass-search-input">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        </div>

                        <div class="flex gap-2">
                            <button class="glass-filter-btn" data-filter="all">
                                <i class="fas fa-users"></i>
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
                            <button class="glass-filter-btn" data-filter="retired">
                                <i class="fas fa-user-clock"></i>
                                Retired
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="glass-morphism-card" id="bulkActionsBar" style="display: none;">
            <div class="p-6">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="selectAll" class="glass-checkbox">
                            <label for="selectAll" class="font-medium text-white">Select All</label>
                        </div>
                        <span class="text-sm text-slate-300" id="selectedCount">0 teachers selected</span>
                    </div>

                    <div class="flex gap-3">
                        <button id="deleteSelectedBtn" class="glass-action-btn glass-action-danger disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-trash-alt mr-2"></i>
                            Delete Selected
                        </button>
                        <button id="deleteAllBtn" class="glass-action-btn glass-action-danger">
                            <i class="fas fa-trash-alt mr-2"></i>
                            Delete All Teachers
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teachers Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 md:gap-6" id="teachersGrid">
            @forelse($teachers as $teacher)
                <div class="teacher-card glass-morphism-card group h-full" data-status="{{ $teacher->status }}" data-teacher-id="{{ $teacher->id }}">
                    <div class="relative p-4 md:p-5 flex flex-col h-full">
                        <!-- Selection Checkbox -->
                        <div class="absolute top-4 left-4 z-20">
                            <label for="checkbox-{{ $teacher->id }}" class="checkbox-label flex items-center justify-center w-8 h-8 bg-white/20 backdrop-blur-sm rounded-lg border-2 border-white/30 cursor-pointer hover:bg-white/30 hover:border-white/50 transition-all duration-200">
                                <input type="checkbox" id="checkbox-{{ $teacher->id }}" class="teacher-checkbox opacity-0 absolute w-8 h-8 cursor-pointer" value="{{ $teacher->id }}"
                                        data-teacher-id="{{ $teacher->id }}" data-teacher-name="{{ $teacher->nama }}">
                                <span class="checkmark text-white text-lg font-bold opacity-0 transition-opacity duration-200">✓</span>
                            </label>
                        </div>

                        <!-- Teacher Avatar and Basic Info -->
                        <div class="flex items-start justify-between mb-3 pl-8">
                            <div class="flex items-center gap-3">
                                <div class="teacher-avatar-large bg-gradient-to-br from-orange-500 to-amber-600 flex-shrink-0">
                                    <span class="text-white font-bold text-lg">
                                        {{ strtoupper(substr($teacher->nama, 0, 1)) }}
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-lg font-bold text-white mb-1 truncate" style="color: var(--text-primary);" title="{{ $teacher->nama }}">{{ $teacher->nama }}</h4>
                                    <p class="text-slate-300 text-sm flex items-center truncate" style="color: var(--text-secondary);" title="{{ $teacher->nip }}">
                                        <i class="fas fa-id-badge mr-2 flex-shrink-0"></i>
                                        <span class="truncate">{{ $teacher->nip }}</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                @if($teacher->status === 'active')
                                    <span class="glass-status-badge glass-status-active text-xs">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Active
                                    </span>
                                @elseif($teacher->status === 'inactive')
                                    <span class="glass-status-badge glass-status-inactive text-xs">
                                        <i class="fas fa-pause-circle mr-1"></i>
                                        Inactive
                                    </span>
                                @else
                                    <span class="glass-status-badge glass-status-retired text-xs">
                                        <i class="fas fa-user-clock mr-1"></i>
                                        Retired
                                    </span>
                                @endif

                                <!-- Teacher ID -->
                                <span class="text-xs text-slate-400 font-mono">#{{ $teacher->id }}</span>
                            </div>
                        </div>

                        <!-- Subject and Role Info -->
                        <div class="space-y-2 mb-4 flex-grow">
                            @if($teacher->expertise)
                                <div class="glass-subject-badge bg-gradient-to-r from-orange-500/20 to-amber-500/20 border-orange-400/30 text-sm">
                                    <i class="fas fa-book mr-2"></i>
                                    {{ $teacher->expertise }}
                                </div>
                            @else
                                <div class="glass-subject-badge bg-gradient-to-r from-slate-500/20 to-slate-600/20 border-slate-400/30 text-sm">
                                    <i class="fas fa-graduation-cap mr-2"></i>
                                    {{ $teacher->position ?: 'General Position' }}
                                </div>
                            @endif
                        </div>

                        <!-- Teacher Stats -->
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="glass-stat-mini text-center">
                                <div class="text-sm font-bold text-white">{{ $teacher->created_at ? $teacher->created_at->diffForHumans() : 'N/A' }}</div>
                                <div class="text-xs text-slate-400">Joined</div>
                            </div>
                            <div class="glass-stat-mini text-center">
                                <div class="text-sm font-bold text-white">{{ ucfirst($teacher->status) }}</div>
                                <div class="text-xs text-slate-400">Status</div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-between pt-3 border-t mt-auto" style="border-color: rgba(255, 255, 255, 0.1);">
                            <div class="flex gap-2">
                                <a href="{{ route('web-teachers.show', $teacher->id) }}" class="glass-action-btn glass-action-view" title="View Details">
                                    <i class="fas fa-eye text-sm"></i>
                                </a>
                                <a href="{{ route('web-teachers.edit', $teacher->id) }}" class="glass-action-btn glass-action-edit" title="Edit Teacher">
                                    <i class="fas fa-edit text-sm"></i>
                                </a>
                            </div>

                            <div class="flex gap-2">
                                @if(!$teacher->is_banned)
                                    <form method="POST" action="{{ route('web-teachers.destroy', $teacher->id) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="glass-action-btn glass-action-delete" title="Delete Teacher"
                                                onclick="return confirm('Are you sure you want to delete {{ $teacher->nama }}?')">
                                            <i class="fas fa-trash-alt text-sm"></i>
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('web-teachers.restore', $teacher->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="glass-action-btn glass-action-restore" title="Restore Teacher">
                                            <i class="fas fa-undo text-sm"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('web-teachers.force-delete', $teacher->id) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="glass-action-btn glass-action-delete" title="Permanently Delete"
                                                onclick="return confirm('Are you sure you want to permanently delete this teacher?')">
                                            <i class="fas fa-times text-sm"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <div class="glass-morphism-card">
                        <div class="p-12 text-center">
                            <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-orange-500/20 to-amber-500/20 flex items-center justify-center">
                                <i class="fas fa-chalkboard-teacher text-4xl text-orange-400"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-3">No Teachers Found</h3>
                            <p class="text-slate-300 mb-8 max-w-md mx-auto">Get started by creating your first teacher account to begin managing your academic staff.</p>
                            <a href="{{ route('web-teachers.create') }}" class="glass-action-button group inline-flex">
                                <div class="p-3 rounded-xl bg-gradient-to-br from-orange-500/20 to-amber-500/20 border border-orange-400/20">
                                    <i class="fas fa-plus text-orange-300 text-xl"></i>
                                </div>
                                <div>
                                    <div class="text-white font-semibold">Add New Teacher</div>
                                    <div class="text-slate-300 text-sm">Create staff account</div>
                                </div>
                                <i class="fas fa-arrow-right text-slate-400 group-hover:text-white transition-colors duration-300"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Modern Pagination -->
        @if($teachers->hasPages())
            <div class="glass-morphism-card">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-2 text-slate-300">
                            <i class="fas fa-info-circle text-orange-400"></i>
                            <span>Showing <span class="font-semibold text-white">{{ $teachers->firstItem() }}</span> to
                            <span class="font-semibold text-white">{{ $teachers->lastItem() }}</span> of
                            <span class="font-semibold text-white">{{ $teachers->total() }}</span> teachers</span>
                        </div>
                        <div class="pagination-controls">
                            {{ $teachers->appends(request()->query())->links() }}
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
    const teachersGrid = document.getElementById('teachersGrid');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const filter = this.value.toLowerCase().trim();

        // Add loading state
        this.classList.add('loading');

        searchTimeout = setTimeout(() => {
            const teacherCards = teachersGrid.querySelectorAll('.teacher-card');
            let visibleCount = 0;

            teacherCards.forEach(card => {
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
            const teacherCards = teachersGrid.querySelectorAll('.teacher-card');
            teacherCards.forEach(card => {
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
            infoElement.textContent = `Found ${count} teacher${count !== 1 ? 's' : ''} matching "${filter}"`;
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

    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-teacher-btn')) {
            e.preventDefault();
            const btn = e.target.closest('.delete-teacher-btn');
            const teacherId = btn.dataset.teacherId;
            const teacherName = btn.dataset.teacherName;

            if (confirm(`Are you sure you want to delete "${teacherName}"?`)) {
                // Show loading state
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                fetch(`{{ url('web-teachers') }}/${teacherId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the teacher card from DOM
                        const teacherCard = btn.closest('.teacher-card');
                        if (teacherCard) {
                            teacherCard.remove();
                        }
                        // Show success notification
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('An error occurred while deleting the teacher.', 'error');
                    console.error('Delete error:', error);
                })
                .finally(() => {
                    // Restore button state
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                });
            }
        }
    });

    // Bulk Selection Functionality
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectAllCheckbox = document.getElementById('selectAll');
    const teacherCheckboxes = document.querySelectorAll('.teacher-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const deleteAllBtn = document.getElementById('deleteAllBtn');

    console.log('=== TEACHER BULK SELECTION DEBUG ===');
    console.log('bulkActionsBar:', bulkActionsBar);
    console.log('selectAllCheckbox:', selectAllCheckbox);
    console.log('teacherCheckboxes found:', teacherCheckboxes.length);
    console.log('selectedCount:', selectedCount);
    console.log('deleteSelectedBtn:', deleteSelectedBtn);
    console.log('deleteAllBtn:', deleteAllBtn);

    // CSRF token for AJAX requests
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // Update bulk actions bar visibility and selected count
    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('.teacher-checkbox:checked:not([disabled])');
        const totalChecked = checkedBoxes.length;

        if (bulkActionsBar && selectedCount && deleteSelectedBtn) {
            if (totalChecked > 0) {
                bulkActionsBar.style.display = 'block';
                selectedCount.textContent = `${totalChecked} teacher${totalChecked !== 1 ? 's' : ''} selected`;
                deleteSelectedBtn.disabled = false;
            } else {
                bulkActionsBar.style.display = 'none';
                deleteSelectedBtn.disabled = true;
            }
        }
    }

    // Select All functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            teacherCheckboxes.forEach(checkbox => {
                if (!checkbox.disabled) {
                    checkbox.checked = isChecked;
                }
            });
            updateBulkActions();
        });
    }

    // Individual checkbox change
    teacherCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('Individual teacher checkbox changed:', this.value, 'checked:', this.checked);

            // Update label styling
            const label = this.closest('label');
            if (label) {
                if (this.checked) {
                    label.classList.add('checked');
                } else {
                    label.classList.remove('checked');
                }
            }

            const totalCheckboxes = document.querySelectorAll('.teacher-checkbox:not([disabled])').length;
            const checkedBoxes = document.querySelectorAll('.teacher-checkbox:checked:not([disabled])').length;

            console.log('Total checkboxes:', totalCheckboxes, 'Checked boxes:', checkedBoxes);

            // Update select all checkbox state
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = checkedBoxes === totalCheckboxes && totalCheckboxes > 0;
                if (checkedBoxes > 0 && checkedBoxes < totalCheckboxes) {
                    selectAllCheckbox.indeterminate = true;
                } else {
                    selectAllCheckbox.indeterminate = false;
                }
            }

            updateBulkActions();
        });
    });

    // Delete Selected functionality
    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function() {
            const selectedTeachers = document.querySelectorAll('.teacher-checkbox:checked:not([disabled])');

            console.log('=== DELETE SELECTED TEACHERS DEBUG ===');
            console.log('Selected teachers count:', selectedTeachers.length);
            console.log('Selected checkboxes:', selectedTeachers);

            if (selectedTeachers.length === 0) {
                showNotification('error', 'No teachers selected. Please select teachers to delete.');
                return;
            }

            const teacherNames = Array.from(selectedTeachers).map(cb => cb.dataset.teacherName).join(', ');
            const teacherIds = Array.from(selectedTeachers).map(cb => cb.value);

            console.log('Teacher IDs to delete:', teacherIds);
            console.log('Teacher names:', teacherNames);
            console.log('CSRF Token:', csrfToken);

            if (confirm(`Are you sure you want to delete ${selectedTeachers.length} selected teacher(s)?\n\nTeachers: ${teacherNames}\n\nThis action cannot be undone.`)) {
                // Show loading state
                deleteSelectedBtn.disabled = true;
                deleteSelectedBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';

                console.log('Sending DELETE request to:', '{{ route("web-teachers.bulk-delete.post") }}');
                console.log('Request body:', { teacher_ids: teacherIds });

                // Send AJAX request
                fetch('{{ route("web-teachers.bulk-delete.post") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ teacher_ids: teacherIds })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);

                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Error response body:', text);
                            throw new Error(`HTTP error! status: ${response.status}, body: ${text}`);
                        });
                    }

                    return response.json();
                })
                .then(data => {
                    console.log('Success response:', data);
                    if (data.success) {
                        showNotification('success', data.message);

                        // Remove deleted teacher cards with animation
                        selectedTeachers.forEach(checkbox => {
                            const card = checkbox.closest('.teacher-card');
                            if (card) {
                                card.style.opacity = '0';
                                card.style.transform = 'scale(0.95)';
                                card.style.transition = 'all 0.3s ease';
                                setTimeout(() => card.remove(), 300);
                            }
                        });

                        // Hide bulk actions bar
                        if (bulkActionsBar) bulkActionsBar.style.display = 'none';

                        // Reset select all checkbox
                        if (selectAllCheckbox) {
                            selectAllCheckbox.checked = false;
                            selectAllCheckbox.indeterminate = false;
                        }

                        // Reload page after 2 seconds if all cards removed
                        const remainingCards = document.querySelectorAll('.teacher-card').length;
                        if (remainingCards === 0) {
                            setTimeout(() => window.location.reload(), 2000);
                        }
                    } else {
                        showNotification('error', data.message || 'Failed to delete teachers');
                    }
                })
                .catch(error => {
                    console.error('Bulk delete error:', error);
                    showNotification('error', 'Network error occurred during deletion');
                })
                .finally(() => {
                    // Reset button state
                    deleteSelectedBtn.disabled = false;
                    deleteSelectedBtn.innerHTML = '<i class="fas fa-trash-alt mr-2"></i>Delete Selected';
                });
            }
        });
    }

    // Delete All functionality
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', function() {
            const totalTeachers = document.querySelectorAll('.teacher-checkbox:not([disabled])').length;

            if (totalTeachers === 0) {
                showNotification('error', 'No teachers available to delete.');
                return;
            }

            if (confirm(`Are you sure you want to delete ALL ${totalTeachers} teachers?\n\nThis will delete all teachers except administrators.\n\nThis action cannot be undone.`)) {
                // Show loading state
                deleteAllBtn.disabled = true;
                deleteAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';

                // Send AJAX request
                fetch('{{ route("web-teachers.bulk-delete-all.post") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('success', data.message);
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        showNotification('error', data.message || 'Failed to delete all teachers');
                    }
                })
                .catch(error => {
                    console.error('Bulk delete all error:', error);
                    showNotification('error', 'Network error occurred during deletion');
                })
                .finally(() => {
                    // Reset button state
                    deleteAllBtn.disabled = false;
                    deleteAllBtn.innerHTML = '<i class="fas fa-trash-alt mr-2"></i>Delete All Teachers';
                });
            }
        });
    }

    // Notification helper function
    function showNotification(type, message) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.temp-notification');
        existingNotifications.forEach(notification => notification.remove());

        // Create new notification
        const notification = document.createElement('div');
        notification.className = `temp-notification mx-6 mb-6 glass-notification glass-notification-${type === 'success' ? 'success' : 'error'}`;
        notification.style.animation = 'fadeInUp 0.3s ease-out';
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        notification.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-${type === 'success' ? 'green' : 'red'}-500/20">
                    <i class="fas fa-${type === 'success' ? 'check-circle text-green-400' : 'exclamation-triangle text-red-400'}"></i>
                </div>
                <span style="color: var(--text-primary); font-weight: 500;">${message}</span>
            </div>
        `;

        // Insert at the end of body for better visibility
        document.body.appendChild(notification);

        // Auto-remove after 3 seconds (before page reload)
        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Initialize bulk actions on page load
    updateBulkActions();

    // Notification system
    function showNotification_old(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification-toast');
        existingNotifications.forEach(notification => notification.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification-toast fixed top-4 right-4 z-50 p-4 rounded-lg backdrop-blur-xl border max-w-sm ${
            type === 'success' ? 'bg-green-500/20 border-green-400/30 text-green-100' :
            type === 'error' ? 'bg-red-500/20 border-red-400/30 text-red-100' :
            'bg-blue-500/20 border-blue-400/30 text-blue-100'
        }`;
        notification.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg ${
                    type === 'success' ? 'bg-green-500/20' :
                    type === 'error' ? 'bg-red-500/20' :
                    'bg-blue-500/20'
                }">
                    <i class="fas ${
                        type === 'success' ? 'fa-check-circle text-green-400' :
                        type === 'error' ? 'fa-exclamation-triangle text-red-400' :
                        'fa-info-circle text-blue-400'
                    }"></i>
                </div>
                <span class="text-sm font-medium">${message}</span>
            </div>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
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

/* Teacher Cards */
.teacher-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.teacher-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
}

/* Teacher Avatar */
.teacher-avatar-large {
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

.glass-status-banned {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.glass-status-inactive {
    background: rgba(156, 163, 175, 0.1);
    border-color: rgba(156, 163, 175, 0.3);
    color: #6b7280;
}

.glass-status-retired {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
    color: #f59e0b;
}

/* Subject Badges */
.glass-subject-badge {
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

.glass-action-restore:hover {
    background: rgba(34, 197, 94, 0.2);
    border-color: rgba(34, 197, 94, 0.3);
    color: #22c55e;
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

/* Checkbox Styles */
.checkbox-label {
    transition: all 0.2s ease;
    cursor: pointer;
}

.checkbox-label:hover {
    transform: scale(1.1);
}

.checkbox-label.checked .checkmark {
    opacity: 1;
    transform: scale(1);
}

.checkmark {
    opacity: 0;
    transform: scale(0.8);
    transition: all 0.2s ease;
    color: #3b82f6;
    font-weight: bold;
    font-size: 16px;
}

/* Glass Checkbox */
.glass-checkbox {
    width: 1.2rem;
    height: 1.2rem;
    border-radius: 0.25rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    transition: all 0.2s ease;
    cursor: pointer;
    appearance: none;
}

.glass-checkbox:checked {
    background: rgba(59, 130, 246, 0.8);
    border-color: rgba(59, 130, 246, 0.8);
}

/* Action Danger Button */
.glass-action-danger {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.glass-action-danger:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.4);
    color: #dc2626;
}

/* Responsive Design */
@media (max-width: 768px) {
    .glass-morphism-card {
        margin: 0 1rem;
    }

    .teacher-card {
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
    .teacher-card {
        background: white !important;
        border: 1px solid #e5e7eb !important;
        box-shadow: none !important;
    }
}
</style>
@endsection
