# PetHeal API Documentation — FROZEN CONTRACT (Phase 2, 2026-10-03)

> Source of truth = kode aktual (`routes/api.php`, `app/Http/Controllers/Api/*`,
> `bootstrap/app.php`). Dokumen ini diregenerasi dari `php artisan route:list --path=api`
> (64 routes) dan diverifikasi terhadap controller. Jika bertentangan dengan catatan
> lama, dokumen ini yang berlaku.

## Base URL

```
http://127.0.0.1:8000/api   (lokal)
https://<ngrok-host>/api/   (publik, harus https + diakhiri /api/)
```

## Auth & Tenant

- Auth: Sanctum Bearer. Semua route proteksi wajib header
  `Authorization: Bearer <token>`.
- Tenant: user terikat 1 klinik (`users.clinic_id`). Request terautentikasi
  otomatis ter-scope ke klinik user. Header `X-Clinic-Slug` opsional: bila
  dikirim harus sama dengan klinik user, bila tidak → `403`.
- User tenant tanpa `clinic_id` → `403` di semua route proteksi (fail-closed,
  Phase 1). `super_admin` tanpa klinik = overview semua klinik (perilaku existing).
- Rate limit: `auth` 8/mnt, `password-reset` 3/mnt, `uploads` 10/mnt,
  `payment` 10/mnt, `api` 60/mnt, `webhook` 120/mnt.

## Canonical Envelope (frozen)

Success object:

```json
{ "success": true, "message": "...", "data": {} }
```

`message` selalu ada pada write/action; `GET` detail boleh tanpa `message`.
Action tanpa payload memakai `{ "success": true, "message": "..." }` (tanpa `data`).

Collection paginated:

```json
{
  "success": true,
  "data": [],
  "pagination": { "current_page": 1, "last_page": 5, "per_page": 20, "total": 100 }
}
```

Error:

```json
{ "success": false, "message": "...", "errors": {} }
```

`errors` hanya ada untuk 422 validation / upstream payment. Semua 422 validasi
memiliki `success: false` (dijamin handler `bootstrap/app.php`). Semua 404 API
memiliki `success: false`.

401 canonical (satu-satunya pesan untuk semua kegagalan auth):

```json
{ "success": false, "message": "Unauthenticated." }
```

### Pengecualian frozen (disengaja, JANGAN diubah tanpa migrasi Android)

| Endpoint | Bentuk | Alasan |
|---|---|---|
| `POST /midtrans/webhook` | `{status:ok}` / `{error:...}` tanpa `success` | server-to-server Midtrans, bukan klien |
| `GET /payment/transaction-status/{orderId}` sukses | raw JSON Midtrans (`transaction_status`, `fraud_status`, …) | DTO Android `TransactionStatusResponse` parse raw |
| `GET pets/{id}/weight-history`, `GET pets/{id}/vaccinations` | `data: {pet_id, records[]/vaccinations[], pagination{...}}` nested | DTO Android baca nested `data.records` + `data.pagination` |
| `GET bookings/{id}/medical-record` kosong | `200 {success:true, message, data:null}` | klien null-check; bedakan dari 404 |
| `GET payment/preflight` error | tetap membawa `data:{ready,mode,checks}` | probe diagnostik; klien baca `data.ready` |
| `GET doctors`, `GET services`, `GET payment-methods`, `GET public/clinics`, `GET notifications`, `GET dashboard`, `GET bookings/upcoming`, `GET doctors/{id}/slots` | tanpa `pagination` | list by-design (`limit` / fixed / full). `GET notifications` memakai `?limit=` (max 100) |

### Status code semantics (frozen)

| Code | Arti |
|---|---|
| 200 | OK (termasuk `data:null` medical-record kosong) |
| 201 | Created (POST sukses membuat resource) |
| 400 | Business rule gagal (extra payment tak diperlukan, dsb) |
| 401 | Tanpa token / token invalid — pesan selalu `Unauthenticated.` |
| 403 | Forbidden: slug mismatch, akun tanpa klinik, bukan pemilik |
| 404 | Tidak ditemukan / di luar klinik (`{success:false, message}`) |
| 409 | Konflik: slot bentrok, review duplikat, email terdaftar |
| 422 | Validasi gagal (`{success:false, message, errors}`) — termasuk ID lintas klinik |
| 429 | Rate limit (throttle middleware) |
| 500/502 | Server / upstream Midtrans gagal |

## Endpoint Reference

### Auth (publik, throttle:auth)

| Method & Path | Body | Response |
|---|---|---|
| `POST /auth/login` | `{email!, password!, fcm_token?, clinic_slug?}` — `clinic_slug` opsional; bila diisi harus milik user else 403 | `200 {success,message,data:{token,user}}`; `401` kredensial salah; `403` slug mismatch |
| `POST /auth/register-direct` | `{name!, email! (unique), password! min:8, phone?, fcm_token?, clinic_slug?}` — user baru tanpa slug → `clinic_id=null` (akses proteksi 403 sampai diikat klinik) | `200 {success,message,data:{token,user}}`; `409` email ada |
| `POST /auth/firebase-login` | `{id_token! (Firebase ID token, BUKAN firebase_uid mentah), fcm_token?, clinic_slug?}` — auto-create user bila belum ada | `200` sama; `401` token invalid; `403` slug mismatch |
| `POST /auth/register` | `{id_token!, name!, phone?, fcm_token?, clinic_slug?}` | `200` sama; `409` user sudah ada (pakai firebase-login) |
| `POST /auth/forgot-password` | `{email!}` — selalu 200 walau email tak dikenal (anti-enumerasi) | `200 {success,message}` |
| `POST /auth/verify-reset-code` | `{email!, code!}` (expiry 15 mnt) | `200`; `400` kode salah/kedaluwarsa |
| `POST /auth/reset-password` | `{email!, code!, password! confirmed min:8}` | `200`; `400` kode invalid |
| `POST /auth/logout` (proteksi) | `{fcm_token?}` | `200 {success,message}` |
| `GET /auth/profile` (proteksi) | — | `200 {success,data:user+clinic}` |
| `PUT /auth/profile` (proteksi) | `{name?, phone?, photo? string}` (hanya PUT, tanpa PATCH) | `200 {success,message,data}` |
| `POST /auth/profile/photo` (proteksi, throttle:uploads) | multipart `photo!` (jpeg/png/jpg/webp max 4MB) | `200 {success,message,data}` |
| `DELETE /auth/account` (proteksi) | — | `200 {success,message}` (cascade manual + revoke token) |

`user` object: `{id,name,email,role,phone,photo,photo_url,firebase_uid,created_at,updated_at,clinic:{id,name,slug,logo_url,primary_color}|null}`.

### Public catalog

| Method & Path | Auth | Response |
|---|---|---|
| `GET /health` | tidak | `200/503 {success,message,data:{database,firebase,midtrans,storage,routes}}` |
| `GET /public/clinics` | tidak | `200 {success,data:[{id,name,slug,address,phone,email,logo_url,primary_color,description}]}` order name, tanpa paginasi |
| `GET /public/clinics/{slug}` | tidak | `200 {success,data:{...+doctors_count,services_count}}`; `404` slug tak dikenal/nonaktif |
| `GET /payment-methods` | tidak (katalog global) | `200 {success,data:[]}` cache 6 jam, order type,name |
| `GET /services` | publik TAPI tenant-eksplisit: Bearer+klinik → filter klinik user; anonim wajib `?clinic_slug=` else `422 {success:false,message}`; slug asing → `404` | `200 {success,data:[]}` order name, cache 6 jam |
| `GET /services/{id}` | sama (slug rule sama) | `200 {success,data}`; `404` |
| `POST /midtrans/webhook` | tidak (verifikasi signature sha512, throttle:webhook) | `{status:ok}` / `{error}` — BUKAN envelope standar |

### Pets (proteksi, owner-scoped)

| Method & Path | Body/Query | Response |
|---|---|---|
| `GET /pets` | `?per_page=` 1–100 default 50 | `200 {success,data[],pagination}` canonical |
| `POST /pets` | `{name!,species!,breed?,weight?,gender:male\|female,date_of_birth?,photo? file}` | `201 {success,message,data:pet}` |
| `POST /pets/with-photo` | sama tapi `photo! required image` (throttle:uploads) | `201` sama (duplikat kompatibel — JANGAN hapus) |
| `GET /pets/{id}` | — | `200 {success,data:pet+relasi}`; `404 {success:false}` |
| `PUT /pets/{id}` | field `sometimes` | `200 {success,message,data}` |
| `DELETE /pets/{id}` | — | `200 {success,message}` |
| `POST /pets/{id}/photo` | multipart `photo!` | `200 {success,message,data:{photo_url}}` (bukan objek pet) |
| `GET /pets/{id}/medical-records` | paginate 20 fixed | `200 {success,data[],pagination}` |
| `GET /pets/{id}/weight-history` | — | `200 {success,data:{pet_id,pet_name,current_weight,records[],weight_change,pagination}}` nested |
| `POST /pets/{id}/weight-records` | `{weight! 0.1–200, recorded_at?, notes?}` + update `pet.weight` | `201 {success,message,data}` |
| `DELETE /pets/{id}/weight-records/{recordId}` | — | `200 {success,message}` |
| `GET /pets/{id}/vaccinations` | — | `200 {success,data:{pet_id,pet_name,vaccinations[],upcoming_due[],pagination}}` nested |
| `POST /pets/{id}/vaccinations` | `{vaccine_name!,date_administered!,next_due_date after_or_equal, ...}` | `201` |
| `PUT /pets/{id}/vaccinations/{vaccinationId}` | `sometimes` (+`reminder_sent` boolean) | `200` |
| `DELETE /pets/{id}/vaccinations/{vaccinationId}` | — | `200 {success,message}` |

### Doctors (proteksi, clinic-scoped)

| Method & Path | Query/Body | Response |
|---|---|---|
| `GET /doctors` | `?limit=` default/max 100, `?search=` prefix-match name/specialization | `200 {success,data:[]}` tanpa paginasi (by-design), cache 900s |
| `GET /doctors/{id}` | — | `200 {success,data+reviews_count}`; `404` (termasuk lintas klinik) |
| `GET /doctors/{id}/slots` | `?date! >= today` | `200 {success,data:[{time,available}]}` (slot 30 mnt; `[]` bila libur); `404/422` |
| `POST /doctors/{id}/reviews` | `{booking_id! (milik reviewer + completed + seklinik), rating! 1–5, review?}` | `201`; `404` dokter/booking tak valid; `409` duplikat; `422` validasi |
| `GET /doctors/{id}/reviews` | paginate 10 server-side (klien tidak kirim page) | `200 {success,data:{reviews[],average_rating,total_reviews},pagination{...}}` |

### Bookings (proteksi, owner + clinic)

| Method & Path | Body | Response |
|---|---|---|
| `GET /bookings` | paginate 20 fixed, order date/time desc | `200 {success,data[],pagination}` |
| `GET /bookings/upcoming` | — (limit 1 untuk home screen) | `200 {success,data:[]}` tanpa paginasi |
| `GET /bookings/{id}` | — | `200 {success,data+relasi}`; `404` |
| `POST /bookings` | `{pet_id! (milik user), doctor_id! (seklinik+aktif), service_id! (seklinik+aktif), booking_date! >= today, booking_time! H:i, notes?, payment_type? dp\|full, payment_method_id?}` — ID lintas klinik → `422` validasi | `201 {success,message,data}`; `404/409` slot bentrok; `422`; `403` akun tanpa klinik |
| `POST /bookings/{id}/cancel` | `{reason? max:500}` (hanya pending/confirmed) | `200 {success,message,data}` |
| `POST /bookings/{id}/reschedule` | `{booking_date!, booking_time!}` (hanya pending; lockForUpdate) | `200`; `409` bentrok |
| `DELETE /bookings/{id}` | — (hanya pending/cancelled) | `200 {success,message}` |
| `GET /bookings/{id}/medical-record` | — | `200 {success,data}` atau `200 {success:true,message,data:null}` bila belum ada |

### Medical records (proteksi, via booking/pet milik user)

| Method & Path | Response |
|---|---|
| `GET /medical-records` | `200 {success,data[],pagination}` (paginate 20; masking `diagnosis/treatment/medicine/notes/next_visit=null` bila `can_view_full_record=false`) |
| `GET /medical-records/{id}` | `200 {success,data}`; `404` |
| `POST /medical-records/{id}/pay` (throttle:payment) | `200 {success,message,data:{token,redirect_url,transaction_id,order_id MEDREC-{id}-{ts},medical_record}}`; `400` tak perlu bayar; `502` Midtrans gagal |
| `GET /medical-records/{id}/payment-status` | `200 {success,data:{id,extra_payment_*,can_view_full_record}}` |

> TIDAK ADA `PUT/POST/DELETE /medical-records` (dokumen lama salah — akan 404/405).

### Payment Midtrans (proteksi kecuali webhook)

| Method & Path | Body | Response |
|---|---|---|
| `GET /payment/preflight` | — | `200/500/502 {success,message,data:{ready,mode,checks}}` (`data` selalu ada) |
| `POST /payment/snap-token` (throttle:payment) | `{transaction_details:{order_id! /^BOOKING-\d+-\d+$/, gross_amount!}, customer_details?, item_details?}` + cek milik + nominal | `200 {success,message,data:{token,...}}`; `400/404/422/500`; upstream gagal → `{success:false,message,errors:{upstream...}}` |
| `GET /payment/transaction-status/{orderId}` | `orderId` = `BOOKING-{id}-{ts}` / `BOOKING-{id}-REMAINING-{ts}` / `MEDREC-{id}-{ts}` | sukses = **raw Midtrans** (tanpa envelope); gagal = `{success:false,message,errors}` |
| `POST /payment/sync-status` (throttle:payment) | `{order_id!}` via `PaymentStatusService` | `200 {success,message,data:{...}}` (varian booking vs medrec) |
| `POST /payment/remaining/{bookingId}` (throttle:payment) | — (hanya `payment_type=dp` + `remaining>0`) | `200 {success,message,data:{token,redirect_url,order_id BOOKING-{id}-REMAINING-{ts},booking}}` |
| `GET /payment/booking/{bookingId}` | — | `200 {success,data:{id,payment_type,payment_status,amounts,payment_date}}` |

### Dashboard / device / notifications (proteksi)

| Method & Path | Response |
|---|---|
| `GET /dashboard` | `200 {success,data:{pets,bookings,payments,medical,vaccination_alerts,summary}}` cache 60s |
| `POST /device-token` | `{token!, device_type: android\|ios\|web}` → `200 {success,message,data}` |
| `DELETE /device-token` | `{token!}` di body → `200 {success,message}` |
| `GET /notifications` | `?limit=` default 50 max 100 → `200 {success,message,data:{notifications[],unread_count}}` |
| `POST /notifications/read-all` | `200 {success,message}` |
| `POST /notifications/{id}/read` | `200 {success,message,data}`; `404 {success:false}` |
| `DELETE /notifications` | `200 {success,message}` |

## Tenant `exists` validation (frozen, Phase 1+2)

`pet_id` → milik user; `doctor_id`/`service_id` → seklinik + aktif;
`booking_id` (review) → milik reviewer + completed + seklinik dokter.
ID lintas klinik → `422 {success:false,message,errors}` (bukan 404).
`payment_method_id` → tabel global (tanpa scope). `super_admin` tanpa klinik
memakai rule unscoped (perilaku existing).

## Naming (frozen — tidak ada endpoint dihapus)

Plural: `pets, doctors, bookings, medical-records, notifications, services,
payment-methods, public/clinics`. Singular legacy yang dipertahankan:
`device-token`, `payment/*`, `bookings/{id}/medical-record`,
`dashboard`, `health`, `pets/with-photo`, `pets/{id}/photo`,
`weight-history` vs `weight-records`, action-POST (`cancel/reschedule/pay/
read-all/sync-status/snap-token/preflight/remaining`). Param camelCase legacy
(`{petId},{recordId},{vaccinationId},{bookingId},{orderId}`) dipertahankan
bersama `{id}/{slug}` lowercase. Semua di atas adalah compatibility surface —
klien boleh mengandalkannya.
