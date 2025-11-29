@extends('layouts.app')

@section('title', 'Create Teacher')

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
                        <span class="text-white/90 text-sm font-medium">System Online</span>
                    </div>

                    <div>
                        <h1 class="text-4xl md:text-5xl font-bold text-white mb-3 tracking-tight">
                            Create New Teacher
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Add a new teacher to the system with appropriate credentials and professional information. Ensure all details are accurate and complete.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ \App\Models\Teacher::count() }}</div>
                            <div class="text-white/70 text-sm">Total Teachers</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ \App\Models\Teacher::where('status', 'active')->count() }}</div>
                            <div class="text-white/70 text-sm">Active Teachers</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ \App\Models\Teacher::where('status', 'retired')->count() }}</div>
                            <div class="text-white/70 text-sm">Retired Teachers</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <a href="{{ route('web-teachers.index') }}" class="glass-action-button group">
                        <div class="p-3 rounded-xl bg-gradient-to-br from-slate-500/20 to-slate-600/20 border border-slate-400/20">
                            <i class="fas fa-arrow-left text-slate-300 text-xl"></i>
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

    <!-- Form Container -->
    <div class="px-6 space-y-8">
        <!-- Alert Messages -->
        @if(session('success'))
            <div class="glass-notification glass-notification-success">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-green-500/20">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <span class="text-white">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="glass-notification glass-notification-error">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-red-500/20">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                    </div>
                    <span class="text-white">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <!-- Main Form Card -->
        <div class="glass-morphism-card">
            <div class="p-8">
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-white mb-3">Teacher Information</h2>
                    <p class="text-slate-300 text-lg">Enter complete teacher information to create a new teacher record</p>
                </div>

                <form method="POST" action="{{ route('web-teachers.store') }}" class="space-y-8" id="createTeacherForm">
                    @csrf

                    <!-- Name Input Section -->
                    <!-- Basic Information Section -->
                    <div class="glass-form-section">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-4 rounded-xl bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-400/20">
                                <i class="fas fa-user text-blue-300 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Basic Information</h3>
                                <p class="text-slate-300">Essential details for the new teacher</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="glass-form-group">
                                <label for="nama" class="glass-form-label">
                                    <i class="fas fa-user mr-2 text-blue-400"></i>
                                    Full Name <span class="text-red-400">*</span>
                                </label>
                                <input type="text"
                                       class="glass-form-input @error('nama') glass-form-error @enderror"
                                       id="nama"
                                       name="nama"
                                       value="{{ old('nama') }}"
                                       placeholder="Enter teacher's full name"
                                       required
                                       maxlength="255">
                                @error('nama')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group">
                                <label for="nip" class="glass-form-label">
                                    <i class="fas fa-id-card mr-2 text-green-400"></i>
                                    NIP <span class="text-red-400">*</span>
                                </label>
                                <input type="text"
                                       class="glass-form-input @error('nip') glass-form-error @enderror"
                                       id="nip"
                                       name="nip"
                                       value="{{ old('nip') }}"
                                       placeholder="National Identity Number"
                                       required
                                       maxlength="255">
                                @error('nip')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group">
                                <label for="teacher_code" class="glass-form-label">
                                    <i class="fas fa-hashtag mr-2 text-purple-400"></i>
                                    Teacher Code <span class="text-red-400">*</span>
                                </label>
                                <input type="text"
                                       class="glass-form-input @error('teacher_code') glass-form-error @enderror"
                                       id="teacher_code"
                                       name="teacher_code"
                                       value="{{ old('teacher_code') }}"
                                       placeholder="Unique teacher code"
                                       required
                                       maxlength="255">
                                @error('teacher_code')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group">
                                <label for="join_date" class="glass-form-label">
                                    <i class="fas fa-calendar mr-2 text-orange-400"></i>
                                    Join Date <span class="text-red-400">*</span>
                                </label>
                                <input type="date"
                                       class="glass-form-input @error('join_date') glass-form-error @enderror"
                                       id="join_date"
                                       name="join_date"
                                       value="{{ old('join_date', date('Y-m-d')) }}"
                                       required>
                                @error('join_date')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information Section -->
                    <div class="glass-form-section">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-4 rounded-xl bg-gradient-to-br from-green-500/20 to-green-600/20 border border-green-400/20">
                                <i class="fas fa-graduation-cap text-green-300 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Professional Information</h3>
                                <p class="text-slate-300">Teaching position and department details</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="glass-form-group">
                                <label for="position" class="glass-form-label">
                                    <i class="fas fa-briefcase mr-2 text-green-400"></i>
                                    Position <span class="text-red-400">*</span>
                                </label>
                                <input type="text"
                                       class="glass-form-input @error('position') glass-form-error @enderror"
                                       id="position"
                                       name="position"
                                       value="{{ old('position') }}"
                                       placeholder="e.g., Mathematics Teacher, Principal"
                                       required
                                       maxlength="255">
                                @error('position')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group">
                                <label for="department" class="glass-form-label">
                                    <i class="fas fa-building mr-2 text-indigo-400"></i>
                                    Department <span class="text-red-400">*</span>
                                </label>
                                <input type="text"
                                       class="glass-form-input @error('department') glass-form-error @enderror"
                                       id="department"
                                       name="department"
                                       value="{{ old('department') }}"
                                       placeholder="e.g., Mathematics, Science, Administration"
                                       required
                                       maxlength="255">
                                @error('department')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group">
                                <label for="expertise" class="glass-form-label">
                                    <i class="fas fa-star mr-2 text-yellow-400"></i>
                                    Expertise
                                </label>
                                <input type="text"
                                       class="glass-form-input @error('expertise') glass-form-error @enderror"
                                       id="expertise"
                                       name="expertise"
                                       value="{{ old('expertise') }}"
                                       placeholder="e.g., Algebra, Physics, Programming"
                                       maxlength="255">
                                @error('expertise')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group">
                                <label for="certification" class="glass-form-label">
                                    <i class="fas fa-certificate mr-2 text-cyan-400"></i>
                                    Certification
                                </label>
                                <input type="text"
                                       class="glass-form-input @error('certification') glass-form-error @enderror"
                                       id="certification"
                                       name="certification"
                                       value="{{ old('certification') }}"
                                       placeholder="e.g., M.Ed., Ph.D., Microsoft Certified"
                                       maxlength="255">
                                @error('certification')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group md:col-span-2">
                                <label for="status" class="glass-form-label">
                                    <i class="fas fa-toggle-on mr-2 text-pink-400"></i>
                                    Status <span class="text-red-400">*</span>
                                </label>
                                <select class="glass-form-input @error('status') glass-form-error @enderror"
                                        id="status"
                                        name="status"
                                        required>
                                    <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>
                                        🟢 Active - Teacher is currently employed
                                    </option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>
                                        🟡 Inactive - Temporarily not teaching
                                    </option>
                                    <option value="retired" {{ old('status') == 'retired' ? 'selected' : '' }}>
                                        🔴 Retired - No longer employed
                                    </option>
                                </select>
                                @error('status')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row justify-end gap-4 pt-8 border-t border-white/10">
                        <a href="{{ route('web-teachers.index') }}" class="glass-action-button glass-action-secondary">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </a>
                        <button type="submit" class="glass-action-button glass-action-primary">
                            <i class="fas fa-save mr-2"></i>Create Teacher
                        </button>
                    </div>
                </form>
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

    // Enhanced form validation with visual feedback
    const form = document.getElementById('createTeacherForm');
    const inputs = document.querySelectorAll('.glass-form-input, .glass-form-select');

    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });

        input.addEventListener('input', function() {
            if (this.classList.contains('glass-form-error')) {
                validateField(this);
            }
        });
    });

    function validateField(field) {
        const value = field.value.trim();
        const isRequired = field.hasAttribute('required');

        if (isRequired && value === '') {
            field.classList.add('glass-form-error');
            field.style.transform = 'translateX(5px)';
            setTimeout(() => {
                field.style.transform = 'translateX(0)';
            }, 150);
        } else {
            field.classList.remove('glass-form-error');
            if (value !== '') {
                field.style.borderColor = 'rgba(34, 197, 94, 0.5)';
            }
        }
    }

    // Form submission with loading state
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating Teacher...';
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
            }
        });
    }

    // Add change detection for unsaved changes warning
    let formChanged = false;
    inputs.forEach(input => {
        const initialValue = input.value;
        input.addEventListener('input', function() {
            formChanged = (this.value !== initialValue);
        });
    });

    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    // Smooth animations for form sections
    const formSections = document.querySelectorAll('.glass-form-section');
    formSections.forEach((section, index) => {
        setTimeout(() => {
            section.style.animation = 'fadeInUp 0.6s ease-out forwards';
            section.style.opacity = '0';
            section.style.transform = 'translateY(20px)';

            setTimeout(() => {
                section.style.opacity = '1';
                section.style.transform = 'translateY(0)';
            }, 100);
        }, index * 150);
    });
});
</script>

<style>
/* Glass Morphism Form Components */
.glass-form-section {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    padding: 2rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.glass-form-section:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.glass-form-group {
    position: relative;
}

.glass-form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #e2e8f0;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.glass-form-input,
.glass-form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    color: white;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.glass-form-input:focus,
.glass-form-select:focus {
    outline: none;
    border-color: rgba(59, 130, 246, 0.5);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.glass-form-input::placeholder,
.glass-form-select::placeholder {
    color: #94a3b8;
}

.glass-form-error {
    border-color: rgba(239, 68, 68, 0.5) !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}

.glass-error-message {
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: #ef4444;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.glass-error-message::before {
    content: '⚠';
    font-size: 0.875rem;
}

.glass-help-text {
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

/* Action Buttons */
.glass-action-button {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid;
    backdrop-filter: blur(10px);
}

.glass-action-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border-color: rgba(59, 130, 246, 0.3);
    color: white;
}

.glass-action-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
}

.glass-action-secondary {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

.glass-action-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    color: white;
    transform: translateY(-2px);
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
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
    border-radius: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Responsive Design */
@media (max-width: 768px) {
    .glass-form-section {
        padding: 1.5rem;
        margin: 0 0.5rem;
    }

    .glass-form-input,
    .glass-form-select {
        font-size: 16px; /* Prevent zoom on iOS */
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
    .glass-form-section {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .glass-form-input,
    .glass-form-select {
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid rgba(0, 0, 0, 0.1);
        color: #1f2937;
    }

    .glass-form-input::placeholder,
    .glass-form-select::placeholder {
        color: #6b7280;
    }

    .glass-form-label {
        color: #374151;
    }
}

/* Glass Stat Cards */
.glass-stat-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    padding: 0.75rem;
    text-align: center;
    min-width: 80px;
}

/* Glass Notifications */
.glass-notification {
    padding: 1rem;
    border-radius: 0.5rem;
    backdrop-filter: blur(10px);
    border: 1px solid;
    margin-bottom: 1rem;
}

.glass-notification-success {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.2);
}

.glass-notification-error {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.2);
}

/* Glass Morphism Card */
.glass-morphism-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
}
</style>
@endsection
