# ⚡ QUICK START - JALANKAN FIX SERVER SISWA

## 🚨 LANGKAH CEPAT (5 MENIT)

### 1. Laravel Server Fix

```powershell
# Buka PowerShell sebagai Administrator
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"

# Jalankan script optimisasi
.\optimize-siswa.ps1

# Cek apakah berhasil
php artisan route:list | findstr siswa
```

### 2. Test Endpoint Baru

```bash
# Test dengan curl atau Postman
GET http://localhost:8000/api/siswa/jadwal-hari-ini
GET http://localhost:8000/api/siswa/riwayat-kehadiran?page=1&limit=5
GET http://localhost:8000/api/siswa/my-schedule?page=1
```

### 3. Android App Update

1. **Sync Project** di Android Studio
2. **Replace** existing API calls dengan yang baru:

```kotlin
// OLD (menyebabkan crash)
apiService.getSchedules()
apiService.getKehadiranHistory()

// NEW (ultra optimized)
enhancedApiService.getJadwalHariIni()
enhancedApiService.getRiwayatKehadiran()
```

## 🔥 INSTANT TEST

### Buka Laravel Server

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan serve --host=0.0.0.0 --port=8000
```

### Test API Response

```powershell
# Test jadwal hari ini (harus <5 detik)
Invoke-RestMethod -Uri "http://localhost:8000/api/siswa/jadwal-hari-ini" -Headers @{"Authorization"="Bearer YOUR_TOKEN"}
```

## 🎯 HASIL YANG HARUS TERLIHAT

✅ **Response Time**: <5 detik  
✅ **Server Memory**: <100MB  
✅ **No Timeouts**: Server tidak hang  
✅ **Smooth Pagination**: Data loading bertahap  
✅ **Circuit Breaker**: Auto recovery dari error

## 🚑 TROUBLESHOOTING

### Jika masih crash:

```powershell
# Clear semua cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Restart server
php artisan serve
```

### Jika endpoint tidak ditemukan:

```powershell
# Refresh route cache
php artisan route:cache
php artisan route:list | findstr siswa
```

### Jika database error:

```powershell
# Jalankan migration ulang
php artisan migrate:refresh --seed
php artisan migrate --force
```

---

**⚡ 3 ENDPOINT KRUSIAL YANG HARUS BEKERJA:**

1. `GET /api/siswa/jadwal-hari-ini`
2. `GET /api/siswa/riwayat-kehadiran`
3. `GET /api/siswa/my-schedule`

**Jika ketiga endpoint ini load <5 detik = FIX BERHASIL! 🎉**
