# Pendaftaran Walk-in dan Darurat (Web Admin)

Status: **UI web admin selesai; Android tidak diubah**  
Tanggal: 27 November 2026  
Migration: `2026_11_27_000020_add_triage_level_to_bookings.php`

## 1. Tujuan

Pasien yang datang langsung tanpa booking dari aplikasi dapat:

1. didaftarkan oleh admin;
2. diberi nomor antrian otomatis;
3. dipanggil dari papan antrian;
4. dilanjutkan ke rekam medis dokter.

Kunjungan darurat mendapat **triage** wajib.

## 2. Halaman

```text
/admin/walk-ins
```

Sidebar admin:

```text
Walk-in & Darurat
```

## 3. Form pendaftaran

Isi form:

- jenis kunjungan:
  - walk-in;
  - darurat;
- dokter penanggung jawab;
- triage (wajib untuk darurat):
  - merah;
  - oranye;
  - kuning;
  - hijau;
- pemilik:
  - cari pemilik terdaftar dengan autocomplete langsung saat mengetik
    (nama/email/telepon, minimal 2 karakter) tanpa reload;
  - atau buat pemilik baru;
- hewan:
  - pilih hewan milik pemilik;
  - atau buat hewan baru;
- keluhan/catatan awal;
- perlu pembayaran dan nominal (opsional).

Setelah disimpan:

- booking dibuat dengan status `confirmed`;
- `service_type` = `walk_in` atau `emergency`;
- `triage_level` diisi untuk darurat;
- nomor antrian langsung dibuat;
- status antrian `waiting`;
- muncul di `/admin/queue`;
- muncul di daftar kunjungan hari ini.

## 4. Alur dokter

Karena kunjungan memakai booking:

1. booking muncul di portal dokter;
2. dokter membuka antrian;
3. dokter memanggil pasien;
4. dokter menekan **Isi Rekam Medis**;
5. form rekam medis yang sudah ada dipakai;
6. rekam medis final mengikuti aturan kredensial yang sama.

Tidak ada jalur rekam medis kedua.

## 5. Alur admin setelah pendaftaran

Di daftar **Kunjungan hari ini**:

- lihat nomor antrian;
- lihat status antrian;
- lihat triage;
- buka detail booking;
- buat/buka rekam medis.

## 6. Pembayaran

- Jika “Perlu pembayaran” tidak dicentang:
  - `payment_status = not_required`;
  - total 0.
- Jika dicentang:
  - `payment_status = unpaid`;
  - total mengikuti nominal;
  - pembayaran menyusul lewat alur pembayaran yang ada.

## 7. Pemilik baru

Untuk pasien tanpa akun:

- nama wajib;
- telepon opsional;
- email opsional;
- password acak dibuat, akun tidak diberi akses login otomatis;
- jika email sudah terdaftar sebagai pemilik, akun itu dipakai;
- jika email kosong, dibuat email internal `walkin.*@petheal.local`.

Catatan: mekanisme klaim akun untuk pemilik baru belum dibuat.

## 8. Panel encounter darurat di rekam medis

Pada halaman detail rekam medis admin dan dokter, kunjungan darurat menampilkan
panel **Encounter Darurat**:

- triage aktif;
- status encounter;
- disposisi;
- form perbarui triage dan keluhan utama;
- riwayat consent darurat;
- form catat consent darurat:
  - dasar tindakan;
  - penandatangan;
  - hubungan;
  - saksi;
  - risiko yang dijelaskan;
- form tutup encounter dengan disposisi:
  - stabil;
  - rujuk;
  - rawat inap;
  - pulang;
  - meninggal.

Saat rekam medis ditautkan ke booking walk-in/darurat, encounter otomatis
memakai tipe `walk_in` atau `emergency` dan membawa triage dari booking.

## 9. Batasan

- Android tidak menampilkan menu darurat/walk-in.
- Pendaftaran hanya dari web admin.
- Belum ada unggah bukti consent di panel darurat.
- Belum ada form pencabutan consent dari panel darurat.
- Belum ada pendaftaran pasien tanpa nama pemilik/anonymous.
- Belum ada pencarian hewan lintas pemilik.
- Belum ada estimasi waktu tunggu.
- Belum ada integrasi nomor antrian tanpa booking.
- Kunjungan memakai booking agar antrian dan rekam medis lama tetap bekerja.
- Belum ada uji browser manual di perangkat nyata.
- Full regression belum dijalankan setelah perubahan ini.

## 9. Pengujian otomatis

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P47WalkInEmergencyUiTest.php
```

Hasil: **5 passed, 46 assertions**.

Cakupan:

- halaman admin terbuka;
- autocomplete pemilik mengembalikan pemilik dan hewannya;
- walk-in pemilik lama mendapat nomor antrian;
- darurat tanpa triage ditolak;
- darurat dengan triage membuat pemilik + hewan + booking;
- pembayaran wajib menghasilkan status `unpaid`;
- encounter otomatis bertipe `emergency`;
- triage dapat diperbarui;
- consent darurat tercatat;
- encounter dapat ditutup dengan disposisi.
