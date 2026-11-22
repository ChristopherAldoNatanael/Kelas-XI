# REMEDIASI AUDIT BACKEND MENDALAM — PetHeal

Dokumen ini mencatat **apa yang sudah diperbaiki** dari
`../PETHEAL_DEEP_BACKEND_AUDIT.md` (33 temuan: 3 CRITICAL, 10 HIGH, 9 MEDIUM,
7 LOW, 4 INFO), apa yang sengaja **belum** disentuh beserta alasannya, dan
aturan apa yang tidak boleh dilanggar lagi oleh perubahan berikutnya.

Ditulis untuk pengembang berikutnya dan agent AI berikutnya: setiap baris
menyebut berkas, perilaku sebelum/sesudah, dan cara membuktikannya.

| | |
|---|---|
| Basis kode | `PetHeal_Backend` (Laravel 12.62, PHP 8.3) |
| Status verifikasi | `php artisan test` **534 lulus / 3185 assertion / 0 gagal**, `php -l` bersih untuk semua berkas yang diubah, `php artisan route:list` normal, smoke test HTTP 200 untuk `/api/health`, `/admin/payments`, `/admin/payment-settings`, `/admin/bookings` (login admin demo) |
| Perubahan skema | **Tidak ada.** Semua perbaikan murni level aplikasi, jadi tidak perlu `php artisan migrate` |
| Perubahan `.env` | Tidak ada (variabel opsional: `TRUSTED_PROXIES`, `LOG_STACK`) |
| Migrasi yang sengaja tidak dibuat | Unique index slot booking (PB-H-04) — lihat §4 |

---

## 1. Ringkasan status

| ID | Tingkat | Masalah | Status | Berkas kunci |
|---|---|---|---|---|
| PB-C-01 | CRITICAL | `DELETE /api/pets/{id}` menghapus booking + rekam medis lewat cascade | Selesai (guard) | `Api/PetController.php` |
| PB-C-02 | CRITICAL | `extra_payment_amount` tidak dihitung ulang setelah booking dibayar → pasien ditagih dua kali | Selesai | `Models/MedicalRecord.php`, `Services/PaymentStatusService.php`, `Admin/PaymentController.php` |
| PB-C-03 | CRITICAL | Hapus akun menghancurkan riwayat klinis + meninggalkan `payments` yatim | Selesai (anonimisasi) | `Api/AuthController.php` |
| PB-H-01 | HIGH | Booking batal padahal sudah dibayar tidak meninggalkan jejak refund | Selesai (`refund_pending`/`refunded` + kartu laporan) | `Models/Booking.php`, `Services/RefundService.php`, `Admin/BookingController.php`, `Api/BookingController.php`, `Admin/PaymentController.php` |
| PB-H-02 | HIGH | Pesanan Pet Shop bisa `expired` + `paid` (stok dilepas, uang masuk, tanpa refund) | Selesai | `Services/ProductOrderService.php`, `Services/PaymentService.php` |
| PB-H-03 | HIGH | Dua intent pembayaran bersamaan → `paid_amount` melebihi tagihan | Selesai | `Services/PaymentService.php`, `Services/PaymentStatusService.php` |
| PB-H-04 | HIGH | Tidak ada UNIQUE slot booking di DB | **Ditunda** (butuh migrasi; ditolak untuk batch ini) | lihat §4 |
| PB-H-05 | HIGH | `ServiceRequestService::accept` tidak memvalidasi jadwal/klinik | Selesai | `Services/ServiceRequestService.php` |
| PB-H-06 | HIGH | Booking `completed` tidak bisa ditagih sisa | Selesai | `Services/PaymentService.php` |
| PB-H-07 | HIGH | Hapus rekam medis tanpa penjaga uang/audit/berkas | Selesai | `Admin/MedicalRecordController.php` |
| PB-H-08 | HIGH | `trustProxies('*')` membuat IP bisa dipalsukan | Selesai | `bootstrap/app.php` |
| PB-H-09 | HIGH | `/api/health` publik membocorkan path & error DB | Selesai | `Api/HealthController.php` |
| PB-H-10 | HIGH | `PaymentController` mengembalikan `$e->getMessage()` ke klien | Selesai | `Api/PaymentController.php` |
| PB-M-02 | MEDIUM | Refund tidak memberi tahu pasien | Selesai (notifikasi) | `Admin/PaymentController.php` |
| PB-M-03 | MEDIUM | Webhook tak dikenal hilang tanpa jejak | Selesai (`payment_notifications` + panel tinjau) | `Admin/PaymentSettingsController.php`, `resources/views/admin/payment-settings/index.blade.php`, `routes/web.php` |
| PB-M-04 | MEDIUM | Aplikasi mengirim `BOOKING-{id}` / nominal salah → 422 | Sebagian (sisi backend) | `Api/PaymentController.php` |
| PB-M-05 | MEDIUM | N+1 posisi antrian (20 query per halaman) | Selesai | `Services/QueueService.php`, `Api/BookingController.php` |
| PB-M-06 | MEDIUM | Cache statistik pembayaran bertabrakan antar filter | **Bukan bug** + log `daily` (lihat §5) | `config/logging.php` |
| PB-M-07 | MEDIUM | `advance`/`offer` service request tanpa row lock | Selesai | `Services/ServiceRequestService.php` |
| PB-L-01 | LOW | Berkas lampiran rekam medis tertinggal di disk | Selesai | `Admin/MedicalRecordController.php` |
| PB-L-03 | LOW | `register_direct` di daftar wajib health check (route 410) | Selesai | `Api/HealthController.php` |
| PB-I-01/02/03/04 | INFO | Catatan desain, bukan cacat | Tidak diubah | — |

**Tidak diubah sama sekali:** skema database, konfigurasi Midtrans/FCM, dan
kontrak JSON yang sudah dipakai aplikasi (field lama tetap ada; penambahan
bersifat opsional). Blade hanya disentuh di dua halaman admin yang memang menjadi
permukaan temuannya: `/admin/payments` (kartu + filter refund) dan
`/admin/payment-settings` (panel tinjau webhook).

---

## 2. Perbaikan per kelompok

### 2.1 Integritas riwayat klinis (PB-C-01, PB-C-03, PB-H-07, PB-L-01)

**Sebelum.** `bookings`, `medical_records`, dan `payment_events` memakai
`ON DELETE CASCADE`. Satu tap "hapus hewan" di aplikasi, atau satu tombol hapus
rekam medis di panel admin, ikut melenyapkan invoice dan catatan SOAP — sementara
`Admin\UserController` justru menolak menghapus user yang punya riwayat.

**Sesudah.**

- `Api\PetController::destroy` menolak (HTTP **409**, `code: pet_has_history`)
  bila hewan punya booking/rekam medis. Hewan tanpa riwayat tetap bisa dihapus.
- `Api\AuthController::deleteAccount` tidak lagi menghapus riwayat:
  - akun **tanpa** booking → baris user benar-benar dihapus (perilaku lama,
    test `AccountOtpFlowTest` tetap lulus);
  - akun **dengan** booking → data pribadi dianonimkan (nama, email, telepon,
    foto, `firebase_uid`, password, `remember_token`), notifikasi/device
    token/token login dihapus, ulasan dokter dihapus, hewan tanpa riwayat
    dihapus; booking, rekam medis, dan pembayaran tetap utuh dengan responden
    `"mode": "anonymized"`.
- `Admin\MedicalRecordController::destroy` menolak menghapus rekam medis yang
  sudah punya pembayaran tercatat (`payments` atau `extra_payment_paid_amount`
  > 0), menghapus berkas lampiran lewat `MedicalRecordAttachmentService`, dan
  menulis `AuditLog` (`medical_record.deleted`) di dalam satu transaksi.

**Kenapa aman.** Semua penolakan mengembalikan pesan yang bisa ditampilkan
aplikasi; jalur yang tidak punya riwayat tetap berjalan persis seperti dulu.
Kolom/relasi tidak berubah.

**Cek di sini.** Hapus hewan yang punya booking dari aplikasi → muncul pesan
"memiliki riwayat kunjungan … hubungi klinik".

---

### 2.2 Uang: satu angka, satu kebenaran (PB-C-02, PB-H-02, PB-H-03, PB-H-06)

**Sebelum.**

1. `extra_payment_amount = total_medical_cost - booking.paid_amount` hanya
   dihitung saat rekam medis dibuat/diedit. Pembayaran yang datang belakangan
   (pelunasan DP, konfirmasi kas) tidak pernah menurunkannya → pasien ditagih
   lagi sebesar uang yang sudah masuk.
2. Dua request snap-token bersamaan sama-sama lolos pemeriksaan "sudah ada
   transaksi pending?" → dua baris `payments` untuk satu tagihan.
3. Webhook yang masuk setelah scheduler mengedaluwarkan pesanan tetap menulis
   `payment_status = paid` pada pesanan `expired` (stok sudah dilepas).
4. Booking `completed` selalu ditolak untuk pembayaran sisa.

**Sesudah.**

- `MedicalRecord::syncExtraPaymentForBooking($booking)` menjadi satu-satunya
  tempat menghitung ulang biaya tambahan; dipanggil dari
  `PaymentStatusService` (setelah `bookings.paid_amount` berubah karena
  webhook/pelunasan) dan dari `Admin\PaymentController::confirm` (konfirmasi
  kas) di dalam transaksi yang sama. Selisih lebih dicatat sebagai
  `Log::warning` agar ditinjau untuk refund.
- `PaymentService::createBookingPayment` dan `createPetShopPayment` dibungkus
  `Cache::lock(...)->block(5)`: request kedua menunggu lalu memakai transaksi
  yang dibuat request pertama. Bila lock tidak didapat dalam 5 detik, klien
  menerima pesan `payments.payment_in_progress` (bukan transaksi ganda).
- `PaymentStatusService` membatasi `paid_amount` pada `total_amount` dan
  menulis `Log::warning` berisi nominal kelebihan (uang tetap terlihat di
  `payment_events`).
- `ProductOrderService::transition` menolak `expired` untuk pesanan yang sudah
  `isPaid()`; `expireStaleOrders()` membaca ulang baris sebelum bertindak.
- `PaymentService::applyToPayable` menulis `payment_status = refund_pending`
  (bukan `paid`) bila dana masuk untuk pesanan yang sudah ditutup, plus baris
  riwayat status yang menyatakan kewajiban refund.
- `createBookingPayment` hanya menolak booking `cancelled`; booking `completed`
  boleh ditagih selama masih ada sisa (`fullAmount`/`remainingAmount` tetap
  menolak nominal 0).

**Kenapa aman.** Semua penjagaan lama (monoton status, idempotensi
`payment_events.order_id`, rute kredensial per klinik) tidak diubah; yang
ditambah hanya cek setelah baris dikunci.

**Cek di sini.** Booking DP 200.000 dengan biaya rekam medis 747.500 → kartu
sisa tagihan turun menjadi 547.500 setelah DP dikonfirmasi, bukan tetap 747.500.

---

### 2.3 Pembatalan & refund (PB-H-01, PB-M-02)

**Sebelum.** Pembatalan hanya menulis `status = cancelled`. `payment_status` tetap
`paid`/`dp_paid`, tidak ada kewajiban refund yang tercatat di mana pun, dan refund
yang dicatat admin tidak memberi tahu pasien.

**Sesudah.** Satu aturan, satu implementasi:

```
booking.status = cancelled
dan paid_amount > 0:
    refund tercatat >= paid_amount  ->  payment_status = refunded
    selain itu                      ->  payment_status = refund_pending
```

- `Booking::syncRefundPaymentStatus()` (`app/Models/Booking.php`) adalah
  satu-satunya tempat aturan itu ditulis; `Booking::refundedTotal()`,
  `refundableAmount()`, dan `refundPaymentStatusFor()` menjadi helper-nya.
  `RefundService::refundedTotal()/refundableAmount()` hanya meneruskan ke model,
  jadi tidak ada dua hitungan refund.
- Pemanggil: pembatalan admin (`Admin/BookingController::cancel`), pembatalan
  pasien (`Api/BookingController::cancel`), pencatatan refund
  (`RefundService::record`, di transaksi yang sama dengan baris refund), dan
  **webhook yang masuk setelah booking dibatalkan**
  (`PaymentStatusService::applyTransactionStatus`).
- Webhook untuk booking `cancelled`: uang tetap dicatat di `paid_amount` dan
  `payment_events` (uangnya nyata), `remaining_amount` dipaksa 0 (booking batal
  tidak boleh punya sisa tagihan), status menjadi `refund_pending`, dan
  `Log::warning` menulis nominalnya. Sebelumnya jalur ini menulis
  `payment_status = paid` sehingga booking batal terlihat lunas.
- Status `refund_pending`/`refunded` tidak bisa diturunkan oleh event `pending`
  atau `failure` yang datang belakangan (keduanya masuk daftar status monoton).
- `Admin\PaymentController::confirm` **menolak** konfirmasi kas untuk booking
  batal (pesan `admin.payment_confirm_cancelled`): uang yang masuk setelah
  pembatalan adalah kewajiban refund, bukan pelunasan, dan menambah
  `paid_amount` di sana akan menghapus status `refund_pending`.
- Halaman pembayaran admin: status `refund_pending` dan `refunded` masuk daftar
  filter + label (`AdminStatus::paymentLabel`), dan `paymentStats()` sekarang
  mengembalikan `refund_due_count` + `refund_due_amount` (paid − refund tercatat
  untuk booking batal). Kartu **"Refund belum dicatat"** hanya muncul bila ada
  kewajibannya, dan menautkan ke filter `status=refund_pending`.
- `Admin\PaymentController::refund` tetap mengirim notifikasi "Refund telah
  dicatat" berisi nominal (PB-M-02).

**Catatan skema.** `bookings.payment_status` adalah **varchar(255)**, bukan enum,
sehingga nilai baru ini tidak memerlukan migrasi. Perbarui dokumen ini bila suatu
saat kolom tersebut diubah menjadi enum.

**Catatan aplikasi Android.** `GET /api/bookings` kini bisa mengembalikan
`payment_status` bernilai `refund_pending` atau `refunded` untuk booking yang
dibatalkan. Backend tidak mengubah bentuk respons; aplikasi cukup memetakan dua
nilai itu ke label sendiri (mis. "Refund diproses" / "Sudah direfund"). Bila
belum dipetakan, nilai mentahnya tampil apa adanya — tidak menyesatkan, tetapi
kurang ramah.

---

### 2.4 Keamanan & sanitasi error (PB-H-08, PB-H-09, PB-H-10)

- `bootstrap/app.php`: `trustProxies` tidak lagi `*`. Default sekarang
  `127.0.0.1` + `::1` (cukup untuk ngrok/nginx di mesin yang sama). Untuk
  deployment di belakang load balancer, isi `TRUSTED_PROXIES` di `.env` dengan
  daftar CIDR dipisah koma. Header `X-Forwarded-*` yang dipakai tetap
  `FOR|HOST|PORT|PROTO`.
- `Api\HealthController`: respons publik hanya berisi `status` + pesan umum per
  komponen (database, firebase, midtrans, storage, routes). Path absolut,
  pesan exception SQL, mode Midtrans, dan status key tidak lagi dikirim —
  semuanya masuk log. `register_direct` dikeluarkan dari daftar route wajib.
- `Api\PaymentController`: tujuh `catch` yang dulu mengembalikan
  `"Internal server error: " . $e->getMessage()` sekarang memakai
  `logServerError()` → klien menerima
  `"… Kode kesalahan: ERR-XXXXXXXX"` dan detailnya hanya di
  `storage/logs/laravel.log` dengan kode yang sama (bisa dicocokkan saat
  pengguna melapor).

---

### 2.5 Alur layanan, antrian, dan kompatibilitas aplikasi (PB-H-05, PB-M-04, PB-M-05, PB-M-07)

- `ServiceRequestService::accept` memvalidasi ulang slot lewat
  `DoctorScheduleService::validateSlot()` (cuti, hari praktik, jam di luar
  jadwal, slot sudah lewat) dan menolak klinik nonaktif sebelum booking dibuat.
- `advance()` dan `offer()` mengunci baris (`lockForUpdate`) sebelum memvalidasi
  transisi; notifikasi dikirim setelah commit, bukan di tengah transaksi.
- `QueueService::positionsFor()` menghitung posisi antrian satu query per
  kombinasi klinik+tanggal; `Api\BookingController::index/upcoming` memakainya
  (sebelumnya ±20 query hitung per halaman). `positionOf()` tetap ada untuk satu
  booking.
- `Api\PaymentController::parsePaymentOrderId` menerima `BOOKING-{id}` dua
  segmen (aplikasi versi lama) dan membuat id kanonik sendiri; validasi
  snap-token dilonggarkan ke `^BOOKING-\d+(-\d+)?$`.

**Catatan aplikasi Android.** Perbaikan di sisi klien masih disarankan
(`PaymentScreen.kt` jangan memakai `totalAmount` saat `dpAmount` kosong), tetapi
backend sekarang tidak lagi menolak 422 hanya karena format id.

### 2.6 Notifikasi webhook yang tidak bisa diproses (PB-M-03) & rotasi log

**Sebelum.** Setiap payload Midtrans memang sudah tersimpan di
`payment_notifications` (`PaymentService::logNotification`), termasuk yang
signature-nya gagal atau `order_id`-nya tidak dikenal. Masalahnya tidak ada satu
layar pun yang menampilkan baris itu: satu-satunya pembacaan adalah *jumlah*
notifikasi signature invalid 7 hari terakhir di halaman pengaturan pembayaran.
Jadi "webhook tak dikenal hilang tanpa jejak" sebenarnya "tersimpan tetapi tidak
pernah bisa ditindaklanjuti".

**Sesudah.**

- `Admin\PaymentSettingsController::index` mengirim `$notificationIssues`:
  `payment_notifications` yang `processed = false` dalam 30 hari terakhir
  (maksimal 15 baris), dibatasi ke klinik pemilik payment bila pengguna adalah
  admin klinik; Super Admin tanpa klinik terpilih melihat semuanya.
- Panel **"Notifikasi webhook bermasalah"** di `admin/payment-settings/index`
  menampilkan waktu, `order_id`, nominal, status transaksi, catatan proses, dan
  penanda signature invalid, plus form catatan + tombol **Tandai ditinjau**
  (`POST admin/payment-settings/notifications/{id}/review`).
- `resolveNotification()` hanya menulis `processed = true` + `processing_note`
  dan satu baris `AuditLog` (`payment_notification.reviewed`). **Tidak** mengubah
  status pembayaran — perubahan uang tetap hanya lewat webhook ulang dari
  Midtrans atau rekonsiliasi. Admin klinik lain mendapat 404.
- Efeknya: daftar hanya menyusut karena benar-benar ditangani, bukan karena
  tertutup data baru.
- `config/logging.php`: default `LOG_STACK` sekarang **`daily`** (14 hari), bukan
  `single`. Sebelumnya `laravel.log` tumbuh tanpa batas dan tidak pernah
dipangkas, sehingga disk VPS bisa penuh tanpa peringatan. Deployment yang
memakai kanal lain cukup mengisi `LOG_STACK`.

---

## 3. Cara memverifikasi

```bash
cd PetHeal_Backend
php -l app/Services/PaymentService.php        # dan berkas lain yang diubah
php artisan route:list --path=api             # aplikasi boot normal
php artisan test                              # lihat angka terakhir di README bagian ini
```

Pos pemeriksaan manual:

1. `/api/health` → 200, isi hanya status (tanpa path/`mode`/key).
2. Hapus hewan yang punya booking dari aplikasi → 409 `pet_has_history`.
3. Booking DP → konfirmasi kas → angka "sisa tagihan" di rekam medis turun.
4. Batalkan booking yang sudah dibayar → `payment_status` menjadi
   `refund_pending`, audit log `booking.refund_due`, kartu **Refund belum
   dicatat** muncul di `/admin/payments`, dan filter `status=refund_pending`
   menampilkan booking itu.
5. Catat refund sebagian di panel booking → status tetap `refund_pending`;
   refund penuh → `refunded` dan kartu refund hilang.
6. Konfirmasi kas untuk booking batal → ditolak dengan pesan (bukan menambah
   `paid_amount`).
7. Buka Riwayat booking di aplikasi → `queue_position` tetap benar, query
   hitung hanya satu per klinik/tanggal.
8. Kirim webhook dengan `order_id` tak dikenal → 403, baris muncul di panel
   "Notifikasi webhook bermasalah"; tekan **Tandai ditinjau** → baris hilang +
   `AuditLog` `payment_notification.reviewed`.

Test yang mengunci perilaku baru:

- `tests/Feature/BookingRefundLifecycleTest.php` (10 test): pembatalan admin &
  pasien, refund sebagian/penuh, konfirmasi kas ditolak, webhook settlement
  setelah pembatalan, event `pending` tidak menurunkan status refund, angka
  `refund_due` di laporan, label/filter halaman pembayaran.
- `tests/Feature/PaymentNotificationReviewTest.php` (2 test): payload webhook
  tak dikenal tersimpan → tampil di panel → ditinjau (audit) → hilang; admin
  klinik lain tidak bisa meninjau.

---

## 4. Yang sengaja ditunda (butuh migrasi / keputusan produk)

| Item | Kenapa ditunda | Langkah berikutnya |
|---|---|---|
| PB-H-04 unique slot booking | Perlu `ALTER TABLE` + pembersihan data lama; batch ini diputuskan **tanpa perubahan skema**, sehingga duplikasi slot tetap hanya dicegah di level aplikasi | (1) cari duplikat `(clinic_id, doctor_id, booking_date, booking_time, status in pending/confirmed)`, (2) bersihkan, (3) tambah generated column `active_slot_key` + unique index, (4) arahkan `Api\BookingController::store`, `Api\BookingController::reschedule`, dan `ServiceRequestService::accept` ke satu service pembuat booking, (5) petakan pelanggaran unique (SQLSTATE 23000) menjadi 409 `slot_conflict` |
| Satu ledger pembayaran (PB-M-01) | Menyatukan `bookings.paid_amount` (kas) dan `payments` (online) menyentuh dashboard, laporan, refund, dan struk sekaligus | Kerjakan bersama laporan keuangan, bukan terpisah |
| Kunci `pet_stats`/`payment_stats` | Kode sudah benar (lihat §5) | — |
| `APP_DEBUG=false` / `APP_ENV=production` saat deploy | `.env` sengaja tidak diubah agar konfigurasi lokal tidak berubah diam-diam | Set saat deploy; `LOG_STACK` sekarang default `daily` (14 hari) |

---

## 5. Koreksi terhadap laporan audit

- **PB-M-06 (cache statistik) bukan cacat.** `Admin\PaymentController::index`
  sudah memisahkan jalur: request dengan filter dihitung langsung, cache 5 menit
  hanya untuk tampilan tanpa filter. Tidak ada tabrakan kunci. Bagian temuan
  yang tetap berlaku hanya soal logging — kini default `LOG_STACK=daily`
  (`config/logging.php`), jadi tidak perlu mengubah `.env` lokal.
- **PB-M-04 (foto pet berupa string bebas) tidak bisa dieksploitasi.** Rule
  `nullable|image|mimes:…|max:4096` menolak nilai non-file, sehingga cabang
  `elseif ($request->filled('photo'))` tidak pernah tercapai (dead code).
- **`bookings.payment_status` bukan enum.** Dokumen versi sebelumnya menyebut
  penambahan `refund_pending` butuh migrasi enum. Kenyataannya kolomnya
  `varchar(255)` (`2026_03_02_000001_add_payment_to_bookings_table.php:16`), jadi
  nilai baru cukup ditulis dari aplikasi. Ini yang membuat PB-H-01 bisa selesai
  tanpa perubahan skema.
- **`payment_notifications` sudah ada sejak awal**, lengkap dengan `payload`,
  `signature_valid`, `processed`, dan `processing_note`. Kebutuhan PB-M-03 bukan
  tabel baru, melainkan permukaan baca + tindak lanjut (lihat §2.6). Tabel
  paralel (`payment_dead_letters`) tidak dibuat agar tidak ada dua tempat
  menyimpan payload yang sama.
- **PB-H-04 tidak punya jalur tulis ketiga.** `Admin/BookingController` tidak
  membuat booking manual (hanya confirm/complete/cancel), jadi yang perlu
  disatukan hanya `Api\BookingController::store`, `Api\BookingController::reschedule`,
  dan `ServiceRequestService::accept`.

---

## 6. Aturan yang tidak boleh dilanggar lagi

1. **Jangan** menambah `->delete()` pada model yang menjadi induk data klinis
   tanpa penjaga riwayat (booking, rekam medis, pets yang punya booking).
2. **Jangan** menulis `booking.paid_amount` tanpa memanggil
   `MedicalRecord::syncExtraPaymentForBooking()` di transaksi yang sama.
3. **Jangan** membuat intent pembayaran tanpa `Cache::lock('payment-intent-…')`.
4. **Jangan** mengembalikan `$e->getMessage()` dari controller API; pakai
   `logServerError()` (Payment) atau biarkan handler global di `bootstrap/app.php`.
5. **Jangan** mengembalikan `trustProxies(at: '*')`; tambahkan proxy lewat
   `TRUSTED_PROXIES`.
6. **Jangan** menambah endpoint yang mengembalikan `MedicalRecord` mentah tanpa
   `maskClinicalFields()`.
7. **Jangan** mengubah `bookings.payment_status` untuk booking `cancelled`
   secara manual. Selalu lewat `Booking::syncRefundPaymentStatus()` (dipanggil
   otomatis oleh pembatalan, `RefundService::record`, dan webhook).
8. **Jangan** menulis `paid_amount` pada booking `cancelled` dari form
   konfirmasi kas; `Admin\PaymentController::confirm` menolaknya dan uang
   setelah pembatalan wajib lewat panel refund.
9. **Jangan** membuat tabel/kolom penyimpan webhook kedua. Semua payload masuk
   `payment_notifications`; tindak lanjutnya di panel "Notifikasi webhook
   bermasalah", bukan dengan menulis status pembayaran dari aksi tinjau.
