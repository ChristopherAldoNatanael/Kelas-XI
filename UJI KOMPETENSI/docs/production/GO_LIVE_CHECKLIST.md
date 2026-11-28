# Checklist Go-Live PetHeal

Dokumen ini adalah gerbang terakhir sebelum produksi. Jangan menganggap aplikasi
siap produksi hanya karena semua test lulus.

## Status saat ini

Hasil `php artisan app:production-check` pada 28 November 2026:

```text
GAGAL: 4 pemeriksaan wajib
PERINGATAN: 10
```

Kegagalan wajib saat ini:

```text
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
Queue backlog: 5 job, tertua sekitar 171 hari
```

Peringatan utama:

- config/route cache belum dibuat;
- `SESSION_SECURE_COOKIE` belum true;
- CSP masih report-only;
- cache memakai file;
- queue worker dan scheduler tidak dapat dideteksi dari command;
- Midtrans masih sandbox;
- OPcache belum terdeteksi dari CLI.

## 1. Environment produksi

Di server produksi, isi `.env` dengan nilai berikut. Jangan memakai nilai lokal.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com
APP_KEY=base64:...
APP_TIMEZONE=UTC

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true

CACHE_STORE=redis
QUEUE_CONNECTION=redis

LOG_CHANNEL=daily
LOG_LEVEL=warning
```

Bila belum memakai Redis, `database` untuk cache/queue masih bisa dipakai, tetapi
Redis lebih baik untuk worker dan cache produksi.

Jalankan:

```bash
php artisan key:generate --show
```

Simpan `APP_KEY` di password manager. Jangan pernah commit `.env`.

## 2. HTTPS dan reverse proxy

Wajib:

- domain memakai HTTPS;
- redirect HTTP → HTTPS;
- HSTS aktif;
- `TRUSTED_PROXIES` hanya berisi IP/CIDR proxy milik Anda;
- `APP_URL` dan `ASSET_URL` memakai domain HTTPS yang sama.

Contoh nginx ada di:

```text
deploy/nginx/petheal.conf
```

Contoh Apache ada di:

```text
public/.htaccess
docs/performance/ASSET_CACHE_NGINX.md
```

## 3. Queue worker

Pilih salah satu:

### Supervisor

```bash
sudo cp deploy/supervisor/petheal-worker.conf /etc/supervisor/conf.d/petheal-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status petheal-worker:*
```

### systemd

```bash
sudo cp deploy/systemd/petheal-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now petheal-queue
sudo systemctl status petheal-queue
```

Setelah worker hidup, periksa:

```bash
php artisan queue:monitor default:100
php artisan app:production-check
```

5 job FCM lama harus diperiksa isinya sebelum diproses karena dapat mengirim
notifikasi nyata.

## 4. Scheduler

Pasang cron:

```bash
sudo cp deploy/cron/petheal /etc/cron.d/petheal
sudo chmod 644 /etc/cron.d/petheal
```

Atau systemd timer:

```bash
sudo cp deploy/systemd/petheal-scheduler.* /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now petheal-scheduler.timer
```

Verifikasi:

```bash
php artisan schedule:list
```

## 5. Deploy

Gunakan:

```bash
APP_DIR=/var/www/petheal/current ./deploy/scripts/deploy.sh
```

Script melakukan:

- maintenance mode;
- `composer install --no-dev`;
- `npm ci && npm run build`;
- migration;
- config/route/view/event cache;
- thumbnail;
- queue restart;
- keluar maintenance.

## 6. Backup dan restore

Backup:

```bash
sudo mkdir -p /etc/petheal
sudo install -m 600 /path/backup.cnf /etc/petheal/backup.cnf

MYSQL_CNF=/etc/petheal/backup.cnf \
BACKUP_DIR=/var/backups/petheal \
./deploy/scripts/backup.sh
```

Yang harus dibackup:

1. `MEDICAL_RECORDS_ATTACHMENT_KEY`;
2. database;
3. `storage/app`;
4. `.env` produksi;
5. konfigurasi nginx/Supervisor.

Uji restore minimal sekali di database terpisah. Backup yang belum pernah
direstore belum bisa dipercaya.

## 7. UAT wajib

- Login admin, dokter, dan super admin.
- Booking dari Android.
- Check-in dan antrian.
- Walk-in dan darurat.
- Rekam medis dibuat dokter.
- Finalisasi rekam medis.
- Resep dan pengurangan stok.
- Pembayaran Midtrans sandbox.
- Refund.
- PDP: ajukan, verifikasi, selesai, unduh berkas.
- Pet Shop: pesanan, pembayaran, pickup.
- Dark mode.
- Mobile 390px.
- Chrome, Edge, Firefox, Safari.
- Satu perangkat Android nyata.

## 8. Keamanan sebelum go-live

- `APP_DEBUG=false`.
- CSP masih report-only; pantau laporan sebelum enforcement.
- Pastikan folder `storage` dan `.env` tidak bisa diakses publik.
- Pastikan `public/storage` hanya menunjuk ke storage publik.
- Rotasi kredensial yang pernah tampil di log/chat.
- Batasi akses phpMyAdmin/SSH.
- Aktifkan firewall.
- Aktifkan backup otomatis.
- Pastikan email OTP benar-benar terkirim.
- Pastikan Midtrans production key dan mode production.

## 9. Performance gate

- Aset memiliki gzip/brotli.
- Aset memiliki `Cache-Control` satu tahun.
- Thumbnail sudah dibuat:
  ```bash
  php artisan media:thumbnails --width=96,192,320
  ```
- Dashboard query ≤ 35.
- Halaman dokter ≤ 500 KB.
- Halaman rekam medis ≤ 500 KB.
- Tidak ada console error.
- `/api/health` tidak menunjukkan queue backlog.
- Lakukan load test minimal 50 pengguna bersamaan.

## 10. Perintah gerbang akhir

```bash
php artisan test
php artisan app:production-check
curl -I https://domain-anda.com
curl -I -H 'Accept-Encoding: gzip' https://domain-anda.com/build/assets/app-xxxx.css
```

Produksi baru boleh dibuka jika:

- `app:production-check` tidak memiliki status FAIL;
- queue worker hidup;
- scheduler hidup;
- backup dan restore sudah diuji;
- UAT selesai;
- HTTPS aktif.
