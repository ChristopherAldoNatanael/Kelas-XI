@php
/** @var \Illuminate\Support\ViewErrorBag $errors */
@endphp
@extends('layouts.app')

@section('title', 'Create Teacher Leave Request')

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
                        <span style="color: var(--text-primary); opacity: 0.9;" class="text-sm font-medium">Create Leave Request</span>
                    </div>

                    <div>
                        <h1 class="text-4xl md:text-5xl font-bold mb-3 tracking-tight" style="color: var(--text-primary);">
                            New Leave Application
                        </h1>
                        <p class="text-lg md:text-xl leading-relaxed max-w-2xl" style="color: var(--text-secondary);">
                            Submit a comprehensive teacher leave request with all necessary details and supporting documentation.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $pendingCount ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Pending</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $approvedCount ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Approved</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $rejectedCount ?? 0 }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Rejected</div>
                        </div>
                    </div>
                </div>

                <div class="lg:shrink-0">
                    <div class="flex gap-4">
                        <a href="{{ route('teacher-leaves.index') }}" class="glass-action-button group">
                            <div class="p-4 rounded-xl bg-linear-to-br from-slate-500/20 to-slate-600/20 border border-slate-400/20">
                                <i class="fas fa-arrow-left text-slate-300 text-2xl"></i>
                            </div>
                            <div>
                                <div class="font-semibold" style="color: var(--text-primary);">Back to List</div>
                                <div class="text-sm" style="color: var(--text-secondary);">View all requests</div>
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

    @if($errors->any())
        <div class="mx-6 mb-6 glass-notification glass-notification-error">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-red-500/20">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div style="color: var(--text-primary);">
                    <div class="font-medium mb-1">Please fix the following errors:</div>
                    <ul class="text-sm space-y-1">
                        @foreach($errors->all() as $error)
                            <li>• {{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <!-- Main Form Container -->
    <div class="px-6 space-y-6">
        <form id="leave-form" method="POST" action="{{ route('teacher-leaves.store') }}" enctype="multipart/form-data">
            @csrf

            <!-- Basic Information Section -->
            <div class="glass-morphism-card">
                <div class="p-6">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="p-3 rounded-xl bg-linear-to-br from-blue-500/20 to-blue-600/20 border border-blue-400/20">
                            <i class="fas fa-user-graduate text-blue-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold mb-1" style="color: var(--text-primary);">Basic Information</h3>
                            <p style="color: var(--text-secondary);">Select the teacher and specify the leave reason</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Teacher Selection -->
                        <div class="form-group">
                            <label class="form-label flex items-center gap-2">
                                <i class="fas fa-user text-blue-400"></i>
                                Teacher <span class="text-red-400">*</span>
                            </label>
                            <select name="teacher_id" id="teacher_id" class="glass-form-input" required>
                                <option value="">Select Teacher</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" {{ old('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                        {{ $teacher->nama }}
                                    </option>
                                @endforeach
                            </select>
                            @error('teacher_id')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>{{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Reason Selection -->
                        <div class="form-group">
                            <label class="form-label flex items-center gap-2">
                                <i class="fas fa-question-circle text-purple-400"></i>
                                Leave Reason <span class="text-red-400">*</span>
                            </label>
                            <select name="reason" id="reason" class="glass-form-input" required>
                                <option value="">Select Reason</option>
                                <option value="sakit" {{ old('reason') == 'sakit' ? 'selected' : '' }}>🏥 Medical Leave</option>
                                <option value="cuti_tahunan" {{ old('reason') == 'cuti_tahunan' ? 'selected' : '' }}>🏖️ Annual Leave</option>
                                <option value="urusan_keluarga" {{ old('reason') == 'urusan_keluarga' ? 'selected' : '' }}>👨‍👩‍👧‍👦 Family Matters</option>
                                <option value="acara_resmi" {{ old('reason') == 'acara_resmi' ? 'selected' : '' }}>🎭 Official Event</option>
                                <option value="lainnya" {{ old('reason') == 'lainnya' ? 'selected' : '' }}>📝 Other</option>
                            </select>
                            @error('reason')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>{{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Custom Reason (shown when "lainnya" is selected) -->
                        <div class="form-group md:col-span-2" id="custom-reason-group" style="display: none;">
                            <label class="form-label flex items-center gap-2">
                                <i class="fas fa-edit text-orange-400"></i>
                                Specify Reason <span class="text-red-400">*</span>
                            </label>
                            <input type="text" name="custom_reason" id="custom_reason" class="glass-form-input"
                                    value="{{ old('custom_reason') }}" placeholder="Please specify the reason for leave">
                            @error('custom_reason')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>{{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date & Duration Section -->
            <div class="glass-morphism-card">
                <div class="p-6">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="p-3 rounded-xl bg-linear-to-br from-green-500/20 to-emerald-600/20 border border-green-400/20">
                            <i class="fas fa-calendar-alt text-green-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold mb-1" style="color: var(--text-primary);">Leave Period</h3>
                            <p style="color: var(--text-secondary);">Specify the start and end dates for the leave</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Start Date -->
                        <div class="form-group">
                            <label class="form-label flex items-center gap-2">
                                <i class="fas fa-play-circle text-green-400"></i>
                                Start Date <span class="text-red-400">*</span>
                            </label>
                            <input type="date" name="start_date" id="start_date" class="glass-form-input"
                                    value="{{ old('start_date', now()->format('Y-m-d')) }}" required min="{{ now()->format('Y-m-d') }}">
                            @error('start_date')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>{{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- End Date -->
                        <div class="form-group">
                            <label class="form-label flex items-center gap-2">
                                <i class="fas fa-stop-circle text-red-400"></i>
                                End Date <span class="text-red-400">*</span>
                            </label>
                            <input type="date" name="end_date" id="end_date" class="glass-form-input"
                                    value="{{ old('end_date', now()->format('Y-m-d')) }}" required min="{{ now()->format('Y-m-d') }}">
                            @error('end_date')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>{{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Duration Display -->
                        <div class="form-group">
                            <label class="form-label flex items-center gap-2">
                                <i class="fas fa-clock text-blue-400"></i>
                                Duration
                            </label>
                            <div class="glass-stat-display">
                                <span id="duration-display" class="font-bold text-lg">1 day</span>
                                <span class="text-sm opacity-75">calculated</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Substitute Teacher Section -->
            <div class="glass-morphism-card">
                <div class="p-6">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="p-3 rounded-xl bg-linear-to-br from-indigo-500/20 to-purple-600/20 border border-indigo-400/20">
                            <i class="fas fa-user-friends text-indigo-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold mb-1" style="color: var(--text-primary);">Substitute Teacher</h3>
                            <p style="color: var(--text-secondary);">Assign a substitute teacher for the leave period</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label flex items-center gap-2">
                            <i class="fas fa-user-plus text-indigo-400"></i>
                            Select Substitute Teacher
                        </label>
                        <select name="substitute_teacher_id" id="substitute_teacher_id" class="glass-form-input">
                            <option value="">🤖 Auto-assign substitute teacher</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                        <div class="text-sm mt-2 flex items-center gap-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle text-blue-400"></i>
                            Leave empty for automatic assignment or select a specific teacher
                        </div>
                        @error('substitute_teacher_id')
                            <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                <i class="fas fa-exclamation-circle"></i>{{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Additional Information Section -->
            <div class="glass-morphism-card">
                <div class="p-6">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="p-3 rounded-xl bg-linear-to-br from-amber-500/20 to-orange-600/20 border border-amber-400/20">
                            <i class="fas fa-file-alt text-amber-400 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold mb-1" style="color: var(--text-primary);">Additional Information</h3>
                            <p style="color: var(--text-secondary);">Provide supporting documents and additional notes</p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <!-- File Attachment -->
                        <div class="form-group">
                            <label class="form-label flex items-center gap-2">
                                <i class="fas fa-paperclip text-amber-400"></i>
                                Supporting Document <span class="text-sm font-normal text-gray-400">(Optional)</span>
                            </label>
                            <div class="file-upload-area" id="file-upload-area">
                                <input type="file" name="attachment" id="attachment" class="hidden"
                                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                <div class="file-upload-content" id="file-upload-content">
                                    <i class="fas fa-cloud-upload-alt text-4xl mb-3" style="color: var(--text-secondary);"></i>
                                    <div class="text-center">
                                        <div class="font-medium mb-1" style="color: var(--text-primary);">Click to upload or drag and drop</div>
                                        <div class="text-sm" style="color: var(--text-secondary);">PDF, JPG, PNG, DOC, DOCX (Max: 2MB)</div>
                                    </div>
                                </div>
                                <!-- File preview area -->
                                <div class="file-preview hidden" id="file-preview">
                                    <div class="flex items-center gap-3 p-3 bg-green-500/10 border border-green-500/20 rounded-lg">
                                        <i class="fas fa-file-alt text-green-400 text-xl"></i>
                                        <div class="flex-1">
                                            <div class="font-medium text-sm" style="color: var(--text-primary);" id="file-name"></div>
                                            <div class="text-xs" style="color: var(--text-secondary);" id="file-size"></div>
                                        </div>
                                        <button type="button" id="remove-file" class="text-red-400 hover:text-red-300">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @error('attachment')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>{{ $message }}
                                </div>
                            @enderror
                        </div>

                        <!-- Additional Notes -->
                        <div class="form-group">
                            <label class="form-label flex items-center gap-2">
                                <i class="fas fa-sticky-note text-amber-400"></i>
                                Additional Notes <span class="text-sm font-normal text-gray-400">(Optional)</span>
                            </label>
                            <textarea name="notes" id="notes" class="glass-form-textarea" rows="4"
                                      placeholder="Any additional information, special requests, or context for this leave request...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="text-red-400 text-sm mt-1 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle"></i>{{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="glass-morphism-card">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row gap-4 justify-end">
                        <a href="{{ route('teacher-leaves.index') }}" class="glass-action-button-secondary group">
                            <div class="p-3 rounded-xl bg-linear-to-br from-slate-500/20 to-slate-600/20 border border-slate-400/20">
                                <i class="fas fa-times text-slate-300 text-xl"></i>
                            </div>
                            <div>
                                <div class="font-semibold" style="color: var(--text-primary);">Cancel</div>
                                <div class="text-sm" style="color: var(--text-secondary);">Return to list</div>
                            </div>
                        </a>
                        <button type="submit" class="glass-action-button-primary group">
                            <div class="p-3 rounded-xl bg-linear-to-br from-blue-500/20 to-blue-600/20 border border-blue-400/20">
                                <i class="fas fa-paper-plane text-blue-300 text-xl"></i>
                            </div>
                            <div>
                                <div class="font-semibold" style="color: var(--text-primary);">Submit Request</div>
                                <div class="text-sm" style="color: var(--text-secondary);">Send for approval</div>
                            </div>
                            <i class="fas fa-arrow-right transition-colors duration-300" style="color: var(--text-secondary);"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
// Form handling and validation
document.addEventListener('DOMContentLoaded', function() {
    setupFormValidation();
    setupDateValidation();
    setupReasonToggle();
    setupSubstituteTeacherLoading();
    calculateDuration();
});

function setupFormValidation() {
    const form = document.getElementById('leave-form');

    form.addEventListener('submit', function(e) {
        // Basic client-side validation
        const teacherId = document.getElementById('teacher_id').value;
        const reason = document.getElementById('reason').value;
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;

        if (!teacherId || !reason || !startDate || !endDate) {
            e.preventDefault();
            showError('Harap isi semua field yang wajib diisi');
            return false;
        }

        if (new Date(startDate) > new Date(endDate)) {
            e.preventDefault();
            showError('Tanggal akhir tidak boleh sebelum tanggal mulai');
            return false;
        }

        if (reason === 'lainnya') {
            const customReason = document.getElementById('custom_reason').value.trim();
            if (!customReason) {
                e.preventDefault();
                showError('Harap sebutkan alasan khusus');
                return false;
            }
        }

        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Mengirim...';
    });
}

function setupDateValidation() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    startDateInput.min = today;
    endDateInput.min = today;

    // Update end date min when start date changes
    startDateInput.addEventListener('change', function() {
        endDateInput.min = this.value;
        if (endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = this.value;
        }
        calculateDuration();
        loadSubstituteTeachers();
    });

    // Recalculate duration when end date changes
    endDateInput.addEventListener('change', function() {
        calculateDuration();
        loadSubstituteTeachers();
    });
}

function setupReasonToggle() {
    const reasonSelect = document.getElementById('reason');
    const customReasonGroup = document.getElementById('custom-reason-group');
    const customReasonInput = document.getElementById('custom_reason');

    reasonSelect.addEventListener('change', function() {
        if (this.value === 'lainnya') {
            customReasonGroup.style.display = 'block';
            customReasonInput.required = true;
        } else {
            customReasonGroup.style.display = 'none';
            customReasonInput.required = false;
            customReasonInput.value = '';
        }
    });
}

function calculateDuration() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const display = document.getElementById('duration-display');

    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

        display.textContent = `${diffDays} day${diffDays > 1 ? 's' : ''}`;
    } else {
        display.textContent = '1 day';
    }
}

function setupSubstituteTeacherLoading() {
    const teacherSelect = document.getElementById('teacher_id');
    const substituteSelect = document.getElementById('substitute_teacher_id');

    teacherSelect.addEventListener('change', loadSubstituteTeachers);
    loadSubstituteTeachers(); // Load initially
}

function loadSubstituteTeachers() {
    const teacherId = document.getElementById('teacher_id').value;
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const substituteSelect = document.getElementById('substitute_teacher_id');

    if (!teacherId || !startDate || !endDate) {
        substituteSelect.innerHTML = '<option value="">No substitute needed / Auto-assign</option>';
        return;
    }

    // Show loading
    substituteSelect.innerHTML = '<option value="">Loading available teachers...</option>';

    fetch(`{{ route('teacher-leaves.substitute-teachers') }}?teacher_id=${teacherId}&start_date=${startDate}&end_date=${endDate}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        let options = '<option value="">No substitute needed / Auto-assign</option>';

        if (data.success && data.data) {
            data.data.forEach(teacher => {
                options += `<option value="${teacher.id}">${teacher.nama || teacher.name || 'Unknown'}</option>`;
            });
        }

        substituteSelect.innerHTML = options;
    })
    .catch(error => {
        console.error('Error loading substitute teachers:', error);
        substituteSelect.innerHTML = '<option value="">Error loading teachers</option>';
    });
}

function showError(message) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert-error');
    existingAlerts.forEach(alert => alert.remove());

    // Create new alert
    const alert = document.createElement('div');
    alert.className = 'alert alert-error mb-4';
    alert.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span>${message}</span>
        </div>
    `;

    // Insert at the top of the form
    const form = document.getElementById('leave-form');
    form.insertBefore(alert, form.firstChild);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

// File upload validation and preview
document.getElementById('attachment').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const filePreview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    const fileSize = document.getElementById('file-size');
    const uploadContent = document.getElementById('file-upload-content');

    if (file) {
        // Check file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            showError('File size must be less than 2MB');
            e.target.value = '';
            filePreview.classList.add('hidden');
            uploadContent.classList.remove('hidden');
            return;
        }

        // Check file type
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!allowedTypes.includes(file.type)) {
            showError('Only PDF, JPG, PNG, DOC, and DOCX files are allowed');
            e.target.value = '';
            filePreview.classList.add('hidden');
            uploadContent.classList.remove('hidden');
            return;
        }

        // Show file preview
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        uploadContent.classList.add('hidden');
        filePreview.classList.remove('hidden');
    } else {
        // No file selected
        filePreview.classList.add('hidden');
        uploadContent.classList.remove('hidden');
    }
});

// Remove file button
document.getElementById('remove-file').addEventListener('click', function() {
    const fileInput = document.getElementById('attachment');
    const filePreview = document.getElementById('file-preview');
    const uploadContent = document.getElementById('file-upload-content');

    fileInput.value = '';
    filePreview.classList.add('hidden');
    uploadContent.classList.remove('hidden');
});

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// File upload drag and drop
const fileUploadArea = document.querySelector('.file-upload-area');
const fileInput = document.getElementById('attachment');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    fileUploadArea.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    fileUploadArea.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    fileUploadArea.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    fileUploadArea.classList.add('drag-over');
}

function unhighlight(e) {
    fileUploadArea.classList.remove('drag-over');
}

fileUploadArea.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    fileInput.files = files;
    fileInput.dispatchEvent(new Event('change'));
}

fileUploadArea.addEventListener('click', function() {
    fileInput.click();
});
</script>

<style>
/* Glass Morphism Variables */
:root {
--card-bg: rgba(255, 255, 255, 0.05);
--text-primary: rgba(255, 255, 255, 0.9);
--text-secondary: rgba(255, 255, 255, 0.7);
--border-color: rgba(255, 255, 255, 0.1);
}

/* Dark mode adjustments */
@media (prefers-color-scheme: dark) {
:root {
    --card-bg: rgba(0, 0, 0, 0.3);
    --text-primary: rgba(255, 255, 255, 0.9);
    --text-secondary: rgba(255, 255, 255, 0.7);
    --border-color: rgba(255, 255, 255, 0.1);
}
}

/* Glass Morphism Card */
.glass-morphism-card {
background: var(--card-bg);
backdrop-filter: blur(20px);
border: 1px solid var(--border-color);
border-radius: 1rem;
box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

/* Glass Form Inputs */
.glass-form-input {
width: 100%;
padding: 0.75rem 1rem;
background: rgba(255, 255, 255, 0.1);
backdrop-filter: blur(10px);
border: 1px solid rgba(255, 255, 255, 0.2);
border-radius: 0.5rem;
color: var(--text-primary);
font-size: 0.875rem;
transition: all 0.3s ease;
}

.glass-form-input:focus {
outline: none;
border-color: rgba(59, 130, 246, 0.5);
box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
background: rgba(255, 255, 255, 0.15);
}

.glass-form-input::placeholder {
color: var(--text-secondary);
}

.glass-form-input option {
background: rgba(0, 0, 0, 0.8);
color: white;
}

/* Glass Form Textarea */
.glass-form-textarea {
width: 100%;
padding: 0.75rem 1rem;
background: rgba(255, 255, 255, 0.1);
backdrop-filter: blur(10px);
border: 1px solid rgba(255, 255, 255, 0.2);
border-radius: 0.5rem;
color: var(--text-primary);
font-size: 0.875rem;
resize: vertical;
transition: all 0.3s ease;
}

.glass-form-textarea:focus {
outline: none;
border-color: rgba(59, 130, 246, 0.5);
box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
background: rgba(255, 255, 255, 0.15);
}

.glass-form-textarea::placeholder {
color: var(--text-secondary);
}

/* Glass Stat Display */
.glass-stat-display {
padding: 1rem;
background: rgba(255, 255, 255, 0.1);
backdrop-filter: blur(10px);
border: 1px solid rgba(255, 255, 255, 0.2);
border-radius: 0.5rem;
display: flex;
flex-direction: column;
align-items: center;
justify-content: center;
color: var(--text-primary);
min-height: 3rem;
}

/* File Upload Area */
.file-upload-area {
    border: 2px dashed rgba(255, 255, 255, 0.3);
    border-radius: 0.75rem;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    position: relative;
}

.file-upload-area:hover {
    border-color: rgba(59, 130, 246, 0.5);
    background: rgba(255, 255, 255, 0.1);
}

.file-upload-area.drag-over {
    border-color: rgba(34, 197, 94, 0.5);
    background: rgba(34, 197, 94, 0.1);
}

/* File Preview */
.file-preview {
    margin-top: 1rem;
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Glass Action Buttons */
.glass-action-button-primary,
.glass-action-button-secondary {
display: flex;
align-items: center;
gap: 1rem;
padding: 1rem 1.5rem;
background: rgba(255, 255, 255, 0.1);
backdrop-filter: blur(10px);
border: 1px solid rgba(255, 255, 255, 0.2);
border-radius: 0.75rem;
color: var(--text-primary);
text-decoration: none;
transition: all 0.3s ease;
cursor: pointer;
}

.glass-action-button-primary:hover,
.glass-action-button-secondary:hover {
background: rgba(255, 255, 255, 0.15);
border-color: rgba(255, 255, 255, 0.3);
transform: translateY(-2px);
box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.glass-action-button-primary {
background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.2));
border-color: rgba(59, 130, 246, 0.3);
}

.glass-action-button-primary:hover {
background: linear-gradient(135deg, rgba(59, 130, 246, 0.3), rgba(37, 99, 235, 0.3));
}

/* Form Labels */
.form-label {
display: block;
font-size: 0.875rem;
font-weight: 600;
color: var(--text-primary);
margin-bottom: 0.5rem;
}

/* Form Groups */
.form-group {
margin-bottom: 1.5rem;
}

/* Glass Notifications */
.glass-notification {
padding: 1rem 1.5rem;
background: var(--card-bg);
backdrop-filter: blur(20px);
border: 1px solid var(--border-color);
border-radius: 0.75rem;
box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.glass-notification-success {
border-color: rgba(34, 197, 94, 0.3);
}

.glass-notification-error {
border-color: rgba(239, 68, 68, 0.3);
}

.glass-notification-info {
border-color: rgba(59, 130, 246, 0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
.glass-morphism-card {
    margin: 0 0.5rem;
}

.glass-action-button-primary,
.glass-action-button-secondary {
    padding: 0.75rem 1rem;
    flex-direction: column;
    text-align: center;
    gap: 0.5rem;
}

.file-upload-area {
    padding: 1.5rem;
}
}

/* Animation for form transitions */
.form-group {
animation: fadeInUp 0.6s ease-out forwards;
}

.form-group:nth-child(1) { animation-delay: 0.1s; }
.form-group:nth-child(2) { animation-delay: 0.2s; }
.form-group:nth-child(3) { animation-delay: 0.3s; }

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

/* Loading states */
button:disabled {
opacity: 0.6;
cursor: not-allowed;
}

/* Focus states for accessibility */
.glass-form-input:focus,
.glass-form-textarea:focus {
outline: 2px solid rgba(59, 130, 246, 0.5);
outline-offset: 2px;
}
</style>
@endsection
