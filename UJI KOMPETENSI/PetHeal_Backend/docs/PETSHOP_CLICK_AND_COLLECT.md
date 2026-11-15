# Pet Shop — Click & Collect, Bukti Pembayaran, dan QR Pickup

Dokumen ini menjelaskan implementasi lanjutan Pet Shop di atas modul yang sudah ada
(katalog, keranjang, checkout, pembayaran Midtrans multi-klinik, VCMS). Tidak ada
aplikasi baru, tidak ada flow pembayaran kedua, dan konsultasi/booking tidak diubah.

## 1. Ringkasan alur

1. Pelanggan membuka detail produk. Aplikasi meminta izin lokasi **hanya saat
   pengguna menekan tombol**, bukan otomatis dan bukan background tracking.
2. Backend menghitung jarak klinik (Haversine) dan mengembalikan daftar klinik
   yang melayani pickup beserta harga/stok masing-masing (stok = `stock_on_hand - stock_reserved`).
3. Memilih klinik lain **memuat ulang produk klinik tersebut** (harga, varian, stok),
   bukan sekadar mengganti label.
4. Checkout tetap satu klinik per pesanan (aturan lama dipertahankan).
5. Pembayaran online lewat `PaymentService` / resolver Midtrans existing, atau bayar di klinik.
6. Bukti pembayaran (JSON + PDF) hanya berstatus lunas bila backend mencatat pembayaran lunas.
7. Setelah klinik menandai `ready_for_pickup`, backend menerbitkan QR Pickup Pass (token acak 64 karakter)
   + kode manual 8 karakter. QR hanya memulai verifikasi.
8. Petugas memverifikasi lewat VCMS (pindai QR / kode manual / nomor pesanan), mencatat pembayaran
   offline bila perlu, lalu mengonfirmasi serah terima. Status pesanan menjadi `completed`,
   stok dikomit sekali, dan pass dinonaktifkan.

## 2. Perbaikan error 500 pembayaran Pet Shop

Penyebab yang ditemukan di log: relasi `pickupPass` memakai foreign key default Eloquent
(`product_order_id`), sedangkan tabel `product_pickup_passes` memakai `order_id`. Setiap
membuka detail pesanan (langkah setelah kembali dari Midtrans) menghasilkan
`SQLSTATE[42S22] Unknown column 'product_pickup_passes.product_order_id'` → 500.

Perbaikan: model `ProductPickupPass` + relasi `ProductOrder::pickupPass()` mendeklarasikan
`order_id` secara eksplisit, dan migration `2026_11_14_100003_create_product_pickup_passes_table.php`
dibuat ulang agar fresh install memiliki skema yang sama.

## 3. Database

| Migration | Isi |
|---|---|
| `2026_11_14_100002_add_pickup_enabled_to_clinics_table` | flag pickup per klinik |
| `2026_11_14_100003_create_product_pickup_passes_table` | token QR, kode manual, masa berlaku, status pakai |
| `2026_11_15_100001_add_location_details_to_clinics_table` | `city`, `district`, `postal_code` untuk pencarian manual |
| `2026_11_15_100002_add_handover_fields_to_product_orders_table` | `picked_up_at`, `picked_up_by`, tabel `product_pickup_handovers` (unique per pesanan) |

`latitude`/`longitude` klinik sudah tersedia sejak fitur discovery dan dipakai ulang
(tidak ada kolom duplikat).

## 4. API pasien (Sanctum)

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/pet-shop/clinics/nearby` | `latitude`, `longitude`, `city`, `district`, `search`, `product_id`, `variant_id`, `quantity` |
| GET | `/api/pet-shop/clinics/filters` | daftar kota/kecamatan untuk pencarian manual |
| GET | `/api/pet-shop/products/{id}/clinics` | ketersediaan produk sama di klinik lain |
| GET | `/api/pet-shop/products/{id}` | kini menyertakan `pickup_clinics` |
| GET | `/api/pet-shop/orders/{id}/receipt` | snapshot bukti pembayaran |
| GET | `/api/pet-shop/orders/{id}/receipt/pdf` | PDF (hanya bila lunas) |
| GET | `/api/pet-shop/orders/{id}/pickup-pass` | hanya saat `ready_for_pickup` |
| GET | `/api/pet-shop/orders/{id}/pickup-pass/qr` | PNG QR (token acak) |

Aturan keamanan: seluruh endpoint pesanan memfilter `user_id` (404, bukan 403),
koordinat pengguna tidak disimpan, koordinat tidak lengkap/tidak valid ditolak 422,
dan jarak selalu dihitung server.

## 5. VCMS

- **Penjualan Produk → Verifikasi Pengambilan** (`admin/pet-shop/pickups`)
  - pindai QR memakai `BarcodeDetector` + `getUserMedia` (tanpa library baru; bila
    browser tidak mendukung atau izin kamera ditolak, kode manual tetap berfungsi),
  - input kode manual (tidak peka huruf besar/kecil & tanda hubung),
  - daftar pesanan `preparing`/`ready_for_pickup` + riwayat singkat,
  - konfirmasi serah terima hanya aktif bila pembayaran lunas.
- **Catat pembayaran** pada halaman pesanan & halaman verifikasi. Nominal wajib sama
  dengan sisa tagihan; backend memvalidasi, client tidak bisa mengirim nominal bebas.
- **Riwayat Pengambilan** (`admin/pet-shop/pickups/history`) menyimpan produk, petugas,
  waktu, metode, dan pembayaran.
- **Pengaturan klinik**: field kota/kecamatan/kode pos + toggle “Melayani ambil di klinik”
  pada form klinik yang sudah ada (super admin dan profil klinik).

## 6. Android

- **Detail produk**: bagian “Ambil di Klinik” berisi nama, jarak (jika ada), alamat singkat,
  ketersediaan stok, dan harga per klinik. Mengganti klinik memuat ulang data produk klinik
  tersebut; tombol tambah keranjang nonaktif bila stok tidak mencukupi.
- **Izin lokasi**: tombol eksplisit, memakai `LocationHelper` (LocationManager) yang sudah ada.
  Menolak izin tidak memblokir Pet Shop; jarak tidak ditampilkan.
- **Bukti pembayaran**: layar baru menampilkan snapshot transaksi dan 4 aksi nyata:
  simpan PNG (MediaStore/gallery), bagikan PNG (FileProvider + chooser), unduh PDF
  (endpoint backend → Downloads), bagikan PDF. PNG dirender langsung ke `Canvas` 1080px.
- **Pickup Pass**: kartu QR (gambar dari backend) + kode manual pada detail pesanan;
  hanya dimuat saat backend menyatakan `pickup_pass_available`.
- File disimpan di cache untuk dibagikan dan di `Pictures/PetHeal` atau `Downloads/PetHeal`
  untuk disimpan; tidak ada URI internal yang dibagikan.

## 7. Verifikasi yang benar-benar dijalankan

| Perintah | Hasil |
|---|---|
| `php artisan test --filter=PetShopPickupTest` | **12 passed / 81 assertions** |
| `php artisan test --filter='PetShopTest\|PaymentSplitCalculatorTest\|AdminConsoleSmokeTest\|ClinicPaymentRoutingTest\|RefundTest'` | 56 passed, **2 failed (pre-existing)** |
| `php artisan view:cache` | sukses |
| `php artisan route:list --path=api/pet-shop` | 17 route |
| `php artisan route:list --path=admin/pet-shop` | 33 route |
| `./gradlew.bat assembleDebug` (PetHeal_Android) | **BUILD SUCCESSFUL** |
| Uji manual API | `/clinics/nearby` (jarak & label), `/pickup-pass` + `/pickup-pass/qr` (PNG 2 KB), `/receipt/pdf` (PDF 1.7, 26 KB) |

Cakupan `PetShopPickupTest`: pengurutan jarak + klinik tanpa koordinat, pencarian manual kota,
koordinat tidak valid, ketersediaan & harga per klinik, receipt snapshot (tidak berubah saat nama
produk diubah), PDF ditolak sebelum lunas, isolasi pengguna lain, pass hanya setelah siap,
pass klinik lain ditolak, pass sekali pakai, penyerahan tanpa pembayaran ditolak, stok dikomit
sekali, konfirmasi ganda idempoten, nominal offline harus sama, halaman VCMS ter-scope klinik.

### Kegagalan yang sudah ada sebelum pekerjaan ini

Dua test lama di `PetShopTest` masih mengasumsikan pembayaran online **selalu** nonaktif saat
Midtrans Partner belum siap:

- `catalog only lists products that can actually be bought` → `online_payment_available` true;
- `online order is rejected and released when partner is not ready` → order online diterima (201).

Kode yang ada (dan komentar Android) memang memakai **central fallback sandbox** ketika
`MIDTRANS_SERVER_KEY` terisi (`services.midtrans.central_fallback.enabled` default true di
non-produksi). Test tersebut tidak diubah dalam pekerjaan ini; perlu keputusan produk apakah
fallback dipertahankan (lalu test disesuaikan) atau dimatikan (lalu perilaku kode dikembalikan).

## 8. Sisa pekerjaan produksi

- Kredensial Midtrans Partner + onboarding merchant tiap klinik tetap diperlukan untuk
  pembayaran online tanpa fallback pusat.
- QR scanner VCMS bergantung pada `BarcodeDetector` (Chrome/Edge modern) + HTTPS; fallback
  manual selalu tersedia.
- Scheduler (`schedule:run`) harus aktif agar `petshop:expire-orders` dan `payments:reconcile` berjalan.
- Belum ada instrumentasi UI Android; verifikasi Android sebatas kompilasi + review kode.
