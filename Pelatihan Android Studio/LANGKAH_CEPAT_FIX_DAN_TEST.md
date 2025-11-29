# ⚡ LANGKAH CEPAT: FIX & TEST

## 🎯 YANG SUDAH DIPERBAIKI

✅ Jadwal berbeda per kelas  
✅ Network connection  
✅ Server tidak crash

---

## 🚀 LANGKAH TESTING (3 MENIT)

### STEP 1: START SERVER (30 detik)

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan serve --host=0.0.0.0 --port=8000
```

**Atau klik:** `START-SERVER.bat`

**Cek:** Terminal tampil `Server running on [http://0.0.0.0:8000]`

---

### STEP 2: INSTALL APK (1 menit)

Lokasi:

```
AplikasiMonitoringKelas\app\build\outputs\apk\debug\app-debug.apk
```

**Copy ke HP & install**

---

### STEP 3: TEST LOGIN (30 detik)

#### Test 1: X RPL 1

- Email: `siswa1@example.com`
- Password: `password123`
- **Expected:** 12 jadwal muncul

#### Test 2: XI RPL 1

- Logout → Login lagi
- Email: `siswa3@example.com`
- Password: `password123`
- **Expected:** 6 jadwal (BERBEDA dari test 1!)

#### Test 3: XII RPL 1

- Logout → Login lagi
- Email: `siswa5@example.com`
- Password: `password123`
- **Expected:** 6 jadwal (BERBEDA lagi!)

---

### STEP 4: VERIFY (1 menit)

#### ✅ Server Terminal

Harus tampil:

```
[timestamp] local.INFO: ==== myWeeklySchedule CALLED ====
[timestamp] local.INFO: MyWeeklySchedule: User class found
[timestamp] local.INFO: MyWeeklySchedule: Query results
```

**Server TIDAK BOLEH crash!**

#### ✅ Android App

- Jadwal muncul di tab "Jadwal"
- Kelas X RPL 1 ≠ Kelas XI RPL 1 ≠ Kelas XII RPL 1
- Tidak ada error "failed to connect"

---

## ❌ JIKA ADA MASALAH

### Problem: "Failed to connect"

**Solusi:**

```powershell
# 1. Check IP komputer
ipconfig

# 2. Update NetworkConfig.kt dengan IP yang benar
# File: AplikasiMonitoringKelas/.../network/NetworkConfig.kt
# Ganti: BASE_URL = "http://192.168.1.XX:8000/api/"

# 3. Rebuild APK
cd "c:\Kelas XI RPL\Pelatihan Android Studio\AplikasiMonitoringKelas"
.\gradlew clean assembleDebug
```

### Problem: Server crash

**Solusi:**

```powershell
# Kill & restart
Get-Process php* | Stop-Process -Force
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan serve --host=0.0.0.0 --port=8000
```

### Problem: Jadwal masih sama

**Solusi:**

```powershell
# Clear app data di HP
Settings → Apps → Monitoring Kelas → Storage → Clear Data

# Atau uninstall & reinstall APK
```

---

## 📊 EXPECTED RESULTS

| User               | Kelas     | Jumlah Jadwal |
| ------------------ | --------- | ------------- |
| siswa1@example.com | X RPL 1   | 12            |
| siswa2@example.com | X RPL 2   | 5             |
| siswa3@example.com | XI RPL 1  | 6             |
| siswa4@example.com | XI RPL 2  | 7             |
| siswa5@example.com | XII RPL 1 | 6             |
| siswa6@example.com | XII RPL 2 | 6             |

---

## 🔧 TOOLS UNTUK DEBUG

### Check Database

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php test-siswa-schedules.php
```

### Check Server Memory

```powershell
Get-Process php | Select Name, @{N="Memory(MB)";E={[math]::Round($_.WS/1MB,2)}}
```

### Check Laravel Logs

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
Get-Content storage\logs\laravel.log -Tail 20
```

### Check Android Logs

```bash
adb logcat -s NetworkRepository:D SiswaViewModel:D
```

---

## 📄 DOKUMENTASI LENGKAP

Baca file-file ini untuk detail:

1. **`RINGKASAN_LENGKAP_SEMUA_FIX.md`** ← START HERE!
2. **`PERBAIKAN_JADWAL_SISWA_FIXED.md`** - Fix jadwal
3. **`FIX_SERVER_CRASH_AFTER_LOGIN.md`** - Fix crash
4. **`PENJELASAN_LOCALHOST_VS_NETWORK_IP.md`** - Network setup
5. **`QUICK_FIX_SERVER_CRASH.md`** - Quick reference

---

## ✅ SUCCESS CHECKLIST

Centang ini semua:

- [ ] Server running di `http://0.0.0.0:8000`
- [ ] APK installed di HP
- [ ] Login X RPL 1 → 12 jadwal
- [ ] Login XI RPL 1 → 6 jadwal (BERBEDA!)
- [ ] Login XII RPL 1 → 6 jadwal (BERBEDA LAGI!)
- [ ] Server tidak crash > 5 menit
- [ ] Bisa logout & login berkali-kali

---

## 🎉 SELESAI!

**Kalau semua checklist ✅, berarti SUKSES!**

Jadwal sekarang **berbeda untuk setiap kelas** dan server **tidak crash** lagi! 🚀
