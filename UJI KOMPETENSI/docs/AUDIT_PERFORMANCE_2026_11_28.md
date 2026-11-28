# Audit Performa PetHeal — 28 November 2026

Sifat: pengukuran browser, query counter, EXPLAIN database, dan audit statis.
Bukan load test dan bukan benchmark perangkat produksi.

Status perbaikan: lihat `docs/performance/PERFORMANCE_HANDOFF.md`.

## Ringkasan

| Area | Kondisi |
|---|---|
| Query per halaman | Baik–sedang. Dashboard 40 query, Medicines 24 query, halaman lain 1–11. |
| N+1 besar | Tidak ada N+1 kritis pada daftar utama. |
| Query lambat | Tidak ada query > 20 ms pada data saat ini. |
| Payload gambar | **Buruk** pada halaman dokter dan rekam medis. |
| Kompresi web | **Tidak aktif** untuk HTML/CSS/JS. |
| Cache aset | **Tidak ada header cache** pada aset statis. |
| JS global | **276 KB** dimuat di semua halaman admin, termasuk Chart.js. |
| API | Ringan; dashboard 18 query, katalog dokter 17 query. |
| Index database | Baik untuk filter utama, tetapi `whereDate` dan `LIKE '%...%'` membatasi penggunaan index. |

---

# P1 — prioritas tinggi

## P1.1 Halaman dokter mengirim 8,1 MB gambar

Hasil pengukuran browser:

```text
/admin/doctors  total 8.130 KB
img             7.641 KB
```

Ukuran file:

```text
1.951 KB  1408x768  PNG
1.929 KB  1408x768  PNG
1.846 KB  1408x768  PNG
1.802 KB  1408x768  PNG
```

Foto tampil hanya **40x40 px** di tabel.

Masalah tambahan:

```blade
src="{{ $doctor->photo_url }}?t={{ time() }}"
```

`time()` berubah setiap request, sehingga browser **tidak pernah memakai cache**
dan mengunduh ulang 7,6 MB setiap kali halaman dibuka.

Dampak:
- halaman dokter paling berat di aplikasi;
- boros kuota, terutama lewat ngrok dan perangkat mobile;
- memperlambat LCP dan konsumsi memori browser.

Perbaikan:
- hapus `?t=time()`; path foto sudah berubah saat upload baru;
- buat thumbnail 96px/160px;
- gunakan `srcset`;
- konversi PNG besar ke WebP/JPEG;
- tambahkan `loading="lazy"` dan `decoding="async"`.

## P1.2 Halaman rekam medis mengirim 2,8 MB gambar

```text
/admin/medical-records  total 3.348 KB
img                     2.810 KB
```

Foto terbesar:

```text
2.246 KB  3120x4160  JPEG
```

Foto ditampilkan sekitar **40x40 px**.

Perbaikan:
- buat thumbnail 96px untuk daftar;
- simpan foto asli hanya untuk halaman detail;
- gunakan `srcset` dan `loading="lazy"`.

## P1.3 HTML, CSS, dan JS tidak dikompresi

Hasil `curl`:

```text
CSS 149.303 byte tanpa Content-Encoding
JS  282.699 byte tanpa Content-Encoding
HTML tanpa gzip
```

`CompressJsonResponse` hanya mengompres JSON API GET di atas 1 KB. HTML panel dan
aset statis tidak dikompresi.

Perkiraan:
- CSS 146 KB dapat menjadi sekitar 21 KB dengan gzip;
- JS 276 KB dapat menjadi sekitar 97 KB;
- HTML dapat turun 60–75%.

Perbaikan:
- aktifkan gzip/brotli di nginx/Apache;
- tambahkan aturan di `public/.htaccess` untuk Apache;
- pastikan `gzip_static on` atau `brotli` di nginx.

## P1.4 Aset statis tidak memiliki header cache

Hasil `curl` untuk CSS dan JS:

```text
tidak ada Cache-Control
tidak ada ETag
tidak ada Last-Modified
```

`CacheStaticAssets` tidak membantu untuk file statis karena request aset dilayani
langsung oleh web server, bukan melewati Laravel.

Perbaikan:
- aset `public/build/*` memakai `Cache-Control: public, max-age=31536000, immutable`;
- file non-hash memakai ETag/Last-Modified;
- konfigurasi harus berada di nginx/Apache, bukan middleware Laravel.

## P1.5 Chart.js ikut di semua halaman

Build JS:

```text
app.js 276 KB (98 KB gzip)
```

`app.js` mengimpor `chart.js/auto` secara global:

```js
import Chart from 'chart.js/auto';
window.Chart = Chart;
```

Chart hanya dipakai dashboard, tetapi seluruh halaman admin membayar 276 KB JS.

Perbaikan:
- pindahkan Chart.js ke dynamic import khusus dashboard;
- atau buat entry terpisah `dashboard.js`;
- target: halaman non-dashboard turun ke bawah 100 KB JS.

---

# P2 — penting

## P2.1 Dashboard menjalankan 40 query

Query berulang:

```text
6x count bookings by payment_status
4x sum medical_records
2x users
2x pets
```

Waktu ukur test: sekitar 135 ms untuk query + render awal. Di produksi data lebih
besar akan menambah biaya.

Perbaikan:
- gabungkan 6 count status menjadi satu query `GROUP BY payment_status`;
- gabungkan 4 agregat pendapatan menjadi satu query `CASE WHEN`;
- gabungkan data pengguna/hewan yang berulang.

Target: 40 query menjadi sekitar 20–24.

## P2.2 Halaman Medicines menjalankan 24 query

```text
18x select value from app_settings where key = ?
```

`AppSetting::getValue()` memakai cache, tetapi pada cache dingin setiap key
melakukan query sendiri. Halaman ini memanggil banyak setting.

Perbaikan:
- ambil semua setting sekaligus lalu cache satu array;
- atau cache `AppSetting::allValues()`;
- setelah cache hangat, query menjadi 0.

## P2.3 `whereDate()` menghalangi index tanggal

Perbandingan EXPLAIN pada `medical_records`:

```text
whereDate: key=idx_mr_clinic_created rows=11
range:     key=idx_mr_clinic_created rows=1
```

`whereDate('created_at', ...)` membungkus kolom dengan `DATE()` sehingga index
hanya dipakai untuk `clinic_id`, bukan rentang tanggal.

Ditemukan pada:
- filter rekam medis;
- filter pembayaran;
- filter mutasi obat;
- filter pesanan/pickup Pet Shop;
- dashboard `whereDate(created_at, today())`.

Perbaikan:
- gunakan `where('created_at', '>=', $startOfDay)`
  dan `where('created_at', '<=', $endOfDay)`;
- untuk kolom DATE murni seperti `booking_date`, gunakan `where('booking_date', $date)`.

## P2.4 Pencarian `%kata%` tidak memakai index

Ditemukan di:
- booking;
- pengguna;
- dokter;
- obat;
- Pet Shop;
- layanan;
- walk-in;
- pencarian global command palette.

`LIKE '%term%'` tidak dapat memakai B-tree index. Pada tabel besar akan menjadi
full scan.

Perbaikan:
- gunakan prefix `term%` bila memungkinkan;
- atau MySQL FULLTEXT;
- atau search engine terpisah bila data sudah besar.

## P2.5 API dashboard 18 query untuk payload 324 byte

Pengukuran API:

```text
/api/dashboard             18 query   324 byte    60 ms
/api/doctors               17 query  19.552 byte  19 ms
/api/bookings               9 query   9.092 byte  26 ms
/api/pet-shop/products      7 query   8.388 byte  40 ms
/api/medical-records/summary 2 query      96 byte   3 ms
```

`/api/dashboard` menjalankan banyak agregat untuk respons sangat kecil.
Gabungkan agregat seperti dashboard web.

## P2.6 28 dari 30 gambar tidak memakai lazy loading

Hampir semua `<img>` di halaman admin tidak memiliki:

```html
loading="lazy"
decoding="async"
```

Untuk foto di bawah lipatan layar, browser langsung mengunduh semuanya.

## P2.7 Font eksternal menambah jalur render

Layout memuat:

- Google Fonts Inter;
- Google Material Symbols.

Sudah ada `preconnect`, tetapi masih ada 2 request CSS eksternal dan file font.
Pada koneksi ngrok, ini menambah waktu render.

Perbaikan:
- self-host font yang benar-benar dipakai;
- subset Material Symbols;
- preload font utama.

## P2.8 Tidak ada APM atau query monitoring produksi

Belum ada:
- query count per request;
- slow query threshold;
- tracing;
- metrik LCP/INP;
- alert jika query atau response melewati batas.

Audit ini memakai probe sementara; tidak ada monitoring permanen.

Rekomendasi:
- middleware query counter di staging;
- log query > 500 ms;
- metrik per halaman;
- alert queue backlog yang sudah ditambahkan di `/api/health`.

## P2.9 Belum ada optimasi gambar massal

Upload baru sudah dikompres lewat `ImageService`, tetapi file lama/seed tetap
berukuran 1,8–2,2 MB. Tidak ada command untuk memproses ulang file lama.

Perbaikan:
- command `media:optimize`;
- backup lalu konversi thumbnail;
- jangan ubah file asli tanpa persetujuan.

## P2.10 DOM halaman cukup besar

Jumlah elemen:

```text
Medicines       1.033
Bookings          948
Payments          773
Medical records   713
Dashboard         666
```

Bukan masalah saat ini, tetapi tabel dan kartu yang berat akan memperlambat
interaksi pada perangkat rendah.

---

# P3 — pantau

- CSS 146 KB; Tailwind belum dipisah per halaman.
- `app.js` 276 KB dan dimuat di semua halaman.
- `jsQR` 128 KB terpisah dan hanya dimuat di halaman pickup.
- Tidak ada preload untuk bundle utama.
- Tidak ada pengukuran performa pada perangkat Android nyata.
- Tidak ada load test untuk 100+ pengguna bersamaan.
- Data saat audit masih kecil: 56 booking, 14 rekam medis, 76 obat. Hasil query
  belum mencerminkan data produksi bertahun-tahun.

---

# Rekomendasi urutan

1. Hapus `?t=time()` pada foto dokter.
2. Buat thumbnail 96px dan `srcset` untuk dokter, hewan, dan rekam medis.
3. Aktifkan gzip/brotli dan cache aset di web server.
4. Pindahkan Chart.js ke dashboard saja.
5. Gabungkan agregat dashboard dan AppSetting.
6. Ganti `whereDate` dengan rentang tanggal.
7. Tambahkan lazy loading dan pengukuran APM.
8. Optimasi pencarian `%kata%` bila data sudah besar.

# Bukti yang sudah baik

- Query halaman utama rendah: settings 1, walk-in 2, dokter 3, queue 4.
- Tidak ada query > 20 ms pada data saat ini.
- Index inti tersedia dan EXPLAIN memakai index untuk clinic scope.
- JSON API GET di atas 1 KB sudah gzip dengan `Vary: Accept-Encoding`.
- Android summary endpoint hanya 2 query dan 96 byte.
- Halaman mobile tidak mengalami overflow horizontal.
- Logo sudah turun dari 351 KB menjadi 22 KB.
- Queue backlog sekarang terlihat di `/api/health`.
