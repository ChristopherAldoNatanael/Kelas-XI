# Audit Menyeluruh PetHeal

Tanggal: 28 November 2026  
Sifat: audit statis, crawl browser, accessibility scan, dan pemeriksaan database.  
Bukan penetration test penuh dan bukan jaminan bebas celah.

## Ruang lingkup

- 61 controller.
- 133 Blade view.
- 343 route.
- 42 halaman GET admin di-crawl pada desktop 1440x960.
- 42 halaman admin diuji pada mobile 390x844.
- 11 halaman utama dianalisis dengan axe-core WCAG 2.1 AA.
- Pemeriksaan integritas data lintas klinik pada tabel inti.
- Pemeriksaan queue, scheduler, index database, aset, dan log.

## Ringkasan

| Prioritas | Jumlah temuan |
|---|---:|
| P0 — darurat | 0 |
| P1 — harus segera | 5 |
| P2 — penting | 12 |
| P3 — perbaikan bertahap | 9 |

Tidak ditemukan data medis lintas klinik yang bocor pada pemeriksaan
integritas database. Masalah utama ada pada aksesibilitas, operasional queue,
header keamanan, dan kesiapan rilis.

---

# P1 — harus segera diperbaiki

## P1.1 Kontras tombol utama gagal WCAG

Bukti axe:
- 23 node pada dashboard;
- 23 node pada booking;
- 21 node pada rekam medis;
- 27 node pada pengguna dan dokter.

Penyebab:

```css
.admin-btn-primary {
    background: #10b981;
    color: #ffffff;
}
```

Rasio kontras putih di `#10b981` hanya sekitar **2,5:1**, jauh di bawah minimum
4,5:1 untuk teks normal.

Perbaikan:
- gunakan `#047857` atau `#059669` untuk latar tombol;
- pertahankan putih untuk teks;
- periksa juga badge, tautan `text-primary`, dan progress bar.

## P1.2 Queue worker tidak berjalan, 5 job FCM tertahan

Bukti:

```text
queue=database
jobs=5
failed_jobs=0
overdue_jobs=5
```

Job tertua tersedia sejak **9 Juni 2026** dan sudah melewati waktu saat ini
(28 November 2026), dengan `attempts=0`.

Dampak:
- notifikasi push tidak terkirim;
- pengingat dan informasi pembayaran bisa terlambat;
- antrean job terus menumpuk.

Perbaikan:
- jalankan worker permanen (`queue:work --tries=3 --timeout=90`);
- gunakan Supervisor atau systemd;
- tambahkan health check jumlah job tertua;
- bersihkan atau proses ulang 5 job lama setelah isi payload diperiksa.

## P1.3 Header keamanan HTTP belum global

Tidak ditemukan middleware yang memasang:

- `Content-Security-Policy`;
- `Strict-Transport-Security`;
- `X-Frame-Options` atau `frame-ancestors`;
- `Referrer-Policy`;
- `Permissions-Policy`.

`X-Content-Type-Options: nosniff` hanya dipasang manual pada endpoint unduhan
PDP, bukan seluruh aplikasi.

Perbaikan:
- buat middleware `SecurityHeaders`;
- terapkan pada grup web dan API;
- mulai CSP dengan mode report-only karena masih ada 29 view berisi script
  inline dan 59 atribut `onclick`.

## P1.4 Verifikasi rilis belum lengkap setelah P4.6–P4.10

Full suite terakhir yang tercatat adalah **652 passed / 4.044 assertions**
pada titik P4.5.

Setelah itu P4.6 sampai P4.10 hanya memakai targeted test:
- berkas jawaban PDP;
- performa mobile;
- performa global;
- walk-in/darurat;
- ruang klinis dan sidebar.

Belum ada bukti full regression setelah seluruh perubahan tersebut.

Perbaikan:
- jalankan full suite sekali sebelum staging/UAT;
- simpan log hasil;
- jangan menyatakan production-ready sebelum hasilnya 0 failure.

## P1.5 Tabel klinis tidak memakai semantik tab yang valid

`resources/views/admin/clinical/index.blade.php` memakai:

```html
<div role="tablist">
    <button type="button" data-clinical-tab="...">
```

Masalah:
- tombol tidak memiliki `role="tab"`;
- tidak ada `aria-selected`;
- tidak ada `aria-controls`;
- panel tidak memiliki `role="tabpanel"`;
- navigasi keyboard panah kiri/kanan tidak ada.

Axe memberi temuan **critical: aria-required-children**.

Perbaikan:
- lengkapi semantik tab; atau
- turunkan `role="tablist"` dan jadikan tombol filter biasa dengan `aria-pressed`.

---

# P2 — penting

## P2.1 Filter antrian tidak memiliki label

Axe menemukan:
- `critical: label`;
- `critical: select-name`.

Lokasi:
- input tanggal `name="date"`;
- select dokter `name="doctor_id"` pada halaman antrian.

Dampak: pembaca layar hanya membacakan “edit teks” dan “combobox” tanpa nama.

Perbaikan: tambahkan `<label class="sr-only">` atau `aria-label`.

## P2.2 Target sentuh terlalu kecil

Diukur pada viewport mobile 390px:

- tombol menu: **24x36px**;
- tombol hapus/block: **32x32px**;
- tombol chevron: tinggi **32px**;
- tombol Terapkan: tinggi **34px**;
- tombol Salin: tinggi **27px**.

Target nyaman adalah minimal 44x44px.

## P2.3 Gambar tanpa teks alternatif

Ditemukan minimal dua gambar tanpa `alt`:

- foto dokter di `admin/doctors/index.blade.php`;
- foto hewan di `admin/medical-records/index.blade.php`.

Perbaikan: isi `alt` dengan nama dokter/hewan, atau `alt=""` bila murni dekoratif.

## P2.4 Nama aksesibel command palette tidak cocok

Axe menemukan `label-content-name-mismatch` pada `#commandPaletteTrigger`.

Label aksesibel berbunyi “Buka pencarian global”, sedangkan teks yang terlihat
adalah “Cari”. Nama aksesibel harus memuat teks yang terlihat.

## P2.5 Ruang Klinis tidak punya pagination

Semua daftar dibatasi 50 baris:

```php
->limit(50)->get();
```

Data ke-51 dan seterusnya tidak bisa diakses dari UI. Tidak ada tombol
“lihat semua” atau pagination.

Perbaikan:
- gunakan pagination per tab;
- atau sediakan halaman arsip per modul.

## P2.6 Format tanggal tidak konsisten

Ditemukan empat pola dalam view:

```text
d M Y H:i
d/m/Y H:i
d/m/Y
Y-m-d
```

Contoh: tabel booking memakai `d M Y`, sedangkan rekam medis memakai
`d/m/Y H:i`.

Perbaikan: satu helper tanggal Indonesia, misalnya `format_tanggal()` dan
`format_tanggal_waktu()`.

## P2.7 Sidebar terang mengabaikan mode gelap

Sidebar sekarang selalu hijau terang. Saat tema gelap aktif, sidebar tetap
terang dan menimbulkan kontras yang tidak konsisten dengan seluruh isi halaman.

Perbaikan:
- sediakan varian dark yang tetap hijau tetapi lebih redup; atau
- nonaktifkan mode gelap untuk area admin sampai varian sidebar tersedia.

## P2.8 Logo terlalu besar untuk ukuran tampil

`public/logo.png` berukuran:

```text
1600x567 px
351 KB
```

Di sidebar logo hanya tampil setinggi 22–24px dengan atribut `width=158`.
Browser tetap mengunduh 351 KB.

Perbaikan:
- buat versi 320px dan 640px;
- gunakan `srcset`;
- atau konversi ke WebP.

## P2.9 Tidak ada policy/ability layer

Tidak ditemukan folder `app/Policies`.

Otorisasi dilakukan manual melalui:
- middleware role;
- `currentClinicId()`;
- query scope.

Saat ini pemeriksaan database bersih, tetapi tidak ada satu tempat yang
menjamin aturan seperti “admin klinik tidak boleh finalisasi rekam medis”.
Risiko meningkat ketika route baru ditambahkan.

Perbaikan: tambahkan Policy untuk MedicalRecord, Booking, Payment, dan
PrivacyRequest; panggil `authorize()` di controller.

## P2.10 Debug dan environment template berisiko

`.env.example` memakai:

```text
APP_ENV=local
APP_DEBUG=true
```

Nilai ini aman untuk lokal, tetapi berbahaya jika disalin ke server produksi.
Deployment checklist harus menolak `APP_DEBUG=true`.

## P2.11 Belum ada UAT browser/device formal

Belum ada bukti:
- UAT browser Chrome, Edge, Firefox, Safari;
- UAT perangkat Android nyata;
- UAT alur walk-in/darurat oleh petugas;
- UAT ruang klinis oleh dokter.

Crawl otomatis tidak menggantikan UAT manusia.

## P2.12 Tidak ada pengukuran query per halaman

Tidak ditemukan APM, query counter, atau threshold performa. Beberapa halaman
memuat banyak relasi dan agregat, tetapi belum ada bukti jumlah query per
request.

Perbaikan:
- tambahkan middleware query counter untuk staging;
- ukur dashboard, rekam medis, dan pembayaran;
- tetapkan batas, misalnya maksimal 40 query per halaman.

---

# P3 — perbaikan bertahap

## P3.1 Banyak teks sangat kecil

Ditemukan:

```text
203 penggunaan text-[10px]
242 penggunaan text-[11px]
  8 penggunaan text-[9px]
```

Sebagian adalah label sekunder, tetapi ukuran 9–10px sulit dibaca pada layar
laptop biasa dan sering menjadi penyebab kontras rendah.

## P3.2 Banyak handler dan script inline

- 59 atribut `onclick`, `onchange`, atau sejenis;
- 29 view berisi `<script>`;
- 72 atribut `style` inline.

Ini menyulitkan CSP yang ketat dan memperbesar risiko regresi.

## P3.3 Logika konfirmasi tidak konsisten

Sebagian aksi memakai `confirm()` bawaan browser:

```text
clinical/index.blade.php
join-requests.blade.php
```

Halaman lain memakai modal kustom. Pengalaman pengguna dan gaya visual menjadi
tidak seragam.

## P3.4 Chart pendapatan memakai batang sangat pucat

Batang chart memakai `bg-emerald-500/25` pada latar putih. Nilai kecil hampir
tidak terlihat. Naikkan opasitas atau gunakan warna solid yang lebih gelap.

## P3.5 37 notifikasi tidak terbaca, sebagian sejak Oktober

Data saat audit:

```text
notifications total=39
unread=37
oldest unread=2026-10-02
```

Kemungkinan besar data QA, tetapi produksi membutuhkan kebijakan retensi dan
pembersihan notifikasi lama.

## P3.6 Log lama besar dan berisi stack trace

`storage/logs/laravel.log` berukuran **6,6 MB** dan masih menyimpan error lama.
Pastikan server memakai log harian dengan retensi terbatas dan tidak dapat
diakses publik.

## P3.7 Beberapa teks UI masih hardcoded

Ruang Klinis memakai teks Indonesia langsung di Blade dan controller, bukan
translation key. Ini membuat aplikasi tidak konsisten dengan halaman lain yang
sudah mendukung `id`/`en`.

## P3.8 Log upload dokter terlalu berisik

`DoctorController` mencatat `Log::info` untuk setiap percobaan upload foto.
Informasi ini bukan error dan menambah ukuran log.

## P3.9 Belum ada bukti performa aset produksi

Ukuran build saat audit:

```text
app.js   277 KB
app.css  144 KB
jsQR     128 KB
```

Belum ada pengukuran:
- Largest Contentful Paint;
- interaction latency;
- cache hit ratio;
- perilaku pada koneksi 3G.

---

# Hasil yang sudah baik

- Semua 42 halaman GET admin yang diuji memberi status sesuai peran.
- Tidak ada route `route('...')` yang menunjuk nama route tidak terdefinisi.
- Tidak ada overflow horizontal pada 42 halaman mobile 390px.
- Sidebar lulus pemeriksaan kontras axe.
- Integritas lintas klinik bersih:
  - booking tanpa klinik: 0;
  - rekam medis tanpa klinik: 0;
  - booking dengan hewan milik pemilik lain: 0;
  - dokter lintas klinik pada booking: 0;
  - layanan lintas klinik pada booking: 0;
  - rekam medis dengan dokter lintas klinik: 0;
  - resep dengan obat lintas klinik: 0;
  - pembayaran dengan pemilik berbeda: 0;
  - nomor antrian aktif ganda: 0.
- Index inti untuk booking, rekam medis, pembayaran, pesanan, dan pengguna
  sudah tersedia.
- Translation `id` dan `en` untuk admin sama-sama memiliki 967 key.
- `failed_jobs=0`.
- Scheduler aktif dengan 9 tugas terjadwal.

# Urutan perbaikan yang disarankan

1. Perbaiki kontras tombol utama.
2. Jalankan queue worker dan proses 5 job tertahan.
3. Tambahkan middleware security headers.
4. Lengkapi semantik tab dan label filter.
5. Perbesar target sentuh.
6. Jalankan full regression suite.
7. Rapikan format tanggal, pagination Ruang Klinis, dan logo.
8. Tambahkan Policy layer dan query monitoring.
9. Lakukan UAT browser dan perangkat.

# Batasan audit

- Tidak ada penetration test.
- Tidak ada pengujian beban.
- Tidak ada uji perangkat Android nyata.
- Tidak ada uji payment gateway live.
- Tidak ada uji restore storage dan kunci enkripsi.
- Full test suite tidak dijalankan dalam audit ini.
- Temuan aksesibilitas terbatas pada 11 halaman yang dipindai axe.
