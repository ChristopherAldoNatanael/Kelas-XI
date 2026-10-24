# FASE 5 — Admin Console Overhaul (Konsistensi Visual, Bilingual, Konsolidasi)

> Tanggal: 2026-10-22 (final verifikasi 2026-10-23)
> Scope: halaman admin per-klinik. Halaman super-admin dan halaman auth admin
> tidak dirombak pada fase ini, kecuali layout/admin shell yang dipakai bersama.
> Sumber: `docs/UIUX_ROADMAP.md` (Fase 5)

## 1. Masalah yang diselesaikan

Sebelum fase ini, tiap halaman admin membangun markup, status, dan copy-nya
sendiri. Akibatnya ada halaman duplikat, alur yang saling bertentangan, label
status mentah (`dp_pending`, `admin.password.change`), tanggal berformat
Inggris walau locale ID, dan pesan controller yang tetap berbahasa Indonesia
saat locale EN.

Fase ini menutup empat kelas masalah:

1. **Duplikasi halaman/alur** — profil klinik dan tim punya halaman sendiri
   walau datanya sudah ada di Pengaturan/Users.
2. **Bug fungsional** — filter pembayaran yang redirect saat `direction` tidak
   valid, overpayment manual, hapus pasien yang bisa menghapus riwayat,
   statistik rekam medis yang hanya menghitung satu halaman, dan cache
   dashboard yang bisa kehilangan key baru.
3. **Bilingual tidak konsisten** — label status, tanggal, flash message,
   invoice, dan PDF export masih hardcoded atau tidak mengikuti locale.
4. **Dead code** — route/method/view/status-map yang tidak lagi dipakai.

## 2. Keputusan konsolidasi

| Sebelum | Sesudah | Alasan |
| --- | --- | --- |
| `GET /admin/clinic-profile` + `PUT /admin/clinic-profile` | redirect ke `admin.settings?tab=klinik`; update lewat `PUT /admin/settings/clinic` | satu sumber data klinik, tidak ada dua form yang bisa berbeda |
| `admin.settings?tab=tim` | dihapus; query lama fallback ke tab akun | daftar admin sudah ada di `admin.users` dengan filter peran |
| Menu "Pasien" | menu "Pengguna" | halaman yang sama menampilkan pasien + admin klinik |
| Booking list dan schedule board | tetap satu modul, dipisah tab | tidak perlu dua menu untuk data yang sama |
| `GET /admin/services/{id}` | dihapus; edit lewat `/admin/services/{id}/edit` | tidak ada view show yang pernah dipakai |
| `payments/{id}` | tetap redirect ke detail booking `#pembayaran` | satu booking = satu catatan pembayaran |

Route profile lama dipertahankan sebagai redirect untuk bookmark, bukan
sebagai halaman kedua.

## 3. Perubahan per modul

### 3.1 Dashboard

- Cache payload dashboard dinaikkan ke `dashboard_stats_v3` agar cache lama
  tidak mencapai view dengan key baru.
- `dp_pending` masuk ke kartu pembayaran dan grafik status.
- Outstanding count dan daftar outstanding memakai kriteria yang sama
  (non-cancelled, `remaining_amount > 0`).
- Label chart, tanggal, dan angka "juta" mengikuti locale.
- Variabel view yang tidak dipakai dihapus.
- Foto dokter memakai `photo_url`, bukan `asset('storage/...')`.

### 3.2 Booking dan schedule board

- Tab daftar/papan tetap satu halaman.
- Badge status memakai `AdminStatus` (label + tone).
- Tanggal dibuat/confirmed/completed/reminder dan tanggal follow-up mengikuti
  locale.
- Foto pet memakai `photo_url`, fallback inisial tetap ada.
- Status `dp_pending` tampil benar di daftar dan detail booking.

### 3.3 Payments

- `PaymentController::validateFilters()` tidak lagi memvalidasi `sort` dan
  `direction`; `applySort()` sudah melakukan whitelist + fallback
  `updated_at desc`, jadi URL lama/tampered tetap 200, bukan redirect/500.
- Overpayment manual ditolak di backend dengan pesan yang mengikuti locale.
- Konfirmasi manual memakai transaksi + row lock; status tidak pernah
  melebihi total tagihan.
- Filter method memakai nama metode pembayaran yang benar-benar tersimpan,
  bukan pseudo-type lama.
- Export PDF mengikuti filter status/type/method/search/date.
- Invoice PDF dan flash message bilingual; nominal memakai pemisah ribuan
  sesuai locale.
- Statistik outstanding tidak memasukkan booking cancelled.

### 3.4 Rekam medis

- Statistik dihitung dari seluruh hasil filter, bukan hanya baris di halaman
  pertama.
- Label status extra payment memakai `AdminStatus`.
- Tanggal, foto pet/dokter, dan nominal mengikuti locale.
- Export PDF/CSV memakai label dan format tanggal yang sama dengan UI.

### 3.5 Users

- Satu halaman untuk pasien dan admin klinik, dengan tab peran
  (`all`, `patient`, `admin`).
- Form tambah pasien hanya muncul untuk tab pasien; akun admin tidak dibuat
  dari halaman ini.
- Foto memakai `photo_url`, tanggal lokal, dan statistik dihitung dari query
  dasar yang sama dengan daftar.
- Penghapusan:
  - akun admin tidak bisa dihapus dari halaman Users;
  - akun sendiri tidak bisa dihapus (pesan khusus);
  - pasien yang punya riwayat booking tidak dihapus agar invoice/rekam medis
    tetap utuh;
  - pasien tanpa riwayat dihapus dalam transaksi bersama pets, device token,
    dan token akses.

### 3.6 Dokter, jadwal, dan layanan

- Foto dokter memakai `photo_url`; fallback placeholder dipertahankan.
- Day picker tetap valid untuk create/edit, termasuk copy EN.
- Pengecualian jadwal (cuti/ubah jam) memakai flash message bilingual.
- Import layanan: pesan sukses/gagal dan laporan error bilingual; import tetap
  terikat ke klinik aktif.
- CRUD layanan/dokter memakai flash message dari file bahasa, bukan string
  Indonesia di controller.

### 3.7 Pengaturan dan layout

- Tab Pengaturan: akun, keamanan, klinik. Tab tim dihapus.
- Form klinik pindah ke tab klinik; slug dan status tetap tidak bisa diubah
  oleh clinic admin.
- OTP ganti password dan pesan validasinya bilingual.
- Layout admin: skip link, label navigasi, command palette, pencarian global,
  theme toggle, dan toast memakai key `admin.*`; tidak ada copy ID hardcoded.

## 4. Satu sumber label status

`app/Support/AdminStatus.php` menjadi satu-satunya pemetaan label/tone untuk:

- status booking (`pending`, `confirmed`, `completed`, `cancelled`);
- status pembayaran (`unpaid`, `pending`, `dp_pending`, `dp_paid`, `partial`,
  `paid`, `failed`);
- tipe pembayaran (`dp`, `full`);
- status extra payment rekam medis;
- label aksi audit log (termasuk `admin.password.change`).

Dengan begitu halaman daftar, detail, dashboard, export, dan invoice tidak
bisa lagi menampilkan key mentah atau warna badge yang berbeda untuk status
yang sama.

## 5. File utama yang disentuh

- **Baru:** `app/Support/AdminStatus.php`,
  `resources/views/components/admin/*`,
  `resources/views/vendor/pagination/petheal.blade.php`,
  `resources/lang/{id,en}/admin.php`,
  `tests/Feature/AdminConsoleSmokeTest.php`,
  `tests/Feature/AdminFilterConsistencyTest.php`.
- **Diubah:** controller `Admin/{AdminAuth,Clinic,Dashboard,Doctor,
  DoctorSchedule,MedicalRecord,NotificationSettings,Payment,Service,User}`,
  `app/Services/PaymentStatusService.php`, `routes/web.php`,
  seluruh view `resources/views/admin/**` yang masuk scope,
  `resources/views/layouts/admin.blade.php`,
  `resources/lang/{id,en}/{auth,common,dashboard}.php`,
  `resources/css/app.css`, dan test `ClinicProfileTest`.
- **Dihapus:** `resources/views/admin/settings/partials/tim.blade.php` dan
  route/method show layanan yang tidak terpakai.
- **Tidak diubah:** kontrak API, skema database, dan aplikasi Android.

## 6. Verifikasi final

- `php artisan test`: **173 passed (985 assertions)**.
  - Termasuk `AdminConsoleSmokeTest` 7 test / 137 assertion: seluruh halaman
    admin ID+EN, pagination, label `dp_pending`, guard hapus user, overpayment,
    PDF invoice/export dua locale, dan label aksi audit.
  - `ClinicProfileTest` 9 test: redirect profil lama, update via Settings,
    isolasi klinik, upload logo, validasi warna, dan perilaku super admin.
  - `AdminFilterConsistencyTest` 6 test / 37 assertion: isi PDF export untuk
    filter tanpa `paid_amount`, kartu ringkasan mengikuti filter, fallback
    filter tak dikenal, filter tanggal dashboard pada kartu dan daftar,
    pembayaran langsung terlihat pada dashboard berdate-range, serta CSV rekam
    medis melewati batas 200 baris milik PDF.
- `php artisan view:cache`: sukses.
- `npm run build`: sukses — `app-B1yx_PJM.css` 109.22 kB,
  `app-C-6D00GQ.js` 258.46 kB.
- `php artisan route:list --path=admin`: 80 route; tidak ada route show
  layanan yang yatim.
- Parity bahasa: `admin` 687/687 key, `dashboard` 96/96 key, tanpa duplikat.
- Scan pemakaian `__('admin.*')`/`__('dashboard.*')` di app + view: 0 key
  hilang (hanya `admin.role_label_*` yang dibangun dinamis).
- Smoke HTTP nyata lewat ngrok, login `admin@petheal.com`:
  **21 halaman admin x ID/EN = 200**, tanpa `Undefined variable`, `SQLSTATE`,
  atau halaman error.
- `/api/health` 200 dan `/admin/login` 200 (lokal + ngrok).
- `settings?tab=tim` mengembalikan 200 (fallback tab akun), bukan 404/500.

## 7. Sisa / catatan

- Dashboard tanpa filter tetap memakai cache 5 menit dan diinvalidate lewat
  `PaymentStatusService`. Dashboard dengan rentang tanggal sengaja tidak
  di-cache: key per rentang tidak bisa dienumerasi, jadi data dihitung langsung
  agar pembayaran baru tidak tertinggal di cache.
- Kartu statistik dan daftar pembayaran sama-sama mengikuti filter aktif.
  Export PDF memakai query yang sama, termasuk filter status yang tidak punya
  `paid_amount` (`unpaid`, `dp_pending`).
- Deskripsi audit log adalah teks historis yang disimpan saat aksi terjadi;
  label aksinya sudah diterjemahkan, tetapi isi deskripsi lama tidak
  diterjemahkan ulang. Strategi berbasis key belum diterapkan.
- Belum ada uji visual browser/emulator pada fase ini; verifikasi UI dilakukan
  lewat render Blade, HTTP smoke, dan test suite.
