@extends('layouts.app')

@section('title', 'Edit Schedule')

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
                            Edit Schedule
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Update schedule information and optimize your academic timetable management.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $schedule->mata_pelajaran ?? 'N/A' }}</div>
                            <div class="text-white/70 text-sm">Subject</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $schedule->kelas ?? 'N/A' }}</div>
                            <div class="text-white/70 text-sm">Class</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">{{ $schedule->hari ?? 'N/A' }}</div>
                            <div class="text-white/70 text-sm">Day</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <div class="flex gap-3">
                        <a href="{{ route('web-schedules.show', $schedule->id) }}" class="glass-action-button group">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-cyan-500/20 border border-blue-400/20">
                                <i class="fas fa-eye text-blue-300 text-xl"></i>
                            </div>
                            <div>
                                <div class="text-white font-semibold">View Details</div>
                                <div class="text-slate-300 text-sm">See schedule info</div>
                            </div>
                        </a>
                        <a href="{{ route('web-schedules.index') }}" class="glass-action-button group">
                            <div class="p-3 rounded-xl bg-gradient-to-br from-slate-500/20 to-gray-500/20 border border-slate-400/20">
                                <i class="fas fa-arrow-left text-slate-300 text-xl"></i>
                            </div>
                            <div>
                                <div class="text-white font-semibold">Back to List</div>
                                <div class="text-slate-300 text-sm">All schedules</div>
                            </div>
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

    <!-- Form Container -->
    <div class="px-6 space-y-6">
        <!-- Form Card -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20 border border-purple-400/20">
                        <i class="fas fa-edit text-purple-300 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">Schedule Information</h3>
                        <p class="text-slate-300">Update the schedule details below</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('web-schedules.update', $schedule->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Hari Selection -->
                        <div>
                            <label for="hari" class="block text-sm font-medium text-white mb-2">
                                <span class="flex items-center">
                                    <i class="fas fa-calendar-day mr-2 text-indigo-400"></i>
                                    Day
                                </span>
                            </label>
                            <select id="hari" name="hari" required class="glass-select">
                                <option value="">Select Day</option>
                                <option value="Senin" {{ ($schedule->hari ?? '') == 'Senin' ? 'selected' : '' }}>Monday</option>
                                <option value="Selasa" {{ ($schedule->hari ?? '') == 'Selasa' ? 'selected' : '' }}>Tuesday</option>
                                <option value="Rabu" {{ ($schedule->hari ?? '') == 'Rabu' ? 'selected' : '' }}>Wednesday</option>
                                <option value="Kamis" {{ ($schedule->hari ?? '') == 'Kamis' ? 'selected' : '' }}>Thursday</option>
                                <option value="Jumat" {{ ($schedule->hari ?? '') == 'Jumat' ? 'selected' : '' }}>Friday</option>
                                <option value="Sabtu" {{ ($schedule->hari ?? '') == 'Sabtu' ? 'selected' : '' }}>Saturday</option>
                            </select>
                            @error('hari')
                                <p class="mt-1 text-sm text-red-400 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Kelas Input -->
                        <div>
                            <label for="kelas" class="block text-sm font-medium text-white mb-2">
                                <span class="flex items-center">
                                    <i class="fas fa-users mr-2 text-blue-400"></i>
                                    Class
                                </span>
                            </label>
                            <input type="text" id="kelas" name="kelas" value="{{ $schedule->kelas ?? '' }}" required
                                    class="glass-input" placeholder="Example: X RPL, XI IPA">
                            @error('kelas')
                                <p class="mt-1 text-sm text-red-400 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Mata Pelajaran Input -->
                        <div class="md:col-span-2">
                            <label for="mata_pelajaran" class="block text-sm font-medium text-white mb-2">
                                <span class="flex items-center">
                                    <i class="fas fa-book mr-2 text-green-400"></i>
                                    Subject
                                </span>
                            </label>
                            <input type="text" id="mata_pelajaran" name="mata_pelajaran" value="{{ $schedule->mata_pelajaran ?? '' }}" required
                                    class="glass-input" placeholder="Example: Mathematics, English">
                            @error('mata_pelajaran')
                                <p class="mt-1 text-sm text-red-400 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Guru Selection -->
                        <div class="md:col-span-2">
                            <label for="guru_id" class="block text-sm font-medium text-white mb-2">
                                <span class="flex items-center">
                                    <i class="fas fa-chalkboard-teacher mr-2 text-purple-400"></i>
                                    Teacher
                                </span>
                            </label>
                            <select id="guru_id" name="guru_id" required class="glass-select">
                                <option value="">Select Teacher</option>
                                @foreach($dropdownData['teachers'] ?? [] as $teacher)
                                    <option value="{{ $teacher['id'] }}" {{ ($schedule->guru_id ?? '') == $teacher['id'] ? 'selected' : '' }}>
                                        👨‍🏫 {{ $teacher['name'] }}
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

                        <!-- Time Fields -->
                        <div>
                            <label for="jam_mulai" class="block text-sm font-medium text-white mb-2">
                                <span class="flex items-center">
                                    <i class="fas fa-clock mr-2 text-teal-400"></i>
                                    Start Time
                                </span>
                            </label>
                            <input type="time" id="jam_mulai" name="jam_mulai" value="{{ $schedule->jam_mulai ?? '' }}" required
                                    class="glass-input">
                            @error('jam_mulai')
                                <p class="mt-1 text-sm text-red-400 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label for="jam_selesai" class="block text-sm font-medium text-white mb-2">
                                <span class="flex items-center">
                                    <i class="fas fa-clock mr-2 text-pink-400"></i>
                                    End Time
                                </span>
                            </label>
                            <input type="time" id="jam_selesai" name="jam_selesai" value="{{ $schedule->jam_selesai ?? '' }}" required
                                    class="glass-input">
                            @error('jam_selesai')
                                <p class="mt-1 text-sm text-red-400 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Ruang Input -->
                        <div class="md:col-span-2">
                            <label for="ruang" class="block text-sm font-medium text-white mb-2">
                                <span class="flex items-center">
                                    <i class="fas fa-map-marker-alt mr-2 text-orange-400"></i>
                                    Room
                                </span>
                            </label>
                            <input type="text" id="ruang" name="ruang" value="{{ $schedule->ruang ?? '' }}"
                                    class="glass-input" placeholder="Example: Room 101, Computer Lab">
                            @error('ruang')
                                <p class="mt-1 text-sm text-red-400 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                    <!-- Submit Buttons -->
                    <div class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-6 border-t border-white/10">
                        <a href="{{ route('web-schedules.index') }}" class="glass-action-btn glass-action-secondary">
                            <i class="fas fa-times mr-2"></i>
                            Cancel
                        </a>
                        <button type="submit" class="glass-action-btn glass-action-primary">
                            <i class="fas fa-save mr-2"></i>
                            Update Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
    border-color: rgba(147, 51, 234, 0.5);
    box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.1);
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
    background: rgba(147, 51, 234, 0.1);
    border-color: rgba(147, 51, 234, 0.3);
    color: #c084fc;
}

.glass-action-primary:hover {
    background: rgba(147, 51, 234, 0.2);
    border-color: rgba(147, 51, 234, 0.4);
    color: #ddd6fe;
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
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const subjectSelect = document.getElementById('subject_id');
    const teacherSelect = document.getElementById('teacher_id');
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const periodInput = document.getElementById('period_number');
    const notesTextarea = document.getElementById('notes');
    const classroomSelect = document.getElementById('classroom_id');
    const daySelect = document.getElementById('day');

    // Pre-select values based on current schedule using proper model relationships
    const currentTeacherId = @if($schedule->teacher) {{ $schedule->teacher->id }} @else null @endif;
    const currentSubjectId = @if($schedule->subject) {{ $schedule->subject->id }} @else null @endif;
    const currentClassroomId = @if($schedule->classroom) {{ $schedule->classroom->id }} @else null @endif;

    // Pre-select current values
    if (currentSubjectId && subjectSelect) {
        subjectSelect.value = currentSubjectId;
        loadTeachersForSubject(currentSubjectId);
    }

    if (currentClassroomId && classroomSelect) {
        classroomSelect.value = currentClassroomId;
    }

    // Subject change handler
    if (subjectSelect) {
        subjectSelect.addEventListener('change', function() {
            const subjectId = this.value;
            loadTeachersForSubject(subjectId);
        });
    }

    function loadTeachersForSubject(subjectId) {
        if (!teacherSelect) return;

        teacherSelect.innerHTML = '<option value="">Select Teacher</option>';

        if (subjectId) {
            // Get data from controller
            const subjects = @json($dropdownData['subjects'] ?? []);
            const teachers = @json($dropdownData['teachers'] ?? []);

            // Find the selected subject
            const selectedSubject = subjects.find(s => s.id == subjectId);

            if (selectedSubject) {
                // Filter teachers by subject
                const filteredTeachers = teachers.filter(teacher =>
                    teacher.mata_pelajaran === selectedSubject.nama
                );

                // Add filtered teachers to dropdown
                filteredTeachers.forEach(teacher => {
                    const option = document.createElement('option');
                    option.value = teacher.id;
                    option.textContent = `👨‍🏫 ${teacher.name} (${teacher.mata_pelajaran})`;
                    if (teacher.id == currentTeacherId) {
                        option.selected = true;
                    }
                    teacherSelect.appendChild(option);
                });

                // If no teachers found for this subject, show message
                if (filteredTeachers.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No teachers available for this subject';
                    option.disabled = true;
                    teacherSelect.appendChild(option);
                }
            }
        }
    }

    // Initial load
    loadTeachersForSubject(currentSubjectId);

    // Enhanced form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        let hasErrors = false;
        let errorMessages = [];

        // Validate required fields
        if (!subjectSelect || !subjectSelect.value) {
            hasErrors = true;
            errorMessages.push('Please select a subject');
        }

        if (!teacherSelect || !teacherSelect.value) {
            hasErrors = true;
            errorMessages.push('Please select a teacher');
        }

        if (!classroomSelect || !classroomSelect.value) {
            hasErrors = true;
            errorMessages.push('Please select a classroom');
        }

        if (!daySelect || !daySelect.value) {
            hasErrors = true;
            errorMessages.push('Please select a day');
        }

        // Validate time fields
        if (!startTimeInput || !startTimeInput.value) {
            hasErrors = true;
            errorMessages.push('Please select a start time');
        }

        if (!endTimeInput || !endTimeInput.value) {
            hasErrors = true;
            errorMessages.push('Please select an end time');
        }

        if (startTimeInput && endTimeInput && startTimeInput.value && endTimeInput.value) {
            if (startTimeInput.value >= endTimeInput.value) {
                hasErrors = true;
                errorMessages.push('End time must be after start time');
            }
        }

        // Validate period number
        if (!periodInput || !periodInput.value) {
            hasErrors = true;
            errorMessages.push('Please enter a period number');
        } else {
            const periodNum = parseInt(periodInput.value);
            if (periodNum < 1 || periodNum > 10) {
                hasErrors = true;
                errorMessages.push('Period number must be between 1 and 10');
            }
        }

        // Show validation errors
        if (hasErrors) {
            e.preventDefault();
            alert(errorMessages.join('\n'));
            return false;
        }

        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Updating Schedule...
            `;
        }
    });

    // Real-time validation feedback
    function addValidationStyling(input, isValid) {
        if (isValid) {
            input.classList.remove('border-red-300', 'focus:border-red-500', 'focus:ring-red-500');
            input.classList.add('border-green-300', 'focus:border-green-500', 'focus:ring-green-500');
        } else {
            input.classList.remove('border-green-300', 'focus:border-green-500', 'focus:ring-green-500');
            input.classList.add('border-red-300', 'focus:border-red-500', 'focus:ring-red-500');
        }
    }

    // Add real-time validation for time fields
    if (startTimeInput && endTimeInput) {
        [startTimeInput, endTimeInput].forEach(input => {
            input.addEventListener('change', function() {
                if (startTimeInput.value && endTimeInput.value) {
                    if (startTimeInput.value >= endTimeInput.value) {
                        addValidationStyling(endTimeInput, false);
                    } else {
                        addValidationStyling(startTimeInput, true);
                        addValidationStyling(endTimeInput, true);
                    }
                }
            });
        });
    }

    // Add real-time validation for period number
    if (periodInput) {
        periodInput.addEventListener('input', function() {
            const value = parseInt(this.value);
            if (this.value && (value < 1 || value > 10)) {
                addValidationStyling(this, false);
            } else if (this.value) {
                addValidationStyling(this, true);
            }
        });
    }

    // Auto-save notes (optional enhancement)
    let notesTimeout;
    if (notesTextarea) {
        notesTextarea.addEventListener('input', function() {
            clearTimeout(notesTimeout);
            notesTimeout = setTimeout(() => {
                // Could implement auto-save here if needed
                console.log('Notes updated:', this.value);
            }, 1000);
        });
    }
});
</script>
@endsection
