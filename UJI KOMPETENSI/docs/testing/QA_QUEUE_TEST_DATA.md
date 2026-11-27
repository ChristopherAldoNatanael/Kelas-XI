# Data Uji Antrian (Android + Web Admin)

Dokumen ini untuk menguji:

1. check-in booking dari aplikasi Android;
2. nomor antrian muncul di Android;
3. admin memanggil nomor antrian;
4. status “Dipanggil” terlihat oleh pemilik.

## 1. Membuat / mereset data

Di folder `PetHeal_Backend`:

```bash
php artisan qa:seed-queue-test
```

Default memakai:

- klinik `#1` — Klinik PetHeal Pusat;
- dokter `#8` — Dr. Emily Williams;
- tanggal **hari ini**.

Jika perlu klinik/dokter lain:

```bash
php artisan qa:seed-queue-test --clinic=1 --doctor=8
```

Command bersifat idempoten. Jalankan ulang untuk mengembalikan ketiga booking
ke kondisi awal.

## 2. Akun uji

| Peran | Email | Password |
|---|---|---|
| Pemilik hewan | `qa.owner@petheal.test` | `PetHealQa123!` |
| Admin klinik | `qa.admin@petheal.test` | `PetHealAdmin123!` |

Akun ini hanya untuk development/testing. Hapus atau ganti sebelum produksi.

## 3. Isi data

Command membuat tiga booking pada tanggal hari ini:

| Booking | Kondisi awal | Kegunaan |
|---|---|---|
| A | `confirmed`, belum check-in, tanpa nomor | uji check-in dari Android |
| B | `confirmed`, sudah check-in, `waiting` | uji panggil antrian |
| C | `confirmed`, sudah check-in, `waiting` | cadangan / uji panggil berikutnya |

Nomor antrian mengikuti nomor terakhir klinik pada hari itu. Contoh keluaran:

```text
Booking A #691 — belum check-in
Booking B #692 — A-01 menunggu
Booking C #693 — A-02 menunggu
```

Nomor sebenarnya bisa berbeda bila sudah ada antrian lain hari ini.

## 4. Uji check-in di Android

1. Login sebagai `qa.owner@petheal.test`.
2. Buka **Booking / My Booking**.
3. Pilih booking **A**.
4. Tekan **Check-in**.
5. Nomor antrian muncul, misalnya **A-03**.
6. Status berubah menjadi **Menunggu**.

Check-in hanya berhasil pada tanggal booking. Jika command dijalankan kemarin,
jalankan ulang hari ini.

## 5. Uji panggil antrian di web admin

1. Buka `/admin/login`.
2. Login sebagai `qa.admin@petheal.test`.
3. Buka `/admin/queue`.
4. Pastikan booking **B (A-01)** dan **C (A-02)** terlihat di kolom menunggu.
5. Tekan **Panggil berikutnya**.
6. Booking **B** berubah menjadi **Dipanggil**.
7. Panggil lagi untuk memanggil **C**.

## 6. Melihat status di Android

1. Kembali ke Android.
2. Buka booking **B**.
3. Tunggu maksimal **10 detik** atau tarik-refresh.
4. Status antrian harus menjadi **Dipanggil**.

Cache booking di Android hanya 10 detik. Perubahan dari web admin tidak
mengosongkan cache perangkat, jadi status bisa terlihat setelah cache
kedaluwarsa atau setelah refresh.

## 7. Mereset data

```bash
php artisan qa:seed-queue-test
```

Ini:

- mengembalikan booking A ke kondisi belum check-in;
- menghapus nomor lama B/C lalu memberi nomor baru;
- mempertahankan akun dan hewan uji.

## 8. Catatan teknis

- Data ditandai pada `bookings.notes` dengan awalan `[QA-QUEUE]`.
- Nomor antrian unik per klinik + tanggal.
- Command tidak mengirim FCM.
- Booking A, B, C memakai pemilik dan hewan yang sama:
  - pemilik `QA Pemilik Antrian`;
  - hewan `Mochi QA`.
- Tanggal booking selalu `today()` saat command dijalankan.
- Jam booking: 09:00, 10:00, 10:30.
- Pembayaran ditandai `paid` agar tampilan tidak terhalang status pembayaran.
- Jangan memakai akun uji ini di produksi.
