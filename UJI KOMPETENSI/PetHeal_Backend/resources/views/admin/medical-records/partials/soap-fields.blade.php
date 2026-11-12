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
</div>
