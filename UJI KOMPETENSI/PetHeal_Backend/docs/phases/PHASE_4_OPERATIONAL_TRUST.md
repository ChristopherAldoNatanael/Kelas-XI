# PHASE 4 — Operational Trust & Proof

**Tanggal:** 2026-10-21
**Status:** Selesai untuk scope jadwal efektif (cuti/jam khusus), PDF bukti pembayaran, dan status reminder
**Roadmap:** `docs/UIUX_ROADMAP.md`

## 1. Tujuan

Fase ini menutup tiga celah kepercayaan yang tersisa setelah Fase 3:

1. Slot dokter hanya dihitung dari `available_days` + jam mingguan, sehingga cuti
   dan jam khusus tidak pernah tercermin — dan request yang dimanipulasi bisa
   mengirim jam di luar jadwal.
2. Bukti pembayaran pelanggan hanya PNG; dokumen resmi (PDF A4) belum ada.
3. Admin tidak tahu booking mana yang sudah diingatkan, dan reminder otomatis
   bisa terkirim dua kali pada hari yang sama.

Tidak ada membership, harga paket, subscription, billing SaaS, chat, atau mock UI.

## 2. Jadwal dokter, cuti, dan pengecualian per tanggal

### 2.1 Database

- Migration `database/migrations/2026_10_21_000001_create_doctor_schedule_exceptions_table.php`
  - `doctor_id` FK CASCADE, `clinic_id` FK CASCADE, `date`, `is_closed`
    (default true), `start_time` NULL, `end_time` NULL, `reason` NULL max 160,
    `created_by` NULL FK users NULLOnDelete, timestamps.
  - UNIQUE(`doctor_id`, `date`) → satu pengecualian per dokter per tanggal.
  - INDEX(`clinic_id`, `date`) untuk papan jadwal.
- Model `app/Models/DoctorScheduleException.php` menormalkan TIME MySQL
  (`08:00:00`) menjadi `08:00` dan tanggal menjadi `YYYY-MM-DD`.
- `Doctor::scheduleExceptions()` ditambahkan. Tidak ada kolom lama yang diubah
  atau dihapus.

### 2.2 Satu sumber kebenaran

`app/Services/DoctorScheduleService.php` menjadi satu-satunya perhitungan jadwal
efektif, dipakai oleh endpoint slot, create/reschedule booking, dan papan jadwal:

- Pengecualian `is_closed` selalu menang → tidak ada slot.
- Pengecualian jam khusus menimpa jam mingguan, dan boleh membuka hari yang
  biasanya tutup (mis. Sabtu tambahan).
- Tanpa pengecualian → `available_days` + `start_time`/`end_time`.
- `validateSlot()` juga menolak jam yang bukan kelipatan 30 menit dari jam mulai,
  sehingga `09:07` tidak bisa lolos hanya karena berada di antara 08:00–17:00.
- `bookingsOutsideSchedule()` mengembalikan booking aktif yang berada di luar
  jadwal baru — dipakai untuk peringatan admin, bukan pembatalan.

### 2.3 API

- `GET /api/doctors/{id}/slots` (`app/Http/Controllers/Api/DoctorController.php`)
  kini mengembalikan `meta` aditif:
  `{doctor_id,date,is_working_day,is_closed,has_exception,start_time,end_time,reason}`.
  Data tetap `[{time,available}]`; klien lama tidak terpengaruh.
- `GET /api/doctors/{id}` memuat `schedule_exceptions` (≤30 tanggal ke depan)
  agar date rail Android bisa menandai cuti tanpa request tambahan.
- `POST /api/bookings` (`BookingController::store`) memvalidasi ulang jadwal
  efektif sebelum menyimpan:
  - cuti / di luar jam / bukan slot → `422 {code:"slot_unavailable", message}`
  - bentrok tetap `409 {code:"slot_conflict"}`
- `POST /api/bookings/{id}/reschedule` memakai aturan yang sama dan kode error
  yang sama.

### 2.4 Admin

- Route baru (`routes/web.php`):
  - `GET /admin/doctors/{id}/schedule` → `admin.doctors.schedule`
  - `POST /admin/doctors/{id}/schedule-exceptions` → simpan (upsert)
  - `DELETE /admin/doctors/{id}/schedule-exceptions/{exception}` → hapus
- `Admin/DoctorScheduleController.php`:
  - Clinic-scoped (`currentClinicId()`); dokter klinik lain → 404.
  - Super admin tanpa konteks klinik diarahkan memilih klinik dulu.
  - Setiap aksi masuk `audit_logs` (`doctor.schedule_exception.save|delete`).
  - Jika masih ada booking aktif di luar jadwal baru, flash `warning` berisi
    jumlah booking yang perlu ditinjau. **Booking tidak dibatalkan otomatis.**
- View `admin/doctors/schedule.blade.php`: ringkasan jadwal mingguan, cuti
  terdekat, daftar 30 pengecualian terdekat, form cuti/jam khusus dengan
  validasi per-field, dan tautan ke papan jadwal tanggal tersebut.
- Papan jadwal `admin/schedule/index.blade.php`:
  - Badge per dokter: `Cuti`, `Jam khusus`, atau `Tidak praktik`.
  - Jam efektif tanggal itu ditampilkan (bukan jam mingguan).
  - Booking di luar jadwal diberi chip `Di luar jadwal` + peringatan
    “N booking di luar jadwal — tinjau manual”.
  - Tombol `tune` menuju halaman jadwal dokter dengan tanggal terpilih.
- Tautan `Jadwal & Cuti` ditambahkan di daftar dokter dan profil dokter.

### 2.5 Android

- `data/model/Models.kt`: `SlotMeta`, `DoctorSlots`, `DoctorScheduleException`,
  dan `Doctor.scheduleExceptions`. `SlotsResponse.meta` nullable (klien lama aman).
- `DoctorRepository.getDoctorSlots()` mengembalikan `DoctorSlots` (slot + meta).
- `util/SlotDayState.kt` (murni, teruji):
  - prioritas `CLOSED_LEAVE` → `NOT_PRACTICING` → `NO_SLOTS` → `HOURS_PASSED`
    → `FULLY_BOOKED` → `AVAILABLE`.
  - `toLeaveDates()` dan `customHoursLabel()`.
- `ui/components/BookingSlotPicker.kt`:
  - Kartu `Dokter cuti` menampilkan alasan dari server.
  - Banner `Jam khusus hari ini · 10:00 - 12:00` beserta alasan.
  - Date rail menandai tanggal cuti (latar amber, titik, deskripsi aksesibilitas)
    dari `doctor.scheduleExceptions`.
- `BookingViewModel`:
  - `slotMeta` / `rescheduleSlotMeta` di state; dimuat ulang saat tanggal berubah.
  - `422 slot_unavailable` membuang jam terpilih, menampilkan pesan server, dan
    memuat ulang slot tanpa menghapus pilihan hewan/layanan/dokter.
  - Tanggal awal booking melewati tanggal cuti yang diketahui.
- `BookingRepository` membaca `code` + `message` dari `errorBody` sekali, sehingga
  pesan cuti/jam khusus tampil apa adanya (bukan pesan generik 422).

## 3. PDF bukti pembayaran

- `ui/components/PaymentReceiptImage.kt`:
  - Renderer `drawReceipt()` diekstrak dan dipakai bersama oleh PNG dan PDF.
  - `createPaymentReceiptPdf()` menggambar ke halaman A4 portrait 595×842 pt
    dengan skala seragam (tanpa meregangkan layout) → PDF **vector**, bukan
    screenshot UI.
  - `saveReceiptPdfToDownloads()` menulis ke `Downloads/PetHeal` lewat
    MediaStore (Android 10+); Android 9 memakai pemilih lokasi.
  - `shareReceiptPdf()` membagikan lewat FileProvider (cache `receipts/`).
- `PaymentReceiptSheet`:
  - Header punya aksi salin teks.
  - Aksi: `Bagikan gambar`, `Bagikan PDF`, `Simpan PNG`, `Simpan PDF`.
  - Semua nominal tetap dari `Booking` backend; tidak ada data baru/mock.

## 4. Status reminder

- Migration `2026_10_21_000002_add_last_reminder_at_to_bookings_table.php`:
  `bookings.last_reminder_at` nullable. Cast `datetime` + fillable.
- `Admin/BookingController::sendReminder`: hanya mengisi `last_reminder_at`
  ketika FCM benar-benar mengirim. Pengiriman gagal tetap memberi flash warning
  dan tidak mengunci percobaan berikutnya.
- `app/Console/Commands/SendReminders.php`: booking yang sudah diingatkan pada
  hari yang sama dilewati; setelah terkirim, `last_reminder_at` diisi.
- Detail booking admin menampilkan `Belum diingatkan` atau
  `Terakhir diingatkan dd Mmm yyyy, HH:mm`.

## 5. Verifikasi

Backend:

- `php -l` untuk model, service, controller API/admin, command, routes, dan test:
  tidak ada syntax error.
- `php artisan migrate --force` → 2 migration DONE (dev DB).
- `DB_DATABASE=pet_heal_test php artisan migrate --force` → 2 migration DONE
  (test DB; suite memakai `DatabaseTransactions`, bukan `RefreshDatabase`).
- `php artisan view:cache` → sukses setelah semua view diubah.
- `php artisan route:list --path=admin/doctors` → 3 route baru terdaftar.
- Test baru `tests/Feature/Phase4OperationalTrustTest.php`: **19 test / 74
  assertion**, semua lolos (slot cuti/jam khusus, validasi create/reschedule,
  isolasi klinik, warning tanpa auto-cancel, badge board, halaman admin, status
  reminder, dan dedupe harian).
- Full suite: `php artisan test` → **140 passed (687 assertions)**, exit `0`.
- Smoke HTTP nyata (server lokal + admin `admin.pusat@petheal.com`):
  - `POST /admin/doctors/8/schedule-exceptions` (cuti) → `302`, baris DB dibuat.
  - `GET /admin/doctors/8/schedule` → `200`, form tampil.
  - `GET /api/doctors/8/slots?date=2026-10-22` →
    `{data:[], meta:{is_closed:true, reason:"Uji smoke fase 4"}}`.
  - `POST ... mode=custom 10:00–12:00` → `302`; slots `10:00,10:30,11:00,11:30`.
  - `POST /api/bookings` tanggal cuti → `422 slot_unavailable` (pesan cuti).
  - `POST /api/bookings` di luar jam khusus → `422 slot_unavailable` (pesan jam).
  - Jumlah booking sebelum/sesudah = 34 (tidak ada baris tercipta).
  - Semua data uji (2 pengecualian, token smoke, audit log uji) dihapus kembali
    dan diverifikasi 0 sisa.

Android:

- `./gradlew testDebugUnitTest` → **50 test, 0 failure, 0 skip** (termasuk 13
  test `SlotDayStateTest` dan 4 test parsing kontrak baru `SlotsResponse.meta`
  / `Doctor.scheduleExceptions`).
- `BackendIntegrationTest` ikut berjalan terhadap backend lokal yang hidup:
  9/9 lolos (kontrak lama tetap utuh).
- `./gradlew assembleDebug` → `BUILD SUCCESSFUL`.

## 6. Batasan dan sisa

- Uji visual di emulator/perangkat dan pembukaan WhatsApp/telepon tidak
  dijalankan karena `adb` tidak tersedia di environment ini. PDF dan sheet bukti
  belum diverifikasi secara visual; yang terverifikasi adalah kompilasi, kontrak
  data, dan build.
- Backend belum menolak jam hari ini yang sudah lewat bila masih berada di dalam
  jam praktik (mis. memesan 09:00 pada pukul 18:00 lewat request manual). Slot
  picker Android sudah menonaktifkannya; menambahkan aturan server perlu
  keputusan timezone (server UTC vs WIB) agar tidak menolak slot valid.
- Booking lama tidak dibatalkan otomatis saat cuti; admin hanya diberi
  peringatan dan tautan ke papan jadwal. Ini keputusan produk fase ini.
- Cache detail dokter di Android berlaku 5 menit, jadi badge cuti di date rail
  bisa terlambat muncul setelah admin mengubah jadwal. Slot tetap benar karena
  `meta` selalu diambil langsung dari endpoint slot.
- Reminder otomatis hanya dicegah dobel per hari, bukan per jenis reminder.
  Reminder manual pada hari yang sama tetap diizinkan (dan mencatat ulang
  `last_reminder_at`).

## 7. File yang disentuh

| Area | File |
| --- | --- |
| DB | `database/migrations/2026_10_21_000001_create_doctor_schedule_exceptions_table.php`, `2026_10_21_000002_add_last_reminder_at_to_bookings_table.php` |
| Model | `app/Models/DoctorScheduleException.php`, `Doctor.php`, `Booking.php` |
| Service | `app/Services/DoctorScheduleService.php` |
| API | `app/Http/Controllers/Api/DoctorController.php`, `Api/BookingController.php` |
| Admin | `app/Http/Controllers/Admin/DoctorScheduleController.php`, `Admin/BookingController.php`, `routes/web.php` |
| View admin | `resources/views/admin/doctors/schedule.blade.php`, `admin/schedule/index.blade.php`, `admin/doctors/index.blade.php`, `admin/doctors/show.blade.php`, `admin/bookings/show.blade.php` |
| Reminder | `app/Console/Commands/SendReminders.php` |
| Android data | `data/model/Models.kt`, `data/repository/DoctorRepository.kt`, `BookingRepository.kt` || Android UI | `ui/components/BookingSlotPicker.kt`, `PaymentReceiptImage.kt`, `PaymentReceiptSheet.kt`, `ui/screens/booking/BookingViewModel.kt`, `BookingsScreen.kt`, `ui/screens/doctor/DoctorsViewModel.kt` |
| Android util | `util/SlotDayState.kt`, `util/DoctorAvailability.kt`, `util/PaymentReceipt.kt` |
| Test | `tests/Feature/Phase4OperationalTrustTest.php`, `app/src/test/.../SlotDayStateTest.kt`, `ContractParsingTest.kt` |
| Docs | `docs/UIUX_ROADMAP.md`, `docs/DB_SCHEMA.md`, `docs/API_CONTRACT.md`, `API_DOCUMENTATION.md`, `docs/PROGRESS.md` |

## 8. Next step

Fase 4 selesai. Fase berikutnya tetap **Backlog** sampai ada keputusan produk:
membership/promo engine, SaaS billing, chat/telemedicine, PWA, dan analitik
lintas klinik. Jangan membuat mock UI untuk item tersebut.
