@extends('layouts.app')

@section('title', 'Users Management')

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
                            Users Management
                        </h1>
                        <p class="text-lg md:text-xl leading-relaxed max-w-2xl" style="color: var(--text-secondary);">
                            Comprehensive user administration system. Manage roles, permissions, and access control for your educational platform.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($users->total()) }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Total Users</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $users->where('role', 'admin')->count() }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Administrators</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $users->where('role', 'siswa')->count() }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Students</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <div class="flex gap-4">
                        <a href="{{ route('web-users.create') }}" class="glass-action-button group">
                            <div class="p-4 rounded-xl bg-linear-to-br from-blue-500/20 to-blue-600/20 border border-blue-400/20">
                                <i class="fas fa-plus text-blue-300 text-2xl"></i>
                            </div>
                            <div>
                                <div class="font-semibold" style="color: var(--text-primary);">Add New User</div>
                                <div class="text-sm" style="color: var(--text-secondary);">Create account</div>
                            </div>
                            <i class="fas fa-arrow-right transition-colors duration-300" style="color: var(--text-secondary);"></i>
                        </a>

                        <a href="{{ route('web-users.import') }}" class="glass-action-button group">
                            <div class="p-4 rounded-xl bg-linear-to-br from-green-500/20 to-emerald-500/20 border border-green-400/20">
                                <i class="fas fa-upload text-green-300 text-2xl"></i>
                            </div>
                            <div>
                                <div class="font-semibold" style="color: var(--text-primary);">Import Users</div>
                                <div class="text-sm" style="color: var(--text-secondary);">Bulk upload</div>
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

    <!-- Users Directory with Glass Morphism -->
    <div class="px-6 space-y-6">
        <!-- Search and Filters Header -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div>
                        <h3 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">Users Directory</h3>
                        <p style="color: var(--text-secondary);">Browse and manage all registered users in your system</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search users..."
                                   class="glass-search-input">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);"></i>
                        </div>

                        <div class="flex gap-2">
                            <button class="glass-filter-btn" data-filter="all">
                                <i class="fas fa-users"></i>
                                All
                            </button>
                            <button class="glass-filter-btn" data-filter="admin">
                                <i class="fas fa-crown"></i>
                                Admin
                            </button>
                            <button class="glass-filter-btn" data-filter="siswa">
                                <i class="fas fa-graduation-cap"></i>
                                Students
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
                            <label for="selectAll" class="font-medium" style="color: var(--text-primary);">Select All</label>
                        </div>
                        <span class="text-sm" id="selectedCount" style="color: var(--text-secondary);">0 users selected</span>
                    </div>

                    <div class="flex gap-3">
                        <button id="deleteSelectedBtn" class="glass-action-btn glass-action-danger disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-trash-alt mr-2"></i>
                            Delete Selected
                        </button>
                        <button id="deleteAllBtn" class="glass-action-btn glass-action-danger">
                            <i class="fas fa-trash-alt mr-2"></i>
                            Delete All Users
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="usersGrid">
            @forelse($users as $user)
                <div class="user-card-modern" data-role="{{ $user->role }}" data-status="{{ $user->is_banned ? 'banned' : 'active' }}" data-user-id="{{ $user->id }}">
                    <!-- Card Header with Gradient -->
                    @php
                        $roleGradients = [
                            'admin' => 'from-amber-500/20 via-orange-500/15 to-transparent',
                            'kurikulum' => 'from-blue-500/20 via-indigo-500/15 to-transparent',
                            'kepala_sekolah' => 'from-purple-500/20 via-pink-500/15 to-transparent',
                            'siswa' => 'from-emerald-500/20 via-teal-500/15 to-transparent'
                        ];
                        $roleIcons = [
                            'admin' => 'fa-crown',
                            'kurikulum' => 'fa-book-reader',
                            'kepala_sekolah' => 'fa-user-tie',
                            'siswa' => 'fa-graduation-cap'
                        ];
                        $roleIconColors = [
                            'admin' => 'text-amber-400',
                            'kurikulum' => 'text-blue-400',
                            'kepala_sekolah' => 'text-purple-400',
                            'siswa' => 'text-emerald-400'
                        ];
                        $gradient = $roleGradients[$user->role] ?? 'from-gray-500/20 via-slate-500/15 to-transparent';
                        $roleIcon = $roleIcons[$user->role] ?? 'fa-user';
                        $iconColor = $roleIconColors[$user->role] ?? 'text-gray-400';
                    @endphp

                    <div class="card-header-gradient bg-gradient-to-b {{ $gradient }}">
                        <!-- Selection Checkbox -->
                        <div class="absolute top-3 left-3 z-10">
                            <label class="custom-checkbox">
                                <input type="checkbox"
                                       class="user-checkbox"
                                       value="{{ $user->id }}"
                                       data-user-id="{{ $user->id }}"
                                       data-user-name="{{ $user->name }}"
                                       {{ $user->id === auth()->id() || $user->role === 'admin' ? 'disabled' : '' }}>
                                <span class="checkmark-custom">
                                    <i class="fas fa-check"></i>
                                </span>
                            </label>
                        </div>

                        <!-- User Avatar Section -->
                        <div class="text-center pt-8 pb-4">
                            <div class="user-avatar-professional mx-auto mb-3 relative">
                                <div class="avatar-ring {{ $iconColor }}"></div>
                                <div class="avatar-content">
                                    <span class="avatar-letter">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                </div>
                                <div class="avatar-role-icon {{ $iconColor }}">
                                    <i class="fas {{ $roleIcon }}"></i>
                                </div>
                            </div>

                            <h4 class="user-name" title="{{ $user->name }}">{{ $user->name }}</h4>
                            <p class="user-email" title="{{ $user->email }}">
                                <i class="fas fa-envelope mr-1.5"></i>
                                {{ $user->email }}
                            </p>
                            <span class="user-id">#{{ str_pad($user->id, 4, '0', STR_PAD_LEFT) }}</span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body-content">
                        <!-- Status and Role Section -->
                        <div class="info-section">
                            <div class="flex items-center justify-between mb-3">
                                <!-- Status Badge -->
                                @if($user->is_banned)
                                    <span class="status-badge status-banned">
                                        <i class="fas fa-ban"></i>
                                        <span>Banned</span>
                                    </span>
                                @else
                                    <span class="status-badge status-active">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Active</span>
                                    </span>
                                @endif

                                <!-- Role Badge -->
                                <span class="role-badge role-{{ $user->role }}">
                                    <i class="fas {{ $roleIcon }}"></i>
                                    <span>{{ ucfirst(str_replace('_', ' ', $user->role)) }}</span>
                                </span>
                            </div>

                            <!-- Class Info for Students -->
                            @if($user->role === 'siswa' && $user->class)
                                <div class="class-info-box">
                                    <i class="fas fa-school"></i>
                                    <span>{{ $user->class->nama_kelas }}</span>
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
                                    <div class="stat-label">Joined</div>
                                    <div class="stat-value">{{ $user->created_at ? $user->created_at->diffForHumans() : 'N/A' }}</div>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-label">Last Login</div>
                                    <div class="stat-value">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons-grid">
                            <a href="{{ route('web-users.show', $user->id) }}" class="action-btn action-btn-view" title="View Details">
                                <i class="fas fa-eye"></i>
                                <span>View</span>
                            </a>
                            <a href="{{ route('web-users.edit', $user->id) }}" class="action-btn action-btn-edit" title="Edit User">
                                <i class="fas fa-edit"></i>
                                <span>Edit</span>
                            </a>
                            @if(!$user->is_banned)
                                <form method="POST" action="{{ route('web-users.destroy', $user->id) }}" class="inline-block w-full">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-btn action-btn-delete w-full" title="Delete User"
                                            onclick="return confirm('Are you sure you want to delete {{ $user->name }}?')">
                                        <i class="fas fa-trash-alt"></i>
                                        <span>Delete</span>
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('web-users.restore', $user->id) }}" class="inline-block w-full">
                                    @csrf
                                    <button type="submit" class="action-btn action-btn-restore w-full" title="Restore User">
                                        <i class="fas fa-undo"></i>
                                        <span>Restore</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <div class="glass-morphism-card">
                        <div class="p-12 text-center">
                            <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-linear-to-br from-slate-500/20 to-slate-600/20 flex items-center justify-center">
                                <i class="fas fa-users text-4xl" style="color: var(--text-secondary);"></i>
                            </div>
                            <h3 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">No Users Found</h3>
                            <p class="mb-8 max-w-md mx-auto" style="color: var(--text-secondary);">Get started by creating your first user account to begin managing your educational platform.</p>
                            <a href="{{ route('web-users.create') }}" class="glass-action-button group inline-flex">
                                <div class="p-3 rounded-xl bg-linear-to-br from-blue-500/20 to-blue-600/20 border border-blue-400/20">
                                    <i class="fas fa-plus text-blue-300 text-xl"></i>
                                </div>
                                <div>
                                    <div class="font-semibold" style="color: var(--text-primary);">Add New User</div>
                                    <div class="text-sm" style="color: var(--text-secondary);">Create account</div>
                                </div>
                                <i class="fas fa-arrow-right transition-colors duration-300" style="color: var(--text-secondary);"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Modern Pagination -->
        @if($users->hasPages())
            <div class="glass-morphism-card">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle text-blue-400"></i>
                            <span>Showing <span class="font-semibold" style="color: var(--text-primary);">{{ $users->firstItem() }}</span> to
                            <span class="font-semibold" style="color: var(--text-primary);">{{ $users->lastItem() }}</span> of
                            <span class="font-semibold" style="color: var(--text-primary);">{{ $users->total() }}</span> users</span>
                        </div>
                        <div class="pagination-controls">
                            {{ $users->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced Search Functionality
    const searchInput = document.getElementById('searchInput');
    const usersGrid = document.getElementById('usersGrid');
    let searchTimeout;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const filter = this.value.toLowerCase().trim();

            searchTimeout = setTimeout(() => {
                const userCards = usersGrid.querySelectorAll('.user-card-modern');
                let visibleCount = 0;

                userCards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    const shouldShow = !filter || text.includes(filter);
                    card.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) visibleCount++;
                });

                updateSearchInfo(visibleCount, filter);
            }, 300);
        });
    }

    // Filter Buttons
    const filterButtons = document.querySelectorAll('.glass-filter-btn');
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.dataset.filter;

            // Update active state
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            // Filter cards
            const userCards = usersGrid.querySelectorAll('.user-card-modern');
            userCards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = '';
                } else {
                    const cardRole = card.dataset.role;
                    card.style.display = (cardRole === filter) ? '' : 'none';
                }
            });
        });
    });

    // Search info update
    function updateSearchInfo(count, filter) {
        let infoElement = document.querySelector('.search-info');
        if (!infoElement && searchInput) {
            infoElement = document.createElement('div');
            infoElement.className = 'search-info text-sm mt-4 px-4 py-2 bg-white/5 rounded-lg backdrop-blur-sm border border-white/10';
            infoElement.style.color = 'var(--text-secondary)';
            searchInput.parentNode.appendChild(infoElement);
        }

        if (infoElement) {
            if (filter) {
                infoElement.textContent = `Found ${count} user${count !== 1 ? 's' : ''} matching "${filter}"`;
                infoElement.style.display = 'block';
            } else {
                infoElement.style.display = 'none';
            }
        }
    }

    // Bulk Selection Functionality
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectAllCheckbox = document.getElementById('selectAll');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const deleteAllBtn = document.getElementById('deleteAllBtn');

    console.log('=== BULK SELECTION DEBUG ===');
    console.log('bulkActionsBar:', bulkActionsBar);
    console.log('selectAllCheckbox:', selectAllCheckbox);
    console.log('userCheckboxes found:', userCheckboxes.length);
    console.log('selectedCount:', selectedCount);
    console.log('deleteSelectedBtn:', deleteSelectedBtn);
    console.log('deleteAllBtn:', deleteAllBtn);

    // CSRF token for AJAX requests
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // Update bulk actions bar visibility and selected count
    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked:not([disabled])');
        const totalChecked = checkedBoxes.length;

        if (bulkActionsBar && selectedCount && deleteSelectedBtn) {
            if (totalChecked > 0) {
                bulkActionsBar.style.display = 'block';
                selectedCount.textContent = `${totalChecked} user${totalChecked !== 1 ? 's' : ''} selected`;
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
            userCheckboxes.forEach(checkbox => {
                if (!checkbox.disabled) {
                    checkbox.checked = isChecked;
                }
            });
            updateBulkActions();
        });
    }

    // Individual checkbox change
    userCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('Individual checkbox changed:', this.value, 'checked:', this.checked);

            // Update label styling
            const label = this.closest('label');
            if (label) {
                if (this.checked) {
                    label.classList.add('checked');
                } else {
                    label.classList.remove('checked');
                }
            }

            const totalCheckboxes = document.querySelectorAll('.user-checkbox:not([disabled])').length;
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked:not([disabled])').length;

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
            const selectedUsers = document.querySelectorAll('.user-checkbox:checked:not([disabled])');

            console.log('=== DELETE SELECTED DEBUG ===');
            console.log('Selected users count:', selectedUsers.length);
            console.log('Selected checkboxes:', selectedUsers);

            if (selectedUsers.length === 0) {
                showNotification('error', 'No users selected. Please select users to delete.');
                return;
            }

            const userNames = Array.from(selectedUsers).map(cb => cb.dataset.userName).join(', ');
            const userIds = Array.from(selectedUsers).map(cb => cb.value);

            console.log('User IDs to delete:', userIds);
            console.log('User names:', userNames);
            console.log('CSRF Token:', csrfToken);

            if (confirm(`Are you sure you want to delete ${selectedUsers.length} selected user(s)?\n\nUsers: ${userNames}\n\nThis action cannot be undone.`)) {
                // Show loading state
                deleteSelectedBtn.disabled = true;
                deleteSelectedBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';

                console.log('Sending DELETE request to:', '{{ route("web-users.bulk-delete") }}');
                console.log('Request body:', { user_ids: userIds });

                // Send AJAX request
                fetch('{{ route("web-users.bulk-delete.post") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ user_ids: userIds })
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

                        // Remove deleted user cards with animation
                        selectedUsers.forEach(checkbox => {
                            const card = checkbox.closest('.user-card-modern');
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
                        const remainingCards = document.querySelectorAll('.user-card-modern').length;
                        if (remainingCards === 0) {
                            setTimeout(() => window.location.reload(), 2000);
                        }
                    } else {
                        showNotification('error', data.message || 'Failed to delete users');
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
            const totalUsers = document.querySelectorAll('.user-checkbox:not([disabled])').length;

            if (totalUsers === 0) {
                showNotification('error', 'No users available to delete.');
                return;
            }

            if (confirm(`Are you sure you want to delete ALL ${totalUsers} users?\n\nThis will delete all users except administrators and the current user.\n\nThis action cannot be undone.`)) {
                // Show loading state
                deleteAllBtn.disabled = true;
                deleteAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';

                // Send AJAX request
                fetch('{{ route("web-users.bulk-delete-all.post") }}', {
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
                        showNotification('error', data.message || 'Failed to delete all users');
                    }
                })
                .catch(error => {
                    console.error('Bulk delete all error:', error);
                    showNotification('error', 'Network error occurred during deletion');
                })
                .finally(() => {
                    // Reset button state
                    deleteAllBtn.disabled = false;
                    deleteAllBtn.innerHTML = '<i class="fas fa-trash-alt mr-2"></i>Delete All Users';
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
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

/* Professional User Card - NEW DESIGN */
.user-card-modern {
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

.user-card-modern:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    border-color: rgba(255, 255, 255, 0.2);
}

/* Card Header with Gradient */
.card-header-gradient {
    position: relative;
    padding: 1.5rem;
    padding-top: 3rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

/* Custom Checkbox */
.custom-checkbox {
    position: relative;
    display: inline-block;
    cursor: pointer;
}

.custom-checkbox input[type="checkbox"] {
    opacity: 0;
    position: absolute;
    width: 0;
    height: 0;
}

.checkmark-custom {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.custom-checkbox input[type="checkbox"]:checked ~ .checkmark-custom {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.custom-checkbox input[type="checkbox"]:disabled ~ .checkmark-custom {
    opacity: 0.4;
    cursor: not-allowed;
}

.checkmark-custom i {
    color: white;
    font-size: 12px;
    opacity: 0;
    transform: scale(0);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.custom-checkbox input[type="checkbox"]:checked ~ .checkmark-custom i {
    opacity: 1;
    transform: scale(1);
}

.custom-checkbox:hover .checkmark-custom {
    border-color: rgba(255, 255, 255, 0.5);
    background: rgba(255, 255, 255, 0.1);
}

/* Professional Avatar */
.user-avatar-professional {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto;
}

.avatar-ring {
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    background: linear-gradient(135deg, currentColor 0%, transparent 100%);
    opacity: 0.3;
    animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse-ring {
    0%, 100% {
        opacity: 0.3;
        transform: scale(1);
    }
    50% {
        opacity: 0.5;
        transform: scale(1.05);
    }
}

.avatar-content {
    position: relative;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.8) 0%, rgba(37, 99, 235, 0.9) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid var(--card-bg);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

.avatar-letter {
    font-size: 2rem;
    font-weight: 700;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.avatar-role-icon {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--card-bg);
    border: 2px solid var(--card-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

/* User Info Text */
.user-name {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0.75rem 0 0.375rem 0;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 0 1rem;
}

.user-email {
    font-size: 0.875rem;
    color: var(--text-secondary);
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 0 1rem;
    margin: 0;
}

.user-id {
    display: inline-block;
    margin-top: 0.5rem;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 9999px;
}

/* Card Body */
.card-body-content {
    padding: 1.5rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Info Section */
.info-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid;
    width: fit-content;
}

.status-active {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.status-banned {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Role Badge */
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid;
    width: fit-content;
}

.role-admin {
    background: rgba(251, 191, 36, 0.1);
    border-color: rgba(251, 191, 36, 0.3);
    color: #fbbf24;
}

.role-kurikulum {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

.role-kepala_sekolah {
    background: rgba(168, 85, 247, 0.1);
    border-color: rgba(168, 85, 247, 0.3);
    color: #a855f7;
}

.role-siswa {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
    color: #10b981;
}

/* Class Info Box */
.class-info-box {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(148, 163, 184, 0.1);
    border: 1px solid rgba(148, 163, 184, 0.2);
    color: var(--text-secondary);
    width: fit-content;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
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

/* Action Buttons Grid */
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

.action-btn-edit {
    color: #f59e0b;
}

.action-btn-edit:hover {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
}

.action-btn-delete {
    color: #ef4444;
}

.action-btn-delete:hover {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
}

.action-btn-restore {
    color: #22c55e;
}

.action-btn-restore:hover {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
}

.user-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    min-height: 280px;
    display: flex;
    flex-direction: column;
}

.user-card:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.3);
}

.user-avatar-large {
    width: 60px;
    height: 60px;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

/* Other Glass Components */
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

.glass-role-badge {
    padding: 0.5rem 1rem;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    backdrop-filter: blur(10px);
    border: 1px solid;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    width: fit-content;
}

.glass-class-info {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
    background: rgba(148, 163, 184, 0.1);
    border: 1px solid rgba(148, 163, 184, 0.2);
    backdrop-filter: blur(10px);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.glass-stat-mini {
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-align: center;
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
    color: var(--text-secondary);
    background: var(--card-bg);
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

.glass-action-danger {
    padding: 0.5rem 1rem;
    height: auto;
    width: auto;
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.glass-action-danger:hover:not(:disabled) {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.4);
    color: #f87171;
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

.glass-checkbox {
    width: 2rem;
    height: 2rem;
    border-radius: 0.25rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: var(--input-bg);
    backdrop-filter: blur(10px);
    transition: all 0.2s ease;
    cursor: pointer;
    appearance: none;
    position: relative;
}

.glass-checkbox:checked {
    background: rgba(59, 130, 246, 0.8);
    border-color: rgba(59, 130, 246, 0.8);
}

.glass-checkbox:checked + .checkmark {
    opacity: 1;
}

.glass-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.checkbox-label.checked {
    background: rgba(59, 130, 246, 0.3) !important;
    border-color: rgba(59, 130, 246, 0.5) !important;
}

.checkmark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 1rem;
    font-weight: bold;
    pointer-events: none;
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

@keyframes fadeOut {
    from {
        opacity: 1;
        transform: scale(1);
    }
    to {
        opacity: 0;
        transform: scale(0.95);
    }
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .glass-morphism-card {
        margin: 0 1rem;
    }

    .user-card-modern,
    .user-card {
        margin: 0 0.5rem;
        min-height: 240px;
    }

    .user-avatar-professional {
        width: 70px;
        height: 70px;
    }

    .avatar-content {
        width: 70px;
        height: 70px;
    }

    .avatar-letter {
        font-size: 1.75rem;
    }

    .avatar-role-icon {
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
    }

    .user-name {
        font-size: 1rem;
    }

    .user-email {
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

    .user-avatar-large {
        width: 50px;
        height: 50px;
    }

    .glass-role-badge,
    .glass-class-info {
        font-size: 0.75rem;
    }

    .grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.xl\\:grid-cols-4 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
}

@media (min-width: 640px) and (max-width: 1023px) {
    .grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.xl\\:grid-cols-4 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
@endsection
