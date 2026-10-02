# API_CONTRACT.md — Endpoint Lama vs Baru

## Aturan Emas
- JANGAN hapus/rename endpoint lama. JANGAN hapus field response lama. Tambahan = field baru `clinic:{id,name,slug,logo_url,primary_color}`.
- Request lama tetap valid. `clinic_slug` / `X-Clinic-Slug` = opsional tahap transisi, wajib setelah Fase 2 untuk katalog.

## Endpoint Lama (tetap, jangan breaking)
- `POST /api/auth/login {email,password,fcm_token?,device_type?}` → `{success,token,user{id,...}}`
- `POST /api/auth/register-direct {name,email,password,phone?,fcm_token?,device_type?}`
- `POST /api/auth/firebase-login {id_token,fcm_token?,device_type?}`, `POST /api/auth/register {id_token,name,phone?,...}`
- `GET /api/health` → `{success,message,data{database,firebase,midtrans,storage,routes}}`
- `GET /api/payment-methods`, `GET /api/services`, `GET /api/services/{id}` (publik — akan difilter klinik)
- `POST /api/midtrans/webhook` (Midtrans, tanpa auth)
- Proteksi: `auth/profile`, `dashboard`, `pets*`, `doctors*`, `bookings*`, `medical-records*`, `payment/*`, `notifications*`, `device-token` (lihat PROJECT_CONTEXT.md).

## Endpoint Baru (Fase 2)
### Publik (tanpa auth)
- `GET /public/clinics` → `200 {success:true, data:[{id,name,slug,address,phone,email,logo_url,primary_color,description,doctors_count,services_count}]}` — hanya `is_active=true`, order name.
- `GET /public/clinics/{slug}` → `200 {success:true, data:{id,name,slug,address,phone,email,logo_url,primary_color,description,doctors_count,services_count}}`, `404` jika slug tak dikenal/nonaktif.
### Katalog Terfilter (wajib kirim tenant)
- `GET /api/doctors?clinic_slug=petheal-pusat` + header `X-Clinic-Slug` → hanya dokter klinik itu. Tanpa slug → fallback `user->clinic_id`; tanpa auth → `422 {message:'clinic_slug wajib'}`.
- `GET /api/services?clinic_slug=...` — sama.
- `POST /api/bookings {pet_id,doctor_id,service_id?,booking_date,booking_time,...,clinic_slug?}` → validasi `doctor.clinic_id == user.clinic_id (== service.clinic_id jika ada)` else `422 {success:false,message:'Dokter/layanan berbeda klinik'}`.
- Semua response detail tambah `clinic:{id,name,slug,logo_url,primary_color}`.
### Admin Klinik (web session)
- `GET|POST /admin/clinics` (super_admin CRUD + upload logo), `GET /admin/join-requests`, `POST /admin/join-requests/{id}/approve|reject`.
- Switch klinik super_admin: `POST /admin/clinics/switch {clinic_id}` → set session `current_clinic_id`.

## Header Tenant
- Android kirim `X-Clinic-Slug: <slug>` di SEMUA request authed (lihat Android/docs). Backend baca: header → fallback `user->clinic_id` → fallback `?clinic_slug=`.
- Jika ketiganya absen untuk endpoint terfilter → `422`. Jika slug milik klinik lain dari user → `403`.
