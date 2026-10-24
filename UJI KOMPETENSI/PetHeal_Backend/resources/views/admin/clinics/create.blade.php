@extends('layouts.admin')
@section('title', __('clinics.add_clinic') . ' — ' . ($clinicName ?? 'VCMS') . ' Admin')
@section('header', __('clinics.add_new_clinic'))

@section('content')
<div class="max-w-6xl space-y-6">
    <x-admin.page-header
        :title="__('clinics.add_new_clinic')"
        :eyebrow="__('menu.administration')"
        icon="local_hospital"
        :description="__('clinics.manage_all_clinics')">
        <x-slot:actions>
            <a href="{{ route('admin.clinics.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                {{ __('clinics.back') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        {{-- Form --}}
        <form method="POST" action="{{ route('admin.clinics.store') }}" enctype="multipart/form-data" class="lg:col-span-3">
            @csrf
            <x-admin.panel :title="__('clinics.clinic_name')" :subtitle="__('clinics.profile_title')">
                <div class="space-y-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="clinic_name" class="admin-label">{{ __('clinics.clinic_name') }} <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" id="clinic_name" value="{{ old('name') }}" required
                                   class="admin-input" placeholder="Happy Paws">
                        </div>
                        <div>
                            <label for="clinic_slug" class="admin-label">{{ __('common.slug') }} <span class="text-rose-500">*</span></label>
                            <input type="text" name="slug" id="clinic_slug" value="{{ old('slug') }}" required
                                   pattern="[a-z0-9]+(?:-[a-z0-9]+)*" class="admin-input font-mono"
                                   placeholder="{{ __('clinics.slug_example') }}">
                        </div>
                    </div>

                    <div>
                        <label for="address" class="admin-label">{{ __('clinics.address') }} <span class="text-rose-500">*</span></label>
                        <textarea name="address" id="address" rows="2" required class="admin-input"
                                  placeholder="{{ __('clinics.address_placeholder') }}">{{ old('address') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="phone" class="admin-label">{{ __('clinics.phone') }}</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="admin-input"
                                   placeholder="{{ __('clinics.phone_placeholder') }}">
                        </div>
                        <div>
                            <label for="email" class="admin-label">{{ __('clinics.email') }}</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" class="admin-input"
                                   placeholder="{{ __('clinics.email_placeholder') }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="clinic_logo" class="admin-label">{{ __('clinics.logo_upload') }}</label>
                            <input type="file" name="logo" id="clinic_logo" accept="image/jpeg,image/png,image/webp"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <div>
                            <label for="clinic_color" class="admin-label">{{ __('clinics.primary_color') }} <span class="text-rose-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="primary_color" id="clinic_color" value="{{ old('primary_color', '#18C964') }}"
                                       class="h-10 w-10 cursor-pointer rounded-lg border border-slate-200 dark:border-slate-700">
                                <input type="text" id="color_hex" value="{{ old('primary_color', '#18C964') }}"
                                       class="admin-input font-mono" readonly>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="description" class="admin-label">{{ __('clinics.description') }}</label>
                        <textarea name="description" id="description" rows="2" class="admin-input"
                                  placeholder="{{ __('clinics.description_placeholder') }}">{{ old('description') }}</textarea>
                    </div>

                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="checkbox" name="is_active" value="1" id="is_active" checked
                               class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('clinics.is_active') }}</span>
                    </label>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5 dark:border-slate-800">
                        <button type="submit" class="admin-btn-primary">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                            {{ __('clinics.create_clinic') }}
                        </button>
                        <a href="{{ route('admin.clinics.index') }}" class="admin-btn-ghost">{{ __('clinics.cancel') }}</a>
                    </div>
                </div>
            </x-admin.panel>
        </form>

        {{-- Live Preview --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Sidebar Preview --}}
            <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
                <div class="p-4 border-b border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('clinics.preview_sidebar') }}</p>
                </div>
                <div class="bg-gradient-to-b from-emerald-950 via-teal-950 to-slate-950 p-4">
                    <div class="flex items-center gap-3 mb-6">
                        <a href="#" class="bg-white/10 p-1.5 rounded-lg block">
                            <img id="preview_sidebar_logo" src="" alt="" class="h-7 w-auto hidden">
                            <div id="preview_sidebar_initials" class="h-7 w-7 rounded flex items-center justify-center text-white text-xs font-bold" style="background-color: #18C964">
                                PH
                            </div>
                        </a>
                        <span id="preview_sidebar_name" class="font-semibold text-lg text-white tracking-tight">Nama Klinik</span>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-3 px-4 py-2.5 rounded-xl bg-white/5">
                            <span class="material-symbols-outlined text-[18px]" style="color: #18C964">grid_view</span>
                            <span class="text-sm text-white/80">Overview</span>
                        </div>
                        <div class="flex items-center gap-3 px-4 py-2.5 rounded-xl">
                            <span class="material-symbols-outlined text-[18px] text-white/30">group</span>
                            <span class="text-sm text-white/40">Patients</span>
                        </div>
                        <div class="flex items-center gap-3 px-4 py-2.5 rounded-xl">
                            <span class="material-symbols-outlined text-[18px] text-white/30">calendar_today</span>
                            <span class="text-sm text-white/40">Appointments</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card Preview --}}
            <div class="glass-card rounded-2xl p-6 border border-slate-200/50">
                <div class="p-4 border-b border-slate-100 -mx-6 -mt-6 mb-4 rounded-t-2xl">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('clinics.preview_card') }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <div id="preview_card_avatar" class="h-14 w-14 rounded-xl flex items-center justify-center text-white font-bold text-xl" style="background-color: #18C964">
                        PH
                    </div>
                    <div>
                        <p id="preview_card_name" class="font-bold text-lg text-slate-900">Nama Klinik</p>
                        <p id="preview_card_slug" class="text-xs text-slate-400 font-mono">slug</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <div id="preview_card_color_dot" class="w-5 h-5 rounded-full border-2 border-white shadow" style="background-color: #18C964"></div>
                    <span id="preview_card_color_hex" class="text-xs font-mono text-slate-500">#18C964</span>
                </div>
            </div>

            {{-- Header Preview --}}
            <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
                <div class="p-4 border-b border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('clinics.preview_header') }}</p>
                </div>
                <div class="bg-white/70 backdrop-blur-xl border-b border-slate-200/50 px-6 py-3 flex items-center justify-between">
                    <h1 class="text-sm font-semibold text-slate-900 tracking-tight">Dashboard</h1>
                    <div class="flex items-center gap-2">
                        <div id="preview_header_badge" class="flex items-center gap-1.5 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200/60">
                            <span class="material-symbols-outlined text-xs" style="color: #18C964">local_hospital</span>
                            <span id="preview_header_name" class="text-[10px] font-semibold text-slate-600">Nama Klinik</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const nameInput = document.getElementById('clinic_name');
    const slugInput = document.getElementById('clinic_slug');
    const colorInput = document.getElementById('clinic_color');
    const hexInput = document.getElementById('color_hex');
    const logoInput = document.getElementById('clinic_logo');

    const previewSidebarName = document.getElementById('preview_sidebar_name');
    const previewSidebarLogo = document.getElementById('preview_sidebar_logo');
    const previewSidebarInitials = document.getElementById('preview_sidebar_initials');
    const previewCardAvatar = document.getElementById('preview_card_avatar');
    const previewCardName = document.getElementById('preview_card_name');
    const previewCardSlug = document.getElementById('preview_card_slug');
    const previewCardColorDot = document.getElementById('preview_card_color_dot');
    const previewCardColorHex = document.getElementById('preview_card_color_hex');
    const previewHeaderName = document.getElementById('preview_header_name');

    function getInitials(name) {
        const clean = name.replace(/[^A-Za-z\s]/g, '').trim();
        const words = clean.split(/\s+/);
        if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase();
        if (clean.length >= 2) return clean.substring(0, 2).toUpperCase();
        return 'PH';
    }

    function autoSlug(name) {
        return name.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    }

    function updatePreview() {
        const name = nameInput.value || 'Nama Klinik';
        const slug = slugInput.value || 'slug';
        const color = colorInput.value;
        const initials = getInitials(name);

        // Sidebar
        previewSidebarName.textContent = name;
        previewSidebarInitials.textContent = initials;
        previewSidebarInitials.style.backgroundColor = color;

        // Card
        previewCardName.textContent = name;
        previewCardSlug.textContent = slug;
        previewCardAvatar.textContent = initials;
        previewCardAvatar.style.backgroundColor = color;
        previewCardColorDot.style.backgroundColor = color;
        previewCardColorHex.textContent = color;

        // Header
        previewHeaderName.textContent = name;

        // Color hex input
        hexInput.value = color;
    }

    // Auto-generate slug from name (only if slug is empty or matches previous auto-slug)
    let lastAutoSlug = '';
    nameInput.addEventListener('input', () => {
        if (!slugInput.value || slugInput.value === lastAutoSlug) {
            lastAutoSlug = autoSlug(nameInput.value);
            slugInput.value = lastAutoSlug;
        }
        updatePreview();
    });

    slugInput.addEventListener('input', updatePreview);
    colorInput.addEventListener('input', updatePreview);

    // Logo preview
    logoInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (ev) => {
                previewSidebarLogo.src = ev.target.result;
                previewSidebarLogo.classList.remove('hidden');
                previewSidebarInitials.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            previewSidebarLogo.classList.add('hidden');
            previewSidebarInitials.classList.remove('hidden');
        }
    });

    // Initialize
    updatePreview();
</script>
@endsection