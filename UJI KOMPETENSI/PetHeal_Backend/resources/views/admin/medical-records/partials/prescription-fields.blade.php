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
    $rowCount = max(count($rows), 1);
    // Sama dengan aturan validasi server (`prescription` => max:20).
    $maxRows = 20;
@endphp

<div class="space-y-4" data-prescription data-max-rows="{{ $maxRows }}">
    <div>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Resep obat</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Pilih obat dari stok klinik. Stok berkurang otomatis saat rekam medis disimpan,
            dan biaya obat dihitung dari harga satuan × jumlah.
            Bila obat yang diresepkan lebih dari tiga, tambahkan baris dengan tombol
            <strong>Tambah obat lain</strong> (opsional).
            Kolom <strong>Biaya obat</strong> di bagian biaya terisi otomatis dari total resep ini.
        </p>
    </div>

    @if($medicines->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-300">
            Belum ada obat aktif di stok klinik ini. Resep tetap bisa ditulis di kolom
            <strong>Obat</strong> sebagai teks, tetapi stok tidak akan berkurang.
            <a href="{{ route('admin.medicines.create') }}" class="underline">Tambah obat</a>.
        </div>
    @else
        <div class="space-y-3" data-prescription-rows>
            @for($i = 0; $i < $rowCount; $i++)
                @php $row = $rows[$i] ?? []; @endphp
                <div class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200/70 p-3 md:grid-cols-[2fr_1fr_1fr_1fr] dark:border-slate-700" data-prescription-row>
                    <div>
                        <label for="prescription_{{ $i }}_medicine_id" class="admin-label">Obat</label>
                        <select name="prescription[{{ $i }}][medicine_id]" id="prescription_{{ $i }}_medicine_id" class="admin-input">
                            <option value="">— tidak dipakai —</option>
                            @foreach($medicines as $medicine)
                                <option value="{{ $medicine->id }}"
                                    data-price="{{ (float) $medicine->price }}"
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
        </div>

        {{-- Baris baru dibuat dari template ini; __INDEX__ diganti nomor unik. --}}
        <template data-prescription-template>
            <div class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200/70 p-3 md:grid-cols-[2fr_1fr_1fr_1fr] dark:border-slate-700" data-prescription-row>
                <div>
                    <label for="prescription___INDEX___medicine_id" class="admin-label">Obat</label>
                    <select name="prescription[__INDEX__][medicine_id]" id="prescription___INDEX___medicine_id" class="admin-input">
                        <option value="">— tidak dipakai —</option>
                        @foreach($medicines as $medicine)
                            <option value="{{ $medicine->id }}" data-price="{{ (float) $medicine->price }}">
                                {{ $medicine->name }} · stok
                                {{ rtrim(rtrim(number_format((float) $medicine->stock, 2, ',', '.'), '0'), ',') }} {{ $medicine->unit }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="prescription___INDEX___dose" class="admin-label">Dosis</label>
                    <input type="text" name="prescription[__INDEX__][dose]" id="prescription___INDEX___dose"
                           value="" class="admin-input" placeholder="2x1">
                </div>

                <div>
                    <label for="prescription___INDEX___quantity" class="admin-label">Jumlah</label>
                    <input type="number" name="prescription[__INDEX__][quantity]" id="prescription___INDEX___quantity"
                           min="0" step="0.01" value="1" class="admin-input">
                </div>

                <div>
                    <label for="prescription___INDEX___unit_price" class="admin-label">Harga satuan</label>
                    <input type="number" name="prescription[__INDEX__][unit_price]" id="prescription___INDEX___unit_price"
                           min="0" step="0.01" value="" class="admin-input" placeholder="otomatis">
                    <p class="admin-hint">Kosongkan untuk memakai harga master.</p>
                </div>
            </div>
        </template>

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="admin-btn-secondary" data-prescription-add>
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                Tambah obat lain
            </button>
            <p class="text-[11px] text-slate-500 dark:text-slate-400" data-prescription-limit hidden>
                Batas maksimal {{ $maxRows }} obat per resep sudah tercapai.
            </p>
        </div>

        <p class="text-[11px] text-slate-500 dark:text-slate-400">
            Baris dengan obat kosong diabaikan. Biaya obat otomatis menggantikan isian biaya obat manual
            bila resep terisi.
        </p>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const root = document.querySelector('[data-prescription]');
    if (!root) return;

    const rows = root.querySelector('[data-prescription-rows]');
    const template = root.querySelector('[data-prescription-template]');
    const addButton = root.querySelector('[data-prescription-add]');
    const limitNote = root.querySelector('[data-prescription-limit]');
    if (!rows || !template || !addButton) return;

    // Nomor baris lanjut setelah baris yang sudah dirender server, supaya
    // index lama (termasuk old input setelah validasi gagal) tidak tertimpa.
    let nextIndex = {{ $rowCount }};
    const maxRows = parseInt(root.dataset.maxRows, 10) || {{ $maxRows }};

    const countRows = () => rows.querySelectorAll('[data-prescription-row]').length;

    function refreshState() {
        const isFull = countRows() >= maxRows;
        addButton.hidden = isFull;
        if (limitNote) limitNote.hidden = !isFull;
    }

    // ---- Biaya obat otomatis --------------------------------------------
    // Server tetap menghitung ulang total dari resep saat menyimpan; skrip ini
    // hanya menyamakan angka yang terlihat di kolom "Biaya obat" supaya tidak
    // tertinggal di 0. Harga memakai isian "Harga satuan" bila diisi, atau
    // harga master obat (data-price pada option) bila dikosongkan.
    const form = root.closest('form');
    const costInput = form ? form.querySelector('[name="medicine_cost"]') : null;
    const rowSelector = '[data-prescription-rows] [data-prescription-row]';

    function rupiah(value) {
        return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);
    }

    function rowFields(row) {
        return {
            medicine: row.querySelector('select[name$="[medicine_id]"]'),
            quantity: row.querySelector('input[name$="[quantity]"]'),
            price: row.querySelector('input[name$="[unit_price]"]'),
        };
    }

    function masterPrice(select) {
        if (!select) return null;
        const option = select.options[select.selectedIndex];
        const price = option ? parseFloat(option.getAttribute('data-price')) : NaN;
        return Number.isFinite(price) ? price : null;
    }

    // Tampilkan harga master pada placeholder supaya dokter tahu angka yang
    // dipakai ketika "Harga satuan" dikosongkan.
    function syncPricePlaceholder(row) {
        const { medicine, price } = rowFields(row);
        if (!price) return;
        const master = masterPrice(medicine);
        price.placeholder = master !== null && master > 0
            ? 'otomatis (' + rupiah(master) + ')'
            : 'otomatis';
    }

    let autoCost = null;   // total terakhir yang diisi skrip
    let autoNote = null;   // keterangan kecil di bawah kolom biaya obat

    function setAutoNote(active) {
        if (!costInput) return;

        if (active && !autoNote) {
            autoNote = document.createElement('p');
            autoNote.className = 'admin-hint';
            autoNote.textContent = 'Terisi otomatis dari total resep obat di atas.';
            costInput.insertAdjacentElement('afterend', autoNote);
        } else if (!active && autoNote) {
            autoNote.remove();
            autoNote = null;
        }
    }

    function recalcCost() {
        let total = 0;

        root.querySelectorAll(rowSelector).forEach(function (row) {
            const { medicine, quantity, price } = rowFields(row);
            if (!medicine || !medicine.value) return;

            const qty = quantity ? parseFloat(quantity.value) : 0;
            if (!Number.isFinite(qty) || qty <= 0) return;

            const typed = price && price.value.trim() !== '' ? parseFloat(price.value) : NaN;
            const unit = Number.isFinite(typed) ? typed : masterPrice(medicine);
            if (unit === null || !Number.isFinite(unit) || unit < 0) return;

            total += qty * unit;
        });

        total = Math.round(total * 100) / 100;

        if (!costInput) return;

        if (total > 0) {
            costInput.value = String(total);
            autoCost = total;
            // Saat resep terisi, server memakai total resep dan mengabaikan
            // isian manual, jadi kolomnya dibuat tidak bisa diedit agar jujur.
            costInput.readOnly = true;
            setAutoNote(true);
        } else if (autoCost !== null && parseFloat(costInput.value) === autoCost) {
            // Resep dikosongkan lagi: kembalikan ke biaya obat manual.
            autoCost = null;
            costInput.readOnly = false;
            costInput.value = '0';
            setAutoNote(false);
        }
    }

    function syncRow(row) {
        syncPricePlaceholder(row);
        recalcCost();
    }

    root.addEventListener('input', function (event) {
        if (!event.target.matches('select, input')) return;
        const row = event.target.closest('[data-prescription-row]');
        if (row) syncRow(row);
    });

    root.addEventListener('change', function (event) {
        if (!event.target.matches('select, input')) return;
        const row = event.target.closest('[data-prescription-row]');
        if (row) syncRow(row);
    });

    addButton.addEventListener('click', function () {
        if (countRows() >= maxRows) return;

        const markup = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = markup.trim();

        const row = wrapper.firstElementChild;
        if (!row) return;

        rows.appendChild(row);
        nextIndex++;
        refreshState();
        syncPricePlaceholder(row);
        recalcCost();

        const firstField = row.querySelector('select, input');
        if (firstField) firstField.focus();
    });

    refreshState();
    root.querySelectorAll(rowSelector).forEach(syncPricePlaceholder);
    recalcCost();
})();
</script>
@endpush
