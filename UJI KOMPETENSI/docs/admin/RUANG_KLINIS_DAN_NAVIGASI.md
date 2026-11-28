# Ruang Klinis dan Navigasi Web

Status: **UI web admin selesai; Android tidak diubah**  
Tanggal: 27 November 2026

## 1. Ruang Klinis

Halaman:

```text
/admin/clinical
```

Menu sidebar:

```text
Klinis → Ruang Klinis
```

Tujuan: menampilkan modul yang sebelumnya hanya ada di service/model tanpa
layar admin.

### Tab yang tersedia

1. **Laboratorium**
   - order, hewan, pemeriksaan, prioritas, jumlah spesimen, status;
   - tautan ke rekam medis.

2. **Rawat Inap**
   - hewan, lokasi kandang/bangsal, dokter, waktu masuk, status;
   - form pemulangan untuk pasien aktif:
     - ringkasan pulang wajib;
     - instruksi pulang opsional.

3. **Rujukan**
   - nomor rujukan, hewan, tujuan, arah, status, tanggal;
   - form pembatalan dengan alasan wajib.

4. **Penyakit Menular**
   - penyakit, hewan, klasifikasi, tenggat, status, verifier;
   - tenggat lewat ditampilkan merah.

5. **Template**
   - daftar template klinis aktif;
   - daftar template consent aktif;
   - form publikasi versi baru;
   - schema klinis wajib berisi `fields`.

6. **Ekspor Medis**
   - hewan, format, tujuan, penerima, kedaluwarsa;
   - status: aktif, sudah diunduh, kedaluwarsa, dicabut.

7. **Kesejahteraan**
   - hewan, skor rata-rata, penilai, waktu, catatan.

### Aksi yang sudah tersedia

- publikasi template klinis;
- publikasi template consent;
- pemulangan rawat inap;
- pembatalan rujukan.

Aksi lain (buat order lab, admisi rawat inap, buat rujukan, buat laporan
penyakit) masih dibuat dari service/alur rekam medis dan belum punya form di
Ruang Klinis.

## 2. Navigasi Admin

Sidebar admin sekarang memakai kelompok:

- **Operasional**: antrian, walk-in/darurat, permintaan layanan, pengguna,
  booking, dokter, layanan;
- **Klinis**: rekam medis, ruang klinis, permintaan PDP;
- **Keuangan & Penjualan**: pembayaran, lalu grup Pet Shop;
- **Inventori**: obat, kedaluwarsa, mutasi stok;
- **Administrasi**: audit, pengaturan pembayaran, pengaturan, notifikasi.

Label kelompok disembunyikan saat sidebar diciutkan.

### Bahasa visual sidebar

- permukaan hijau sage lembut, bukan hitam dan tidak neon;
- aksen emerald dipakai untuk menu aktif, ikon aktif, fokus, dan tombol ciut;
- ikon garis 18px tanpa tile, mengikuti pola konsol SaaS perusahaan;
- item 36px, label 12,75px, radius 7px;
- item aktif memakai pil emerald lembut dengan teks putih dan bilah putih;
- label kelompok 9px, huruf kapital, warna hijau tua;
- submenu memakai garis vertikal hijau, animasi buka 240ms;
- rail ciut 76px, ikon tepat di tengah, tooltip native memakai judul menu;
- divider kelompok tampil sebagai garis tipis saat rail ciut;
- tombol buka kembali tampil sebagai tombol emerald di bawah logo;
- kartu pengguna memakai avatar emerald dan teks peran hijau tua;
- fokus keyboard memakai outline emerald di dalam item.

Animasi yang dipakai hanya:
- lebar rail 500ms dengan easing `cubic-bezier(0.4, 0, 0.2, 1)`;
- saat menutup, lebar disusutkan lebih dulu; tata letak rail baru dipasang
  setelah 520ms agar ikon tidak melompat ke tengah saat sidebar masih lebar;
- saat membuka, tata letak penuh dipasang lebih dulu, lalu lebar tumbuh;
- tinggi header dikunci 64px selama animasi agar tidak ada lompatan;
- latar item 300ms;
- indikator aktif 300ms;
- tinggi submenu 280ms saat buka dan 240ms saat tutup, memakai Web
  Animations API agar tidak melompat;
- teks sidebar 260ms.

Semua animasi dimatikan otomatis oleh `prefers-reduced-motion`.

Diverifikasi lewat Edge 1440×960: submenu tertutup secara default, tinggi
submenu bertambah bertahap (30,7px lalu 84px) dan menutup kembali ke 0 tanpa
error JavaScript; rail dapat diciutkan dan dibuka kembali; tooltip judul
muncul saat ciut; ikon rail berada pada sumbu tengah; pil aktif tidak
menyentuh tepi rail.
## 3. Navigasi Dokter

Sidebar dokter memakai kelompok:

- **Praktik**: booking, antrian, permintaan layanan;
- **Dokumentasi**: rekam medis;
- **Jadwal**: jadwal praktik;
- **Akun**: profil dan logout.

## 4. Navigasi Super Admin

Super admin tanpa klinik tetap memakai blok **Super Admin**:

- ringkasan sistem;
- permintaan bergabung;
- klinik;
- pengaturan global.

Saat memilih klinik, sidebar admin klinik dipakai agar konteks tidak tercampur.

## 5. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P48ClinicalWorkspaceUiTest.php
```

Hasil: **3 passed, 19 assertions**.

Cakupan:

- seluruh bagian Ruang Klinis tampil;
- template klinis dapat dipublikasikan;
- template consent dapat dipublikasikan dan hash tersimpan;
- schema klinis tidak valid ditolak.

`php artisan view:cache` sukses.

## 6. Batasan

- Ruang Klinis masih read-only untuk laboratorium, penyakit, ekspor, dan
  kesejahteraan.
- Belum ada form admisi rawat inap dari UI.
- Belum ada form order laboratorium dari UI.
- Belum ada form rujukan baru dari UI.
- Belum ada form laporan penyakit baru dari UI.
- Belum ada halaman detail per order/stay.
- Belum ada pagination; daftar dibatasi 50 data terbaru.
- Belum ada uji browser manual.
- Full regression belum dijalankan setelah perubahan ini.
