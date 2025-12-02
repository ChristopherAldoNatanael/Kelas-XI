# Fitur Status Izin Guru di Dashboard Kurikulum

## Deskripsi

Fitur ini menambahkan tampilan status "IZIN" untuk guru yang sedang cuti/izin di dashboard role Kurikulum. Ketika seorang guru memiliki izin yang disetujui (approved) pada tanggal tertentu, status kehadiran mereka akan otomatis ditampilkan sebagai "IZIN" dengan warna purple, bukan "Pending".

## Perubahan yang Dilakukan

### 1. Backend API (PHP/Laravel)

**File: `sekolah-api/app/Http/Controllers/Api/KurikulumController.php`**

- Ditambahkan query untuk cek tabel `leaves` untuk guru yang sedang izin:

```php
// Get all teacher IDs from schedules to check for leaves
$teacherIds = $schedules->pluck('guru_id')->filter()->unique()->toArray();

// Check for approved teacher leaves on today's date
$teachersOnLeave = [];
if (count($teacherIds) > 0 && Schema::hasTable('leaves')) {
    $teachersOnLeave = DB::table('leaves')
        ->where('status', 'approved')
        ->where('start_date', '<=', $today)
        ->where('end_date', '>=', $today)
        ->whereIn('teacher_id', $teacherIds)
        ->get()
        ->keyBy('teacher_id');
}
```

- Ditambahkan field baru dalam response:

  - `teacher_on_leave: boolean` - Apakah guru sedang izin
  - `leave_reason: string` - Alasan izin (Sakit, Cuti Tahunan, dll)
  - `status: "izin"` - Status khusus untuk guru yang izin
  - `status_color: "purple"` - Warna untuk status izin

- Ditambahkan statistik izin:

```php
'izin' => collect($data)->where('status', 'izin')->count(),
```

### 2. Data Models (Kotlin)

**File: `AplikasiMonitoringKelas/.../data/KurikulumModels.kt`**

- `DashboardStats` - Ditambahkan field `izin: Int`
- `StatusCounts` - Ditambahkan field `izin: Int`
- `ScheduleOverview` - Ditambahkan fields:
  - `teacherOnLeave: Boolean`
  - `leaveReason: String?`
- `ClassScheduleItem` - Ditambahkan fields:
  - `teacherOnLeave: Boolean`
  - `leaveReason: String?`

### 3. UI Screen (Kotlin/Compose)

**File: `AplikasiMonitoringKelas/.../ui/screens/kurikulum/KurikulumDashboardScreen.kt`**

- **DashboardHeader Stats**: Ditambahkan StatCard untuk "Izin" dengan warna purple `Color(0xFF9C27B0)`

- **StatusLegend**: Ditambahkan item legend untuk "Izin" dengan warna purple

- **ClassScheduleCard**: Ditambahkan badge count untuk izin

- **ScheduleItem**:
  - Deteksi status izin: `val isTeacherOnLeave = schedule.teacherOnLeave || schedule.status == "izin"`
  - Status color purple untuk izin
  - Menampilkan teks "Izin" pada status chip
  - Menampilkan alasan izin (`leaveReason`)
  - Warna substitute teacher juga purple jika guru izin

## Warna yang Digunakan

| Status      | Warna      | Kode Hex      |
| ----------- | ---------- | ------------- |
| Hadir       | Hijau      | `#4CAF50`     |
| Telat       | Kuning     | `#FFC107`     |
| Tidak Hadir | Merah      | `#F44336`     |
| **Izin**    | **Purple** | **`#9C27B0`** |
| Pending     | Abu-abu    | `#9E9E9E`     |
| Diganti     | Biru       | `#2196F3`     |

## Cara Kerja

1. Saat dashboard dimuat, API mengambil jadwal untuk hari yang dipilih
2. API mengecek tabel `leaves` untuk mencari guru yang memiliki izin approved
3. Jika guru ditemukan memiliki izin pada tanggal tersebut:
   - `status` diset ke `"izin"`
   - `status_color` diset ke `"purple"`
   - `teacher_on_leave` diset ke `true`
   - `leave_reason` diisi dengan alasan izin
4. Android app menampilkan status dengan warna purple dan info izin

## Screenshot (Expected)

### Stats Header

```
┌───────┐ ┌───────┐ ┌───────────┐ ┌───────┐ ┌─────────┐
│   5   │ │   2   │ │     1     │ │   3   │ │    2    │
│ Hadir │ │ Telat │ │Tidak Hadir│ │ Izin  │ │ Pending │
│(green)│ │(yellow│ │  (red)    │ │(purple│ │ (gray)  │
└───────┘ └───────┘ └───────────┘ └───────┘ └─────────┘
```

### Schedule Item dengan Status Izin

```
┌─────────────────────────────────────────────────────┐
│ [1] Matematika                        ┌─────────┐   │
│     Bpk. Ahmad                        │  Izin   │   │
│     07:00 - 07:45                     └─────────┘   │
│                                       Cuti Tahunan  │
│                                   → Ibu Siti (Pengganti) │
└─────────────────────────────────────────────────────┘
```

## Testing

1. Pastikan ada data izin guru di tabel `leaves` dengan status `approved`
2. Login sebagai role Kurikulum
3. Pilih hari dan kelas yang memiliki jadwal dengan guru yang sedang izin
4. Verifikasi:
   - StatCard "Izin" menampilkan jumlah yang benar
   - Legend menampilkan item "Izin" dengan warna purple
   - Schedule item menampilkan status "Izin" dengan warna purple
   - Alasan izin ditampilkan
   - Guru pengganti ditampilkan (jika ada)

## File yang Dimodifikasi

1. `sekolah-api/app/Http/Controllers/Api/KurikulumController.php`
2. `AplikasiMonitoringKelas/.../data/KurikulumModels.kt`
3. `AplikasiMonitoringKelas/.../ui/screens/kurikulum/KurikulumDashboardScreen.kt`

## Tanggal Implementasi

2 Desember 2025
