# TESTING.md — Skenario Uji Manual + Data Demo

## Data Demo (seed Fase 3)
- Klinik 1: `PetHeal Pusat` slug `petheal-pusat`, Jakarta, telp 021-xxxx, warna `#18C964`, dokter: Dr. Budi (Umum), Dr. Sari (Bedah); layanan: Checkup 100rb, Vaksin 150rb.
- Klinik 2: `Happy Paws Bandung` slug `happy-paws`, Bandung, warna `#0EA5A5`, dokter: Dr. Andi (Kulit); layanan: Grooming 80rb.
- Klinik 3: `MeowCare Surabaya` slug `meowcare`, Surabaya, warna `#F59E0B`, dokter: Dr. Rina (Kucing); layanan: Steril 500rb.
- Akun: `super@petheal.com` (super_admin), `admin-pusat@...`, `admin-paws@...`, `admin-meow@...` (clinic_admin per klinik, pass `admin123`), user pasien `budi@pusat.com` (klinik 1), `dedi@paws.com` (klinik 2).

## Skenario Web
1. Buka `http://127.0.0.1:8000/admin/login` → login admin-pusat → `/admin/doctors` hanya Dr. Budi/Sari. Coba akses `?clinic_id=<id happy-paws>` → tetap data Pusat (bypass ditolak).
2. Login super → switch ke happy-paws → data berubah ke Dr. Andi. CRUD klinik: tambah logo + warna → cek navbar berubah.
3. Daftar baru: `POST /admin/register` pilih happy-paws → cek `clinic_join_requests` pending → approve → login berhasil di happy-paws saja.

## Skenario API (curl, ganti $B dan $TOKEN)
```powershell
$B="https://envious-reselect-darn.ngrok-free.dev"
curl.exe -s "$B/public/clinics"                       # harap 3 klinik
curl.exe -s "$B/public/clinics/happy-paws"            # harap detail + counts
# katalog terfilter
curl.exe -s -H "Authorization: Bearer $TOKEN" -H "X-Clinic-Slug: happy-paws" "$B/api/doctors"
# booking silang (user pusat + doctor paws) → harap 422/403
curl.exe -s -X POST -H "Authorization: Bearer $TOKEN_PUSAT" -H "Content-Type: application/json" -H "X-Clinic-Slug: petheal-pusat" -d '{"pet_id":1,"doctor_id":99,"booking_date":"2026-10-10","booking_time":"10:00"}' "$B/api/bookings"
```

## Skenario Android
1. Fresh install (clear data) → `clinic_picker` muncul → search `paws` → pilih → cek home: nama `Happy Paws Bandung`, warna `#0EA5A5`, alamat Bandung, dokter hanya Dr. Andi.
2. Register akun baru → cek di web admin happy-paws muncul user itu; cek di Pusat TIDAK ada.
3. Settings → Ganti Klinik → pilih meowcare → login ulang → dokter hanya Dr. Rina, booking lama Pusat tidak tampil (terisolasi).
4. Matikan internet → ErrorState muncul, bukan crash. Nyalakan → retry berhasil.

## Regresi (setiap fase)
- `/` = 200, `/admin/login` = 200 (lokal + ngrok), `/api/health` success, tidak ada `Unsupported SSL request`.
- Export PDF/CSV bookings & payments per klinik tetap terunduh. FCM + Midtrans Sandbox tetap jalan.
