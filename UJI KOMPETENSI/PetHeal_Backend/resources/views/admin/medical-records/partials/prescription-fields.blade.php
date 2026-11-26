@php
    /**
     * Resep terstruktur.
     *
     * Hanya obat AKTIF milik klinik rekam medis yang bisa dipilih — server
     * memvalidasi hal yang sama, jadi id klinik lain tetap ditolak walau
     * dikirim manual.
     *
     * Harga TIDAK diisi di form. Nilainya selalu diambil dari master obat di
     * server supaya tagihan tidak bisa diubah dari payload, dan harga yang
     * tercetak di resume medis sama dengan yang dipakai menghitung biaya.
     *
     * @var \App\Models\MedicalRecord|null $record
     * @var \Illuminate\Support\Collection $medicines
     * @var \Illuminate\Support\Collection|null $allergies
     */
    $record = $record ?? null;
    $medicines = $medicines ?? collect();
    $allergies = $allergies ?? collect();

    $existing = $record
        ? $record->prescriptionItems->map(fn ($i) => [
            'medicine_id' => $i->medicine_id,
            'quantity' => (float) $i->quantity,
            'dose' => $i->dose,
            'route' => $i->route,
            'frequency' => $i->frequency,
            'duration_days' => $i->duration_days,
            'instruction' => $i->instruction,
        ])->all()
        : [];

    // Selalu sediakan tiga baris kosong supaya form bisa langsung diisi
    // tanpa harus menekan tombol tambah.
    $emptyRow = [
        'medicine_id' => null, 'quantity' => 1, 'dose' => null,
        'route' => null, 'frequency' => null, 'duration_days' => null, 'instruction' => null,
    ];
    $rows = old('prescription', $existing ?: array_fill(0, 3, $emptyRow));
    $rowCount = max(count($rows), 1);
    // Sama dengan aturan validasi server (`prescription` => max:20).
    $maxRows = 20;
@endphp

<div class="space-y-4" data-prescription data-max-rows="{{ $maxRows }}">
    <div>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Resep obat</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Pilih obat dari stok klinik. Stok berkurang otomatis saat rekam medis disimpan,
            dan biaya obat dihitung dari harga master × jumlah.
            Bila obat yang diresepkan lebih dari tiga, tambahkan baris dengan tombol
            <strong>Tambah obat lain</strong> (opsional).
            Kolom <strong>Biaya obat</strong> di bagian biaya terisi otomatis dari total resep ini.
        </p>
    </div>

    @if($allergies->isNotEmpty())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <strong>Perhatian alergi:</strong>
            {{ $allergies->map(fn ($a) => $a->allergen . ($a->reaction ? ' (' . $a->reaction . ')' : ''))->join(', ') }}.
            Pastikan tidak ada obat yang memicu reaksi tersebut.
        </div>
    @endif

    @if($medicines->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-300">
            Belum ada obat aktif di stok klinik ini. Resep tetap bisa ditulis di kolom
            <strong>Obat</strong> sebagai teks, tetapi stok tidak akan berkurang.
            <a href="{{ route('admin.medicines.create') }}" class="underline">Tambah obat</a>.
        </div>
    @else
        <div class="space-y-3" data-prescription-rows>
            @for($i = 0; $i < $rowCount; $i++)
                @php $row = $rows[$i] ?? $emptyRow; @endphp
                @include('admin.medical-records.partials.prescription-row', [
                    'index' => $i,
                    'row' => $row,
                    'medicines' => $medicines,
                ])
            @endfor
        </div>

        {{-- Baris baru dibuat dari template ini; __INDEX__ diganti nomor unik. --}}
        <template data-prescription-template>
            @include('admin.medical-records.partials.prescription-row', [
                'index' => '__INDEX__',
                'row' => $emptyRow,
                'medicines' => $medicines,
            ])
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
            bila resep terisi. Nomor batch dan tanggal kedaluwarsa dicatat dari batch yang benar-benar
            diserahkan (FEFO) dan muncul di resume medis.
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
            priceHint: row.querySelector('[data-price-hint]'),
        };
    }

    function masterPrice(select) {
        if (!select) return null;
        const option = select.options[select.selectedIndex];
        const price = option ? parseFloat(option.getAttribute('data-price')) : NaN;
        return Number.isFinite(price) ? price : null;
    }

    function syncPriceHint(row) {
        const { medicine, priceHint } = rowFields(row);
        if (!priceHint) return;
        const master = masterPrice(medicine);
        priceHint.textContent = master !== null && master > 0
            ? 'Harga master: ' + rupiah(master)
            : 'Harga mengikuti master obat';
    }

    let autoCost = null;

    function setAutoNote(active) {
        if (!costInput) return;
        let note = costInput.parentElement.querySelector('[data-auto-cost-note]');

        if (active && !note) {
            note = document.createElement('p');
            note.className = 'admin-hint';
            note.setAttribute('data-auto-cost-note', '');
            note.textContent = 'Terisi otomatis dari total resep obat di atas.';
            costInput.insertAdjacentElement('afterend', note);
        } else if (!active && note) {
            note.remove();
        }
    }

    function recalcCost() {
        let total = 0;

        root.querySelectorAll(rowSelector).forEach(function (row) {
            const { medicine, quantity } = rowFields(row);
            if (!medicine || !medicine.value) return;

            const qty = quantity ? parseFloat(quantity.value) : 0;
            if (!Number.isFinite(qty) || qty <= 0) return;

            const unit = masterPrice(medicine);
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
        syncPriceHint(row);
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

        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;
        rows.appendChild(row);
        nextIndex++;
        refreshState();
    });

    refreshState();
    root.querySelectorAll('[data-prescription-row]').forEach(syncPriceHint);
    recalcCost();
})();
</script>
@endpush
