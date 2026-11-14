# PetHeal — Rangkuman Perubahan Sesi (sebelum vs sesudah sidang)

> Dokumen ini untuk **Anda dan penguji**. Isinya: apa yang berubah, mengapa,
> file mana yang tersentuh, dan **halaman mana yang perlu dicek visualnya**.
> Semua angka diambil dari source control dan hasil test, bukan perkiraan.

Terakhir diperbarui: 2026-11-12 · **99 file** (58 diubah + 41 baru) ·
**324 test lulus** (1711 assertions).

---

## 1. Empat fitur besar yang ditambahkan

| # | Fitur | Sebelum | Sesudah |
|---|---|---|---|
| 1 | **Rekam medis SOAP + lampiran** | Hanya diagnosis/tindakan/obat sebagai teks bebas. Tidak bisa melampirkan rontgen atau hasil lab. | Catatan **S-O-A-P** terstruktur + lampiran **JPG/PNG/WEBP/PDF** (maks 5 MB) yang tampil di aplikasi pasien |
| 2 | **Stok obat** | Obat hanya teks; biaya obat diketik manual; stok tidak ada | Master obat per klinik, **buku mutasi stok**, stok **berkurang otomatis** saat resep disimpan, peringatan stok menipis |
| 3 | **Antrian harian** | Tidak ada konsep antrian | Nomor antrian **saat pasien datang**, papan antrian admin, posisi tunggu di aplikasi pasien |
| 4 | **Refund manual** | Booking batal tetap dihitung sebagai pendapatan; tidak ada cara mencatat uang kembali | Catatan refund per transaksi (transfer/tunai/e-wallet) yang **mengurangi pendapatan terkumpul** |

---

## 2. Untuk apa sih refund itu? (jawaban untuk penguji)

**Masalah nyata:** pasien membayar Rp 300.000, lalu membatalkan. Uangnya sudah
masuk ke kas klinik. Di sistem lama, pembatalan hanya mengubah status menjadi
`cancelled`, tetapi **angka "pendapatan terkumpul" tetap menghitung Rp 300.000
itu** — padahal uang itu harus dikembalikan. Laporan jadi lebih besar daripada
uang yang benar-benar milik klinik.

**Refund menyelesaikan dua hal:**

1. **Bukti keuangan.** Setiap pengembalian dicatat: berapa, kapan, lewat cara
   apa (transfer/tunai/e-wallet), nomor referensi bank, alasan, dan siapa admin
   yang mencatat. Kalau penguji bertanya "mana bukti uangnya dikembalikan?",
   jawabannya ada di halaman detail booking.
2. **Laporan yang jujur.** Total "terkumpul" otomatis dikurangi sebesar refund,
   jadi dashboard menampilkan uang yang benar-benar diterima klinik.

**Batas yang sengaja dipilih:** sistem ini **tidak mentransfer uang sendiri**.
Refund lewat API Midtrans butuh izin produksi, biaya, dan aturan bisnis yang
harus diputuskan klinik (potong biaya admin atau tidak). Untuk UKK, pencatatan
manual lebih aman dan tetap bisa dipertanggungjawabkan — teks bantuan di form
menyatakan ini secara eksplisit kepada admin.

**Aturan yang dijaga sistem:**
- Tidak bisa mengembalikan lebih dari yang **pernah dibayar**.
- Beberapa refund **boleh** (bertahap), asal totalnya ≤ uang yang dibayar.
- Hanya booking **yang sudah dibatalkan** yang bisa direfund.
- Tidak bisa refund booking yang belum dibayar sama sekali.

---

## 3. File yang berubah (bukti source control)

```
58 file diubah · 41 file baru · +7.879 baris
```

Angka diambil dari `git status --porcelain --untracked-files=all` dan
`git diff --shortstat HEAD` pada batch yang belum di-commit. Total baris =
2.624 baris sisipan pada file lama + 5.255 baris isi file baru.

### File baru (27)

**Migrasi (7)**
```
add_soap_to_medical_records_table
create_medical_record_attachments_table
create_medicines_table
create_medicine_stock_movements_table
create_prescription_items_table
add_queue_fields_to_bookings_table
create_booking_refunds_table
```

**Model (4)** `Medicine` · `MedicineStockMovement` · `PrescriptionItem` ·
`MedicalRecordAttachment` · `BookingRefund`

**Service (5)** `MedicalRecordAttachmentService` · `MedicineStockService`
· `PrescriptionService` · `QueueService` · `RefundService`

**Controller (2)** `Admin/MedicineController` · `Admin/QueueController`

**View admin (10)** `admin/medicines/{index,create,edit,movements}`,
`admin/queue/{index,select_clinic}`,
`admin/medical-records/partials/{soap-fields,attachments-fields,prescription-fields}`

**Test (5)** `MedicalRecordSoapAndAttachmentsTest` · `MedicineStockTest` ·
`ClinicQueueTest` · `RefundTest` · `DataIntegrityGuardsTest`

### File diubah (36) — yang paling berdampak

| Area | File | Perubahan |
|---|---|---|
| Model | `Booking.php` | kolom antrian + relasi refund |
| | `MedicalRecord.php` | SOAP, lampiran, resep, masking |
| Admin | `MedicalRecordController.php` | simpan SOAP, lampiran, resep, kembalikan stok |
| | `MedicineController.php` *(baru)* | CRUD obat + stok masuk/koreksi |
| | `QueueController.php` *(baru)* | papan antrian |
| | `PaymentController.php` | aksi refund + laporan dikurangi refund |
| | `DashboardController.php` | peringatan stok, pendapatan bersih |
| | `DoctorController.php` | hapus dokter tidak memusnahkan riwayat |
| | `BookingController.php` | data refund di detail booking |
| Dokter | `DoctorMedicalRecordController.php` | SOAP, lampiran, resep |
| API | `BookingController.php` | check-in + nomor antrian |
| | `MedicalRecordController.php` | kirim SOAP & lampiran |
| | `PetController.php` | validasi upload foto |
| | `MidtransWebhookController.php` | `hash_equals` |
| Service | `PaymentStatusService.php` | nominal vs sisa tagihan |
| View | `layouts/admin.blade.php` | menu Stok Obat + Antrian |
| | `admin/bookings/show.blade.php` | panel refund |
| | `admin/dashboard.blade.php` | banner stok |
| | `admin/medical-records/{create,edit,show}.blade.php` | SOAP/lampiran/resep |
| Android | `Models.kt` | SOAP, lampiran, kolom antrian |
| | `BookingsScreen.kt` | kartu antrian + tombol check-in |
| | `BookingViewModel.kt` | aksi check-in |
| | `BookingRepository.kt`, `ApiService.kt` | endpoint check-in |
| | `MedicalRecordsScreen.kt` | blok SOAP + lampiran |
| Lang | `lang/{id,en}/{admin,menu}.php` | ±118 string baru |

---

## 4. Halaman yang WAJIB Anda cek visualnya

Urut dari yang paling besar perubahannya.

### Web admin

| # | Halaman | URL | Yang harus dilihat |
|---|---|---|---|
| 1 | **Antrian** *(baru)* | `/admin/queue` | 4 kartu statistik; 4 panel (sedang ditangani, menunggu, belum check-in, selesai). Coba **Check-in**, **Mulai periksa**, **Selesai**, **Tidak hadir**, **Panggil berikutnya** |
| 2 | **Stok Obat** *(baru)* | `/admin/medicines` | 3 kartu ringkasan, filter *Stok menipis* & *Hampir kedaluwarsa*, harga stok merah bila menipis |
| 3 | **Ubah obat + mutasi** *(baru)* | `/admin/medicines/{id}/edit` | Panel kiri form, panel kanan **Stok saat ini**, form *Stok masuk*, form *Koreksi stok*, riwayat mutasi |
| 4 | **Form rekam medis** | `/admin/medical-records/create/{bookingId}` | Blok **Catatan SOAP** (4 kolom), **Resep obat** (pilih obat → tampil sisa stok), **Lampiran pemeriksaan** |
| 5 | **Detail rekam medis** | `/admin/medical-records/{id}` | Blok S/O/A/P, daftar lampiran (bisa diklik), daftar resep + subtotal |
| 6 | **Detail booking → panel Pembayaran** | `/admin/bookings/{id}#pembayaran` | Panel **Pengembalian dana**: total sudah dikembalikan, riwayat, form *Catat pengembalian* |
| 7 | **Dashboard** | `/admin` | Banner **"Stok obat perlu perhatian"** (muncul bila ada obat menipis/kedaluwarsa) |
| 8 | **Portal dokter → form rekam medis** | `/doctor/medical-records/create/{bookingId}` | Panel **Resep Obat** + **Lampiran Pemeriksaan** (unggah JPG/PDF benar-benar tersimpan) |
| 9 | **Sidebar admin** | semua halaman admin | Dua menu baru: **Antrian** dan **Stok Obat** |
| 10 | **Picker klinik antrian** | `/admin/queue` sebagai super admin tanpa klinik | Daftar klinik bisa diklik → kembali ke papan antrian, bukan 500 |

### Aplikasi Android

| # | Layar | Yang harus dilihat |
|---|---|---|
| 1 | **Booking → tab Jadwal** | Tombol **"Check-in & ambil nomor antrian"** (hanya hari jadwal, status *confirmed*, belum punya nomor). Saat ditekan: tombol jadi **"Memproses check-in…"** lalu muncul snackbar nomor antrian |
| 2 | **Booking → kartu setelah check-in** | Strip biru: **nomor antrian** + status + **"2 antrian di depan"** / **"Giliran Anda"** — tetap tampil setelah daftar di-refresh |
| 3 | **Booking → seksi "Hari ini di klinik"** | Semua booking hari ini (termasuk yang sudah check-in), subjudul "Kunjungan dan antrian hari ini" |
| 4 | **Rekam medis → detail** | Blok **S/O/A/P** (hanya yang diisi) + **Lampiran pemeriksaan** yang bisa diketuk |

### Cara cepat menguji di lokal

```powershell
# 1. Siapkan data uji
cd PetHeal_Backend
php artisan migrate           # 7 migrasi baru

# 2. Backend
php artisan serve

# 3. Android
cd ../PetHeal_Android
.\gradlew assembleDebug
```

Skenario uji 10 menit:
1. Admin → **Stok Obat** → tambah obat dengan stok awal 10.
2. Booking → konfirmasi booking hari ini → **Check-in** (nomor 1 muncul).
2b. Di aplikasi pasien, buka booking hari yang sama → tombol check-in.
3. Admin → **Antrian** → *Mulai periksa* → **Selesai**.
4. Dokter/admin → buat rekam medis dengan resep 4 tablet → stok jadi 6.
5. Batalkan booking yang sudah dibayar → **Catat pengembalian** → cek dashboard
   "terkumpul" berkurang.

---

## 5. Status verifikasi

| Item | Hasil |
|---|---|
| `php artisan test` | **324 passed** (1711 assertions) — suite penuh, dijalankan ulang tanpa proses lain |
| `php artisan view:cache` | semua Blade terkompilasi |
| `compileDebugKotlin` | BUILD SUCCESSFUL |
| `assembleDebug` | **BUILD SUCCESSFUL** (6m18s) — APK debug terbaru |
| Migrasi DB dev + `pet_heal_test` | 7 migrasi baru, keduanya jalan |
| `php -l` file PHP yang diubah | bersih (13 file) |
| Uji di perangkat | **BELUM** — perlu Anda lakukan |
| Browser / Lighthouse / axe-core | **BELUM** — tool tidak tersedia di sesi ini |

Catatan kejujuran: satu kali jalan sempat menampilkan 34 gagal
(`Table 'pet_heal_test.clinics' doesn't exist`) karena dua runner test berjalan
bersamaan di database yang sama. Bukan regresi; setelah dijalankan sendiri,
seluruh suite hijau.

---

## 6. Bug yang ditemukan & diperbaiki selama pengerjaan

Ini bukan daftar teori; semuanya benar-benar terjadi dan sudah dikunci test.

1. **Closure transaksi kurang `$request`** → 4 test alur rekam medis gagal.
2. **`queue_date` dibandingkan objek vs string** → check-in ganda memberi nomor baru (1 lalu 2).
3. **Objek model usang di `adjustTo()`** → koreksi stok opname menghitung selisih dari angka basi.
4. **`stock` tidak mass-assignable** → `Medicine::create(['stock'=>10])` menyimpan 0.
5. **Urutan migrasi** → `prescription_items` dibuat sebelum tabel `medicines`.
6. **Bookings batal dihitung pendapatan** → laporan lebih besar dari uang nyata.

---

## 6k. Sesi keduabelas: kenapa fitur Android "tidak ketemu" + import obat

### 6k.1 Akar masalah: bukan fitur hilang, gerbangnya terlalu ketat

Kode tombol check-in, strip antrian, dan blok SOAP **memang sudah ada**
(`BookingsScreen.kt`, `MedicalRecordsScreen.kt`). Tetapi di perangkat terasa
tidak ada karena tiga hal:

| Penyebab | Akibat yang terlihat |
|---|---|
| Status dibandingkan `== "confirmed"` (persis) | Staf menyimpan "Confirmed"/" confirmed " → tombol **tidak pernah** muncul |
| Tombol hanya muncul bila `bookingDate == hari ini` | Booking minggu depan **memang tidak** menampilkan tombol — fitur terlihat tidak ada |
| Tidak ada penanda "hari ini" di daftar | Booking hari ini tenggelam di bawah booking mendatang |

Perbaikan di `BookingsScreen.kt`:

- `isConfirmedLike()` — status dibandingkan setelah `trim().lowercase()`.
- `isToday()` — tanggal `Y-m-d` maupun ISO lengkap (`2026-11-11T00:00:00Z`)
  dibandingkan sebagai tanggal, bukan string mentah.
- `canCheckInNow()` — satu definisi untuk daftar & kartu, supaya gerbangnya
  tidak bisa berbeda.
- Seksi **"Hari ini di klinik"** di atas daftar Jadwal, dengan subjudul
  "Ambil nomor antrian saat tiba".
- Booking hari ini yang belum bisa check-in **menyebut alasannya**
  ("Menunggu konfirmasi klinik sebelum bisa check-in.") alih-alih diam.
- Label posisi diperjelas: `"2 antrian"` → `"2 antrian lagi"`.

Blok SOAP & lampiran **tidak diubah** — kodenya sudah benar
(`MedicalRecordsScreen.kt:1143` dan `:1421`). Kalau tetap tidak terlihat,
penyebabnya **data**: rekam medis lama belum punya SOAP (disembunyikan
sengaja agar tidak muncul blok kosong) dan belum punya lampiran. Isi lewat
form rekam medis baru untuk melihatnya.

### 6k.2 Import CSV/Excel stok obat

Library `maatwebsite/excel` sudah terpasang (dipakai import layanan), jadi
tidak ada dependensi baru. File baru:

| Berkas | Peran |
|---|---|
| `app/Imports/MedicinesImport.php` | Pembaca CSV/XLS/XLSX, validasi per baris, denah kesalahan |
| `app/Exports/MedicinesTemplateExport.php` | Template **dan** ekspor data yang sudah ada (kolom sama) |
| `app/Exports/MedicinesImportErrorReportExport.php` | Laporan baris gagal |
| `MedicineController@import`, `downloadTemplateCsv/Xlsx`, `exportCsv/Xlsx`, `downloadImportErrorReport` | Aksi web |
| Rute `admin.medicines.{import,template.*,export.*,import.error-report}` | Didaftarkan **sebelum** resource |
| `resources/views/admin/medicines/index.blade.php` | Modal import + panel ringkasan hasil |

**Aturan yang dijaga (dan dikunci test):**

1. Import **selalu** masuk ke klinik pemanggil; `clinic_id` tidak pernah dibaca
   dari file. Tanpa konteks klinik import ditolak.
- **Stok tidak ditulis langsung.** Untuk obat baru, kolom `stock` menjadi stok
  awal **lewat buku mutasi**. Untuk obat lama, kolom `stock` **diabaikan** —
  stok hanya berubah lewat stok masuk/koreksi.
- Sel kosong **tidak menghapus** data lama (harga 3000 tidak menjadi 0 hanya
  karena kolom di file kosong).
- Satu baris gagal tidak menghentikan baris lain; semua kesalahan bisa diunduh
  sebagai CSV/XLSX.
- Angka "Rp 3.500" dan `12.500` dibaca sebagai 3500 / 12500, bukan 3,5.
- Tanggal menerima `2027-06-30`, `30/06/2027`, dan serial number Excel.
- Batas 2.000 baris per unggahan, maksimal 4 MB.

**Bug yang tertangkap test saat pengerjaan:** `unit` semula divalidasi
`required`, sehingga baris update harga (yang selnya kosong) **selalu gagal**.
Sekarang `unit` wajib hanya untuk obat baru.

### 6k.3 Verifikasi

```
php artisan test --filter=MedicineImportTest → 10 passed (48 assertions)
php artisan test (suite penuh)               → 308 passed (1.658 assertions)
php artisan view:cache                       → semua Blade terkompilasi
assembleDebug                                → BUILD SUCCESSFUL · APK 28,7 MB
compileDebugKotlin                           → BUILD SUCCESSFUL
```

## 6l. Audit Android: sisa asumsi "satu klinik" (sesi ketigabelas)

Audit seluruh layar menemukan tiga tempat yang masih membaca klinik dari
**preferensi sesi** (klinik terakhir dipakai), padahal pasien bisa berobat di
beberapa klinik. Semua berdampak nyata pada dokumen yang dicetak.

| # | Lokasi | Sebelum | Sesudah |
|---|---|---|---|
| 1 | Beranda — header | "Selamat datang di <Klinik>" → seolah pasien hanya di klinik itu | `Halo, <Nama>!` + label kecil **"Klinik terakhir dipakai"** |
| 2 | Struk pembayaran (tab Riwayat) | `clinicName = state.clinicName` (klinik sesi) | `booking.clinic?.name` — **klinik milik booking** |
| 3 | Struk biaya tambahan (rekam medis) | `state.clinicName` (klinik sesi) | `record.clinic?.name` — **klinik milik rekam medis** |

### Akar masalah: server tidak mengirim klinik per transaksi

`GET /api/bookings` sudah memuat `clinic`, tetapi `GET /api/medical-records`
**tidak** menyertakan klinik sama sekali. Akibatnya aplikasi tidak punya data
benar dan menebak dari preferensi.

Perbaikan backend:

- `BookingController@index` — `clinic` dipastikan ikut pada eager-load.
- `MedicalRecordController@serializeRecord` — memuat `booking.clinic` dan
  mengirim objek `clinic` (`id`, `name`, `slug`, `address`, `phone`,
  `logo_url`, `primary_color`). `null` bila klinik tidak terbaca — **bukan**
  diganti klinik lain.
- `ClinicRef` Android + `Booking` model Android mendapat field klinik.

### Bukti

`tests/Feature/ApiClinicIdentityTest.php` (3 tes) membuat **satu pasien dengan
dua booking di dua klinik** dan memastikan tiap respons membawa kliniknya
sendiri, termasuk nama, alamat, dan warna.

```
php -l (semua file PHP)          → bersih
ApiClinicIdentityTest            → 3 passed (12 assertions)
compileDebugKotlin               → BUILD SUCCESSFUL
```

**Batasan yang diakui:** `bookings.clinic_id` adalah NOT NULL, sehingga kondisi
"booking tanpa klinik" tidak bisa dibuat dan tidak diuji. Aplikasi tetap punya
tampilan cadangan bila `clinic` null (misale klinik tidak lagi terbaca).

### Yang sengaja TIDAK diubah

- **Peringatan di detail klinik** ("Booking akan di klinik ini, bukan <klinik
  favorit>") sudah benar: tombol di sana memang mengganti klinik transaksi.
- **Header `X-Clinic-Slug`** tetap ada; ia penanda konteks, bukan otoritas.
- **Picker klinik saat login** tetap, karena branding portal memang per klinik.

## 6m. Audit pasca-implementasi: 7 bug P0 (sesi keempatbelas)

Empat fitur besar sudah terpasang, tetapi audit menemukan **tujuh bug di jalur
harian** yang tidak tertangkap test lama (test lama menguji service, bukan alur
form/HTTP penuh). Ketujuhnya sudah diperbaiki dan dikunci test regresi.

| # | Gejala yang dilihat pengguna | Penyebab | Perbaikan |
|---|---|---|---|
| 1 | Simpan rekam medis **baru** + pilih obat → halaman error 500 | Item resep ditulis sebelum rekam medis punya `id` (FK NOT NULL) | `$record->save()` sebelum `syncForRecord()`, di admin & dokter |
| 2 | Stok obat makin banyak setiap kali resep dikoreksi | Pengembalian stok menjumlahkan semua baris `out`, bukan net `out + return` | Hitung net, lewati bila tidak ada sisa pemakaian |
| 3 | Booking batal + refund penuh → "terkumpul" jadi **minus** | Status `cancelled` dikeluarkan dulu, refund dikurangkan lagi | Satu formula: `SUM(paid_amount) − refund`, dipakai payments & dashboard |
| 4 | Super admin tanpa klinik membuka **Antrian** → 500 | Picker memakai route yang tidak ada | Form POST ke `admin.switch-clinic` + `return_to=queue` |
| 5 | Nomor antrian hilang dari kartu setelah daftar di-refresh | `queue_label`/`queue_position` hanya dikirim saat check-in | `attachQueueMeta()` dipakai `index`, `upcoming`, `show`, `check_in` |
| 6 | Tekan **Check-in** di aplikasi, tidak ada reaksi apa pun | Pesan ditulis ke state detail, tombolnya ada di daftar | State daftar + snackbar sukses/gagal + tombol "Memproses check-in…" |
| 7 | Dokter tidak bisa mengunggah lampiran (file tidak terkirim) | Form dokter tanpa `enctype="multipart/form-data"` | Multipart + partial lampiran yang sama dengan admin |

### P1 yang ikut dibereskan

- **Hapus obat** membedakan "dipakai resep" dan "punya riwayat stok lain";
  keduanya menonaktifkan, bukan menghapus — buku mutasi tetap bisa diaudit.
- **Tanggal papan antrian** divalidasi; `?date=abc` kembali ke hari ini, bukan 500.
- **Check-in API** mencatat audit atas nama pasien dan mengulang maksimal 3×
  bila nomor antrian bentrok karena dua pasien datang bersamaan.
- **"Hari ini di klinik"** kini memuat semua booking hari ini (termasuk yang
  sudah check-in), bukan hanya yang masih bisa check-in.
- **`currentClinic()`** memakai cache per request — cache statis lama bisa
  membocorkan klinik antar-tenant dan membuat suite penuh gagal hanya pada
  urutan test tertentu.

### Cara memeriksa sendiri (5 menit)

1. Admin → **Rekam Medis** → buat baru untuk booking hari ini → pilih 1 obat →
   simpan. Harus sukses; stok obat berkurang tepat sejumlah qty.
2. Ubah resep yang sama 3× (qty 4 → 6 → 3). Stok akhir harus `stok awal − 3`,
   bukan makin besar.
3. Batalkan booking yang sudah dibayar penuh → **Catat pengembalian** sebesar
   total → angka "terkumpul" harus **turun sebesar itu**, tidak minus.
4. Login sebagai super admin **tanpa** memilih klinik → menu **Antrian** →
   muncul daftar klinik (bukan 500) → pilih satu → kembali ke papan antrian.
5. Aplikasi pasien → booking hari ini → **Check-in** → muncul snackbar berisi
   nomor antrian, kartu langsung menampilkan nomor, tombol tidak bisa ditekan
   dua kali.
6. Login sebagai dokter → buka form rekam medis → unggah JPG/PDF → tersimpan
   dan muncul di daftar lampiran.
7. Refresh daftar booking di aplikasi → nomor antrian **tetap** tampil.

### Batasan yang diakui

Lampiran rekam medis berada di disk `public`, jadi URL-nya bisa dibuka tanpa
login oleh orang yang tahu alamatnya. Untuk UKK diterima; produksi harus
memakai disk privat + signed URL. Belum dikerjakan di sesi ini.

### Verifikasi

```
php artisan test                 → 324 passed (1711 assertions)
assembleDebug                    → BUILD SUCCESSFUL (6m18s)
php -l (13 file PHP diubah)      → bersih
```

Browser, Lighthouse, axe-core, dan uji perangkat **belum** dijalankan (tool
tidak tersedia di sesi ini) — jangan diklaim lulus.

## 7. Yang sengaja TIDAK dikerjakan (jawaban jujur untuk penguji)

| Hal | Alasan |
|---|---|
| Refund otomatis via API Midtrans | Kebijakan uang (potong biaya admin?) harus diputuskan klinik; perlu izin produksi |
| Ubah `bookings.status` untuk antrian | Sumbu kehadiran & administrasi sengaja dipisah; menggabung akan merusak laporan & enum yang dipakai banyak tes |
| Nomor antrian dipesan jauh hari | Nomor hanya bermakna di hari kedatangan |
| Stok obat multi-gudang / transfer antar klinik | Di luar cakupan UKK; sudah ada fondasi tabel mutasi |
| Upload database klaim/lab PDF otomatis terbaca | Lampiran sekarang disimpan & ditampilkan, bukan di-OCR |

## 8. Addendum — Midtrans multi-klinik (14 November 2026)

### Sebelum

- Booking dan Pet Shop sudah punya `PaymentAccountResolver`, tetapi biaya
  tambahan rekam medis (`MEDREC-*`) masih memanggil `MidtransService` dengan
  Server Key pusat.
- Rekonsiliasi payment ber-route masih memakai kredensial pusat.
- Webhook MEDREC belum memvalidasi merchant/gross/currency dan tidak menulis
  baris `payments`.
- VCMS belum memisahkan panel Sandbox/Production, belum punya salin URL,
  monitor routing, jejak audit, atau konfirmasi password production.

### Sesudah

- `PaymentService::createMedicalRecordPayment()` merutekan MEDREC ke akun klinik;
  nominal, merchant, dan order id diputuskan server.
- Webhook MEDREC/booking/Pet Shop memvalidasi merchant, gross, currency; MEDREC
  menulis status payment + `extra_payment_*` dalam satu alur.
- Rekonsiliasi dan sync manual memakai akun terkunci transaksi.
- VCMS punya panel per environment, salin Notification URL, monitor routing +
  signature invalid, audit trail, dan re-auth password untuk aksi production.
- Panduan admin: `PetHeal_Backend/docs/MIDTRANS_CLINIC_ADMIN_GUIDE.md`.

### Verifikasi

```
php artisan test --filter=ClinicPaymentRoutingTest    → 21 passed (129 assertions)
php artisan test --filter="PetShopTest|PaymentSplitCalculatorTest|AdminConsoleSmokeTest|RefundTest"
                                                      → 36 passed (337 assertions)
php artisan view:cache                                → sukses
php -l (file PHP diubah)                              → bersih
```

Browser, Lighthouse, axe-core, dan uji perangkat **belum** dijalankan pada sesi
ini; jangan diklaim lulus.
