# Kurikulum API - Fix Summary

## Status: ✅ COMPLETE

Semua API endpoint untuk role Kurikulum telah diperbaiki dan berfungsi.

## API Endpoints Yang Tersedia

### 1. Dashboard Overview

- **URL**: `GET /api/kurikulum/dashboard`
- **Fungsi**: Menampilkan semua jadwal hari ini dengan status kehadiran guru
- **Response**: Stats (hadir, telat, tidak_hadir, pending), data jadwal dengan status warna

### 2. Class Management

- **URL**: `GET /api/kurikulum/classes`
- **Fungsi**: Menampilkan kelas diurutkan berdasarkan status guru
- **Response**: Status counts, alert classes (>15 menit tanpa guru), data jadwal

### 3. Available Substitutes

- **URL**: `GET /api/kurikulum/substitutes`
- **Query Params**: `start_time`, `subject_id`
- **Fungsi**: Daftar guru pengganti yang tersedia
- **Response**: Daftar guru yang tidak sedang mengajar

### 4. Assign Substitute

- **URL**: `POST /api/kurikulum/assign-substitute`
- **Body**: `schedule_id`, `substitute_teacher_id`, `keterangan` (optional)
- **Fungsi**: Menugaskan guru pengganti

### 5. Attendance History

- **URL**: `GET /api/kurikulum/history`
- **Query Params**: `page`, `limit`, `date_from`, `date_to`, `teacher_id`, `class_id`, `status`
- **Fungsi**: Riwayat kehadiran guru dengan filter

### 6. Attendance Statistics

- **URL**: `GET /api/kurikulum/statistics`
- **Query Params**: `month`, `year`, `teacher_id`
- **Fungsi**: Statistik persentase kehadiran bulanan

### 7. Export Attendance

- **URL**: `GET /api/kurikulum/export`
- **Query Params**: `date_from`, `date_to`, `teacher_id`, `class_id`
- **Fungsi**: Export data kehadiran untuk CSV/PDF

### 8. Filter Classes

- **URL**: `GET /api/kurikulum/filter/classes`
- **Fungsi**: Daftar kelas untuk dropdown filter

### 9. Filter Teachers

- **URL**: `GET /api/kurikulum/filter/teachers`
- **Fungsi**: Daftar guru untuk dropdown filter

### 10. Class Students

- **URL**: `GET /api/kurikulum/class/{classId}/students`
- **Fungsi**: Daftar siswa dalam kelas tertentu

## Perbaikan Yang Dilakukan

### Backend Fixes (KurikulumController.php)

1. **Import Subject Model**

   ```php
   use App\Models\Subject;
   ```

2. **Column Name Fixes**

   - `nama` → `nama_kelas` (ClassModel)
   - `tingkat` → `level` (ClassModel)
   - `jurusan` → `major` (ClassModel)
   - `kelas_id` → `kelas` (Schedule table uses class name)
   - `mapel_id` → `mata_pelajaran` (Schedule table uses subject name)
   - `period_number` → removed (tidak ada di schema baru)

3. **Eager Loading Fixes**

   - Removed column selection from `class` relationship karena relationship berbasis nama, bukan ID
   - Changed `'class:id,nama_kelas,level,major'` → `'class'`
   - Changed `'schedule.class:id,nama_kelas,level,major'` → `'schedule.class'`

4. **Filter Logic Fixes**

   - Class filter sekarang menggunakan lookup ke ClassModel untuk mendapatkan `nama_kelas`
   - Subject filter sekarang menggunakan lookup ke Subject untuk mendapatkan `nama`

5. **OrderBy Fixes**

   - `orderBy('kelas_id')` → `orderBy('kelas')`
   - `orderBy('tingkat')` → `orderBy('level')`
   - `orderBy('nama')` → `orderBy('nama_kelas')`

6. **Response Data Fixes**
   - `$schedule->kelas_id` → `$schedule->class->id ?? null`
   - Added fallback: `$schedule->class->nama_kelas ?? $schedule->kelas ?? 'Unknown'`

## Database Schema Reference

### schedules table (new structure)

- `hari` - enum (Senin, Selasa, ...)
- `kelas` - string (class name like "X RPL 1")
- `mata_pelajaran` - string (subject name)
- `guru_id` - foreign key to teachers
- `jam_mulai`, `jam_selesai` - time
- `ruang` - string

### classes table

- `nama_kelas` - string
- `level` - integer (10, 11, 12)
- `major` - string

### subjects table

- `nama` - string
- `kode` - string

## Android App Status

- ✅ Build SUCCESSFUL
- ✅ All Kurikulum screens implemented
- ✅ API endpoints configured in ApiService.kt
- ✅ ViewModel with auto-refresh 30 seconds
- ✅ Dark mode support
- ✅ Export functionality (CSV/PDF)

## Testing

```bash
# Test Dashboard
curl http://localhost:8000/api/kurikulum/dashboard

# Test Class Management
curl http://localhost:8000/api/kurikulum/classes

# Test History
curl http://localhost:8000/api/kurikulum/history

# Test Statistics
curl http://localhost:8000/api/kurikulum/statistics

# Test Export
curl http://localhost:8000/api/kurikulum/export
```

## Files Modified

### Backend

- `sekolah-api/app/Http/Controllers/Api/KurikulumController.php`

### Android (previously created)

- `app/.../data/KurikulumModels.kt`
- `app/.../ui/viewmodel/KurikulumViewModel.kt`
- `app/.../ui/screens/kurikulum/KurikulumDashboardScreen.kt`
- `app/.../ui/screens/kurikulum/KurikulumClassManagementScreen.kt`
- `app/.../ui/screens/kurikulum/KurikulumHistoryScreen.kt`
- `app/.../KurikulumActivity.kt`
- `app/.../network/ApiService.kt`
- `app/.../network/NetworkRepository.kt`
