@extends('layouts.app')

@section('title', 'Schedules Management')

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
                            Schedules Management
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Comprehensive academic scheduling system. Manage class timetables, teacher assignments, and optimize educational resources.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ count($schedules) }}</div>
                            <div class="text-white/70 text-sm">Total Schedules</div>
                        </div>
                        <div class="glass-stat-card">
                            @php
                                $dayMap = [
                                    'Monday' => 'Senin',
                                    'Tuesday' => 'Selasa',
                                    'Wednesday' => 'Rabu',
                                    'Thursday' => 'Kamis',
                                    'Friday' => 'Jumat',
                                    'Saturday' => 'Sabtu',
                                    'Sunday' => 'Minggu'
                                ];
                                $todayIndonesian = $dayMap[now()->format('l')] ?? '';
                            @endphp
                            <div class="text-2xl font-bold text-white">{{ collect($schedules)->where('hari', $todayIndonesian)->count() }}</div>
                            <div class="text-white/70 text-sm">Today's Classes</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $dropdownData['teachers']->count() ?? 0 }}</div>
                            <div class="text-white/70 text-sm">Active Teachers</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0 flex gap-4">
                    <a href="{{ route('web-schedules.import') }}" class="glass-action-button group">
                        <div class="p-4 rounded-xl bg-gradient-to-br from-green-500/20 to-emerald-500/20 border border-green-400/20">
                            <i class="fas fa-upload text-green-300 text-2xl"></i>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Import Schedules</div>
                            <div class="text-slate-300 text-sm">Bulk upload CSV</div>
                        </div>
                        <i class="fas fa-arrow-right text-slate-400 group-hover:text-white transition-colors duration-300"></i>
                    </a>

                    <a href="{{ route('web-schedules.create') }}" class="glass-action-button group">
                        <div class="p-4 rounded-xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20 border border-purple-400/20">
                            <i class="fas fa-plus text-purple-300 text-2xl"></i>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Create Schedule</div>
                            <div class="text-slate-300 text-sm">Add new class</div>
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

    <!-- Search and Filters Header -->
    <div class="px-6 space-y-6">
        <!-- Search and Filters Header -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-2">Schedule Directory</h3>
                        <p class="text-slate-300">Browse and manage all class schedules in your system</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search schedules..."
                                    class="glass-search-input">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        </div>

                        <div class="flex gap-2">
                            <button class="glass-filter-btn" data-filter="all">
                                <i class="fas fa-calendar"></i>
                                All
                            </button>
                            <button class="glass-filter-btn" data-filter="today">
                                <i class="fas fa-calendar-day"></i>
                                Today
                            </button>
                            <button class="glass-filter-btn" data-filter="week">
                                <i class="fas fa-calendar-week"></i>
                                This Week
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Filters Bar -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="mata_pelajaran_filter" class="block text-sm font-medium text-white mb-2">Subject</label>
                        <input type="text" id="mata_pelajaran_filter" placeholder="Search subjects..."
                                class="glass-input">
                    </div>
                    <div>
                        <label for="guru_filter" class="block text-sm font-medium text-white mb-2">Teacher</label>
                        <select id="guru_filter" class="glass-select">
                            <option value="">All Teachers</option>
                            @foreach($dropdownData['teachers'] ?? [] as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="hari_filter" class="block text-sm font-medium text-white mb-2">Day</label>
                        <select id="hari_filter" class="glass-select">
                            <option value="">All Days</option>
                            <option value="Senin">Monday</option>
                            <option value="Selasa">Tuesday</option>
                            <option value="Rabu">Wednesday</option>
                            <option value="Kamis">Thursday</option>
                            <option value="Jumat">Friday</option>
                            <option value="Sabtu">Saturday</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button id="filter_btn" class="glass-action-btn glass-action-primary w-full">
                            <i class="fas fa-filter mr-2"></i>
                            Apply Filters
                        </button>
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
                        <span class="text-sm text-slate-300" id="selectedCount">0 schedules selected</span>
                    </div>

                    <div class="flex gap-3">
                        <button id="deleteSelectedBtn" class="glass-action-btn glass-action-danger disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-trash-alt mr-2"></i>
                            Delete Selected
                        </button>
                        <button id="deleteAllBtn" class="glass-action-btn glass-action-danger">
                            <i class="fas fa-trash-alt mr-2"></i>
                            Delete All Schedules
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedules Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 md:gap-6" id="schedulesGrid">
            @forelse($schedules as $schedule)
                <div class="schedule-card glass-morphism-card group h-full" data-guru="{{ $schedule['guru_id'] ?? '' }}" data-hari="{{ $schedule['hari'] ?? '' }}">
                    <div class="relative p-4 md:p-5 flex flex-col h-full">
                        <!-- Selection Checkbox -->
                        <div class="absolute top-4 left-4 z-20">
                            <label for="checkbox-{{ $schedule['id'] }}" class="checkbox-label flex items-center justify-center w-8 h-8 bg-white/20 backdrop-blur-sm rounded-lg border-2 border-white/30 cursor-pointer hover:bg-white/30 hover:border-white/50 transition-all duration-200">
                                <input type="checkbox" id="checkbox-{{ $schedule['id'] }}" class="schedule-checkbox opacity-0 absolute w-8 h-8 cursor-pointer" value="{{ $schedule['id'] }}"
                                        data-schedule-id="{{ $schedule['id'] }}" data-schedule-subject="{{ $schedule['mata_pelajaran'] ?? 'N/A' }}">
                                <span class="checkmark text-white text-lg font-bold opacity-0 transition-opacity duration-200">✓</span>
                            </label>
                        </div>

                        <!-- Schedule ID Badge -->
                        <div class="absolute top-4 right-4">
                            <span class="glass-status-badge glass-status-active text-xs">
                                #{{ $schedule['id'] }}
                            </span>
                        </div>

                        <!-- Subject Info -->
                        <div class="flex items-start justify-between mb-3 pl-8 pr-16">
                            <div class="flex items-center gap-3">
                                <div class="subject-avatar bg-gradient-to-br from-purple-500 to-indigo-600 flex-shrink-0">
                                    <span class="text-white font-bold text-lg">
                                        {{ strtoupper(substr($schedule['mata_pelajaran'] ?? 'N', 0, 1)) }}
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-lg font-bold text-white mb-1 truncate" style="color: var(--text-primary);" title="{{ $schedule['mata_pelajaran'] ?? 'N/A' }}">{{ $schedule['mata_pelajaran'] ?? 'N/A' }}</h4>
                                    <p class="text-slate-300 text-sm truncate" style="color: var(--text-secondary);">Subject</p>
                                </div>
                            </div>
                        </div>

                        <!-- Teacher Info -->
                        <div class="space-y-2 mb-4 flex-grow">
                            <div class="glass-teacher-info text-sm">
                                <i class="fas fa-chalkboard-teacher mr-2"></i>
                                Teacher: {{ $schedule['guru_nama'] ?? 'Unknown' }}
                            </div>
                            <div class="glass-class-info text-sm">
                                <i class="fas fa-users mr-2"></i>
                                Class: {{ $schedule['kelas'] ?? 'N/A' }}
                            </div>
                        </div>

                        <!-- Schedule Details -->
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="glass-stat-mini text-center">
                                <div class="text-sm font-bold text-white">{{ $schedule['hari'] ?? 'N/A' }}</div>
                                <div class="text-xs text-slate-400">Day</div>
                            </div>
                            <div class="glass-stat-mini text-center">
                                <div class="text-sm font-bold text-white">{{ $schedule['ruang'] ?? 'N/A' }}</div>
                                <div class="text-xs text-slate-400">Room</div>
                            </div>
                        </div>

                        <!-- Time Info -->
                        <div class="mb-4">
                            <div class="glass-time-info text-sm">
                                <i class="fas fa-clock mr-2"></i>
                                @if(isset($schedule['jam_mulai']) && isset($schedule['jam_selesai']))
                                    {{ $schedule['jam_mulai'] }} - {{ $schedule['jam_selesai'] }}
                                @else
                                    Time not set
                                @endif
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-between pt-3 border-t mt-auto" style="border-color: rgba(255, 255, 255, 0.1);">
                            <div class="flex gap-2">
                                <a href="{{ route('web-schedules.show', $schedule['id']) }}" class="glass-action-btn glass-action-view" title="View Details">
                                    <i class="fas fa-eye text-sm"></i>
                                </a>
                                <a href="{{ route('web-schedules.edit', $schedule['id']) }}" class="glass-action-btn glass-action-edit" title="Edit Schedule">
                                    <i class="fas fa-edit text-sm"></i>
                                </a>
                            </div>

                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('web-schedules.destroy', $schedule['id']) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="glass-action-btn glass-action-delete" title="Delete Schedule"
                                            onclick="return confirm('Are you sure you want to delete this schedule?')">
                                        <i class="fas fa-trash-alt text-sm"></i>
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
                            <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-purple-500/20 to-indigo-500/20 flex items-center justify-center">
                                <i class="fas fa-calendar-alt text-4xl text-purple-400"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-3">No Schedules Found</h3>
                            <p class="text-slate-300 mb-8 max-w-md mx-auto">Get started by creating your first class schedule to begin managing your academic timetable.</p>
                            <a href="{{ route('web-schedules.create') }}" class="glass-action-button group inline-flex">
                                <div class="p-3 rounded-xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20 border border-purple-400/20">
                                    <i class="fas fa-plus text-purple-300 text-xl"></i>
                                </div>
                                <div>
                                    <div class="text-white font-semibold">Create Schedule</div>
                                    <div class="text-slate-300 text-sm">Add new class</div>
                                </div>
                                <i class="fas fa-arrow-right text-slate-400 group-hover:text-white transition-colors duration-300"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>
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

    // Enhanced Search Functionality
    const searchInput = document.getElementById('searchInput');
    const schedulesGrid = document.getElementById('schedulesGrid');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const filter = this.value.toLowerCase().trim();

        // Add loading state
        this.classList.add('loading');

        searchTimeout = setTimeout(() => {
            const scheduleCards = schedulesGrid.querySelectorAll('.schedule-card');
            let visibleCount = 0;

            scheduleCards.forEach(card => {
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
            const scheduleCards = schedulesGrid.querySelectorAll('.schedule-card');
            const today = new Date().toLocaleLowerCase('id-ID', { weekday: 'long' });

            scheduleCards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = '';
                    card.style.animation = 'fadeInUp 0.3s ease-out forwards';
                } else if (filter === 'today') {
                    const cardDay = card.dataset.hari?.toLowerCase();
                    const shouldShow = cardDay === today;
                    card.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) {
                        card.style.animation = 'fadeInUp 0.3s ease-out forwards';
                    }
                } else if (filter === 'week') {
                    // Show all for this week (simplified)
                    card.style.display = '';
                    card.style.animation = 'fadeInUp 0.3s ease-out forwards';
                }
            });
        });
    });

    // Advanced Filters
    const filterBtn = document.getElementById('filter_btn');
    const mataPelajaranFilter = document.getElementById('mata_pelajaran_filter');
    const guruFilter = document.getElementById('guru_filter');
    const hariFilter = document.getElementById('hari_filter');

    function applyAdvancedFilters() {
        const mataPelajaranValue = mataPelajaranFilter.value.toLowerCase();
        const guruValue = guruFilter.value;
        const hariValue = hariFilter.value;
        const searchValue = searchInput.value.toLowerCase();

        const scheduleCards = schedulesGrid.querySelectorAll('.schedule-card');
        scheduleCards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const mataPelajaranMatch = !mataPelajaranValue || text.includes(mataPelajaranValue);
            const guruMatch = !guruValue || card.dataset.guru === guruValue;
            const hariMatch = !hariValue || card.dataset.hari === hariValue;
            const searchMatch = !searchValue || text.includes(searchValue);

            if (mataPelajaranMatch && guruMatch && hariMatch && searchMatch) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    filterBtn.addEventListener('click', applyAdvancedFilters);
    mataPelajaranFilter.addEventListener('input', applyAdvancedFilters);
    guruFilter.addEventListener('change', applyAdvancedFilters);
    hariFilter.addEventListener('change', applyAdvancedFilters);

    // Search info update
    function updateSearchInfo(count, filter) {
        let infoElement = document.querySelector('.search-info');
        if (!infoElement) {
            infoElement = document.createElement('div');
            infoElement.className = 'search-info text-sm text-slate-400 mt-4 px-4 py-2 bg-white/5 rounded-lg backdrop-blur-sm border border-white/10';
            searchInput.parentNode.appendChild(infoElement);
        }

        if (filter) {
            infoElement.textContent = `Found ${count} schedule${count !== 1 ? 's' : ''} matching "${filter}"`;
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

    // Bulk Selection Functionality
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectAllCheckbox = document.getElementById('selectAll');
    const scheduleCheckboxes = document.querySelectorAll('.schedule-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const deleteAllBtn = document.getElementById('deleteAllBtn');

    console.log('=== SCHEDULE BULK SELECTION DEBUG ===');
    console.log('bulkActionsBar:', bulkActionsBar);
    console.log('selectAllCheckbox:', selectAllCheckbox);
    console.log('scheduleCheckboxes found:', scheduleCheckboxes.length);
    console.log('selectedCount:', selectedCount);
    console.log('deleteSelectedBtn:', deleteSelectedBtn);
    console.log('deleteAllBtn:', deleteAllBtn);

    // CSRF token for AJAX requests
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // Update bulk actions bar visibility and selected count
    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('.schedule-checkbox:checked:not([disabled])');
        const totalChecked = checkedBoxes.length;

        if (bulkActionsBar && selectedCount && deleteSelectedBtn) {
            if (totalChecked > 0) {
                bulkActionsBar.style.display = 'block';
                selectedCount.textContent = `${totalChecked} schedule${totalChecked !== 1 ? 's' : ''} selected`;
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
            scheduleCheckboxes.forEach(checkbox => {
                if (!checkbox.disabled) {
                    checkbox.checked = isChecked;
                }
            });
            updateBulkActions();
        });
    }

    // Individual checkbox change
    scheduleCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('Individual schedule checkbox changed:', this.value, 'checked:', this.checked);

            // Update label styling
            const label = this.closest('label');
            if (label) {
                if (this.checked) {
                    label.classList.add('checked');
                } else {
                    label.classList.remove('checked');
                }
            }

            const totalCheckboxes = document.querySelectorAll('.schedule-checkbox:not([disabled])').length;
            const checkedBoxes = document.querySelectorAll('.schedule-checkbox:checked:not([disabled])').length;

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
            const selectedSchedules = document.querySelectorAll('.schedule-checkbox:checked:not([disabled])');

            console.log('=== DELETE SELECTED SCHEDULES DEBUG ===');
            console.log('Selected schedules count:', selectedSchedules.length);
            console.log('Selected checkboxes:', selectedSchedules);

            if (selectedSchedules.length === 0) {
                showNotification('error', 'No schedules selected. Please select schedules to delete.');
                return;
            }

            const scheduleNames = Array.from(selectedSchedules).map(cb => cb.dataset.scheduleSubject).join(', ');
            const scheduleIds = Array.from(selectedSchedules).map(cb => cb.value);

            console.log('Schedule IDs to delete:', scheduleIds);
            console.log('Schedule subjects:', scheduleNames);
            console.log('CSRF Token:', csrfToken);

            if (confirm(`Are you sure you want to delete ${selectedSchedules.length} selected schedule(s)?\n\nSubjects: ${scheduleNames}\n\nThis action cannot be undone.`)) {
                // Show loading state
                deleteSelectedBtn.disabled = true;
                deleteSelectedBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';

                console.log('Sending DELETE request to:', '{{ route("web-schedules.bulk-delete.post") }}');
                console.log('Request body:', { schedule_ids: scheduleIds });

                // Send AJAX request
                fetch('{{ route("web-schedules.bulk-delete.post") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ schedule_ids: scheduleIds })
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

                        // Remove deleted schedule cards with animation
                        selectedSchedules.forEach(checkbox => {
                            const card = checkbox.closest('.schedule-card');
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
                        const remainingCards = document.querySelectorAll('.schedule-card').length;
                        if (remainingCards === 0) {
                            setTimeout(() => window.location.reload(), 2000);
                        }
                    } else {
                        showNotification('error', data.message || 'Failed to delete schedules');
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
            const totalSchedules = document.querySelectorAll('.schedule-checkbox:not([disabled])').length;

            if (totalSchedules === 0) {
                showNotification('error', 'No schedules available to delete.');
                return;
            }

            if (confirm(`Are you sure you want to delete ALL ${totalSchedules} schedules?\n\nThis will delete all schedules permanently.\n\nThis action cannot be undone.`)) {
                // Show loading state
                deleteAllBtn.disabled = true;
                deleteAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';

                // Send AJAX request
                fetch('{{ route("web-schedules.bulk-delete-all.post") }}', {
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
                        showNotification('error', data.message || 'Failed to delete all schedules');
                    }
                })
                .catch(error => {
                    console.error('Bulk delete all error:', error);
                    showNotification('error', 'Network error occurred during deletion');
                })
                .finally(() => {
                    // Reset button state
                    deleteAllBtn.disabled = false;
                    deleteAllBtn.innerHTML = '<i class="fas fa-trash-alt mr-2"></i>Delete All Schedules';
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

/* Schedule Cards */
.schedule-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.schedule-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
}

/* Subject Avatar */
.subject-avatar {
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

/* Info Components */
.glass-teacher-info, .glass-class-info, .glass-time-info {
    padding: 0.75rem 1rem;
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

/* Form Inputs */
.glass-input, .glass-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    color: white;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.glass-input:focus, .glass-select:focus {
    outline: none;
    border-color: rgba(147, 51, 234, 0.5);
    box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.1);
}

.glass-input::placeholder {
    color: #94a3b8;
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
    border-color: rgba(147, 51, 234, 0.5);
    box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.1);
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
    background: rgba(147, 51, 234, 0.2);
    border-color: rgba(147, 51, 234, 0.4);
    color: #c084fc;
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
    border-top: 2px solid #8b5cf6;
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

    .schedule-card {
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

/* Checkbox Styles */
.checkbox-label {
    position: relative;
    cursor: pointer;
    transition: all 0.2s ease;
}

.checkbox-label:hover {
    transform: scale(1.05);
}

.checkbox-label.checked .checkmark {
    opacity: 1;
    transform: scale(1);
}

.checkmark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.8);
    opacity: 0;
    transition: all 0.2s ease;
    font-weight: bold;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
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
    position: relative;
}

.glass-checkbox:checked {
    background: rgba(59, 130, 246, 0.8);
    border-color: rgba(59, 130, 246, 0.8);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.glass-checkbox:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
}

/* Danger Action Button */
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

/* Print Styles */
@media print {
    .glass-morphism-card,
    .schedule-card {
        background: white !important;
        border: 1px solid #e5e7eb !important;
        box-shadow: none !important;
    }
}
</style>
@endsection
