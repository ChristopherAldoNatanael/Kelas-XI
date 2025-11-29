@extends('layouts.app')

@section('title', 'Create User')

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
                            Create New User
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Add a new user to the system with appropriate permissions and access control. Ensure all information is accurate and secure.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ \App\Models\User::count() }}</div>
                            <div class="text-white/70 text-sm">Total Users</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ \App\Models\User::where('role', 'admin')->count() }}</div>
                            <div class="text-white/70 text-sm">Administrators</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ \App\Models\User::where('role', 'siswa')->count() }}</div>
                            <div class="text-white/70 text-sm">Students</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <a href="{{ route('web-users.index') }}" class="glass-action-button group">
                        <div class="p-3 rounded-xl bg-gradient-to-br from-slate-500/20 to-slate-600/20 border border-slate-400/20">
                            <i class="fas fa-arrow-left text-slate-300 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Back to Users</div>
                            <div class="text-slate-300 text-sm">View all users</div>
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
                    <h2 class="text-3xl font-bold text-white mb-3">User Information</h2>
                    <p class="text-slate-300 text-lg">Fill in the details to create a new user account with appropriate permissions</p>
                </div>

                <form method="POST" action="{{ route('web-users.store') }}" class="space-y-8" id="createUserForm">
                    @csrf

                    <!-- Personal Information Section -->
                    <div class="glass-form-section">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-4 rounded-xl bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-400/20">
                                <i class="fas fa-user text-blue-300 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Personal Information</h3>
                                <p class="text-slate-300">Basic user details and contact information</p>
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
                                        placeholder="Enter full name"
                                        required>
                                @error('nama')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group">
                                <label for="email" class="glass-form-label">
                                    <i class="fas fa-envelope mr-2 text-green-400"></i>
                                    Email Address <span class="text-red-400">*</span>
                                </label>
                                <input type="email"
                                        class="glass-form-input @error('email') glass-form-error @enderror"
                                        id="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        placeholder="Enter email address"
                                        required>
                                @error('email')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Security Section -->
                    <div class="glass-form-section">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-4 rounded-xl bg-gradient-to-br from-red-500/20 to-red-600/20 border border-red-400/20">
                                <i class="fas fa-shield-alt text-red-300 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Security Settings</h3>
                                <p class="text-slate-300">Set up password and security credentials</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="glass-form-group">
                                <label for="password" class="glass-form-label">
                                    <i class="fas fa-lock mr-2 text-red-400"></i>
                                    Password <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password"
                                            class="glass-form-input @error('password') glass-form-error @enderror pr-12"
                                            id="password"
                                            name="password"
                                            placeholder="Enter password"
                                            required>
                                    <button type="button" id="toggle-password" class="glass-password-toggle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                @error('password')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group">
                                <label for="password_confirmation" class="glass-form-label">
                                    <i class="fas fa-check-double mr-2 text-orange-400"></i>
                                    Confirm Password <span class="text-red-400">*</span>
                                </label>
                                <input type="password"
                                        class="glass-form-input"
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        placeholder="Confirm password"
                                        required>
                            </div>
                        </div>
                    </div>

                    <!-- Role & Permissions Section -->
                    <div class="glass-form-section">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="p-4 rounded-xl bg-gradient-to-br from-purple-500/20 to-purple-600/20 border border-purple-400/20">
                                <i class="fas fa-user-shield text-purple-300 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Role & Permissions</h3>
                                <p class="text-slate-300">Define user role and access permissions</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="glass-form-group">
                                <label for="role" class="glass-form-label">
                                    <i class="fas fa-crown mr-2 text-purple-400"></i>
                                    Role <span class="text-red-400">*</span>
                                </label>
                                <select class="glass-form-select @error('role') glass-form-error @enderror" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>
                                        Administrator
                                    </option>
                                    <option value="kurikulum" {{ old('role') == 'kurikulum' ? 'selected' : '' }}>
                                        Kurikulum
                                    </option>
                                    <option value="kepala_sekolah" {{ old('role') == 'kepala_sekolah' ? 'selected' : '' }}>
                                        Kepala Sekolah
                                    </option>
                                    <option value="siswa" {{ old('role') == 'siswa' ? 'selected' : '' }}>
                                        Siswa
                                    </option>
                                </select>
                                @error('role')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="glass-form-group" id="class-field" style="display: {{ old('role') == 'siswa' ? 'block' : 'none' }};">
                                <label for="class_id" class="glass-form-label">
                                    <i class="fas fa-school mr-2 text-indigo-400"></i>
                                    Kelas <span class="text-red-400">*</span>
                                </label>
                                <select class="glass-form-select @error('class_id') glass-form-error @enderror" id="class_id" name="class_id" {{ old('role') == 'siswa' ? 'required' : '' }}>
                                    <option value="">Pilih Kelas</option>
                                    @foreach($classes ?? [] as $class)
                                        <option value="{{ $class->id }}" {{ old('class_id') == $class->id ? 'selected' : '' }}>
                                            {{ $class->nama_kelas }} (Level {{ $class->level }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('class_id')
                                    <div class="glass-error-message">{{ $message }}</div>
                                @enderror
                                <div class="glass-help-text">Pilih kelas untuk siswa (X RPL, XI RPL, atau XII RPL)</div>
                            </div>

                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row justify-end gap-4 pt-8 border-t border-white/10">
                        <a href="{{ route('web-users.index') }}" class="glass-action-button glass-action-secondary">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </a>
                        <button type="submit" class="glass-action-button glass-action-primary">
                            <i class="fas fa-save mr-2"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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


    // Enhanced password toggle with animation
    const togglePassword = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('password');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');

            // Add rotation animation
            this.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                this.style.transform = 'rotate(0deg)';
            }, 300);
        });
    }

    // Real-time password confirmation validation
    const passwordConfirmation = document.getElementById('password_confirmation');
    if (passwordConfirmation) {
        passwordConfirmation.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = this.value;

            if (password !== '' && confirmPassword !== '' && password !== confirmPassword) {
                this.style.borderColor = 'rgba(239, 68, 68, 0.5)';
                this.style.transform = 'translateX(3px)';
                setTimeout(() => {
                    this.style.transform = 'translateX(0)';
                }, 200);
            } else {
                this.style.borderColor = 'rgba(34, 197, 94, 0.5)';
            }
        });
    }

    // Enhanced form validation with visual feedback
    const form = document.getElementById('createUserForm');
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

    // Form submission with AJAX
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            // Set loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating User...';
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');

            // Prepare form data
            const formData = new FormData(form);

            // Send AJAX request
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message || 'User created successfully!',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = '{{ route("web-users.index") }}';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to create user.',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                let errorMessage = 'An unexpected error occurred.';

                // Try to parse validation errors
                if (error.response && error.response.data && error.response.data.errors) {
                    const errors = error.response.data.errors;
                    errorMessage = Object.values(errors).flat().join('\n');
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMessage,
                    confirmButtonText: 'OK'
                });
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            });
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

    // Enhanced Role-based field visibility with animation
    const roleSelect = document.getElementById('role');
    const classField = document.getElementById('class-field');
    const classSelect = document.getElementById('class_id');

    roleSelect.addEventListener('change', function() {
        if (this.value === 'siswa') {
            classField.style.display = 'block';
            classField.style.animation = 'slideDown 0.4s ease-out';
            classSelect.required = true;
        } else {
            classField.style.animation = 'slideUp 0.4s ease-out';
            setTimeout(() => {
                classField.style.display = 'none';
            }, 400);
            classSelect.required = false;
            classSelect.value = '';
        }
    });

    // Trigger on page load if role is already selected
    if (roleSelect.value === 'siswa') {
        classField.style.display = 'block';
        classSelect.required = true;
    }

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

.glass-password-toggle {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.375rem;
    border-radius: 0.25rem;
    transition: all 0.2s ease;
}

.glass-password-toggle:hover {
    color: #e2e8f0;
    background: rgba(255, 255, 255, 0.1);
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

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-15px);
        max-height: 0;
    }
    to {
        opacity: 1;
        transform: translateY(0);
        max-height: 200px;
    }
}

@keyframes slideUp {
    from {
        opacity: 1;
        transform: translateY(0);
        max-height: 200px;
    }
    to {
        opacity: 0;
        transform: translateY(-15px);
        max-height: 0;
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
</style>
@endsection
