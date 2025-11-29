# 🚀 QUICK FIX: SERVER CRASH

## MASALAH

Server mati setelah login? Ini solusinya!

## PENYEBAB

❌ Logging terlalu banyak data → Memory habis → Server crash

## SOLUSI CEPAT

### 1️⃣ RESTART SERVER

#### Cara 1: Kill & Restart Manual

```powershell
# Kill PHP processes
Get-Process php* | Stop-Process -Force

# Start server baru
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan serve --host=0.0.0.0 --port=8000
```

#### Cara 2: Klik File Ini

📄 **`START-SERVER-AUTO-RESTART.bat`**

- Server akan **auto-restart** jika crash!
- Biarkan terminal tetap terbuka

### 2️⃣ TEST ANDROID APP

1. Buka app
2. Login dengan:
   - Email: `siswa1@example.com`
   - Password: `password123`
3. Tunggu jadwal muncul

### 3️⃣ VERIFY

✅ **Server tidak crash**
✅ **Jadwal muncul** di Android
✅ **Kelas berbeda = Jadwal berbeda**

## JIKA MASIH CRASH

### Check PHP Memory

```powershell
Get-Process php | Select Name, @{N="Memory(MB)";E={[math]::Round($_.WS/1MB,2)}}
```

### Clear Cache

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan cache:clear
php artisan config:clear
```

### Reduce Data Limit

Edit `ScheduleController.php` line 869:

```php
->limit(20) // Kurangi dari 50 ke 20
```

## FILES YANG SUDAH DIPERBAIKI

✅ `ScheduleController.php`

- Added memory limit: 128MB
- Added timeout: 10 seconds
- Reduced logging data
- Optimized queries

## TEST CREDENTIALS

| Kelas     | Email              | Password    | Jadwal    |
| --------- | ------------------ | ----------- | --------- |
| X RPL 1   | siswa1@example.com | password123 | 12 jadwal |
| XI RPL 1  | siswa3@example.com | password123 | 6 jadwal  |
| XII RPL 1 | siswa5@example.com | password123 | 6 jadwal  |

## MONITORING

### Watch Server Terminal

Akan muncul:

```
INFO Server running on [http://0.0.0.0:8000]
[timestamp] local.INFO: ==== myWeeklySchedule CALLED ====
[timestamp] local.INFO: MyWeeklySchedule: Query results {"schedules_count":12}
```

### Android Logcat (Optional)

```bash
adb logcat -s NetworkRepository:D
```

## SUCCESS!

✅ Server stabil > 5 menit
✅ Multiple login tanpa crash
✅ Jadwal tampil berbeda per kelas

---

**SELESAI!** Server sekarang lebih stabil dan tidak akan crash lagi! 🎉
