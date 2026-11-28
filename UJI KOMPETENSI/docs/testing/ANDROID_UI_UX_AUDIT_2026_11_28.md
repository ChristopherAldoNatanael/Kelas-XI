# Audit UI/UX Android — 28 November 2026

## Lingkungan

- Emulator Pixel 7, Android API 35
- Resolusi 1080 × 2400
- APK debug dari `assembleDebug`
- Backend lokal `http://127.0.0.1:8000`
- Akun uji: `qa.owner@petheal.test`

Screenshot audit dipakai sementara selama pemeriksaan dan sudah dihapus
setelah audit selesai agar tidak menambah ukuran project.

## Cakupan

- 68 file UI Kotlin
- 39 route layar di `PetHealNavHost`
- 16 komponen bersama
- 7 file tema
- Navigasi bawah, transisi, state kosong, loading, error, dan kontras

Layar yang diperiksa visual langsung:

1. Splash
2. Onboarding
3. Login
4. Phone setup
5. Beranda
6. Hewan
7. Booking
8. Rekam Medis
9. Profil
10. Notifikasi
11. Pet Shop
12. Dialog logout

Layar lain diperiksa lewat kode, komponen bersama, dan pola yang sama.

## Masalah yang ditemukan dan diperbaiki

### 1. Ringkasan beranda berbahasa Inggris

Sebelum:

```text
You have 1 pet.
```

Sesudah:

```text
1 hewan terdaftar.
```

Sumber string ada di backend `Api/DashboardController`, bukan di Android. Diperbaiki agar aplikasi web/Android konsisten.

### 2. Pemilih klinik dihapus dari Login dan Register

Konsep akun sekarang global: satu akun bisa mencari klinik, membandingkan
harga, lalu booking di klinik mana pun. Karena itu:

- kartu “Klinik tujuan” di Login dihapus;
- parameter `clinicSlug` tidak lagi dikirim saat login email;
- banner “Satu akun untuk semua klinik” di Register dihapus;
- `ClinicPickerSheet` tidak lagi dimuat dari layar Login.

Klinik tetap ditentukan server dari akun dan/atau langkah booking.

### 3. Tombol Masuk disabled terlalu pudar

Warna disabled diganti dari abu-abu kebiruan dengan teks 62% menjadi:

- latar `SurfaceMuted`
- teks `OnBackground` 76%

Teks tetap terbaca tanpa terlihat seperti tombol aktif.

### 4. Tautan login kurang kontras

`Lupa Kata Sandi?`, `Ganti`, dan `Daftar Sekarang` sekarang memakai
`PrimaryDark`, bukan hijau terang.

### 5. Tombol Google menabrak gesture bar

Jarak vertikal atas dan bawah dirapatkan, padding bawah dinaikkan, sehingga
tombol Google berhenti di atas area gestur.

### 6. Aksi tambah hewan duplikat

Sebelum ada dua aksi untuk hal yang sama:

- kartu “Tambah Hewan Baru”
- FAB “+”

FAB dihapus. Satu aksi utama tersisa dan layar lebih bersih.

### 7. Kartu tambah hewan tidak konsisten

Ikon `+` diubah dari kotak membulat menjadi lingkaran, sama seperti avatar
hewan. Chevron diberi lingkaran putih seperti kartu hewan.

### 8. Kontras tab dan chip Booking

- tab tidak aktif dinaikkan dari 88% menjadi 98% putih
- track tab dibuat lebih tegas
- label chip 9,5sp dengan ellipsis
- angka chip 15sp
- “Perlu bayar” tidak lagi terpotong

### 9. Teks antrian hijau di atas hijau

Label “Menunggu”, “Dipanggil”, “Sedang diperiksa” sekarang memakai warna teks
yang lebih gelap (`primaryDeep`, `amberText`, `skyDeep`), bukan warna aksen
terang.

### 10. Empty state Rekam Medis tanpa judul

Empty state sekarang memiliki:

- app bar “Rekam Medis”
- tombol utama **Buat Jadwal**
- tautan sekunder **Kembali ke Beranda**

Sebelumnya hanya ada tombol “Kembali” yang membingungkan.

### 11. Tipe akun menampilkan data internal

Sebelum:

```text
Tipe Akun: User
```

Sesudah:

```text
Tipe Akun: Pemilik Hewan
```

Pemetaan juga menangani Dokter, Admin Klinik, dan Super Admin.

### 12. Teks Inggris di pembayaran

```text
Returning automatically...
```

diganti:

```text
Kembali otomatis...
```

### 13. Warna aksi cepat Beranda

Tile “Rekam” yang abu-abu diganti aksen ungu lembut, sehingga lima aksi punya
kelompok warna yang disengaja.

### 14. Navigasi bawah

- ditambah scrim gradien agar konten yang lewat di belakang tidak terlihat
  terpotong tajam
- `zIndex` dinaikkan
- item aktif sekarang memiliki semantics `selected` dan `Role.Tab` untuk
  TalkBack

### 15. Label kategori Pet Shop terpotong

Chip kategori dari 72dp menjadi 86dp dan boleh dua baris. Label seperti
“Makanan Kucing”, “Makanan Anjing”, dan “Makanan Hamster” tidak lagi menjadi
“Makanan Ku…”.

### 16. Subjudul notifikasi duplikat

Subjudul kosong yang sebelumnya “Tidak ada pembaruan baru” diganti menjadi
penjelasan fungsi:

```text
Pemberitahuan booking, pembayaran, dan perawatan
```

## Daftar semua layar

| Kelompok | Layar | Status |
|---|---|---|
| Auth | Splash, Onboarding, Login, Register, VerifyOtp, PhoneSetup, CompleteSetup | diaudit |
| Utama | Home, Pets, Bookings, MedicalRecords, Profile | diaudit visual |
| Hewan | PetDetail, AddPet, EditPet | diaudit kode |
| Booking | BookingStart, CreateBooking, BookingDetail | diaudit kode |
| Medis | MedicalRecordDetail, MedicalExtraPayment | diaudit kode |
| Pembayaran | Payment, PaymentResult | diaudit kode |
| Dokter | Doctors, DoctorDetail | diaudit kode |
| Klinik | ClinicDiscovery, ClinicDetail, PriceCompare | diaudit kode |
| Layanan | ServiceRequests | diaudit kode |
| Notifikasi | Notifications | diaudit visual |
| Pet Shop | PetShop, PetShopProduct, PetShopCheckout, PetShopOrders, PetShopOrderDetail, PetShopReceipt | diaudit visual/kode |
| Profil | EditProfile | diaudit kode |
| Pengaturan | PrivacySecurity, HelpSupport, About | diaudit kode |

## Verifikasi

```text
assembleDebug       BUILD SUCCESSFUL
testDebugUnitTest   97 tes, 0 gagal
lintDebug           BUILD SUCCESSFUL, 0 error
```

Lint masih memiliki 140 warning dan 6 information. Tidak ada error atau fatal.

## Batasan

- Belum diuji pada perangkat fisik.
- Belum diuji pada tablet atau layar lipat.
- Belum ada audit TalkBack menyeluruh.
- Belum diuji rotasi layar.
- Beberapa layar detail hanya diaudit kode, bukan screenshot.
- Gambar onboarding masih memakai URL eksternal; jika gagal, fallback ikon
  PetHeal tampil.
- Produk Pet Shop yang tidak punya foto memakai ilustrasi kategori, bukan foto
  produk asli.

## Aturan untuk perubahan berikutnya

1. Jangan menambah FAB jika sudah ada kartu aksi di dalam daftar.
2. Jangan memakai hijau terang untuk teks di atas latar putih.
3. Semua tab bawah wajib memiliki semantics `selected`.
4. Semua empty state harus punya satu aksi maju, bukan hanya “Kembali”.
5. Jangan menulis status internal (`User`, `login email`, nama role mentah) ke UI.
6. Jangan memakai bahasa Inggris untuk kalimat produk.
