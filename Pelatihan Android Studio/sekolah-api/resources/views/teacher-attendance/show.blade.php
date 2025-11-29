@extends('layouts.app')

@section('title', 'Teacher Attendance Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 dark:from-blue-800 dark:to-purple-800 rounded-2xl p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold mb-2">Teacher Attendance Details</h1>
                <p class="text-blue-100">Detailed information about this attendance record</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('teacher-attendance.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- Attendance Details Card -->
    <div class="adaptive-card-section">
        <div class="p-6">
            <h3 class="text-xl font-bold text-white dark:text-white light:text-gray-900 mb-6">Attendance Information</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 dark:text-slate-300 light:text-gray-600 mb-1">Date</label>
                        <p class="text-lg font-semibold text-white dark:text-white light:text-gray-900">
                            {{ \Carbon\Carbon::parse($attendance->tanggal)->format('l, F j, Y') }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 dark:text-slate-300 light:text-gray-600 mb-1">Check-in Time</label>
                        <p class="text-lg font-semibold text-white dark:text-white light:text-gray-900">
                            {{ $attendance->jam_masuk ? \Carbon\Carbon::parse($attendance->jam_masuk)->format('H:i') : 'Not recorded' }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 dark:text-slate-300 light:text-gray-600 mb-1">Status</label>
                        <div class="flex items-center gap-2">
                            @if($attendance->status === 'hadir')
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle mr-1"></i>Present
                                </span>
                            @elseif($attendance->status === 'telat')
                                <span class="badge badge-warning">
                                    <i class="fas fa-clock mr-1"></i>Late
                                </span>
                            @elseif($attendance->status === 'tidak_hadir')
                                <span class="badge badge-danger">
                                    <i class="fas fa-times-circle mr-1"></i>Absent
                                </span>
                            @elseif($attendance->status === 'diganti')
                                <span class="badge badge-info">
                                    <i class="fas fa-user-friends mr-1"></i>Substitute
                                </span>
                            @else
                                <span class="badge badge-secondary">{{ $attendance->status }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Schedule Information -->
                <div class="space-y-4">
                    @if($attendance->schedule)
                        <div>
                            <label class="block text-sm font-medium text-slate-300 dark:text-slate-300 light:text-gray-600 mb-1">Subject</label>
                            <p class="text-lg font-semibold text-white dark:text-white light:text-gray-900">
                                {{ $attendance->schedule->mata_pelajaran ?? 'N/A' }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-300 dark:text-slate-300 light:text-gray-600 mb-1">Class</label>
                            <p class="text-lg font-semibold text-white dark:text-white light:text-gray-900">
                                {{ $attendance->schedule->kelas ?? 'N/A' }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-300 dark:text-slate-300 light:text-gray-600 mb-1">Schedule Time</label>
                            <p class="text-lg font-semibold text-white dark:text-white light:text-gray-900">
                                {{ $attendance->schedule->jam_mulai ?? 'N/A' }} - {{ $attendance->schedule->jam_selesai ?? 'N/A' }}
                            </p>
                        </div>
                    @else
                        <div class="text-slate-400 dark:text-slate-400 light:text-gray-500">
                            Schedule information not available
                        </div>
                    @endif
                </div>
            </div>

            <!-- Teacher Information -->
            <div class="mt-8">
                <h4 class="text-lg font-semibold text-white dark:text-white light:text-gray-900 mb-4">Teacher Information</h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Main Teacher -->
                    <div class="adaptive-card p-4">
                        <h5 class="font-medium text-white dark:text-white light:text-gray-900 mb-3">
                            @if($attendance->guru_asli)
                                Substitute Teacher
                            @else
                                Teacher
                            @endif
                        </h5>

                        @if($attendance->guru)
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 light:bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-user text-blue-600 dark:text-blue-400 light:text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-white dark:text-white light:text-gray-900">{{ $attendance->guru->name }}</p>
                                    <p class="text-sm text-slate-400 dark:text-slate-400 light:text-gray-500">{{ $attendance->guru->email ?? 'No email' }}</p>
                                </div>
                            </div>
                        @else
                            <p class="text-slate-400 dark:text-slate-400 light:text-gray-500">Teacher information not available</p>
                        @endif
                    </div>

                    <!-- Original Teacher (if substitute) -->
                    @if($attendance->guru_asli)
                        <div class="adaptive-card p-4">
                            <h5 class="font-medium text-white dark:text-white light:text-gray-900 mb-3">Original Teacher</h5>

                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900 light:bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-user text-green-600 dark:text-green-400 light:text-green-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-white dark:text-white light:text-gray-900">{{ $attendance->guru_asli->name }}</p>
                                    <p class="text-sm text-slate-400 dark:text-slate-400 light:text-gray-500">{{ $attendance->guru_asli->email ?? 'No email' }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Additional Information -->
            @if($attendance->created_by_user || $attendance->assigned_by_user)
                <div class="mt-8">
                    <h4 class="text-lg font-semibold text-white dark:text-white light:text-gray-900 mb-4">Additional Information</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @if($attendance->created_by_user)
                            <div>
                                <label class="block text-sm font-medium text-slate-300 dark:text-slate-300 light:text-gray-600 mb-1">Created By</label>
                                <p class="text-lg font-semibold text-white dark:text-white light:text-gray-900">
                                    {{ $attendance->created_by_user->name }}
                                </p>
                            </div>
                        @endif

                        @if($attendance->assigned_by_user)
                            <div>
                                <label class="block text-sm font-medium text-slate-300 dark:text-slate-300 light:text-gray-600 mb-1">Assigned By</label>
                                <p class="text-lg font-semibold text-white dark:text-white light:text-gray-900">
                                    {{ $attendance->assigned_by_user->name }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Notes -->
            @if($attendance->catatan)
                <div class="mt-8">
                    <label class="block text-sm font-medium text-slate-300 dark:text-slate-300 light:text-gray-600 mb-2">Notes</label>
                    <div class="adaptive-card p-4">
                        <p class="text-white dark:text-white light:text-gray-900">{{ $attendance->catatan }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
