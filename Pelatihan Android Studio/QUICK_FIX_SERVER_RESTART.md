# 🚀 QUICK FIX - Restart Server dengan IP yang Benar

## MASALAH

Android app tidak bisa connect karena server hanya listen ke `127.0.0.1` (localhost)

## SOLUSI CEPAT

### Step 1: Stop Server Lama

Jika ada terminal yang running Laravel server, tekan **CTRL+C**

### Step 2: Start Server Baru

Double-click file ini:

```
START-SERVER.bat
```

Atau manual di PowerShell:

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan serve --host=0.0.0.0 --port=8000
```

### Step 3: Verifikasi

Server harus tampilkan:

```
Laravel development server started: http://0.0.0.0:8000
```

### Step 4: Test di Browser

Buka browser, test URL ini:

```
http://192.168.1.10:8000/api/test
```

Harus return JSON response!

### Step 5: Test di Android App

1. Buka app
2. Login dengan:
   - **siswa1@example.com** / password
   - Atau **siswa3@example.com** / password
3. Jadwal seharusnya muncul sesuai kelas!

## PENJELASAN SINGKAT

**Kenapa harus pakai `--host=0.0.0.0`?**

- ❌ `127.0.0.1:8000` → Hanya bisa diakses dari komputer sendiri
- ✅ `0.0.0.0:8000` → Bisa diakses dari Android/device lain di WiFi yang sama

**Analogi:**

- `127.0.0.1` = Pintu kamar (hanya untuk Anda)
- `0.0.0.0` = Pintu rumah (tamu bisa masuk)

## VERIFIKASI BERHASIL

✅ **Browser komputer** bisa buka http://192.168.1.10:8000/api/test
✅ **Android app** bisa login
✅ **Jadwal** muncul sesuai kelas siswa (X RPL 1, XI RPL 1, dll)

## JIKA MASIH ERROR

### Check Firewall

Windows Firewall mungkin block port 8000:

```powershell
# Run sebagai Administrator
netsh advfirewall firewall add rule name="Laravel Server" dir=in action=allow protocol=TCP localport=8000
```

### Check IP Address

Pastikan IP masih `192.168.1.10`:

```powershell
ipconfig | findstr IPv4
```

Jika IP berubah, update di:

```
AplikasiMonitoringKelas\app\src\main\java\com\christopheraldoo\aplikasimonitoringkelas\network\NetworkConfig.kt
```

Ganti `BASE_URL = "http://192.168.1.10:8000/api/"` dengan IP baru, lalu rebuild APK.

---

**SELESAI! Sekarang Android app seharusnya bisa connect!** 🎉

Untuk penjelasan detail, baca: `PENJELASAN_LOCALHOST_VS_NETWORK_IP.md`
