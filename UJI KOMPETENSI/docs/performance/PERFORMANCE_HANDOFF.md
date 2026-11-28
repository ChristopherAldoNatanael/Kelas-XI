# Handoff Optimasi Performa

Tanggal: 28 November 2026  
Status: implementasi utama selesai, menunggu hasil full regression terakhir.

## Hasil sebelum → sesudah

Diukur dengan browser 1440x960 pada server lokal.

| Halaman | Transfer awal | Transfer akhir | Gambar awal | Gambar akhir |
|---|---:|---:|---:|---:|
| Dokter | 8.130 KB | **320 KB** | 7.641 KB | **31 KB** |
| Rekam medis | 3.348 KB | **376 KB** | 2.810 KB | **37 KB** |
| Booking | 1.092 KB | **421 KB** | 500 KB | **30 KB** |
| Pembayaran | 565 KB | **362 KB** | 24 KB | **24 KB** |
| Ruang Klinis | 501 KB | **299 KB** | 24 KB | **24 KB** |

Query:

| Halaman | Awal | Akhir |
|---|---:|---:|
| Dashboard | 40 | **32** |
| Medicines | 24 | **7** |
| Bookings | 6 | **6** |
| Medical records | 9 | **9** |

Bundle:

| Aset | Awal | Akhir |
|---|---:|---:|
| `app.js` | 276 KB | **75,7 KB** |
| Chart.js | global 276 KB | **206 KB hanya dashboard** |
| `dashboard.js` | tidak ada | 206,65 KB, hanya dimuat dashboard |

## 1. Thumbnail gambar

File baru:

```text
app/Support/ImageVariant.php
app/Console/Commands/GenerateImageThumbnails.php
```

Cara kerja:

- sumber selalu file asli;
- thumbnail WebP dibuat di:
  ```text
  storage/app/public/thumbnails/{width}/{path}.webp
  ```
- jika sumber berubah mtime, thumbnail dibuat ulang;
- jika gagal, URL asli dipakai sebagai fallback;
- URL eksternal tidak diubah.

Perintah:

```bash
php artisan media:thumbnails --width=96,192,320
php artisan media:thumbnails --width=96,192,320 --dry-run
```

Sudah dijalankan untuk data saat ini:

```text
57 proses, 0 gagal
42 file thumbnail
total 352 KB
```

Halaman yang memakai thumbnail:

- daftar dokter;
- detail dokter;
- edit dokter;
- profil dokter;
- daftar rekam medis;
- detail rekam medis (admin dan dokter);
- booking;
- pengguna;
- dashboard.

Semua `?t=time()` pada URL foto sudah dihapus. Path foto berubah saat upload
baru, jadi cache tidak perlu di-bust setiap request.

## 2. Chart.js

Sebelumnya:

```js
// resources/js/app.js
import Chart from 'chart.js/auto';
window.Chart = Chart;
```

Sekarang:

```text
resources/js/dashboard.js
```

dimuat hanya oleh `resources/views/admin/dashboard.blade.php`:

```blade
@push('scripts')
    @vite('resources/js/dashboard.js')
@endpush
```

`vite.config.js` menambahkan entry:

```js
'resources/js/dashboard.js'
```

Halaman non-dashboard tidak lagi mengunduh Chart.js.

## 3. Query dashboard

Empat agregat pendapatan digabung menjadi satu query dengan `CASE WHEN`.
Enam count status pembayaran digabung menjadi satu query.

Perubahan ada di:

```text
app/Http/Controllers/Admin/DashboardController.php
```

Gunakan rentang tanggal eksplisit, bukan `whereDate()`.

## 4. AppSetting

Sebelumnya:

```php
Cache::remember("app_setting_{$key}", ...)
```

Satu halaman bisa memanggil 18 key berbeda dan melakukan 18 query saat cache
dingin.

Sekarang:

```php
AppSetting::allValues()
```

mengambil semua setting dalam satu query dan menyimpannya di:

```text
app_settings_all_v1
```

`setValue()` menghapus cache agregat dan cache key lama.

## 5. whereDate → rentang index-friendly

Diubah pada:

- booking;
- queue;
- walk-in;
- jadwal dokter;
- rekam medis;
- pembayaran;
- mutasi obat;
- pesanan dan penjualan Pet Shop;
- dashboard API dan web.

Aturan untuk agent berikutnya:

- kolom `DATE` murni seperti `booking_date`: gunakan `where('booking_date', $date)`;
- kolom `DATETIME` seperti `created_at`/`updated_at`:
  ```php
  ->where('created_at', '>=', $start->startOfDay())
  ->where('created_at', '<=', $end->endOfDay())
  ```
- **jangan** memakai `whereDate()` pada kolom yang punya index rentang.

## 6. Kompresi dan cache aset

Apache:

```text
public/.htaccess
```

sudah berisi aturan `mod_deflate`, `mod_expires`, dan `mod_headers`.

nginx:

```text
docs/performance/ASSET_CACHE_NGINX.md
```

Penting: middleware `CacheStaticAssets` tidak menangani file statis yang
dilayani langsung web server. Konfigurasi harus ada di Apache/nginx.

## 7. Endpoint health

`/api/health` sekarang menyertakan:

```json
{
  "queue": {
    "status": "warning",
    "message": "5 job(s) pending, oldest 14843074s",
    "pending": 5,
    "oldest_age_seconds": 14843074
  }
}
```

Ini tidak memperbaiki worker, hanya membuat backlog terlihat oleh monitoring.

## 8. Batasan yang masih ada

- Queue worker tetap harus dijalankan di server.
- 5 job FCM lama belum diproses/dihapus.
- Kompresi dan cache aset baru berlaku setelah konfigurasi web server diarahkan
  ke aturan Apache/nginx di atas.
- Thumbnail harus dibuat di server produksi setelah deploy:
  ```bash
  php artisan media:thumbnails --width=96,192,320
  ```
- Gambar sumber lama tetap besar; thumbnail hanya mengurangi yang dikirim ke
  halaman daftar.
- Pencarian `LIKE '%kata%'` belum diubah ke FULLTEXT.
- Belum ada APM produksi.
- Belum ada load test.

## 9. Verifikasi yang harus dijalankan setelah deploy

```bash
php artisan media:thumbnails --width=96,192,320
php artisan view:cache
npm run build
php artisan test
```

Cek manual:

- foto dokter/hewan tampil;
- dashboard chart tampil;
- tidak ada console error;
- `curl -I` aset menunjukkan `Cache-Control` dan `Content-Encoding` sesuai
  konfigurasi server.
