# ACCEPTANCE.md — Kriteria Selesai + Perintah Tes

## Kriteria (semua HARUS hijau)
1. `GET /public/clinics` tampil ≥3 klinik demo beda alamat/warna/logo; `GET /public/clinics/{slug}` detail + counts benar.
2. Login `clinic_admin` klinik A TIDAK melihat dokter/booking/layanan/pasien klinik B (web + API). `super_admin` bisa switch dan melihat semua.
3. Daftar admin baru → status pending → approve super_admin → aktif HANYA di klinik itu.
4. Android fresh install → `clinic_picker` muncul → pilih klinik B → nama/logo/warna/alamat/list dokter berubah → list dokter HANYA klinik B.
5. Booking silang ditolak: user klinik B + `doctor_id` klinik A → `422`/`403` dengan pesan jelas. Direct `GET /bookings/{id}` milik klinik lain → `403`.
6. Tidak ada regresi: `GET /api/health` success, `/admin/login` 200 (lokal + ngrok), `/` 200 (tidak redirect maintenance), tidak ada `Unsupported SSL request` bila diakses via jalur benar.
7. Backward compat: field lama tetap ada, response lama + `clinic:{...}`, data lama masih bisa dibaca (milik klinik default).

## Perintah Tes Cepat
```powershell
cd "C:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Backend"
php artisan config:clear; php artisan cache:clear
php artisan route:list --path=public
php artisan route:list --path=admin | Select-Object -First 20

# Lokal
curl.exe -s --max-time 10 http://127.0.0.1:8000/ -o NUL -w "root:%{http_code}\n"
curl.exe -s --max-time 10 http://127.0.0.1:8000/admin/login -o NUL -w "login:%{http_code}\n"
curl.exe -s --max-time 10 http://127.0.0.1:8000/public/clinics | Select-Object -First 5

# Ngrok (ganti URL jika berubah)
$B="https://envious-reselect-darn.ngrok-free.dev"
curl.exe -s --max-time 15 "$B/api/health"
curl.exe -s --max-time 15 "$B/public/clinics"
curl.exe -s --max-time 15 "$B/admin/login" -o NUL -w "%{http_code}\n"
```
```powershell
cd "C:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Android"
.\gradlew.bat assembleDebug
```
## Tes Silang (manual, wajib catat hasil di PROGRESS.md)
- Ambil `doctor_id` klinik A, login token user klinik B → `POST /api/bookings` dengan doctor itu → harap `422/403`.
- Login `clinic_admin` A di web → buka `/admin/doctors` → pastikan tidak ada nama dokter klinik B.
- Android: pilih klinik B → cek dokter → ganti klinik A → cek dokter berubah + cache lama tidak bocor.
