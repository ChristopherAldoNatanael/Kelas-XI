@extends('layouts.app')

@section('title', 'Create Subject')

@section('content')
<div class="min-h-screen">
    <!-- Hero Header with Glass Morphism -->
    <div class="relative overflow-hidden rounded-3xl mx-6 mb-8">
        <!-- Background Layers -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-white/10"></div>

        <!-- Animated Background Elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-teal-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-cyan-500/5 rounded-full blur-2xl animate-pulse" style="animation-delay: 4s;"></div>

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
                            Create New Subject
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Add a new academic subject to your curriculum. Configure subject details, categorization, and availability settings.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ \App\Models\Subject::count() + 1 }}</div>
                            <div class="text-white/70 text-sm">Total Subjects After</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ \App\Models\Subject::where('status', 'active')->count() }}</div>
                            <div class="text-white/70 text-sm">Currently Active</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ \App\Models\Subject::where('category', 'wajib')->count() }}</div>
                            <div class="text-white/70 text-sm">Required Courses</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <a href="{{ route('web-subjects.index') }}" class="glass-action-button group">
                        <div class="p-4 rounded-xl bg-gradient-to-br from-slate-500/20 to-slate-600/20 border border-slate-400/20">
                            <i class="fas fa-arrow-left text-slate-300 text-2xl"></i>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Back to Subjects</div>
                            <div class="text-slate-300 text-sm">Cancel creation</div>
                        </div>
                        <i class="fas fa-arrow-left text-slate-400 group-hover:text-white transition-colors duration-300"></i>
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

    @if($errors->any())
        <div class="mx-6 mb-6 glass-notification glass-notification-error">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-lg bg-red-500/20">
                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                </div>
                <div class="flex-1">
                    <div class="text-white font-semibold mb-2">Please fix the following errors:</div>
                    <ul class="text-red-300 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>• {{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <!-- Create Form with Glass Morphism -->
    <div class="px-6">
        <form method="POST" action="{{ route('web-subjects.store') }}" class="max-w-4xl mx-auto space-y-8" id="subjectCreateForm">
            @csrf

            <!-- Subject Details Section -->
            <div class="glass-morphism-card p-8">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-emerald-500/20 to-teal-500/20 border border-emerald-400/20">
                        <i class="fas fa-book text-emerald-300 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-1">Subject Details</h3>
                        <p class="text-slate-300">Configure the basic information for this subject</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Subject Code -->
                    <div class="space-y-2">
                        <label for="kode" class="block text-sm font-semibold text-white">
                            <i class="fas fa-hashtag mr-2 text-emerald-400"></i>
                            Subject Code <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <input type="text"
                                   id="kode"
                                   name="kode"
                                   value="{{ old('kode') }}"
                                   class="glass-search-input @error('kode') border-red-400 @enderror"
                                   placeholder="e.g., MTK001, BIN002"
                                   required
                                   maxlength="10">
                            @error('kode')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="text-xs text-slate-400">Unique identifier (max 10 characters)</div>
                    </div>

                    <!-- Subject Name -->
                    <div class="space-y-2">
                        <label for="nama" class="block text-sm font-semibold text-white">
                            <i class="fas fa-tag mr-2 text-teal-400"></i>
                            Subject Name <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <input type="text"
                                   id="nama"
                                   name="nama"
                                   value="{{ old('nama') }}"
                                   class="glass-search-input @error('nama') border-red-400 @enderror"
                                   placeholder="e.g., Mathematics, English Language"
                                   required
                                   maxlength="255">
                            @error('nama')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="text-xs text-slate-400">Full subject name (max 255 characters)</div>
                    </div>
                </div>
            </div>

            <!-- Additional Information Section -->
            <div class="glass-morphism-card p-8">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 border border-blue-400/20">
                        <i class="fas fa-info-circle text-blue-300 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-1">Additional Information</h3>
                        <p class="text-slate-300">Configure category, description, and availability settings</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Category -->
                    <div class="space-y-2">
                        <label for="category" class="block text-sm font-semibold text-white">
                            <i class="fas fa-layer-group mr-2 text-purple-400"></i>
                            Category <span class="text-red-400">*</span>
                        </label>
                        <select id="category" name="category" class="glass-search-input @error('category') border-red-400 @enderror" required>
                            <option value="">Select Category</option>
                            <option value="wajib" {{ old('category') == 'wajib' ? 'selected' : '' }}>
                                📚 Required (Wajib) - Mandatory course
                            </option>
                            <option value="peminatan" {{ old('category') == 'peminatan' ? 'selected' : '' }}>
                                🎯 Specialization (Peminatan) - Elective course
                            </option>
                            <option value="mulok" {{ old('category') == 'mulok' ? 'selected' : '' }}>
                                🌍 Multicultural (Mulok) - Cultural education
                            </option>
                        </select>
                        @error('category')
                            <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                <i class="fas fa-exclamation-circle"></i>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div class="space-y-2">
                        <label for="status" class="block text-sm font-semibold text-white">
                            <i class="fas fa-toggle-on mr-2 text-green-400"></i>
                            Status <span class="text-red-400">*</span>
                        </label>
                        <select id="status" name="status" class="glass-search-input @error('status') border-red-400 @enderror" required>
                            <option value="">Select Status</option>
                            <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>
                                ✅ Active - Available for scheduling
                            </option>
                            <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>
                                ❌ Inactive - Not available for scheduling
                            </option>
                        </select>
                        @error('status')
                            <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                <i class="fas fa-exclamation-circle"></i>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-6 space-y-2">
                    <label for="description" class="block text-sm font-semibold text-white">
                        <i class="fas fa-align-left mr-2 text-cyan-400"></i>
                        Description
                    </label>
                    <textarea id="description"
                              name="description"
                              rows="4"
                              class="glass-search-input resize-none @error('description') border-red-400 @enderror"
                              placeholder="Enter subject description, learning objectives, and key topics...">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                            <i class="fas fa-exclamation-circle"></i>
                            {{ $message }}
                        </div>
                    @enderror
                    <div class="text-xs text-slate-400">Optional detailed description (max 500 characters)</div>
                </div>
            </div>

            <!-- Academic Settings Section -->
            <div class="glass-morphism-card p-8">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-orange-500/20 to-amber-500/20 border border-orange-400/20">
                        <i class="fas fa-graduation-cap text-orange-300 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-1">Academic Settings</h3>
                        <p class="text-slate-300">Configure credit hours and semester information</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Credit Hours -->
                    <div class="space-y-2">
                        <label for="credit_hours" class="block text-sm font-semibold text-white">
                            <i class="fas fa-clock mr-2 text-orange-400"></i>
                            Credit Hours
                        </label>
                        <select id="credit_hours" name="credit_hours" class="glass-search-input">
                            @for($i = 1; $i <= 6; $i++)
                                <option value="{{ $i }}" {{ old('credit_hours', 2) == $i ? 'selected' : '' }}>
                                    {{ $i }} Credit{{ $i > 1 ? 's' : '' }}
                                </option>
                            @endfor
                        </select>
                        <div class="text-xs text-slate-400">Academic credit value for this subject</div>
                    </div>

                    <!-- Semester -->
                    <div class="space-y-2">
                        <label for="semester" class="block text-sm font-semibold text-white">
                            <i class="fas fa-calendar-alt mr-2 text-amber-400"></i>
                            Semester
                        </label>
                        <select id="semester" name="semester" class="glass-search-input">
                            @for($i = 1; $i <= 6; $i++)
                                <option value="{{ $i }}" {{ old('semester', 1) == $i ? 'selected' : '' }}>
                                    Semester {{ $i }}
                                </option>
                            @endfor
                        </select>
                        <div class="text-xs text-slate-400">Recommended semester for this subject</div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end gap-4 pt-6">
                <a href="{{ route('web-subjects.index') }}" class="glass-action-btn glass-action-secondary">
                    <i class="fas fa-times mr-2"></i>
                    Cancel
                </a>
                <button type="submit" class="glass-action-btn glass-action-success" id="submitBtn">
                    <i class="fas fa-save mr-2"></i>
                    Create Subject
                </button>
            </div>
        </form>
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

    // Form validation and enhancement
    const form = document.getElementById('subjectCreateForm');
    const submitBtn = document.getElementById('submitBtn');
    const kodeInput = document.getElementById('kode');
    const namaInput = document.getElementById('nama');
    const descriptionTextarea = document.getElementById('description');

    // Auto-format subject code
    if (kodeInput) {
        kodeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 10);
        });
    }

    // Auto-generate subject code from name
    if (namaInput && kodeInput) {
        namaInput.addEventListener('input', function() {
            if (kodeInput.value === '') {
                const words = this.value.split(' ');
                let code = '';

                words.forEach((word, index) => {
                    if (index < 2 && word.length > 0) {
                        code += word.substring(0, 3).toUpperCase();
                    }
                });

                if (code.length > 0) {
                    code += '001';
                }

                kodeInput.value = code.substring(0, 10);
            }
        });
    }

    // Character counter for description
    if (descriptionTextarea) {
        const maxLength = 500;
        let counter = descriptionTextarea.parentNode.querySelector('.char-counter');

        if (!counter) {
            counter = document.createElement('div');
            counter.className = 'char-counter text-xs text-slate-400 mt-1';
            descriptionTextarea.parentNode.appendChild(counter);
        }

        descriptionTextarea.addEventListener('input', function() {
            const length = this.value.length;
            counter.textContent = `${length} / ${maxLength} characters`;

            if (length > maxLength * 0.8) {
                counter.style.color = '#f59e0b';
            } else {
                counter.style.color = '#94a3b8';
            }

            if (length > maxLength) {
                this.value = this.value.substring(0, maxLength);
                counter.style.color = '#ef4444';
            }
        });

        // Initialize counter
        descriptionTextarea.dispatchEvent(new Event('input'));
    }

    // Form submission with loading state
    if (form) {
        form.addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating Subject...';

            // Re-enable after 10 seconds if still on page
            setTimeout(() => {
                if (submitBtn.disabled) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Create Subject';
                }
            }, 10000);
        });
    }

    // Real-time validation
    const inputs = form.querySelectorAll('input[required], select[required]');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.classList.add('border-red-400');
            } else {
                this.classList.remove('border-red-400');
            }
        });

        input.addEventListener('input', function() {
            if (this.classList.contains('border-red-400') && this.value.trim()) {
                this.classList.remove('border-red-400');
            }
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+Enter to submit
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            if (!submitBtn.disabled) {
                form.dispatchEvent(new Event('submit'));
            }
        }

        // Escape to cancel
        if (e.key === 'Escape') {
            window.location.href = '{{ route("web-subjects.index") }}';
        }
    });
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

/* Search Input (reused for form inputs) */
.glass-search-input {
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

.glass-search-input:focus {
    outline: none;
    border-color: rgba(59, 130, 246, 0.5);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.glass-search-input::placeholder {
    color: #94a3b8;
}

.glass-search-input option {
    background: rgba(15, 23, 42, 0.95);
    color: white;
}

/* Action Buttons */
.glass-action-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-decoration: none;
    color: #e2e8f0;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.glass-action-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.glass-action-secondary {
    background: rgba(148, 163, 184, 0.1);
    border-color: rgba(148, 163, 184, 0.3);
    color: #94a3b8;
}

.glass-action-secondary:hover {
    background: rgba(148, 163, 184, 0.2);
    border-color: rgba(148, 163, 184, 0.4);
    color: #cbd5e1;
}

.glass-action-success {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.glass-action-success:hover {
    background: rgba(34, 197, 94, 0.2);
    border-color: rgba(34, 197, 94, 0.4);
    color: #16a34a;
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

/* Responsive Design */
@media (max-width: 768px) {
    .glass-morphism-card {
        margin: 0 1rem;
    }

    .glass-search-input {
        font-size: 16px; /* Prevent zoom on iOS */
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
</style>
@endsection
