# Status Implementasi — Pet Shop Multi-Klinik & Centralized Payment

Dokumen ini merangkum **apa yang sudah jadi, bagaimana keputusannya, dan apa yang belum**. Sumber spesifikasi: `MASTER-PROMPT-PETSHOP.md`. Log pengerjaan per fase: `progress.md`.

Status keseluruhan: **10/10 fase selesai di lingkungan lokal**, dengan satu ketergantungan eksternal yang memang belum tersedia: kredensial **Midtrans Partner** dan onboarding merchant tiap klinik. Selama kredensial itu belum diisi, pembayaran online Pet Shop otomatis tidak ditawarkan dan pesanan hanya memakai **Bayar di Klinik** — bukan fallback diam-diam ke merchant pusat.

---

## 1. Ringkasan hasil

| Area | Status | Bukti |
|---|---|---|
| Skema database (katalog, pesanan, stok, payment terpusat) | ✅ | 3 migration baru, sudah dijalankan di `pet_heal` dan `pet_heal_test` |
| Model + state machine pesanan | ✅ | `ProductOrder::TRANSITIONS`, 12 model baru |
| Layanan stok (reservasi/komit/lepas, idempoten) | ✅ | `ProductStockService`, diuji langsung lewat feature test |
| Layanan pesanan (harga server-side, multi-klinik guard) | ✅ | `ProductOrderService` |
| Payment terpusat + split + refund | ✅ | `PaymentService`, `PaymentSplitCalculator`, `MidtransPartnerGateway` |
| Webhook Midtrans (BOOKING/MEDREC/SHOP) | ✅ | Signature merchant & Partner, validasi merchant/partner/gross/currency |
| API pasien Pet Shop | ✅ | 10 endpoint `/api/pet-shop/*` |
| VCMS Pet Shop (dashboard, produk, pesanan, penjualan, kategori, merchant) | ✅ | 22 route admin, 9 view, sidebar baru |
| Android Pet Shop (katalog → varian → keranjang → checkout → pesanan) | ✅ | `assembleDebug` sukses |
| Test otomatis | ✅ | 12 feature test / 89 assertion + 6 unit test / 91 assertion |
| Regresi admin console | ✅ | `AdminConsoleSmokeTest` 7 test / 141 assertion |
| Dokumentasi | ✅ | `progress.md`, file ini, `PetHeal_Backend/docs/PROGRESS.md` |

---

## 2. Keputusan arsitektur penting

### 2.1 Satu pesanan = satu klinik
Keranjang Android dan backend sama-sama menolak mencampur produk dari klinik berbeda. Ini menjaga agar:
- stok, pickup, dan merchant tujuan dana tidak ambigu;
- split pembayaran selalu mengarah ke satu merchant;
- tidak ada pengiriman lintas klinik yang tidak pernah disepakati.

### 2.2 Stok: satu sumber kebenaran
Tidak ada tabel reservasi terpisah. Reservasi disimpan sebagai agregat:
- `product_variants.stock_reserved`, dan
- penanda idempoten di order: `stock_reserved_at`, `stock_released_at`, `stock_committed_at`.

Stok tersedia selalu `stock_on_hand - stock_reserved`. Komitmen penjualan mengurangi keduanya sekaligus sehingga stok fisik tidak pernah terpotong dua kali. Setiap perubahan menulis `product_stock_movements`.

### 2.3 Uang selalu integer rupiah
Harga, diskon, subtotal, total, refund, dan pembagian dana memakai integer. Tidak ada float yang disimpan. Pembulatan komisi memakai `HALF_UP`, sisa selalu menjadi bagian klinik sehingga `platform + clinic === gross`.

### 2.4 Status pesanan dan status pembayaran dipisah
Contoh nyata: pesanan bisa `ready_for_pickup` dengan pembayaran `unpaid` (bayar di klinik), atau `rejected` dengan pembayaran `refund_pending`. Penggabungan keduanya akan membuat refund dan pembatalan saling bertabrakan.

### 2.5 Pembayaran online hanya lewat Midtrans Partner
Syarat lengkap agar tombol online muncul dan transaksi dibuat:
1. `services.midtrans.partner.enabled = true`;
2. Partner ID, server key, Snap URL, dan API URL terisi;
3. klinik punya `clinic_merchant_configs` dengan Merchant ID;
4. `onboarding_status = active`;
5. `verified_at` terisi;
6. `is_online_payment_enabled = true`;
7. environment merchant sama dengan environment aplikasi.

Jika salah satu gagal, API katalog mengirim `clinic.online_payment_available = false`, checkout Android hanya menampilkan **Bayar di Klinik**, dan backend menolak pesanan online dengan `code: online_payment_unavailable`.

### 2.6 Konsultasi tidak diubah alurnya
Flow konsultasi lama tetap memakai `PaymentStatusService` dan `RefundService`. Yang ditambahkan hanya **write-through** ke tabel `payments` melalui `PaymentService::recordConsultationPayment`, serta sinkronisasi status dari webhook. Tidak ada dua alur refund untuk konsultasi.

---

## 3. Database

### 3.1 Migration baru
| File | Isi |
|---|---|
| `2026_11_13_100001_create_pet_shop_catalog_tables.php` | `product_categories`, `clinic_products`, `product_variants` |
| `2026_11_13_100002_create_pet_shop_order_tables.php` | `product_orders`, `product_order_items`, `product_order_status_histories`, `product_stock_movements` |
| `2026_11_13_100003_create_payment_tables.php` | `clinic_merchant_configs`, `payments`, `payment_notifications`, `payment_status_histories`, `payment_refunds` |

### 3.2 Aturan data
- Kategori produk **global** (dibuat super_admin, dipakai semua klinik).
- Produk, varian, pesanan, mutasi stok **per `clinic_id`**.
- SKU unik global (mencegah dua klinik mengklaim SKU yang sama untuk integrasi inventaris).
- `payments` menyimpan snapshot `merchant_id`, `partner_id`, `environment`, dan `split_settings` agar perubahan konfigurasi klinik tidak mengubah histori.
- Order menyimpan snapshot nama/alamat/telepon klinik untuk pickup, sehingga struk lama tetap benar meski profil klinik berubah.

---

## 4. API pasien

Base: `/api/pet-shop` (auth Sanctum, middleware `ApiAuthenticate`).

| Method | Path | Fungsi |
|---|---|---|
| GET | `/categories` | Kategori aktif + jumlah produk yang benar-benar bisa dibeli |
| GET | `/sellers` | Klinik aktif yang punya produk tersedia |
| GET | `/products` | Katalog; filter `search`, `category`, `clinic_id`, `clinic_slug`, `sort`, paginasi |
| GET | `/products/{id}` | Detail + seluruh varian aktif |
| POST | `/orders` | Buat pesanan; server menghitung harga & mereservasi stok |
| GET | `/orders` | Pesanan milik user (paginasi) |
| GET | `/orders/{id}` | Detail + timeline |
| POST | `/orders/{id}/cancel` | Batal (hanya status awal & belum dibayar) |
| POST | `/orders/{id}/pay` | Buat transaksi Snap Partner |
| GET | `/orders/{id}/payment-status` | Sinkronisasi status pembayaran |

Catatan keamanan:
- `total_amount` dari client tidak pernah dibaca; semua dihitung dari varian di database.
- `GET /orders/{id}` memfilter `user_id`, sehingga pesanan pengguna lain menghasilkan 404 (bukan 403 yang membocorkan keberadaan data).
- Pesanan online yang kliniknya belum siap langsung dibatalkan dan stoknya dilepas pada request yang sama.

---

## 5. VCMS (web admin)

Route: `/admin/pet-shop/*` — nama route `admin.pet-shop.*`.

| Halaman | Akses | Isi |
|---|---|---|
| Ringkasan | admin klinik & super admin | Produk, varian, stok tersedia, penjualan selesai, grafik 7 hari, produk terlaris, stok menipis |
| Produk | admin klinik | CRUD, arsip (soft delete), toggle aktif, varian + harga + stok, riwayat stok |
| Pesanan | admin klinik | Filter status/pembayaran/tanggal, detail, timeline, transisi status, refund |
| Riwayat Penjualan | admin klinik | Hanya pesanan `completed`, ringkasan pendapatan/item, ekspor CSV |
| Kategori Produk | **super admin** | CRUD kategori global; kategori yang masih dipakai tidak bisa dihapus |
| Merchant Klinik | **super admin** | Merchant ID, Partner ID, environment, status onboarding, komisi, aktif/nonaktif online, alasan belum siap |

UI memakai komponen admin yang sudah ada (`x-admin.panel`, `page-header`, `badge`, `stat-card`, `empty-state`) dan token warna yang sama, sehingga tidak terasa sebagai halaman tempelan.

---

## 6. Android

Entry: kartu **Pet Shop** di Beranda. Route baru:
`pet-shop`, `pet-shop/product/{productId}`, `pet-shop/checkout`, `pet-shop/orders`, `pet-shop/orders/{orderId}`.

Alur:
1. **Katalog** — pencarian (debounce), chip kategori, chip klinik, sort, grid 2 kolom, indikator stok menipis, badge diskon.
2. **Detail produk** — gambar, brand, harga/diskon, pemilihan varian dengan stok nyata, stepper jumlah, kartu klinik penjual, deskripsi.
3. **Keranjang/checkout** — ubah jumlah, hapus baris, info pickup, pilihan bayar online (hanya jika server menyatakan siap) atau bayar di klinik, telepon & catatan, ringkasan total.
4. **Pesanan** — daftar + status pesanan/pembayaran, paginasi.
5. **Detail pesanan** — ringkasan status, aksi bayar/cek status/batal, item, pickup, rincian pembayaran, timeline.

Keputusan UX:
- Keranjang hanya di memori; harga selalu divalidasi ulang server agar tidak menampilkan harga kedaluwarsa.
- Pembayaran online membuka `redirect_url` Snap di browser. Aplikasi **tidak pernah** menyimpulkan “lunas”; pengguna kembali lalu menekan **Cek Status Pembayaran** yang memanggil server.
- Tidak ada bottom navigation baru di dalam alur Pet Shop; kembali selalu kembali ke layar sebelumnya.

---

## 7. Pembayaran & refund

1. `POST /orders/{id}/pay` memanggil `PaymentService::createPetShopPayment`.
2. Payment dibuat dengan `payment_code`, `provider_order_id`, snapshot merchant/partner, dan `split_settings`.
3. `MidtransPartnerGateway` membuat transaksi ke endpoint Partner (bukan endpoint merchant biasa).
4. Webhook `SHOP-*`:
   - memverifikasi signature Partner (`SHA512(order_id+status_code+gross_amount+PartnerServerKey)`) atau merchant (server key lama);
   - menolak merchant/partner/gross/currency yang tidak cocok;
   - menyimpan setiap notifikasi (termasuk yang invalid) di `payment_notifications`;
   - idempoten: notifikasi berulang tidak menaikkan status dua kali.
5. Status `paid` menaikkan pesanan `pending_payment → awaiting_confirmation` dan mencatat histori.
6. Penolakan pesanan yang sudah dibayar membuat `payment_refunds` berstatus `requested`; bila provider belum mendukung refund otomatis, refund dikonfirmasi manual dari VCMS.
7. `payments:reconcile` mencocokkan transaksi yang tertinggal dengan provider.

---

## 8. Testing yang benar-benar dijalankan

| Perintah | Hasil |
|---|---|
| `php artisan test --filter=PetShopTest` | **12 passed / 89 assertions** |
| `php artisan test --filter=PaymentSplitCalculatorTest` | **6 passed / 91 assertions** |
| `php artisan test --filter=AdminConsoleSmokeTest` | **7 passed / 141 assertions** |
| `php artisan view:cache` | sukses (seluruh Blade ter-compile) |
| `php artisan route:list --path=admin/pet-shop` | 22 route |
| `php artisan route:list --path=api/pet-shop` | 10 route |
| `./gradlew.bat assembleDebug` | **BUILD SUCCESSFUL**, APK debug dihasilkan |
| `php -l` pada service/controller yang diubah | tidak ada syntax error |

Cakupan `PetShopTest`:
- katalog hanya menampilkan produk yang bisa dibeli (stok 0 & nonaktif tersembunyi);
- filter klinik/kategori di server;
- harga dari server (payload `total_amount` palsu diabaikan);
- reservasi stok + mutasi stok;
- stok kurang → 422 tanpa order;
- campur klinik → 422;
- batal melepas reservasi tepat sekali;
- selesai → stok fisik berkurang, reservasi nol, pembayaran tunai tercatat satu kali;
- online ditolak & stok dilepas saat Partner belum siap;
- webhook valid menandai lunas, duplikat idempoten, signature salah 403;
- admin klinik lain tidak bisa membuka/mengubah pesanan (404).

Catatan: eksekusi **seluruh** suite backend melebihi batas 10 menit pada mesin ini, jadi yang dijalankan adalah suite baru + smoke test admin console yang menyentuh seluruh halaman admin (termasuk sidebar baru). Suite lama lain tidak diubah oleh pekerjaan ini.

---

## 9. Known issues & kesiapan produksi

### Belum bisa dipakai tanpa langkah eksternal
- **Kredensial Midtrans Partner** belum ada di environment. Selama kosong, pembayaran online memang dinonaktifkan by design.
- **Onboarding merchant tiap klinik** harus diselesaikan di Midtrans lalu diisi di menu *Merchant Klinik* (Merchant ID, status `active`, verifikasi, aktifkan online, komisi).
- Header **`X-Partner-Id`** pada request Snap Partner mengikuti dokumentasi Partner yang sudah diverifikasi, tetapi wajib dicek ulang saat onboarding resmi Midtrans (nama/format header dapat berbeda per akun Partner).

### Operasional
- Jalankan scheduler Laravel (cron `schedule:run`) agar `petshop:expire-orders` dan `payments:reconcile` berjalan. Tanpa itu, reservasi stok tidak pernah kedaluwarsa otomatis.
- `php artisan storage:link` diperlukan agar foto produk tampil (disk `public`).
- Pembayaran online Android membuka browser eksternal, bukan WebView internal. Ini disengaja untuk mengurangi duplikasi alur Snap, tetapi belum ada deep link kembali ke aplikasi; pengguna harus menekan *Cek Status Pembayaran* setelah kembali.
- Ekspor CSV penjualan memakai nama kolom Indonesia; belum ada ekspor Excel/PDF.

### Teknis
- `ProductStockService` membuka transaksi sendiri dan dipanggil dari dalam transaksi `ProductOrderService::transition` (savepoint). Sudah diuji berhasil pada MySQL, tetapi bila logika transisi tumbuh, pertimbangkan memindahkan pemanggilan stok ke luar transaksi luar.
- Belum ada test UI/instrumentasi Android; verifikasi Android sebatas kompilasi dan peninjauan kode.
- Komisi dan settlement tidak pernah di-hardcode; angka 0% berarti seluruh dana ke klinik.

---

## 10. Cara menjalankan

### Backend
```bash
cd PetHeal_Backend
php artisan migrate --force
php artisan storage:link
php artisan view:cache
php artisan serve
# terminal lain, untuk scheduler:
php artisan schedule:work
```

Isi `.env` (jangan commit):
```env
MIDTRANS_ENVIRONMENT=sandbox
MIDTRANS_PARTNER_ENABLED=true
MIDTRANS_PARTNER_ID=...
MIDTRANS_PARTNER_SERVER_KEY=...
MIDTRANS_PARTNER_SNAP_URL=...
MIDTRANS_PARTNER_API_URL=...
MIDTRANS_PLATFORM_MERCHANT_ID=...
```

Lalu buka **Admin → Penjualan Produk → Merchant Klinik**, isi merchant tiap klinik, dan aktifkan pembayaran online hanya setelah merchant benar-benar `active` + terverifikasi.

### Android
```bash
cd PetHeal_Android
./gradlew.bat assembleDebug
```
APK: `app/build/outputs/apk/debug/app-debug.apk`. Base URL tetap dari `local.properties` (ngrok), tidak ada flavor/applicationId baru.

### Test
```bash
cd PetHeal_Backend
php artisan test --filter=PetShopTest
php artisan test --filter=PaymentSplitCalculatorTest
```
