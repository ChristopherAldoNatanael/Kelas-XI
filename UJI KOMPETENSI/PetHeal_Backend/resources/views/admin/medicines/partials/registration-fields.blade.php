@php
    $medicine = $medicine ?? null;
    $statusOptions = [
        'registered' => 'Terdaftar',
        'not_registered' => 'Tidak terdaftar',
        'unknown' => 'Belum diverifikasi',
        'expired' => 'Kedaluwarsa',
        'withdrawn' => 'Ditarik',
    ];
@endphp

<div class="space-y-5 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
    <div>
        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Registrasi Obat</h3>
        <p class="admin-hint">Wajib untuk penyerahan obat. Status selain "Terdaftar" akan diblokir kecuali dokter memberi alasan override.</p>
    </div>

    <div>
        <label for="registration_number" class="admin-label">Nomor Registrasi</label>
        <input type="text" name="registration_number" id="registration_number"
               value="{{ old('registration_number', $medicine?->registration_number) }}"
               class="admin-input" maxlength="80" placeholder="Contoh: KEMENTAN RI No. ...">
        @error('registration_number') <p class="admin-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="registration_status" class="admin-label">Status Registrasi</label>
        <select name="registration_status" id="registration_status" class="admin-input">
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('registration_status', $medicine?->registration_status ?? 'unknown') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('registration_status') <p class="admin-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="registration_expires_at" class="admin-label">Berlaku Sampai</label>
        <input type="date" name="registration_expires_at" id="registration_expires_at"
               value="{{ old('registration_expires_at', $medicine?->registration_expires_at?->format('Y-m-d')) }}"
               class="admin-input">
        @error('registration_expires_at') <p class="admin-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="approved_species" class="admin-label">Spesies yang Disetujui</label>
        <input type="text" name="approved_species" id="approved_species"
               value="{{ old('approved_species', $medicine?->approved_species) }}"
               class="admin-input" maxlength="255" placeholder="Contoh: Anjing, Kucing">
        @error('approved_species') <p class="admin-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="withdrawal_period" class="admin-label">Masa Henti Obat (Withdrawal)</label>
        <input type="text" name="withdrawal_period" id="withdrawal_period"
               value="{{ old('withdrawal_period', $medicine?->withdrawal_period) }}"
               class="admin-input" maxlength="80" placeholder="Contoh: 7 hari sebelum potong">
        @error('withdrawal_period') <p class="admin-error">{{ $message }}</p> @enderror
    </div>
</div>
