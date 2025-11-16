@extends('layouts.app')

@section('title', 'Edit Schedule')

@section('content')
<div class="container mx-auto px-6 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Modern Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    Edit Schedule
                </h1>
                <p class="text-gray-600 mt-1">Update schedule information and settings</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('web-schedules.show', $schedule->id) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    View Details
                </a>
                <a href="{{ route('web-schedules.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to List
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        @if(session('success'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg animate-fade-in">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg animate-fade-in">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <!-- Modern Form Card -->
        <div class="bg-white shadow-2xl rounded-2xl overflow-hidden transform transition-all duration-300 hover:shadow-3xl">
            <!-- Card Header -->
            <div class="bg-gradient-to-r from-purple-50 to-indigo-100 px-6 py-4 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Schedule Information</h3>
                        <p class="text-sm text-gray-600">Update the schedule details below</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('web-schedules.update', $schedule->id) }}" class="p-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Hari Selection -->
                    <div>
                        <label for="hari" class="block text-sm font-medium text-gray-700 mb-2">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Hari
                            </span>
                        </label>
                        <div class="relative">
                            <select id="hari" name="hari" required
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 transition-colors duration-200">
                                <option value="">Pilih Hari</option>
                                <option value="Senin" {{ ($schedule->hari ?? '') == 'Senin' ? 'selected' : '' }}>Senin</option>
                                <option value="Selasa" {{ ($schedule->hari ?? '') == 'Selasa' ? 'selected' : '' }}>Selasa</option>
                                <option value="Rabu" {{ ($schedule->hari ?? '') == 'Rabu' ? 'selected' : '' }}>Rabu</option>
                                <option value="Kamis" {{ ($schedule->hari ?? '') == 'Kamis' ? 'selected' : '' }}>Kamis</option>
                                <option value="Jumat" {{ ($schedule->hari ?? '') == 'Jumat' ? 'selected' : '' }}>Jumat</option>
                                <option value="Sabtu" {{ ($schedule->hari ?? '') == 'Sabtu' ? 'selected' : '' }}>Sabtu</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        @error('hari')
                            <p class="mt-1 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Kelas Input -->
                    <div>
                        <label for="kelas" class="block text-sm font-medium text-gray-700 mb-2">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                Kelas
                            </span>
                        </label>
                        <input type="text" id="kelas" name="kelas" value="{{ $schedule->kelas ?? '' }}" required
                               class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 transition-colors duration-200"
                               placeholder="Contoh: X RPL, XI IPA">
                        @error('kelas')
                            <p class="mt-1 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Mata Pelajaran Input -->
                    <div class="md:col-span-2">
                        <label for="mata_pelajaran" class="block text-sm font-medium text-gray-700 mb-2">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                                Mata Pelajaran
                            </span>
                        </label>
                        <input type="text" id="mata_pelajaran" name="mata_pelajaran" value="{{ $schedule->mata_pelajaran ?? '' }}" required
                               class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 transition-colors duration-200"
                               placeholder="Contoh: Matematika, Bahasa Indonesia">
                        @error('mata_pelajaran')
                            <p class="mt-1 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Guru Selection -->
                    <div class="md:col-span-2">
                        <label for="guru_id" class="block text-sm font-medium text-gray-700 mb-2">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Guru Pengajar
                            </span>
                        </label>
                        <div class="relative">
                            <select id="guru_id" name="guru_id" required
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 transition-colors duration-200">
                                <option value="">Pilih Guru</option>
                                @foreach($dropdownData['teachers'] ?? [] as $teacher)
                                    <option value="{{ $teacher['id'] }}" {{ ($schedule->guru_id ?? '') == $teacher['id'] ? 'selected' : '' }}>
                                        👨‍🏫 {{ $teacher['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        @error('guru_id')
                            <p class="mt-1 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Time Fields -->
                    <div>
                        <label for="jam_mulai" class="block text-sm font-medium text-gray-700 mb-2">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Jam Mulai
                            </span>
                        </label>
                        <input type="time" id="jam_mulai" name="jam_mulai" value="{{ $schedule->jam_mulai ?? '' }}" required
                               class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 transition-colors duration-200">
                        @error('jam_mulai')
                            <p class="mt-1 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="jam_selesai" class="block text-sm font-medium text-gray-700 mb-2">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Jam Selesai
                            </span>
                        </label>
                        <input type="time" id="jam_selesai" name="jam_selesai" value="{{ $schedule->jam_selesai ?? '' }}" required
                               class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 transition-colors duration-200">
                        @error('jam_selesai')
                            <p class="mt-1 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Ruang Input -->
                    <div class="md:col-span-2">
                        <label for="ruang" class="block text-sm font-medium text-gray-700 mb-2">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                Ruang Kelas
                            </span>
                        </label>
                        <input type="text" id="ruang" name="ruang" value="{{ $schedule->ruang ?? '' }}"
                               class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 transition-colors duration-200"
                               placeholder="Contoh: Ruang 101, Lab Komputer">
                        @error('ruang')
                            <p class="mt-1 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="md:col-span-2 flex flex-col sm:flex-row justify-end items-center space-y-3 sm:space-y-0 sm:space-x-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('web-schedules.index') }}" class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancel
                    </a>
                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 text-white text-sm font-medium rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Update Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
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
            const subjects = @json($dropdownData['subjects'] ?? []);
            const selectedSubject = subjects.find(s => s.id == subjectId);

            if (selectedSubject && selectedSubject.teachers) {
                selectedSubject.teachers.forEach(teacher => {
                    const option = document.createElement('option');
                    option.value = teacher.id;
                    option.textContent = `${teacher.user ? teacher.user.nama : 'Unknown'} (${teacher.teacher_code})`;
                    if (teacher.id == currentTeacherId) {
                        option.selected = true;
                    }
                    teacherSelect.appendChild(option);
                });
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
