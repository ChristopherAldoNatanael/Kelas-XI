# AGENTS.md — PetHeal Android (Kotlin + Compose + Hilt)

> Dibaca otomatis tiap sesi. Patuhi tanpa kecuali.

## 1. Bahasa & Gaya
- Indonesia untuk penjelasan. Istilah teknis English. Sertakan `path:line` tiap menyebut kode.

## 2. Stack & Config (JANGAN diubah seenaknya)
- `com.christopheraldoo.petheal`, compileSdk/targetSdk 34, minSdk 24, v1.0, Java/Kotlin 17, Compose BOM 2023.10.01 + material3, navigation-compose 2.7.5, Hilt 2.48, Retrofit 2.9 + Gson + OkHttp 4.12, coroutines 1.7.3, Coil 2.5, DataStore 1.0, Firebase BOM 33.8 (auth/messaging), Midtrans UIKit 2.0 SANDBOX.
- `BACKEND_BASE_URL` dari root `local.properties` (ngrok `https://...ngrok-free.dev/api/` WAJIB https + `/api/` + slash; emulator `http://10.0.2.2:8000/api/`). `AppModule.kt`: `STORAGE_URL = normalize(BASE_URL)+storage/`. HARUS sama dengan backend `.env APP_URL`.
- Build: `.\gradlew.bat assembleDebug`. `ENABLE_HTTP_LOGGING` true di debug, false di release.

## 3. Arsitektur (tanpa layer domain)
- `data/model/Models.kt` (DTO) + `MidtransModels.kt`; `data/remote/ApiService.kt + NetworkInterceptor.kt` (Bearer dari DataStore + cachedToken, 401 invalidate); `data/local/PreferencesManager.kt` (`petheal_prefs`); `data/repository/*` (Auth, Pet, Doctor cache RAM 5 mnt, Booking + BookingRefreshManager, Service, MedicalRecord, Payment, Notification, DeviceToken); `di/AppModule.kt` (Retrofit/OkHttp/Coil share client + cache); `ui/navigation/Screen.kt` (21 route) + `PetHealNavHost` + ViewModels per screen; `ui/theme/*` (darkTheme=false, dynamicColor=false); `service/PetHealFirebaseService`; `util/*`.
- Detail lengkap: `docs/ANDROID_ARCH.md` — BACA sebelum refactor besar.

## 4. Keputusan Multi-Klinik (FINAL)
- **1 APK dinamis** (DILARANG flavors/beda APK). Tenant via `X-Clinic-Slug` + `GET public/clinics`. Lihat backend `docs/TENANCY_RULES.md` + `API_CONTRACT.md`.
- User terikat 1 klinik. Ganti klinik = clear cache + logout + picker ulang. Cache dokter key by slug.
- Branding dinamis in-app (nama/logo via Coil/warna/alamat dari API). Launcher icon & applicationId TETAP.

## 5. Gaya Kode
- Kotlin + Compose idiomatis, ViewModel → Repository → ApiService. Jangan panggil Retrofit langsung dari Composable selain via VM (kecuali pola existing memaksa).
- Warna via `ClinicColors`/`LocalClinicColors` (setelah Fase 4), jangan hardcode hex baru. Hapus duplikat `0xFF2BEE6C` di Splash.
- String user-facing dari `clinic_name`/DataStore atau `strings.xml`, jangan literal "PetHeal" baru.

## 6. Kapan Bertanya
- Tanya jika: mau ganti applicationId/flavors, mau hapus field DTO lama, mau tambah SDK berbayar, alur picker vs login ambigu.

## 7. Verifikasi
- Setelah edit: `assembleDebug` + cek base URL + login + daftar dokter + booking via ngrok. Lihat backend `docs/ACCEPTANCE.md` + `docs/TESTING.md`.
