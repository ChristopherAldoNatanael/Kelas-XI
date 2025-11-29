@extends('layouts.app')

@section('title', 'Import Schedules')

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
                        <span class="text-white/90 text-sm font-medium">Bulk Import System</span>
                    </div>

                    <div>
                        <h1 class="text-4xl md:text-5xl font-bold text-white mb-3 tracking-tight">
                            Import Schedules
                        </h1>
                        <p class="text-white/70 text-lg md:text-xl leading-relaxed max-w-2xl">
                            Upload CSV files to bulk import class schedules into your system. Supports validation, error handling, and progress tracking.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-4">
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">CSV Format</div>
                            <div class="text-white/70 text-sm">Supported Format</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">Real-time</div>
                            <div class="text-white/70 text-sm">Progress Tracking</div>
                        </div>
                        <div class="glass-stat-card">
                            <div class="text-2xl font-bold text-white">Validation</div>
                            <div class="text-white/70 text-sm">Error Handling</div>
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

    <!-- Import Form -->
    <div class="px-6 space-y-6">
        <!-- Upload Section -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20 border border-purple-400/20">
                        <i class="fas fa-upload text-purple-300 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">File Upload</h3>
                        <p class="text-slate-300">Select your CSV file to import schedules</p>
                    </div>
                </div>

                <form id="importForm" method="POST" action="{{ route('web-schedules.import.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <!-- File Input -->
                    <div>
                        <label for="file" class="block text-sm font-medium text-white mb-2">
                            <span class="flex items-center">
                                <i class="fas fa-file-upload mr-2 text-purple-400"></i>
                                Select File
                            </span>
                        </label>
                        <div class="relative">
                            <input type="file" id="file" name="file" accept=".csv,.txt"
                                   class="hidden" required>
                            <label for="file" class="glass-file-input cursor-pointer">
                                <div class="flex items-center justify-center gap-4 py-8">
                                    <div class="p-4 rounded-xl bg-gradient-to-br from-slate-500/20 to-slate-600/20 border border-slate-400/20">
                                        <i class="fas fa-cloud-upload-alt text-slate-300 text-2xl"></i>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-white font-medium mb-1">Click to select file</p>
                                        <p class="text-slate-400 text-sm">CSV files up to 10MB</p>
                                        <p class="text-slate-500 text-xs mt-2" id="fileName">No file selected</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                        @error('file')
                            <p class="mt-1 text-sm text-red-400 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Options -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="skip_duplicates" value="1" class="glass-checkbox">
                                <span class="ml-3 text-white">Skip duplicate schedules</span>
                            </label>
                            <p class="text-slate-400 text-sm mt-1">Skip schedules with same day/class/subject/teacher</p>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="update_existing" value="1" class="glass-checkbox">
                                <span class="ml-3 text-white">Update existing schedules</span>
                            </label>
                            <p class="text-slate-400 text-sm mt-1">Update schedule data if same combination exists</p>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end pt-6 border-t border-white/10">
                        <button type="submit" id="importBtn" class="glass-action-btn glass-action-primary disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-upload mr-2"></i>
                            <span id="importBtnText">Start Import</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Progress Section (Hidden initially) -->
        <div id="progressSection" class="glass-morphism-card hidden">
            <div class="p-6">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 border border-blue-400/20">
                        <i class="fas fa-chart-line text-blue-300 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">Import Progress</h3>
                        <p class="text-slate-300">Processing your file...</p>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-white font-medium">Overall Progress</span>
                        <span class="text-slate-300" id="progressText">0%</span>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-3">
                        <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-3 rounded-full transition-all duration-300" id="progressBar" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Status Messages -->
                <div id="statusMessages" class="mt-6 space-y-2"></div>
            </div>
        </div>

        <!-- Template Download -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-purple-500/20 to-violet-500/20 border border-purple-400/20">
                        <i class="fas fa-download text-purple-300 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">Download Template</h3>
                        <p class="text-slate-300">Get a sample file with the correct format</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <a href="{{ route('web-schedules.import.template', ['format' => 'csv']) }}" class="glass-action-btn glass-action-secondary">
                        <i class="fas fa-file-csv mr-2"></i>
                        CSV Template
                    </a>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="glass-morphism-card">
            <div class="p-6">
                <div class="flex items-center gap-4 mb-6">
                    <div class="p-3 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 border border-amber-400/20">
                        <i class="fas fa-info-circle text-amber-300 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">Import Instructions</h3>
                        <p class="text-slate-300">Follow these guidelines for successful imports</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-white font-semibold mb-3">Required Columns</h4>
                        <ul class="space-y-2 text-slate-300">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                <span><strong>hari:</strong> Senin, Selasa, Rabu, Kamis, Jumat, Sabtu, Minggu</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                <span><strong>kelas:</strong> Class name (e.g., X RPL 1)</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                <span><strong>mata_pelajaran:</strong> Subject name</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-400 mr-2"></i>
                                <span><strong>guru_id:</strong> Teacher ID (must exist in teachers table)</span>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-white font-semibold mb-3">Optional Columns</h4>
                        <ul class="space-y-2 text-slate-300">
                            <li class="flex items-center">
                                <i class="fas fa-minus text-slate-400 mr-2"></i>
                                <span><strong>jam_mulai:</strong> Start time (HH:MM:SS)</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-minus text-slate-400 mr-2"></i>
                                <span><strong>jam_selesai:</strong> End time (HH:MM:SS)</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-minus text-slate-400 mr-2"></i>
                                <span><strong>ruang:</strong> Room name</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('file');
    const fileName = document.getElementById('fileName');
    const importForm = document.getElementById('importForm');
    const importBtn = document.getElementById('importBtn');
    const importBtnText = document.getElementById('importBtnText');
    const progressSection = document.getElementById('progressSection');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const statusMessages = document.getElementById('statusMessages');

    // File selection handling
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            fileName.textContent = file.name;
            fileName.className = 'text-green-400 text-xs mt-2 font-medium';
        } else {
            fileName.textContent = 'No file selected';
            fileName.className = 'text-slate-500 text-xs mt-2';
        }
    });

    // Form submission with progress tracking
    importForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        // Show progress section
        progressSection.classList.remove('hidden');
        importBtn.disabled = true;
        importBtnText.textContent = 'Importing...';

        // Start progress simulation
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;

            progressBar.style.width = progress + '%';
            progressText.textContent = Math.round(progress) + '%';
        }, 500);

        // Submit form via fetch for better progress handling
        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            clearInterval(progressInterval);
            progressBar.style.width = '100%';
            progressText.textContent = '100%';

            if (data.success) {
                addStatusMessage('success', `Import completed! ${data.processed} schedules processed.`);
                if (data.errors && data.errors.length > 0) {
                    data.errors.forEach(error => {
                        addStatusMessage('warning', error);
                    });
                }
                importBtnText.textContent = 'Import Complete';
                setTimeout(() => {
                    window.location.href = '{{ route("web-schedules.index") }}';
                }, 3000);
            } else {
                addStatusMessage('error', data.message || 'Import failed');
                importBtn.disabled = false;
                importBtnText.textContent = 'Try Again';
            }
        })
        .catch(error => {
            clearInterval(progressInterval);
            addStatusMessage('error', 'Network error occurred');
            importBtn.disabled = false;
            importBtnText.textContent = 'Try Again';
        });
    });

    function addStatusMessage(type, message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `p-3 rounded-lg text-sm ${
            type === 'success' ? 'bg-green-500/20 text-green-300 border border-green-500/30' :
            type === 'error' ? 'bg-red-500/20 text-red-300 border border-red-500/30' :
            'bg-yellow-500/20 text-yellow-300 border border-yellow-500/30'
        }`;

        const icon = type === 'success' ? 'check-circle' :
                    type === 'error' ? 'exclamation-triangle' : 'exclamation-circle';

        messageDiv.innerHTML = `
            <div class="flex items-center gap-2">
                <i class="fas fa-${icon}"></i>
                <span>${message}</span>
            </div>
        `;

        statusMessages.appendChild(messageDiv);
    }
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

/* File Input */
.glass-file-input {
    display: block;
    border: 2px dashed rgba(255, 255, 255, 0.2);
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.02);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.glass-file-input:hover {
    border-color: rgba(147, 51, 234, 0.5);
    background: rgba(147, 51, 234, 0.05);
}

/* Checkboxes */
.glass-checkbox {
    width: 1.2rem;
    height: 1.2rem;
    border-radius: 0.25rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    transition: all 0.2s ease;
    cursor: pointer;
}

.glass-checkbox:checked {
    background: rgba(147, 51, 234, 0.8);
    border-color: rgba(147, 51, 234, 0.8);
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

/* Responsive Design */
@media (max-width: 768px) {
    .glass-morphism-card {
        margin: 0 1rem;
    }

    .glass-file-input {
        padding: 1rem;
    }
}
</style>
@endsection
