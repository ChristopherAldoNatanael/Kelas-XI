l;# 🔧 Konfigurasi Google Sign-In untuk Firebase

## Setup Google Sign-In di Firebase Console

1. Buka Firebase Console: https://console.firebase.google.com/
2. Pilih project: `waves-of-food-9af5f`
3. Masuk ke Authentication > Sign-in method
4. Enable Google provider
5. Klik tombol "Configure"
6. Download file `google-services.json` yang baru

## Web Client ID

Setelah mengaktifkan Google Sign-In, Anda akan mendapatkan Web Client ID.
Salin Web Client ID tersebut dan ganti di file:

`app/src/main/res/values/strings.xml`

```xml
<string name="default_web_client_id">YOUR_ACTUAL_WEB_CLIENT_ID_HERE</string>
```

## SHA-1 Fingerprint

Untuk development, Anda perlu menambahkan SHA-1 fingerprint:

1. Buka terminal di root project
2. Jalankan command:
   ```
   ./gradlew signingReport
   ```
3. Salin SHA1 fingerprint untuk debug
4. Tambahkan di Firebase Console > Project Settings > Your apps > SHA certificate fingerprints

## Testing

Setelah konfigurasi selesai:

1. Rebuild project
2. Test Google Sign-In
3. Periksa Firebase Console > Authentication > Users untuk melihat user yang berhasil login

## Troubleshooting

Jika Google Sign-In tidak bekerja:

1. Pastikan `google-services.json` sudah di-update
2. Pastikan Web Client ID sudah benar
3. Pastikan SHA-1 fingerprint sudah ditambahkan
4. Clean dan rebuild project
