@php
    $currency = static fn ($value) => format_rupiah($value);
@endphp

{{-- Form pencatatan pembayaran di klinik (offline). Dipakai di halaman detail
     pesanan dan halaman verifikasi pengambilan supaya tidak ada dua form
     berbeda untuk pekerjaan yang sama. Total tagihan hanya DITAMPILKAN:
     nilainya selalu diambil backend dari pesanan di database. --}}
<form method="POST" action="{{ route('admin.pet-shop.orders.payment', $order) }}"
      class="space-y-3" data-offline-payment data-total="{{ (int) $order->total_amount }}">
    @csrf

    <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
        <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('petshop.pickup_total_due') }}</span>
        <span class="text-sm font-bold tabular-nums text-slate-800 dark:text-slate-200">
            {{ $currency($order->total_amount) }}
        </span>
    </div>
    <p class="text-xs text-slate-500 dark:text-slate-400">
        {{ __('petshop.pickup_record_payment_hint', ['amount' => $currency($order->total_amount)]) }}
    </p>

    <div>
        <label class="admin-label" for="amount_received">{{ __('petshop.pickup_amount_received') }}</label>
        <input id="amount_received" type="number" name="amount_received" min="1" step="1" required
               class="admin-input" value="{{ (int) $order->total_amount }}" data-amount-received>
    </div>

    <div class="flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
        <span>{{ __('petshop.pickup_change') }}</span>
        <span data-change-preview class="text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-200">Rp 0</span>
    </div>
    <p data-change-warning class="hidden text-xs font-medium text-rose-600 dark:text-rose-400">
        {{ __('petshop.pickup_change_invalid') }}
    </p>
    <p class="text-[11px] text-slate-500">{{ __('petshop.pickup_change_hint') }}</p>

    <div>
        <label class="admin-label" for="payment_method">{{ __('petshop.pickup_payment_method') }}</label>
        <select id="payment_method" name="payment_method" class="admin-input" required>
            <option value="cash">{{ __('petshop.payment_method.cash') }}</option>
            <option value="debit_card">{{ __('petshop.payment_method.debit_card') }}</option>
            <option value="credit_card">{{ __('petshop.payment_method.credit_card') }}</option>
            <option value="bank_transfer">{{ __('petshop.payment_method.bank_transfer') }}</option>
            <option value="qris">{{ __('petshop.payment_method.qris') }}</option>
        </select>
    </div>

    <div>
        <label class="admin-label" for="paid_at">{{ __('petshop.pickup_paid_at') }}</label>
        <input id="paid_at" type="datetime-local" name="paid_at" class="admin-input"
               value="{{ now()->format('Y-m-d\TH:i') }}">
    </div>

    <input type="text" name="payment_reference" maxlength="100" class="admin-input"
           placeholder="{{ __('petshop.pickup_payment_reference') }}"
           aria-label="{{ __('petshop.pickup_payment_reference') }}">
    <input type="text" name="notes" maxlength="255" class="admin-input"
           placeholder="{{ __('petshop.pickup_notes') }}" aria-label="{{ __('petshop.pickup_notes') }}">

    <button type="submit" class="admin-btn-secondary w-full justify-center">
        {{ __('petshop.pickup_record_payment') }}
    </button>
</form>

@once
    @push('scripts')
    <script>
        // Pratinjau kembalian. Angka yang DISIMPAN tetap dihitung ulang backend
        // dari total pesanan — ini hanya bantuan tampilan untuk petugas.
        document.querySelectorAll('[data-offline-payment]').forEach(function (form) {
            var total = Number(form.dataset.total || 0);
            var input = form.querySelector('[data-amount-received]');
            var preview = form.querySelector('[data-change-preview]');
            var warning = form.querySelector('[data-change-warning]');
            var submit = form.querySelector('button[type="submit"]');

            if (!input || !preview) return;

            var format = function (value) {
                return 'Rp ' + Math.max(0, value).toLocaleString('id-ID');
            };

            var sync = function () {
                var received = Number(input.value || 0);
                preview.textContent = format(received - total);

                var invalid = received < total;
                if (warning) warning.classList.toggle('hidden', !invalid);
                if (submit) submit.disabled = invalid;
            };

            input.addEventListener('input', sync);
            sync();
        });
    </script>
    @endpush
@endonce
