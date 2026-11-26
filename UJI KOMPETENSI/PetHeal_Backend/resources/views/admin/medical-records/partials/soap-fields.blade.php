@php
    /**
     * SOAP (Subjective, Objective, Assessment, Plan).
     *
     * Dipakai form create & edit admin dan portal dokter. Empat field ini
     * opsional: rekam medis lama yang hanya punya diagnosis/treatment tetap
     * valid. Diagnosis & treatment tetap wajib sebagai ringkasan.
     *
     * @var \App\Models\MedicalRecord|null $record
     */
    $record = $record ?? null;
    $soapFields = [
        'subjective' => [
            'label' => 'S — Subjective (keluhan pemilik)',
            'hint' => 'Apa yang disampaikan pemilik: keluhan, riwayat, nafsu makan.',
            'placeholder' => 'Contoh: Pemilik melaporkan kucing muntah 2 hari, tidak mau makan.',
        ],
        'objective' => [
            'label' => 'O — Objective (temuan pemeriksaan)',
            'hint' => 'Hasil pemeriksaan fisik dan penunjang: suhu, berat, temuan klinis.',
            'placeholder' => 'Contoh: BB 3,8 kg, suhu 39,2 °C, dehidrasi ringan, mukosa pucat.',
        ],
        'assessment' => [
            'label' => 'A — Assessment (penilaian)',
            'hint' => 'Diagnosis kerja atau diagnosis banding.',
            'placeholder' => 'Contoh: Suspek gastroenteritis akut, rule out parasit saluran cerna.',
        ],
        'plan' => [
            'label' => 'P — Plan (rencana)',
            'hint' => 'Terapi, tindakan, dan rencana kontrol.',
            'placeholder' => 'Contoh: Cairan Ringer Laktat 100 ml IV, antiemetik, kontrol 3 hari.',
        ],
    ];
@endphp

<div class="space-y-4">
    <div>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Catatan SOAP</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Format catatan medis standar. Semua kolom opsional, tetapi isi yang kosong juga tidak bisa dipulihkan nanti.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        @foreach($soapFields as $field => $meta)
            <div>
                <label for="soap_{{ $field }}" class="admin-label">{{ $meta['label'] }}</label>
                <textarea name="soap[{{ $field }}]" id="soap_{{ $field }}" rows="3" class="admin-input"
                          placeholder="{{ $meta['placeholder'] }}">{{ old('soap.' . $field, $record?->{$field}) }}</textarea>
                <p class="admin-hint">{{ $meta['hint'] }}</p>
                @error('soap.' . $field) <p class="admin-error">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>

    @php
        $vitals = $record?->vitals;
        $vitalFields = [
            'temperature_c' => ['label' => 'Suhu (°C)', 'step' => '0.1', 'placeholder' => '39.2'],
            'heart_rate' => ['label' => 'Frekuensi jantung (/menit)', 'step' => '1', 'placeholder' => '120'],
            'respiratory_rate' => ['label' => 'Frekuensi napas (/menit)', 'step' => '1', 'placeholder' => '30'],
            'crt_seconds' => ['label' => 'CRT (detik)', 'step' => '0.1', 'placeholder' => '1.5'],
            'bcs' => ['label' => 'BCS (1–9)', 'step' => '1', 'placeholder' => '5'],
            'pain_score' => ['label' => 'Skor nyeri (0–4)', 'step' => '1', 'placeholder' => '0'],
            'weight_kg' => ['label' => 'Berat (kg)', 'step' => '0.01', 'placeholder' => '3.8'],
        ];
    @endphp

    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Tanda vital</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Opsional. Diisi bila pemeriksaan fisik dicatat; kosongkan bila tidak diukur.
        </p>

        <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach($vitalFields as $field => $meta)
                <div>
                    <label for="vitals_{{ $field }}" class="admin-label">{{ $meta['label'] }}</label>
                    <input type="number" name="vitals[{{ $field }}]" id="vitals_{{ $field }}"
                           step="{{ $meta['step'] }}" value="{{ old('vitals.' . $field, $vitals?->{$field}) }}"
                           class="admin-input" placeholder="{{ $meta['placeholder'] }}">
                    @error('vitals.' . $field) <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div>
                <label for="vitals_mucosa" class="admin-label">Mukosa</label>
                <input type="text" name="vitals[mucosa]" id="vitals_mucosa"
                       value="{{ old('vitals.mucosa', $vitals?->mucosa) }}" class="admin-input" placeholder="pink / pucat">
            </div>

            <div>
                <label for="vitals_hydration" class="admin-label">Hidrasi</label>
                <input type="text" name="vitals[hydration]" id="vitals_hydration"
                       value="{{ old('vitals.hydration', $vitals?->hydration) }}" class="admin-input" placeholder="normal / ringan">
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700">
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Hasil akhir kunjungan</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Opsional. Dipakai untuk statistik klinik dan surat keterangan.
        </p>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
            <div>
                <label for="outcome" class="admin-label">Outcome</label>
                <select name="outcome" id="outcome" class="admin-input">
                    <option value="">— belum ditentukan —</option>
                    @foreach(['sembuh' => 'Sembuh', 'membaik' => 'Membaik', 'berlanjut' => 'Masih berlanjut', 'dirujuk' => 'Dirujuk', 'mati' => 'Mati', 'euthanasia' => 'Euthanasia'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('outcome', $record?->outcome) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="cause_of_death" class="admin-label">Sebab kematian</label>
                <input type="text" name="cause_of_death" id="cause_of_death"
                       value="{{ old('cause_of_death', $record?->cause_of_death) }}" class="admin-input" placeholder="Diisi bila hewan mati">
            </div>

            <div>
                <label for="disposal_method" class="admin-label">Penanganan jenazah</label>
                <select name="disposal_method" id="disposal_method" class="admin-input">
                    <option value="">— tidak ada —</option>
                    @foreach(['pemakaman' => 'Pemakaman', 'kremasi' => 'Kremasi', 'lainnya' => 'Lainnya'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('disposal_method', $record?->disposal_method) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
