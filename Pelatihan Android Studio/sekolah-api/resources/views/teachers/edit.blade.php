@extends('layouts.app')

@section('title', 'Edit Teacher - ' . $teacher->nama)

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
                        <div class="w-2 h-2 bg-blue-400 rounded-full animate-pulse"></div>
                        <span class="text-white/90 text-sm font-medium">Editing Teacher</span>
                    </div>

                    <div>
                        <h1 class="text-4xl md:text-5xl font-bold text-white mb-3 tracking-tight">
                            Edit Teacher Profile
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Update {{ $teacher->nama }}'s information, professional details, and account settings. Make changes carefully to ensure data integrity.
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
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <div class="flex flex-col gap-4">
                        <a href="{{ route('web-teachers.show', $teacher->id) }}" class="glass-action-button group">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-green-500/20 to-green-600/20 border border-green-400/20">
                                <i class="fas fa-eye text-green-300 text-xl"></i>
                            </div>
                            <div>
                                <div class="text-white font-semibold">View Profile</div>
                                <div class="text-slate-300 text-sm">See full details</div>
                            </div>
                        </a>

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

        @if($errors->any())
            <div class="glass-notification glass-notification-error">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-red-500/20">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                    </div>
                    <div class="flex-1">
                        <div class="text-white font-semibold mb-2">Please fix the following errors:</div>
                        <ul class="text-red-300 text-sm space-y-1">
                            @foreach($errors->all() as $error)
                                <li>• {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <!-- Main Form Card -->
        <div class="glass-morphism-card">
            <div class="p-8">
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-white mb-3">Update Teacher Information</h2>
                    <p class="text-slate-300 text-lg">Modify {{ $teacher->nama }}'s details, professional information, and account settings</p>
                </div>

                <form method="POST" action="{{ route('web-teachers.update', $teacher->id) }}" class="space-y-8" id="editTeacherForm">
                    @csrf
                    @method('PUT')

                    <!-- Personal Information Section -->
                    <div class="glass-form-section">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-4 rounded-xl bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-400/20">
                                <i class="fas fa-user text-blue-300 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Personal Information</h3>
                                <p class="text-slate-300">Update teacher's basic details and identification</p>
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
                                         value="{{ old('nama', $teacher->nama) }}"
                                         placeholder="Enter full name"
                                         required>
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
                                         value="{{ old('nip', $teacher->nip) }}"
                                         placeholder="Enter NIP number"
                                         required>
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
                                         value="{{ old('teacher_code', $teacher->teacher_code) }}"
                                         placeholder="Enter teacher code"
                                         required>
                                @error('teacher_code')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group">
                                <label for="join_date" class="glass-form-label">
                                    <i class="fas fa-calendar-plus mr-2 text-orange-400"></i>
                                    Join Date <span class="text-red-400">*</span>
                                </label>
                                <input type="date"
                                         class="glass-form-input @error('join_date') glass-form-error @enderror"
                                         id="join_date"
                                         name="join_date"
                                         value="{{ old('join_date', $teacher->join_date ? $teacher->join_date->format('Y-m-d') : '') }}"
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
                            <div class="p-4 rounded-xl bg-gradient-to-br from-emerald-500/20 to-emerald-600/20 border border-emerald-400/20">
                                <i class="fas fa-graduation-cap text-emerald-300 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Professional Information</h3>
                                <p class="text-slate-300">Update teaching position, department, and qualifications</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="glass-form-group">
                                <label for="position" class="glass-form-label">
                                    <i class="fas fa-briefcase mr-2 text-emerald-400"></i>
                                    Position <span class="text-red-400">*</span>
                                </label>
                                <input type="text"
                                         class="glass-form-input @error('position') glass-form-error @enderror"
                                         id="position"
                                         name="position"
                                         value="{{ old('position', $teacher->position) }}"
                                         placeholder="e.g., Senior Teacher, Head of Department"
                                         required>
                                @error('position')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group">
                                <label for="department" class="glass-form-label">
                                    <i class="fas fa-building mr-2 text-teal-400"></i>
                                    Department <span class="text-red-400">*</span>
                                </label>
                                <input type="text"
                                         class="glass-form-input @error('department') glass-form-error @enderror"
                                         id="department"
                                         name="department"
                                         value="{{ old('department', $teacher->department) }}"
                                         placeholder="e.g., Mathematics, Computer Science"
                                         required>
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
                                         value="{{ old('expertise', $teacher->expertise) }}"
                                         placeholder="e.g., Calculus, Programming, Database Design">
                                @error('expertise')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                                <div class="glass-help-text">Areas of specialization (optional)</div>
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
                                         value="{{ old('certification', $teacher->certification) }}"
                                         placeholder="e.g., PhD Mathematics, Microsoft Certified">
                                @error('certification')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                                <div class="glass-help-text">Professional certifications (optional)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Status & Permissions Section -->
                    <div class="glass-form-section">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-4 rounded-xl bg-gradient-to-br from-indigo-500/20 to-indigo-600/20 border border-indigo-400/20">
                                <i class="fas fa-shield-alt text-indigo-300 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Status & Employment</h3>
                                <p class="text-slate-300">Update employment status and account permissions</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="glass-form-group">
                                <label for="status" class="glass-form-label">
                                    <i class="fas fa-toggle-on mr-2 text-indigo-400"></i>
                                    Employment Status <span class="text-red-400">*</span>
                                </label>
                                <select class="glass-form-select @error('status') glass-form-error @enderror" id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="active" {{ old('status', $teacher->status) == 'active' ? 'selected' : '' }}>
                                        Active - Currently employed
                                    </option>
                                    <option value="inactive" {{ old('status', $teacher->status) == 'inactive' ? 'selected' : '' }}>
                                        Inactive - Temporarily inactive
                                    </option>
                                    <option value="retired" {{ old('status', $teacher->status) == 'retired' ? 'selected' : '' }}>
                                        Retired - No longer employed
                                    </option>
                                </select>
                                @error('status')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group">
                                <label class="glass-form-label">
                                    <i class="fas fa-info-circle mr-2 text-slate-400"></i>
                                    Account Information
                                </label>
                                <div class="glass-info-card">
                                    <div class="space-y-3 text-sm">
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-400">Teacher ID:</span>
                                            <span class="font-mono text-white">#{{ $teacher->id }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-400">Created:</span>
                                            <span class="text-white">{{ $teacher->created_at->format('M d, Y') }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-400">Last Updated:</span>
                                            <span class="text-white">{{ $teacher->updated_at->format('M d, Y') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row justify-end gap-4 pt-8 border-t border-white/10">
                        <a href="{{ route('web-teachers.index') }}" class="glass-action-button glass-action-secondary">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </a>
                        <button type="submit" class="glass-action-button glass-action-primary">
                            <i class="fas fa-save mr-2"></i>Update Teacher
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
    const form = document.getElementById('editTeacherForm');
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
        } else if (field.type === 'email' && value !== '' && !isValidEmail(value)) {
            field.classList.add('glass-form-error');
        } else {
            field.classList.remove('glass-form-error');
            if (value !== '') {
                field.style.borderColor = 'rgba(34, 197, 94, 0.5)';
            }
        }
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Form submission with loading state
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating Teacher...';
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

    // NIP formatting (basic validation)
    const nipInput = document.getElementById('nip');
    if (nipInput) {
        nipInput.addEventListener('input', function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');
            // Limit to reasonable length
            if (this.value.length > 21) {
                this.value = this.value.slice(0, 21);
            }
        });
    }

    // Teacher code formatting
    const teacherCodeInput = document.getElementById('teacher_code');
    if (teacherCodeInput) {
        teacherCodeInput.addEventListener('input', function() {
            // Convert to uppercase and remove spaces
            this.value = this.value.toUpperCase().replace(/\s/g, '');
        });
    }

    // Auto-capitalize position and department
    const positionInput = document.getElementById('position');
    const departmentInput = document.getElementById('department');

    [positionInput, departmentInput].forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                // Auto-capitalize words
                this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
            });
        }
    });
});
</script>

<style>
/* Glass Morphism Form Components */
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
    border-color: rgba(245, 158, 11, 0.5);
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
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

.glass-info-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.5rem;
    padding: 1rem;
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
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-color: rgba(245, 158, 11, 0.3);
    color: white;
}

.glass-action-primary:hover {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(245, 158, 11, 0.3);
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
    .glass-morphism-card {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

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
    // Form elements
    const form = document.getElementById('teacherEditForm');
    const submitBtn = document.getElementById('submitBtn');

    // Input elements
    const nameInput = document.getElementById('name');
    const subjectInput = document.getElementById('mata_pelajaran');
    const statusSelect = document.getElementById('is_banned');
    const passwordInput = document.getElementById('password');

    // Counter elements
    const nameCount = document.getElementById('nameCount');
    const subjectCount = document.getElementById('subjectCount');

    // Status preview elements
    const statusPreview = document.getElementById('statusPreview');
    const statusTitle = document.getElementById('statusTitle');
    const statusDescription = document.getElementById('statusDescription');

    // Character counters
    if (nameInput && nameCount) {
        nameInput.addEventListener('input', function() {
            nameCount.textContent = this.value.length;
            updateCounterColor(nameCount, this.value.length, 100);
        });
        // Initialize counter color
        updateCounterColor(nameCount, nameInput.value.length, 100);
    }

    if (subjectInput && subjectCount) {
        subjectInput.addEventListener('input', function() {
            subjectCount.textContent = this.value.length;
            updateCounterColor(subjectCount, this.value.length, 100);
        });
        // Initialize counter color
        updateCounterColor(subjectCount, subjectInput.value.length, 100);
    }

    // Status preview update
    function updateStatusPreview() {
        const isBanned = statusSelect.value === '1';
        const statusIcon = statusPreview.querySelector('.status-icon i');

        statusPreview.className = `mt-4 p-4 rounded-lg border transition-all duration-300 ${
            isBanned
                ? 'border-red-200 bg-gradient-to-r from-red-50 to-transparent dark:border-red-700 dark:from-red-900/20'
                : 'border-green-200 bg-gradient-to-r from-green-50 to-transparent dark:border-green-700 dark:from-green-900/20'
        }`;

        statusIcon.className = `fas ${isBanned ? 'fa-ban text-red-500' : 'fa-check-circle text-green-500'}`;
        statusTitle.textContent = isBanned ? 'Banned Teacher' : 'Active Teacher';
        statusDescription.textContent = isBanned
            ? 'This teacher will lose access to the system'
            : 'This teacher has full access to the system';
    }

    statusSelect.addEventListener('change', updateStatusPreview);
    updateStatusPreview(); // Initialize

    // Update counter colors based on usage
    function updateCounterColor(element, current, max) {
        const percentage = (current / max) * 100;
        element.className = percentage > 90 ? 'text-danger-dark font-bold' :
                           percentage > 70 ? 'text-warning-dark font-medium' :
                           'text-success-dark';
    }

    // Form validation
    function validateForm() {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            const wrapper = field.closest('.form-group');
            if (!field.value.trim()) {
                wrapper?.classList.add('error');
                isValid = false;
            } else {
                wrapper?.classList.remove('error');
            }
        });

        // Email validation
        const emailInput = document.getElementById('email');
        if (emailInput.value && !isValidEmail(emailInput.value)) {
            const wrapper = emailInput.closest('.form-group');
            wrapper?.classList.add('error');
            isValid = false;
        }

        // Password validation (if provided)
        if (passwordInput.value && passwordInput.value.length < 8) {
            const wrapper = passwordInput.closest('.form-group');
            wrapper?.classList.add('error');
            isValid = false;
        }

        return isValid;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Real-time validation
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            const wrapper = this.closest('.form-group');
            if (this.hasAttribute('required') && !this.value.trim()) {
                wrapper?.classList.add('error');
            } else if (this.type === 'email' && this.value && !isValidEmail(this.value)) {
                wrapper?.classList.add('error');
            } else if (this.type === 'password' && this.value && this.value.length < 8) {
                wrapper?.classList.add('error');
            } else {
                wrapper?.classList.remove('error');
            }
        });

        input.addEventListener('input', function() {
            const wrapper = this.closest('.form-group');
            if (this.value.trim()) {
                wrapper?.classList.remove('error');
            }
        });
    });

    // Form submission
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();

            // Scroll to first error
            const firstError = form.querySelector('.form-group.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            return false;
        }

        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <div class="flex items-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Updating Teacher...
            </div>
        `;
    });

    // Auto-format inputs
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            // Auto-capitalize names
            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });
    }

    if (subjectInput) {
        subjectInput.addEventListener('input', function() {
            // Auto-capitalize subject names
            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });
    }
});

// Password toggle function
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + 'ToggleIcon');

    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'fas fa-eye';
    }
}
</script>
@endsection
