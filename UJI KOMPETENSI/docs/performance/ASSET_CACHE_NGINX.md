# Cache Aset dan Kompresi di nginx

`public/.htaccess` hanya dipakai Apache. Untuk nginx, tambahkan konfigurasi
berikut di dalam blok `server` yang melayani PetHeal.

```nginx
gzip on;
gzip_comp_level 5;
gzip_min_length 1024;
gzip_vary on;
gzip_proxied any;
gzip_types
    text/plain
    text/css
    text/xml
    application/javascript
    application/json
    application/xml
    image/svg+xml;

# Opsional bila modul brotli tersedia
brotli on;
brotli_comp_level 5;
brotli_types text/plain text/css application/javascript application/json image/svg+xml;

location /build/ {
    try_files $uri =404;
    access_log off;
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
}

location ~* \.(?:png|jpe?g|webp|gif|svg|ico|woff2?|ttf)$ {
    try_files $uri =404;
    access_log off;
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Catatan:

- Jangan menambahkan `immutable` pada HTML atau JSON dinamis.
- File di `public/build` memiliki hash pada nama, jadi aman di-cache satu tahun.
- Foto dokter/hewan memakai nama file baru saat upload, sehingga URL berubah
  ketika foto diganti.
- Setelah mengubah konfigurasi, uji:

```bash
curl -I https://domain-anda/build/assets/app-xxxx.css
curl -I -H 'Accept-Encoding: gzip' https://domain-anda/build/assets/app-xxxx.css
```

Yang diharapkan:

```text
Content-Encoding: gzip
Cache-Control: public, max-age=31536000, immutable
Vary: Accept-Encoding
```
