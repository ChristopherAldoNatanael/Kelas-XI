# ANDROID_ARCH.md — Arsitektur PetHeal Android + Rencana Dinamis Klinik

## 1. Package & Dependensi
- App: `com.christopheraldoo.petheal`, `MainActivity.kt`, `PetHealApplication.kt` (Hilt `@HiltAndroidApp`, `ImageLoaderFactory`), `AndroidManifest.xml` (launcher `logo_android`, channel `petheal_notifications`, icon `ic_notification`).
- Gradle: lihat `app/build.gradle.kts` (Compose BOM 2023.10.01, Hilt 2.48, Retrofit 2.9, OkHttp 4.12, Coil 2.5, DataStore 1.0, Firebase BOM 33.8, Midtrans UIKit SANDBOX 2.0, webkit 1.8). Config via root `local.properties` → `BuildConfig` (`BACKEND_BASE_URL`, `MIDTRANS_*`, `GOOGLE_CLIENT_ID`, `ENABLE_HTTP_LOGGING`).

## 2. Data Layer
- `data/model/Models.kt` (532 baris, semua DTO; `User{id,firebase_uid,name,email,role,phone,photo}` — BELUM ada `clinic_*`) + `MidtransModels.kt`.
- `data/remote/ApiService.kt`: endpoint `auth/*` (login body `{email,password,fcm_token,device_type=android}`, register-direct `{name,email,password,phone?,...}`, firebase-login/register `{id_token,...}`), `health`, `payment-methods`, `services`, `midtrans/webhook`, grup authed (profile, dashboard→`DashboardData`, pets CRUD + with-photo/photo/weight/vaccinations, doctors + slots?date= + reviews, bookings CRUD + upcoming/cancel{reason}/reschedule{date,time}, medical-records + pay/payment-status, payment preflight/snap-token/transaction-status/sync-status/remaining, notifications + device-token). BELUM ada `public/clinics`, BELUM ada header tenant.
- `data/remote/NetworkInterceptor.kt:20-30`: baca `PreferencesManager.authToken` + cache, inject `Authorization: Bearer`. Rencana: tambah `X-Clinic-Slug` dari DataStore di sini (satu titik, semua request ikut).
- `data/local/PreferencesManager.kt` (DataStore `petheal_prefs`): `authToken, userId, email, name, photo, authProvider, isLoggedIn`. Rencana tambah: `clinic_slug, clinic_name, clinic_color, clinic_logo, clinic_address` + `clearClinic()` + `saveClinic(dto)`.
- `data/repository/`: `AuthRepository` (FCM token nullable → call → saveTokenAndCache + saveUserInfo), `DoctorRepository` (cache RAM 5 mnt — HARUS key by slug setelah multi-klinik), lainnya passthrough. `BookingRefreshManager` untuk refresh list.

## 3. DI & Gambar
- `di/AppModule.kt:30-35,171-184`: `BASE_URL=BuildConfig.BACKEND_BASE_URL`, `STORAGE_URL=normalize(BASE_URL)+storage/` ( dipakai untuk `photo` dokter/pet/user). Retrofit + Coil share OkHttpClient + cache (http 10MB, image 50MB, coil 100MB, mem 25%).

## 4. UI & Navigasi (21 route di `Screen.kt` + `PetHealNavHost.kt`)
- splash (SplashViewModel: isLoggedIn+onboarding) → onboarding → login/register (Login/RegisterViewModel→AuthRepository) → home (HomeViewModel: `GET dashboard` + `GET auth/profile`) → pets CRUD → doctors (list/detail/slots/reviews) → bookings (create `{pet_id,doctor_id,service_id,date,time,notes,payment_method_id,payment_type,total,dp}`) → medical-records → payment (Snap WebView) → notifications (+FCM) → profile/edit → privacy/help/about (statis, mailto `PetHeal Support`).
- Rencana rute baru: `clinic_picker` (pertama jika `clinic_slug==null`; search + list `GET public/clinics`; simpan → lanjut onboarding). Settings tambah `Ganti Klinik` (clear cache dokter/booking + logout opsional + nav ke picker).

## 5. Theme & Branding (sekarang 100% hardcoded)
- `ui/theme/Color.kt`: Primary `0xFF18C964`, Dark `0xFF087A3A`, Light `0xFFE8F9EF`, Secondary `0xFF0EA5A5`, Tertiary `0xFFF59E0B`, Background `0xFFF6F8F6`. `Theme.kt`: `PetHealTheme(darkTheme=false, dynamicColor=false)`.
- Duplikat: `SplashScreen.kt:41 PrimaryGreen 0xFF2BEE6C` (beda!) + teks `PetHeal`. Literal tersebar: `Akun PetHeal`, `Tentang PetHeal`, `©2026 PetHeal`, `PetHeal User`, FCM fallback `PetHeal`, channel `PetHeal Notifications`.
- Rencana refactor (Fase 4): `data class ClinicColors(primary, primaryDark, primaryLight)` + `fun parseHex` + `CompositionLocal LocalClinicColors` + `PetHealTheme(clinicColors)`; ganti literal → `clinic_name`; logo header/splash/about via Coil (`clinic_logo` URL); launcher icon tetap.

## 6. Aturan Main dengan Backend
- Base URL HARUS sinkron dengan backend `.env APP_URL` + `/api/` (lihat `NGROK_README.md`, `setup_ngrok.bat`).
- Kirim `X-Clinic-Slug` + (saat auth) `clinic_slug` nullable di body. Tangani `403/422` beda klinik dengan `ErrorState` jelas + tombol ganti klinik. Jangan hardcode `10.0.2.2`/slug di kode — via DataStore/BuildConfig.
- Test: `assembleDebug` → fresh install → picker → pilih klinik → dokter/booking terfilter → ganti klinik → cache tidak bocor.
