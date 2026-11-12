# PetHeal — Multi-Clinic Discovery (Before / After)

> **Untuk AI agent berikutnya.** Baca dokumen ini sebelum menyentuh kode. Isinya bukan
> changelog, tapi *mengapa* arsitektur berubah, apa yang sudah final, dan apa yang
> sengaja belum dikerjakan. Semua klaim di sini punya bukti di file yang disebut.
>
> Terakhir diperbarui: 2026-11-09 · 70 file berubah (65 entri git) · build APK hijau.

---

## 1. Ringkas: satu paragraf konteks

PetHeal awalnya aplikasi **satu-klinik**: user memilih klinik saat mendaftar, lalu
seluruh aplikasi terkunci ke klinik itu. Hari ini diubah menjadi **platform penemuan &
booking multi-klinik**: pasien mendaftar tanpa klinik, bisa menjelajah banyak klinik,
membandingkan harga layanan setara, dan booking di klinik berbeda pada tiap transaksi.
Staf klinik tetap terkurung ke kliniknya sendiri.

Perubahan terbesar bukan fiturnya, tapi **model identitas**: pasien jadi akun global,
staf tetap tenant-scoped.

---

## 2. Sebelum vs Sesudah

| Aspek | SEBELUM | SESUDAH |
|---|---|---|
| Pendaftaran | Wajib memilih klinik | Tanpa klinik (`clinic_id = NULL` sah) |
| Route terproteksi untuk pasien | 403 bila tanpa klinik | Diizinkan (pasien global) |
| Staf tanpa klinik | 403 | **Tetap 403** (isolasi tenant tidak dilonggarkan) |
| Penemuan klinik | Tidak ada | `GET /api/public/clinics` + filter & jarak |
| Perbandingan harga | Tidak ada | Berbasis **kategori layanan standar**, bukan nama mirip |
| Kategori layanan | Teks bebas per klinik | Tabel `service_categories` global (super_admin) |
| Rating klinik | Tidak ada di tabel klinik | Kolom `rating` + `reviews_count`, agregasi otomatis dari ulasan dokter |
| Lokasi klinik | Tidak ada | `latitude`, `longitude`, jam operasional, hari buka, website |
| Jarak | Tidak ada | Haversine di backend & Android, **tanpa** Play Services |
| Booking per klinik | Terkunci 1 klinik | Pasien boleh booking di klinik berbeda per transaksi |
| Android | Hanya layar klinik sendiri | + Cari Klinik, Detail Klinik, Bandingkan Harga |

---

## 3. Aturan identitas (PALING PENTING — jangan dilanggar)

Satu tempat menentukan peran: [`PatientScope.php`](../PetHeal_Backend/app/Services/PatientScope.php).

```
role = user        → PASIEN. Akun global. clinic_id bukan otorisasi.
role = doctor      → STAF. Wajib clinic_id aktif & sama dengan header.
role = clinic_admin→ STAF. Sama.
role = admin       → STAF. Sama.
role = super_admin → Bypass, boleh tanpa klinik.
```

Konsekuensi yang sudah diuji (`MultiClinicDiscoveryTest`):

- `test_registration_never_requires_or_binds_a_clinic`
- `test_patient_without_clinic_can_call_protected_endpoints`
- `test_clinic_staff_without_clinic_is_still_blocked`
- `test_one_patient_can_book_in_two_different_clinics`
- `test_doctor_cannot_see_other_clinic_booking`

**Guardrail:** header `X-Clinic-Slug` adalah penanda konteks *tenant* untuk staf, **bukan**
sumber otorisasi. Untuk pasien nilainya diabaikan. Lihat
[`ApiAuthenticate.php`](../PetHeal_Backend/app/Http/Middleware/ApiAuthenticate.php).

---

## 4. Backend — apa yang ditambahkan

### 4.1 Skema database (3 migrasi baru)

`2026_11_09_000002_add_location_to_clinics_table.php`
```
latitude DECIMAL(10,7) NULL · longitude DECIMAL(10,7) NULL
rating DECIMAL(3,2) NULL · reviews_count UNSIGNED INT DEFAULT 0
opening_time TIME NULL · closing_time TIME NULL
operating_days JSON NULL · website VARCHAR NULL
featured_image_path VARCHAR(512) NULL
```

`2026_11_09_000003_create_service_categories_table.php`
```
id · name · slug UNIQUE · description · icon · is_active · timestamps
```

`2026_11_09_000004_add_category_id_to_services_table.php`
```
services.service_category_id → FK ke service_categories (nullable, beralasan:
layanan lama tanpa kategori tetap valid, hanya tidak bisa dibandingkan)
```

> **Rating nullable itu disengaja.** `NULL` = belum ada ulasan, **bukan** 0.
> UI wajib menampilkan "Belum ada rating". Jangan diganti default 0.

### 4.2 Endpoint publik baru

Semua di bawah `Route::prefix('public')->middleware('throttle:public-catalog')`,
**tanpa auth** — discovery harus jalan sebelum login.
Lihat [`routes/api.php:55-65`](../PetHeal_Backend/routes/api.php).

| Endpoint | Fungsi |
|---|---|
| `GET /api/public/clinics` | Daftar klinik + `search`, `sort` (name/rating/distance), `lat`/`lng`, `min_rating`, `max_distance_km`, `category`, `service`, `open_now`, `sync_rating` |
| `GET /api/public/clinics/{slug}` | Detail klinik + daftar layanan berkategori |
| `GET /api/public/clinics/{slug}/services` | Katalog layanan satu klinik |
| `GET /api/public/service-categories` | Kategori layanan global |
| `GET /api/public/services/compare` | Perbandingan (wajib `category_slug` / `service_id` / `name`) |
| `GET /api/public/services/{id}` | Detail layanan + klinik penyedia layanan setara |

Implementasi: [`PublicClinicController.php`](../PetHeal_Backend/app/Http/Controllers/Api/PublicClinicController.php)

### 4.3 Prinsip pembandingan harga

Perbandingan **selalu** memakai `service_category_id` yang sama. Pencarian nama
hanya dipakai sebagai upaya terakhir bila kategori tidak ada. Alasannya: dua klinik
bisa menamai layanan sama dengan teks berbeda ("Vaksin Rabies" vs "Rabies Vaccine").
Respons menyertakan `meta.comparison_basis` agar UI bisa jujur soal dasar pembanding.

### 4.4 Agregasi rating

[`ClinicRatingService.php`](../PetHeal_Backend/app/Services/ClinicRatingService.php)
dipanggil melalui observer pada `DoctorReview` (create/update/delete) yang
didaftarkan di [`AppServiceProvider.php`](../PetHeal_Backend/app/Providers/AppServiceProvider.php).
Jadi `clinics.rating` selalu turunan dari ulasan nyata — tidak ada angka karangan.

### 4.5 Admin web

- Field lokasi & jam operasional klinik → partial
  [`clinics/partials/location-fields.blade.php`](../PetHeal_Backend/resources/views/admin/clinics/partials/location-fields.blade.php),
  dipakai create/edit/profil agar tidak duplikasi.
- Kategori layanan (super_admin) →
  [`ServiceCategoryController.php`](../PetHeal_Backend/app/Http/Controllers/Admin/ServiceCategoryController.php)
  + view `admin/service-categories/index.blade.php`.
- Form layanan memakai partial
  [`services/partials/category-select.blade.php`](../PetHeal_Backend/resources/views/admin/services/partials/category-select.blade.php).
- Validasi koordinat: longitude wajib bila latitude diisi (dan sebaliknya) — supaya
  tidak ada klinik dengan koordinat setengah yang membuat jarak salah.

---

## 5. Android — apa yang ditambahkan

### 5.1 Lapisan data

| File | Peran |
|---|---|
| [`DiscoveryModels.kt`](../PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/data/model/DiscoveryModels.kt) | DTO discovery. **Semua harga/rating/jarak nullable** — UI wajib punya state "belum tersedia" |
| [`DiscoveryRepository.kt`](../PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/data/repository/DiscoveryRepository.kt) | Sumber data layar discovery, cache 60 detik |
| [`ApiService.kt`](../PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/data/remote/ApiService.kt) | +5 method publik |
| [`LocationHelper.kt`](../PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/util/LocationHelper.kt) | `LocationManager` + Haversine. **Tanpa** `play-services-location` |
| [`DistanceFormatter.kt`](../PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/util/DistanceFormatter.kt) | Format jarak & harga yang konsisten |

### 5.2 Layar (`ui/screens/discovery/`)

```
ClinicDiscoveryScreen  → cari/filter klinik, kartu lokasi, rail kategori
ClinicDetailScreen     → identitas + katalog layanan + aksi booking
PriceCompareScreen     → tabel perbandingan + penanda klinik aktif
+ masing-masing ViewModel
```

### 5.3 Keputusan lokasi

Lokasi **opsional dan tidak pernah memaksa**. Kegagalan GPS bukan error halaman,
hanya status di kartu lokasi. Tanpa lokasi, `distance_km = null` dan UI
menyembunyikan jarak — **bukan menampilkan 0 km**.

---

## 6. Perbaikan UI/UX (sesi kedua hari ini)

Audit menemukan tiga kebingungan struktural, bukan kosmetik:

1. **Klinik tujuan booking tidak pernah terlihat.** Klinik aktif disimpan diam-diam
   saat login; user bisa menelusuri Klinik A lalu transaksinya masuk ke Klinik B.
2. **"Booking di sini" adalah loop buntu.** `serviceId` dibuang, user dilempar balik
   ke detail klinik yang tidak punya tombol booking.
3. **Angka langkah bertentangan.** Judul "Langkah 1 dari 2" vs stepper 3 langkah.

Perbaikan yang dikerjakan:

- [`ClinicContextResolver.kt`](../PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/data/repository/ClinicContextResolver.kt)
  — **baru**, hanya *membaca* klinik aktif untuk keperluan peringatan. Tidak menulis apa pun.
- `ClinicDetailScreen` — tombol `Pilih dokter & jadwal` + `Booking` per layanan;
  banner kuning bila klinik ini ≠ klinik aktif.
- `PriceCompareScreen` — klinik aktif ditandai border hijau + label `Klinik Anda`;
  aksi mengarah ke tujuan yang benar, bukan memutar.
- `BookingStartScreen` — menampilkan `Booking di klinik: [nama]`; "1 dari 2" → **"1 dari 3"**.
- `PetHealNavHost` + `NavigationViewModel.activeClinicSlug` — rute tombol booking
  bergantung pada apakah klinik tujuan = klinik aktif.

**Batas yang dipegang:** nol perubahan pada business logic booking (`CreateBooking`,
`BookingViewModel` inti). Tombol booking di detail klinik membuka alur booking yang
sama dengan Home. Semua token warna memakai yang sudah ada (`PrimaryLight`,
`PrimaryDark`, `WarningSoft`, `WarningText`, `OnPrimaryStrong`) — tidak ada hex baru.

---

## 6b. Alur booking dipisah per klinik (sesi ketiga)

Kebingungan berikutnya bukan kosmetik: layar **Buat Booking** langkah 1 (hewan)
→ 2 (dokter) menampilkan dokter **semua klinik** tanpa satu pun penanda klinik,
padahal transaksi selalu milik satu klinik. Lebih buruk, langkah 3 memuat
**layanan semua klinik** lalu memilih `services.firstOrNull()` — pasangan dokter
klinik A + layanan klinik B ditolak backend.

Informasi arsitektur yang wajib diingat:

| Fakta | Konsekuensi |
|---|---|
| `Doctor.clinic_id` ada, `services.clinic_id` ada | katalog bisa difilter per klinik |
| Dokter & layanan **tidak** menyertakan objek klinik di JSON | respons dokter kini menyertakan `clinic` ringkas (id/name/slug/address/primary_color) |
| `BookingController@store` mengunci dokter & layanan ke klinik yang sama | UI wajib menawarkan pasangan yang mungkin |
| Klinik booking = `doctor.clinic_id`; `clinic_slug` opsional tapi **harus cocok** bila dikirim | kirim slug klinik pilihan user, bukan klinik favorit |

Perubahan:

- **API**: `GET /doctors` menerima `?clinic_id=` (klinik nonaktif/tidak dikenal
  → 404) dan mengirim `clinic` per dokter. `GET /services` menerima
  `?clinic_id=` yang didahulukan di atas slug/staf (lihat `forcedClinicId()`).
  Tanpa parameter, katalog lintas klinik tetap seperti sebelumnya.
- [`BookingStartScreen.kt`](../PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/ui/screens/booking/BookingStartScreen.kt)
  — stepper **Hewan → Klinik → Dokter**; daftar klinik lengkap dengan jumlah
  dokter aktif, klinik favorit hanya diberi label "Terakhir dipakai"; dokter
  baru muncul setelah klinik dipilih (dengan tombol `Ganti`). Rute
  `bookings/start?clinicSlug=` memilih klinik awal dari halaman detail klinik.
- [`BookingViewModel.kt`](../PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/ui/screens/booking/BookingViewModel.kt)
  — `selectedClinicId` + turunan `clinics`/`doctorsInSelectedClinic`
  (sengaja tanpa fallback "semua dokter"), `selectStartDoctor` menolak dokter
  klinik lain, `loadCreateBookingData` memuat layanan dengan
  `clinicId = doctor.clinic.id`, dan `applySelectedClinicAsActive()` menyimpan
  klinik pilihan sebagai klinik transaksi.
- `CreateBookingUiState` + (`clinicName`, `clinicAddress`, `clinicSlug`):
  nama klinik tampil di kartu dokter dan di atas CTA; `BookingRequest`
  mengirim `clinic_slug` eksplisit sehingga header `X-Clinic-Slug` yang
  menunjuk klinik favorit lain tidak lagi memblokir.
- Tombol booking di detail klinik dan perbandingan harga langsung membuka
  alur booking dengan klinik tersebut terpilih (tidak lagi berputar ke detail).
- Teks basi diperbaiki: `ClinicPickerSheet` ("satu akun terikat satu klinik")
  dan banner di detail klinik.

Bukti: `MultiClinicDiscoveryTest`
`test_doctors_catalog_exposes_and_filters_by_clinic`,
`test_doctors_catalog_rejects_unknown_clinic_id`,
`test_services_catalog_can_be_forced_to_one_clinic` — 21 lulus.
Android `assembleDebug` hijau.

**Yang masih terbuka:** data klinik pada baris dokter bersifat additif —
klien lama mengabaikannya, jadi tidak ada breaking change; tetapi `HomeScreen`
dan `DoctorsScreen` belum memakai `Doctor.clinic` untuk memberi label klinik.

## 6c. Admin non-teknis mengisi lokasi klinik (sesi keempat)

Versi pertama form lokasi meminta admin menyalin dua angka desimal
(`latitude`/`longitude`) dari Google Maps. Admin klinik bukan pengguna teknis.

Sekarang: **tulis alamat → Buka peta → klik titik klinik**, koordinat tersimpan
otomatis (juga saat marker digeser). Tidak ada angka contoh/karangan.

- [`pin-location.js`](../PetHeal_Backend/resources/js/pin-location.js) — Leaflet
  dari CDN + tile OpenStreetMap, geocoding alamat lewat Nominatim (tanpa API key).
  Pusat peta awal hanya dipakai jika klinik belum punya koordinat. Bila peta
  gagal dimuat, admin tetap punya tautan Google Maps + input manual.
- [`location-fields.blade.php`](../PetHeal_Backend/resources/views/admin/clinics/partials/location-fields.blade.php)
  — input `latitude`/`longitude` TETAP ada (sumber kebenaran) tetapi berada di
  bagian "Punya angka koordinat? (opsional)". Jangan diberi atribut `hidden`.
- `ClinicController::guardCoordinates()` — koordinat setengah ditolak dengan
  pesan "Isi keduanya, atau kosongkan keduanya", bukan nama atribut mentah.
- Entry `resources/js/pin-location.js` **wajib** terdaftar di `vite.config.js`;
  tanpa itu `@vite()` melempar "Unable to locate file in Vite manifest".

Bukti: `ClinicLocationPickerTest` (4 tes) — peta tampil, simpan koordinat,
tolak setengah, kosongkan lagi.

## 6d. Tombol "Kembali ke Beranda" setelah pembayaran (sesi kelima)

**Gejala:** di layar hasil pembayaran, tombol **Kembali ke Beranda** tidak
memindahkan user apa pun — terasa seperti tombol dummy.

**Akar masalah:** `navigateToTab()` memakai `popUpTo(Screen.Home.route)`
**tanpa** `inclusive`. Home sudah ada di back stack, sehingga tidak ada yang
di-pop: layar `PaymentResult` tetap di depan dan yang terjadi hanya salinan Home
ditumpuk di atasnya.

**Perbaikan:**
- `navigateToTab()` mem-pop **route layar saat ini** (`inclusive`), sehingga
  target tab benar-benar tampil dari layar mana pun. Route tujuan diambil dari
  graph (`findNode`) agar `bookings/start?clinicSlug={clinicSlug}` ikut match.
- `navigateBackOrHome()` baru: `popBackStack()` yang gagal (stack kosong) jatuh
  ke Beranda — tombol tidak pernah diam.
- Ketiga rute pembayaran (`Payment`, `MedicalExtraPayment`, `PaymentResult`)
  memakai helper tersebut; `BackHandler` di `PaymentScreen` kini juga aktif
  saat belum ada snap token / gagal, bukan hanya saat token ada.

**Batasan versi Navigation di proyek ini:** `popUpTo(id) { inclusive = true }`
**tidak didukung** (error kompilasi "Cannot access 'inclusive'"). Gunakan
`popUpTo(route) { inclusive = true }` dengan **route**, bukan pola dan bukan id.

### Jebakan yang termakan di sesi ini

1. **Migrasi multi-clinic belum dijalankan di DB lokal** → `GET /api/doctors/{id}`
   menjawab 500 "Unknown column 'latitude'", aplikasi menampilkan
   `Failed to get doctor` di layar booking. Selalu `php artisan migrate` setelah
   menarik perubahan skema.
2. `GET /api/doctors/{id}` kini menerima `?clinic_id=` (hanya untuk pasien) dan
   aplikasi memakainya saat membuka detail dokter klinik lain.

## 6e. Label klinik + lokasi user di luar alur booking (sesi keenam)

Daftar dokter tidak pernah menyebut kliniknya. Sekarang satu util dipakai
semua layar:

- [`DoctorListing.kt`](../PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/util/DoctorListing.kt)
  — `DoctorListing` (dokter + nama klinik + jarak), `DoctorListingMapper`
  (map & urutkan), `DoctorSort`, `DoctorLocationStatus`, `resolveUserLocation`.
  Jarak = `LocationHelper.haversineKm`, **null** bila lokasi/klinik belum ada
  (tidak pernah 0).
- **Cari Dokter** — kartu berlabel klinik + jarak, chip lokasi/urutan
  (Terdekat ⇄ Nama A–Z), izin lokasi diminta **hanya** saat chip ditekan,
  pencarian juga mencocokkan nama klinik.
- **Beranda** — seksi **Dokter terdekat** (maks. 3) dari lokasi tersimpan.
  Tanpa lokasi, seksi menampilkan ajakan "Aktifkan lokasi" — tidak meminta
  izin otomatis dan tidak mengarang jarak.
- **Detail dokter** — baris "Praktik di <klinik + alamat>".
- API: `GET /doctors` & `/doctors/{id}` menyertakan
  `clinic.{id,name,slug,address,primary_color,latitude,longitude}`.

Verifikasi: `compileDebugKotlin` + `assembleDebug` hijau (APK 28,9 MB).

## 6f. Audit pra-sidang: guard integritas data & uang (sesi ketujuh)

Audit menyeluruh (backend + Android) menemukan tiga kelas masalah yang tidak
terlihat dari layar. Semua sudah diperbaiki dan dikunci dengan test di
[`DataIntegrityGuardsTest.php`](../PetHeal_Backend/tests/Feature/DataIntegrityGuardsTest.php).

### 1. Menghapus dokter pernah ikut memusnahkan rekam medis & invoice

FK `bookings.doctor_id` dan `medical_records.doctor_id` memakai
`onDelete('cascade')`. Satu klik "hapus dokter" menghapus rekam medis, booking,
dan dengan begitu juga dasar perhitungan pendapatan klinik.

Perbaikan di [`Admin/DoctorController::destroy`](../PetHeal_Backend/app/Http/Controllers/Admin/DoctorController.php):
dokter yang punya riwayat **tidak dihapus**; profilnya dinonaktifkan
(`is_active = false`) dan admin diberi pesan. Pola ini sama dengan guard hapus
pasien di `Admin/UserController::destroy`. Dokter tanpa riwayat tetap bisa
dihapus permanen.

### 2. Booking batal ikut terhitung sebagai pendapatan

`payments` dan dashboard menjumlahkan `SUM(paid_amount)` tanpa melihat status,
padahal belum ada mekanisme refund. Angka "terkumpul" jadi lebih besar daripada
uang yang benar-benar diterima. Sekarang agregat hanya menjumlahkan transaksi
yang tidak `cancelled`, konsisten dengan cara `total_outstanding` dihitung.

> **Diganti di §6n (P0 #3).** Setelah refund ada, mengecualikan `cancelled`
> LALU mengurangkan refund membuat booking yang dibatalkan dan direfund penuh
> menyumbang angka **negatif**. Formula akhirnya: seluruh `paid_amount` − refund.

Dashboard pasien juga tidak lagi menagihkan sisa pembayaran dari booking yang
sudah dibatalkan.

### 2b. Pembayaran tidak boleh lunas dengan nominal kurang
`PaymentStatusService` sekarang membandingkan sisa tagihan sebelum menandai
`paid`/`dp_paid`. Event tetap dicatat di `payment_events` (jejak audit), dan
event duplikat tetap idempoten.

### 3. Upload & foto yang gagal diam-diam

- `POST/PUT /api/pets` menerima berkas apa pun di field `photo` (sebelumnya
  hanya `nullable`). Sekarang `image|mimes:jpeg,png,jpg,webp|max:4096`.
- Foto dokter yang gagal diproses hanya masuk log, sementara admin melihat
  "berhasil". Sekarang admin menerima pesan error eksplisit
  (`admin.doctor_photo_failed`) dan datanya tetap tersimpan.

### Verifikasi

```
php artisan test → 259 passed (1526 assertions)
```

Catatan kejujuran: pada satu kali jalan, `PayloadGateAndPermissionFixesTest`
gagal 2 test (404) karena urutan test; percobaan berikutnya hijau. Test tersebut
lulus juga saat dijalankan sendiri, berpasangan dengan test baru, dan pada suite
penuh berikutnya. Sifatnya flaky antar-test, bukan regresi dari perubahan ini.

## 6g. Tahap 1 pra-UKK: SOAP + lampiran rekam medis (sesi kedelapan)

Rekam medis sebelumnya hanya punya tiga kolom teks bebas (diagnosis,
treatment, medicine) dan tidak bisa menyimpan bukti pemeriksaan. Sekarang:

- **SOAP** — `subjective`, `objective`, `assessment`, `plan` (semua nullable).
  Kolom lama **tidak dihapus**: rekam medis lama, ekspor PDF/CSV, dan laporan
  pendapatan tetap membaca `diagnosis`/`treatment` seperti sebelumnya. Form
  dikirim sebagai grup `soap[*]`.
- **Lampiran** — tabel `medical_record_attachments` dengan `path`,
  `original_name`, `caption`, `mime_type`, `size_bytes`. Gambar di-re-encode
  ke JPEG maks 1600px (cukup untuk membaca rontgen); PDF disimpan apa adanya
  karena itu dokumen medis. Batas 5 MB, maks 10 berkas per unggahan.
  Service [`MedicalRecordAttachmentService.php`](../PetHeal_Backend/app/Services/MedicalRecordAttachmentService.php)
  dipakai bersama admin & portal dokter; menu hapus juga menghapus berkas di
  disk, bukan hanya baris database.
- **Gerbang pembayaran ikut menjaga SOAP & lampiran.** `maskClinicalFields()`
  mengosongkan empat kolom SOAP dan mengosongkan daftar lampiran saat rekam
  medis masih terkunci. Tanpa ini, diagnosis terlindungi tetapi isi
  pemeriksaan bocor — inkonsistensi yang sulit dijelaskan saat sidang.
- **API** `serializeRecord()` mengirim `subjective/objective/assessment/plan`
  dan `attachments[]` (`url`, `caption`, `original_name`, `mime_type`,
  `is_image`). Lampiran tanpa URL valid dibuang server, jadi aplikasi tidak
  pernah menampilkan gambar rusak.
- **Aplikasi pasien** — blok SOAP muncul hanya bila diisi, dan daftar
  lampiran bisa diketuk untuk dibuka lewat penampil sistem.

### Verifikasi

```
php artisan test → 264 passed (1548 assertions)
compileDebugKotlin → BUILD SUCCESSFUL
```

### Dua jebakan yang benar-benar terjadi di sesi ini

1. **Closure `use` kurang `$request`.** `DB::transaction(function () use ($record, $booking))`
   memakai `$request->file()` di dalamnya → `Undefined variable $request`,
   dan 4 test alur rekam medis gagal. Selalu daftarkan variabel luar yang
   dipakai di dalam closure.
2. **DB test sendiri tidak ikut termigrasi.** `phpunit.xml` memakai
   `pet_heal_test`, bukan DB dev. `php artisan migrate --env=testing` tidak
   menyentuhnya. Yang bekerja:
   `$env:DB_DATABASE="pet_heal_test"; php artisan migrate`.

## 6h. Tahap 2 pra-UKK: stok obat (sesi kesembilan)

Sebelumnya obat hanya teks bebas (`medical_records.medicine`) dan biaya obat
diisi manual dari dua tempat berbeda. Sekarang stok nyata, dan angkanya bisa
dipertanggungjawabkan.

### Skema

| Tabel | Isi |
|---|---|
| `medicines` | master obat per klinik: `name`, `unit`, `form`, `stock`, `min_stock`, `price`, `expiry_date`, `manufacturer`, `is_active`. Unik `(clinic_id, name)` |
| `medicine_stock_movements` | buku besar: `type` (in/out/adjustment/return), `quantity` bertanda, `stock_after`, `reason`, `medical_record_id`, `created_by` |
| `prescription_items` | resep terstruktur: `medicine_id`, salinan `medicine_name`/`unit`/`unit_price`, `dose`, `quantity` |

`stock_after` disimpan di setiap mutasi supaya setiap baris buku besar bisa
dibaca berdiri sendiri; saldo obat adalah hasil penjumlahan mutasi.

### Aturan yang dikunci

1. **Satu pintu untuk stok** — [`MedicineStockService.php`](../PetHeal_Backend/app/Services/MedicineStockService.php).
   `medicines.stock` sengaja **tidak** mass-assignable, jadi angka stok tidak
   pernah datang dari input form.
2. **Stok tidak boleh minus.** Permintaan melebihi stok melempar
   `RuntimeException` dengan pesan manusiawi ("Stok Amoxicillin tidak cukup.
   Tersedia 6 tablet, diminta 10 tablet").
3. **Koreksi mencatat selisih**, bukan angka target, dan alasan wajib diisi.
   Selisih dihitung dari stok **terkini di database**, bukan dari objek di
   memori (bug yang sempat terjadi: objek usang membuat saldo akhir meleset).
4. **Resep memotong stok sekali.** Mengedit resep mengembalikan pemotongan
   lama lebih dulu, lalu memotong ulang — bukan menumpuk.
5. **Obat klinik lain ditolak** di validasi form *dan* di service.
6. **Obat yang pernah dipakai resep tidak dihapus**, hanya dinonaktifkan —
   pola yang sama dengan guard hapus dokter/pasien.
7. **Biaya obat otomatis** dari `quantity × unit_price` bila resep terisi;
   input manual tetap dipakai bila resep kosong (klinik yang belum memakai
   master obat tidak kehilangan cara lama).
8. **Hapus rekam medis mengembalikan stok** yang pernah dipotong resepnya.

### Admin

- Menu **Stok Obat** ([`admin/medicines/`](../PetHeal_Backend/resources/views/admin/medicines)):
  daftar + filter stok menipis/kedaluwarsa/pencarian, ringkasan tiga kartu,
  form tambah/ubah, aksi **stok masuk**, **koreksi stok opname**, dan halaman
  **mutasi stok** per obat.
- Dashboard menampilkan banner peringatan bila ada obat menipis atau
  kedaluwarsa ≤60 hari. Angka ini dihitung **live**, bukan dari cache, supaya
  langsung berubah setelah resep memotong stok.

### Verifikasi

```
php artisan test  → 274 passed (1569 assertions)
php artisan view:cache → semua Blade terkompilasi
migrate (dev + pet_heal_test) → 3 migrasi baru
```

### Jebakan yang benar-benar terjadi di sesi ini

1. **Urutan migrasi.** `prescription_items` mereferensikan `medicines`; file
   migrasi awal bertanggal lebih lampau → `Failed to open the referenced
   table 'medicines'`. Migrasi yang punya FK wajib bertanggal SETELAH tabel
   induknya.
2. **`stock` sengaja tidak fillable.** Menulis `Medicine::create(['stock'=>10])`
   diam-diam menyimpan 0. Stok awal wajib lewat `setOpeningStock()`.
3. **Objek model usang setelah mutasi.** `adjustTo()` sempat menghitung selisih
   dari `$medicine->stock` di memori; setelah mutasi lain, angka itu basi.

## 6i. Tahap 3 pra-UKK: antrian harian (sesi kesepuluh)

**Keputusan desain paling penting:** antrian adalah **sumbu terpisah** dari
`bookings.status`. `status` mengurus administrasi (pending → confirmed →
completed/cancelled); antrian mengurus kehadiran fisik di klinik. Menggabung
keduanya akan memaksa mengubah enum status yang dipakai banyak tes, laporan,
dan tombol admin — risiko besar tanpa manfaat.

### Kolom baru (semua nullable, additive)

`bookings`: `queue_number`, `queue_date`, `queue_status`, `checked_in_at`,
`called_at`, `queue_finished_at`.
Unique `(clinic_id, queue_date, queue_number)`; index papan
`(clinic_id, queue_date, queue_status)`.

`queue_status`: `waiting` · `called` · `in_service` · `done` · `no_show`.

### Aturan yang dikunci

| Aturan | Alasan |
|---|---|
| Nomor diberikan **saat datang** (check-in), bukan saat booking | nomor yang dipesan jauh hari tidak berarti apa-apa di ruang tunggu |
| Urutan mengikuti **check-in**, bukan jam booking | pasien yang datang lebih dulu dilayani lebih dulu |
| Nomor **unik per klinik per hari** | klinik B mulai dari 1 lagi, tidak tercampur |
| Check-in **idempoten** | menekan dua kali tidak memberi nomor baru |
| `pending`/`cancelled` tidak bisa check-in | mencegah antrian untuk booking yang belum sah |
| `no_show` **tidak** mengembalikan nomor | urutan tidak bergeser di belakang |
| Antrian selesai **tidak** menandai booking selesai | booking ditutup saat dokter menyimpan rekam medis |

### Admin & pasien

- Menu **Antrian** ([`admin/queue/`](../PetHeal_Backend/resources/views/admin/queue)):
  kartu statistik, daftar sedang ditangani / menunggu / belum check-in /
  selesai, tombol **Check-in**, **Mulai periksa**, **Selesai**, **Tidak hadir**,
  dan **Panggil berikutnya** (mengambil nomor terkecil yang menunggu).
- API `POST /api/bookings/{id}/check-in` dengan throttle `api`: hanya untuk
  booking milik pasien, **hari ini**, dan sudah dikonfirmasi. Respons membawa
  `queue_number`, `queue_label`, dan `queue_position`.
- `GET /api/bookings` juga mengirim `queue_label` + `queue_position` sehingga
  aplikasi tidak menebak.
- Aplikasi: kartu booking menampilkan nomor antrian + posisi tunggu bila sudah
  check-in, dan tombol **Check-in & ambil nomor antrian** hanya pada hari
  jadwal untuk booking terkonfirmasi yang belum punya nomor.

### Verifikasi

```
php artisan test   → 288 passed (1595 assertions)   [14 tes antrian baru]
compileDebugKotlin → BUILD SUCCESSFUL
view:cache         → semua Blade terkompilasi
```

### Jebakan yang benar-benar terjadi di sesi ini

`$locked->queue_date === $queueDate` **selalu false**, karena `queue_date`
adalah cast tanggal (objek Carbon) sementara pembandingnya string. Akibatnya
check-in ganda memberi nomor baru (1 lalu 2). Perbaikannya: bandingkan
`$locked->queue_date?->toDateString()`. Test `check_in_is_idempotent` mengunci
perilaku ini.

## 6j. Tahap 4 pra-UKK: refund manual (sesi kesebelas)

**Masalah nyata:** booking yang dibatalkan tetap menyumbang `paid_amount` ke
laporan, padahal uangnya harus dikembalikan. Laporan jadi lebih besar daripada
uang milik klinik.

**Refund di sini = PENCATATAN, bukan transfer.** Uang dikembalikan admin lewat
bank/tunai/e-wallet, lalu dicatat sebagai bukti. Teks bantuan di form
menyatakan hal ini eksplisit supaya admin tidak mengira sistem memindahkan
uang sendiri.

### Skema

`booking_refunds`: `booking_id`, `clinic_id`, `amount`, `method`
(transfer/cash/ewallet/other), `reference`, `reason`, `recorded_by`.

Satu booking boleh punya **beberapa** refund (pengembalian bertahap) — karena
itu tabel terpisah, bukan satu kolom `refunded_at`.

### Aturan yang dikunci ([`RefundService.php`](../PetHeal_Backend/app/Services/RefundService.php))

| Aturan | Alasan |
|---|---|
| Refund ≤ `paid_amount` − total refund sebelumnya | tidak mungkin mengembalikan uang yang belum masuk |
| Hanya booking `cancelled` | kalau masih berjalan itu pembatalan, bukan refund |
| Alasan wajib | refund tanpa alasan tidak bisa dipertanggungjawabkan |
| Lock baris saat mencatat | dua admin tidak bisa bersama-sama melewati batas |
| `total_collected` dikurangi total refund | laporan menunjukkan uang yang benar-benar diterima |
| Invoice PDF mencantumkan refund | struk dan layar admin tidak boleh bercerita beda |

### Verifikasi

```
php artisan test → 298 passed (1610 assertions)   [10 tes refund]
assembleDebug    → BUILD SUCCESSFUL · app-debug.apk 28,9 MB
view:cache       → semua Blade terkompilasi
```

## 6m. Audit menyeluruh tiap layar Android (sesi keempatbelas)

Audit ini bukan tambahan fitur: ia menutup **jalur kebocoran klinik sesi** yang
masih tersisa setelah sesi 6l. Aturan yang sekarang dipegang di seluruh aplikasi:
**dokumen & label klinik selalu membaca klinik milik transaksinya sendiri; bila
data itu tidak ada, baris disembunyikan (atau label netral "Klinik" di kwitansi) —
tidak pernah meminjam klinik sesi lain.**

### Sisi Android

| Lokasi | Sebelum | Sesudah |
|---|---|---|
| `PaymentNavViewModel` | kop kwitansi = preferensi klinik aktif | diturunkan dari `booking.clinic` / `medicalRecord.clinic` (`receiptClinicName/Address/Color`) |
| `PaymentResultScreen` | memakai parameter klinik sesi | `medicalRecord.clinic` → `booking.clinic` → parameter |
| `BookingsScreen` (kwitansi) | nama dari booking, **alamat dari sesi** | ketiganya dari `booking.clinic` |
| `MedicalRecordsScreen` | `record.clinic ?: sesi` | hanya `record.clinic` |
| `BookingViewModel` / `MedicalRecordsViewModel` | field klinik sesi di state | dihapus (tidak ada lagi sumber salah) |
| Kartu booking, detail booking, invoice center | tanpa identitas klinik | baris klinik dari `booking.clinic` |
| Rekam medis (kartu + detail) | klinik penerbit tidak pernah disebut | baris klinik dari `record.clinic` |
| Beranda (kunjungan terdekat & terakhir) | tanpa klinik | `booking.clinic` / `record.clinic` |
| Beranda (avatar) | logo klinik menimpa foto pasien | foto pasien dulu, logo hanya bila tidak ada foto |
| Layar Midtrans | tidak menyebut penerima uang | "Dibayarkan ke <klinik booking>" |
| Detail dokter | `getDoctor(id)` tanpa scope klinik | rute membawa `clinicId` dari baris dokter |
| Bandingkan harga | badge "Klinik Anda" | "Terakhir dipakai" (pasien tidak terikat satu klinik) |
| Detail klinik | "0 dokter"/"0 layanan" saat angka null | chip disembunyikan |
| Invoice | nominal null tampil Rp 0 | tampil "—" |
| Bantuan alur booking | fallback ke klinik favorit | ditolak dengan pesan, tidak pernah salah kirim |

### Sisi backend

1. **`DoctorController::catalogClinicId`** — `users.clinic_id` pada akun pasien
   diabaikan (sesuai docblock-nya). Tanpa ini pasien lama hanya melihat dokter
   klinik lamanya.
2. **`DoctorController::doctorScope`** (baru) — detail/slot/review memakai satu
   definisi: pasien tanpa filter klinik, staf tetap terikat kliniknya.
   Sebelumnya `clinic_id` difilter untuk semua orang, sehingga pasien lintas
   klinik mendapat 404 saat membuka dokter klinik lain.
3. **`ServiceController::resolveTenant`** — `users.clinic_id` tidak lagi dipakai
   untuk role `user`; katalog layanan lintas klinik kembali utuh (dasar
   perbandingan harga).
4. `GET /doctors/{id}/reviews` mengembalikan `rating: null` bila belum ada
   ulasan (bukan 0).
5. `POST /bookings/{id}/check-in` **ada** (`BookingController::checkIn`) — route
   sebelumnya menunjuk method yang tidak ada sehingga selalu 500.
6. `GET /bookings/{id}` dan respons booking lain menyertakan `clinic`.

Uji baru: `tests/Feature/PatientGlobalScopeTest.php` — 6 tes (pasien lama
melihat dokter klinik lain, detail lintas klinik, katalog layanan global,
rating null, check-in idempoten, check-in milik orang lain ditolak).

### Halaman yang masih perlu diuji di perangkat

| Halaman | Yang harus dipastikan |
|---|---|
| Riwayat → struk pembayaran | nama **dan** alamat klinik sesuai booking, bukan klinik lain |
| Detail rekam medis → struk biaya tambahan | klinik penerima = klinik rekam medis |
| Booking list / detail / invoice | setiap entri menampilkan kliniknya |
| Detail dokter dari Beranda | terbuka normal walau pasien pernah memakai klinik lain |
| Detail klinik | tidak menampilkan "0 layanan" saat angka belum tersedia |

### Aturan untuk layar baru (WAJIB)

1. Jangan pernah mengambil klinik dari `PreferencesManager` untuk **menampilkan
   data transaksi**. Sumbernya: `Booking.clinic`, `Record.clinic`,
   `Doctor.clinic`, atau model discovery.
2. `X-Clinic-Slug` untuk staf / konteks saja, bukan sumber tampilan.

## 6n. Audit pasca-implementasi: 7 bug P0 (sesi kelimabelas)

Audit setelah empat fitur besar terpasang menemukan tujuh bug di jalur yang
dipakai **setiap hari** — bukan kasus tepi, dan semuanya lolos dari test lama
karena test lama menguji lapisan service, bukan alur form/HTTP penuh. Semua
sudah diperbaiki dan dikunci dengan test regresi.

### P0 #1 — Rekam medis baru + resep berakhir 500

Kolom `prescription_items.medical_record_id` bersifat NOT NULL, tetapi
`PrescriptionService::syncForRecord()` dipanggil **sebelum** `$record->save()`.
Selama record belum punya `id`, setiap item ditulis dengan FK kosong → query
gagal → admin/dokter melihat error 500 saat mengisi obat pada rekam medis baru.

Perbaikan: `$record->save()` dipindah ke dalam transaksi **sebelum**
`syncForRecord()`, di
[`Admin/MedicalRecordController`](../PetHeal_Backend/app/Http/Controllers/Admin/MedicalRecordController.php)
dan
[`Doctor/DoctorMedicalRecordController`](../PetHeal_Backend/app/Http/Controllers/Doctor/DoctorMedicalRecordController.php).
Stok tidak cukup tetap membatalkan seluruh transaksi, jadi tidak ada rekam
medis setengah jadi.

Dikunci: `MedicineStockTest::test_admin_create_with_prescription_saves_record_and_deducts_stock`.

### P0 #2 — Koreksi resep berulang membuat stok membengkak

`MedicineStockService::restoreForMedicalRecord()` hanya menjumlahkan baris
`out`, padahal baris `return` (hasil koreksi sebelumnya) juga ada di buku
mutasi. Akibatnya edit pertama benar, edit kedua mengembalikan 2× qty, ketiga
3× — stok naik tanpa ada yang menyadari.

Perbaikan: jumlahkan **net** `out + return`, dan lewati bila `net >= 0` (tidak
ada sisa pemakaian).

Dikunci: `MedicineStockTest::test_repeated_prescription_edits_keep_stock_in_sync`
(qty 4 → 6 → 3 → 8 → 2, saldo harus `10 − qty` setelah **setiap** simpan).

### P0 #3 — Refund penuh membuat pendapatan minus

Ada dua definisi "terkumpul" yang saling menumpuk: pembatalan dikeluarkan dari
`paid_amount` **lalu** refund dikurangkan lagi. Booking Rp 100.000 yang
dibatalkan dan direfund penuh menyumbang **−100.000** ke laporan.

Perbaikan: satu formula untuk semua status —
`SUM(paid_amount) − total refund`. Dipakai
[`PaymentController::paymentStats()`](../PetHeal_Backend/app/Http/Controllers/Admin/PaymentController.php)
dan dashboard (`DashboardController`), sehingga dua halaman tidak bisa
bercerita beda.

Dikunci: `RefundTest::test_fully_refunded_booking_contributes_zero_not_negative`
dan `DataIntegrityGuardsTest::test_cancelled_booking_counts_until_refunded_then_nets_to_zero`
(naik +100.000 saat dibatalkan, kembali tepat ke angka semula setelah refund).

### P0 #4 — `/admin/queue` 500 untuk super admin tanpa klinik aktif

Picker klinik memakai `route('admin.clinic-context.switch', $clinic->slug)`,
padahal rute itu **tidak pernah ada**. Halaman langsung 500, bukan menampilkan
pilihan.

Perbaikan: picker menjadi form POST ke `admin.switch-clinic` dengan
`clinic_id` + `return_to=queue`; `AdminAuthController::switchClinic()` mengenali
`return_to=queue` dan kembali ke papan antrian (bukan dashboard). Rute
`clinic-context.switch` tidak lagi dipakai di picker mana pun.

Dikunci: `ClinicQueueTest::test_super_admin_without_clinic_sees_picker_not_500`.

### P0 #5 — Label & posisi antrian hilang setiap kali daftar di-refresh

`queue_label` ("A-01") dan `queue_position` hanya dihitung di respons
check-in. `GET /api/bookings` mengirim `queue_number` mentah, sehingga strip
antrian kosong lagi setelah aplikasi memuat ulang daftar.

Perbaikan: `Api/BookingController` mendapat `attachQueueMeta()` dan memakainya
di `index`, `upcoming`, `show`, dan `checkIn`. Nilai juga dimasukkan **ke dalam
`data`** (bukan hanya level atas) supaya klien yang memetakan DTO `Booking`
tetap menerimanya.

Dikunci: `PatientGlobalScopeTest` memeriksa baris `GET /api/bookings` punya
`queue_label = "A-01"` dan `queue_position = 0`.

### P0 #6 — Check-in di aplikasi tanpa umpan balik

Pesan sukses/gagal ditulis ke `BookingDetailUiState`, padahal tombol check-in
ada di **kartu daftar**. Layar daftar tidak pernah membaca state detail → pasien
menekan tombol dan tidak melihat apa pun.

Perbaikan di
[`BookingViewModel.kt`](../PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/ui/screens/booking/BookingViewModel.kt)
dan
[`BookingsScreen.kt`](../PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/ui/screens/booking/BookingsScreen.kt):

- `BookingsUiState` mendapat `checkInLoadingId`, `checkInMessage`, `checkInError`.
- Snackbar sukses (`SuccessSoft`) berisi nomor antrian + sisa antrian di depan;
  snackbar gagal (`ErrorSoft`) menampilkan alasan dari server apa adanya.
- Tombol berubah menjadi **"Memproses check-in…"** dan nonaktif selama request;
  `checkInBooking()` menolak tap kedua (guard double-submit).
- Kartu langsung memakai `Booking` hasil check-in tanpa menunggu reload.
- `clearCheckInNotice()` menutup pesan, bukan menunggu timeout.

### P0 #7 — Dokter tidak bisa mengunggah lampiran rekam medis

Form portal dokter tidak punya `enctype="multipart/form-data"` dan tidak
merender field lampiran. Berkas tidak pernah terkirim, sementara controller
sudah siap menyimpannya.

Perbaikan: form dokter memakai `enctype` multipart dan meng-include partial
`admin/medical-records/partials/attachments-fields` (satu sumber untuk admin &
dokter, termasuk pesan batas ukuran dan daftar lampiran lama).

### P1 yang ikut dibereskan

| Hal | Perubahan |
|---|---|
| Hapus obat | Membedakan **dipakai resep** (`medicine_in_use`) vs **punya riwayat stok lain** (`medicine_has_history`); keduanya menonaktifkan, bukan menghapus |
| Tanggal papan antrian | `resolveDate()` hanya menerima `Y-m-d`; query string rusak jatuh ke hari ini, bukan 500 |
| Audit check-in API | `AuditLog::log()` menerima `$userId` eksplisit → aksi pasien tercatat `queue.check_in.patient` meski guard Sanctum tidak mengisi `auth()->id()` |
| Nomor antrian bentrok | `checkIn()` mengulang maksimal 3× saat unique index `(clinic_id, queue_date, queue_number)` menolak INSERT (dua pasien datang bersamaan) |
| "Hari ini di klinik" | Memakai `isTodayBooking()` — booking yang **sudah** check-in atau masih `pending` tetap tampil sebagai kunjungan hari ini, tidak tenggelam ke daftar bawah |
| Strip antrian | Label baris menampilkan status ("Menunggu"/"Dipanggil"), bukan mengulang nomor yang sudah tampil besar di sebelahnya |
| `currentClinic()` | Cache `static` diganti cache **per request** (request attribute). Cache statis bertahan melewati request sehingga id klinik lama bisa menunjuk baris yang sudah tidak ada — ini yang membuat suite penuh gagal hanya saat urutan test tertentu |

### Batasan yang diketahui (jujur)

Lampiran rekam medis disimpan di disk **`public`**
(`storage/app/public/medical-records/...`), sehingga siapa pun yang memiliki
URL dapat membukanya tanpa login. Untuk UKK ini diterima (berkas hanya
ditautkan dari halaman yang butuh login), tetapi produksi wajib memakai disk
privat + signed URL berumur pendek. Belum dikerjakan di sesi ini.

### Verifikasi

```
php artisan test                    → 324 passed (1711 assertions)
php -l (13 file PHP yang diubah)    → bersih
php artisan route:list (switch-clinic, queue) → sesuai
assembleDebug (Android)             → BUILD SUCCESSFUL (6m18s)
```

Yang **BELUM** dijalankan dan tidak boleh diklaim: uji browser (Playwright/
Chrome DevTools), Lighthouse, axe-core, dan uji di perangkat — tool-nya tidak
tersedia di sesi ini.

## 7. Jebakan teknis yang sudah memakan waktu (baca sebelum debug)



Ini bukan teori — semuanya benar-benar terjadi hari ini.

1. **`/*` di dalam KDoc menutup komentar secara bersarang.**
   Menulis `` token `ui/theme/*` `` di komentar menyebabkan
   `error: Unclosed comment`. Hindari `/*` dan `*/` di dalam komentar dokumentasi.

2. **Kapt/Hilt error "could not be resolved" biasanya berarti import hilang, bukan Hilt rusak.**
   Pesan aslinya: `InjectProcessingStep was unable to process 'X(DiscoveryRepository)'`.
   Penyebab nyata: `DiscoveryRepository` tidak di-import di ViewModel.

3. **`items(items, key = { ... })` tidak tersedia** pada versi Compose di proyek ini.
   Pakai `itemsIndexed(items, key = { _, item -> item.key })`.

4. **`collectAsStateWithLifecycle` tidak tersedia** — `lifecycle-runtime-compose`
   tidak ada di dependensi. Pakai `collectAsState()`.

5. **Material3 experimental butuh opt-in eksplisit di titik pemanggilan.**
   `@androidx.compose.material3.ExperimentalMaterial3Api` menghasilkan error; pakai
   `@OptIn(ExperimentalMaterial3Api::class)` seperti pola repo.

6. **`object Dimens` ada di `ui/components/CommonComponents.kt`**, bukan `ui/theme`.
   Salah import → `Unresolved reference: Dimens`.

7. **Token yang tidak ada:** `StatusClosed` tidak ada. Gunakan `StatusCancelled` /
   `Neutral` sesuai makna.

8. **`ErrorState` vs `ErrorStateView`.** `ErrorState` (ui/components/ErrorState.kt)
   punya `title` + `primaryActionText`; `ErrorStateView` (CommonComponents) hanya
   `message` + `onRetry`. Keduanya ada — jangan bingung.

9. **`BookingStartUiState.clinicName`** ditambahkan untuk menampilkan konteks klinik.
   Bila menambah field baru ke state ini, cek `loadBookingStartData()`.

---

## 8. Status verifikasi (jujur)

| Item | Status |
|---|---|
| Backend test | **324 lulus** (1711 assertions) — seluruh suite, dijalankan ulang tanpa proses lain |
| Android `compileDebugKotlin` | Hijau, hanya warning lama yang sudah ada sebelumnya |
| Android `assembleDebug` | **Hijau** — `app-debug.apk` (6m18s) |
| Uji di perangkat | **BELUM** |
| Verifikasi visual (screenshot 1440/375) | **BELUM** — browser tool nonaktif saat pengerjaan |
| Lighthouse / axe-core | **BELUM** — tool tidak tersedia di sesi ini |
| Semgrep / Snyk | **BELUM** — tool tidak tersedia di sesi ini |

Catatan kejujuran: satu kali jalan suite penuh sempat menampilkan 34 gagal
dengan pesan `Table 'pet_heal_test.clinics' doesn't exist`. Penyebabnya dua
runner test berjalan bersamaan di database yang sama, bukan regresi — setelah
dijalankan sendiri hasilnya 324 lulus.

Titik panggil parameter baru sudah dicek: semua punya nilai default, jadi tidak ada
pemanggil lain yang rusak.

---

## 9. Yang sengaja BELUM dikerjakan

Jangan dianggap bug — ini keputusan sadar.

1. **Booking dengan `serviceId` terpilih otomatis.** Saat ini `CreateBooking` tetap
   memilih `services.firstOrNull()`. Meneruskan `serviceId` menyentuh logika booking
   yang sudah lulus test, jadi ditahan sampai ada persetujuan.
2. **Pindah klinik aktif dari layar discovery.** Sengaja tidak dibuat: mengubah
   `X-Clinic-Slug` berisiko mengacaukan sesi. Yang dibuat hanya *peringatan*.
3. **Uji regresi perangkat.** Belum dijalankan sama sekali.
4. **Dua file scratch backend** masih ada dan belum dibersihkan:
   `PetHeal_Backend/route_dump.tmp.json`, `PetHeal_Backend/route_names.tmp.txt`.
   Hapus bila tidak dipakai.

---

## 10. Peta file yang berubah

Batch yang belum di-commit saat dokumen ini diperbarui: **99 file**
(58 diubah + 41 baru), **+7.879 baris** — angka dari
`git status --porcelain --untracked-files=all` dan `git diff --shortstat HEAD`,
bukan perkiraan. Peta di bawah hanya daftar fitur multi-clinic (sesi awal);
tujuh bug P0 di §6n menyentuh file backend/Android yang sudah ada di daftar itu
plus `Admin/{Queue,Medicine}Controller`, `Api/BookingController`,
`Services/{QueueService,MedicineStockService}`, dan tiga test regresi.

```
PetHeal_Backend/
  app/Http/Middleware/ApiAuthenticate.php            ← pasien global, staf tetap dikunci
  app/Services/PatientScope.php                      ← BARU, sumber kebenaran peran
  app/Services/ClinicRatingService.php               ← BARU, agregasi rating
  app/Http/Controllers/Api/PublicClinicController.php ← endpoint publik discovery
  app/Http/Controllers/Api/{Auth,Booking,Doctor,Service}Controller.php
  app/Http/Controllers/Admin/{Clinic,Service,ServiceCategory}Controller.php
  app/Models/{Clinic,DoctorReview,Service,ServiceCategory*}.php
  app/Providers/AppServiceProvider.php               ← observer rating
  database/migrations/2026_11_09_00000{2,3,4}_*.php  ← BARU
  resources/lang/{en,id}/clinics.php
  resources/views/admin/clinics/{create,edit,index}.blade.php
  resources/views/admin/clinics/partials/location-fields.blade.php      ← BARU
  resources/views/admin/service-categories/index.blade.php              ← BARU
  resources/views/admin/services/{create,edit,index}.blade.php
  resources/views/admin/services/partials/category-select.blade.php     ← BARU
  resources/views/admin/settings/partials/klinik.blade.php
  resources/views/layouts/admin.blade.php
  routes/{api,web}.php
  tests/Feature/{AdminConsoleSmoke,Phase1TenantIsolation,Phase2ApiContract,
                 Phase3Stabilization,Phase6SecurityMatrix}Test.php
  tests/Feature/MultiClinicDiscoveryTest.php                            ← BARU

PetHeal_Android/
  app/src/main/AndroidManifest.xml                   ← izin lokasi
  data/model/DiscoveryModels.kt                      ← BARU
  data/remote/ApiService.kt
  data/repository/{AuthRepository,DiscoveryRepository*,ClinicContextResolver*}.kt
  data/local/PreferencesManager.kt
  ui/navigation/{PetHealNavHost,Screen,NavigationViewModel}.kt
  ui/components/ClinicDiscoveryCard.kt               ← BARU
  ui/screens/discovery/                              ← BARU (6 file)
  ui/screens/auth/{Register,CompleteSetup,Login,OtpVerification}*.kt
  ui/screens/booking/{BookingStartScreen,BookingViewModel}.kt
  ui/screens/home/HomeScreen.kt                      ← aksi cepat "Cari Klinik"
  util/{LocationHelper,DistanceFormatter}.kt         ← BARU
```

---

## 11. Aturan kerja untuk agent berikutnya

1. **Jangan longgarkan isolasi tenant.** Pasien boleh global; staf **tidak**.
   Setiap perubahan pada `ApiAuthenticate` / `PatientScope` wajib disertai test.
2. **Jangan ganti `NULL` jadi `0`** untuk harga, rating, atau jarak. Itu berbohong
   ke pengguna.
3. **Perbandingan harga hanya lewat kategori**, bukan kemiripan nama.
4. **Jangan tambah dependensi lokasi Google Play.** `LocationManager` sudah cukup
   dan disengaja.
5. **Ikuti flow yang ada.** Booking aktif hanya untuk satu klinik; jangan diam-diam
   memindahkan klinik saat user menekan booking.
6. **Verifikasi dengan build, bukan asumsi:** `./gradlew assembleDebug` (Android) dan
   test PHPUnit (backend). Kapt menyembunyikan pesan error nyata — baca bagian 7.

---

## 12. Cara menjalankan

```bash
# Backend
cd PetHeal_Backend
php artisan serve                 # sudah berjalan di terminal terpisah
php artisan test                  # 324 test

# Android
cd PetHeal_Android
./gradlew assembleDebug           # -> app/build/outputs/apk/debug/app-debug.apk
```
