# Fitur Baru: Dashboard Kehadiran Guru Hari Ini

## Deskripsi

Fitur baru pada Dashboard Kepala Sekolah yang menampilkan daftar lengkap kehadiran guru berdasarkan status untuk hari ini dengan tampilan yang profesional dan interaktif.

## Implementasi

### Backend (Laravel)

**File:** `sekolah-api/app/Http/Controllers/Api/KepalaSekolahController.php`

#### Method Baru: `getTeachersAttendanceSummary($date)`

Method ini menghitung dan mengelompokkan guru berdasarkan status kehadiran:

```php
private function getTeachersAttendanceSummary($date): array
{
    // 1. Ambil semua guru yang punya jadwal hari ini
    // 2. Cek setiap guru apakah punya approved leave
    // 3. Cek record attendance di database
    // 4. Kelompokkan berdasarkan status: hadir, telat, tidak_hadir, izin, pending
    // 5. Return data lengkap dengan info jadwal masing-masing guru
}
```

#### Response API (teachers_attendance_today):

```json
{
    "date": "2025-12-03",
    "day": "Rabu",
    "summary": {
        "total_scheduled": 8,
        "present": 3,
        "late": 1,
        "absent": 2,
        "on_leave": 1,
        "pending": 1
    },
    "teachers_present": [...],
    "teachers_late": [...],
    "teachers_absent": [...],
    "teachers_on_leave": [...],
    "teachers_pending": [...]
}
```

### Frontend (Android Kotlin)

**File:** `AplikasiMonitoringKelas/app/src/main/java/com/.../data/KepalaSekolahModels.kt`

#### Model Baru:

- `TeachersAttendanceToday` - Container utama
- `AttendanceTodaySummary` - Ringkasan angka per status
- `TeacherAttendanceInfo` - Info detail guru
- `TeacherScheduleInfo` - Info jadwal guru

**File:** `AplikasiMonitoringKelas/app/src/main/java/com/.../KepalaSekolahActivity.kt`

#### Composable Baru:

1. **`TeachersAttendanceTodaySection`** - Section utama dengan:

   - Header card dengan tanggal dan total guru
   - Tab pills untuk filter status (Hadir/Terlambat/Tidak Hadir/Izin/Pending)
   - List guru berdasarkan status yang dipilih

2. **`AttendancePillButton`** - Button pill dengan:

   - Warna sesuai status (Hijau/Orange/Merah/Ungu/Abu-abu)
   - Badge angka jumlah guru
   - Efek selected/unselected

3. **`TeacherAttendanceItemCard`** - Card per guru dengan:
   - Avatar dengan inisial nama
   - Nama dan NIP guru
   - Waktu kehadiran (jika ada)
   - Alasan izin (jika izin)
   - Badge jumlah jam pelajaran
   - Info kelas pertama yang diajar

## Warna Status

| Status      | Warna   | Hex     |
| ----------- | ------- | ------- |
| Hadir       | Hijau   | #4CAF50 |
| Terlambat   | Orange  | #FF9800 |
| Tidak Hadir | Merah   | #F44336 |
| Izin        | Ungu    | #9C27B0 |
| Pending     | Abu-abu | Gray    |

## UI Preview

```
┌─────────────────────────────────────────┐
│  Kehadiran Guru Hari Ini                │
│  Rabu, 2025-12-03              8 Guru   │
└─────────────────────────────────────────┘

[Hadir 3] [Terlambat 1] [Tidak Hadir 2] [Izin 1] [Pending 1]
   ↑ selected

┌─────────────────────────────────────────┐
│ ┌──┐ Sari Dewi              ┌─────┐    │
│ │SD│ NIP: 1988...           │ 6JP │    │
│ └──┘ ⏰ 07:15               └─────┘    │
│                              XI RPL 2   │
├─────────────────────────────────────────┤
│ ┌──┐ Budi Santoso           ┌─────┐    │
│ │BS│ NIP: 1990...           │ 4JP │    │
│ └──┘ ⏰ 07:00               └─────┘    │
│                              X RPL 1    │
└─────────────────────────────────────────┘
```

## Testing

1. Buka Dashboard Kepala Sekolah
2. Scroll ke section "Kehadiran Guru Hari Ini"
3. Klik tab berbeda untuk melihat guru per status
4. Verifikasi data sesuai dengan database

## Files Modified

1. `sekolah-api/app/Http/Controllers/Api/KepalaSekolahController.php`

   - Ditambahkan method `getTeachersAttendanceSummary()`
   - Modified `dashboardOverview()` untuk include data baru

2. `AplikasiMonitoringKelas/.../data/KepalaSekolahModels.kt`

   - Ditambahkan model baru untuk attendance today

3. `AplikasiMonitoringKelas/.../KepalaSekolahActivity.kt`
   - Ditambahkan UI components baru
