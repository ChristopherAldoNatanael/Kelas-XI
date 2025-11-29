@extends('layouts.app')

@section('title', 'Edit Class - ' . $class->nama_kelas)

@section('content')
<div class="min-h-screen">
    <!-- Hero Header with Glass Morphism -->
    <div class="relative overflow-hidden rounded-3xl mx-6 mb-8">
        <!-- Background Layers -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-white/10"></div>

        <!-- Animated Background Elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-cyan-500/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
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
                            Edit Class
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Update class information and settings for <span class="text-cyan-300 font-semibold">{{ $class->nama_kelas }}</span>.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $class->users->count() }}</div>
                            <div class="text-white/70 text-sm">Students</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ ucfirst($class->status) }}</div>
                            <div class="text-white/70 text-sm">Current Status</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $class->created_at->diffForHumans() }}</div>
                            <div class="text-white/70 text-sm">Created</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <a href="{{ route('web-classes.index') }}" class="glass-action-button group">
                        <div class="p-4 rounded-xl bg-gradient-to-br from-slate-500/20 to-slate-600/20 border border-slate-400/20">
                            <i class="fas fa-arrow-left text-slate-300 text-2xl"></i>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Back to Classes</div>
                            <div class="text-slate-300 text-sm">Cancel editing</div>
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

    <!-- Edit Form with Glass Morphism -->
    <div class="px-6">
        <form method="POST" action="{{ route('web-classes.update', $class->id) }}" class="max-w-4xl mx-auto space-y-8" id="classEditForm">
            @csrf
            @method('PUT')

            <!-- Class Details Section -->
            <div class="glass-morphism-card p-8">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-cyan-500/20 to-blue-500/20 border border-cyan-400/20">
                        <i class="fas fa-users-class text-cyan-300 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-1">Class Details</h3>
                        <p class="text-slate-300">Update the basic information for this class</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Class Name -->
                    <div class="space-y-2">
                        <label for="nama_kelas" class="block text-sm font-semibold text-white">
                            <i class="fas fa-tag mr-2 text-cyan-400"></i>
                            Class Name <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <input type="text"
                                   id="nama_kelas"
                                   name="nama_kelas"
                                   value="{{ old('nama_kelas', $class->nama_kelas) }}"
                                   class="glass-search-input @error('nama_kelas') border-red-400 @enderror"
                                   placeholder="e.g., X RPL 1, XI IPA 2"
                                   required
                                   maxlength="255">
                            @error('nama_kelas')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="text-xs text-slate-400">Full class name (max 255 characters)</div>
                    </div>

                    <!-- Class Code -->
                    <div class="space-y-2">
                        <label for="kode_kelas" class="block text-sm font-semibold text-white">
                            <i class="fas fa-hashtag mr-2 text-blue-400"></i>
                            Class Code
                        </label>
                        <div class="relative">
                            <input type="text"
                                   id="kode_kelas"
                                   name="kode_kelas"
                                   value="{{ old('kode_kelas', $class->kode_kelas) }}"
                                   class="glass-search-input @error('kode_kelas') border-red-400 @enderror"
                                   placeholder="e.g., X-RPL-1, XI-IPA-2"
                                   maxlength="50">
                            @error('kode_kelas')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="text-xs text-slate-400">Unique identifier (max 50 characters)</div>
                    </div>
                </div>
            </div>

            <!-- Academic Information Section -->
            <div class="glass-morphism-card p-8">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-green-500/20 to-emerald-500/20 border border-green-400/20">
                        <i class="fas fa-graduation-cap text-green-300 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-1">Academic Information</h3>
                        <p class="text-slate-300">Configure grade level, academic year, and capacity settings</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Grade Level -->
                    <div class="space-y-2">
                        <label for="tingkat_kelas" class="block text-sm font-semibold text-white">
                            <i class="fas fa-layer-group mr-2 text-green-400"></i>
                            Grade Level <span class="text-red-400">*</span>
                        </label>
                        <select id="tingkat_kelas" name="tingkat_kelas" class="glass-search-input @error('tingkat_kelas') border-red-400 @enderror" required>
                            <option value="">Select Grade Level</option>
                            <option value="10" {{ old('tingkat_kelas', $class->tingkat_kelas) == '10' ? 'selected' : '' }}>Grade 10 (Senior High)</option>
                            <option value="11" {{ old('tingkat_kelas', $class->tingkat_kelas) == '11' ? 'selected' : '' }}>Grade 11 (Senior High)</option>
                            <option value="12" {{ old('tingkat_kelas', $class->tingkat_kelas) == '12' ? 'selected' : '' }}>Grade 12 (Senior High)</option>
                        </select>
                        @error('tingkat_kelas')
                            <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                <i class="fas fa-exclamation-circle"></i>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Academic Year -->
                    <div class="space-y-2">
                        <label for="tahun_ajaran" class="block text-sm font-semibold text-white">
                            <i class="fas fa-calendar-alt mr-2 text-emerald-400"></i>
                            Academic Year <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <input type="text"
                                   id="tahun_ajaran"
                                   name="tahun_ajaran"
                                   value="{{ old('tahun_ajaran', $class->tahun_ajaran) }}"
                                   class="glass-search-input @error('tahun_ajaran') border-red-400 @enderror"
                                   placeholder="e.g., 2024/2025"
                                   required
                                   maxlength="20">
                            @error('tahun_ajaran')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="text-xs text-slate-400">Academic year in YYYY/YYYY format</div>
                    </div>

                    <!-- Major -->
                    <div class="space-y-2">
                        <label for="major" class="block text-sm font-semibold text-white">
                            <i class="fas fa-cogs mr-2 text-purple-400"></i>
                            Major/Specialization
                        </label>
                        <select id="major" name="major" class="glass-search-input">
                            <option value="">Select Major (Optional)</option>
                            <option value="Rekayasa Perangkat Lunak" {{ old('major', $class->major) == 'Rekayasa Perangkat Lunak' ? 'selected' : '' }}>Rekayasa Perangkat Lunak (RPL)</option>
                            <option value="Teknik Komputer dan Jaringan" {{ old('major', $class->major) == 'Teknik Komputer dan Jaringan' ? 'selected' : '' }}>Teknik Komputer dan Jaringan (TKJ)</option>
                            <option value="Multimedia" {{ old('major', $class->major) == 'Multimedia' ? 'selected' : '' }}>Multimedia (MM)</option>
                            <option value="Akuntansi" {{ old('major', $class->major) == 'Akuntansi' ? 'selected' : '' }}>Akuntansi (AK)</option>
                            <option value="Administrasi Perkantoran" {{ old('major', $class->major) == 'Administrasi Perkantoran' ? 'selected' : '' }}>Administrasi Perkantoran (AP)</option>
                            <option value="Bisnis Daring dan Pemasaran" {{ old('major', $class->major) == 'Bisnis Daring dan Pemasaran' ? 'selected' : '' }}>Bisnis Daring dan Pemasaran (BDP)</option>
                            <option value="IPA" {{ old('major', $class->major) == 'IPA' ? 'selected' : '' }}>Ilmu Pengetahuan Alam (IPA)</option>
                            <option value="IPS" {{ old('major', $class->major) == 'IPS' ? 'selected' : '' }}>Ilmu Pengetahuan Sosial (IPS)</option>
                        </select>
                        <div class="text-xs text-slate-400">Class specialization or major</div>
                    </div>

                    <!-- Capacity -->
                    <div class="space-y-2">
                        <label for="capacity" class="block text-sm font-semibold text-white">
                            <i class="fas fa-users mr-2 text-orange-400"></i>
                            Class Capacity
                        </label>
                        <div class="relative">
                            <input type="number"
                                   id="capacity"
                                   name="capacity"
                                   value="{{ old('capacity', $class->capacity) }}"
                                   class="glass-search-input"
                                   placeholder="e.g., 30"
                                   min="1"
                                   max="100">
                        </div>
                        <div class="text-xs text-slate-400">Maximum number of students (optional)</div>
                    </div>

                    <!-- Homeroom Teacher -->
                    <div class="space-y-2">
                        <label for="homeroom_teacher_id" class="block text-sm font-semibold text-white">
                            <i class="fas fa-chalkboard-teacher mr-2 text-indigo-400"></i>
                            Homeroom Teacher
                        </label>
                        <select id="homeroom_teacher_id" name="homeroom_teacher_id" class="glass-search-input">
                            <option value="">Select Homeroom Teacher (Optional)</option>
                            @foreach(\App\Models\Teacher::where('status', 'active')->orderBy('nama')->get() as $teacher)
                                <option value="{{ $teacher->id }}" {{ old('homeroom_teacher_id', $class->homeroom_teacher_id) == $teacher->id ? 'selected' : '' }}>
                                    {{ $teacher->nama }} ({{ $teacher->nip }})
                                </option>
                            @endforeach
                        </select>
                        <div class="text-xs text-slate-400">Teacher responsible for this class</div>
                    </div>
                </div>

                <!-- Status -->
                <div class="mt-6 space-y-2">
                    <label for="status" class="block text-sm font-semibold text-white">
                        <i class="fas fa-toggle-on mr-2 text-blue-400"></i>
                        Status <span class="text-red-400">*</span>
                    </label>
                    <select id="status" name="status" class="glass-search-input @error('status') border-red-400 @enderror" required>
                        <option value="">Select Status</option>
                        <option value="active" {{ old('status', $class->status) == 'active' ? 'selected' : '' }}>
                            ✅ Active - Available for student enrollment
                        </option>
                        <option value="inactive" {{ old('status', $class->status) == 'inactive' ? 'selected' : '' }}>
                            ❌ Inactive - Not available for enrollment
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


            <!-- System Information Section -->
            <div class="glass-morphism-card p-8">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-gray-500/20 to-slate-500/20 border border-gray-400/20">
                        <i class="fas fa-database text-gray-300 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-1">System Information</h3>
                        <p class="text-slate-300">Record creation and modification details</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="glass-stat-mini">
                        <div class="text-lg font-bold text-white">#{{ $class->id }}</div>
                        <div class="text-xs text-slate-400">Record ID</div>
                    </div>
                    <div class="glass-stat-mini">
                        <div class="text-lg font-bold text-white">{{ $class->created_at->format('M d, Y') }}</div>
                        <div class="text-xs text-slate-400">Created</div>
                    </div>
                    <div class="glass-stat-mini">
                        <div class="text-lg font-bold text-white">{{ $class->updated_at->format('M d, Y') }}</div>
                        <div class="text-xs text-slate-400">Last Updated</div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end gap-4 pt-6">
                <a href="{{ route('web-classes.index') }}" class="glass-action-btn glass-action-secondary">
                    <i class="fas fa-times mr-2"></i>
                    Cancel
                </a>
                <button type="submit" class="glass-action-btn glass-action-success" id="submitBtn">
                    <i class="fas fa-save mr-2"></i>
                    Update Class
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
    const form = document.getElementById('classEditForm');
    const submitBtn = document.getElementById('submitBtn');
    const namaKelasInput = document.getElementById('nama_kelas');
    const kodeKelasInput = document.getElementById('kode_kelas');
    const tahunAjaranInput = document.getElementById('tahun_ajaran');
    const deskripsiTextarea = document.getElementById('deskripsi');

    // Auto-format class code
    if (kodeKelasInput) {
        kodeKelasInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '').substring(0, 50);
        });
    }

    // Auto-format academic year
    if (tahunAjaranInput) {
        tahunAjaranInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9\/]/g, '').substring(0, 20);
        });
    }

    // Character counter for description
    if (deskripsiTextarea) {
        const maxLength = 500;
        let counter = deskripsiTextarea.parentNode.querySelector('.char-counter');

        if (!counter) {
            counter = document.createElement('div');
            counter.className = 'char-counter text-xs text-slate-400 mt-1';
            deskripsiTextarea.parentNode.appendChild(counter);
        }

        deskripsiTextarea.addEventListener('input', function() {
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
        deskripsiTextarea.dispatchEvent(new Event('input'));
    }

    // Form submission with loading state
    if (form) {
        form.addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating Class...';

            // Re-enable after 10 seconds if still on page
            setTimeout(() => {
                if (submitBtn.disabled) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update Class';
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
            window.location.href = '{{ route("web-classes.index") }}';
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

/* Mini Stats */
.glass-stat-mini {
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    text-align: center;
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
