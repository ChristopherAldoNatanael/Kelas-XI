@php
    /**
     * Satu baris resep. Dipakai untuk render server maupun template baris baru
     * (index berupa string `__INDEX__` yang diganti skrip).
     *
     * @var int|string $index
     * @var array<string, mixed> $row
     * @var \Illuminate\Support\Collection $medicines
     */
@endphp

<div class="rounded-xl border border-slate-200/70 p-3 dark:border-slate-700" data-prescription-row>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
        <div class="md:col-span-2">
            <label for="prescription_{{ $index }}_medicine_id" class="admin-label">Obat</label>
            <select name="prescription[{{ $index }}][medicine_id]" id="prescription_{{ $index }}_medicine_id" class="admin-input">
                <option value="">— tidak dipakai —</option>
                @foreach($medicines as $medicine)
                    <option value="{{ $medicine->id }}"
                        data-price="{{ (float) $medicine->price }}"
                        @selected((string) ($row['medicine_id'] ?? '') === (string) $medicine->id)>
                        {{ $medicine->name }} · stok
                        {{ format_qty((float) $medicine->stock) }} {{ $medicine->unit }}
                    </option>
                @endforeach
            </select>
            @error("prescription.{$index}.medicine_id") <p class="admin-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="prescription_{{ $index }}_dose" class="admin-label">Dosis</label>
            <input type="text" name="prescription[{{ $index }}][dose]" id="prescription_{{ $index }}_dose"
                   value="{{ $row['dose'] ?? '' }}" class="admin-input" placeholder="2x1">
            @error("prescription.{$index}.dose") <p class="admin-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="prescription_{{ $index }}_quantity" class="admin-label">Jumlah</label>
            <input type="number" name="prescription[{{ $index }}][quantity]" id="prescription_{{ $index }}_quantity"
                   min="0" step="0.01" value="{{ $row['quantity'] ?? 1 }}" class="admin-input">
            @error("prescription.{$index}.quantity") <p class="admin-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="prescription_{{ $index }}_route" class="admin-label">Rute</label>
            <input type="text" name="prescription[{{ $index }}][route]" id="prescription_{{ $index }}_route"
                   value="{{ $row['route'] ?? '' }}" class="admin-input" placeholder="oral / topikal / injeksi">
            @error("prescription.{$index}.route") <p class="admin-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="prescription_{{ $index }}_frequency" class="admin-label">Frekuensi</label>
            <input type="text" name="prescription[{{ $index }}][frequency]" id="prescription_{{ $index }}_frequency"
                   value="{{ $row['frequency'] ?? '' }}" class="admin-input" placeholder="2x sehari">
            @error("prescription.{$index}.frequency") <p class="admin-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="prescription_{{ $index }}_duration_days" class="admin-label">Durasi (hari)</label>
            <input type="number" name="prescription[{{ $index }}][duration_days]" id="prescription_{{ $index }}_duration_days"
                   min="0" step="1" value="{{ $row['duration_days'] ?? '' }}" class="admin-input" placeholder="5">
            @error("prescription.{$index}.duration_days") <p class="admin-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="prescription_{{ $index }}_instruction" class="admin-label">Instruksi pemakaian</label>
            <input type="text" name="prescription[{{ $index }}][instruction]" id="prescription_{{ $index }}_instruction"
                   value="{{ $row['instruction'] ?? '' }}" class="admin-input" placeholder="Sesudah makan">
            @error("prescription.{$index}.instruction") <p class="admin-error">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-4">
            <p class="admin-hint" data-price-hint>Harga mengikuti master obat</p>
        </div>
    </div>
</div>
