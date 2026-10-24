# UI/UX ROADMAP — PetHeal

> **Sumber kebenaran untuk pekerjaan UI/UX.** Jika ada ide baru di tengah sesi,
> jangan langsung dikerjakan. Tambahkan ke bagian **Backlog** di bawah, lalu
> selesaikan fase yang sedang berjalan. Dokumen ini mengalahkan rekomendasi
> model/AI mana pun.

## 0. Aturan main (wajib dipatuhi agent)

1. **Kerjakan berurutan.** Jangan lompat fase. Satu fase harus punya bukti
   verifikasi sebelum fase berikutnya dimulai.
2. **Tidak ada data palsu.** Semua angka di UI harus berasal dari query/response
   nyata. Jika pembanding belum ada, tampilkan `No baseline` — bukan persentase
   karangan.
3. **Tidak ada dead code.** Jangan tinggalkan tombol tanpa aksi, route tanpa
   pemakai, import tak terpakai, atau komponen yang hanya dipakai di preview.
4. **Tidak ada flow baru yang setengah jadi.** Jika sebuah fitur butuh backend
   baru, selesaikan kontrak API + validasi + UI + error state sekaligus.
5. **Dependency:** gunakan stack yang sudah ada. Jangan tambah library baru
   untuk hal yang bisa diselesaikan dengan Compose/Canvas/Tailwind. Dependency
   berbayar wajib tanya user.
6. **Warna & copy:** warna dari token (`ui/theme/Color.kt` untuk Android,
   `--color-primary` untuk admin). Jangan menambah hex baru di layar. Jangan
   menulis literal "PetHeal" di UI yang seharusnya memakai `clinic_name`.
7. **Aksesibilitas minimum:** target sentuh 44dp, `contentDescription` untuk
   elemen non-teks yang penting, focus ring terlihat, dan kontras teks wajar.
8. **Setiap fase selesai:** update `docs/PROGRESS.md` + buat/ubah file di
   `docs/phases/`. Sertakan file yang disentuh, cara verifikasi, dan next step.
9. **Jangan commit/push** kecuali diminta user.

## 1. Status fase

| Fase | Nama | Status | Bukti |
| --- | --- | --- | --- |
| 1 | Trust & Polish | **Selesai** — 2026-10-20 | `docs/phases/PHASE_1_TRUST_POLISH.md` |
| 2 | Core Loop Visibility | **Selesai (scope health)** — 2026-10-20 | `docs/phases/PHASE_2_CORE_LOOP.md` |
| 3 | Operational Depth | **Selesai** — 2026-10-20 | `docs/phases/PHASE_3_OPERATIONAL_DEPTH.md` |
| 4 | Operational Trust & Proof | **Selesai** — 2026-10-21 | `docs/phases/PHASE_4_OPERATIONAL_TRUST.md` |
| 5 | Admin Console Overhaul | **Selesai** — 2026-10-22 | `docs/phases/PHASE_5_ADMIN_CONSOLE.md` |

> Fase 4 lama (**Scale & Monetization**: membership, SaaS billing, chat/telemedicine)
> dipindah utuh ke **Backlog** (bagian 6) karena belum ada keputusan produk.
> Fase 4 sekarang berisi pekerjaan operasional + bukti pelanggan yang bisa
> diselesaikan tanpa pricing, subscription, atau mock UI.

> Fase 2 sengaja dibatasi pada **visibilitas kesehatan hewan** (weight trend +
> status vaksinasi). Booking slot, invoice center, dan schedule board dipindah
> ke Fase 3 agar tidak ada alur setengah jadi. Ini keputusan sadar, bukan
> kekurangan yang tidak tercatat.

## 2. Fase 1 — Trust & Polish

**Tujuan:** aplikasi terlihat seperti produk yang dirawat, bukan demo.

**Scope yang dikerjakan**

- Admin shell responsif: drawer mobile, backdrop, tombol buka/tutup, skip link,
  `aria-current`, focus ring, dan preferensi collapse yang tersimpan.
- Build asset produksi: Tailwind v4 + Vite, Chart.js dibundel. CDN dihapus dari
  layout admin.
- Dashboard jujur: perbandingan MTD vs periode yang sama bulan lalu, hari ini
  vs kemarin, collection rate nyata, dan Action Center dari data booking.
  Angka palsu (`+12.4%`, `+5.1%`, `75%`, `0.42ms`, `65.2%`) dihapus.
- Tabel booking: header dan sel kembali sejajar (9 kolom), harga menyatu dengan
  layanan, foto memakai accessor `photo_url`, dan tabel punya lebar minimum
  agar tidak rusak di layar kecil.
- Error page branded: 404, 419, 500, 503.
- Android: channel notifikasi per kategori, ID notifikasi stabil, dan deep link
  `petheal://open/...` untuk booking/record/pet/doctor/notifications.

**Non-goal fase ini:** fitur bisnis baru, dark mode Android, Room/WorkManager,
redesign total dashboard, atau mengubah struktur database.

**Definisi selesai**

- Tidak ada `cdn.tailwindcss.com` / `cdn.jsdelivr.net` di layout admin.
- `npm run build` sukses dan `@vite` memuat asset hasil build.
- Semua route admin GET utama merespons 200 tanpa error Blade.
- Dashboard tidak menampilkan persentase hardcoded.
- `./gradlew assembleDebug` sukses.
- Error page custom tampil untuk 404.

## 3. Fase 2 — Core Loop Visibility

**Tujuan:** pemilik hewan bisa melihat perkembangan kesehatan tanpa membuka
rekam medis satu per satu.

**Scope yang dikerjakan**

- Grafik tren berat badan di Pet Detail (8 pengukuran terakhir), digambar
  dengan Compose Canvas — tanpa library chart baru.
- Badge status vaksinasi: `Terlambat`, `Jatuh tempo` (≤30 hari), `Terjadwal`.
- Empty state tetap aman: grafik hanya muncul jika ada ≥2 pengukuran bertanggal.

**Non-goal fase ini:** booking slot picker, invoice center, pull-to-refresh,
sinkronisasi offline, dan reminder otomatis baru.

**Definisi selesai**

- Grafik tidak crash saat data kosong/berat sama.
- Grafik punya deskripsi aksesibilitas dan label tanggal.
- Status vaksinasi memakai `next_due_date` dari API, bukan tanggal tebakan.
- `./gradlew assembleDebug` sukses.

## 4. Fase 3 - Operational Depth (selesai 2026-10-20)

**Urutan wajib di dalam fase ini:**

1. **Booking slot picker** — tampilkan slot tersedia per dokter, cegah double
   book, dan tangani 422 dari API tanpa menghapus pilihan user.
2. **Invoice/receipt center** — satu tempat untuk tagihan belum dibayar, DP,
   lunas, dan unduh bukti. Reuse endpoint payment yang ada; jangan duplikasi
   perhitungan.
3. **Admin schedule board** — tampilan hari ini per dokter/jam dengan aksi
   konfirmasi/selesai. Wajib clinic-scoped dan menghormati permission.
4. **Global command palette** — pencarian booking/pet/owner dengan endpoint
   yang dibatasi `clinic_id`; jangan pernah mengembalikan data lintas klinik.

**Non-goal:** chat realtime/telemedicine, notifikasi inbox baru, dan perubahan
skema database besar. Jika salah satu dibutuhkan, naikkan ke Fase 4 dan minta
persetujuan user.

## 5. Fase 4 — Operational Trust & Proof (2026-10-21)

**Tujuan:** jadwal klinik bisa dipercaya (cuti dan jam khusus benar-benar
tercermin di slot), pelanggan punya bukti pembayaran resmi berformat PDF, dan
admin tahu booking mana yang sudah diingatkan.

**Urutan wajib di dalam fase ini:**

1. **Jadwal dokter, cuti, dan pengecualian per tanggal** — tabel
   `doctor_schedule_exceptions`, UI admin (papan jadwal + halaman jadwal
   dokter), validasi slot di API create/reschedule, dan state slot picker
   Android (`cuti`, `tidak praktik`, `penuh`, `jam lewat`). Booking lama
   **tidak** dibatalkan otomatis; admin hanya diberi peringatan.
2. **PDF bukti pembayaran** — renderer bukti yang sama dipakai untuk PNG dan
   PDF A4 vector, simpan ke `Downloads/PetHeal`, bagikan lewat FileProvider.
3. **Status reminder** — kolom `bookings.last_reminder_at`, diisi hanya saat
   pengiriman berhasil, remover otomatis tidak dobel di hari yang sama, dan
   statusnya terlihat di detail booking admin.

**Non-goal fase ini:** membership/promo, harga paket, subscription, SaaS
billing, chat/telemedicine, auto-cancel booking saat dokter cuti, PWA/public
website, dan data mock.

**Definisi selesai:**

- `GET /doctors/{id}/slots` mengembalikan metadata cuti/jam khusus dan slot
  mengikuti jadwal efektif.
- Create booking & reschedule menolak cuti/jam di luar jadwal dengan `422
  slot_unavailable` (bentrok tetap `409 slot_conflict`).
- Slot picker Android membedakan cuti, tidak praktik, penuh, dan jam lewat.
- Bukti PDF A4 bisa disimpan dan dibagikan dari sheet bukti.
- Detail booking admin menampilkan status reminder; command `reminders:send`
  tidak mengirim dua kali pada hari yang sama.
- `php artisan test` hijau dan `./gradlew assembleDebug` sukses.

## 5.1 Fase 5 — Admin Console Overhaul (selesai 2026-10-22)

**Tujuan:** seluruh halaman konsol admin (admin klinik + super admin) tampil
konsisten dan benar-benar bilingual, tanpa mengubah alur atau kontrak apa pun.

**Scope yang dikerjakan:**

- Komponen bersama `x-admin.{page-header,panel,stat-card,badge,empty-state}`
  dipakai seluruh halaman admin; primitif form/tabel (`admin-input`,
  `admin-btn-*`, `admin-table-head`, ...) dipusatkan di `resources/css/app.css`.
- File bahasa baru `resources/lang/{id,en}/admin.php`; copy Indonesia yang
  tadinya tertinggal di dalam Blade dipindah ke file bahasa.
- Pagination admin memakai view `pagination::petheal` (view Laravel bawaan
  terlihat asing di konsol admin dan sempat 500 saat data melewati satu
  halaman).

**Non-goal fase ini:** mengubah alur kerja, kontrak API, skema database, dan
tampilan aplikasi Android.

**Definisi selesai:**

- Semua route admin GET merespons 200 pada locale ID **dan** EN.
- Tidak ada kunci terjemah mentah (`admin.xxx`) yang tampil di HTML.
- `npm run build` deterministik dan mencakup seluruh kelas yang dipakai view.
- `php artisan test` hijau.

## 6. Backlog (ide yang ditunda)

Tambahkan ide di sini, jangan langsung implementasi:

- **Scale & Monetization (dulu Fase 4):** membership/promo engine, SaaS
  plan/billing multi-klinik, chat/telemedicine, PWA/public website, analytics
  lintas klinik. Belum boleh dibuat sebagian atau sebagai mock UI.
- Pull-to-refresh di daftar Booking/Pets/Notifications (butuh cek versi
  Material3 atau implementasi nested scroll).
- Dark mode Android (butuh audit token + kontras seluruh layar).
- `values-en/strings.xml` untuk lokalisasi penuh.
- Appointment reminder H-1 dengan WorkManager.
- Export laporan admin ke Excel.
- Grafik berat badan dengan rentang 3/6/12 bulan.

## 7. Peta file utama

| Area | File |
| --- | --- |
| Admin shell | `PetHeal_Backend/resources/views/layouts/admin.blade.php` |
| Admin design system | `PetHeal_Backend/resources/css/app.css` |
| Komponen admin | `PetHeal_Backend/resources/views/components/admin/*.blade.php` |
| Copy admin (ID/EN) | `PetHeal_Backend/resources/lang/{id,en}/admin.php` |
| Pagination admin | `PetHeal_Backend/resources/views/vendor/pagination/petheal.blade.php` |
| Admin JS/Chart | `PetHeal_Backend/resources/js/app.js` |
| Dashboard | `.../Admin/DashboardController.php`, `.../admin/dashboard.blade.php` |
| Booking table | `.../admin/bookings/index.blade.php` |
| Error pages | `PetHeal_Backend/resources/views/errors/*` |
| Android notifikasi | `.../service/PetHealFirebaseService.kt`, `MainActivity.kt`, `AndroidManifest.xml`, `res/values/strings.xml` |
| Android health | `.../ui/screens/pet/PetsScreen.kt`, `.../ui/components/WeightTrendChart.kt` |
| Jadwal efektif dokter | `.../app/Services/DoctorScheduleService.php`, `.../app/Models/DoctorScheduleException.php` |
| Admin cuti dokter | `.../app/Http/Controllers/Admin/DoctorScheduleController.php`, `.../resources/views/admin/doctors/schedule.blade.php`, `.../resources/views/admin/schedule/index.blade.php` |
| Slot states Android | `PetHeal_Android/.../util/SlotDayState.kt`, `.../ui/components/BookingSlotPicker.kt` |
| PDF bukti pembayaran | `.../ui/components/PaymentReceiptImage.kt`, `PaymentReceiptSheet.kt` |

## 8. Cara verifikasi cepat

```powershell
# Backend
cd PetHeal_Backend
npm run build
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000

# Android
cd ..\PetHeal_Android
.\gradlew.bat assembleDebug
```

Smoke test admin (setelah login):

```powershell
$routes = @('/admin','/admin/users','/admin/bookings','/admin/schedule',
  '/admin/doctors','/admin/services','/admin/medical-records','/admin/payments',
  '/admin/clinics','/admin/join-requests','/admin/clinic-profile',
  '/admin/audit-logs','/admin/settings','/admin/notification-settings')
```

Semua harus `200` dan tidak mengandung `Undefined variable`/`SQLSTATE`.
