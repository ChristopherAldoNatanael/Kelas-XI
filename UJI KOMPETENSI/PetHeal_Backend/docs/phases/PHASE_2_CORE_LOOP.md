# PHASE 2 — Core Loop Visibility

**Tanggal:** 2026-10-20
**Status:** Selesai untuk scope kesehatan hewan
**Roadmap:** `docs/UIUX_ROADMAP.md`

## 1. Tujuan

Pemilik hewan bisa menjawab dua pertanyaan tanpa membuka rekam medis satu per
satu:

1. "Berat badan hewan saya naik/turun?"
2. "Vaksin berikutnya sudah dekat atau terlambat?"

Fase ini sengaja dibatasi pada visibilitas data yang **sudah ada** di API.
Tidak ada endpoint baru dan tidak ada perubahan skema.

## 2. Yang diubah

### 2.1 Grafik tren berat badan

File baru: `PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/ui/components/WeightTrendChart.kt`

- Composable `WeightTrendChart` (`WeightTrendChart.kt:37`) menggambar line
  chart dengan Compose `Canvas` (`:60`) — tanpa library chart baru.
- Hanya memakai maksimal 8 pengukuran terakhir yang punya `recordedAt` dan
  `weight`, diurutkan naik. Titik terakhir diberi ring lebih tebal.
- Grid tiga garis, label tanggal pertama/terakhir, dan nilai terendah/
  tertinggi ditampilkan di luar canvas agar mengikuti font scale sistem.
- `contentDescription` dinamis (`:56`) untuk pembaca layar.
- Data kosong/berat sama tidak menghasilkan pembagian nol
  (`range = (highest - lowest).takeIf { it > 0.0 } ?: 1.0`).

File: `PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/ui/screens/pet/PetsScreen.kt`

- `HealthTrackingSection` membangun `chartPoints` dari `weightRecords`
  (`PetsScreen.kt:1110`) dan hanya merender grafik jika ada ≥2 titik.
- Warna memakai token yang sudah ada: `PetPrimary`, `textMuted`,
  `surfaceColor`. Tidak ada hex baru.
- Tidak ada layout baru yang menggeser alur Pet Detail; grafik disisipkan
  tepat setelah chip trend dan sebelum daftar riwayat.

### 2.2 Status vaksinasi

File: `PetsScreen.kt`

- Helper `vaccinationDueStatus` (`PetsScreen.kt:1200`) menghitung selisih hari
  dari `nextDueDate`:
  - `< 0` → `Terlambat` (`ErrorStrong`);
  - `0..30` → `Jatuh tempo` (`Tertiary`);
  - `> 30` → `Terjadwal` (`Info`);
  - tanggal kosong/tidak valid → tanpa badge (bukan "aman" palsu).
- `HealthMiniRow` menerima `badgeText`/`badgeColor` opsional
  (`:1217`, render di `:1259`), jadi baris berat badan tidak terpengaruh.
- Badge hanya informatif; aksi tambah/hapus vaksin tetap di tempatnya.

## 3. Verifikasi

| Cek | Hasil |
| --- | --- |
| `.\gradlew.bat assembleDebug` | `BUILD SUCCESSFUL` |
| Kompilasi Kotlin | Tidak ada error; hanya warning lama `initialAge` tidak dipakai |
| Data < 2 titik | Grafik tidak dirender, tidak crash |
| Data berat sama | Range fallback 1.0, garis tetap tampil |
| Tanggal vaksin tidak valid | `runCatching` → tanpa badge, tidak crash |
| Warna | Memakai token theme, tidak ada `Color(0xFF...)` baru |

## 4. Keputusan penting

- **Canvas, bukan library chart.** Library chart Android menambah dependency
  dan ukuran APK untuk kebutuhan yang sederhana. Canvas cukup dan tetap
  konsisten dengan tema.
- **Maksimal 8 titik.** Lebih dari itu membuat label tanggal bertabrakan di
  layar kecil; grafik adalah ringkasan, bukan alat analisis.
- **Badge tanggal, bukan teks bebas.** Status dihitung dari `next_due_date`
  sehingga tidak bisa "menyimpan" status lama di UI.
- **Tidak ada perubahan backend.** Semua data sudah tersedia di response
  `weight_records` dan `vaccinations`.

## 5. Kekurangan yang diketahui (jangan dianggap selesai)

- Belum ada filter rentang 3/6/12 bulan; grafik selalu 8 titik terakhir.
- Belum ada pull-to-refresh; data mengikuti refresh yang sudah ada di screen.
- Badge vaksinasi belum diklik untuk membuka detail/aksi.
- Baris riwayat masih menampilkan tanggal mentah dari API; belum diformat
  konsisten di semua tempat.

Item di atas masuk **Backlog** atau **Fase 3**, bukan dikerjakan diam-diam
di fase ini.

## 6. Next step

1. Selesaikan verifikasi manual di HP: buka Pet Detail dengan ≥3 riwayat
   berat, pastikan garis tidak terpotong dan badge vaksin terbaca.
2. Lanjut ke **Fase 3** dengan urutan yang sudah ditetapkan di roadmap:
   booking slot picker → invoice center → admin schedule board → command
   palette.
3. Jangan menambah chat/telemedicine atau billing di Fase 3.

## 7. File yang disentuh

- `app/src/main/java/com/christopheraldoo/petheal/ui/components/WeightTrendChart.kt` (baru)
- `app/src/main/java/com/christopheraldoo/petheal/ui/screens/pet/PetsScreen.kt`
