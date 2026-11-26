# Jam Layanan Klinik pada Slot Booking

Dokumen ini menjelaskan aturan jam layanan yang sekarang dipakai bersama oleh
form penawaran admin, API slot Android, pembuatan booking, dan penerimaan
penawaran pemilik hewan.

Terakhir diperbarui: 26 November 2026.

---

## 1. Masalah yang diperbaiki

Sebelumnya slot hanya mengikuti jadwal dokter (`available_days`, `start_time`,
`end_time`, dan pengecualian cuti). Jam operasional klinik (`opening_time`,
`closing_time`, `operating_days`) tidak pernah dipakai saat menghitung slot.
Akibatnya:

- admin bisa mengirim penawaran jam 08.00 padahal klinik buka 10.00;
- aplikasi Android menampilkan slot saat klinik tutup;
- pesan penolakan booking menyebut "dokter tidak praktik", padahal masalahnya
  klinik tutup.

Sekarang jam layanan = **irisan** jadwal dokter dengan jam operasional klinik.

---

## 2. Aturan yang berlaku

1. Klinik belum mengisi `opening_time`/`closing_time` → tidak ada batasan baru;
   jadwal dokter dipakai apa adanya (perilaku lama).
2. `operating_days` kosong atau berisi `all` → klinik dianggap buka setiap hari.
3. Klinik tutup pada hari itu → tidak ada slot, meskipun dokter punya jadwal.
4. Klinik buka → jam mulai = `max(jam mulai dokter, jam buka klinik)`, jam
   selesai = `min(jam selesai dokter, jam tutup klinik)`.
5. Bila jam dokter dan jam klinik tidak beririsan → tidak ada slot, dengan
   pesan yang menyebut kedua rentang jam.
6. Penawaran admin dan penerimaan penawaran oleh pemilik sama-sama memvalidasi
   ulang slot, sehingga penawaran lama tidak bisa menjadi booking di luar jam
   layanan.

---

## 3. Sumber kode

| Bagian | Berkas |
|--------|--------|
| Perhitungan jadwal + jam klinik | `app/Services/DoctorScheduleService.php` (`effectiveSchedule`, `clinicWindow`, `applyClinicHours`, `validateSlot`) |
| API slot Android | `app/Http/Controllers/Api/DoctorController.php` (`getAvailableSlots`) |
| Slot form penawaran admin | `app/Http/Controllers/Admin/ServiceRequestController.php` (`doctorSlots`, `update`) |
| Rute | `routes/web.php` — `admin.service-requests.doctor-slots` |
| UI penawaran | `resources/views/admin/service-requests/partials/offer-form.blade.php` + `offer-schedule-script.blade.php` |
| Booking & reschedule | `app/Http/Controllers/Api/BookingController.php` (memakai `validateSlot`) |
| Penerimaan penawaran | `app/Services/ServiceRequestService.php` (`accept`) |

---

## 4. Perilaku UI admin

Di panel **Susun Penawaran**:

- pilih dokter dan tanggal → jam dimuat otomatis dari server;
- pilihan jam berupa daftar, bukan input bebas;
- slot yang sudah dipesan pasien tampil sebagai `HH:MM — penuh` dan tidak bisa
  dipilih;
- teks di bawah tanggal menampilkan `Jam klinik: 10:00–15:00`;
- bila klinik tutup, dokter cuti, atau dokter tidak praktik, daftar jam kosong
  dan alasannya ditampilkan;
- bila jam penawaran sebelumnya sudah tidak tersedia, admin diminta memilih
  jam baru saat mengirim ulang.

Server tetap menolak jam yang tidak sah walaupun permintaan dikirim di luar UI.

---

## 5. Metadata API slot

`GET /api/doctors/{id}/slots?date=YYYY-MM-DD` sekarang mengirim tambahan pada
`meta`:

```json
{
  "is_working_day": true,
  "is_closed": false,
  "start_time": "10:00",
  "end_time": "15:00",
  "reason": null,
  "clinic_id": 12,
  "clinic_name": "PetHeal Vet",
  "clinic_opening_time": "10:00",
  "clinic_closing_time": "15:00",
  "clinic_operating_days": ["monday", "tuesday"],
  "clinic_is_open": true,
  "clinic_closed": false,
  "outside_clinic_hours": false
}
```

`start_time`/`end_time` sudah merupakan irisan, jadi klien lama yang hanya
membaca `data` tetap benar.

---

## 6. Perubahan Android

- `SlotMeta` menerima field klinik di atas (nullable, aman untuk backend lama).
- Status baru `CLINIC_CLOSED` ditampilkan sebagai kartu "Klinik tutup" berisi
  alasan dan jam operasional klinik.
- Hari yang dokternya tidak praktik memakai alasan dari server, termasuk kasus
  jam dokter tidak beririsan dengan jam klinik.
- Saat ada slot, picker menampilkan baris kecil `Jam klinik 10:00 - 15:00`.

Berkas: `data/model/Models.kt`, `util/SlotDayState.kt`,
`ui/components/BookingSlotPicker.kt`.

---

## 7. Verifikasi

Backend:

```bash
php artisan test --filter=ClinicHoursSchedulingTest
php artisan test
```

Hasil: 572 test lulus (3.452 assertion).

Android:

```bash
./gradlew testDebugUnitTest
```

Hasil: BUILD SUCCESSFUL, termasuk test baru `closed clinic wins over doctor
working day` dan `clinic hours label comes from server metadata`.

---

## 8. Form Susun Penawaran (admin)

Form dipecah menjadi tiga bagian bernomor agar admin tidak melewatkan harga
atau jadwal:

1. **Layanan & harga** — katalog opsional mengisi nama, deskripsi, durasi, dan
   harga awal; harga tetap dapat diubah admin dan wajib diisi. Jenis harga
   `Final` atau `Estimasi`.
2. **Dokter & jadwal** — memilih dokter langsung menampilkan hari praktik,
   jam praktik, dan jam klinik. Bila tanggal masih kosong, tanggal diisi
   otomatis ke hari praktik terdekat. Tanggal yang bukan hari praktik diberi
   peringatan dan tombol "Pakai <tanggal praktik terdekat>". Daftar jam baru
   aktif setelah dokter dan tanggal dipilih, dan hanya berisi irisan jam
   dokter dengan jam klinik.
3. **Masa berlaku & catatan** — batas waktu opsional (terisi otomatis H+3
   saat tanggal dipilih, boleh dikosongkan) dan catatan untuk pemilik.

Ringkasan penawaran (layanan, dokter, jadwal, biaya) diperbarui langsung saat
form diisi, sehingga admin bisa memeriksa sebelum mengirim.

Server tetap memvalidasi ulang: harga wajib ada, dokter harus aktif di klinik,
dan jam harus lolos `validateSlot()`.

## 9. Catatan

- Jangan menambahkan kembali perhitungan slot yang hanya memakai jadwal dokter.
  Semua jalur harus lewat `DoctorScheduleService::effectiveSchedule()`.
- Kolom `clinic.operating_days` diisi lewat **Pengaturan → Klinik**; bila
  kosong, klinik dianggap buka setiap hari.
- Booking yang dibuat sebelum perubahan ini tidak dibatalkan. Booking lama di
  luar jam klinik hanya ditandai oleh papan jadwal admin untuk ditinjau.
