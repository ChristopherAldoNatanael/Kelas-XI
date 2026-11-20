# AGENTS.md — PetHeal Android (Kotlin + Compose + Hilt)

> Dibaca otomatis tiap sesi. Patuhi tanpa kecuali.

## 1. Bahasa & Gaya
- Indonesia untuk penjelasan. Istilah teknis English. Sertakan `path:line` tiap menyebut kode.

## 2. Stack & Config (JANGAN diubah seenaknya)
- `com.christopheraldoo.petheal`, compileSdk/targetSdk 34, minSdk 24, v1.1.0 (versionCode 2), Java/Kotlin 17, Compose BOM 2023.10.01 + material3, navigation-compose 2.7.5, Hilt 2.48, Retrofit 2.9 + Gson + OkHttp 4.12, coroutines 1.7.3, Coil 2.5, DataStore 1.0, Firebase BOM 33.8 (auth/messaging), Midtrans UIKit 2.0 SANDBOX.
- `BACKEND_BASE_URL` dari root `local.properties` (ngrok `https://...ngrok-free.dev/api/` WAJIB https + `/api/` + slash; emulator `http://10.0.2.2:8000/api/`). `AppModule.kt`: `STORAGE_URL = normalize(BASE_URL)+storage/`. HARUS sama dengan backend `.env APP_URL`.
- Cleartext HTTP hanya untuk build **debug** (`app/src/debug/res/xml/network_security_config.xml` → `10.0.2.2`, `localhost`, `127.0.0.1`). Rilis menolak semua cleartext.
- APK rilis ditandatangani via `keystore.properties` + `keystore/petheal-release.jks` (keduanya tidak di-commit). Detail: `keystore/README.md`. Naikkan `versionCode`/`versionName` tiap rilis.
- Build: `.\gradlew.bat assembleDebug`. `ENABLE_HTTP_LOGGING` true di debug, false di release.

## 3. Arsitektur (tanpa layer domain)
- `data/model/Models.kt` (DTO) + `MidtransModels.kt`; `data/remote/ApiService.kt + NetworkInterceptor.kt` (Bearer dari DataStore + cachedToken, 401 invalidate); `data/local/PreferencesManager.kt` (`petheal_prefs`); `data/repository/*` (Auth, Pet, Doctor cache RAM 5 mnt, Booking + BookingRefreshManager, Service, MedicalRecord, Payment, Notification, DeviceToken); `di/AppModule.kt` (Retrofit/OkHttp/Coil share client + cache); `ui/navigation/Screen.kt` (21 route) + `PetHealNavHost` + `NavGate` + `PetHealTransitions` + ViewModels per screen; `ui/theme/*` (darkTheme=false, dynamicColor=false, palet `Brand.kt`); `service/PetHealFirebaseService`; `util/*`.
- Sesi jaringan: `data/remote/SessionCacheBootstrap.kt` mengisi token/slug ke `NetworkInterceptor` sekali di awal (tanpa `runBlocking`); `data/remote/RetryInterceptor.kt` mengulang GET sekali pada 429/5xx. `di/AppModule.kt` menyediakan `@ApplicationScope`.
- Detail lengkap: `docs/ANDROID_ARCH.md` — BACA sebelum refactor besar.

## 4. Keputusan Multi-Klinik (FINAL)
- **1 APK dinamis** (DILARANG flavors/beda APK). Tenant via `X-Clinic-Slug` + `GET public/clinics`. Lihat backend `docs/TENANCY_RULES.md` + `API_CONTRACT.md`.
- User terikat 1 klinik. Ganti klinik = clear cache + logout + picker ulang. Cache dokter key by slug.
- Branding dinamis in-app (nama/logo via Coil/warna/alamat dari API). Launcher icon & applicationId TETAP.

## 5. Gaya Kode
- Kotlin + Compose idiomatis, ViewModel → Repository → ApiService. Jangan panggil Retrofit langsung dari Composable selain via VM (kecuali pola existing memaksa).
- Warna: token semantik di `ui/theme/Color.kt` (peta lengkap di `docs/UI_CONVENTIONS.md`) — DILARANG menambah `Color(0xFF...)` baru di layar. Dinamis per klinik (`ClinicTheme.parsePrimaryColor`, dipakai Home + ClinicPickerSheet) hanya untuk branding; `LocalClinicColors` (Fase 4) belum dikerjakan.
- String user-facing dari `clinic_name`/DataStore atau `strings.xml`, jangan literal "PetHeal" baru. Pola nama: `<layar>_<bagian>_<arti>`, aksi lintas layar di `common_*`.
- Warna layar memakai `Brand.*` (`ui/theme/Brand.kt`). Alias per layar (`MrPrimary`, `BkSurface`, …) sudah dihapus — jangan dibuat lagi.
- **Tanpa gradien dekoratif**; header memakai satu warna solid. Gradien hanya untuk scrim foto & asset brand `ui/screens/auth/`.
- Area sentuh minimum 48dp (`Dimens.MinTouchTarget`, `Modifier.heightIn(min = …)`), jangan `.height(44.dp)` tetap.
- State Flow di Composable: `collectAsStateLifecycleAware()` (`ui/util/LifecycleAwareState.kt`), bukan `collectAsState()`.
- Riwayat & alasan perubahan besar: `docs/FIXES_REPORT.md`. Konvensi visual lengkap: `docs/UI_CONVENTIONS.md`.

## 6. Kapan Bertanya
- Tanya jika: mau ganti applicationId/flavors, mau hapus field DTO lama, mau tambah SDK berbayar, alur picker vs login ambigu.

## 7. Verifikasi
- Setelah edit: `assembleDebug` + cek base URL + login + daftar dokter + booking via ngrok. Lihat backend `docs/ACCEPTANCE.md` + `docs/TESTING.md`.

## 8. Roadmap UI/UX (WAJIB)
- Sebelum mengerjakan UI/UX, baca `../PetHeal_Backend/docs/UIUX_ROADMAP.md`. Urutan fase dan non-goal di sana adalah keputusan final.
- Notifikasi: channel per kategori + deep link `petheal://open/...` sudah ada; jangan mengganti scheme tanpa memperbarui manifest dan handler.
- Grafik berat badan memakai Compose Canvas di `ui/components/WeightTrendChart.kt`; jangan tambah library chart baru tanpa alasan kuat.
