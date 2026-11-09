@php
    /*
     | Partial lokasi & jam operasional klinik.
     | Dipakai di admin/clinics/{create,edit}.blade.php dan
     | admin/settings/partials/klinik.blade.php supaya field koordinat tidak
     | pernah berbeda antar form (audit menemukan koordinat hanya ada di DB).
     |
     | $clinic : Clinic|null (null pada form create).
     */
    $clinic = $clinic ?? null;
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $dayLabels = [
        'monday' => __('clinics.day_monday'),
        'tuesday' => __('clinics.day_tuesday'),
        'wednesday' => __('clinics.day_wednesday'),
        'thursday' => __('clinics.day_thursday'),
        'friday' => __('clinics.day_friday'),
        'saturday' => __('clinics.day_saturday'),
        'sunday' => __('clinics.day_sunday'),
    ];
    $selectedDays = old('operating_days', $clinic?->operating_days);
    if (is_string($selectedDays)) {
        $selectedDays = array_filter(array_map('trim', explode(',', $selectedDays)));
    }
    $selectedDays = is_array($selectedDays) ? array_map('strtolower', $selectedDays) : null;
    // null = belum pernah diatur → semua hari dicentang (perilaku isOpenNow).
    $checkedDays = $selectedDays ?? $days;
    $openingTime = old('opening_time', $clinic?->opening_time ? substr((string) $clinic->opening_time, 0, 5) : '');
    $closingTime = old('closing_time', $clinic?->closing_time ? substr((string) $clinic->closing_time, 0, 5) : '');
@endphp

<div class="space-y-5">
    <div>
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('clinics.location_title') }}</h3>
        <p class="admin-hint mt-1">{{ __('clinics.location_hint') }}</p>
    </div>

    {{--
        Titik lokasi klinik, dirancang untuk admin non-teknis:
        ketik alamat → buka peta → klik titik klinik → tersimpan.
        Angka koordinat tetap ada tetapi disembunyikan di bagian "opsional":
        hanya dipakai bila admin memang punya koordinat dari Google Maps.
    --}}
    <div class="clinic-pin"
         data-pin-location
         data-msg-ready="{{ __('clinics.pin_status_ready') }}"
         data-msg-empty="{{ __('clinics.pin_status_empty') }}"
         data-msg-choose="{{ __('clinics.pin_msg_choose') }}"
         data-msg-searching="{{ __('clinics.pin_msg_searching') }}"
         data-msg-not-found="{{ __('clinics.pin_msg_not_found') }}"
         data-msg-map-fail="{{ __('clinics.pin_msg_map_fail') }}"
         data-msg-cleared="{{ __('clinics.pin_msg_cleared') }}">

        <p class="admin-label mb-2">{{ __('clinics.pin_title') }}</p>
        <div class="clinic-pin__steps">
            <span class="clinic-pin__step"><span class="clinic-pin__step-no">1</span>{{ __('clinics.pin_step_1') }}</span>
            <span class="clinic-pin__step"><span class="clinic-pin__step-no">2</span>{{ __('clinics.pin_step_2') }}</span>
            <span class="clinic-pin__step"><span class="clinic-pin__step-no">3</span>{{ __('clinics.pin_step_3') }}</span>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="pin_search" class="admin-label">{{ __('clinics.pin_search_label') }}</label>
                <input type="search" id="pin_search" data-search
                       value="{{ old('address', $clinic?->address) }}"
                       data-address-source="#address"
                       class="admin-input" placeholder="{{ __('clinics.pin_search_placeholder') }}">
            </div>
            <div class="flex items-end">
                <button type="button" data-open-map class="admin-btn-primary w-full justify-center">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 21s7-5.686 7-11a7 7 0 1 0-14 0c0 5.314 7 11 7 11Z" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="10" r="2.5"/>
                    </svg>
                    {{ __('clinics.pin_open_map') }}
                </button>
            </div>
        </div>

        <div class="clinic-pin__map-wrap" data-map-wrap hidden>
            <div class="clinic-pin__map" data-map></div>
        </div>
        <div class="clinic-pin__notice" data-notice hidden></div>

        <div class="clinic-pin__status-row">
            <span class="clinic-pin__status" data-status></span>
            <span class="clinic-pin__coords" data-coords hidden></span>
            <a data-gmaps-link href="#" target="_blank" rel="noopener noreferrer"
               class="font-semibold text-primary hover:underline" hidden>{{ __('clinics.pin_view_maps') }}</a>
            <button type="button" data-clear class="font-semibold text-rose-600 hover:underline" hidden>
                {{ __('clinics.pin_clear') }}
            </button>
        </div>

        {{-- Koordinat: tetap dikirim sebagai sumber kebenaran, tapi bukan
             langkah wajib. Sinkron otomatis dari peta. --}}
        {{-- TIDAK boleh hidden/disabled: input di dalam elemen hidden tetap
             terkirim, tetapi atribut `hidden` pada <details> membuat admin
             tidak bisa memeriksanya. Isinya punya CSS sendiri, jadi selalu
             tampil sebagai bagian opsional yang bisa dilipat. --}}
        <details class="clinic-pin__advanced mt-3" id="pin-coords-details">
            <summary>{{ __('clinics.pin_advanced') }}</summary>
            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="latitude" class="admin-label">{{ __('clinics.latitude') }}</label>
                    <input type="number" step="0.0000001" min="-90" max="90" inputmode="decimal"
                           name="latitude" id="latitude" data-lat
                           value="{{ old('latitude', $clinic?->latitude) }}"
                           class="admin-input font-mono @error('latitude') admin-input-error @enderror" placeholder="-6.2088000">
                    @error('latitude') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="longitude" class="admin-label">{{ __('clinics.longitude') }}</label>
                    <input type="number" step="0.0000001" min="-180" max="180" inputmode="decimal"
                           name="longitude" id="longitude" data-lng
                           value="{{ old('longitude', $clinic?->longitude) }}"
                           class="admin-input font-mono @error('longitude') admin-input-error @enderror" placeholder="106.8456000">
                    @error('longitude') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="admin-hint">
                {{ __('clinics.coordinates_hint') }}
                <a href="https://www.google.com/maps" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary hover:underline">{{ __('clinics.coordinates_help_link') }}</a>
            </p>
        </details>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="opening_time" class="admin-label">{{ __('clinics.opening_time') }}</label>
            <input type="time" name="opening_time" id="opening_time" value="{{ $openingTime }}"
                   class="admin-input font-mono @error('opening_time') admin-input-error @enderror">
            @error('opening_time') <p class="admin-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="closing_time" class="admin-label">{{ __('clinics.closing_time') }}</label>
            <input type="time" name="closing_time" id="closing_time" value="{{ $closingTime }}"
                   class="admin-input font-mono @error('closing_time') admin-input-error @enderror">
            @error('closing_time') <p class="admin-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="website" class="admin-label">{{ __('clinics.website') }}</label>
        <input type="url" name="website" id="website" placeholder="https://"
               value="{{ old('website', $clinic?->website) }}"
               class="admin-input @error('website') admin-input-error @enderror">
        @error('website') <p class="admin-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <span class="admin-label">{{ __('clinics.operating_days') }}</span>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach($days as $day)
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 transition hover:border-primary/50 dark:border-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="operating_days[]" value="{{ $day }}"
                           class="h-3.5 w-3.5 rounded border-slate-300 text-primary focus:ring-primary"
                           {{ in_array($day, $checkedDays, true) ? 'checked' : '' }}>
                    {{ $dayLabels[$day] }}
                </label>
            @endforeach
        </div>
        <p class="admin-hint mt-2">{{ __('clinics.operating_days_hint') }}</p>
        @error('operating_days') <p class="admin-error">{{ $message }}</p> @enderror
    </div>
</div>
