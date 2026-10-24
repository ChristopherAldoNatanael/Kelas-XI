{{--
    Pemilih hari kerja untuk form dokter (create/edit).

    Menggantikan <select multiple> bawaan browser yang tampilannya polos
    (daftar "Senin Selasa Rabu ...") dan merepotkan di ponsel. Nilai tetap
    dikirim sebagai array `available_days[]` supaya controller, validasi, dan
    API tidak berubah sama sekali.

    Nilai terpilih bisa berupa array, JSON string, atau null.
--}}
@props([
    'selected' => [],
    'name' => 'available_days[]',
    'label' => null,
    'hint' => null,
    'id' => 'available-days',
])

@php
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $codes = __('admin.weekday_short');
    $codes = is_array($codes) ? $codes : [];

    $raw = is_array($selected) ? $selected : (json_decode((string) $selected, true) ?? []);
    $selectedDays = collect($raw)
        ->map(fn ($day) => strtolower(trim((string) $day)))
        ->filter(fn ($day) => in_array($day, $days, true))
        ->unique()
        ->values();

    $selectedNames = $selectedDays->map(fn ($day) => __('admin.' . $day));
@endphp

<div data-day-picker
     class="admin-daypicker"
     role="group"
     aria-labelledby="{{ $id }}-label"
     data-count-template="{{ __('admin.day_picker_count') }}"
     data-none-template="{{ __('admin.day_picker_none') }}"
     data-label-template="{{ __('admin.day_picker_selected_label') }}">
    <div class="admin-daypicker__head">
        <div>
            <p id="{{ $id }}-label" class="admin-daypicker__title">
                {{ $label ?? __('admin.available_days') }}
                <span class="text-rose-500">*</span>
            </p>
            <p class="admin-daypicker__hint">{{ $hint ?? __('admin.day_picker_hint') }}</p>
        </div>

        <div class="admin-daypicker__presets">
            <button type="button" class="admin-daypicker__preset" data-day-picker-preset="all">
                <span class="material-symbols-outlined text-[14px] leading-none" aria-hidden="true">select_all</span>
                {{ __('admin.day_picker_all') }}
            </button>
            <button type="button" class="admin-daypicker__preset" data-day-picker-preset="weekdays">
                <span class="material-symbols-outlined text-[14px] leading-none" aria-hidden="true">work</span>
                {{ __('admin.day_picker_weekdays') }}
            </button>
            <button type="button" class="admin-daypicker__preset" data-day-picker-preset="weekend">
                <span class="material-symbols-outlined text-[14px] leading-none" aria-hidden="true">weekend</span>
                {{ __('admin.day_picker_weekend') }}
            </button>
            <button type="button" class="admin-daypicker__preset" data-day-picker-preset="none">
                <span class="material-symbols-outlined text-[14px] leading-none" aria-hidden="true">backspace</span>
                {{ __('admin.day_picker_clear') }}
            </button>
        </div>
    </div>

    <div class="admin-daypicker__grid">
        @foreach ($days as $index => $day)
            <label class="admin-daypicker__tile">
                <input type="checkbox"
                       class="admin-daypicker__input"
                       name="{{ $name }}"
                       value="{{ $day }}"
                       data-day-label="{{ __('admin.' . $day) }}"
                       @checked($selectedDays->contains($day))>
                <span class="admin-daypicker__code">{{ $codes[$index] ?? strtoupper(substr($day, 0, 3)) }}</span>
                <span class="admin-daypicker__name">{{ __('admin.' . $day) }}</span>
                <span class="admin-daypicker__tick" aria-hidden="true">
                    <span class="material-symbols-outlined text-[11px] leading-none">check</span>
                </span>
            </label>
        @endforeach
    </div>

    <div class="admin-daypicker__foot">
        <span class="admin-daypicker__count" data-day-picker-count>
            {{ __('admin.day_picker_count', ['selected' => $selectedDays->count(), 'total' => count($days)]) }}
        </span>
        <span class="admin-daypicker__summary" data-day-picker-summary>
            {{ $selectedNames->isEmpty()
                ? __('admin.day_picker_none')
                : __('admin.day_picker_selected_label') . ' ' . $selectedNames->implode(' · ') }}
        </span>
    </div>

    {{-- Satu elemen pesan untuk dua sumber: error validasi server (langsung
         tampil setelah reload) dan penjaga sisi klien (muncul saat submit
         tanpa memilih hari). --}}
    <p class="admin-daypicker__error {{ $errors->has('available_days') ? '' : 'hidden' }}" data-day-picker-error>
        {{ $errors->first('available_days') ?: __('admin.day_picker_required') }}
    </p>
</div>

<script>
    (function () {
        var WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        var WEEKEND = ['saturday', 'sunday'];

        document.querySelectorAll('[data-day-picker]').forEach(function (root) {
            if (root.getAttribute('data-day-picker-ready') === '1') return;
            root.setAttribute('data-day-picker-ready', '1');

            var inputs = Array.prototype.slice.call(root.querySelectorAll('.admin-daypicker__input'));
            var countEl = root.querySelector('[data-day-picker-count]');
            var summaryEl = root.querySelector('[data-day-picker-summary]');
            var errorEl = root.querySelector('[data-day-picker-error]');
            var form = root.closest('form');

            var countTemplate = root.getAttribute('data-count-template') || ':selected / :total';
            var noneTemplate = root.getAttribute('data-none-template') || '';
            var labelTemplate = root.getAttribute('data-label-template') || '';

            var names = {};
            inputs.forEach(function (input) {
                names[input.value] = input.getAttribute('data-day-label') || input.value;
            });

            function fill(template, values) {
                return String(template).replace(/:(\w+)/g, function (match, key) {
                    return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : match;
                });
            }

            function chosen() {
                return inputs.filter(function (input) { return input.checked; })
                    .map(function (input) { return input.value; });
            }

            function presetValues(preset) {
                if (preset === 'all') return inputs.map(function (input) { return input.value; });
                if (preset === 'weekdays') return WEEKDAYS.slice();
                if (preset === 'weekend') return WEEKEND.slice();
                return [];
            }

            function render() {
                var picked = chosen();

                if (countEl) {
                    countEl.textContent = fill(countTemplate, {
                        selected: picked.length,
                        total: inputs.length
                    });
                }

                if (summaryEl) {
                    summaryEl.textContent = picked.length
                        ? labelTemplate + ' ' + picked.map(function (day) { return names[day] || day; }).join(' · ')
                        : noneTemplate;
                }

                root.querySelectorAll('[data-day-picker-preset]').forEach(function (button) {
                    var preset = button.getAttribute('data-day-picker-preset');
                    if (preset === 'none') {
                        button.classList.remove('is-active');
                        return;
                    }
                    var target = presetValues(preset);
                    var same = picked.length === target.length && target.every(function (day) {
                        return picked.indexOf(day) !== -1;
                    });
                    button.classList.toggle('is-active', same);
                });
            }

            function clearError() {
                if (errorEl) errorEl.classList.add('hidden');
            }

            inputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    render();
                    clearError();
                });
            });

            root.querySelectorAll('[data-day-picker-preset]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var target = presetValues(button.getAttribute('data-day-picker-preset'));
                    inputs.forEach(function (input) {
                        input.checked = target.indexOf(input.value) !== -1;
                    });
                    render();
                    clearError();
                });
            });

            // Minimal satu hari wajib dipilih. Validasi server tetap menjadi
            // jaring pengaman (dan sumber pesan kalau JS tidak termuat).
            if (form) {
                form.addEventListener('submit', function (event) {
                    if (chosen().length > 0) {
                        clearError();
                        return;
                    }
                    event.preventDefault();
                    if (errorEl) {
                        errorEl.classList.remove('hidden');
                        errorEl.scrollIntoView({ block: 'nearest' });
                    }
                });
            }

            render();
        });
    })();
</script>
