# Progress Pet Shop Multi-Klinik & Centralized Payment

Catatan pengerjaan bertahap dari `MASTER-PROMPT-PETSHOP.md`. Ringkasan status akhir dan kesiapan produksi ada di `PHASE-STATUS-PETSHOP.md`; catatan domain backend juga ditambahkan ke `PetHeal_Backend/docs/PROGRESS.md`.

Tanggal mulai/selesai: 14 November 2026.
Status: **10/10 fase selesai** (kode, UI, test, dokumentasi). Pembayaran online menunggu kredensial Midtrans Partner dan onboarding merchant klinik.

---

## Phase 1 — Audit & desain domain
- Membaca seluruh `PetHeal_Backend` (model, service, route, VCMS, Android) untuk memastikan modul baru tidak menduplikasi flow yang ada.
- Temuan yang menentukan desain:
  - konsultasi & booking punya `PaymentStatusService`/`RefundService` sendiri → Pet Shop tidak membuat flow pembayaran tandingan, hanya menambah `payments` sebagai lapisan rekonsiliasi terpusat;
  - semua data medis sudah ter-scope `clinic_id` → katalog/order Pet Shop mengikuti aturan yang sama;
  - VCMS sudah punya design system (`x-admin.*`) → halaman baru wajib memakainya, bukan Tailwind generik.
- Keputusan: kategori global (super_admin), produk & pesanan per klinik, uang integer, komisi dari konfigurasi merchant (tidak di-hardcode).
- Tidak ada perubahan file pada fase ini.

## Phase 2 — Skema database
- Migration baru:
  - `2026_11_13_100001_create_pet_shop_catalog_tables.php` — `product_categories`, `clinic_products`, `product_variants`;
  - `2026_11_13_100002_create_pet_shop_order_tables.php` — `product_orders`, `product_order_items`, `product_order_status_histories`, `product_stock_movements`;
  - `2026_11_13_100003_create_payment_tables.php` — `clinic_merchant_configs`, `payments`, `payment_notifications`, `payment_status_histories`, `payment_refunds`.
- Reservasi stok diputuskan sebagai agregat (`stock_reserved` di varian + penanda idempoten di order) agar tidak ada tabel reservasi yang harus disinkronkan manual.
- Migration dijalankan di `pet_heal` dan `pet_heal_test`, tanpa mengubah tabel lama.

## Phase 3 — Model, enum status, dan aturan transisi
- 12 model baru: `ProductCategory`, `ClinicProduct`, `ProductVariant`, `ProductOrder`, `ProductOrderItem`, `ProductOrderStatusHistory`, `ProductStockMovement`, `ClinicMerchantConfig`, `Payment`, `PaymentNotification`, `PaymentStatusHistory`, `PaymentRefund`.
- `ProductOrder::TRANSITIONS` menjadi satu-satunya sumber aturan perpindahan status; controller dan API hanya memanggil service.
- Status pesanan dan status pembayaran dipisah (contoh: `ready_for_pickup` + `unpaid`), karena digabung akan membuat refund dan pembatalan bertabrakan.
- Relasi + scope `clinic_id` ditambahkan di model agar tidak ada query lintas klinik yang lolos.

## Phase 4 — Layanan domain & pembayaran
- `ProductStockService` — reservasi, komit, lepas, koreksi; setiap perubahan menulis `product_stock_movements`; operasi idempoten lewat penanda di order.
- `ProductOrderService` — membuat order dari varian di database (harga dari client tidak pernah dipercaya), validasi satu klinik, transisi status, pembatalan, penyelesaian.
- `PaymentSplitCalculator` — komisi `HALF_UP`, sisa ke klinik, invarian `platform + clinic === gross`.
- `MidtransPartnerGateway` — transaksi lewat endpoint Partner, bukan endpoint merchant biasa; dipakai hanya bila semua syarat merchant terpenuhi.
- `MerchantConfigurationService` — membaca/menulis konfigurasi merchant + `onlinePaymentAvailable()` sebagai satu-satunya penentu apakah pembayaran online boleh ditawarkan.
- `PaymentService` — membuat payment Pet Shop, mencatat pembayaran konsultasi (write-through, tanpa mengubah alur lama), refund.
- `PaymentReconciliationService` — mencocokkan transaksi tertinggal dengan provider (command `payments:reconcile`).
- Webhook Midtrans diperluas ke prefix `SHOP-*`: verifikasi signature Partner/merchant, validasi merchant/partner/gross/currency, simpan semua notifikasi, idempoten.
- Command `petshop:expire-orders` untuk melepas reservasi pesanan yang kedaluwarsa.

## Phase 5 — API pasien
- `Api/PetShopController` + `PetShopOrderController`: 10 endpoint `/api/pet-shop/*` (kategori, penjual, produk, detail, buat pesanan, daftar/detail pesanan, batal, bayar, status pembayaran).
- Semua endpoint memakai Sanctum + `ApiAuthenticate`; detail/batal hanya untuk pemilik order (404, bukan 403).
- Katalog hanya menampilkan produk aktif yang punya varian dengan stok tersedia; kategori menghitung produk yang benar-benar bisa dibeli.
- Payload produk menyertakan `clinic.online_payment_available`, sehingga Android tidak pernah membuat order online yang pasti dibatalkan.
- Validasi request dipisah ke FormRequest; pesan error berbahasa Indonesia.

## Phase 6 — VCMS Pet Shop
- 9 view: `dashboard`, `products/{index,form,show}`, `orders/{index,show}`, `sales/index`, `categories/index`, `merchants/index` + komponen `petshop/status-badge`.
- 22 route `admin.pet-shop.*`, sidebar baru, lang `id`/`en` untuk `petshop` dan `menu`.
- Scoping lewat trait `Concerns/ResolvesPetShopScope`: admin klinik hanya melihat kliniknya, super admin melihat semua; kategori & merchant hanya super admin.
- UI memakai komponen admin yang sudah ada supaya konsisten dengan halaman lain (bukan halaman tempelan).
- `php artisan view:cache` sukses; `route:list` mengonfirmasi 22 route.

## Phase 7 — Pembersihan & konsistensi
- `ProductStockService::logMovement` dirapikan (parameter `$legacyNote`/`$physical` dihapus dan seluruh call site diperbarui).
- Docblock `PaymentService::requestRefund` diperbaiki menjadi `refund:?PaymentRefund`.
- `PetShopController` menyuntikkan `MerchantConfigurationService` dan menyimpan hasil `onlinePaymentAvailable()` per klinik dalam satu request (menghindari query berulang di katalog).
- Tidak ada flow ganda: konsultasi tetap memakai service lamanya; Pet Shop punya satu jalur order, satu jalur bayar, satu jalur refund.

## Phase 8 — Android
- Model DTO lengkap (`PetShopModels.kt`), 10 endpoint di `ApiService.kt`, `PetShopRepository` + keranjang in-memory (single-clinic, validasi stok di client, harga selalu dari server saat checkout).
- 5 ViewModel dan 5 layar: katalog, detail produk, checkout, daftar pesanan, detail pesanan + komponen bersama (`PetShopComponents.kt`).
- Entry card Pet Shop di Beranda, route baru di `Screen.kt` dan `PetHealNavHost.kt`; navigasi lama tidak dipindahkan, tidak ada bottom nav baru di dalam alur.
- Pembayaran online membuka `redirect_url` di browser; status tidak pernah dianggap lunas dari UI (harus lewat `payment-status`).
- `./gradlew.bat assembleDebug` **BUILD SUCCESSFUL**. Error yang ditemukan & diperbaiki: import `KeyboardOptions` harus dari `androidx.compose.foundation.text`, `orderStatusStyle`/`paymentStatusStyle` harus `@Composable`, import `fillMaxSize` hilang.

## Phase 9 — Test & regresi
- `tests/Feature/PetShopTest.php` — **12 test / 89 assertion PASS**: katalog tersembunyi saat stok 0/nonaktif, filter server, harga server mengabaikan `total_amount` client, reservasi + mutasi stok, stok kurang 422, campur klinik 422, batal melepas reservasi sekali, selesai mengurangi stok fisik, online ditolak & stok dilepas saat Partner belum siap, webhook valid/duplikat/signature salah, isolasi klinik.
- `tests/Unit/PaymentSplitCalculatorTest.php` — **6 test / 91 assertion PASS**: invarian split, pembulatan, komisi 0%, komisi >0 tanpa merchant platform ditolak.
- `AdminConsoleSmokeTest` — **7 test / 141 assertion PASS** (seluruh halaman admin termasuk sidebar baru).
- `phpunit.xml` ditambah suite `Unit`.
- Menjalankan seluruh `php artisan test` melebihi batas 10 menit di mesin ini; regresi diverifikasi lewat tiga suite di atas.
- Artefak test (`storage/framework/testing/disks/public/medicine-mutations/...pdf`) dihapus agar tidak tertinggal di repo.

## Phase 10 — Dokumentasi
- `progress.md` (file ini): log per fase.
- `PHASE-STATUS-PETSHOP.md`: status, keputusan arsitektur, inventaris DB/API/VCMS/Android, keamanan pembayaran, hasil verifikasi, known issues, cara menjalankan.
- `PetHeal_Backend/docs/PROGRESS.md`: seksi `2026-11-14 — Pet Shop multi-klinik & payment terpusat`.

---

## Verifikasi akhir (yang benar-benar dijalankan)

| Perintah | Hasil |
|---|---|
| `php artisan test --filter=PetShopTest` | 12 passed / 89 assertions |
| `php artisan test --filter=PaymentSplitCalculatorTest` | 6 passed / 91 assertions |
| `php artisan test --filter=AdminConsoleSmokeTest` | 7 passed / 141 assertions |
| `php artisan view:cache` | sukses |
| `php artisan route:list --path=admin/pet-shop` | 22 route |
| `php artisan route:list --path=api/pet-shop` | 10 route |
| `./gradlew.bat assembleDebug` (PetHeal_Android) | BUILD SUCCESSFUL |

## Tambahan — Import katalog Pet Shop (14 November 2026)
- Halaman `/admin/pet-shop/products` kini punya tombol **Impor Produk** (CSV/XLSX/XLS) dengan modal: pilih file, strategi duplikat (perbarui/lewati), unduh template CSV/Excel, ekspor katalog berjalan, panduan kolom, dan ringkasan hasil + laporan baris gagal.
- Format: satu baris = satu varian; baris dengan nama produk sama digabung. Kategori yang belum ada dibuat otomatis (tercatat di ringkasan), varian tanpa nama memakai “Standar”, harga menerima `Rp`/titik ribuan/`185.000,-`, dan baris header berulang dilewati. SKU tetap unik global; stok hanya untuk varian baru dan tercatat sebagai mutasi “Stok awal”. Panel hasil kini menampilkan 3 alasan gagal pertama.
- File: `app/Imports/PetShopProductsImport.php`, `app/Exports/PetShopProductsTemplateExport.php`, `app/Exports/PetShopProductsImportErrorReportExport.php`, method import/template/export di `PetShopProductController`, 6 route baru, partial `admin/pet-shop/products/partials/import-modal.blade.php`, lang id/en, test `PetShopProductImportTest`.
- URL aset lewat tunnel: middleware `UseRequestAssetOrigin` mengganti origin @vite/logo/storage dari loopback ke host ngrok saat perlu; test `TunnelAssetOriginTest`.
- Verifikasi: `PetShopProductImportTest` **11 passed / 52 assertions**, `TunnelAssetOriginTest` **1 passed / 4 assertions**, `php artisan view:cache` sukses, `php -l` bersih.

## Tambahan — Midtrans MEDREC, rekonsiliasi, & VCMS (14 November 2026)
- Biaya tambahan rekam medis (`MEDREC-*`) sekarang ikut routing per klinik lewat `PaymentService::createMedicalRecordPayment()`; tidak ada lagi pemakaian Server Key pusat langsung dari `MedicalRecordController`.
- Webhook MEDREC/booking/Pet Shop memvalidasi merchant, gross, dan currency; MEDREC menulis ke `payments` + `extra_payment_*` dalam satu alur. Rekonsiliasi dan sync-status manual memakai akun yang terkunci pada transaksi.
- VCMS: panel Sandbox/Production terpisah, salin Notification URL, monitor routing + signature invalid, jejak audit, dan konfirmasi password untuk aksi production.
- Verifikasi: `ClinicPaymentRoutingTest` **21 passed / 129 assertions**; regresi `PetShopTest` 12/89, `AdminConsoleSmokeTest` 7/141, `RefundTest` 11/16, `PaymentSplitCalculatorTest` 6/91 → **36 passed / 337 assertions**; `view:cache` sukses.
- Panduan admin klinik: `PetHeal_Backend/docs/MIDTRANS_CLINIC_ADMIN_GUIDE.md`.
- Sisa: refund MEDREC belum lewat gateway; halaman VCMS belum diuji browser/Lighthouse/axe.

## Sisa pekerjaan / limitasi
- Kredensial Midtrans Partner belum tersedia → pembayaran online nonaktif by design; perlu onboarding merchant tiap klinik lewat menu *Merchant Klinik*.
- Header `X-Partner-Id` mengikuti dokumentasi Partner, wajib dicek ulang saat onboarding resmi.
- Scheduler (`schedule:work`/cron) harus berjalan agar `petshop:expire-orders` dan `payments:reconcile` aktif.
- Belum ada test instrumentasi Android; verifikasi Android sebatas kompilasi + review kode.
- Ekspor penjualan baru CSV, belum Excel/PDF.
- Full backend suite tidak selesai dalam 10 menit, jadi tidak diklaim lulus seluruhnya.
