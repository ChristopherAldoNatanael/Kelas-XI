# PLAN.md — Rencana 6 Fase Multi-Klinik

> Centang `[x]` saat fase selesai + tulis tanggal di PROGRESS.md. Jangan lompat fase.

## Fase 0 — Persiapan & Baseline [x]
- [x] Audit backend (36 migration, 14 model, route web/api) + audit Android (1 APK, branding hardcoded)
- [x] Fix bug `/` → maintenance (`routes/web.php`: `app()->isDownForMaintenance()`)
- [x] Verifikasi ngrok tembus (`/admin/login` 200, `/api/health` success)
- [x] Buat file konteks `AGENTS.md` + `docs/*` (fase ini)

## Fase 1 — DB & Model Tenant [x]
- [x] Migration `create_clinics_table` (name, slug UNIQUE, address TEXT, phone, email, logo_path NULL, primary_color DEFAULT #18C964, description NULL, is_active DEFAULT true)
- [x] Alter `users` + `clinic_id NULL FK NULLOnDelete` + index(clinic_id,role); tambah role `super_admin, clinic_admin`
- [x] Alter `doctors, services` + `clinic_id FK CASCADE` + index(clinic_id,is_active)
- [x] Alter `bookings` + `clinic_id FK CASCADE` + index(clinic_id,status,booking_date) + index(clinic_id,doctor_id,booking_date,booking_time)
- [x] Alter `medical_records, payment_events, audit_logs, notifications` + `clinic_id NULL`
- [x] Seeder klinik default `PetHeal Pusat` (slug `petheal-pusat`) + backfill SEMUA data lama → jadikan NOT NULL bertahap
- [x] Model `Clinic.php` + relasi (`hasMany` doctors/services/bookings/users, accessor `logo_url`) + `belongsTo Clinic` di Doctor/Service/Booking/User
- [x] Verifikasi: `php artisan migrate --force`, cek `route:list`, `DB_SCHEMA.md` cocok

## Fase 2 — Auth, Middleware & API Publik [x]
- [x] Middleware `EnsureClinicAccess` + `EnsureSuperAdmin` + helper `currentClinic()` (web: session `current_clinic_id`; API: header `X-Clinic-Slug` fallback `user->clinic_id`)
- [x] Ubah `GET|POST /admin/register` → request gabung klinik (pending) + tabel `clinic_join_requests` + halaman approve super_admin
- [x] Promosikan admin lama → `super_admin`
- [x] API publik (tanpa auth): `GET /public/clinics`, `GET /public/clinics/{slug}` (lihat API_CONTRACT.md)
- [ ] Filter katalog: `GET /doctors?clinic_slug=`, `GET /services?clinic_slug=`; validasi `store Booking` tolak 422/403 jika beda klinik ← FASE 3
- [x] Verifikasi curl: list publik 200, booking silang 403, health tetap success

## Fase 3 — Data Isolation Backend [x]
- [x] X-Clinic-Slug header validation in ApiAuthenticate (403 if mismatch)
- [x] Admin DashboardController: all queries clinic-scoped (stats, charts, payments, audit logs)
- [x] Admin DoctorController: CRUD + show/edit/destroy scoped, clinic_id set on create
- [x] Admin BookingController: list/show/confirm/complete/cancel/export scoped
- [x] Admin ServiceController: CRUD scoped, clinic_id set on create
- [x] Admin UserController: list/show/edit/destroy scoped, clinic_id set on create
- [x] Admin MedicalRecordController: list/create/show/edit/export scoped, clinic_id from booking
- [x] Admin PaymentController: list/show/confirm/export scoped
- [x] API DoctorController: index/show/slots/reviews clinic-scoped
- [x] API ServiceController: index/show clinic-scoped (authenticated)
- [x] API BookingController: store validates doctor+service clinic, double-book scoped per clinic
- [x] AuditLog::log() auto-captures clinic_id
- [x] helpers.php: applyClinicScope() + currentClinicId() used across all controllers
- [x] Verifikasi: doctor isolation (HP user sees 2), service isolation (HP user sees 3), X-Clinic-Slug 403, health 200, SanityTest PASS

## Fase 4 — Dynamic Web Admin [x]
- [x] Admin layout dinamis: clinic name, logo, primary color, title — semua dari `currentClinic()`
- [x] Clinic switcher untuk super_admin di navbar (dropdown → POST switch-clinic)
- [x] Sidebar: super_admin lihat "Clinics" + "Join Requests" link; clinic_admin lihat nama klinik
- [x] CRUD klinik super_admin: index (list + stats), create, edit, toggle-active. Routes di-protect `super_admin` middleware
- [x] Settings page: tampilkan info klinik aktif (name, slug, color, address, phone, email, status)
- [x] CSS variables + tailwind config dynamic berdasarkan `primary_color` klinik
- [x] FASE 3 regression: doctor isolation PASS (404), X-Clinic-Slug mismatch PASS (403), service cross-clinic PASS (404), health PASS (200)

## Fase 5 — Android 1 APK Dinamis [ ]
- [ ] DTO `ClinicDto` + `ApiService GET public/clinics|{slug}` (tanpa auth) — lihat `Android/docs/ANDROID_ARCH.md`
- [ ] `ClinicRepository` + DataStore keys (clinic_slug/name/color/logo/address) + `ClinicViewModel` + Screen `clinic_picker` + alur Splash→picker→onboarding→login + menu `Ganti Klinik`
- [ ] `NetworkInterceptor` tambah `X-Clinic-Slug`; `AuthRepository` kirim `clinic_slug` nullable
- [ ] Theme dinamis (`ClinicColors`, `LocalClinicColors`, hapus duplikat `0xFF2BEE6C`, ganti literal "PetHeal" → `clinic_name`); logo via Coil
- [ ] Cache dokter key by slug; ErrorState untuk 403 beda klinik
- [ ] Verifikasi: fresh install → picker → pilih B → warna/logo/alamat/dokter berubah; `assembleDebug` sukses

## Fase 6 — Testing & Demo [ ]
- [ ] Jalankan semua skenario `TESTING.md` + checklist `ACCEPTANCE.md`
- [ ] Test silang lengkap (web + API + Android) via ngrok
- [ ] Siapkan akun demo + data 3 klinik untuk sidang

## Fase 7 — Rilis Sidang [ ]
- [ ] `config:clear`, `cache:clear`, `migrate --force`, rebuild APK, catat URL ngrok final di PROGRESS.md
- [ ] Bekukan scope (tidak ada fitur baru H-1 sidang)
