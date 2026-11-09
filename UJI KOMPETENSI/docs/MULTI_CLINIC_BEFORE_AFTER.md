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
| Backend test | **247 lulus**, termasuk 18 test `MultiClinicDiscoveryTest` |
| Android `compileDebugKotlin` | Hijau, tanpa warning baru |
| Android `assembleDebug` | **Hijau** — `app-debug.apk`, 28,6 MB |
| Uji di perangkat | **BELUM** |
| Verifikasi visual (screenshot) | **BELUM** — browser tool nonaktif saat pengerjaan |

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

## 10. Peta file yang berubah (70 file)

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
php artisan test                  # 247 test

# Android
cd PetHeal_Android
./gradlew assembleDebug           # -> app/build/outputs/apk/debug/app-debug.apk
```
