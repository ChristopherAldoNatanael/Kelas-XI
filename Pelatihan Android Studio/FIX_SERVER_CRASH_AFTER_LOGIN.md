# FIX: SERVER CRASH SETELAH LOGIN

## MASALAH

Server Laravel **tiba-tiba mati (crash)** setelah user berhasil login di Android app.

## PENYEBAB

1. **Memory overflow** karena logging yang berlebihan
2. **Nested map()** pada logging yang memproses semua schedules
3. **Headers logging** yang terlalu besar
4. **Tidak ada memory/timeout limit**

## SOLUSI YANG DITERAPKAN

### File: `sekolah-api/app/Http/Controllers/Api/ScheduleController.php`

#### 1. Method `myWeeklySchedule()` - Tambah Memory & Timeout Protection

**SEBELUM:**

```php
public function myWeeklySchedule(Request $request): JsonResponse
{
    try {
        $user = $request->user();
```

**SESUDAH:**

```php
public function myWeeklySchedule(Request $request): JsonResponse
{
    // CRITICAL: Prevent server crash with limits
    @set_time_limit(10); // Max 10 seconds
    @ini_set('memory_limit', '128M'); // Max 128MB

    try {
        $user = $request->user();
```

#### 2. Perbaiki Logging - Hapus Headers yang Berat

**SEBELUM:**

```php
Log::info('==== myWeeklySchedule CALLED ====', [
    'timestamp' => now(),
    'user' => $user ? $user->id : 'NULL',
    'headers' => $request->headers->all() // ❌ BERAT!
]);
```

**SESUDAH:**

```php
Log::info('==== myWeeklySchedule CALLED ====', [
    'timestamp' => now(),
    'user' => $user ? $user->id : 'NULL',
    'ip' => $request->ip() // ✅ RINGAN
]);
```

#### 3. Perbaiki Query Results Logging - Batasi Data

**SEBELUM:**

```php
Log::info('MyWeeklySchedule: Query results', [
    'class_name' => $userClass->nama_kelas,
    'schedules_count' => $schedules->count(),
    'schedules' => $schedules->map(function($s) { // ❌ MAP SEMUA DATA!
        return [
            'id' => $s->id,
            'hari' => $s->hari,
            'kelas' => $s->kelas,
            'mata_pelajaran' => $s->mata_pelajaran
        ];
    })
]);
```

**SESUDAH:**

```php
Log::info('MyWeeklySchedule: Query results', [
    'class_name' => $userClass->nama_kelas,
    'schedules_count' => $schedules->count(),
    'first_schedule' => $schedules->first() ? [ // ✅ HANYA 1 DATA SAMPLE
        'id' => $schedules->first()->id,
        'hari' => $schedules->first()->hari,
        'mata_pelajaran' => $schedules->first()->mata_pelajaran
    ] : null
]);
```

## CARA RESTART SERVER

### 1. Stop Server yang Crash

Tekan `Ctrl+C` di terminal atau kill proses:

```powershell
# Cari proses PHP
Get-Process php* | Stop-Process -Force
```

### 2. Start Server Baru

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan serve --host=0.0.0.0 --port=8000
```

Atau double-click: **START-SERVER.bat**

## TESTING

### 1. Login ke Android App

- Email: `siswa1@example.com`
- Password: `password123`

### 2. Monitor Server Terminal

Perhatikan log yang muncul:

```
[2025-01-24 10:30:00] local.INFO: ==== myWeeklySchedule CALLED ====
[2025-01-24 10:30:00] local.INFO: MyWeeklySchedule Debug
[2025-01-24 10:30:00] local.INFO: MyWeeklySchedule: User class found
[2025-01-24 10:30:00] local.INFO: MyWeeklySchedule: Query results
```

### 3. Verify Response di Android

Jadwal seharusnya muncul tanpa server crash!

## MONITORING RESOURCES

### Check Memory Usage (Windows)

```powershell
Get-Process php | Select-Object Name, @{Name="Memory(MB)";Expression={[math]::Round($_.WS / 1MB, 2)}}
```

### Check Laravel Logs

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
Get-Content storage\logs\laravel.log -Tail 20
```

## ADDITIONAL OPTIMIZATIONS

### Jika Masih Crash, Kurangi Data Limit

Edit `ScheduleController.php` line ~869:

```php
->limit(50) // Safety limit
```

Ubah jadi:

```php
->limit(20) // More conservative limit
```

### Disable Logging Sementara

Jika masih crash, comment semua Log::info():

```php
// Log::info('MyWeeklySchedule Debug', [...]);
```

### Increase PHP Memory Limit

Edit `php.ini`:

```ini
memory_limit = 256M
```

Atau di `.env`:

```
PHP_MEMORY_LIMIT=256M
```

## EXPECTED BEHAVIOR

### ✅ SERVER TIDAK CRASH

- Server tetap running setelah login
- Log muncul dengan data minimal
- Response dikirim ke Android dalam < 2 detik

### ✅ ANDROID APP

- Login berhasil
- Jadwal muncul sesuai kelas siswa
- Tidak ada error "failed to connect"

## ROOT CAUSE ANALYSIS

### Kenapa Server Crash?

1. **Memory Exhaustion**

   - Logging semua schedules dengan map()
   - Headers yang besar
   - Nested objects dalam log

2. **Excessive Processing**

   - Transformasi data 2x (query + transform)
   - Tidak ada timeout protection
   - No memory limit

3. **Log File Size**
   - Log file bisa jadi sangat besar
   - Setiap request log banyak data
   - Disk I/O lambat

## PREVENTION

### Best Practices untuk Mencegah Crash:

1. **Selalu set memory_limit & time_limit**

```php
@set_time_limit(10);
@ini_set('memory_limit', '128M');
```

2. **Log hanya data penting**

```php
// ❌ Jangan
Log::info('Data', ['all_schedules' => $schedules]);

// ✅ Lakukan
Log::info('Data', ['count' => $schedules->count()]);
```

3. **Gunakan limit pada query**

```php
->limit(50) // Always limit
```

4. **Cache results**

```php
Cache::remember($key, 120, function() {
    // expensive query
});
```

5. **Use pagination**

```php
->paginate(20) // Better than ->get()
```

## FILES CHANGED

✅ `sekolah-api/app/Http/Controllers/Api/ScheduleController.php`

- Method: `myWeeklySchedule()`
- Added: memory & timeout limits
- Fixed: excessive logging
- Optimized: log data structure

## NEXT STEPS

1. ✅ Restart server
2. ✅ Test login di Android
3. ✅ Verify jadwal muncul
4. ✅ Monitor memory usage
5. ✅ Check logs untuk errors

## QUICK RESTART

```powershell
# Stop all PHP processes
Get-Process php* | Stop-Process -Force

# Navigate to project
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"

# Start server
php artisan serve --host=0.0.0.0 --port=8000
```

## SUCCESS CRITERIA

✅ Server running stabil > 5 menit  
✅ Multiple logins tidak crash server  
✅ Memory usage < 100MB per request  
✅ Response time < 2 detik  
✅ Logs readable dan minimal

---

**KESIMPULAN:** Server crash disebabkan oleh **excessive logging**. Solusinya adalah **batasi data yang di-log** dan tambahkan **memory/timeout protection**.
