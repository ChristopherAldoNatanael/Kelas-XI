# API_CONTRACT.md — Frozen Contract (Phase 2, 2026-10-03)

> Kontrak beku untuk Android. Source of truth = kode + `API_DOCUMENTATION.md`.
> Revisi ini menggantikan klaim lama yang terbukti tidak sesuai kode
> (`?clinic_slug` wajib untuk doctors, objek `clinic` di semua respons,
> `PUT /bookings`, `POST/PUT/DELETE /medical-records`, `POST /admin/clinics/switch`).

## Aturan Emas (tetap)

- JANGAN hapus/rename endpoint. JANGAN hapus field respons. Tambahan bersifat aditif.
- Request lama tetap valid. Tidak ada field wajib baru pada request existing,
  kecuali `?clinic_slug=` untuk akses ANONIM ke `/services` (Bearer+klinik tak berubah).

## Canonical Shapes (frozen)

- Objek: `{success, message?, data}`. Aksi tanpa payload: `{success, message}`.
- Koleksi paginated: `{success, data[], pagination:{current_page,last_page,per_page,total}}`
  → `GET /pets`, `GET /bookings`, `GET /medical-records`, `GET /pets/{id}/medical-records`,
  `GET /doctors/{id}/reviews` (pagination ditambah Phase 2, aditif).
- Sub-resource histories: nested `data.{records|vaccinations,pagination}`
  → `GET weight-history`, `GET vaccinations` (frozen apa adanya).
- Error: `{success:false, message, errors?}`. 401 selalu `{success:false,
  message:'Unauthenticated.'}`. 404 API selalu `{success:false, message}`.
- Pengecualian frozen: `POST /midtrans/webhook` (`{status}/{error}`),
  `GET /payment/transaction-status` sukses (raw Midtrans),
  `GET /bookings/{id}/medical-record` kosong (`200 data:null`),
  `GET /payment/preflight` (selalu bawa `data`).

## Auth (frozen)

- `POST /api/auth/login {email,password,fcm_token?,clinic_slug?}` →
  `{success,message,data:{token,user}}`. `clinic_slug` opsional; mismatch → 403.
- `POST /api/auth/register-direct {name,email(unique),password min:8,phone?,fcm_token?,clinic_slug?}`.
  Tanpa slug → `clinic_id=null` → proteksi 403 sampai diikat klinik.
- `POST /api/auth/firebase-login {id_token! (ID token, BUKAN firebase_uid),fcm_token?,clinic_slug?}`
  (auto-create). `POST /api/auth/register {id_token!,name!,phone?,...}` (409 bila sudah ada).
- Password reset: `forgot-password {email}` (selalu 200) → `verify-reset-code {email,code}`
  → `reset-password {email,code,password(confirmed)}`. Expiry kode 15 mnt.
- Proteksi: `POST auth/logout`, `GET|PUT auth/profile`, `POST auth/profile/photo`
  (multipart, throttle uploads), `DELETE auth/account`.

## Katalog & Tenant (frozen, Phase 1+2)

- Tenant = `users.clinic_id`. `X-Clinic-Slug` opsional; mismatch → 403.
  Tenant tanpa klinik → 403 di semua proteksi. `super_admin` tanpa klinik = overview.
- `GET /api/doctors` (+`/{id}`, `/slots?date=`, `/reviews`) — Bearer saja cukup
  (filter via klinik user). TIDAK wajib `?clinic_slug` (klaim lama dicabut).
- `GET /api/services`, `GET /api/services/{id}` — Bearer+klinik (aliran Android,
  tak berubah) ATAU anonim + `?clinic_slug=` eksplisit (tanpa slug → 422,
  slug asing → 404).
- `GET /public/clinics`, `GET /public/clinics/{slug}` (404 bila asing/nonaktif),
  `GET /payment-methods` (global) — publik.
- `POST /api/bookings` — validasi tenant-aware: `pet` milik user,
  `doctor`/`service` seklinik + aktif → ID lintas klinik = `422 {success:false,errors}`.
- `POST /api/doctors/{id}/reviews` — `booking_id` milik reviewer + completed +
  seklinik dokter → lintas klinik = 422.
- `POST /admin/switch-clinic {clinic_id}` (path benar; klaim lama `/admin/clinics/switch` salah).
  Web session, bukan API.

## Endpoint Tidak Ada (koreksi dokumen lama — JANGAN dipakai klien)

- `PUT /bookings/{id}` → 405 (resource hanya index/store/show/destroy).
- `POST/PUT/DELETE /medical-records` → 404/405 (hanya index/show + `/{id}/pay`, `/{id}/payment-status`).
- Respons slots = `{success,data:[{time,available}]}` (BUKAN `{doctor_id,date,available_slots}`).

## Naming Freeze (tidak ada yang dihapus)

Plural: `pets, doctors, bookings, medical-records, notifications, services,
payment-methods, public/clinics`. Legacy dipertahankan: `device-token`,
`payment/*`, `bookings/{id}/medical-record`, `dashboard`, `health`,
`weight-history` vs `weight-records`, action-POST (`cancel/reschedule/pay/
read-all/sync-status/snap-token/preflight/remaining`), param camelCase
(`{petId},{recordId},{vaccinationId},{bookingId},{orderId}`) + `{id}/{slug}`.

## Backward Compatibility (wajib dijaga Android)

1. Raw `transaction-status` JANGAN di-envelope (DTO parse raw).
2. `GET /services` Bearer-tanpa-slug JANGAN diwajibkan slug (interceptor tanpa slug).
3. `data` nested weight/vaccination JANGAN dipindah ke top-level.
4. `data:null` medical-record kosong JANGAN jadi 404.
5. Key error `message` JANGAN dihapus (parser baca `message/detail/error/errors`).
6. `message` sukses JANGAN dihapus (DTO punya field `message`).
7. Duplikat `POST /pets/with-photo`, `PUT /auth/profile` vs `POST /auth/profile/photo`
   dipertahankan (klien memakai keduanya di path berbeda).
