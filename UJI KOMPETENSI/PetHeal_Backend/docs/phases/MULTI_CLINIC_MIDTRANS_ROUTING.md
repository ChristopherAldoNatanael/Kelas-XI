# Midtrans Multi-Klinik & Routing Pembayaran Otomatis

Status: implementasi selesai (2026-11-14), termasuk lanjutan biaya tambahan
rekam medis, rekonsiliasi, dan penguatan VCMS. Dokumen ini melengkapi addendum
"Advanced Multi-Clinic Midtrans Configuration & Automatic Payment Routing".

## Masalah

Sebelumnya semua transaksi online (booking DP/lunas/pelunasan dan Pet Shop)
memakai satu `MIDTRANS_SERVER_KEY` global, sehingga dana seluruh klinik masuk ke
merchant pusat. Konfigurasi merchant Partner lama hanya menunjuk merchant klinik
tanpa benar-benar memakai kredensial merchant tersebut.

## Tujuan

1. Setiap klinik dapat menyimpan kredensial Midtrans-nya sendiri.
2. Transaksi dirutekan otomatis ke akun yang benar, tanpa UI memilih manual.
3. Klinik yang belum pernah mengatur boleh memakai akun pusat **hanya** bila
   kebijakan fallback mengizinkan.
4. Konfigurasi klinik yang rusak/nonaktif **tidak pernah** jatuh diam-diam ke
   akun pusat.
5. Transaksi lama tetap sah setelah kredensial dirotasi.

## Skema Database

| Tabel | Isi |
| --- | --- |
| `clinic_payment_accounts` | Satu baris per klinik: status (`not_configured`, `pending_verification`, `active`, `invalid`, `disabled`, `suspended`), `active_environment`, versi aktif, ringkasan validasi terakhir, persetujuan production. |
| `payment_credential_versions` | Versi kredensial immutable: `environment`, `merchant_id`, `client_key`, `server_key` (encrypted cast, hidden), status (`pending`/`valid`/`invalid`/`retired`), `is_active`, hasil uji. |
| `payment_routing_logs` | Jejak keputusan routing: klinik, payment/payable, sumber (`clinic_account`/`central_fallback`), versi kredensial, merchant, hasil (`resolved`/`rejected`), alasan. |
| `payments` (aditif) | `payment_account_id`, `payment_credential_version_id`, `routing_source`, `merchant_id`. |

`payments.provider_order_id` tetap unik dan menjadi kunci webhook.

## Urutan Routing (wajib, tidak boleh dibalik)

`App\Services\PaymentAccountResolver::resolve()`:

1. **Route terkunci** pada payment lama (`routing_source` + credential version).
   Pelunasan memakai `lockedRouteForBooking()` sehingga mengikuti transaksi
   pertama, bukan konfigurasi terbaru.
2. **Kredensial klinik** yang `valid`, `is_active`, dan environment-nya sama
   dengan environment aplikasi.
3. **Fallback pusat** hanya bila klinik belum pernah menyimpan kredensial
   (`hasEverBeenConfigured() === false`) dan gate mengizinkan:
   - sandbox: `MIDTRANS_CENTRAL_FALLBACK_ENABLED=true`;
   - production: kebijakan di atas **dan**
     `MIDTRANS_CENTRAL_FALLBACK_PRODUCTION_APPROVED=true`.
4. **Tolak** (`PaymentRoutingException`) dengan alasan:
   `clinic_credentials_invalid`, `clinic_credentials_pending`,
   `clinic_account_disabled`, `clinic_account_suspended`,
   `environment_mismatch`, `central_fallback_disabled`,
   `central_credentials_missing`.

Route terkunci milik transaksi lama tetap dipakai walau kebijakan fallback
sekarang nonaktif (`resolveCentral(..., enforceFallbackGate: false)`).

## Keamanan Kredensial

- `server_key` disimpan dengan cast `encrypted` dan ada di `$hidden`; tidak
  pernah dikirim ke API, UI, log, atau Android.
- `client_key` boleh dikirim (dibutuhkan Snap), `merchant_id` bukan autentikasi.
- Menyimpan kredensial **bukan** mengaktifkannya: akun menjadi
  `pending_verification` sampai uji koneksi (`GET /v2/{probe}/status`, 200/404 =
  valid) dan aktivasi berhasil.
- Aktivasi production memerlukan `production_approved_at/by` (super admin) atau
  aktor super admin.
- Kredensial pusat hanya dari env `MIDTRANS_CENTRAL_*` (fallback ke
  `MIDTRANS_*` lama); Clinic Admin tidak dapat mengubahnya.

## Transaksi

`App\Services\PaymentService`:

- `createBookingPayment(Booking, 'dp'|'full'|'remaining', ?orderId)`
  - Nominal selalu dihitung dari kolom booking.
  - Order id: `BOOKING-{id}-{millis}` / `BOOKING-{id}-REMAINING-{millis}`.
  - Request berulang dengan order id sama mengembalikan payment yang sama
    (idempoten); payment pending dengan nominal sama juga dipakai ulang.
- `createPetShopPayment(ProductOrder)` — order id `SHOP-{orderNumber}-{millis}`.
- `createMedicalRecordPayment(MedicalRecord)` — biaya tambahan rekam medis.
  - Nominal = `extra_payment_amount - extra_payment_paid_amount` dari database.
  - Order id `MEDREC-{id}-{millis}`; payment pending dengan nominal sama dipakai
    ulang (double tap tidak membuat dua tagihan).
  - Klinik diambil dari `medical_records.clinic_id` (fallback booking).
  - `Payment.payment_type = medical_record`, `payable_type = MedicalRecord`.
- Setiap payment mencatat akun, versi kredensial, sumber routing, dan merchant.
- `applyToPayable()` menangani `ProductOrder`, `Booking`, dan `MedicalRecord`,
  sehingga webhook/rekonsiliasi/sync manual menulis ke domain yang sama.

API (`/api/payment/snap-token`, `/api/payment/remaining/{booking}`,
`/api/medical-records/{id}/pay`, `/api/pet-shop/orders/{id}/pay`)
mengembalikan bentuk lama `token`/`redirect_url`/`transaction_id` ditambah
`order_id`, `payment_id`, `client_key`, `environment`, `routing_source`.
Endpoint MEDREC juga mengembalikan `gross_amount` hasil hitung server.
Merchant/clinic/amount dari client tidak pernah dipakai. Penolakan routing:
HTTP 422 `code=online_payment_unavailable` + `reason`; kegagalan provider:
HTTP 502.

`/api/payment/sync-status` tetap kompatibel untuk transaksi legacy, tetapi bila
order id sudah punya baris `payments`, status provider diterapkan lewat
`PaymentService::applyProviderNotification()` lebih dulu supaya `payments`,
riwayat status, dan booking/rekam medis tidak pernah berbeda.

## Webhook

Satu endpoint `/api/midtrans/webhook`:

- Signature `SHA512(order_id + status_code + gross_amount + ServerKey)`
  diverifikasi dengan **versi kredensial transaksi** (`resolveLocked`), bukan key
  global saat ini. Transaksi partner lama tetap memakai verifikasi Partner.
- Transaksi legacy tanpa route memakai key pusat (atau `MIDTRANS_SERVER_KEY`).
- `SHOP-*`, `BOOKING-*`, dan `MEDREC-*` memvalidasi `merchant_id`,
  `gross_amount`, dan `currency` terhadap baris `payments` (bila ada).
- `MEDREC-*` dengan baris `payments` diproses lewat
  `PaymentService::applyProviderNotification()`: status payment, riwayat, dan
  `extra_payment_paid_amount/status` ditulis dalam satu alur. Transaksi MEDREC
  legacy tanpa baris payment tetap memakai `PaymentStatusService` lama.
- Semua notifikasi (termasuk signature invalid) disimpan di
  `payment_notifications`; pemrosesan idempoten (status tidak mundur, `paid_at`
  tidak berubah pada duplikat, `payment_events.order_id` unik).

## Rekonsiliasi

`App\Services\PaymentReconciliationService::sync()`:

- Partner lama → `MidtransPartnerGateway`.
- Payment dengan route (`routing_source`/`payment_account_id`) →
  `PaymentAccountResolver::resolveLocked()` lalu `MidtransAccountGateway`, jadi
  status ditanyakan ke merchant yang sama dengan transaksi.
- Payment legacy tanpa route → `MidtransService` pusat (perilaku lama).
- Route terkunci yang tidak valid menulis `reconcile_note` dan tidak pernah
  diam-diam memakai kredensial pusat.
- Merchant, gross, dan currency dari provider dicocokkan sebelum status
  diterapkan.

## VCMS

Menu **Pengaturan Pembayaran** (`admin/payment-settings`, route
`admin.payment-settings.*`):

- Super Admin tanpa klinik terpilih melihat ringkasan semua klinik; memilih
  klinik memakai session context yang sama dengan halaman admin lain.
- Clinic Admin hanya melihat/mengubah kliniknya.
- Aksi: simpan kredensial (versi baru), uji koneksi, aktifkan, nonaktifkan,
  setujui production (super admin).
- Server Key hanya berupa input password; tidak pernah dirender kembali.
- Panel **Sandbox** dan **Production** terpisah: status versi, merchant aktif,
  environment aplikasi saat ini, serta waktu uji/pembaruan terakhir.
- Notification URL punya tombol salin (clipboard + fallback `execCommand`)
  untuk ditempel ke dashboard Midtrans.
- Penjelasan sumber dana ditampilkan agar admin tidak mengira semua pembayaran
  masuk ke akun pusat.
- Aksi production (simpan, aktivasi, persetujuan) meminta konfirmasi password
  (`current_password`); setelah benar, berlaku 15 menit dalam sesi.
- Super Admin mendapat **monitor routing** (12 keputusan terakhir, jumlah
  penolakan 7 hari, notifikasi signature invalid 7 hari) dan **jejak audit**
  perubahan kredensial. Tidak ada secret di kedua panel.

## Environment

```env
MIDTRANS_CENTRAL_SANDBOX_MERCHANT_ID=
MIDTRANS_CENTRAL_SANDBOX_CLIENT_KEY=
MIDTRANS_CENTRAL_SANDBOX_SERVER_KEY=
MIDTRANS_CENTRAL_PRODUCTION_MERCHANT_ID=
MIDTRANS_CENTRAL_PRODUCTION_CLIENT_KEY=
MIDTRANS_CENTRAL_PRODUCTION_SERVER_KEY=
MIDTRANS_CENTRAL_FALLBACK_ENABLED=true
MIDTRANS_CENTRAL_FALLBACK_PRODUCTION_APPROVED=false
```

Environment aplikasi tetap ditentukan `MIDTRANS_ENV` / `MIDTRANS_IS_PRODUCTION`.

## Test

`tests/Feature/ClinicPaymentRoutingTest.php` — **22 test / 139 assertion**:

| Kode | Test |
| --- | --- |
| A, E, F | booking DP memakai merchant klinik, nominal dari DB, idempoten |
| A | dua klinik memakai key masing-masing |
| B | klinik belum terkonfigurasi → fallback pusat (sandbox) |
| C | kredensial pending ditolak, tanpa fallback & tanpa panggilan Snap |
| L | fallback nonaktif menolak klinik belum terkonfigurasi |
| L | fallback production butuh `production_approved` eksplisit |
| D, H | webhook salah key/salah merchant ditolak; rotasi tetap verifikasi versi lama |
| H | transaksi baru memakai versi baru |
| J | webhook duplikat idempoten |
| G | pelunasan mengikuti route DP meski kredensial berubah |
| I, J | Pet Shop ikut routing & webhook menandai order lunas |
| K | Server Key tidak bocor ke respons API, serialisasi, atau halaman VCMS |
| MEDREC | biaya tambahan memakai akun klinik; merchant/clinic/amount client diabaikan |
| MEDREC | webhook MEDREC menolak currency/merchant salah lalu menandai record lunas |
| MEDREC | sync-status manual memperbarui payment + rekam medis |
| Ops | rekonsiliasi memakai akun terkunci, bukan key pusat |
| VCMS | simpan production butuh password; versi klinik lain 404; dokter dialihkan |
| VCMS | pesan validasi berbahasa Indonesia, tampil di panel yang dikirim saja, Server Key tidak ikut ter-flash ke session |

Regresi yang dijalankan setelah perubahan: `PetShopTest` 12/89,
`AdminConsoleSmokeTest` 7/141, `RefundTest` 11/16, `PaymentSplitCalculatorTest`
6/91 — total **36 test / 337 assertion** lulus. `php artisan view:cache` sukses
dan seluruh file yang diubah lolos `php -l`.

## Verifikasi UI, Performa, dan Aksesibilitas

Dijalankan pada `/admin/payment-settings` (super admin, klinik terpilih) di
`http://127.0.0.1:8000`, 14 Nov 2026.

- **Console**: 0 error, 0 warning (Playwright, light & dark).
- **axe-core 4.10.2**: 0 violation pada mode terang dan gelap. Sebelum perbaikan
  ditemukan kontras teks `slate-400` (2,5:1) pada 6 elemen halaman ini, label
  header, dan `page-header`; semuanya dinaikkan ke `slate-500` (4,7:1) dengan
  varian `dark:text-slate-400`.
- **Sisa temuan (pre-existing, di luar addendum)**: `admin-btn-primary` memakai
  teks putih di atas warna primer klinik. Untuk warna klinik terang (mis.
  `#F97316`) rasio hanya 2,8:1. Masalah ini global — dasbor `/admin` yang tidak
  diubah pada addendum ini menghasilkan 19 temuan kontras yang sama. Perbaikan
  memerlukan keputusan design system (menggelapkan `--color-primary` atau
  memilih warna teks berdasarkan luminansi), jadi belum diubah di sini.
- **Performa (Playwright, lokal)**: FCP/LCP 1,22 s, DOMContentLoaded 1,18 s,
  transfer awal 65 KB. Lighthouse tidak dijalankan karena halaman berada di balik
  login admin dan tool Lighthouse MCP tidak bisa menyuntikkan sesi; tidak ada
  skor yang diklaim.
- **Screenshot**: `vcms-desktop-1440.png`, `vcms-mobile-375.png`,
  `vcms-desktop-dark-final.png`, `vcms-validation-error-id.png` (tersimpan di
  folder temp sesi, tidak di-commit).
- **Interaksi diuji**: pilih klinik, tombol salin Notification URL
  (‘Salin’ → ‘Tersalin’), validasi form (pesan Indonesia + `aria-invalid`),
  panel sandbox/production terpisah, monitor routing, jejak audit.

## Batas yang Diketahui

- Refund pembayaran tambahan rekam medis belum memakai gateway; alur refund
  provider saat ini dibatasi untuk Pet Shop. Refund booking tetap melalui
  `RefundService`.
- Refund payment dengan route langsung memakai kredensial versi terkunci; refund
  payment Partner lama tetap memakai gateway Partner.
- Split/komisi platform tidak difabrikasi untuk transaksi direct merchant;
  `PaymentSplitCalculator` dipertahankan untuk histori Partner.
- Kontras `admin-btn-primary` mengikuti warna primer klinik; warna terang
  menghasilkan rasio < 4,5:1 (lihat bagian verifikasi). Perbaikan menunggu
  keputusan design system.
