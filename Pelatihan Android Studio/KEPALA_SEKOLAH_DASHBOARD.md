# Kepala Sekolah Dashboard - Implementation Complete

## Overview

Dashboard lengkap untuk Kepala Sekolah dengan statistik kehadiran guru, perbandingan mingguan, dan daftar guru izin.

## Features Implemented

### 1. Dashboard Overview (`/api/kepala-sekolah/dashboard`)

- **Statistik Kehadiran**: Hadir, Telat, Tidak Hadir, Izin, Diganti
- **Perbandingan Mingguan**: This week vs Last week dengan trend indicator
- **Daily Breakdown**: Chart harian untuk visualisasi kehadiran
- **Teachers on Leave**: Daftar guru izin/diganti dengan info pengganti
- **Top Late Teachers**: Ranking guru yang sering terlambat
- **Class Attendance Rates**: Persentase kehadiran per kelas

### 2. Attendance List (`/api/kepala-sekolah/attendance`)

- Filter by status (hadir, telat, tidak_hadir, izin, diganti)
- Filter by week (week_offset parameter)
- Filter by class name
- Filter by teacher
- Filter by date

### 3. Teacher Performance (`/api/kepala-sekolah/teacher-performance`)

- Ranking guru berdasarkan attendance rate
- Statistik per guru: hadir, telat, tidak hadir, izin
- Sortable by: attendance_rate, late_count, absent_count

### 4. Teachers on Leave (`/api/kepala-sekolah/teachers-on-leave`)

- Daftar guru yang izin atau diganti
- Info guru pengganti
- Keterangan izin

## Files Created/Modified

### Laravel API (Backend)

1. `app/Http/Controllers/Api/KepalaSekolahController.php` - Controller utama
2. `routes/api.php` - Routes untuk kepala-sekolah

### Android App (Frontend)

1. `data/KepalaSekolahModels.kt` - Data models for API responses
2. `ui/viewmodel/KepalaSekolahViewModel.kt` - ViewModel for dashboard
3. `ui/screens/kepalasekolah/KepsekDashboardScreen.kt` - Dashboard screen
4. `ui/screens/kepalasekolah/KepsekAttendanceListScreen.kt` - Attendance list screen
5. `ui/screens/kepalasekolah/KepsekTeacherPerformanceScreen.kt` - Performance ranking screen
6. `KepalaSekolahActivity.kt` - Updated with new dashboard
7. `network/ApiService.kt` - Added kepala-sekolah endpoints
8. `network/NetworkRepository.kt` - Added repository methods

## API Endpoints

| Method | Endpoint                                  | Description                               |
| ------ | ----------------------------------------- | ----------------------------------------- |
| GET    | `/api/kepala-sekolah/dashboard`           | Dashboard overview with weekly comparison |
| GET    | `/api/kepala-sekolah/attendance`          | Detailed attendance list with filters     |
| GET    | `/api/kepala-sekolah/teachers-on-leave`   | Teachers on leave with substitutes        |
| GET    | `/api/kepala-sekolah/teacher-performance` | Teacher ranking by attendance             |

## Query Parameters

### Dashboard

- `week_offset` (int): 0 = this week, -1 = last week

### Attendance List

- `status` (string): hadir, telat, tidak_hadir, izin, diganti
- `week_offset` (int): 0 = this week, -1 = last week
- `class_name` (string): Filter by class name
- `teacher_id` (int): Filter by teacher ID
- `date` (string): Specific date (YYYY-MM-DD)

### Teacher Performance

- `week_offset` (int): 0 = this week, -1 = last week
- `sort_by` (string): attendance_rate, late_count, absent_count

## UI Components

### Dashboard Screen

- **Week Selector**: Toggle antara "Minggu Ini" dan "Minggu Lalu"
- **Attendance Rate Card**: Persentase kehadiran dengan trend indicator
- **Statistics Grid**: 4 cards (Hadir, Telat, Tidak Hadir, Izin/Diganti)
- **Daily Breakdown Chart**: Stacked bar chart per hari
- **Teachers on Leave List**: Cards dengan info izin dan pengganti
- **Top Late Teachers**: Ranking guru terlambat
- **Class Attendance Rates**: Progress bar per kelas

### Attendance List Screen

- **Filter Chips**: Quick filter by status
- **Summary Card**: Total per status
- **Attendance Cards**: Detail kehadiran dengan status badge

### Performance Screen

- **Ranking List**: Cards dengan rank badge dan stats
- **Color-coded**: Gold, Silver, Bronze untuk top 3

## Testing

Run test script:

```bash
cd sekolah-api
php test_all_kepsek.php
```

Expected output:

```
=== Testing All Kepala Sekolah Endpoints ===
Dashboard: OK ✓
Attendance List: OK ✓
Teacher Performance: OK ✓
Teachers on Leave: OK ✓
=== All tests completed ===
```

## Navigation

Bottom navigation bar dengan 3 tabs:

1. **Dashboard** - Overview dengan statistik
2. **Jadwal** - Jadwal pelajaran (existing)
3. **Kelas Kosong** - Monitoring ruangan (existing)

## Auto-Refresh

Dashboard auto-refresh setiap 60 detik untuk update real-time.
