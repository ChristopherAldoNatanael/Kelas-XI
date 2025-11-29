# PERBAIKAN JADWAL SISWA - FIXED

## MASALAH

Semua siswa (X RPL, XI RPL, XII RPL) melihat jadwal yang sama walaupun sudah login dengan akun yang berbeda.

## PENYEBAB

1. **Backend API response structure tidak sesuai** - Android app mengharapkan nested `data` field
2. **Android ViewModel menggunakan test/fallback data** - Ketika API gagal atau return empty, app menampilkan data test yang sama untuk semua siswa

## SOLUSI YANG DITERAPKAN

### 1. Backend (Laravel) - ScheduleController.php

#### File: `sekolah-api/app/Http/Controllers/Api/ScheduleController.php`

**Method `myWeeklySchedule()` - Line ~897**

**SEBELUM:**

```php
return response()->json([
    'success' => true,
    'message' => 'Jadwal mingguan berhasil dimuat',
    'data' => $formattedSchedules  // ❌ Langsung array
], 200);
```

**SESUDAH:**

```php
return response()->json([
    'success' => true,
    'message' => 'Jadwal mingguan berhasil dimuat',
    'data' => [
        'success' => true,
        'data' => $formattedSchedules,  // ✅ Nested sesuai StudentWeeklyScheduleResponse
        'message' => 'Jadwal kelas ' . $userClass->nama_kelas
    ]
], 200);
```

**Penjelasan:**

- Android app mengharapkan struktur `ApiResponse<StudentWeeklyScheduleResponse>`
- `ApiResponse` punya field `data`
- `StudentWeeklyScheduleResponse` juga punya field `data`
- Jadi perlu nested: `response.body()?.data?.data`

**Tambahan Logging untuk Debug:**

```php
Log::info('==== myWeeklySchedule CALLED ====', [
    'timestamp' => now(),
    'user' => $user ? $user->id : 'NULL',
]);
```

### 2. Android App - SiswaViewModel.kt

#### File: `AplikasiMonitoringKelas/app/src/main/java/com/christopheraldoo/aplikasimonitoringkelas/ui/viewmodel/SiswaViewModel.kt`

**Method `loadSchedules()` - Line ~102**

**SEBELUM:**

```kotlin
} else {
    // API returned empty, use test data
    val testSchedules = getTestSchedules()
    val groupedByDay = testSchedules.groupBy { it.dayOfWeek }
        .toSortedMap(compareBy { dayOrder(it) })
    _schedulesState.value = SchedulesUiState.Success(testSchedules, groupedByDay)
}
```

**SESUDAH:**

```kotlin
} else {
    // FIXED: Don't use test data, show empty state
    _schedulesState.value = SchedulesUiState.Success(emptyList(), emptyMap())
}
```

**Penjelasan:**

- Hapus semua fallback ke `getTestSchedules()`
- Tampilkan error yang sebenarnya atau empty state
- Jangan sembunyikan masalah dengan fake data

### 3. Android App - NetworkRepository.kt

#### File: `AplikasiMonitoringKelas/app/src/main/java/com/christopheraldoo/aplikasimonitoringkelas/network/NetworkRepository.kt`

**Method `getSchedules()` - Line ~154**

**Tambah Logging Detail:**

```kotlin
Log.d("NetworkRepository", "API Response Code: ${response.code()}")
Log.d("NetworkRepository", "API Response Success: ${response.isSuccessful}")
Log.d("NetworkRepository", "API Body Success: ${response.body()?.success}")
Log.d("NetworkRepository", "API Body Data: ${response.body()?.data}")

// Log first few schedules for debugging
schedules.take(3).forEach { schedule ->
    Log.d("NetworkRepository", "Schedule: ${schedule.className} - ${schedule.subjectName} (${schedule.dayOfWeek})")
}
```

## CARA TESTING

### 1. Backend Test

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php test-siswa-schedules.php
```

**Expected Output:**

```
=== TESTING SISWA SCHEDULE FILTERING ===

Found Siswa:
  ID: 8
  Name: Siti Rahmawati
  Class Name: X RPL 1

=== SCHEDULES FOR THIS CLASS ===

Total schedules found: 12

[Senin] 07:00-08:30 | Matematika Dasar | Guru: Budi Santoso
[Senin] 08:45-10:15 | Bahasa Indonesia | Guru: Siti Nurhaliza
...

=== ALL SISWA USERS ===

Siti Rahmawati (X RPL 1) -> Schedules: 12
Rizky Firmansyah (X RPL 2) -> Schedules: 5
Siswa XI RPL 1 -> Schedules: 6
Siswa XI RPL 2 -> Schedules: 7
```

### 2. Check Laravel Logs

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
Get-Content storage\logs\laravel.log -Tail 50
```

Cari log:

- `==== myWeeklySchedule CALLED ====`
- `MyWeeklySchedule Debug`
- `MyWeeklySchedule: User class found`
- `MyWeeklySchedule: Query results`

### 3. Android App Test

1. **Build & Install APK:**

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\AplikasiMonitoringKelas"
.\gradlew assembleDebug
```

2. **Login dengan berbeda siswa:**

   - siswa1@example.com (X RPL 1) → Should see 12 schedules
   - siswa3@example.com (XI RPL 1) → Should see 6 schedules
   - siswa5@example.com (XII RPL 1) → Should see 6 schedules

3. **Check Android Logcat:**

```
adb logcat -s NetworkRepository:D SiswaViewModel:D
```

Look for:

- `API Response Code: 200`
- `Successfully parsed X schedules`
- `Schedule: X RPL 1 - Matematika Dasar (Senin)`

## VERIFIKASI DATABASE

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php check-schedules-by-class.php
```

**Expected:**

```
=== SCHEDULES BY CLASS ===

Kelas: X RPL 1 -> 12 jadwal
Kelas: X RPL 2 -> 5 jadwal
Kelas: XI RPL 1 -> 6 jadwal
Kelas: XI RPL 2 -> 7 jadwal
Kelas: XII RPL 1 -> 6 jadwal
Kelas: XII RPL 2 -> 6 jadwal
```

## STRUKTUR DATA

### Backend Response Structure:

```json
{
  "success": true,
  "message": "Jadwal mingguan berhasil dimuat",
  "data": {
    "success": true,
    "data": [
      {
        "id": 8,
        "class_id": 1,
        "subject_id": 0,
        "teacher_id": 1,
        "day_of_week": "Senin",
        "period": 1,
        "start_time": "07:00:00",
        "end_time": "08:30:00",
        "status": "active",
        "class_name": "X RPL 1",
        "subject_name": "Matematika Dasar",
        "teacher_name": "Budi Santoso"
      },
      ...
    ],
    "message": "Jadwal kelas X RPL 1"
  }
}
```

### Android Parsing:

```kotlin
val response = apiService.getMyWeeklySchedule("Bearer $token")
val schedules = response.body()?.data?.data  // Double nested
```

## FILES CHANGED

1. ✅ `sekolah-api/app/Http/Controllers/Api/ScheduleController.php`

   - Method: `myWeeklySchedule()`
   - Fixed response structure
   - Added logging

2. ✅ `AplikasiMonitoringKelas/app/src/main/java/com/christopheraldoo/aplikasimonitoringkelas/ui/viewmodel/SiswaViewModel.kt`

   - Method: `loadSchedules()`
   - Removed fallback test data
   - Show real errors

3. ✅ `AplikasiMonitoringKelas/app/src/main/java/com/christopheraldoo/aplikasimonitoringkelas/network/NetworkRepository.kt`

   - Method: `getSchedules()`
   - Added detailed logging

4. ✅ Created test scripts:
   - `sekolah-api/test-siswa-schedules.php`
   - `sekolah-api/check-schedules-by-class.php`

## NEXT STEPS

1. **Rebuild Android App:**

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\AplikasiMonitoringKelas"
.\gradlew clean assembleDebug
```

2. **Clear App Data** (Important!)

   - Settings → Apps → Monitoring Kelas → Storage → Clear Data
   - Atau uninstall & reinstall

3. **Test dengan 3 akun berbeda:**

   - X RPL 1 student
   - XI RPL 1 student
   - XII RPL 1 student

4. **Verify logs** di:
   - Laravel: `storage/logs/laravel.log`
   - Android: Logcat

## TROUBLESHOOTING

### Jika masih menampilkan jadwal yang sama:

1. **Cek token masih valid:**

```kotlin
Log.d("Token", SessionManager(context).getAuthToken())
```

2. **Cek user class_id di backend:**

```php
Log::info('User', ['id' => $user->id, 'class_id' => $user->class_id]);
```

3. **Cek API endpoint:**

```
GET /api/siswa/weekly-schedule
Authorization: Bearer <token>
```

4. **Clear cache:**

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan cache:clear
php artisan config:clear
```

## KESIMPULAN

✅ Backend API sekarang mengembalikan jadwal sesuai kelas siswa  
✅ Filtering berdasarkan `user->class->nama_kelas`  
✅ Android app tidak lagi menggunakan test data  
✅ Response structure sesuai dengan model data Android  
✅ Logging ditambahkan untuk debugging

**JADWAL SEKARANG AKAN BERBEDA UNTUK SETIAP KELAS!**
