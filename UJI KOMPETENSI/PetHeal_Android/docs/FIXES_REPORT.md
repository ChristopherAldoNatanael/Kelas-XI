# Laporan Perbaikan — PetHeal Android 1.1.0

Dokumen ini menjelaskan **semua** perubahan pada putaran perbaikan besar ini:
apa yang diubah, kenapa, di file mana, dan cara memeriksanya. Tulisannya sengaja
rinci supaya bisa dibaca ulang oleh manusia maupun agen AI tanpa menebak.

- Versi sebelum: `versionCode 1` / `1.0`
- Versi sekarang: `versionCode 2` / `1.1.0`

---

## 0. Ringkasan

| # | Area | Sebelum | Sesudah |
|---|---|---|---|
| 1 | APK rilis | tidak bertanda tangan, tidak bisa dipasang | ditandatangani (v2/v3), 6,9 MB |
| 2 | Cleartext HTTP | diblokir total, backend lokal mustahil dipakai | debug boleh `10.0.2.2`/localhost, rilis tetap menolak |
| 3 | Interceptor jaringan | `runBlocking` baca DataStore di setiap permintaan | baca memori, diisi sekali saat start |
| 4 | Retry jaringan | tidak ada | GET diulang sekali pada 408/429/5xx |
| 5 | State Flow di layar | 44× `collectAsState()` (tetap jalan di background) | 0× — semua lifecycle-aware |
| 6 | Tinggi tombol | 30 tombol 40–47dp (terpotong saat font besar) | minimum 48dp (`heightIn`) |
| 7 | Tombol ikon bulat | 42dp | area sentuh 48dp |
| 8 | Chip filter | 34–38dp | area sentuh 48dp, bentuk tetap ramping |
| 9 | Warna | 68 alias lokal per layar | satu palet `Brand.*` |
| 10 | String UI | literal di Compose | pola `strings.xml` + contoh migrasi lengkap |
| 11 | Kode mati | endpoint PDF server tidak terpakai | dihapus |
| 12 | CI | tidak ada | GitHub Actions: build + unit test |
| 13 | Tombol "Aktifkan lokasi" (Beranda) | diam/tidak bereaksi (dummy) | meminta izin, menjelaskan GPS mati, membuka pengaturan |

Verifikasi yang benar-benar dijalankan (bukan asumsi):

- `./gradlew compileDebugKotlin --offline` → **BUILD SUCCESSFUL**
- `./gradlew testDebugUnitTest --offline` → **BUILD SUCCESSFUL**, 80 test, 0 gagal
- `./gradlew assembleDebug --offline` → `app-debug.apk` 30.782.260 byte
- `./gradlew assembleRelease --offline` → `app-release.apk` 6.943.810 byte
- `apksigner verify --print-certs` → `CN=PetHeal, OU=Mobile, O=PetHeal, L=Jakarta, C=ID`
- `aapt2 dump badging` → `versionCode='2' versionName='1.1.0'`, target SDK 34
- Isi APK: `10.0.2.2` hanya ada di APK **debug**, tidak ada di rilis

Yang **belum** bisa diverifikasi di mesin ini: tampilan di perangkat, rasa animasi,
dan notifikasi — karena tidak ada `adb`/emulator. Jalankan
`.\gradlew.bat installDebug` lalu periksa daftar di bagian 9.

---

## 1. Rilis: tanda tangan, versi, konfigurasi jaringan

### 1.1 APK rilis sekarang bertanda tangan

**Masalah.** `assembleRelease` menghasilkan `app-release-unsigned.apk`. Berkas itu
tidak bisa dipasang di HP maupun diunggah ke Play Console.

**Perbaikan** (`app/build.gradle.kts`):

- Blok `signingConfigs.create("release")` membaca `keystore.properties` di root
  proyek. Path kunci diselesaikan dengan `rootProject.file(...)` supaya tidak
  bergantung pada direktori modul.
- `buildTypes.release.signingConfig` memakai konfigurasi itu **bila** berkasnya
  ada. Kalau tidak ada (mesin lain/CI), build tetap jalan dan tetap menghasilkan
  `-unsigned` — tidak ada kegagalan build yang membingungkan.
- `isShrinkResources = true` ditambahkan. Ukuran rilis turun dari 9.888.106 byte
  menjadi 6.943.810 byte (± 30%) tanpa membuang fitur.

**Kunci & kredensial.** Dibuat `keystore/petheal-release.jks` (RSA 2048, alias
`petheal`, 10.000 hari) dan `keystore.properties`. Keduanya **tidak masuk Git**
(`.gitignore`: `**/*.jks`, `**/keystore.properties`, `**/keystore/`). Cara
mencadangkan dan membuat ulang dijelaskan di `keystore/README.md`.

### 1.2 Versi dinaikkan

`versionCode 2`, `versionName "1.1.0"`. Tanpa kenaikan ini, memasang APK baru di
atas versi lama bisa ditolak atau dianggap sama.

### 1.3 Cleartext HTTP hanya untuk debug

**Masalah.** `app/src/main/res/xml/network_security_config.xml` menolak **semua**
cleartext, sedangkan `BACKEND_BASE_URL` default adalah `http://10.0.2.2:8000/api/`.
Artinya build debug tidak bisa menghubungi backend lokal sama sekali.

**Perbaikan.** Ditambahkan `app/src/debug/res/xml/network_security_config.xml`
yang hanya mengizinkan cleartext untuk `10.0.2.2`, `localhost`, `127.0.0.1`.
Berkas di source set `debug` menimpa versi `main` **hanya** pada build debug,
sehingga rilis tetap menolak seluruh cleartext (sudah diperiksa di isi APK).

---

## 2. Jaringan: tanpa `runBlocking`, dengan retry

### 2.1 `runBlocking` di interceptor dihapus

**Masalah** (`data/remote/NetworkInterceptor.kt`, versi lama baris 60, 63, 88).
Setiap kali cache di memori kosong, interceptor memanggil
`runBlocking { preferencesManager.authToken.first() }` dan
`runBlocking { preferencesManager.clearSession() }`. Efeknya thread jaringan
menunggu pembacaan DataStore; pada permintaan pertama setelah proses dibunuh,
aplikasi bisa terasa menggantung. `clearSession()` juga memblokir saat 401.

**Perbaikan.**

- `data/remote/SessionCacheBootstrap.kt` (baru): satu `combine(authToken,
  clinicSlug)` yang berjalan di `@ApplicationScope` dan memanggil
  `NetworkInterceptor.updateToken/updateClinicSlug`. Dipanggil dari
  `PetHealApplication.onCreate`, jadi cache sudah siap sebelum layar pertama
  memanggil API.
- `di/AppScope.kt` (baru) + provider di `di/AppModule.kt`:
  `CoroutineScope(SupervisorJob() + Dispatchers.IO)` sebagai scope proses.
- `NetworkInterceptor` kini hanya membaca `@Volatile` variabel. Kalau permintaan
  datang sebelum cache siap, ia menunggu **maksimal 1,5 detik** lalu lanjut
  tanpa header (permintaan publik tetap berhasil, tidak pernah menggantung).
- Penanganan 401: token di memori dikosongkan seketika, alur "sesi berakhir"
  dikirim sekali (`AtomicBoolean`), lalu `clearSession()` dijalankan di
  background. Token baru mereset penanda itu.

### 2.2 `RetryInterceptor` (baru)

`data/remote/RetryInterceptor.kt` mengulang **GET** sekali bila server menjawab
408, 429, 500, 502, 503, atau 504 (umum pada ngrok + Laravel saat restart), atau
bila koneksi terputus di tengah respons. Menghormati `Retry-After` (maksimal
2 detik). POST/PUT/DELETE **tidak pernah** diulang agar tidak terjadi pesanan
atau pembayaran ganda. Didaftarkan di `di/AppModule.kt` sebelum blok timeout.

---

## 3. State Flow: lifecycle-aware

**Masalah.** 44 pemakaian `collectAsState()` di 28 file. `collectAsState()`
mengoleksi Flow selama composable terpasang, termasuk saat aplikasi di
background → recomposition dan penulisan state untuk layar yang tidak terlihat.

**Perbaikan.** `ui/util/LifecycleAwareState.kt` (baru) menyediakan
`collectAsStateLifecycleAware()` yang memakai `repeatOnLifecycle(STARTED)`.
Seluruh 44 pemakaian diganti, dan 4 pemakaian berbentuk
`collectAsState(initial = null)` ikut diganti ke varian yang menerima `initial`.
Nilai terakhir dipertahankan saat layar kembali, jadi tidak ada kedipan.

Kenapa helper sendiri, bukan `androidx.lifecycle.compose.collectAsStateWithLifecycle`?
Versi pustaka yang tersedia (2.8.x) membutuhkan Compose 1.6+, sedangkan proyek ini
memakai Compose BOM 2023.10.01 (1.5.x). Menambahkannya berisiko konflik versi;
helper 20 baris ini berperilaku sama dan tidak menambah dependensi.

---

## 4. Aksesibilitas: area sentuh 48dp & font besar

**Masalah.** 46 tombol memakai tinggi tetap 40–47dp dan 42 elemen dapat disentuh
berukuran 30–39dp. Tidak ada penanganan `fontScale`, sehingga pada pengaturan
font HP 150–200% teks tombol terpotong.

**Perbaikan.**

1. Token baru di `ui/components/CommonComponents.kt`: `Dimens.ButtonHeight`,
   `Dimens.IconTouchTarget`, dan `Modifier.minTapTarget()`.
2. 30 pemakaian `.height(40..47.dp)` diganti `.heightIn(min = Dimens.MinTouchTarget)`
   di 13 file. Konsekuensinya tombol boleh tumbuh mengikuti teks, dan tidak
   pernah di bawah 48dp.
3. Tombol ikon bulat di hero (Booking, Rekam Medis, Hewan Saya, Pet Shop) kini
   berukuran 48dp. Untuk `ShopRoundButton`, lingkaran **visual** tetap 42dp dan
   `Box` 48dp yang memegang `clickable`.
4. Chip filter (`ShopFilterChip`, `InvoiceFilterChip`, `SelectChip`, `DateChip`)
   dibungkus `Box` 48dp; bentuk pil tetap ramping 34–38dp.
5. `Spacer(Modifier.height(40.dp))` pada Login/Register sengaja **tidak** diubah
   (bukan elemen interaktif).

---

## 5. Warna: satu palet, tanpa gradien

**Masalah.** Setiap layar punya alias warna sendiri: `MrPrimary`, `BkSurface`,
`PetPrimary`, `SrBorder`, `BsSurfaceVariant`, `PaySurface`, `SurfaceDark`, …
total **68 alias** di 11 file. Mengganti satu warna berarti menyunting banyak
berkas dan mudah ada layar yang tertinggal. Ditambah lagi hero memakai gradien
vertikal + lingkaran dekoratif.

**Perbaikan.**

- `ui/theme/Brand.kt` (baru) — objek palet semantik: `Brand.primary`,
  `primaryDeep`, `primarySoft`, `card`, `cardAlt`, `border`, `textPrimary`,
  `textSecondary`, `danger`, `success`, `sky`, `amber`, dst. Nilainya tetap
  menunjuk token `ui/theme/Color.kt`, jadi `Color.kt` tetap satu-satunya sumber
  angka warna.
- 68 alias lokal diganti `Brand.*` dan baris aliasnya dihapus.
- Gradien dekoratif sudah dihilangkan pada putaran sebelumnya (Home, Rekam
  Medis, Booking, Hewan Saya, Pet Shop, Notifikasi, Profil, Bantuan, Dokter).
  Yang tersisa hanya dua scrim foto (agar teks di atas foto terbaca) dan asset
  brand di `ui/screens/auth/`.
- Aturan ini ditulis di `docs/UI_CONVENTIONS.md` §1 dan `AGENTS.md`.

---

## 6. String UI: pola `strings.xml`

**Masalah.** 268 literal teks di Compose vs 61 `stringResource()`. Tidak ada cara
mengubah salinan massal, dan teks yang sama ditulis ulang di beberapa layar.

**Perbaikan (bertahap, tidak sekaligus).**

1. `res/values/strings.xml` mendapat blok `common_*` (aksi yang dipakai lebih dari
   satu layar: `common_retry`, `common_save_png`, `common_share_pdf`,
   `common_check_status`, …) dan blok `petshop_receipt_*`.
2. Contoh migrasi **lengkap**: `ui/screens/petshop/PetShopReceiptScreen.kt` —
   27 literal pindah ke `strings.xml` (judul, label meta, catatan, badge
   LUNAS/MENUNGGU, tombol aksi).
3. `ui/screens/petshop/PetShopPaymentWebView.kt` ikut memakai `common_check_status`
   dan `common_checking_status`.
4. Pola dan aturan penamaan ditulis di `docs/UI_CONVENTIONS.md` §8.

Layar lama belum dipindahkan seluruhnya — itu pekerjaan mekanis yang aman
dilakukan bertahap. Aturannya sekarang: **saat menyentuh sebuah layar, pindahkan
string-nya**, jangan menambah literal baru.

---

## 7. Kode mati dihapus

`ApiService.downloadPetShopReceiptPdf()` dan `PetShopRepository.downloadReceiptPdf()`
tidak dipakai lagi sejak PDF dibuat di perangkat
(`PetShopReceiptExporter.renderPdf`). Keduanya dihapus, dengan catatan di
`data/remote/ApiService.kt` agar tidak ada yang menambahkannya kembali.

---

## 8. CI: build + unit test otomatis

`.github/workflows/android-ci.yml` menjalankan `assembleDebug` +
`testDebugUnitTest` pada setiap push/PR, lalu mengunggah laporan test dan APK
debug sebagai artefak. `google-services.json` dan `local.properties` tidak
di-commit, jadi CI membuat versi tiruan (hanya untuk kompilasi) — build rilis
tetap memakai berkas asli di mesin developer.

---

## 9. Yang perlu Anda periksa di perangkat

1. `cd PetHeal_Android && .\gradlew.bat installDebug`
2. Login → beranda: pastikan header tetap satu warna solid dan tombol ikon di
   kanan atas terasa lebih mudah ditekan.
3. Booking → ketuk kartu dua kali cepat: harus tetap satu layar (gate 400ms).
4. Pengaturan font HP → **besar** → buka Booking/Pet Shop: label tombol tidak
   terpotong.
5. Pet Shop → pesanan **lunas** → Bukti Pembayaran → "Unduh Bukti PDF": berkas
   muncul di `Download/PetHeal/`, dan "Simpan PNG" di `Galeri/PetHeal`.
6. Matikan data seluler, buka ulang aplikasi: tidak ada layar putih/menggantung
   (sesi dibaca dari memori, bukan disk).

---

## 10. Belum dikerjakan (dan alasannya)

| Item | Kenapa ditunda |
|---|---|
| Memecah `BookingsScreen.kt` (3.204 baris), `PetsScreen.kt` (2.923) | Pemindahan composable privat antar berkas berisiko memutus helper bersama. Perlu sesi khusus + build verifikasi tiap langkah. Rencana target: `booking/list/`, `booking/detail/`, `pet/form/`. |
| Pull-to-refresh | Belum ada di `material3 1.1.2` (BOM 2023.10.01). Butuh naik ke material3 1.2+ yang berarti naik Compose — jangan dicampur dengan perbaikan rilis. |
| Crash reporting | Butuh mendaftarkan aplikasi di Firebase Crashlytics + plugin Gradle baru. Aman dilakukan setelah rilis pertama terpasang. |
| `kapt` → `ksp` | Mempercepat build, tetapi mengubah pipeline Hilt. Risiko lebih besar daripada manfaat pada putaran ini. |
| Migrasi 268 string sisanya | Mekanis; dilakukan per layar mengikuti pola di bagian 6. |
| Dark mode | Keputusan produk: aplikasi sengaja light-only (`darkTheme = false`). |

---

## 11. Peta berkas baru (untuk agen AI berikutnya)

| Berkas | Isi |
|---|---|
| `ui/theme/Brand.kt` | Palet semantik satu-satunya untuk layar |
| `ui/util/LifecycleAwareState.kt` | `collectAsStateLifecycleAware()` |
| `data/remote/SessionCacheBootstrap.kt` | Mengisi cache token/tenant saat start |
| `data/remote/RetryInterceptor.kt` | Retry GET (429/5xx) |
| `di/AppScope.kt` | Qualifier `@ApplicationScope` |
| `app/src/debug/res/xml/network_security_config.xml` | Pengecualian cleartext khusus debug |
| `keystore/README.md` | Cara tanda tangan rilis & cadangkan kunci |
| `.github/workflows/android-ci.yml` | CI build + unit test |
| `docs/FIXES_REPORT.md` | Dokumen ini |

Konvensi yang mengikat semuanya: `docs/UI_CONVENTIONS.md` (§1 warna & tanpa
gradien, §8 string, §9 area sentuh, §10 motion, §11 state flow, §12 build) dan
`AGENTS.md`.

---

## 12. Pola dari aplikasi besar yang dipakai

Bukan peniruan visual, melainkan pola kerja yang sudah terbukti:

- **Pemisahan konfigurasi per build type** (debug vs release) seperti yang
  dilakukan aplikasi Android produksi pada umumnya: pengecualian jaringan hanya
  ada di debug, rilis selalu TLS.
- **Rilis wajib bertanda tangan + versi naik tiap distribusi**, sama seperti
  alur rilis aplikasi konsumen (Play Console menolak APK tanpa tanda tangan).
- **Cache sesi di memori + retry GET** — pola yang dipakai aplikasi super-app
  (ride-hailing, e-commerce) supaya header autentikasi tidak pernah memblokir
  thread jaringan dan gangguan server sesaat tidak tampil sebagai error.
- **Kartu status + badge + total dominan pada struk** mengikuti pola invoice
  Stripe/Shopify: satu blok identitas, satu tabel item, satu panel total.
- **Area sentuh 48dp, ukuran teks mengikuti preferensi pengguna** sesuai panduan
  Material Design dan WCAG — praktik yang sama di aplikasi kesehatan besar
  (Halodoc/Alodokter) karena penggunanya sering berusia lanjut.
- **Satu sistem motion** untuk seluruh aplikasi, bukan animasi per layar.

---

## 13. Tombol "Aktifkan lokasi" di Beranda (perbaikan lanjutan)

### Masalah

Kartu "Aktifkan lokasi" pada seksi **Dokter terdekat** punya `onClick`, tetapi
isinya hanya `viewModel.loadNearbyDoctors(forceGps = true)`
(`HomeScreen.kt`). Di dalam ViewModel, GPS hanya dibaca bila
`LocationHelper.hasPermission(context)` sudah bernilai true. Saat pengguna
**belum pernah memberi izin**, syarat itu selalu false, sehingga fungsi berhenti
tanpa dialog, tanpa pesan, tanpa perubahan apa pun — tombol terasa mati.

### Perbaikan

1. **Permintaan izin nyata lewat satu pintu.**
   `ui/screens/home/HomeScreen.kt` kini memakai `rememberLocationPermission()`
   (pintu izin tunggal aplikasi, `ui/location/LocationPermission.kt`), bukan
   memanggil ViewModel langsung. Tiga cabang ditangani: izin sudah ada, dialog
   masih boleh muncul, dan izin diblokir permanen (buka pengaturan aplikasi).

2. **Hasil dialog bisa dilaporkan.**
   `LocationPermissionHandle.request()` menerima `onResult` opsional. Tanpa ini,
   "menolak" dan "diblokir sistem" sama-sama berstatus `DENIED` sehingga layar
   tidak bisa membedakan pesan yang tepat. Pemanggil lama tidak terpengaruh
   karena parameternya default `null`.

3. **Umpan balik selalu ada.** Beranda mendapat `SnackbarHost` di atas navigasi
   mengapung (`navigationBarsPadding()`), dengan pesan berbeda untuk: ditolak,
   diblokir, GPS mati, dan titik lokasi tidak terbaca.

4. **GPS mati ditangani, bukan diabaikan.** Bila izin ada tetapi layanan lokasi
   mati, pengguna diberi pesan dan langsung diarahkan ke pengaturan lokasi
   (`Settings.ACTION_LOCATION_SOURCE_SETTINGS`). Saat kembali dengan GPS
   menyala, lokasi dimuat otomatis (observer `ON_RESUME`).

5. **Kembali dari pengaturan izin → muat otomatis.** Setelah pengguna
   mengaktifkan izin lewat Settings, status dibaca ulang oleh helper dan
   `LaunchedEffect` memicu pemuatan tanpa perlu menekan tombol lagi.

6. **Tidak ada lagi permintaan yang hilang.** `HomeViewModel.loadNearbyDoctors`
   menyimpan `Job`. Permintaan biasa tetap tidak menumpuk, tetapi permintaan
   dari tombol (`forceGps`) selalu dijalankan (job lama dibatalkan) supaya
   ketukan berurutan tidak terasa diam.

7. **Aksesibilitas.** Kartu ajakan memakai `clickable(role = Role.Button)`
   sehingga pembaca layar membacanya sebagai tombol, bukan teks biasa.

### Perilaku baru, skenario demi skenario

| Kondisi pengguna | Yang terjadi saat kartu disentuh |
|---|---|
| Izin belum pernah diminta | dialog izin sistem muncul |
| Memilih "Izinkan" | lokasi diambil (maks 12 detik) → dokter terdekat tampil dengan jarak |
| Memilih "Tolak" | Snackbar: "Izin lokasi diperlukan untuk menghitung jarak klinik." |
| Menolak 2× / "Jangan tanya lagi" | Snackbar + pengaturan aplikasi terbuka; setelah diizinkan, lokasi dimuat otomatis |
| Izin ada, GPS mati | Snackbar + pengaturan lokasi terbuka; setelah GPS menyala, lokasi dimuat otomatis |
| Izin ada, GPS menyala, titik tidak didapat | Snackbar "Lokasi belum terbaca. Coba lagi di area terbuka." |

Lokasi tetap **opsional**: menolak izin tidak menghalangi fitur lain, dan tidak
ada jarak yang dikarang.

### Berkas yang disentuh

| Berkas | Perubahan |
|---|---|
| `ui/screens/home/HomeScreen.kt` | handler izin + Snackbar + observer GPS |
| `ui/screens/home/HomeViewModel.kt` | `nearbyMessage`, `clearNearbyMessage()`, `Job` untuk pemuatan |
| `ui/location/LocationPermission.kt` | `request(onResult)` |

## 14. Riwayat panjang tidak lagi terpotong di 20 item (F-09 audit backend)

Backend memaginasi daftar booking dan rekam medis 20 per halaman. Aplikasi
sebelumnya hanya meminta halaman pertama, jadi pasien dengan lebih dari 20
riwayat tidak pernah melihat sisanya — tanpa pesan apa pun.

Sekarang:

- `data/remote/ApiService.kt` — `getBookings(page)` dan `getMedicalRecords(page)`
  menerima nomor halaman.
- `data/repository/BookingRepository.kt` — mengambil halaman 1..`last_page`,
  menggabung hasil, dan membuang duplikat berdasarkan `id`. Bila halaman lanjutan
  gagal, data yang sudah terkumpul tetap dipakai (dicatat di log), bukan dibuang.
- `data/repository/MedicalRecordRepository.kt` — pola sama, cache detail per id
  tetap terisi.
- Batas `MAX_PAGES = 10` (200 riwayat terbaru) mencegah unduhan tanpa henti pada
  akun lama; batas ini dicatat di log bila tercapai agar tidak tersembunyi.

Tanda tangan fungsi repository tidak berubah, sehingga `BookingViewModel`,
`DoctorsViewModel`, `HomeViewModel`, dan `MedicalRecordsViewModel` tidak perlu
disentuh.

**Yang perlu diperiksa:** akun dengan > 20 booking — tab Booking dan Rekam Medis
harus menampilkan seluruh riwayat, dan log perangkat tidak berisi
`failed on page`.

---

## 15. Form Edit Hewan tampil kosong (prefill hilang)

**Gejala.** Dari detail hewan, tombol pensil ("Ubah hewan") membuka layar *Edit
Hewan* dengan semua isian kosong — terlihat seperti tambah hewan baru. Menyimpan
form kosong akan menimpa data lama.

**Sebab.** `EditPetScreen` hanya membaca satu sumber: `detailState.pet`
(`ui/screens/pet/PetsScreen.kt`). Tiga hal membuat sumber itu kosong saat form
dirender:

1. `LaunchedEffect` memanggil `loadPetDetail(petId)` **tanpa** `forceRefresh`,
   sedangkan `loadPetDetail()` punya guard: bila state sudah memuat hewan dengan
   id yang sama, pemuatan dilewati. Layar edit lalu merender form dari nilai
   default (`""`) dan tidak pernah mendapat data.
2. `PetFormScreen` langsung dirender tanpa menunggu data; tidak ada status
   memuat, jadi tidak ada bedanya antara "belum selesai memuat" dan "kosong".
3. Cabang gagal di `loadPetDetail()` **mengganti seluruh state** dengan
   `PetDetailUiState(error = …)`. Satu kegagalan jaringan (atau 5xx sesaat)
   mengosongkan hewan yang sebenarnya sudah ada di memori, dan layar edit tidak
   pernah menampilkan `detailState.error` sehingga kegagalan itu tidak terlihat.

**Perbaikan.**

- `ui/screens/pet/EditPetPrefill.kt` (baru) — fungsi murni
  `resolveEditablePet(petId, detail, listed)` dan `shouldWaitForPet(...)`:
  sumber data form = detail (bila id cocok) → daftar hewan di memori
  (`uiState.pets`, sudah dimuat `PetsViewModel.init { loadPets() }`). Detail milik
  hewan lain **tidak** dipakai, supaya form tidak pernah terisi data hewan yang
  salah.
- `ui/screens/pet/PetsScreen.kt` — `EditPetScreen`:
  - `loadPetDetail(petId, forceRefresh = true)` (layar edit selalu memuat ulang),
  - form hanya dirender bila data hewan sudah ada, dibungkus `key(pet.id)` agar
    seluruh state form dibuat sekali dari data itu,
  - selagi menunggu: indikator "Memuat data hewan…"; bila gagal dan tidak ada
    cache: pesan + tombol **Coba Lagi** (memanggil `loadPetDetail(..., true)`),
  - `PetDetailScreen` juga memakai `forceRefresh = true`, supaya kembali dari
    layar edit menampilkan data terbaru (dulu guard yang sama membuat detail
    tetap menampilkan data lama).
- `ui/screens/pet/PetsViewModel.kt` —
  - `loadPetDetail()` memakai cache (`detailState.pet` atau `uiState.pets`) untuk
    mengisi `pet` lebih dulu; `isLoading` hanya `true` bila cache kosong, jadi
    refresh berjalan di belakang tanpa kedip,
  - cabang `Result.Error` kini `copy(isLoading = false, error = …)` dan **tidak**
    menghapus data yang sudah ada,
  - `editPet()` sukses: `lastLoadedDetailPetId = null`, `detailState.pet` dan
    entri `uiState.pets` diperbarui dari respons server, lalu `loadPets(forceRefresh = true)`,
  - `deletePet()` sukses: `loadPets(forceRefresh = true)` agar hewan yang dihapus
    tidak tertinggal di tab My Pets.

**Berkas tersentuh.**

- `app/src/main/java/com/christopheraldoo/petheal/ui/screens/pet/PetsScreen.kt`
- `app/src/main/java/com/christopheraldoo/petheal/ui/screens/pet/PetsViewModel.kt`
- `app/src/main/java/com/christopheraldoo/petheal/ui/screens/pet/EditPetPrefill.kt` (baru)
- `app/src/test/java/com/christopheraldoo/petheal/EditPetPrefillTest.kt` (baru)

**Verifikasi yang dijalankan.**

- `.\gradlew.bat assembleDebug --offline` → BUILD SUCCESSFUL; APK debug berisi
  kode baru (string "Data hewan gagal dimuat" ditemukan di `classes13.dex`).
- `.\gradlew.bat testDebugUnitTest --offline` → **87 test, 0 gagal** (7 di
  antaranya `EditPetPrefillTest`: detail-menang, cache-daftar, detail hewan lain
  ditolak, hewan tak dikenal, status memuat vs siap).
- **Belum dijalankan:** uji di perangkat/emulator (tidak ada `adb` di mesin ini).
  Yang perlu dicek: buka detail hewan → pensil → semua isian (nama, jenis,
  ras, gender, tanggal lahir, berat, foto lama) terisi; ubah nama → Simpan →
  kembali ke detail menampilkan nama baru; mode pesawat saat membuka edit →
  form tetap terisi dari cache, bukan kosong.

## 16. Filter Status "Buka sekarang" gagal + scroll terasa patah-patah

**Gejala 1.** Di layar *Cari Klinik*, mengubah filter **Status** dari "Semua" ke
"Buka sekarang" membuat layar berakhir di keadaan error (oleh pengguna disebut
"crash") — daftar klinik hilang dan hanya tersisa pesan gagal.

**Sebab 1 (akar masalah, ada di backend).** `ApiService.discoverClinics()`
mengirim `open_now` sebagai `Boolean`, sehingga Retrofit menulis
`...&open_now=true`. Backend memvalidasi dengan
`'open_now' => 'nullable|boolean'`, dan rule `boolean` Laravel hanya menerima
`1`/`0`/`true`/`false` **asli** — teks `"true"` ditolak. Hasilnya setiap
perubahan filter Status dijawab **HTTP 422**, dan layar menampilkan galat.
Terbukti dengan `curl`: `?open_now=true` → 422, `?open_now=1` → 200.

**Perbaikan 1.**

- Backend `Api/PublicClinicController` (index + compareServices): parameter
  `open_now`/`sync_rating` dinormalisasi lebih dulu
  (`normalizeBooleanQuery()`), sehingga `true`/`false`/`1`/`0` diterima dan nilai
  ngawur tetap 422.
- Aplikasi kini mengirim `1`/`0` (`util/QueryFlags.kt` → `Boolean?.toQueryFlag()`),
  jadi filter tetap benar walau backend belum diperbarui.
- Layar *Cari Klinik* dan *Bandingkan Harga* **tidak lagi mengosongkan daftar**
  ketika satu permintaan filter gagal: hasil lama tetap tampil dan pesan muncul
  sebagai banner `ui/components/InlineErrorBanner.kt` dengan tombol "Coba lagi".
- Daftar klinik di-`distinctBy { key }` sebelum dipakai sebagai key LazyColumn —
  key ganda adalah salah satu penyebab crash Compose yang paling sering.

**Gejala 2.** Scroll di hampir semua layar terasa tersendat/drop frame.

**Sebab & perbaikan 2.**

- Katalog Pet Shop memakai `itemsIndexed(uiState.products.chunked(2))` di dalam
  builder `LazyColumn`. `chunked` membuat list + sub-list baru **setiap
  recomposition**, sehingga semua baris dianggap item baru dan tidak bisa
  dipakai ulang saat scroll. Sekarang dipecah sekali per perubahan daftar
  (`remember(uiState.products)`) dan diberi `key` + `contentType`.
- Lazy list utama (`Cari Klinik`, `Bandingkan Harga`) sekarang memakai
  `contentType`, sehingga Compose bisa memakai ulang slot item antar tipe
  (header/banner/kartu) — standar performa list.
- Ditambahkan `androidx.profileinstaller` (1.3.1) sehingga profil baseline
  bawaan Compose/lifecycle dipasang saat pertama dijalankan; build rilis kini
  membawa `assets/dexopt/baseline.prof`.
- Catatan penting untuk jank yang tersisa: APK **debug** (yang biasa dipasang
  dari Android Studio) memang jauh lebih lambat — tanpa R8, tanpa profil, dan
  penuh verifikasi ART. Untuk merasakan performa sebenarnya pakai
  `app/build/outputs/apk/release/app-release.apk` (R8 aktif, 6,9 MB vs 30,8 MB
  debug).

**Berkas tersentuh.**

- `data/remote/ApiService.kt` (`open_now` dikirim sebagai `Int`)
- `data/repository/DiscoveryRepository.kt` (konversi flag, `distinctBy`)
- `util/QueryFlags.kt` (baru), `ui/components/InlineErrorBanner.kt` (baru)
- `ui/screens/discovery/ClinicDiscoveryScreen.kt`, `PriceCompareScreen.kt`
- `ui/screens/petshop/PetShopScreen.kt` (grid produk)
- `app/build.gradle.kts` (profileinstaller)
- `app/src/test/java/com/christopheraldoo/petheal/QueryFlagTest.kt` (baru)
- Backend: `app/Http/Controllers/Api/PublicClinicController.php`,
  `tests/Feature/MultiClinicDiscoveryTest.php` (test `open_now` baru)

**Verifikasi yang dijalankan.**

- Backend: `?open_now=true|false|1|0` → 200, `?open_now=kemarin` → 422; test
  `test_open_now_filter_accepts_true_false_text_from_http_clients` lulus.
- Android: `assembleDebug` + `assembleRelease` BUILD SUCCESSFUL; APK rilis berisi
  `assets/dexopt/baseline.prof`; `testDebugUnitTest` → **90 test, 0 gagal**
  (termasuk 3 test `QueryFlagTest`).
- **Belum dijalankan:** uji di perangkat (tidak ada `adb` di mesin ini) — yang
  perlu dicek: buka *Cari Klinik* → filter → Status → "Buka sekarang" (daftar
  menyusut, tidak ada layar error), lalu scroll cepat di Beranda, Cari Klinik,
  Booking, dan Pet Shop memakai APK rilis.

---

## 17. Nomor WhatsApp: diminta saat daftar, ditawarkan setelah masuk dengan Google

**Masalah.** Akun Google masuk tanpa nomor telepon (Google memang tidak
mengirimnya), sehingga di dashboard klinik pasien tersebut tidak punya kontak
sama sekali — persis saat nomor itu dibutuhkan: membatalkan jadwal atau
mengembalikan dana. Form daftar email juga tidak pernah menanyakan nomor.

**Perubahan.**

| Berkas | Isi |
|---|---|
| `util/PhoneNumber.kt` (baru) | `canonicalPhoneInput()` menormalkan `0812…`, `+62812…`, `62812…`, `812…`, `0062…`, spasi/titik/strip → `08…`; menolak nomor terlalu pendek/panjang, telepon rumah (`021…`), `080x…`, dan input berhuruf. `formatPhoneForDisplay()` untuk tampilan `0812-3456-7890`. |
| `ui/screens/auth/RegisterScreen.kt` | Field **Nomor WhatsApp** setelah Email, keterangan dua baris yang menyebut WhatsApp secara eksplisit, tanda centang bila valid, pesan kesalahan bila tidak, dan tombol daftar baru aktif setelah nomor valid. Nomor dikirim sebagai bentuk `08…`. |
| `ui/screens/auth/PhoneSetupScreen.kt` (baru) | Layar "Nomor WhatsApp Anda" pasca-login Google: nama (bisa dikoreksi) + nomor, alasan singkat kenapa diminta, email yang sedang masuk, tombol **Simpan nomor** dan **Nanti saja**. |
| `ui/screens/auth/PhoneSetupViewModel.kt` (baru) | Ambil profil dari server (nama/email/nomor), validasi lokal, simpan lewat `PUT /api/auth/profile`, perbarui cache DataStore. |
| `LoginViewModel`, `RegisterViewModel` | State baru `needsPhone` (true bila `user.phone` kosong pada respons login/daftar Google). |
| `LoginScreen`, `RegisterScreen` | `needsPhone` → arahkan ke layar nomor, bukan langsung Beranda. |
| `ui/navigation/Screen.kt`, `PetHealNavHost.kt` | Route `phone-setup`; selesai atau "Nanti saja" → Beranda dengan back stack dibersihkan. |
| `ui/screens/profile/ProfileScreen.kt` | Baris Telepon memakai format `0812-…`; bila kosong muncul aksi **Lengkapi nomor WhatsApp** → Edit Profil. Field di Edit Profil berganti label menjadi "Nomor WhatsApp" + keterangan singkat. |
| `ProfileViewModel` | Nomor dinormalkan sebelum dikirim; nomor tidak valid ditolak dengan pesan, bukan disimpan apa adanya. |

**Nada teks.** Tidak memakai istilah teknis atau kalimat pemasaran. Contoh isi
layar: *"Klinik memakai nomor ini untuk mengabari perubahan jadwal, hasil
pemeriksaan, dan pengembalian dana. Kami tidak memakainya untuk promosi."* dan
*"Akun Google tidak membawa nomor telepon. Tanpa nomor, klinik hanya bisa
menghubungi Anda lewat email yang sering terlewat."*

**Verifikasi.**

- `testDebugUnitTest` → **99 test, 0 gagal** (9 test baru `PhoneNumberTest`).
- `assembleDebug` + `assembleRelease` BUILD SUCCESSFUL (APK rilis 6,96 MB).
- Backend `tests/Feature/RegisterPhoneCaptureTest.php` (3 test): nomor dari form
  tersimpan pada akun yang dibuat setelah OTP, pendaftaran tanpa nomor tetap
  jalan, dan nomor bisa ditambahkan kemudian lewat `PUT /api/auth/profile`.
- **Belum dijalankan:** uji di perangkat. Yang perlu dicek: daftar manual dengan
  nomor `0812…` (tombol aktif setelah nomor valid), masuk dengan akun Google yang
  belum punya nomor (layar nomor muncul), "Nanti saja" masuk Beranda, lalu
  Profil → Lengkapi nomor WhatsApp.
