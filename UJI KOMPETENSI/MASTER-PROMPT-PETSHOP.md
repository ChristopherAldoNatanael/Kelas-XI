# MASTER PROMPT — PETHEAL MULTI-CLINIC PET SHOP & MIDTRANS PARTNER PAYMENT INTEGRATION

## 1. ROLE DAN STANDAR KERJA

Bertindak sebagai Senior Full-Stack Engineer, Senior Laravel Developer, Android Developer, Database Architect, Payment Integration Engineer, QA Engineer, dan Technical Lead yang bertanggung jawab melanjutkan pengembangan project **PetHeal** yang sudah berjalan.

Tugasmu bukan membuat project baru dari nol. Tugasmu adalah memahami, mengaudit, dan mengembangkan repository PetHeal yang sudah ada tanpa merusak fitur, database, API, maupun tampilan yang sebelumnya berfungsi.

Kamu harus bekerja dengan standar production-grade:

- Audit kode dan struktur database sebelum mengubah apa pun.
- Jangan berasumsi tentang implementasi yang belum diperiksa.
- Jangan mengarang nama tabel, endpoint, relasi, atau fungsi yang sebenarnya belum ada.
- Gunakan arsitektur dan pola kode yang sudah diterapkan di repository selama masih layak.
- Pertahankan kompatibilitas dengan fitur lama.
- Hindari duplikasi logic, hardcoding, dummy data, fake success, dan implementasi setengah jadi.
- Setiap perubahan database harus memiliki migration yang aman.
- Setiap endpoint harus memiliki validasi, authorization, dan pengujian.
- Setiap perubahan UI harus mengikuti desain yang sudah ada.
- Jangan menghapus atau mengganti fitur lama hanya karena menurutmu ada cara yang lebih mudah.
- Jangan berhenti hanya pada analisis atau pemberian rekomendasi. Setelah audit, lanjutkan implementasi secara bertahap.
- Jangan meminta konfirmasi untuk hal-hal yang sebenarnya dapat diketahui dari source code. Untuk informasi eksternal yang belum tersedia, buat desain yang aman dan dokumentasikan keterbatasannya.

**Prioritas tertinggi: integritas sistem, keamanan multi-klinik, keamanan transaksi keuangan, konsistensi stok, kompatibilitas fitur lama, dan kualitas implementasi.**

---

# 2. KONTEKS PROJECT PETHEAL

Gunakan informasi berikut sebagai konteks awal. Tetap verifikasi seluruhnya dari repository aktual sebelum mengambil keputusan teknis.

## 2.1 Backend

- Framework: Laravel 12.
- PHP runtime: 8.3.31.
- Database: MySQL.
- Nama database yang digunakan: `pet_heal`.
- Authentication: Laravel Sanctum.
- Role management: Spatie Permission.
- Arsitektur aplikasi: Multi-clinic.
- Foreign key isolasi klinik menggunakan `clinic_id`.

Role yang sudah digunakan:

1. `super_admin`
2. `clinic_admin`
3. `doctor`
4. `user`

## 2.2 Android

- Package: `com.christopheraldoo.petheal`.
- Android SDK target yang diketahui: 34.
- Minimum SDK: 24.
- JVM target: 17.
- Project Android sudah mempunyai halaman, navigasi, authentication, dan fitur yang berjalan.

Jangan mengganti keseluruhan arsitektur Android. Periksa apakah project menggunakan Kotlin, Jetpack Compose, XML, Retrofit, repository pattern, ViewModel, atau pendekatan lain. Ikuti pola aktual yang digunakan.

## 2.3 Sistem Multi-Clinic

PetHeal memiliki konsep banyak klinik, bukan hanya satu klinik.

Data klinik yang sebelumnya digunakan dalam konteks project:

- Klinik PetHeal Pusat.
- Happy Paws Bandung.
- MeowCare Surabaya.

Gunakan data aktual di database sebagai acuan utama.

Setiap klinik harus mempunyai data, produk, transaksi, laporan, konfigurasi, dan akses administratif yang terisolasi sesuai kewenangannya.

## 2.4 Konteks Fitur yang Sudah Ada

Sistem saat ini memiliki atau pernah dikembangkan dengan modul:

- Authentication dan authorization.
- Manajemen klinik.
- Booking konsultasi.
- Dokter dan jadwal konsultasi.
- Rekam medis.
- Manajemen obat.
- Stok obat dan mutasi stok.
- Monitoring kedaluwarsa obat.
- Pengelolaan data pasien/pemilik hewan.
- Aplikasi Android untuk pengguna.
- Dashboard VCMS untuk administrasi.

Audit kondisi sebenarnya sebelum menganggap seluruh fitur tersebut sudah lengkap.

**JANGAN MERUSAK MODUL MEDIS.**

Modul Pet Shop yang akan dikembangkan harus terpisah secara domain dari sistem persediaan obat dan layanan medis.

---

# 3. TUJUAN PENGEMBANGAN

Kembangkan PetHeal menjadi platform layanan kesehatan hewan multi-klinik yang juga menyediakan fasilitas penjualan produk hewan peliharaan.

Ada dua pengembangan utama yang harus terintegrasi:

## A. PetHeal Pet Shop

Fitur marketplace produk hewan peliharaan yang memungkinkan setiap klinik menjual produk miliknya melalui aplikasi PetHeal.

## B. Centralized Payment Service dengan Midtrans Partner Solutions

Sistem pembayaran terpusat pada level integrasi aplikasi, tetapi penerimaan dana tetap diarahkan kepada merchant klinik yang berhak menerimanya melalui skema Partner yang disetujui Midtrans.

Sistem pembayaran harus bisa melayani:

1. Pembayaran booking konsultasi.
2. Pembayaran pembelian produk Pet Shop.
3. Pembayaran untuk lebih dari satu klinik.
4. Pembagian dana antara klinik dan PetHeal apabila skema komisi disepakati dan diizinkan.
5. Pencatatan seluruh transaksi dan riwayat pembayaran.
6. Rekonsiliasi status pembayaran.
7. Refund ketika transaksi memenuhi ketentuan.

**Kedua pengembangan ini harus menggunakan satu arsitektur backend yang konsisten, tetapi domain transaksi, order, stok, dan pelaporan tetap dibedakan.**

---

# 4. TAHAP WAJIB PERTAMA — AUDIT REPOSITORY

Sebelum mengubah kode, lakukan audit menyeluruh.

Jangan langsung membuat migration atau menambahkan komponen baru sebelum memahami implementasi aktual.

## 4.1 Audit Laravel Backend

Periksa:

- Struktur folder Laravel.
- `routes/api.php`, `routes/web.php`, dan route tambahan jika ada.
- Controllers.
- Models.
- Relationships.
- Services.
- Repositories jika digunakan.
- Middleware.
- Policies.
- Form Requests.
- API Resources.
- Jobs dan Queue.
- Events dan Listeners.
- Notifications.
- Migrations.
- Seeders.
- Factories.
- Existing tests.
- Authentication.
- Authorization.
- Error handling.
- Konvensi response API.
- Konfigurasi environment.
- Integrasi eksternal yang sudah tersedia.

Petakan bagaimana proses berikut berjalan:

1. Pengguna login.
2. Pengguna memilih atau berada dalam konteks klinik.
3. Pengguna melakukan booking konsultasi.
4. Pembayaran konsultasi diproses.
5. Klinik menerima booking.
6. Admin klinik mengelola transaksi.
7. Data transaksi masuk ke dashboard dan laporan.

Jangan membuat sistem pembayaran baru yang menduplikasi implementasi lama tanpa alasan teknis yang jelas.

## 4.2 Audit Database

Periksa seluruh tabel yang relevan.

Identifikasi:

- Tabel klinik.
- Tabel user.
- Tabel booking.
- Tabel dokter.
- Tabel jadwal.
- Tabel konsultasi.
- Tabel pembayaran yang sudah ada.
- Tabel obat.
- Tabel stok.
- Tabel mutasi.
- Tabel kategori.
- Tabel transaksi.
- Foreign key.
- Unique constraint.
- Index.
- Cascade behavior.
- Soft deletes.
- Data historis.

Gunakan struktur database aktual sebagai dasar pengembangan.

**Jangan membuat tabel baru jika sebenarnya sudah tersedia fungsi atau tabel serupa.** Perluas struktur yang ada jika memang lebih tepat dan tidak merusak kompatibilitas.

## 4.3 Audit Android

Periksa:

- Struktur module.
- Navigation.
- Existing screens.
- Theme dan design system.
- API client.
- DTO dan model.
- Repository.
- ViewModel.
- State management.
- Authentication token.
- Error handling.
- Image loading.
- Existing booking flow.
- Existing UI.
- Build configuration.

Identifikasi tempat terbaik untuk memasukkan fitur Pet Shop tanpa mengubah navigasi dan alur lama secara sembarangan.

## 4.4 Audit VCMS

Periksa dashboard web berdasarkan role:

- Super Admin.
- Clinic Admin.
- Doctor jika mempunyai akses tertentu.

Identifikasi sidebar, menu, layout, komponen tabel, form, modal, dashboard, grafik, filter, pagination, dan pola UI yang sudah dipakai.

## 4.5 Hasil Audit

Buat ringkasan:

1. Arsitektur aktual.
2. Tabel dan relasi yang sudah tersedia.
3. API yang bisa digunakan kembali.
4. Fitur yang sudah berjalan.
5. Fitur yang belum tersedia.
6. Potensi konflik dengan implementasi baru.
7. Risiko database.
8. Risiko keamanan.
9. Rencana implementasi berdasarkan kondisi aktual.

Setelah audit, langsung lanjut ke implementasi. Jangan berhenti hanya dengan memberikan laporan.

---

# 5. MODUL PETHEAL PET SHOP

## 5.1 Konsep Utama

PetHeal Pet Shop adalah fitur penjualan produk yang dimiliki masing-masing klinik.

Contoh:

- Klinik PetHeal Pusat menjual produk A dan B.
- Happy Paws Bandung menjual produk C dan D.
- MeowCare Surabaya menjual produk E dan F.

Setiap klinik mengelola katalog, harga, stok, dan pesanan miliknya sendiri.

Pengguna dapat menemukan produk dari klinik yang tersedia melalui aplikasi Android.

**PetHeal bukan berarti seluruh produk dimiliki oleh satu penjual pusat. Penjual sebenarnya adalah masing-masing klinik.**

## 5.2 Kategori Produk

Sediakan sistem kategori yang fleksibel.

Contoh kategori:

- Makanan Kucing.
- Makanan Anjing.
- Pasir Kucing.
- Vitamin dan Nutrisi.
- Shampoo dan Perawatan.
- Mainan Hewan.
- Aksesoris.
- Kandang dan Perlengkapan.
- Produk Kebersihan.
- Produk Hewan Lainnya.

Kategori harus dapat dikelola secara administratif sesuai rancangan sistem.

Jangan membatasi kategori hanya pada contoh di atas.

## 5.3 Data Produk

Setiap produk minimal mempunyai:

- Clinic ID.
- Category ID.
- Nama produk.
- Slug jika digunakan.
- Deskripsi.
- Foto produk.
- Brand.
- Status aktif/nonaktif.
- Informasi produk.
- Timestamps.
- Data pemilik/pembuat jika pola audit project menggunakannya.

Setiap produk harus dapat memiliki beberapa varian.

## 5.4 Product Variants

Jangan memperlakukan ukuran produk sebagai teks biasa apabila ukuran tersebut mempunyai harga dan stok yang berbeda.

Contoh:

Produk: Royal Canin Indoor Adult.

Varian:

| Varian | SKU | Harga | Stok |
|---|---|---:|---:|
| 400 gram | RC-400 | Rp45.000 | 15 |
| 1 kilogram | RC-1KG | Rp95.000 | 10 |
| 2 kilogram | RC-2KG | Rp175.000 | 8 |

Setiap varian wajib memiliki identitas SKU yang unik sesuai aturan sistem.

Minimal data:

- Product ID.
- SKU.
- Nama varian.
- Harga.
- Harga diskon bila fitur diskon tersedia.
- Stok fisik.
- Stok yang sedang direservasi.
- Status aktif/nonaktif.
- Timestamps.

Harga dan stok harus dihitung berdasarkan varian yang dipilih, bukan harga induk produk.

## 5.5 Pemisahan dengan Modul Obat

Ini merupakan aturan arsitektur wajib.

Jangan memasukkan makanan hewan, pasir, mainan, shampoo, aksesoris, dan barang Pet Shop lainnya ke master inventory obat.

Pisahkan:

- Medical Inventory.
- Medicine Stock.
- Medicine Mutation.
- Pet Shop Product.
- Pet Shop Variant.
- Pet Shop Stock Movement.

Jangan sampai perubahan modul Pet Shop menyebabkan:

- Stok obat berubah.
- Data obat terhapus.
- Riwayat mutasi obat berubah.
- Laporan obat menjadi salah.
- Aturan pemberian obat terganggu.
- Rekam medis ikut terpengaruh.

Gunakan database dan domain terpisah sesuai kebutuhan sistem.

---

# 6. DASHBOARD VCMS — PET SHOP MANAGEMENT

Tambahkan modul baru pada dashboard VCMS.

Nama menu utama yang disarankan:

**Penjualan Produk / Pet Shop**

Masukkan ke sidebar sesuai pola navigasi yang sudah ada.

Modul ini minimal memiliki bagian berikut.

## 6.1 Dashboard Pet Shop

Tampilkan ringkasan berdasarkan klinik yang sedang dikelola:

- Total produk.
- Total produk aktif.
- Total varian.
- Total stok tersedia.
- Pesanan masuk.
- Pesanan menunggu konfirmasi.
- Pesanan diproses.
- Pesanan siap diambil.
- Pesanan selesai.
- Pesanan dibatalkan.
- Total penjualan.
- Total transaksi berhasil.
- Grafik penjualan.
- Produk terlaris.
- Produk dengan stok menipis.

Pastikan semua angka berasal dari database aktual.

Jangan menggunakan angka dummy atau angka statis.

## 6.2 Manajemen Produk

Fitur:

- Tambah produk.
- Edit produk.
- Lihat detail.
- Hapus atau arsip produk dengan aturan aman.
- Aktifkan/nonaktifkan produk.
- Upload foto.
- Tambahkan beberapa foto jika sesuai desain.
- Kelola kategori.
- Kelola brand.
- Kelola varian.
- Kelola SKU.
- Kelola harga.
- Kelola diskon.
- Kelola stok.
- Pencarian produk.
- Filter kategori.
- Filter status.
- Pagination.

Penghapusan produk yang sudah mempunyai riwayat transaksi tidak boleh merusak detail pesanan historis.

Gunakan soft delete atau mekanisme arsip apabila sesuai dengan pola project.

## 6.3 Manajemen Kategori

Fitur:

- Tambah kategori.
- Edit kategori.
- Aktifkan/nonaktifkan.
- Upload ikon atau gambar jika diperlukan.
- Pengaturan urutan tampil.

Jangan menghapus kategori yang masih dipakai oleh produk tanpa mekanisme pengamanan.

## 6.4 Manajemen Pesanan

Tampilkan pesanan berdasarkan klinik masing-masing.

Informasi minimal:

- Nomor pesanan.
- Nama pelanggan.
- Klinik.
- Produk.
- Varian.
- SKU.
- Jumlah.
- Harga satuan.
- Subtotal.
- Diskon.
- Total.
- Metode pembayaran.
- Status pembayaran.
- Status pesanan.
- Waktu pemesanan.

Aksi:

- Melihat detail.
- Menerima pesanan.
- Menolak pesanan.
- Memproses pesanan.
- Menandai siap diambil.
- Menyelesaikan pesanan.
- Melihat bukti pembayaran jika tersedia dan sah.
- Melihat riwayat perubahan status.

Setiap perubahan status harus disimpan dalam history.

## 6.5 Riwayat Penjualan

Sediakan:

- Daftar transaksi.
- Filter tanggal.
- Filter status pembayaran.
- Filter metode pembayaran.
- Filter produk.
- Filter pesanan.
- Pencarian nomor pesanan.
- Detail transaksi.
- Ringkasan penjualan.
- Export laporan jika pola project memungkinkan.

Laporan klinik harus hanya mengambil transaksi milik klinik tersebut.

## 6.6 Hak Akses

`clinic_admin` hanya boleh melihat dan mengelola data Pet Shop kliniknya sendiri.

`super_admin` dapat melihat data keseluruhan berdasarkan kewenangan platform.

Jangan memberikan akses semua transaksi hanya karena user mempunyai role admin.

Semua query sensitif harus memiliki pembatasan `clinic_id` di backend.

---

# 7. APLIKASI ANDROID — PET SHOP USER EXPERIENCE

Tambahkan Pet Shop ke dalam aplikasi Android yang sudah ada.

Jangan membuat aplikasi baru.

Jangan mengganti seluruh desain aplikasi.

Jangan menghapus halaman yang sudah berjalan.

Gunakan komponen UI, theme, warna, typography, navigation, dan pola state management yang sudah digunakan.

## 7.1 Halaman Pet Shop

Buat tampilan yang rapi dan konsisten dengan desain PetHeal.

Minimal:

- Header Pet Shop.
- Search produk.
- Kategori.
- Produk rekomendasi.
- Produk populer.
- Produk terbaru.
- Daftar klinik atau identitas penjual bila relevan.
- Produk yang tersedia.

Tampilkan seller clinic dengan jelas.

Jangan membuat pengguna mengira seluruh produk dijual oleh PetHeal Pusat apabila penjualnya adalah klinik lain.

## 7.2 Daftar Produk

Informasi:

- Foto produk.
- Nama.
- Brand.
- Harga.
- Harga diskon jika ada.
- Informasi ketersediaan stok.
- Klinik penjual.
- Kategori.

Tambahkan pencarian dan filter sesuai kemampuan API.

Jangan menampilkan produk yang nonaktif atau tidak tersedia untuk dibeli.

## 7.3 Detail Produk

Tampilkan:

- Foto.
- Nama.
- Brand.
- Deskripsi.
- Kategori.
- Klinik penjual.
- Varian.
- SKU bila relevan.
- Harga setiap varian.
- Stok tersedia.
- Quantity selector.
- Tambah ke keranjang.
- Informasi pengambilan di klinik.

Varian harus benar-benar terhubung dengan harga dan stok dari backend.

## 7.4 Keranjang

Aturan penting:

**Satu pesanan hanya boleh berasal dari satu klinik.**

Jangan menggabungkan produk dari beberapa klinik menjadi satu order yang berpotensi salah dalam pembagian pembayaran dan pemenuhan pesanan.

Apabila pengguna mencoba memasukkan produk dari klinik berbeda:

- Berikan penjelasan yang jelas.
- Tawarkan untuk mengosongkan atau menyelesaikan keranjang sebelumnya.
- Jangan diam-diam mencampurkan pesanan.

Pastikan:

- Quantity dapat diubah.
- Produk dapat dihapus.
- Varian dapat diganti.
- Harga dihitung kembali dari backend saat checkout.
- Stok diperiksa kembali sebelum membuat order.

Jangan mempercayai total harga yang dikirim aplikasi Android.

## 7.5 Checkout

Untuk versi pertama, gunakan:

**Pickup at Clinic / Ambil di Klinik.**

Jangan memaksakan fitur pengiriman ke rumah apabila belum direncanakan.

Tampilkan:

- Nama klinik.
- Alamat klinik.
- Kontak klinik jika tersedia.
- Ringkasan item.
- Total pembayaran.
- Metode pembayaran yang tersedia.
- Instruksi pengambilan.

Metode pembayaran:

1. Pembayaran online melalui Midtrans apabila klinik sudah aktif dalam skema pembayaran yang sah.
2. Pembayaran langsung di klinik apabila diizinkan oleh konfigurasi bisnis dan klinik.

Jangan menampilkan pilihan pembayaran online untuk klinik yang Merchant ID-nya belum aktif dan tervalidasi.

## 7.6 Order History

Buat:

- Daftar pesanan.
- Detail pesanan.
- Nomor pesanan.
- Klinik.
- Daftar item.
- Total pembayaran.
- Status order.
- Status pembayaran.
- Metode pembayaran.
- Timeline.
- Instruksi pengambilan.

Pengguna hanya boleh melihat pesanan miliknya sendiri.

## 7.7 Konsistensi UI

Prioritaskan:

- UI konsisten.
- Navigasi mudah dipahami.
- Loading state.
- Empty state.
- Error state.
- Success state.
- Validasi quantity.
- Penanganan koneksi gagal.
- Penanganan produk habis.
- Penanganan pembayaran tertunda.
- Penanganan pembayaran gagal.

Jangan menggunakan data statis untuk menggantikan integrasi API.

---

# 8. SISTEM ORDER DAN MANAJEMEN STOK PET SHOP

Bagian ini sangat penting karena menyangkut uang dan persediaan fisik.

## 8.1 Pemisahan Status

Jangan menggabungkan status pesanan dan status pembayaran.

### Status Pesanan

Gunakan state machine yang jelas, dengan penyesuaian terhadap pola existing:

- `pending_payment`
- `awaiting_confirmation`
- `accepted`
- `preparing`
- `ready_for_pickup`
- `completed`
- `rejected`
- `cancelled`
- `expired`

### Status Pembayaran

Minimal mendukung status internal:

- `unpaid`
- `pending`
- `paid`
- `failed`
- `expired`
- `cancelled`
- `refund_pending`
- `partially_refunded`
- `refunded`
- `chargeback`

Nama status dan mapping wajib disesuaikan dengan kebutuhan model serta status aktual provider.

Simpan status asli dari Midtrans untuk rekonsiliasi.

## 8.2 Alur Pesanan

Rancangan alur online:

1. Pengguna memilih produk dari satu klinik.
2. Pengguna menambahkan item ke keranjang.
3. Backend memvalidasi produk, varian, harga, dan stok.
4. Backend membuat order.
5. Backend membuat reservasi stok.
6. Backend membuat payment record.
7. Backend meminta transaksi pembayaran ke Midtrans.
8. Pengguna menyelesaikan pembayaran.
9. Backend menerima dan memverifikasi notifikasi Midtrans.
10. Status pembayaran diperbarui.
11. Pesanan masuk ke daftar pesanan klinik.
12. Clinic Admin menerima atau menolak pesanan.
13. Pesanan diterima dan disiapkan.
14. Pesanan ditandai siap diambil.
15. Pengguna mengambil produk.
16. Admin mengonfirmasi penyelesaian.
17. Stok fisik dikurangi sesuai barang yang benar-benar diserahkan.
18. Transaksi ditandai selesai.

Sesuaikan waktu konfirmasi dan reservasi dengan kebijakan bisnis yang ditetapkan.

## 8.3 Aturan Reservasi Stok

Gunakan pendekatan yang mencegah overselling.

Konsep:

`available_stock = stock_on_hand - stock_reserved`

Saat checkout:

- Lock data varian yang diperlukan.
- Verifikasi stok tersedia.
- Buat reservasi stok.
- Tambahkan jumlah ke stok reserved.
- Pastikan tidak terjadi race condition.

Saat pembayaran pending:

- Reservasi memiliki waktu kedaluwarsa.
- Stok tidak boleh direservasi tanpa batas waktu.
- Jika pembayaran expired atau pesanan batal, reservasi dilepas.

Saat pembayaran sukses:

- Reservasi tetap dipertahankan sampai proses pemenuhan order selesai.
- Jangan langsung mengurangi stok fisik dua kali.

Saat pesanan selesai:

- Kurangi `stock_on_hand`.
- Kurangi `stock_reserved`.
- Catat stock movement.

Saat clinic admin menolak pesanan:

- Lepaskan reservasi.
- Apabila sudah dibayar online, jalankan alur refund yang sah.
- Jangan menganggap dana otomatis dikembalikan sebelum provider mengonfirmasi.

## 8.4 Stock Movement

Simpan riwayat perubahan stok:

- Pembelian stok.
- Penyesuaian stok.
- Reservasi.
- Pelepasan reservasi.
- Penjualan.
- Pembatalan.
- Pengembalian jika tersedia.

Setiap movement harus menyimpan:

- Clinic ID.
- Product ID.
- Variant ID.
- Movement type.
- Quantity.
- Reference ID.
- Keterangan.
- Actor/user.
- Timestamp.

Jangan mengizinkan perubahan stok tanpa pencatatan yang dapat diaudit.

## 8.5 Perlindungan Transaksi

Gunakan:

- Database transaction.
- Row-level lock.
- Unique constraints.
- Idempotency.
- Validation.
- Atomic stock updates.

Kasus yang wajib ditangani:

- Dua pengguna membeli varian terakhir secara bersamaan.
- Pengguna menekan checkout berkali-kali.
- Callback Midtrans diterima lebih dari sekali.
- Pembayaran berhasil tetapi aplikasi tertutup.
- Payment callback datang setelah order expired.
- Klinik menolak pesanan yang sudah dibayar.
- Pengguna membatalkan pesanan ketika pembayaran sedang diproses.
- Pengambilan barang tercatat lebih dari sekali.

Jangan pernah membiarkan stok negatif.

---

# 9. ARSITEKTUR MIDTRANS — CENTRALIZED PAYMENT SERVICE

## 9.1 Tujuan

Buat satu service pembayaran backend yang dapat menangani transaksi:

- Konsultasi klinik.
- Pet Shop.

Contoh:

| Jenis Transaksi | Nomor Referensi | Klinik |
|---|---|---|
| Konsultasi | CON-XXXX | Happy Paws |
| Pet Shop | SHOP-XXXX | MeowCare |

Satu Payment Service bukan berarti semua uang harus masuk ke satu rekening PetHeal.

**PetHeal menggunakan satu integrasi payment pada level aplikasi, sementara alokasi penerimaan dana dilakukan melalui Merchant ID tujuan sesuai skema Midtrans Partner yang disetujui.**

## 9.2 Wajib Menggunakan Arsitektur Multi-Merchant yang Sah

Model bisnis PetHeal:

- PetHeal adalah platform penghubung.
- Klinik merupakan penjual/penerima dana untuk layanan dan produk miliknya.
- Setiap klinik harus dapat menerima dana transaksinya sendiri.
- PetHeal dapat menerima komisi platform apabila telah ditetapkan, disepakati, dan diizinkan melalui pengaturan Partner.
- Satu transaksi konsultasi atau Pet Shop selalu mempunyai satu klinik pemilik transaksi.

Jangan menggunakan satu akun Merchant biasa PetHeal untuk mengumpulkan pembayaran milik klinik independen yang belum terdaftar sebagai merchant yang sesuai.

Arsitektur pembayaran harus mengacu pada **Midtrans Partner Solutions** atau mekanisme resmi lain yang secara tertulis disetujui Midtrans.

Referensi resmi yang wajib dipelajari:

1. Partner Transaction Split:  
   https://docs.midtrans.com/reference/partner-checkout-advanced-features

2. Perbedaan Merchant dan Partner Account:  
   https://docs.midtrans.com/docs/perbedaan-antara-akun-merchant-dan-akun-partner

3. Pembatasan kategori bisnis dan sub-merchant:  
   https://docs.midtrans.com/docs/can-all-types-of-businesses-accept-payments-through-midtrans

4. Partner HTTP Notification:  
   https://docs.midtrans.com/reference/partner-http-notification

5. Best Practices Notification:  
   https://docs.midtrans.com/reference/best-practices-to-handle-notification

Baca dokumentasi aktual sebelum menulis kode integrasi.

Jangan mengasumsikan bahwa fitur Partner otomatis tersedia di akun yang sekarang digunakan.

## 9.3 Transaction Split

Gunakan fasilitas `transaction_split_settings` apabila skema Partner telah tersedia dan diaktifkan untuk akun PetHeal.

Secara konseptual, dana transaksi dapat dialokasikan kepada:

- Merchant ID klinik.
- Merchant ID PetHeal sebagai platform, jika terdapat komisi yang disepakati.

Contoh ilustrasi:

Total transaksi: Rp100.000.

- Bagian klinik: 95%.
- Bagian PetHeal: 5%.

Persentase tersebut hanya ilustrasi. Jangan menganggap 5% adalah komisi yang sudah ditetapkan.

Sistem harus:

- Mendukung konfigurasi pembagian yang fleksibel.
- Tidak menghardcode persentase.
- Mendukung komisi 0%.
- Menyimpan snapshot aturan pembagian pada transaksi.
- Memastikan persentase pembagian sesuai aturan provider.
- Memastikan Merchant ID penerima benar-benar terdaftar di bawah Partner.
- Menghitung pembulatan rupiah secara konsisten.
- Memastikan jumlah alokasi tidak lebih besar atau lebih kecil dari total yang harus dialokasikan.
- Memisahkan pembagian gross amount dengan pembagian MDR.
- Menyimpan hasil kalkulasi untuk audit.
- Mengikuti konfigurasi fee yang telah disepakati.

Apabila memakai split berbasis persentase, pastikan total persentasenya memenuhi ketentuan Midtrans.

Jika memakai split berbasis nominal, ikuti ketentuan parameter penerima sisa nominal dari dokumentasi aktual.

Jangan mengarang parameter API.

## 9.4 Merchant Registration untuk Setiap Klinik

Buat mekanisme konfigurasi merchant untuk setiap klinik.

Konsep data yang perlu ditangani:

- Clinic ID.
- Provider.
- Merchant ID.
- Status onboarding.
- Status aktivasi.
- Partner registration status.
- Tanggal verifikasi.
- Catatan verifikasi.
- Environment.
- Konfigurasi komisi yang berlaku.
- Informasi status integrasi.

Contoh status onboarding internal:

- `not_started`
- `pending`
- `under_review`
- `approved`
- `active`
- `rejected`
- `suspended`

Nama status dapat disesuaikan, tetapi transisinya harus jelas.

Syarat klinik dapat menerima pembayaran online:

1. Identitas merchant telah diverifikasi sesuai proses bisnis.
2. Merchant ID valid.
3. Merchant terdaftar dalam skema Partner yang digunakan.
4. Konfigurasi penerima telah diverifikasi.
5. Status merchant aktif.
6. Konfigurasi pembayaran sesuai environment.
7. Integrasi provider telah lolos pengujian.

Merchant ID tidak boleh berasal begitu saja dari request Android.

Backend harus mengambil Merchant ID berdasarkan data klinik yang sudah diverifikasi.

`clinic_admin` tidak boleh mengubah Merchant ID tujuan transaksi secara bebas untuk mengalihkan dana.

Pengelolaan konfigurasi merchant hanya boleh dilakukan oleh role yang memang berwenang, dengan audit log.

## 9.5 Perbedaan Akun Partner dan Merchant

Jangan salah mengartikan Partner Account sebagai fitur pembagian dana otomatis.

Partner Account merupakan pengelolaan beberapa Merchant ID di bawah satu Partner ID.

Untuk pembagian transaksi, gunakan kemampuan Transaction Split apabila skema Partner dan merchant penerimanya telah memenuhi syarat.

Pastikan semua rekening dan merchant yang digunakan sesuai proses onboarding Midtrans.

Jangan membuat fitur pencairan dana internal PetHeal yang mengklaim dapat menggantikan settlement provider.

Jangan menjanjikan pencairan instan.

## 9.6 Jika Partner Credentials Belum Tersedia

Ini skenario yang wajib ditangani dengan aman.

Apabila akun Partner, akses Partner, Partner credentials, atau Merchant ID klinik belum tersedia:

- Tetap bangun arsitektur payment service.
- Implementasikan adapter dan kontrak integrasi yang jelas.
- Siapkan konfigurasi environment sandbox.
- Sediakan fallback `Pay at Clinic` sesuai kebijakan.
- Tandai klinik sebagai belum siap menerima pembayaran online.
- Jangan membuat pembayaran online live seolah-olah dana sudah masuk ke rekening klinik.
- Jangan menyimpan pembayaran klinik independen di satu Merchant ID pusat.
- Jangan menggunakan fake split.
- Jangan membuat webhook palsu untuk menandai transaksi sukses.
- Dokumentasikan data onboarding dan konfigurasi yang masih dibutuhkan.

Sistem harus dapat berjalan dalam mode pengujian tanpa mengklaim bahwa settlement multi-klinik sudah aktif di production.

---

# 10. PAYMENT SERVICE — ARSITEKTUR TEKNIS

Implementasikan service pembayaran terpusat.

Nama class dan folder mengikuti pola repository aktual. Contoh konseptual:

- `PaymentService`
- `MidtransPartnerService`
- `MidtransGatewayInterface`
- `PaymentTransaction`
- `PaymentWebhookController`
- `PaymentStatusSynchronizer`
- `PaymentReconciliationService`
- `MerchantConfigurationService`
- `PaymentSplitCalculator`

Jangan membuat semuanya dalam satu controller besar.

Pisahkan tanggung jawab.

## 10.1 Payment Domain

Setiap payment minimal mencatat:

- ID.
- Order ID internal.
- Provider.
- Transaction type.
- Clinic ID.
- User ID.
- Payable reference.
- Midtrans order ID.
- Midtrans transaction ID.
- Partner ID jika relevan.
- Merchant ID penerima.
- Environment.
- Gross amount.
- Currency.
- Payment method.
- Internal payment status.
- Provider transaction status.
- Snap token atau referensi yang diperlukan dengan kebijakan keamanan.
- Payment URL.
- Expiry time.
- Settlement time.
- Refund status.
- Metadata yang diperlukan.
- Timestamps.

Gunakan snapshot agar perubahan konfigurasi klinik di kemudian hari tidak mengubah histori transaksi sebelumnya.

## 10.2 Pemisahan Konsultasi dan Pet Shop

Jangan mencampurkan tabel transaksi bisnis konsultasi dan pesanan Pet Shop menjadi satu tabel tanpa model domain yang jelas.

Kedua domain dapat menggunakan Payment Service yang sama, tetapi harus mempunyai referensi pembayaran yang benar.

Contoh:

`payment_type = consultation`

`payment_type = pet_shop`

Pastikan relasi ke entitas aslinya dapat ditelusuri secara aman.

Gunakan pola relasi polymorphic hanya jika memang cocok dengan desain repository. Jangan menerapkannya hanya karena terlihat praktis.

## 10.3 Membuat Pembayaran

Alur:

1. Android atau Web meminta pembuatan pembayaran.
2. Backend memverifikasi identitas user.
3. Backend memverifikasi ownership transaksi.
4. Backend membaca total harga dari database.
5. Backend membaca clinic ID dari entitas transaksi.
6. Backend mengambil Merchant ID dari konfigurasi klinik yang valid.
7. Backend menghitung alokasi pembayaran.
8. Backend membuat order ID unik.
9. Backend membuat payment record.
10. Backend melakukan request ke Midtrans Partner.
11. Backend menyimpan respons yang diperlukan.
12. Backend mengirim token atau URL pembayaran ke client.

Jangan pernah mempercayai:

- Total pembayaran dari Android.
- Clinic ID yang bebas dikirim client.
- Merchant ID yang dikirim client.
- Persentase komisi yang dikirim client.
- Status pembayaran dari halaman redirect.
- Status transaksi yang diubah melalui request biasa.

Semua keputusan finansial harus berasal dari backend.

## 10.4 Order ID

Gunakan identifier transaksi yang unik dan sulit ditebak.

Order ID harus:

- Unik.
- Tidak berubah untuk satu payment attempt.
- Mampu ditelusuri ke transaksi internal.
- Tidak mengandung data pribadi.
- Memiliki batas panjang sesuai aturan Midtrans.
- Terlindungi dari transaksi duplikat.

Gunakan idempotency agar klik berulang tidak menciptakan transaksi pembayaran ganda.

## 10.5 Snap Checkout

Prioritaskan integrasi Midtrans Snap apabila cocok dengan skema Partner yang disetujui.

Sebelum implementasi:

- Pastikan endpoint Partner yang sesuai.
- Pastikan format request.
- Pastikan Partner credentials.
- Pastikan penggunaan Merchant ID.
- Pastikan transaction split.
- Pastikan flow mobile.
- Pastikan callback dan notification yang benar.

Untuk Android, gunakan alur resmi yang didukung Midtrans. Pilih pendekatan WebView, redirect, atau SDK berdasarkan dokumentasi resmi dan arsitektur aplikasi aktual.

Jangan pernah menaruh Server Key, Partner Server Key, atau secret credentials di Android.

Client hanya menerima token atau informasi transaksi yang memang aman diberikan kepada client.

---

# 11. WEBHOOK, PAYMENT VERIFICATION, DAN REKONSILIASI

Ini adalah bagian kritis. Jangan menandai pembayaran berhasil hanya karena pengguna kembali ke aplikasi setelah checkout.

## 11.1 Webhook

Buat endpoint notification yang sesuai dengan skema Midtrans Partner yang benar.

Wajib:

- Menggunakan HTTPS pada production.
- Memvalidasi signature sesuai jenis integrasi.
- Memvalidasi order ID.
- Memvalidasi gross amount.
- Memvalidasi currency.
- Memvalidasi merchant ID.
- Memvalidasi Partner ID jika tersedia pada flow tersebut.
- Memvalidasi keterkaitan dengan transaksi internal.
- Memastikan status provider valid.
- Memproses callback secara idempotent.

**Perhatian khusus:**

Jangan otomatis menggunakan rumus verifikasi Merchant Notification biasa apabila implementasi menggunakan Partner Notification.

Pelajari skema yang tepat pada dokumentasi Partner HTTP Notification. Gunakan PartnerServerKey atau metode signature yang memang diwajibkan untuk flow tersebut.

Jangan mengarang signature scheme.

Jangan menganggap webhook hanya akan dikirim satu kali.

## 11.2 Mapping Status

Petakan status provider ke status internal.

Minimal tangani:

- `pending`
- `settlement`
- `capture`
- `deny`
- `cancel`
- `expire`
- `refund`
- `partial_refund`
- Status lain yang relevan menurut dokumentasi aktual.

Untuk kartu kredit, periksa `fraud_status` dan ketentuan capture.

Jangan menandai `capture` sebagai berhasil tanpa memeriksa hasil fraud status yang diwajibkan.

Untuk status yang membutuhkan pengecekan lanjutan, lakukan verifikasi server-to-server melalui Transaction Status API.

Status `pending` bukan pembayaran berhasil.

Redirect sukses dari frontend bukan bukti pembayaran berhasil.

## 11.3 Idempotency dan Konsistensi

Jika Midtrans mengirim webhook dua kali:

- Jangan membuat dua transaksi.
- Jangan menambah stok dua kali.
- Jangan menyelesaikan booking dua kali.
- Jangan mencatat pendapatan dua kali.
- Jangan menjalankan refund dua kali.

Gunakan unique key dan transaksi database.

Lakukan locking saat mengubah status pembayaran dan order.

Pastikan status tidak bergerak mundur karena callback terlambat.

Contoh:

Pembayaran sudah `settlement`, kemudian callback lama berisi `pending` datang.

Sistem tidak boleh mengubah status sukses menjadi pending.

## 11.4 Payment Reconciliation

Sediakan mekanisme pengecekan ulang transaksi.

Minimal:

- Mencari transaksi pending.
- Memeriksa status transaksi ke Midtrans.
- Memperbarui status jika sesuai.
- Menyimpan hasil rekonsiliasi.
- Mencatat transaksi yang tidak cocok.
- Menampilkan transaksi yang perlu ditinjau admin.

Gunakan queue atau scheduler sesuai infrastruktur project.

Jangan membuat proses berat yang dijalankan setiap request pengguna.

## 11.5 Audit Trail

Setiap perubahan harus dapat ditelusuri:

- Siapa yang melakukan.
- Waktu.
- Status sebelum.
- Status sesudah.
- Provider.
- Referensi transaksi.
- Sumber perubahan.
- Keterangan.

Pisahkan log webhook dari payment history jika diperlukan.

Jangan menyimpan secret key atau data sensitif yang tidak dibutuhkan di log.

---

# 12. PEMBAYARAN KONSULTASI

Integrasikan Midtrans dengan booking konsultasi yang sudah ada.

Jangan membangun ulang booking system.

Audit terlebih dahulu proses booking saat ini.

Target:

- Pengguna memilih klinik.
- Pengguna memilih dokter atau jadwal sesuai sistem yang tersedia.
- Pengguna membuat booking.
- Backend menghitung biaya konsultasi.
- Payment Service membuat pembayaran.
- Merchant ID tujuan diambil dari klinik pemilik booking.
- Transaction split diterapkan apabila konfigurasi Partner tersedia.
- Status pembayaran diverifikasi.
- Booking diperbarui berdasarkan aturan bisnis yang sudah berlaku.

Pastikan:

- Pembayaran konsultasi tidak tercatat sebagai penjualan Pet Shop.
- Booking tidak dibuat ganda karena retry pembayaran.
- Pembayaran tertunda tidak dianggap lunas.
- Booking yang sudah dibayar tidak menjadi tidak valid karena callback terlambat.
- Pembatalan mengikuti aturan refund.
- Clinic Admin hanya melihat transaksi konsultasi kliniknya sendiri.
- Laporan konsultasi tidak bercampur dengan penjualan produk.

Gunakan kompatibilitas dengan sistem booking existing.

Jangan mengubah jadwal dokter, relasi pasien, atau medical record tanpa alasan yang berkaitan langsung dengan requirement.

---

# 13. PEMBAYARAN PET SHOP

Setiap transaksi Pet Shop harus terhubung dengan satu klinik penjual.

Alur:

1. Pengguna membuat order dari satu klinik.
2. Backend mengunci harga dan quantity berdasarkan database.
3. Backend memvalidasi stok.
4. Backend membuat reservasi.
5. Backend membuat payment record.
6. Backend meminta checkout Midtrans apabila klinik aktif.
7. Pengguna melakukan pembayaran.
8. Midtrans mengirim notifikasi.
9. Backend memverifikasi transaksi.
10. Payment status berubah.
11. Order diteruskan ke proses klinik.
12. Klinik memproses barang.
13. Pengguna mengambil barang.
14. Admin mengonfirmasi penyelesaian.
15. Stok fisik dan laporan penjualan diperbarui.

Satu transaksi tidak boleh mengambil Merchant ID dari klinik lain.

Satu order tidak boleh menggabungkan dua klinik.

Pastikan split penerimaan dana sesuai Merchant ID klinik yang memiliki order tersebut.

---

# 14. REFUND DAN PEMBATALAN

Refund harus ditangani secara benar.

Audit dulu kemampuan refund untuk transaksi Partner yang digunakan.

Jangan menganggap refund transaksi Partner otomatis mengembalikan atau membalikkan semua alokasi split sesuai keinginan aplikasi.

Verifikasi:

- Endpoint refund yang berlaku.
- Siapa merchant yang berwenang melakukan refund.
- Dampak refund ke Merchant ID penerima.
- Dampak terhadap komisi platform.
- Dampak terhadap MDR.
- Status refund.
- Notification yang diterima.
- Mekanisme partial refund jika tersedia.

## 14.1 Alur Refund Pet Shop

Contoh:

Pesanan sudah dibayar, tetapi klinik menolak pesanan karena produk tidak tersedia.

Alur:

1. Pesanan ditandai rejected.
2. Sistem memverifikasi payment status.
3. Jika pembayaran belum berhasil, cukup batalkan dan lepaskan reservasi.
4. Jika pembayaran sudah berhasil, buat refund request yang sah.
5. Simpan referensi refund.
6. Tunggu konfirmasi provider.
7. Jangan langsung mengubah status menjadi refunded sebelum ada bukti konfirmasi.
8. Setelah refund terkonfirmasi, perbarui laporan.
9. Lepaskan reservasi stok.
10. Simpan audit trail lengkap.

## 14.2 Refund Konsultasi

Terapkan aturan pembatalan dan refund konsultasi sesuai kebijakan yang sudah ada.

Jangan membuat kebijakan pengembalian dana baru secara diam-diam.

Jika belum ada aturan, dokumentasikan kebutuhan tersebut dan buat struktur konfigurasi tanpa mengarang kebijakan bisnis.

---

# 15. DESAIN DATABASE DAN MIGRATION

Rancang database berdasarkan hasil audit.

Nama tabel di bawah adalah rancangan domain, bukan perintah untuk membuat tabel dengan nama yang sama tanpa pemeriksaan.

## 15.1 Pet Shop

Entitas yang perlu tersedia:

- Product Categories.
- Clinic Products.
- Product Variants.
- Product Orders.
- Product Order Items.
- Product Order Status Histories.
- Product Stock Movements.
- Product Stock Reservations.

Semua data klinik harus terikat dengan clinic ID.

## 15.2 Payment

Entitas yang perlu tersedia:

- Payments / Payment Transactions.
- Payment Notifications atau Webhook Logs.
- Payment Status Histories.
- Refund Transactions.
- Payment Reconciliation Logs jika diperlukan.
- Merchant Configurations.
- Merchant Onboarding Status.
- Payment Split Snapshots.

Hindari membuat banyak tabel jika existing schema sudah menyediakan sebagian fungsi.

## 15.3 Database Rules

Wajib:

- Foreign key yang sesuai.
- Index pada kolom pencarian.
- Unique constraint pada order ID provider.
- Unique SKU sesuai aturan bisnis.
- Index clinic ID.
- Index payment status.
- Index tanggal transaksi.
- Relasi konsisten.
- Decimal atau integer yang aman untuk nominal uang.
- Hindari float untuk nominal uang.
- Simpan currency.
- Simpan snapshot harga dan produk pada order item.

Gunakan format uang yang konsisten.

Untuk IDR, hindari floating-point calculation.

## 15.4 Migration Safety

Jangan:

- Menghapus tabel existing.
- Menghapus kolom existing tanpa audit.
- Mengganti tipe kolom secara berisiko tanpa data migration.
- Mengubah relasi yang dipakai Android secara sembarangan.
- Menjalankan migration destructive pada data pengguna.

Setiap migration harus:

- Memiliki up yang jelas.
- Memiliki down jika memungkinkan.
- Aman terhadap data lama.
- Dapat dijalankan pada database existing.
- Diuji pada kondisi database bersih.
- Diuji pada kondisi database yang sudah mempunyai data.

---

# 16. API DESIGN DAN BACKEND SECURITY

Gunakan standar API existing.

Contoh kelompok endpoint yang mungkin dibutuhkan:

## Pet Shop User API

- Mendapatkan kategori.
- Mendapatkan daftar produk.
- Mencari produk.
- Mendapatkan detail produk.
- Mendapatkan daftar klinik penjual.
- Membuat order.
- Melihat keranjang jika keranjang disimpan server.
- Membuat checkout.
- Membuat pembayaran.
- Melihat status pembayaran.
- Melihat daftar order.
- Melihat detail order.
- Membatalkan order sesuai ketentuan.

## Pet Shop Clinic Admin API

- Dashboard pet shop.
- CRUD produk.
- CRUD varian.
- CRUD kategori sesuai hak akses.
- Manajemen stok.
- Daftar order.
- Detail order.
- Update status order.
- Riwayat penjualan.
- Laporan penjualan.

## Payment API

- Create payment.
- Get payment status.
- Retry payment apabila aman dan diperbolehkan.
- Request refund jika role dan aturan mengizinkan.
- Payment notification endpoint.
- Reconciliation endpoint internal.

## Security Requirements

Gunakan:

- Authentication.
- Authorization.
- Policy.
- Request validation.
- Rate limiting.
- Database transactions.
- Ownership checks.
- Safe file uploads.
- Secure error handling.

Uji serangan berikut:

- Mengakses order user lain.
- Mengakses produk klinik lain melalui perubahan ID.
- Mengubah clinic ID melalui request.
- Mengubah harga melalui request Android.
- Mengubah Merchant ID melalui request.
- Mengubah status pembayaran secara manual.
- Mengakses laporan klinik lain.
- Mengirim callback palsu.
- Replay webhook.
- Mengirim quantity negatif.
- Membeli stok melebihi jumlah tersedia.
- Upload file dengan ekstensi berbahaya.

Semua kontrol keamanan dilakukan di backend, bukan hanya dengan menyembunyikan tombol di frontend.

---

# 17. MULTI-CLINIC SECURITY

Bagian ini bersifat wajib untuk seluruh modul baru.

Contoh:

Happy Paws Bandung tidak boleh melihat:

- Produk MeowCare Surabaya.
- Stok MeowCare Surabaya.
- Pesanan MeowCare Surabaya.
- Pendapatan MeowCare Surabaya.
- Pembayaran konsultasi MeowCare Surabaya.
- Merchant configuration klinik lain.
- Laporan klinik lain.

Begitu pula sebaliknya.

Aturan:

1. Clinic ID diperoleh berdasarkan context user dan relasi transaksi yang sah.
2. Jangan hanya mempercayai ID dari request.
3. Setiap query harus mempunyai scope yang benar.
4. Setiap controller sensitif harus menggunakan authorization.
5. Setiap payment harus memiliki clinic owner yang tidak dapat diubah client.
6. Setiap webhook harus menemukan transaksi menggunakan referensi internal yang aman.
7. Super Admin memiliki akses sesuai kebijakan platform.
8. Clinic Admin tidak boleh melihat secret atau konfigurasi sensitif merchant.
9. Dokter dan user tidak mendapatkan akses dashboard penjualan jika tidak berwenang.

Tambahkan feature test khusus untuk menguji isolasi multi-tenant.

---

# 18. KEAMANAN FILE UPLOAD DAN DATA PRODUK

Produk memerlukan upload gambar.

Implementasikan:

- Validasi MIME type.
- Validasi ukuran file.
- Batas dimensi bila diperlukan.
- Nama file yang aman.
- Penyimpanan melalui disk konfigurasi Laravel.
- Jangan memakai nama file dari user secara langsung sebagai path.
- Jangan memperbolehkan upload executable.
- Penghapusan gambar lama dengan aturan aman.
- Penanganan upload gagal.
- Optimasi gambar apabila infrastruktur mendukung.

Jangan menghapus gambar produk yang masih direferensikan oleh transaksi historis tanpa memastikan tidak merusak data.

---

# 19. UI/UX VCMS DAN ANDROID

## 19.1 Prinsip Desain

Tampilan harus:

- Modern.
- Konsisten.
- Rapi.
- Responsif.
- Mudah digunakan.
- Tidak berlebihan.
- Konsisten dengan branding PetHeal.
- Mengikuti design system existing.

Jangan membuat halaman yang terlihat berasal dari aplikasi berbeda.

Jangan mengganti warna utama dan typography tanpa kebutuhan.

## 19.2 Admin VCMS

Pastikan:

- Sidebar tetap berfungsi.
- Menu berdasarkan role.
- Form validasi jelas.
- Tabel responsif.
- Search berfungsi.
- Filter berfungsi.
- Pagination berfungsi.
- Modal tidak bertumpuk secara salah.
- Loading state tersedia.
- Empty state tersedia.
- Error message informatif.
- Feedback sukses hanya muncul setelah server berhasil menyimpan.

## 19.3 Android

Pastikan:

- Navigasi lama tidak rusak.
- Back navigation benar.
- Deep link atau payment return ditangani dengan benar.
- Loading state tersedia.
- Error state tersedia.
- Empty state tersedia.
- Token authentication tetap benar.
- Session tetap konsisten.
- Respons API tidak menyebabkan aplikasi crash.
- UI tidak menampilkan fake data.

Jangan menghapus halaman lama hanya untuk menambahkan Pet Shop.

---

# 20. TESTING WAJIB

Jangan menyatakan pekerjaan selesai sebelum menjalankan test yang relevan.

## 20.1 Backend Unit Test

Uji:

- Perhitungan harga.
- Perhitungan diskon.
- Perhitungan total.
- Perhitungan alokasi split.
- Pembulatan nominal.
- Mapping status payment.
- Transisi status order.
- Reservasi stok.
- Pelepasan reservasi.
- Pengurangan stok.
- Refund transition.

## 20.2 Backend Feature Test

Uji:

- User dapat melihat produk aktif.
- User tidak dapat membeli produk nonaktif.
- User tidak dapat membeli stok habis.
- User tidak dapat mengubah harga.
- User tidak dapat membeli melalui Merchant ID klinik lain.
- Clinic Admin hanya mengakses data kliniknya.
- Super Admin dapat mengakses data sesuai kewenangan.
- Order terikat ke klinik yang benar.
- Satu order tidak dapat berisi produk dari klinik berbeda.
- Payment konsultasi tetap berfungsi.
- Payment Pet Shop berfungsi.
- Webhook valid diproses.
- Webhook tidak valid ditolak.
- Webhook duplikat tidak menggandakan transaksi.
- Payment settlement tidak kembali menjadi pending.
- Refund dicatat dengan benar.
- Stok tidak menjadi negatif.

## 20.3 Multi-Clinic Test

Buat skenario:

- User melakukan order di Happy Paws.
- Merchant ID harus mengarah ke Happy Paws.
- Clinic Admin Happy Paws melihat order tersebut.
- Clinic Admin MeowCare tidak dapat mengakses order tersebut.
- Dana split mengarah ke Merchant ID penerima yang benar.
- Platform commission dicatat sesuai konfigurasi.
- Transaksi klinik lain tetap terpisah.

Uji hal yang sama untuk konsultasi.

## 20.4 Midtrans Sandbox Test

Gunakan Sandbox dan skenario pembayaran resmi yang tersedia.

Uji:

- Create payment.
- Snap token.
- Pembayaran berhasil.
- Pembayaran pending.
- Pembayaran gagal.
- Pembayaran expired.
- Pembayaran dibatalkan.
- Callback.
- Signature validation.
- Duplicate notification.
- Status API lookup.
- Refund jika fitur Sandbox mendukung flow tersebut.
- Mapping Merchant ID.
- Transaction split sesuai kemampuan lingkungan Partner Sandbox.

Jangan membuat mock response menjadi bukti bahwa integrasi live telah berhasil.

Mock hanya digunakan dalam unit test atau automated test dengan penandaan yang jelas.

## 20.5 Android Test

Jalankan build yang relevan.

Periksa:

- Compile.
- Navigation.
- API request.
- Authentication.
- Product list.
- Product detail.
- Variant selection.
- Cart.
- Checkout.
- Payment return.
- Order history.
- Error handling.

Jangan hanya mengandalkan screenshot bahwa tampilan berhasil dirender.

## 20.6 Regression Test

Pastikan fitur lama tetap berfungsi:

- Login.
- Register.
- Google authentication jika tersedia.
- Booking konsultasi.
- Jadwal dokter.
- Rekam medis.
- Manajemen obat.
- Stok obat.
- Mutasi obat.
- Monitoring kedaluwarsa.
- Hak akses tiap role.
- Multi-clinic.
- API lama yang digunakan Android.

Fitur baru tidak boleh menyebabkan fitur lama gagal.

---

# 21. ENVIRONMENT DAN CONFIGURATION

Pisahkan environment:

- Local.
- Sandbox.
- Production.

Gunakan environment variables untuk kredensial dan konfigurasi sensitif.

Contoh nama konfigurasi konseptual:

```env
MIDTRANS_ENV=sandbox
MIDTRANS_PARTNER_ID=
MIDTRANS_PARTNER_SERVER_KEY=
MIDTRANS_PARTNER_CLIENT_KEY=
MIDTRANS_PARTNER_MERCHANT_ID=
MIDTRANS_BASE_URL=
MIDTRANS_NOTIFICATION_URL=
MIDTRANS_SNAP_ENABLED=false
```

Nama dan variabel final harus disesuaikan dengan dokumentasi flow Partner yang benar.

Jangan mengasumsikan semua variabel di atas pasti dibutuhkan.

Pisahkan konfigurasi platform dan konfigurasi per klinik.

Jangan simpan secret dalam:

- Source code.
- Git.
- Android.
- Database dalam bentuk plaintext tanpa kebutuhan dan perlindungan.
- Log.
- API response.
- Screenshot.
- Dokumentasi publik.

Jangan meminta atau mencetak secret key yang sudah ada di file environment.

Jika menemukan secret sudah terlanjur masuk Git, laporkan risiko dan sarankan rotasi.

Jangan pernah menggunakan kredensial Sandbox untuk transaksi Live atau sebaliknya.

---

# 22. PENANGANAN KONDISI KHUSUS

Implementasi harus mempertimbangkan kasus berikut.

## Pet Shop

1. Produk tidak aktif.
2. Varian tidak aktif.
3. Stok habis.
4. Stok berubah ketika pengguna checkout.
5. Keranjang berasal dari klinik berbeda.
6. Order tercipta tetapi payment creation gagal.
7. Payment sukses setelah order melewati waktu reservasi.
8. Klinik menolak order yang sudah dibayar.
9. User membatalkan order.
10. Pengguna tidak mengambil barang.
11. Admin mengubah stok secara manual.
12. Harga produk berubah setelah order dibuat.
13. Produk diarsipkan setelah ada histori order.

## Konsultasi

1. Jadwal dokter berubah.
2. Booking dibatalkan.
3. Pembayaran pending.
4. Pembayaran sukses tetapi callback terlambat.
5. Merchant klinik tidak aktif.
6. Booking sudah dibayar.
7. Refund diperlukan.
8. Callback pembayaran diterima dua kali.

## Pembayaran

1. Partner configuration tidak tersedia.
2. Merchant ID klinik belum terdaftar.
3. Merchant dinonaktifkan.
4. Midtrans timeout.
5. Create transaction gagal.
6. Webhook tidak diterima.
7. Signature invalid.
8. Gross amount tidak cocok.
9. Currency tidak cocok.
10. Order ID tidak dikenal.
11. Merchant ID tidak sesuai.
12. Partner ID tidak sesuai.
13. Pembayaran duplicate.
14. Refund pending.
15. Refund gagal.
16. Provider mengalami gangguan.

Setiap kondisi mempunyai hasil yang jelas, aman, dan dapat ditelusuri.

---

# 23. TAHAPAN IMPLEMENTASI

Kerjakan dengan urutan berikut.

## PHASE 1 — Audit dan Architecture Mapping

- Audit repository Laravel.
- Audit Android.
- Audit VCMS.
- Audit database.
- Audit booking flow.
- Audit payment flow.
- Audit multi-clinic.
- Dokumentasikan hasil.
- Tentukan komponen yang bisa digunakan kembali.

## PHASE 2 — Database dan Backend Foundation

- Rancang domain Pet Shop.
- Rancang variant.
- Rancang order.
- Rancang stock reservation.
- Rancang stock movement.
- Rancang Payment Service.
- Rancang Merchant Configuration.
- Buat migration aman.
- Buat model dan relasi.
- Buat validation dan policy.

## PHASE 3 — Pet Shop API

- API katalog.
- API kategori.
- API detail produk.
- API varian.
- API order.
- API checkout.
- API stock reservation.
- API order history.
- API admin management.

## PHASE 4 — VCMS Pet Shop

- Dashboard.
- Produk.
- Kategori.
- Varian.
- Stok.
- Pesanan.
- Riwayat.
- Laporan.
- Role-based access.

## PHASE 5 — Payment Foundation

- Payment Service.
- Gateway interface.
- Transaction records.
- Merchant configurations.
- Payment status mapping.
- Idempotency.
- Webhook.
- Logging.
- Reconciliation.

Bangun adapter khusus Midtrans Partner sesuai dokumentasi resmi.

## PHASE 6 — Midtrans Partner Integration

- Pelajari dan verifikasi Partner Solutions.
- Implementasikan Snap yang sesuai.
- Implementasikan transaction split.
- Validasi Merchant ID.
- Validasi Partner ID.
- Implementasikan callback.
- Implementasikan status verification.
- Implementasikan refund berdasarkan dukungan aktual.
- Uji menggunakan Sandbox.

Jangan mengaktifkan pembayaran live apabila onboarding dan konfigurasi Partner belum terpenuhi.

## PHASE 7 — Integrasi Konsultasi

Hubungkan booking consultation ke Payment Service tanpa menghapus flow lama.

Pastikan status booking dan status pembayaran konsisten.

## PHASE 8 — Android Pet Shop

- Entry point Pet Shop.
- Kategori.
- Product listing.
- Product detail.
- Variant selection.
- Cart.
- Checkout.
- Payment.
- Order history.
- Order detail.
- Timeline.

## PHASE 9 — Integration Testing

Uji seluruh alur:

- Pet Shop.
- Konsultasi.
- Multi-clinic.
- Split.
- Webhook.
- Refund.
- Stock.
- Security.
- Regression.

## PHASE 10 — Documentation dan Final Verification

Buat dokumentasi penggunaan.

Pastikan seluruh test yang relevan berjalan.

Buat catatan konfigurasi Sandbox dan kebutuhan Production.

---

# 24. ATURAN YANG TIDAK BOLEH DILANGGAR

1. Jangan membuat project baru dari nol.
2. Jangan menghapus fitur lama.
3. Jangan menghapus halaman Android yang sudah ada.
4. Jangan mengganti seluruh desain PetHeal.
5. Jangan mengubah modul medis tanpa kebutuhan.
6. Jangan memasukkan produk Pet Shop ke tabel obat.
7. Jangan menggabungkan data multi-klinik.
8. Jangan mengizinkan Clinic Admin mengakses klinik lain.
9. Jangan mempercayai nominal harga dari Android.
10. Jangan mempercayai Merchant ID dari Android.
11. Jangan menaruh secret key di Android.
12. Jangan menggunakan akun Merchant biasa pusat untuk menampung transaksi klinik independen tanpa persetujuan skema resmi Midtrans.
13. Jangan menganggap Partner Account otomatis mengaktifkan Transaction Split.
14. Jangan melakukan live payment sebelum merchant dan skema Partner memenuhi persyaratan.
15. Jangan membuat fake payment success.
16. Jangan mengubah status menjadi paid hanya berdasarkan redirect.
17. Jangan mengabaikan webhook signature.
18. Jangan memproses webhook secara non-idempotent.
19. Jangan mengurangi stok dua kali.
20. Jangan mengizinkan stok negatif.
21. Jangan mencampurkan status order dan payment.
22. Jangan menganggap refund berhasil sebelum dikonfirmasi.
23. Jangan menghitung uang menggunakan float.
24. Jangan mengarang endpoint atau parameter Midtrans.
25. Jangan melakukan migration yang berpotensi merusak data existing.
26. Jangan menyatakan fitur selesai tanpa test dan bukti implementasi.
27. Jangan menggunakan dummy data sebagai data produksi.
28. Jangan menambahkan dependency baru jika dependency existing sudah memenuhi kebutuhan.
29. Jangan mengubah API existing tanpa menguji kompatibilitas.
30. Jangan mengorbankan keamanan demi mempercepat implementasi.

---

# 25. DEFINITION OF DONE

Pekerjaan baru boleh dinyatakan selesai jika seluruh persyaratan yang relevan terpenuhi.

## Backend

- [ ] Pet Shop API berjalan.
- [ ] Product CRUD berjalan.
- [ ] Variant berjalan.
- [ ] Stok berjalan.
- [ ] Stock reservation berjalan.
- [ ] Order berjalan.
- [ ] Role authorization berjalan.
- [ ] Multi-clinic isolation berjalan.
- [ ] Payment Service terintegrasi.
- [ ] Konsultasi tetap berfungsi.
- [ ] Pet Shop payment terintegrasi.
- [ ] Webhook diverifikasi.
- [ ] Idempotency berjalan.
- [ ] Payment reconciliation tersedia.
- [ ] Refund flow ditangani sesuai dukungan provider.

## VCMS

- [ ] Dashboard Pet Shop tersedia.
- [ ] Manajemen produk tersedia.
- [ ] Manajemen kategori tersedia.
- [ ] Manajemen varian tersedia.
- [ ] Manajemen stok tersedia.
- [ ] Manajemen order tersedia.
- [ ] Riwayat penjualan tersedia.
- [ ] Hak akses klinik benar.
- [ ] Tidak terdapat data dummy.

## Android

- [ ] Pet Shop dapat diakses.
- [ ] Katalog berjalan.
- [ ] Detail produk berjalan.
- [ ] Variant selection berjalan.
- [ ] Cart berjalan.
- [ ] Checkout berjalan.
- [ ] Payment flow berjalan.
- [ ] Order history berjalan.
- [ ] UI konsisten.
- [ ] Navigasi lama tidak rusak.
- [ ] Build berhasil.

## Payment

- [ ] Consultation payment terhubung.
- [ ] Pet Shop payment terhubung.
- [ ] Setiap transaksi memiliki clinic owner yang benar.
- [ ] Merchant mapping diverifikasi.
- [ ] Transaction Split sesuai Partner setup.
- [ ] Fee allocation terdokumentasi.
- [ ] Webhook signature benar.
- [ ] Pembayaran tidak bisa dipalsukan melalui frontend.
- [ ] Duplicate callback aman.
- [ ] Refund tidak diklaim berhasil sebelum terkonfirmasi.
- [ ] Sandbox test berhasil.
- [ ] Kebutuhan Production terdokumentasi.

## Regression

- [ ] Login tetap berjalan.
- [ ] Booking tetap berjalan.
- [ ] Dokter dan jadwal tetap berjalan.
- [ ] Medical record tetap berjalan.
- [ ] Inventory obat tetap berjalan.
- [ ] Mutasi obat tetap berjalan.
- [ ] Monitoring kedaluwarsa tetap berjalan.
- [ ] Multi-clinic tetap aman.
- [ ] Android tidak mengalami regression.

---

# 26. FINAL REPORT YANG WAJIB DIBERIKAN

Setelah implementasi, berikan laporan teknis yang transparan.

Format:

## A. Audit Awal
Jelaskan kondisi repository sebelum perubahan.

## B. Fitur yang Berhasil Dibangun
Jelaskan fitur yang benar-benar sudah selesai.

## C. File yang Dibuat dan Diubah
Daftar file penting backend, migration, VCMS, Android, dan konfigurasi.

## D. Database Changes
Jelaskan tabel baru, perubahan kolom, index, foreign key, serta migration yang dijalankan.

## E. API Changes
Daftar endpoint baru dan perubahan endpoint lama.

## F. Payment Architecture
Jelaskan bagaimana konsultasi dan Pet Shop menggunakan Payment Service yang sama.

## G. Midtrans Partner
Jelaskan:
- Cara merchant clinic dipetakan.
- Cara Merchant ID dipilih.
- Cara Transaction Split dikalkulasi.
- Cara webhook diverifikasi.
- Cara settlement dan refund diproses.
- Persyaratan Midtrans yang masih harus dipenuhi.

Jangan menyatakan settlement multi-klinik telah aktif di production apabila belum ada persetujuan, konfigurasi Partner, dan bukti pengujian yang sesuai.

## H. Security
Jelaskan hasil pengujian multi-clinic isolation dan keamanan pembayaran.

## I. Testing
Tuliskan command yang benar-benar dijalankan beserta hasilnya.

## J. Known Issues
Jelaskan bug atau keterbatasan yang masih tersisa.

## K. Production Readiness
Pisahkan:
- Sudah siap.
- Masih membutuhkan konfigurasi.
- Masih membutuhkan approval Midtrans.
- Belum diuji.

## L. Cara Menjalankan
Berikan command yang dibutuhkan untuk menjalankan backend dan Android serta instruksi konfigurasi environment.

Jangan mengaku berhasil menjalankan command apabila memang belum dijalankan.

---

# 27. INSTRUKSI TERAKHIR

Mulailah dengan membaca repository PetHeal secara menyeluruh.

Jangan langsung menulis kode berdasarkan asumsi.

Pahami backend Laravel 12, database MySQL, VCMS, aplikasi Android, booking konsultasi, dan sistem multi-clinic yang telah ada.

Setelah memahami kondisi aktual, implementasikan PetHeal Pet Shop dan Centralized Payment Service dengan Midtrans Partner Solutions secara terstruktur.

Prioritaskan keamanan data, keamanan dana, integritas stok, dan kompatibilitas fitur lama.

**Target akhir: PetHeal menjadi platform multi-klinik yang memungkinkan setiap klinik mengelola produk Pet Shop sendiri, menerima pesanan sendiri, dan menerima bagian dana transaksinya sendiri melalui skema pembayaran Midtrans Partner yang sah, sementara pembayaran konsultasi tetap berjalan menggunakan Payment Service yang sama.**

Pastikan seluruh fitur bekerja secara nyata dari Android, API Laravel, database, VCMS, hingga payment notification. Tidak boleh ada tombol palsu, angka dummy, transaksi yang hanya terlihat sukses di frontend, atau status pembayaran yang tidak diverifikasi.

Kerjakan secara bertahap, audit setiap perubahan, jalankan pengujian, dan berikan laporan final berdasarkan hasil implementasi yang benar-benar telah dilakukan.