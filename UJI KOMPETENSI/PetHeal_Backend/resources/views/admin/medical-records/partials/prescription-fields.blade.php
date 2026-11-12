@php
    /**
     * Resep terstruktur.
     *
     * Hanya obat AKTIF milik klinik rekam medis yang bisa dipilih — server
     * memvalidasi hal yang sama, jadi id klinik lain tetap ditolak walau
     * dikirim manual.
     *
     * @var \App\Models\MedicalRecord|null $record
     * @var \Illuminate\Support\Collection $medicines
     */
    $record = $record ?? null;
    $medicines = $medicines ?? collect();

    $existing = $record
        ? $record->prescriptionItems->map(fn ($i) => [
            'medicine_id' => $i->medicine_id,
            'quantity' => (float) $i->quantity,
            'dose' => $i->dose,
            'unit_price' => (float) $i->unit_price,
        ])->all()
        : [];

    // Selalu sediakan tiga baris kosong supaya form bisa langsung diisi
    // tanpa harus menekan tombol tambah.
    $rows = old('prescription', $existing ?: array_fill(0, 3, ['medicine_id' => null, 'quantity' => 1, 'dose' => null, 'unit_price' => null]));
@endphp

<div class="space-y-4" x-data="{ rows: {{ count($rows) }} }">
    <div>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Resep obat</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Pilih obat dari stok klinik. Stok berkurang otomatis saat rekam medis disimpan,
            dan biaya obat dihitung dari harga satuan × jumlah.
        </p>
    </div>

    @if($medicines->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-300">
            Belum ada obat aktif di stok klinik ini. Resep tetap bisa ditulis di kolom
            <strong>Obat</strong> sebagai teks, tetapi stok tidak akan berkurang.
            <a href="{{ route('admin.medicines.create') }}" class="underline">Tambah obat</a>.
        </div>
    @else
        @for($i = 0; $i < max(count($rows), 1); $i++)
            @php $row = $rows[$i] ?? []; @endphp
            <div class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200/70 p-3 md:grid-cols-[2fr_1fr_1fr_1fr] dark:border-slate-700">
                <div>
                    <label for="prescription_{{ $i }}_medicine_id" class="admin-label">Obat</label>
                    <select name="prescription[{{ $i }}][medicine_id]" id="prescription_{{ $i }}_medicine_id" class="admin-input">
                        <option value="">— tidak dipakai —</option>
                        @foreach($medicines as $medicine)
                            <option value="{{ $medicine->id }}"
                                @selected((string) ($row['medicine_id'] ?? '') === (string) $medicine->id)>
                                {{ $medicine->name }} · stok
                                {{ rtrim(rtrim(number_format((float) $medicine->stock, 2, ',', '.'), '0'), ',') }} {{ $medicine->unit }}
                            </option>
                        @endforeach
                    </select>
                    @error("prescription.{$i}.medicine_id") <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="prescription_{{ $i }}_dose" class="admin-label">Dosis</label>
                    <input type="text" name="prescription[{{ $i }}][dose]" id="prescription_{{ $i }}_dose"
                           value="{{ $row['dose'] ?? '' }}" class="admin-input" placeholder="2x1">
                </div>

                <div>
                    <label for="prescription_{{ $i }}_quantity" class="admin-label">Jumlah</label>
                    <input type="number" name="prescription[{{ $i }}][quantity]" id="prescription_{{ $i }}_quantity"
                           min="0" step="0.01" value="{{ $row['quantity'] ?? 1 }}" class="admin-input">
                    @error("prescription.{$i}.quantity") <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="prescription_{{ $i }}_unit_price" class="admin-label">Harga satuan</label>
                    <input type="number" name="prescription[{{ $i }}][unit_price]" id="prescription_{{ $i }}_unit_price"
                           min="0" step="0.01" value="{{ $row['unit_price'] ?? '' }}" class="admin-input"
                           placeholder="otomatis">
                    <p class="admin-hint">Kosongkan untuk memakai harga master.</p>
                </div>
            </div>
        @endfor

        <p class="text-[11px] text-slate-500 dark:text-slate-400">
            Baris dengan obat kosong diabaikan. Biaya obat otomatis menggantikan isian biaya obat manual
            bila resep terisi.
        </p>
    @endif
</div>
