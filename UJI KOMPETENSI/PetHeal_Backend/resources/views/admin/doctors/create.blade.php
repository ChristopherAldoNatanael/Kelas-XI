@extends('layouts.admin')

@section('title', 'Add Doctor - PetHeal Admin')
@section('header', 'Add New Doctor')

@section('content')
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div>
            <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Doctor Information</h3>
            <p class="text-xs text-slate-400 mt-0.5">Add a new doctor to the clinic</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.doctors.store') }}" enctype="multipart/form-data" class="p-8">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left Column - Basic Info -->
            <div class="space-y-6">
                <div>
                    <label for="name" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Full Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">
                </div>

                <div>
                    <label for="email" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">
                </div>

                <div>
                    <label for="phone" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Phone Number</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">
                </div>

                <div>
                    <label for="specialization" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Specialization</label>
                    <input type="text" name="specialization" id="specialization" value="{{ old('specialization') }}" required
                        placeholder="e.g., General Veterinary, Surgery, Dermatology"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">
                </div>
            </div>

            <!-- Right Column - Schedule & Photo -->
            <div class="space-y-6">
                <div>
                    <label for="photo" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Doctor Photo</label>
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <div class="w-24 h-24 rounded-full bg-slate-50 dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-600 flex items-center justify-center overflow-hidden" id="photo-preview-container">
                                <img id="photo-preview" class="w-full h-full object-cover hidden">
                                <span class="material-symbols-outlined text-slate-400" id="photo-placeholder">person</span>
                            </div>
                        </div>
                        <div class="flex-1">
                            <input type="file" name="photo" id="photo" accept="image/*"
                                class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all">
                            <p class="text-[10px] text-slate-400 mt-1">JPG, PNG. Max 2MB</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="available_days" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Available Days</label>
                    <select name="available_days[]" id="available_days" multiple required
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">
                        <option value="monday">Monday</option>
                        <option value="tuesday">Tuesday</option>
                        <option value="wednesday">Wednesday</option>
                        <option value="thursday">Thursday</option>
                        <option value="friday">Friday</option>
                        <option value="saturday">Saturday</option>
                        <option value="sunday">Sunday</option>
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">Hold Ctrl/Cmd to select multiple days</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="start_time" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Start Time</label>
                        <input type="time" name="start_time" id="start_time" value="{{ old('start_time', '09:00') }}" required
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">
                    </div>
                    <div>
                        <label for="end_time" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">End Time</label>
                        <input type="time" name="end_time" id="end_time" value="{{ old('end_time', '17:00') }}" required
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_active" id="is_active" value="1" checked
                            class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Active</span>
                    </label>
                    <p class="text-[10px] text-slate-400 mt-1">Inactive doctors won't appear in booking options</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 mt-8 pt-6 border-t border-slate-100 dark:border-slate-800">
            <button type="submit" class="bg-primary hover:bg-emerald-600 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">save</span>
                Save Doctor
            </button>
            <a href="{{ route('admin.doctors.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-6 py-2.5 rounded-xl text-sm font-semibold transition-all">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
    // Photo preview
    document.getElementById('photo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('photo-preview');
        const placeholder = document.getElementById('photo-placeholder');

        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
        }
    });
</script>
@endsection