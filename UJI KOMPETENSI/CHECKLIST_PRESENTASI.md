# ✅ CHECKLIST PRESENTASI PETHEAL

> Baca ini SEBELUM presentasi dimulai. Lakukan urutan di bawah.

---

## 🔴 WAJIB DILAKUKAN SEBELUM PRESENTASI (5 menit sebelum)

### 1. Jalankan Laravel Server

```powershell
cd "c:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Backend"
php artisan serve --host=127.0.0.1 --port=8000
```

> Biarkan terminal ini **TETAP TERBUKA** selama presentasi.

### 2. Pastikan ngrok Aktif

```powershell
# Cek apakah ngrok masih jalan — buka browser:
# https://envious-reselect-darn.ngrok-free.dev/api/doctors
# Harus dapat response JSON (bukan halaman error ngrok)
```

> Kalau ngrok MATI → jalankan: `ngrok http 8000`  
> Lalu update `BACKEND_BASE_URL` di `PetHeal_Android/local.properties` → rebuild APK

### 3. Jalankan Health Check (opsional tapi disarankan)

```powershell
cd "c:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Backend"
php health_check.php
# Harus: PASSED: 32   FAILED: 0
```

### 4. Buka Admin Dashboard di Browser

```
http://127.0.0.1:8000/admin
```

> Login dengan akun admin, pastikan semua halaman bisa dibuka.

### 5. Install / Jalankan APK di HP

> Pastikan HP sudah punya APK terbaru.  
> Login ke aplikasi agar FCM token tersimpan (untuk fitur reminder).

---

## 🟡 KALAU ADA MASALAH — SOLUSI CEPAT

### ❌ Android loading terus / timeout

**Penyebab:** ngrok mati atau URL berubah.

```kotlin
// AppModule.kt line 24 — ganti URL ini:
private const val BASE_URL = "https://URL_NGROK_BARU/api/"
```

Lalu Build → Run di Android Studio.

### ❌ Foto tidak tampil di web admin

```powershell
cd "c:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Backend"
php artisan storage:link
```

### ❌ Halaman admin error 500

```powershell
cd "c:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Backend"
php artisan config:clear
php artisan view:clear
php artisan cache:clear
php artisan route:cache
```

### ❌ Reminder FCM gagal

> Pastikan user sudah login di HP (FCM token tersimpan).  
> Cek di admin: Booking detail → Send Reminder.

### ❌ Database error

```powershell
cd "c:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Backend"
php artisan migrate:status   # cek apakah semua migrasi sudah run
```

---

## 📋 STATUS SEMUA FITUR (Per 5 Maret 2026)

| Fitur                            | Status | Catatan                 |
| -------------------------------- | ------ | ----------------------- |
| Admin Login                      | ✅     |                         |
| Admin Dashboard                  | ✅     |                         |
| Daftar Dokter + Foto             | ✅     | `photo_url` accessor    |
| Detail Dokter + Foto             | ✅     |                         |
| Daftar Booking + Foto            | ✅     | Material Symbols icons  |
| Detail Booking                   | ✅     | Carbon::parse fix       |
| Confirm/Complete/Cancel Booking  | ✅     | Modal JS fixed          |
| Send Reminder FCM                | ✅     | FCM v1 API + JWT OAuth2 |
| Daftar Medical Record + Foto     | ✅     |                         |
| Detail Medical Record            | ✅     |                         |
| Daftar User                      | ✅     | Material Symbols icons  |
| Detail User + Foto Pet           | ✅     |                         |
| Android — Login (Email/Firebase) | ✅     |                         |
| Android — Pets List + Foto       | ✅     | Coil + disk cache       |
| Android — Pet Detail             | ✅     |                         |
| Android — Add/Edit Pet + Foto    | ✅     | Camera + Gallery        |
| Android — Bookings               | ✅     |                         |
| Android — Create Booking         | ✅     |                         |
| Android — Medical Records        | ✅     |                         |
| Android — Doctors List           | ✅     |                         |
| Android — FCM Push Notification  | ✅     | device_tokens table     |

---

## ⚠️ SATU-SATUNYA RISIKO: NGROK URL

Ngrok free tier URL bisa **berubah setiap kali restart**.  
URL saat ini: `https://envious-reselect-darn.ngrok-free.dev/`

**Kalau URL berubah**, harus update di:

1. `PetHeal_Android/local.properties` → `BACKEND_BASE_URL`
2. `PetHeal_Android/app/src/main/java/.../di/AppModule.kt` → rebuild APK after the config update
2. Rebuild & install APK ke HP

---

## 🎉 HASIL HEALTH CHECK TERAKHIR (5 Maret 2026)

```
PASSED: 32   FAILED: 0
🎉 ALL CHECKS PASSED — ready for presentation!
```

- Database: ✅ Connected (2 bookings, 4 doctors, 3 users, 1 medical record)
- Booking Relations: ✅ Pet + Doctor + User loaded
- Carbon Parse: ✅ booking_date bisa di-format
- Photo URLs: ✅ Doctor & Pet photos exist on disk
- FCM Service Account: ✅ private_key + client_email + project_id valid
- FCM Service: ✅ Bisa di-instantiate
- FCM Tokens: ✅ 1 token registered
- Storage Symlink: ✅ doctors/ + pets/ accessible
- Admin Routes: ✅ Semua 12 route kritis ada
