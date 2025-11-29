# SOLUSI: TIDAK BISA LOGIN - FIXED

## MASALAH

Aplikasi Android tidak bisa login, menampilkan error "Failed to connect".

## PENYEBAB

**Server Laravel tidak running!**

Aplikasi Android perlu server Laravel aktif di `http://192.168.1.10:8000`

## SOLUSI

### ✅ CARA START SERVER (PILIH SALAH SATU)

#### OPSI 1: Start Manual (Recommended untuk Development)

```powershell
# Buka PowerShell/Terminal
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"

# Start server
php artisan serve --host=192.168.1.10 --port=8000
```

**Output yang benar:**

```
INFO  Server running on [http://192.168.1.10:8000].
Press Ctrl+C to stop the server
```

⚠️ **JANGAN TUTUP TERMINAL INI!** Server harus tetap jalan.

---

#### OPSI 2: Start dengan Background Task

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"

# Start di background
Start-Process powershell -ArgumentList "-NoExit", "-Command", "php artisan serve --host=192.168.1.10 --port=8000"
```

Ini akan membuka window PowerShell baru. Jangan tutup window tersebut.

---

#### OPSI 3: Create Batch File (Paling Mudah)

1. **Create file `start-server.bat`:**

```batch
@echo off
cd /d "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
echo ========================================
echo   Starting Laravel Server
echo   IP: 192.168.1.10:8000
echo ========================================
echo.
php artisan serve --host=192.168.1.10 --port=8000
pause
```

2. **Double-click `start-server.bat`** kapan pun mau menjalankan server

3. **Minimize** window (jangan ditutup)

---

## TESTING

### 1. Verify Server Running

Buka browser dan akses:

```
http://192.168.1.10:8000
```

✅ Should show Laravel welcome page or API info

---

### 2. Test Login API

**Via PowerShell:**

```powershell
$body = @{email='siswa1@example.com';password='password'} | ConvertTo-Json
$response = Invoke-RestMethod -Uri 'http://192.168.1.10:8000/api/auth/login' -Method Post -Body $body -ContentType 'application/json'
$response | ConvertTo-Json -Depth 5
```

**Expected Response:**

```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "user": {
      "id": 24,
      "email": "siswa1@example.com",
      "role": "siswa",
      "class_id": 1
    },
    "token": "27|...",
    "token_type": "Bearer"
  }
}
```

---

### 3. Test dari Android App

**Login Credentials:**

| Email                | Password   | Role  | Kelas     |
| -------------------- | ---------- | ----- | --------- |
| `siswa1@example.com` | `password` | Siswa | X RPL 1   |
| `siswa3@example.com` | `password` | Siswa | XI RPL 1  |
| `siswa5@example.com` | `password` | Siswa | XII RPL 1 |

**Steps:**

1. ✅ Pastikan server jalan
2. ✅ Buka Android app
3. ✅ Input email & password
4. ✅ Tap "Login"
5. ✅ Harus masuk ke dashboard dan lihat jadwal sesuai kelas

---

## TROUBLESHOOTING

### ❌ Error: "Address already in use"

**Penyebab:** Port 8000 sudah dipakai

**Solusi:**

```powershell
# Cari process yang pakai port 8000
netstat -ano | findstr :8000

# Kill process (ganti <PID> dengan angka dari hasil di atas)
taskkill /PID <PID> /F

# Atau gunakan port lain
php artisan serve --host=192.168.1.10 --port=8001
```

**Jika ganti port, update di Android:**

- Edit `NetworkConfig.kt`: `const val BASE_URL = "http://192.168.1.10:8001/api/"`

---

### ❌ Error: "Failed to connect"

**Checklist:**

1. ✅ Server Laravel jalan? Check `http://192.168.1.10:8000` di browser
2. ✅ Firewall block? Matikan Windows Firewall sementara
3. ✅ IP benar? Check dengan `ipconfig` di PowerShell
4. ✅ Android & PC di network yang sama?

---

### ❌ Error: "Unauthorized" atau "Invalid credentials"

**Solusi 1: Reset password**

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan tinker
```

Kemudian di Tinker:

```php
$user = App\Models\User::where('email', 'siswa1@example.com')->first();
$user->password = bcrypt('password');
$user->save();
exit
```

**Solusi 2: Check user exists**

```powershell
php artisan db:table users --where="email=siswa1@example.com"
```

---

### ❌ Login berhasil tapi jadwal kosong

Ini sudah diperbaiki! Backend sekarang filter jadwal berdasarkan kelas siswa.

**Verify:**

1. Login dengan `siswa1@example.com` → Should see 12 schedules (X RPL 1)
2. Login dengan `siswa3@example.com` → Should see 6 schedules (XI RPL 1)

---

## KEEP SERVER RUNNING

### Untuk Development:

- Start server di terminal
- Biarkan terminal terbuka
- Stop dengan `Ctrl+C` jika perlu

### Untuk Production/Testing:

Gunakan **XAMPP** atau **Laragon** agar server auto-start:

1. Install Laragon
2. Copy folder `sekolah-api` ke `C:\laragon\www\`
3. Start Apache & MySQL dari Laragon
4. Server akan jalan di `http://192.168.1.10/sekolah-api/public/`

---

## QUICK REFERENCE

### Start Server:

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan serve --host=192.168.1.10 --port=8000
```

### Check Server:

```
http://192.168.1.10:8000
```

### Test Login:

```
Email: siswa1@example.com
Password: password
```

### Check Logs:

```powershell
Get-Content "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api\storage\logs\laravel.log" -Tail 50
```

---

## FILES SUMMARY

✅ **Backend:** Server Laravel berfungsi dengan baik
✅ **Android:** NetworkConfig.kt sudah benar (`192.168.1.10:8000`)
✅ **Database:** Users & schedules sudah ada
✅ **Login API:** Tested & working

**MASALAH:** Server perlu di-start manual setiap kali

**SOLUSI:** Gunakan batch file atau Laragon untuk auto-start

---

## NEXT STEPS

1. ✅ Start server: `php artisan serve --host=192.168.1.10 --port=8000`
2. ✅ Install APK: `app-debug.apk`
3. ✅ Login dengan `siswa1@example.com` / `password`
4. ✅ Verify jadwal muncul (12 jadwal untuk X RPL 1)
5. ✅ Test dengan user lain untuk verify filtering

---

## STATUS: ✅ FIXED

- ✅ Login API working
- ✅ Token generation working
- ✅ Schedule filtering by class working
- ⚠️ **INGAT: Server harus jalan sebelum buka app!**
