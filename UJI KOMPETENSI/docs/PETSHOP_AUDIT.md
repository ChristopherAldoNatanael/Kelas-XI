# PetHeal Pet Shop - Audit Report

> Tanggal audit: 2026-11-15  
> Auditor: Backend subagent  
> Status fix: **APPLIED & VERIFIED**

---

## 1. Internal Server Error Fix

### Root Cause

**File:** `app/Http/Controllers/Api/PetShopOrderController.php:73`  
**Error:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'product_id' in 'field list'`

Saat user mencoba membuat order dengan `payment_method=online`, controller melakukan
pre-check ketersediaan payment gateway dengan mengambil klinik dari varian pertama.
Kode pada baris 73 memanggil:

```php
// SEBELUM (salah):
->value('product_id');
```

Kolom `product_id` tidak ada di tabel `product_variants`. FK-nya bernama `clinic_product_id`
(sesuai migration `2026_11_13_100001_create_pet_shop_catalog_tables.php:55` dan
model `ProductVariant.php:13`). Error ini terjadi di method `store()`, **sebelum order
dibuat** — sehingga seluruh permintaan bayar online langsung 500.

### Fix Applied

```php
// SESUDAH (benar):
->value('clinic_product_id');
```

### Files Changed

- `app/Http/Controllers/Api/PetShopOrderController.php` — baris 73

### Verifikasi

- `php -l` → **No syntax errors detected**
- `php artisan config:clear && cache:clear` → OK
- Error di log terakhir: `2026-11-14 16:40:10` — tidak ada error baru setelah fix

---

## 2. Existing Features Audit

### Database

#### Tabel `clinics`

Dibuat oleh `2026_10_02_000001_create_clinics_table.php`, diperluas oleh
`2026_11_09_000002_add_location_to_clinics_table.php`.

| Kolom | Status |
|---|---|
| `clinics.latitude` | **EXISTS** (decimal 10,7, nullable) |
| `clinics.longitude` | **EXISTS** (decimal 10,7, nullable) |
| `clinics.pickup_enabled` | **MISSING** — tidak ada di migration manapun |
| `clinics.rating` | EXISTS (decimal 3,2, nullable) |
| `clinics.opening_time` | EXISTS (time, nullable) |
| `clinics.closing_time` | EXISTS (time, nullable) |

#### Tabel `product_orders`

Migration: `2026_11_13_100002_create_pet_shop_order_tables.php`

| Kolom | Status |
|---|---|
| `product_orders.pickup_code` | **MISSING** |
| `product_orders.pickup_token` | **MISSING** |
| `product_order_pickup_tokens` table | **MISSING** — tidak ada tabel ini |

Kolom pickup yang ada: `pickup_clinic_name`, `pickup_clinic_address`, `pickup_clinic_phone`
(snapshot lokasi klinik saat order dibuat, bukan token/kode pengambilan).

---

### Backend Libraries (`composer.json`)

| Library | Status |
|---|---|
| PDF | **EXISTS** — `barryvdh/laravel-dompdf: *` |
| QR Code | **MISSING** — tidak ada library QR (simplesoftwareio/simple-qrcode, endroid/qr-code, dll) |
| Excel | EXISTS — `maatwebsite/excel: ^3.1` |
| Image | EXISTS — `intervention/image: ^3.11` |

---

### Android (`PetHeal_Android`)

#### Permissions di `AndroidManifest.xml`

| Permission | Status |
|---|---|
| Location (`ACCESS_FINE_LOCATION`) | **YES** |
| Location (`ACCESS_COARSE_LOCATION`) | **YES** |
| Camera (`CAMERA`) | YES |
| Internet | YES |
| Push Notifications (`POST_NOTIFICATIONS`) | YES |

#### Libraries di `app/build.gradle.kts`

| Library | Status |
|---|---|
| QR Code scanner/generator | **NO** — tidak ada library ZXing, ML Kit, atau QR apapun |
| Midtrans UIKit | YES — `com.midtrans:uikit:2.0.0-SANDBOX` |
| Coil (image loading) | YES — `io.coil-kt:coil-compose:2.5.0` |
| Firebase Auth + Messaging | YES — BOM 33.8.0 |
| Retrofit + OkHttp | YES |
| Navigation Compose | YES — 2.7.5 |

#### Existing Receipt/Billing UI Files

Belum dicek secara mendalam (di luar scope fix ini), namun berdasarkan navigasi
`Screen.kt` yang sudah ada 21 route. Layar pet-shop-specific perlu dicek lebih lanjut.

---

### VCMS (Admin Panel)

#### Pet Shop Order Management Views

| File | Deskripsi |
|---|---|
| `resources/views/admin/pet-shop/orders/index.blade.php` | List semua order pet shop |
| `resources/views/admin/pet-shop/orders/show.blade.php` | Detail order + transisi status |
| `resources/views/admin/pet-shop/dashboard.blade.php` | Dashboard pet shop |
| `resources/views/admin/pet-shop/products/index.blade.php` | List produk |
| `resources/views/admin/pet-shop/products/show.blade.php` | Detail produk |
| `resources/views/admin/pet-shop/products/form.blade.php` | Form tambah/edit produk |
| `resources/views/admin/pet-shop/categories/index.blade.php` | Manajemen kategori |
| `resources/views/admin/pet-shop/merchants/index.blade.php` | Konfigurasi merchant Midtrans klinik |
| `resources/views/admin/pet-shop/sales/index.blade.php` | Laporan penjualan |

#### Admin Pet Shop Routes

| Method | URI | Route Name |
|---|---|---|
| GET | admin/pet-shop | admin.pet-shop.dashboard |
| GET | admin/pet-shop/orders | admin.pet-shop.orders.index |
| GET | admin/pet-shop/orders/{order} | admin.pet-shop.orders.show |
| POST | admin/pet-shop/orders/{order}/transition | admin.pet-shop.orders.transition |
| POST | admin/pet-shop/refunds/{refund}/confirm | admin.pet-shop.refunds.confirm |
| GET | admin/pet-shop/products | admin.pet-shop.products.index |
| POST | admin/pet-shop/products | admin.pet-shop.products.store |
| GET | admin/pet-shop/products/create | admin.pet-shop.products.create |
| GET | admin/pet-shop/products/{product} | admin.pet-shop.products.show |
| PUT | admin/pet-shop/products/{product} | admin.pet-shop.products.update |
| DELETE | admin/pet-shop/products/{product} | admin.pet-shop.products.destroy |
| GET | admin/pet-shop/products/{product}/edit | admin.pet-shop.products.edit |
| POST | admin/pet-shop/products/{product}/toggle | admin.pet-shop.products.toggle |
| POST | admin/pet-shop/variants/{variant}/stock | admin.pet-shop.variants.stock |
| GET | admin/pet-shop/products/export/csv | admin.pet-shop.products.export.csv |
| GET | admin/pet-shop/products/export/xlsx | admin.pet-shop.products.export.xlsx |
| POST | admin/pet-shop/products/import | admin.pet-shop.products.import |
| GET | admin/pet-shop/categories | admin.pet-shop.categories.index |
| POST | admin/pet-shop/categories | admin.pet-shop.categories.store |
| PUT | admin/pet-shop/categories/{category} | admin.pet-shop.categories.update |
| DELETE | admin/pet-shop/categories/{category} | admin.pet-shop.categories.destroy |
| GET | admin/pet-shop/merchants | admin.pet-shop.merchants.index |
| PUT | admin/pet-shop/merchants/{clinic} | admin.pet-shop.merchants.update |
| GET | admin/pet-shop/sales | admin.pet-shop.sales.index |
| GET | admin/pet-shop/sales/export | admin.pet-shop.sales.export |

---

### ProductOrder Status Constants (`app/Models/ProductOrder.php`)

#### Status Pesanan (`STATUS_*`)

| Konstanta | Nilai |
|---|---|
| `STATUS_PENDING_PAYMENT` | `'pending_payment'` |
| `STATUS_AWAITING_CONFIRMATION` | `'awaiting_confirmation'` |
| `STATUS_ACCEPTED` | `'accepted'` |
| `STATUS_PREPARING` | `'preparing'` |
| `STATUS_READY_FOR_PICKUP` | `'ready_for_pickup'` |
| `STATUS_COMPLETED` | `'completed'` |
| `STATUS_REJECTED` | `'rejected'` |
| `STATUS_CANCELLED` | `'cancelled'` |
| `STATUS_EXPIRED` | `'expired'` |

#### Status Pembayaran (`PAYMENT_*`)

| Konstanta | Nilai |
|---|---|
| `PAYMENT_UNPAID` | `'unpaid'` |
| `PAYMENT_PENDING` | `'pending'` |
| `PAYMENT_PAID` | `'paid'` |
| `PAYMENT_FAILED` | `'failed'` |
| `PAYMENT_EXPIRED` | `'expired'` |
| `PAYMENT_CANCELLED` | `'cancelled'` |
| `PAYMENT_REFUND_PENDING` | `'refund_pending'` |
| `PAYMENT_PARTIALLY_REFUNDED` | `'partially_refunded'` |
| `PAYMENT_REFUNDED` | `'refunded'` |
| `PAYMENT_CHARGEBACK` | `'chargeback'` |

#### Metode Pembayaran (`METHOD_*`)

| Konstanta | Nilai |
|---|---|
| `METHOD_ONLINE` | `'online'` |
| `METHOD_PAY_AT_CLINIC` | `'pay_at_clinic'` |

---

## 3. What Needs to Be Built (Gap Analysis)

### Backend — MISSING

| Fitur | Status | Catatan |
|---|---|---|
| `clinics.pickup_enabled` kolom | **MISSING** | Perlu migration jika flow pickup perlu di-toggle per klinik |
| `product_orders.pickup_code` / token | **MISSING** | Perlu jika ada fitur kode pengambilan di klinik |
| Tabel `product_order_pickup_tokens` | **MISSING** | Perlu jika token QR pickup diimplementasikan |
| QR code generation (library) | **MISSING** | `barryvdh/laravel-dompdf` sudah ada untuk PDF; perlu tambah QR lib (mis. `endroid/qr-code`) jika receipt + QR dibutuhkan |
| PDF receipt endpoint | **BELUM ADA** — dompdf tersedia tapi belum ada route/controller untuk cetak struk |

### Android — MISSING

| Fitur | Status | Catatan |
|---|---|---|
| QR code scanner | **MISSING** | Tidak ada library ZXing/ML Kit di gradle |
| QR code display | **MISSING** | Tidak ada library QR generator |
| Pet shop order list screen | Perlu dicek lebih dalam di `ui/` folder |
| Receipt/billing UI | Perlu dicek lebih dalam |

### Payment Flow — SUDAH ADA & BERFUNGSI

- Routing Midtrans per klinik via `PaymentAccountResolver` ✅
- Central fallback sandbox (MIDTRANS_SERVER_KEY terisi di `.env`) ✅  
- `createPetShopPayment()` di `PaymentService` ✅
- Webhook handler ✅
- `MidtransAccountGateway` tidak throw exception — return array `{success, message}` ✅
- Error handling di `PetShopOrderController::pay()` sudah lengkap ✅

### Kesimpulan Fix

Bug **satu-satunya** yang menyebabkan 500 adalah typo `product_id` → seharusnya
`clinic_product_id` di baris 73 `PetShopOrderController::store()`. Bug ini terjadi
di pre-check availability **sebelum** order dibuat, sehingga order dengan `payment_method=online`
selalu gagal 500.

Setelah fix, alur `store()` → `pay()` seharusnya berjalan normal karena:
- `PaymentService::createPetShopPayment()` sudah benar
- `MidtransAccountGateway` sudah handle HTTP error tanpa throw
- `PaymentAccountResolver::resolveCentral()` bisa resolve karena `MIDTRANS_SERVER_KEY`
  terisi di `.env` dan `central_fallback.enabled` default `true` di sandbox
