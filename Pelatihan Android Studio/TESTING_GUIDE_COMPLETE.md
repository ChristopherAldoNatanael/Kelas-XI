# 🔧 PANDUAN TESTING - JADWAL SISWA FIXED

## ✅ STATUS BUILD

**BUILD SUCCESSFUL!**

- APK Location: `AplikasiMonitoringKelas/app/build/outputs/apk/debug/app-debug.apk`
- Server: Laravel running on `http://192.168.1.10:8000`
- Base URL Updated: `http://192.168.1.10:8000/api/`

---

## 🚀 LANGKAH-LANGKAH TESTING

### 1. VERIFIKASI SERVER JALAN

Server Laravel sudah berjalan di background. Cek dengan:

```powershell
# Test API endpoint
curl http://192.168.1.10:8000/api/ping

# Atau buka di browser
# http://192.168.1.10:8000/api/ping
```

**Expected Response:**

```json
{
  "message": "pong",
  "timestamp": "2025-11-24 ..."
}
```

---

### 2. INSTALL APK DI DEVICE/EMULATOR

#### A. Via USB (Physical Device)

```powershell
# 1. Pastikan USB Debugging aktif di HP
# 2. Cek device terkoneksi
adb devices

# 3. Install APK
adb install "c:\Kelas XI RPL\Pelatihan Android Studio\AplikasiMonitoringKelas\app\build\outputs\apk\debug\app-debug.apk"

# Atau force reinstall (hapus data lama)
adb install -r "c:\Kelas XI RPL\Pelatihan Android Studio\AplikasiMonitoringKelas\app\build\outputs\apk\debug\app-debug.apk"
```

#### B. Via Emulator

```powershell
# 1. Buka Android Studio > Device Manager
# 2. Start emulator
# 3. Drag & drop APK ke emulator window
```

---

### 3. TEST DENGAN 3 AKUN SISWA BERBEDA

#### **Test Case 1: Siswa X RPL 1**

**Login:**

- Email: `siswa1@example.com`
- Password: `password`

**Expected:**

- Melihat **12 jadwal** untuk kelas X RPL 1
- Jadwal meliputi: Matematika Dasar, Bahasa Indonesia, Algoritma, dll.

#### **Test Case 2: Siswa XI RPL 1**

**Login:**

- Email: `siswa3@example.com`
- Password: `password`

**Expected:**

- Melihat **6 jadwal** untuk kelas XI RPL 1
- Jadwal berbeda dari X RPL 1

#### **Test Case 3: Siswa XII RPL 1**

**Login:**

- Email: `siswa5@example.com`
- Password: `password`

**Expected:**

- Melihat **6 jadwal** untuk kelas XII RPL 1
- Jadwal berbeda dari XI dan X RPL

---

## 📱 CARA TESTING DI APLIKASI

### Step 1: Login

1. Buka aplikasi
2. Masukkan email dan password
3. Klik "LOGIN"

### Step 2: Cek Jadwal

1. Setelah login, klik tab **"Jadwal"** di bottom navigation
2. Tunggu loading
3. Verifikasi jadwal yang muncul sesuai dengan kelas siswa

### Step 3: Verifikasi Data

Pastikan:

- ✅ **Nama kelas** sesuai (X RPL 1, XI RPL 1, atau XII RPL 1)
- ✅ **Jumlah jadwal** berbeda untuk setiap kelas
- ✅ **Mata pelajaran** sesuai dengan kelas
- ✅ **Nama guru** muncul dengan benar

---

## 🔍 DEBUGGING (Jika Ada Masalah)

### Problem 1: "Failed to Connect"

**Solusi:**

```powershell
# 1. Cek server masih jalan
netstat -ano | findstr :8000

# 2. Cek IP masih sama
ipconfig | Select-String "IPv4"

# 3. Restart server jika perlu
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan serve --host=0.0.0.0 --port=8000
```

### Problem 2: "Unauthorized" atau "Token Invalid"

**Solusi:**

```powershell
# Clear app data di HP
# Settings > Apps > Monitoring Kelas > Storage > Clear Data

# Atau uninstall & reinstall
adb uninstall com.christopheraldoo.aplikasimonitoringkelas
adb install "c:\Kelas XI RPL\Pelatihan Android Studio\AplikasiMonitoringKelas\app\build\outputs\apk\debug\app-debug.apk"
```

### Problem 3: Jadwal Masih Sama untuk Semua Siswa

**Solusi:**

```powershell
# 1. Check Laravel logs
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
Get-Content storage\logs\laravel.log -Tail 50

# 2. Clear cache
php artisan cache:clear

# 3. Test backend langsung
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php test-siswa-schedules.php
```

---

## 📊 CHECK ANDROID LOGCAT

Untuk melihat log detail dari aplikasi:

```powershell
# Filter log untuk melihat network calls
adb logcat -s NetworkRepository:D SiswaViewModel:D

# Atau lebih detail
adb logcat | Select-String "NetworkRepository|SiswaViewModel|ScheduleController"
```

**Expected Logs:**

```
NetworkRepository: API Response Code: 200
NetworkRepository: Successfully parsed 12 schedules
NetworkRepository: Schedule: X RPL 1 - Matematika Dasar (Senin)
```

---

## 📋 VERIFICATION CHECKLIST

Sebelum declare "SUKSES", pastikan:

- [ ] Server Laravel jalan di `http://192.168.1.10:8000`
- [ ] APK berhasil di-install di device
- [ ] Login berhasil dengan 3 akun berbeda
- [ ] Jadwal X RPL 1 menampilkan 12 schedules
- [ ] Jadwal XI RPL 1 menampilkan 6 schedules
- [ ] Jadwal XII RPL 1 menampilkan 6 schedules
- [ ] Setiap kelas menampilkan jadwal yang BERBEDA
- [ ] Nama guru muncul dengan benar
- [ ] Tidak ada error di logcat

---

## 🎯 EXPECTED RESULTS

### X RPL 1 (12 Jadwal)

```
SENIN
1. Matematika Dasar - Budi Santoso (07:00-08:30)
2. Bahasa Indonesia - Siti Nurhaliza (08:45-10:15)
3. Algoritma dan Pemrograman - Dr. Ahmad Santoso (08:45-10:15)

SELASA
4. Algoritma dan Pemrograman Dasar - Rizki Ramadhan (07:00-09:30)
5. PJOK - Siti Nurhaliza (08:45-10:15)

RABU
6. Fisika - Maya Sari (07:00-08:30)

KAMIS
7. Fisika - Maya Sari (07:00-09:30)
8. Bahasa Inggris - Adi Wijaya (08:45-10:15)

JUMAT
9. Struktur Data - Rizki Ramadhan (07:00-09:30)
10. Matematika - Adi Wijaya (08:45-10:15)

SABTU
11. Pemrograman Berorientasi Objek - Eko Prasetyo (08:00-10:30)
12. Bahasa Inggris - Eko Prasetyo (08:45-10:15)
```

### XI RPL 1 (6 Jadwal)

```
(Jadwal berbeda dari X RPL 1)
```

### XII RPL 1 (6 Jadwal)

```
(Jadwal berbeda dari X dan XI RPL 1)
```

---

## 🐛 TROUBLESHOOTING LENGKAP

### Error: "Connection timeout"

**Penyebab:** Server tidak respond atau firewall blocking

**Solusi:**

```powershell
# 1. Cek Windows Firewall
# Allow port 8000 inbound

# 2. Test dari device
# Buka browser di HP, akses: http://192.168.1.10:8000/api/ping

# 3. Pastikan HP dan PC dalam network yang sama
```

### Error: "Failed to load schedules"

**Penyebab:** API endpoint bermasalah

**Solusi:**

```powershell
# Test API langsung via curl
curl -X GET "http://192.168.1.10:8000/api/siswa/weekly-schedule" `
  -H "Authorization: Bearer <TOKEN>" `
  -H "Accept: application/json"
```

### Error: "Empty schedules"

**Penyebab:** Database kosong atau filter bermasalah

**Solusi:**

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php test-siswa-schedules.php
```

---

## 📞 QUICK COMMANDS

```powershell
# Restart server
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan serve --host=0.0.0.0 --port=8000

# Reinstall APK
adb install -r "c:\Kelas XI RPL\Pelatihan Android Studio\AplikasiMonitoringKelas\app\build\outputs\apk\debug\app-debug.apk"

# View logs
adb logcat -s NetworkRepository:D

# Check Laravel logs
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
Get-Content storage\logs\laravel.log -Tail 30

# Test backend
php test-siswa-schedules.php

# Clear cache
php artisan cache:clear
```

---

## ✅ SUCCESS CRITERIA

Aplikasi **BERHASIL** jika:

1. ✅ Setiap siswa login dan melihat jadwal kelasnya sendiri
2. ✅ Jadwal X RPL 1 ≠ XI RPL 1 ≠ XII RPL 1
3. ✅ Tidak ada error di aplikasi
4. ✅ Tidak ada error di Laravel logs
5. ✅ Loading cepat (< 3 detik)

---

## 🎉 SETELAH TESTING SUKSES

Jika semua test case passed:

1. **Dokumentasikan hasil testing**
2. **Screenshot untuk bukti**
3. **Export APK for production** (optional)

```powershell
# Build release APK (optional)
cd "c:\Kelas XI RPL\Pelatihan Android Studio\AplikasiMonitoringKelas"
.\gradlew assembleRelease
```

---

## 📝 NOTES

- **IP Address:** `192.168.1.10` (pastikan tidak berubah)
- **Port:** `8000`
- **Database:** MySQL `db_sekolah`
- **Cache TTL:** 120 seconds (2 menit)

Jika IP berubah, update di:

- `AplikasiMonitoringKelas/app/src/main/java/com/christopheraldoo/aplikasimonitoringkelas/network/RetrofitClient.kt`
- Line 233: `private const val BASE_URL = "http://NEW_IP:8000/api/"`

---

**Happy Testing! 🚀**
