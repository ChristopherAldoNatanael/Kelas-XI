@extends('layouts.app')

@section('title', 'Create Teacher Leave Request')

@section('page-header')
<div class="bg-gradient-to-r from-purple-600 to-pink-600 dark:from-purple-800 dark:to-pink-800 rounded-2xl p-6 mb-6 text-white">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold mb-2">Create Leave Request</h1>
            <p class="text-purple-100">Submit a new teacher leave request with all necessary details</p>
        </div>
        <div class="hidden md:flex items-center gap-3">
            <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2">
                <i class="fas fa-calendar-plus text-yellow-300"></i>
                <span class="text-sm font-medium">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>
    </div>
</div>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    <form id="leave-form" method="POST" action="{{ route('teacher-leaves.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="adaptive-card-section">
            <div class="p-6">
                <h3 class="text-xl font-bold text-white dark:text-white light:text-gray-900 mb-6">Leave Request Details</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Teacher Selection -->
                    <div class="form-group">
                        <label class="form-label">Teacher <span class="text-red-500">*</span></label>
                        <select name="teacher_id" id="teacher_id" class="form-control" required>
                            <option value="">Select Teacher</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" {{ old('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                    {{ $teacher->name }} {{ $teacher->nama ? "({$teacher->nama})" : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('teacher_id')
                            <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Reason Selection -->
                    <div class="form-group">
                        <label class="form-label">Reason <span class="text-red-500">*</span></label>
                        <select name="reason" id="reason" class="form-control" required>
                            <option value="">Select Reason</option>
                            <option value="sakit" {{ old('reason') == 'sakit' ? 'selected' : '' }}>Sakit</option>
                            <option value="cuti_tahunan" {{ old('reason') == 'cuti_tahunan' ? 'selected' : '' }}>Cuti Tahunan</option>
                            <option value="urusan_keluarga" {{ old('reason') == 'urusan_keluarga' ? 'selected' : '' }}>Urusan Keluarga</option>
                            <option value="acara_resmi" {{ old('reason') == 'acara_resmi' ? 'selected' : '' }}>Acara Resmi</option>
                            <option value="lainnya" {{ old('reason') == 'lainnya' ? 'selected' : '' }}>Lainnya</option>
                        </select>
                        @error('reason')
                            <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Custom Reason (shown when "lainnya" is selected) -->
                    <div class="form-group" id="custom-reason-group" style="display: none;">
                        <label class="form-label">Custom Reason <span class="text-red-500">*</span></label>
                        <input type="text" name="custom_reason" id="custom_reason" class="form-control"
                               value="{{ old('custom_reason') }}" placeholder="Please specify the reason">
                        @error('custom_reason')
                            <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Start Date -->
                    <div class="form-group">
                        <label class="form-label">Start Date <span class="text-red-500">*</span></label>
                        <input type="date" name="start_date" id="start_date" class="form-control"
                               value="{{ old('start_date', now()->format('Y-m-d')) }}" required min="{{ now()->format('Y-m-d') }}">
                        @error('start_date')
                            <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- End Date -->
                    <div class="form-group">
                        <label class="form-label">End Date <span class="text-red-500">*</span></label>
                        <input type="date" name="end_date" id="end_date" class="form-control"
                               value="{{ old('end_date', now()->format('Y-m-d')) }}" required min="{{ now()->format('Y-m-d') }}">
                        @error('end_date')
                            <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Duration Display -->
                    <div class="form-group">
                        <label class="form-label">Duration</label>
                        <div class="bg-slate-100 dark:bg-slate-800 light:bg-gray-100 p-3 rounded-lg">
                            <span id="duration-display" class="font-medium text-slate-700 dark:text-slate-300 light:text-gray-700">1 day</span>
                        </div>
                    </div>
                </div>

                <!-- Substitute Teacher Selection -->
                <div class="mt-6">
                    <h4 class="text-lg font-semibold text-white dark:text-white light:text-gray-900 mb-4">Substitute Teacher</h4>
                    <div class="bg-slate-50 dark:bg-slate-800 light:bg-gray-50 p-4 rounded-lg">
                        <div class="form-group">
                            <label class="form-label">Select Substitute Teacher</label>
                            <select name="substitute_teacher_id" id="substitute_teacher_id" class="form-control">
                                <option value="">No substitute needed / Auto-assign</option>
                                <!-- Options will be loaded via AJAX -->
                            </select>
                            <div class="text-sm text-slate-500 dark:text-slate-400 light:text-gray-500 mt-1">
                                Leave empty for automatic assignment or select a specific teacher
                            </div>
                            @error('substitute_teacher_id')
                                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- File Attachment -->
                <div class="mt-6">
                    <h4 class="text-lg font-semibold text-white dark:text-white light:text-gray-900 mb-4">Attachment (Optional)</h4>
                    <div class="bg-slate-50 dark:bg-slate-800 light:bg-gray-50 p-4 rounded-lg">
                        <div class="form-group">
                            <label class="form-label">Upload Supporting Document</label>
                            <input type="file" name="attachment" id="attachment" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png">
                            <div class="text-sm text-slate-500 dark:text-slate-400 light:text-gray-500 mt-1">
                                Accepted formats: PDF, JPG, PNG. Maximum size: 2MB
                            </div>
                            @error('attachment')
                                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Additional Notes -->
                <div class="mt-6">
                    <div class="form-group">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="4"
                                  placeholder="Any additional information or special requests...">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex flex-col sm:flex-row gap-4 justify-end mt-6">
            <a href="{{ route('teacher-leaves.index') }}" class="btn btn-secondary">
                <i class="fas fa-times mr-2"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane mr-2"></i>Submit Leave Request
            </button>
        </div>
    </form>
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
            showError('Please fill in all required fields');
            return false;
        }

        if (new Date(startDate) > new Date(endDate)) {
            e.preventDefault();
            showError('End date cannot be before start date');
            return false;
        }

        if (reason === 'lainnya') {
            const customReason = document.getElementById('custom_reason').value.trim();
            if (!customReason) {
                e.preventDefault();
                showError('Please specify the custom reason');
                return false;
            }
        }

        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
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
                options += `<option value="${teacher.id}">${teacher.name} ${teacher.nama ? `(${teacher.nama})` : ''}</option>`;
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

// File upload validation
document.getElementById('attachment').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Check file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            showError('File size must be less than 2MB');
            e.target.value = '';
            return;
        }

        // Check file type
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        if (!allowedTypes.includes(file.type)) {
            showError('Only PDF, JPG, and PNG files are allowed');
            e.target.value = '';
            return;
        }
    }
});
</script>
@endsection
