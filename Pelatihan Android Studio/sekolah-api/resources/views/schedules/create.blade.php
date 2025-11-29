@extends('layouts.app')

@section('title', 'Create Schedule')

@section('content')
<div class="min-h-screen">
    <!-- Hero Header with Glass Morphism -->
    <div class="relative overflow-hidden rounded-3xl mx-6 mb-8">
        <!-- Background Layers -->
        <div class="absolute inset-0 bg-gradient-to-br from-green-900 via-green-800 to-green-900"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-white/10"></div>

        <!-- Animated Background Elements -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-teal-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-green-500/5 rounded-full blur-2xl animate-pulse" style="animation-delay: 4s;"></div>

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
                            Create New Schedule
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Schedule new class sessions and academic activities with our intelligent scheduling system.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ count($dropdownData['teachers'] ?? []) }}</div>
                            <div class="text-white/70 text-sm">Available Teachers</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ count($dropdownData['classrooms'] ?? []) }}</div>
                            <div class="text-white/70 text-sm">Classrooms</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ count($dropdownData['subjects'] ?? []) }}</div>
                            <div class="text-white/70 text-sm">Subjects</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <a href="{{ route('web-schedules.index') }}" class="glass-action-button group">
                        <div class="p-3 rounded-xl bg-gradient-to-br from-slate-500/20 to-gray-500/20 border border-slate-400/20">
                            <i class="fas fa-arrow-left text-slate-300 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Back to Schedules</div>
                            <div class="text-slate-300 text-sm">View all schedules</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Message -->
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

    <!-- Error Messages -->
    @if($errors->any())
        <div class="mx-6 mb-6 glass-notification glass-notification-error">
            <div class="flex items-center gap-3">
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

    <!-- Form Container -->
    <div class="px-6 space-y-6">
        <!-- Schedule Information Card -->
        <div class="glass-morphism-card p-6">
            <div class="flex items-center gap-4 mb-6">
                <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 border border-blue-400/20">
                    <i class="fas fa-calendar-alt text-blue-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Schedule Information</h3>
                    <p class="text-slate-300">Set the basic schedule details</p>
                </div>
            </div>

            <form method="POST" action="{{ route('web-schedules.store') }}" class="space-y-6" id="scheduleForm">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Day Selection -->
                    <div>
                        <label for="hari" class="block text-sm font-medium text-white mb-2">
                            <span class="flex items-center">
                                <i class="fas fa-calendar-day mr-2 text-blue-400"></i>
                                Day <span class="text-red-400">*</span>
                            </span>
                        </label>
                        <select id="hari" name="hari" required class="glass-select">
                            <option value="">Select day...</option>
                            <option value="Senin" {{ old('hari') == 'Senin' ? 'selected' : '' }}>Monday</option>
                            <option value="Selasa" {{ old('hari') == 'Selasa' ? 'selected' : '' }}>Tuesday</option>
                            <option value="Rabu" {{ old('hari') == 'Rabu' ? 'selected' : '' }}>Wednesday</option>
                            <option value="Kamis" {{ old('hari') == 'Kamis' ? 'selected' : '' }}>Thursday</option>
                            <option value="Jumat" {{ old('hari') == 'Jumat' ? 'selected' : '' }}>Friday</option>
                            <option value="Sabtu" {{ old('hari') == 'Sabtu' ? 'selected' : '' }}>Saturday</option>
                        </select>
                        @error('hari')
                            <p class="mt-1 text-sm text-red-400 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Class Input -->
                    <div>
                        <label for="kelas" class="block text-sm font-medium text-white mb-2">
                            <span class="flex items-center">
                                <i class="fas fa-users mr-2 text-green-400"></i>
                                Class <span class="text-red-400">*</span>
                            </span>
                        </label>
                        <input type="text" id="kelas" name="kelas" value="{{ old('kelas') }}" required
                                class="glass-input" placeholder="e.g., X RPL, XI IPA" maxlength="50">
                        <div class="text-xs text-slate-400 mt-1">
                            <span id="kelasCount">{{ strlen(old('kelas', '')) }}</span>/50 characters
                        </div>
                        @error('kelas')
                            <p class="mt-1 text-sm text-red-400 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>
        </div>

        <!-- Academic Details Card -->
        <div class="glass-morphism-card p-6">
            <div class="flex items-center gap-4 mb-6">
                <div class="p-3 rounded-xl bg-gradient-to-br from-green-500/20 to-emerald-500/20 border border-green-400/20">
                    <i class="fas fa-book text-green-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Academic Details</h3>
                    <p class="text-slate-300">Subject and teacher information</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Subject Input -->
                <div>
                    <label for="mata_pelajaran" class="block text-sm font-medium text-white mb-2">
                        <span class="flex items-center">
                            <i class="fas fa-book-open mr-2 text-green-400"></i>
                            Subject <span class="text-red-400">*</span>
                        </span>
                    </label>
                    <input type="text" id="mata_pelajaran" name="mata_pelajaran" value="{{ old('mata_pelajaran') }}" required
                            class="glass-input" placeholder="e.g., Mathematics, English" maxlength="100">
                    <div class="text-xs text-slate-400 mt-1">
                        <span id="subjectCount">{{ strlen(old('mata_pelajaran', '')) }}</span>/100 characters
                    </div>
                    @error('mata_pelajaran')
                        <p class="mt-1 text-sm text-red-400 flex items-center">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Teacher Selection -->
                <div>
                    <label for="guru_id" class="block text-sm font-medium text-white mb-2">
                        <span class="flex items-center">
                            <i class="fas fa-chalkboard-teacher mr-2 text-purple-400"></i>
                            Teacher <span class="text-red-400">*</span>
                        </span>
                    </label>
                    <select id="guru_id" name="guru_id" required class="glass-select">
                        <option value="">Select teacher...</option>
                        @foreach($dropdownData['teachers'] ?? [] as $teacher)
                            <option value="{{ $teacher->id }}" {{ old('guru_id') == $teacher->id ? 'selected' : '' }}>
                                👨‍🏫 {{ $teacher->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('guru_id')
                        <p class="mt-1 text-sm text-red-400 flex items-center">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Time & Location Card -->
        <div class="glass-morphism-card p-6">
            <div class="flex items-center gap-4 mb-6">
                <div class="p-3 rounded-xl bg-gradient-to-br from-orange-500/20 to-amber-500/20 border border-orange-400/20">
                    <i class="fas fa-clock text-orange-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Time & Location</h3>
                    <p class="text-slate-300">Schedule timing and classroom details</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Start Time -->
                <div>
                    <label for="jam_mulai" class="block text-sm font-medium text-white mb-2">
                        <span class="flex items-center">
                            <i class="fas fa-play mr-2 text-teal-400"></i>
                            Start Time <span class="text-red-400">*</span>
                        </span>
                    </label>
                    <input type="time" id="jam_mulai" name="jam_mulai" value="{{ old('jam_mulai') }}" required
                            class="glass-input">
                    @error('jam_mulai')
                        <p class="mt-1 text-sm text-red-400 flex items-center">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- End Time -->
                <div>
                    <label for="jam_selesai" class="block text-sm font-medium text-white mb-2">
                        <span class="flex items-center">
                            <i class="fas fa-stop mr-2 text-pink-400"></i>
                            End Time <span class="text-red-400">*</span>
                        </span>
                    </label>
                    <input type="time" id="jam_selesai" name="jam_selesai" value="{{ old('jam_selesai') }}" required
                            class="glass-input">
                    @error('jam_selesai')
                        <p class="mt-1 text-sm text-red-400 flex items-center">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Room -->
                <div>
                    <label for="ruang" class="block text-sm font-medium text-white mb-2">
                        <span class="flex items-center">
                            <i class="fas fa-map-marker-alt mr-2 text-orange-400"></i>
                            Classroom
                        </span>
                    </label>
                    <input type="text" id="ruang" name="ruang" value="{{ old('ruang') }}"
                            class="glass-input" placeholder="e.g., Room 101, Computer Lab" maxlength="50">
                    <div class="text-xs text-slate-400 mt-1">
                        <span id="roomCount">{{ strlen(old('ruang', '')) }}</span>/50 characters
                    </div>
                    @error('ruang')
                        <p class="mt-1 text-sm text-red-400 flex items-center">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            <!-- Duration Display -->
            <div class="mt-6 p-4 glass-morphism-card">
                <div class="flex items-center text-slate-300">
                    <i class="fas fa-hourglass-half mr-3 text-blue-400"></i>
                    <span class="font-medium">Duration: </span>
                    <span id="duration" class="ml-2 font-bold text-white">--</span>
                </div>
            </div>
        </div>

        <!-- Additional Information Card -->
        <div class="glass-morphism-card p-6">
            <div class="flex items-center gap-4 mb-6">
                <div class="p-3 rounded-xl bg-gradient-to-br from-yellow-500/20 to-amber-500/20 border border-yellow-400/20">
                    <i class="fas fa-sticky-note text-yellow-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Additional Information</h3>
                    <p class="text-slate-300">Optional notes and special instructions</p>
                </div>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-white mb-2">
                    <span class="flex items-center">
                        <i class="fas fa-sticky-note mr-2 text-yellow-400"></i>
                        Notes (Optional)
                    </span>
                </label>
                <textarea id="notes" name="notes" rows="4" class="glass-input resize-none"
                          placeholder="Add any special instructions, requirements, or additional information about this schedule..."
                          maxlength="500">{{ old('notes') }}</textarea>
                <div class="text-xs text-slate-400 mt-1">
                    <span id="notesCount">{{ strlen(old('notes', '')) }}</span>/500 characters
                </div>
                @error('notes')
                    <p class="mt-1 text-sm text-red-400 flex items-center">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        {{ $message }}
                    </p>
                @enderror
            </div>
        </div>
            </form>

        <!-- Form Actions -->
        <div class="flex flex-col sm:flex-row justify-center gap-4 pt-6">
            <a href="{{ route('web-schedules.index') }}" class="glass-action-btn glass-action-secondary">
                <i class="fas fa-arrow-left mr-2"></i>
                Cancel
            </a>
            <button type="submit" form="scheduleForm" class="glass-action-btn glass-action-primary">
                <i class="fas fa-save mr-2"></i>
                Create Schedule
            </button>
        </div>
    </div>
</div>
@endsection

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
    border-color: rgba(34, 197, 94, 0.5);
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
}

.glass-input::placeholder {
    color: #94a3b8;
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
    justify-content: center;
    gap: 0.5rem;
}

.glass-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.glass-action-primary {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.glass-action-primary:hover {
    background: rgba(34, 197, 94, 0.2);
    border-color: rgba(34, 197, 94, 0.4);
    color: #16a34a;
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
    border-top: 2px solid #22c55e;
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

    .glass-input, .glass-select {
        font-size: 16px; /* Prevent zoom on iOS */
    }

    .glass-action-btn {
        padding: 0.5rem 1rem;
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
    .glass-morphism-card {
        background: white !important;
        border: 1px solid #e5e7eb !important;
        box-shadow: none !important;
    }
}
</style>

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

    // Form elements
    const form = document.getElementById('scheduleForm');

    // Input elements
    const kelasInput = document.getElementById('kelas');
    const subjectInput = document.getElementById('mata_pelajaran');
    const roomInput = document.getElementById('ruang');
    const notesInput = document.getElementById('notes');
    const startTimeInput = document.getElementById('jam_mulai');
    const endTimeInput = document.getElementById('jam_selesai');

    // Counter elements
    const kelasCount = document.getElementById('kelasCount');
    const subjectCount = document.getElementById('subjectCount');
    const roomCount = document.getElementById('roomCount');
    const notesCount = document.getElementById('notesCount');
    const durationElement = document.getElementById('duration');

    // Character counters
    if (kelasInput && kelasCount) {
        kelasInput.addEventListener('input', function() {
            kelasCount.textContent = this.value.length;
        });
    }

    if (subjectInput && subjectCount) {
        subjectInput.addEventListener('input', function() {
            subjectCount.textContent = this.value.length;
        });
    }

    if (roomInput && roomCount) {
        roomInput.addEventListener('input', function() {
            roomCount.textContent = this.value.length;
        });
    }

    if (notesInput && notesCount) {
        notesInput.addEventListener('input', function() {
            notesCount.textContent = this.value.length;
        });
    }

    // Duration calculation
    function updateDuration() {
        if (startTimeInput.value && endTimeInput.value) {
            const start = new Date(`2000-01-01T${startTimeInput.value}:00`);
            const end = new Date(`2000-01-01T${endTimeInput.value}:00`);

            if (end > start) {
                const diff = end - start;
                const hours = Math.floor(diff / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);

                let duration = '';
                if (hours > 0) duration += `${hours} hour${hours > 1 ? 's' : ''}`;
                if (minutes > 0) {
                    if (duration) duration += ' ';
                    duration += `${minutes} minute${minutes > 1 ? 's' : ''}`;
                }

                durationElement.textContent = duration || '0 minutes';
                durationElement.style.color = '#22c55e';
            } else {
                durationElement.textContent = 'Invalid time range';
                durationElement.style.color = '#ef4444';
            }
        } else {
            durationElement.textContent = '--';
            durationElement.style.color = '#64748b';
        }
    }

    if (startTimeInput && endTimeInput) {
        startTimeInput.addEventListener('change', updateDuration);
        endTimeInput.addEventListener('change', updateDuration);
        updateDuration(); // Initial calculation
    }

    // Form validation and submission
    form.addEventListener('submit', function(e) {
        // Basic validation
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#ef4444';
                isValid = false;
            } else {
                field.style.borderColor = 'rgba(34, 197, 94, 0.5)';
            }
        });

        // Time validation
        if (startTimeInput.value && endTimeInput.value) {
            const start = new Date(`2000-01-01T${startTimeInput.value}:00`);
            const end = new Date(`2000-01-01T${endTimeInput.value}:00`);

            if (end <= start) {
                endTimeInput.style.borderColor = '#ef4444';
                isValid = false;
            }
        }

        if (!isValid) {
            e.preventDefault();
            return false;
        }

        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating Schedule...';
        }
    });

    // Auto-format inputs
    if (kelasInput) {
        kelasInput.addEventListener('input', function() {
            // Auto-capitalize class names
            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });
    }

    if (subjectInput) {
        subjectInput.addEventListener('input', function() {
            // Auto-capitalize subject names
            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });
    }

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
});
</script>
