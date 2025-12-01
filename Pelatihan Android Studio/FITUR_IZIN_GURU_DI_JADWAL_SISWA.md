# Fitur Status Izin Guru di Halaman Jadwal & Kehadiran Siswa

## 📋 Deskripsi Fitur

Fitur ini menampilkan status **IZIN** pada halaman jadwal dan kehadiran siswa ketika guru yang bersangkutan memiliki izin yang sudah disetujui (approved) pada tanggal tersebut. Status izin akan muncul **otomatis** tanpa perlu input manual.

## ✅ Perubahan yang Dilakukan

### 1. Backend (Laravel API)

#### File: `sekolah-api/app/Http/Controllers/Api/ScheduleController.php`

- Menambahkan query untuk cek tabel `leaves` (izin guru)
- Jika guru punya izin approved, status = `izin`
- Menampilkan alasan izin dan guru pengganti

#### File: `sekolah-api/app/Http/Controllers/Api/SiswaKehadiranGuruController.php`

- Menambahkan pengecekan izin guru di endpoint `todaySchedule`
- Response baru: `teacher_on_leave`, `leave_reason`, `substitute_teacher`
- Jika guru izin, `submitted` = true dan `status` = "izin"

### 2. Android App (Kotlin/Jetpack Compose)

#### File: `data/ApiModels.kt`

Menambahkan field baru di `ScheduleItem`:

```kotlin
@SerializedName("teacher_on_leave") val teacherOnLeave: Boolean = false,
@SerializedName("leave_reason") val leaveReason: String? = null,
@SerializedName("substitute_teacher") val substituteTeacher: String? = null
```

#### File: `ui/screens/JadwalScreen.kt`

- Warna ungu untuk status IZIN
- Badge IZIN dan panel info "Guru Sedang Izin"
- Menampilkan guru pengganti jika ada

#### File: `ui/screens/KehadiranScreen.kt`

- Warna ungu untuk status IZIN (`IzinPurple`, `IzinPurpleLight`)
- Ringkasan status menampilkan count "Izin"
- Card dengan border dan background ungu untuk guru izin
- Panel info lengkap: alasan izin + guru pengganti
- Tombol "Isi Kehadiran" tidak muncul jika guru izin

#### File: `ui/viewmodel/SiswaViewModel.kt`

- Mapping data izin dari API ke ScheduleItem

## 🎨 Tampilan UI

### Status Badge (Jadwal & Kehadiran)

| Status      | Warna       | Keterangan                      |
| ----------- | ----------- | ------------------------------- |
| HADIR       | 🟢 Hijau    | Guru sudah hadir                |
| TELAT       | 🟠 Orange   | Guru hadir terlambat            |
| TIDAK HADIR | 🔴 Merah    | Guru tidak hadir tanpa izin     |
| DIGANTI     | 🔵 Biru     | Guru diganti oleh guru lain     |
| **IZIN**    | 🟣 **Ungu** | **Guru sedang izin (approved)** |
| MENUNGGU    | ⚪ Abu-abu  | Menunggu status kehadiran       |

### Panel Info Izin (Jadwal Screen)

```
┌─────────────────────────────────────────┐
│ 📅 Guru Sedang Izin                     │
│ ↔️ Digantikan oleh: [Nama Guru]         │
│    atau                                 │
│ Menunggu guru pengganti dari Waka       │
│ Kurikulum                               │
└─────────────────────────────────────────┘
```

### Panel Info Izin (Kehadiran Screen)

```
┌─────────────────────────────────────────┐
│ 📅 Guru Sedang Izin                     │
│    Guru sedang [Alasan]                 │
│ ↔️ Digantikan: [Nama Guru Pengganti]    │
│    atau                                 │
│    Menunggu guru pengganti              │
└─────────────────────────────────────────┘
```

### Ringkasan Status (Kehadiran Screen)

```
┌──────────────────────────────────────────────────┐
│ Ringkasan Status Guru                            │
│                                                  │
│   🟢 2      🟠 1      🔴 0      🟣 1             │
│  Hadir    Telat    Absen    Izin                │
└──────────────────────────────────────────────────┘
```

## 📝 Fitur Khusus Halaman Kehadiran

1. **Card dengan border ungu** - Jika guru izin, card memiliki border dan background ungu
2. **Tombol "Isi Kehadiran" tersembunyi** - Siswa tidak perlu mengisi kehadiran untuk guru yang izin
3. **Status dihitung sebagai "submitted"** - Sehingga tidak masuk hitungan "Menunggu"
4. **Alasan izin ditampilkan** - Siswa tahu alasan guru tidak hadir

## 🧪 Cara Test

1. **Buat izin guru via API/Admin:**

   - Pastikan izin sudah approved
   - `start_date` ≤ hari ini ≤ `end_date`

2. **Login sebagai siswa**

3. **Test Halaman Jadwal:**

   - Buka halaman Jadwal
   - Cek jadwal hari ini
   - Guru yang izin menampilkan badge IZIN (ungu)
   - Panel info "Guru Sedang Izin" muncul

4. **Test Halaman Kehadiran:**
   - Buka halaman Kehadiran
   - Card guru izin berwarna ungu
   - Badge IZIN muncul
   - Tombol "Isi Kehadiran" tidak muncul
   - Panel info menampilkan alasan izin
   - Ringkasan menampilkan count "Izin"

## 🔄 Alur Data

```
┌──────────────────┐
│   Leaves Table   │
│   (Izin Guru)    │
└────────┬─────────┘
         │
         ├────────────────────────────────┐
         │                                │
┌────────▼─────────┐          ┌───────────▼───────────┐
│  ScheduleController│        │ SiswaKehadiranGuru    │
│ (Jadwal Screen)   │         │ Controller            │
│                   │         │ (Kehadiran Screen)    │
└────────┬─────────┘          └───────────┬───────────┘
         │                                │
         └───────────┬────────────────────┘
                     │
         ┌───────────▼───────────┐
         │   Android App         │
         │   JadwalScreen.kt     │
         │   KehadiranScreen.kt  │
         └───────────────────────┘
```

## 📅 Tanggal Implementasi

1 Desember 2025
