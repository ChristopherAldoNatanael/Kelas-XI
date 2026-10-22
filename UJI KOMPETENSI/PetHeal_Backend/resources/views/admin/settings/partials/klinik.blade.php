@php
    $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $clinic->name), 0, 2) ?: 'PH');
    $color = old('primary_color', $clinic->primary_color);
@endphp

<section class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="border-b border-slate-100 p-6 dark:border-slate-800">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('menu.clinic_profile') }}</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Data ini tampil di aplikasi pet owner, invoice, dan tombol bantuan WhatsApp.
                </p>
            </div>
            @if($isSuperAdmin ?? false)
                <a href="{{ route('admin.clinics.edit', $clinic) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">open_in_new</span>
                    Pengaturan lengkap (slug & status)
                </a>
            @endif
        </div>
    </div>

    @if($errors->any())
        <div class="mx-6 mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
        </div>
    @endif

    <div class="p-6">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr,300px]">

            <form method="POST" action="{{ route('admin.clinic-profile.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- Identitas --}}
                <fieldset class="space-y-4">
                    <legend class="mb-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Identitas klinik</legend>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="clinic_name" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('clinics.clinic_name') }} <span class="text-rose-500">*</span></label>
                            <input id="clinic_name" type="text" name="name" value="{{ old('name', $clinic->name) }}" required
                                   class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        </div>
                        <div>
                            <label for="clinic_slug" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Slug aplikasi</label>
                            <input id="clinic_slug" type="text" value="{{ $clinic->slug }}" disabled
                                   class="w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 font-mono text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">
                            <p class="mt-1 text-[11px] text-slate-400">{{ __('clinics.slug_locked') }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="clinic_address" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('clinics.address') }} <span class="text-rose-500">*</span></label>
                        <textarea id="clinic_address" name="address" rows="2" required
                                  class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">{{ old('address', $clinic->address) }}</textarea>
                    </div>

                    <div>
                        <label for="clinic_description" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('clinics.description') }}</label>
                        <textarea id="clinic_description" name="description" rows="2" placeholder="{{ __('clinics.description_placeholder') }}"
                                  class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">{{ old('description', $clinic->description) }}</textarea>
                    </div>
                </fieldset>

                {{-- Kontak --}}
                <fieldset class="space-y-4 border-t border-slate-100 pt-6 dark:border-slate-800">
                    <legend class="mb-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Kontak</legend>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="clinic_phone" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('clinics.phone') }}</label>
                            <input id="clinic_phone" type="text" name="phone" value="{{ old('phone', $clinic->phone) }}" placeholder="{{ __('clinics.phone_placeholder') }}"
                                   class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                            <p class="mt-1 text-[11px] text-slate-400">Nomor WhatsApp ini dipakai tombol Bantuan di aplikasi pet owner.</p>
                        </div>
                        <div>
                            <label for="clinic_email" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('clinics.email') }}</label>
                            <input id="clinic_email" type="email" name="email" value="{{ old('email', $clinic->email) }}" placeholder="{{ __('clinics.email_placeholder') }}"
                                   class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        </div>
                    </div>
                </fieldset>

                {{-- Branding --}}
                <fieldset class="space-y-4 border-t border-slate-100 pt-6 dark:border-slate-800">
                    <legend class="mb-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Logo & warna</legend>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="clinic_logo" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('clinics.logo_upload') }}</label>
                            <input id="clinic_logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp"
                                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:file:bg-slate-800 dark:file:text-slate-200">
                            <p class="mt-1 text-[11px] text-slate-400">Kosongkan jika tidak ingin mengganti logo.</p>
                        </div>
                        <div>
                            <label for="clinic_color" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('clinics.primary_color') }} <span class="text-rose-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <input id="clinic_color" type="color" name="primary_color" value="{{ $color }}"
                                       class="h-10 w-12 cursor-pointer rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-950">
                                <input id="color_hex" type="text" value="{{ $color }}" readonly
                                       class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 font-mono text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">
                            </div>
                        </div>
                    </div>
                </fieldset>

                <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5 dark:border-slate-800">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                        {{ __('clinics.save_changes') }}
                    </button>
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $clinic->is_active ? 'check_circle' : 'pause_circle' }}</span>
                        {{ $clinic->is_active ? 'Klinik aktif' : 'Klinik nonaktif' }}
                    </span>
                </div>
            </form>

            {{-- Live preview --}}
            <aside class="space-y-4">
                <div class="overflow-hidden rounded-xl border border-slate-200/70 dark:border-slate-800">
                    <p class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:border-slate-800 dark:bg-slate-800/50">
                        {{ __('clinics.preview_sidebar') }}
                    </p>
                    <div class="bg-gradient-to-b from-emerald-950 via-teal-950 to-slate-950 p-4">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="block rounded-lg bg-white p-1.5">
                                <img id="preview_sidebar_logo" src="{{ $clinic->logo_url ?? '' }}" alt=""
                                     class="block h-6 w-auto {{ $clinic->logo_url ? '' : 'hidden' }}">
                                <span id="preview_sidebar_initials"
                                      class="flex h-6 w-6 items-center justify-center rounded text-[10px] font-bold text-white {{ $clinic->logo_url ? 'hidden' : '' }}"
                                      style="background-color: {{ $color }}">{{ $initials }}</span>
                            </span>
                            <span id="preview_sidebar_name" class="truncate text-sm font-semibold text-white">{{ $clinic->name }}</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl bg-white/5 px-3 py-2.5">
                            <span id="preview_sidebar_icon" class="material-symbols-outlined text-[18px]" style="color: {{ $color }}">grid_view</span>
                            <span class="text-xs text-white/80">Ringkasan</span>
                        </div>
                        <div class="mt-1 flex items-center gap-3 rounded-xl px-3 py-2.5">
                            <span class="material-symbols-outlined text-[18px] text-white/30">group</span>
                            <span class="text-xs text-white/40">Pasien</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-800">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ __('clinics.preview_card') }}</p>
                    <div class="flex items-center gap-3">
                        <img id="preview_card_img" src="{{ $clinic->logo_url ?? '' }}" alt=""
                             class="h-12 w-12 rounded-xl border border-slate-200 object-cover {{ $clinic->logo_url ? '' : 'hidden' }}">
                        <span id="preview_card_avatar"
                              class="flex h-12 w-12 items-center justify-center rounded-xl text-base font-bold text-white {{ $clinic->logo_url ? 'hidden' : '' }}"
                              style="background-color: {{ $color }}">{{ $initials }}</span>
                        <div class="min-w-0">
                            <p id="preview_card_name" class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $clinic->name }}</p>
                            <p class="truncate font-mono text-[10px] text-slate-400">{{ $clinic->slug }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <span id="preview_card_color_dot" class="h-4 w-4 rounded-full border-2 border-white shadow" style="background-color: {{ $color }}"></span>
                        <span id="preview_card_color_hex" class="font-mono text-[11px] text-slate-500">{{ $color }}</span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

<script>
    (function () {
        const nameInput = document.getElementById('clinic_name');
        const colorInput = document.getElementById('clinic_color');
        const hexInput = document.getElementById('color_hex');
        const logoInput = document.getElementById('clinic_logo');
        if (!nameInput || !colorInput) return;

        const sidebarName = document.getElementById('preview_sidebar_name');
        const sidebarLogo = document.getElementById('preview_sidebar_logo');
        const sidebarInitials = document.getElementById('preview_sidebar_initials');
        const sidebarIcon = document.getElementById('preview_sidebar_icon');
        const cardImg = document.getElementById('preview_card_img');
        const cardAvatar = document.getElementById('preview_card_avatar');
        const cardName = document.getElementById('preview_card_name');
        const cardDot = document.getElementById('preview_card_color_dot');
        const cardHex = document.getElementById('preview_card_color_hex');

        function initialsOf(name) {
            const clean = (name || '').replace(/[^A-Za-z\s]/g, '').trim();
            const words = clean.split(/\s+/).filter(Boolean);
            if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase();
            if (clean.length >= 2) return clean.substring(0, 2).toUpperCase();
            return 'PH';
        }

        function showImage(src) {
            [sidebarLogo, cardImg].forEach(function (img) {
                img.src = src;
                img.classList.remove('hidden');
            });
            [sidebarInitials, cardAvatar].forEach(function (el) {
                el.classList.add('hidden');
            });
        }

        function updatePreview() {
            const name = nameInput.value || 'Nama Klinik';
            const color = colorInput.value;
            const initials = initialsOf(name);

            sidebarName.textContent = name;
            sidebarInitials.textContent = initials;
            sidebarInitials.style.backgroundColor = color;
            sidebarIcon.style.color = color;

            cardName.textContent = name;
            cardAvatar.textContent = initials;
            cardAvatar.style.backgroundColor = color;
            cardDot.style.backgroundColor = color;
            cardHex.textContent = color;
            hexInput.value = color;
        }

        nameInput.addEventListener('input', updatePreview);
        colorInput.addEventListener('input', updatePreview);

        logoInput.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (e) { showImage(e.target.result); };
            reader.readAsDataURL(file);
        });
    })();
</script>
