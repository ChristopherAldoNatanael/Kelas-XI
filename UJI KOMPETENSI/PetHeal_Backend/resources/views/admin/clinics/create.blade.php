@extends('layouts.admin')
@section('title', __('clinics.add_clinic') . ' — ' . ($clinicName ?? 'VCMS') . ' Admin')
@section('header', __('clinics.add_new_clinic'))

@section('content')
<div class="max-w-5xl">
    <a href="{{ route('admin.clinics.index') }}" class="text-sm text-slate-500 hover:text-primary mb-4 inline-flex items-center gap-1">
        <span class="material-symbols-outlined text-base">arrow_back</span> {{ __('clinics.back') }}
    </a>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
            @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {{-- Form --}}
        <form method="POST" action="{{ route('admin.clinics.store') }}" enctype="multipart/form-data" class="lg:col-span-3 glass-card rounded-2xl p-8 border border-slate-200/50 space-y-5">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('clinics.clinic_name') }} *</label>
                    <input type="text" name="name" id="clinic_name" value="{{ old('name') }}" required
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                        placeholder="Happy Paws">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Slug *</label>
                    <input type="text" name="slug" id="clinic_slug" value="{{ old('slug') }}" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition font-mono text-sm"
                        placeholder="{{ __('clinics.slug_example') }}">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('clinics.address') }} *</label>
                <textarea name="address" rows="2" required
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                    placeholder="{{ __('clinics.address_placeholder') }}">{{ old('address') }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('clinics.phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                        placeholder="{{ __('clinics.phone_placeholder') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('clinics.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                        placeholder="{{ __('clinics.email_placeholder') }}">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('clinics.logo_upload') }}</label>
                    <input type="file" name="logo" id="clinic_logo" accept="image/jpeg,image/png,image/webp"
                        class="w-full px-4 py-2 border border-slate-200 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('clinics.primary_color') }} *</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="primary_color" id="clinic_color" value="{{ old('primary_color', '#18C964') }}"
                            class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer">
                        <input type="text" id="color_hex" value="{{ old('primary_color', '#18C964') }}"
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl font-mono text-sm" readonly>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('clinics.description') }}</label>
                <textarea name="description" rows="2"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                    placeholder="{{ __('clinics.description_placeholder') }}">{{ old('description') }}</textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" id="is_active" checked class="rounded">
                <label for="is_active" class="text-sm text-slate-700">{{ __('clinics.is_active') }}</label>
            </div>
            <button type="submit" class="px-6 py-2.5 bg-primary hover:opacity-90 text-white font-semibold rounded-xl transition-all text-sm">{{ __('clinics.create_clinic') }}</button>
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