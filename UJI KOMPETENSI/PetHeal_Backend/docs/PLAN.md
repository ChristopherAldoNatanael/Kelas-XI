# PLAN.md — Rencana 6 Fase Multi-Klinik

> Centang `[x]` saat fase selesai + tulis tanggal di PROGRESS.md. Jangan lompat fase.

## Fase 0 — Persiapan & Baseline [x]
- [x] Audit backend (36 migration, 14 model, route web/api) + audit Android (1 APK, branding hardcoded)
- [x] Fix bug `/` → maintenance (`routes/web.php`: `app()->isDownForMaintenance()`)
- [x] Verifikasi ngrok tembus (`/admin/login` 200, `/api/health` success)
- [x] Buat file konteks `AGENTS.md` + `docs/*` (fase ini)

## Fase 1 — DB & Model Tenant [ ]
- [ ] Migration `create_clinics_table` (name, slug UNIQUE, address TEXT, phone, email, logo_path NULL, primary_color DEFAULT #18C964, description NULL, is_active DEFAULT true)
- [ ] Alter `users` + `clinic_id NULL FK NULLOnDelete` + index(clinic_id,role); tambah role `super_admin, clinic_admin`
- [ ] Alter `doctors, services` + `clinic_id FK CASCADE` + index(clinic_id,is_active)
- [ ] Alter `bookings` + `clinic_id FK CASCADE` + index(clinic_id,status,booking_date) + index(clinic_id,doctor_id,booking_date,booking_time)
- [ ] Alter `medical_records, payment_events, audit_logs, notifications` + `clinic_id NULL`
- [ ] Seeder klinik default `PetHeal Pusat` (slug `petheal-pusat`) + backfill SEMUA data lama → jadikan NOT NULL bertahap
- [ ] Model `Clinic.php` + relasi (`hasMany` doctors/services/bookings/users, accessor `logo_url`) + `belongsTo Clinic` di Doctor/Service/Booking/User
- [ ] Verifikasi: `php artisan migrate --force`, cek `route:list`, `DB_SCHEMA.md` cocok

## Fase 2 — Auth, Middleware & API Publik [ ]
- [ ] Middleware `EnsureClinicAccess` + `EnsureSuperAdmin` + helper `currentClinic()` (web: session `current_clinic_id`; API: header `X-Clinic-Slug` fallback `user->clinic_id`)
- [ ] Ubah `GET|POST /admin/register` → request gabung klinik (pending) + tabel `clinic_join_requests` + halaman approve super_admin
- [ ] Promosikan admin lama → `super_admin`
- [ ] API publik (tanpa auth): `GET /public/clinics`, `GET /public/clinics/{slug}` (lihat API_CONTRACT.md)
- [ ] Filter katalog: `GET /doctors?clinic_slug=`, `GET /services?clinic_slug=`; validasi `store Booking` tolak 422/403 jika beda klinik
- [ ] Verifikasi curl: list publik 200, booking silang 403, health tetap success

## Fase 3 — Web Admin Per Klinik [ ]
- [ ] Layout Blade dinamis (`currentClinic->name/logo_url/primary_color/address`) untuk navbar/sidebar/login
- [ ] Semua `Admin/*` filter `clinic_id` + badge nama klinik; super_admin dapat switcher
- [ ] CRUD klinik (super_admin): nama, slug auto, alamat, telepon, email, logo upload 2MB, warna hex picker, deskripsi
- [ ] Seed 3 klinik demo (Pusat Jakarta, Happy Paws Bandung, MeowCare Surabaya) + dokter/layanan beda
- [ ] Verifikasi: admin A tak lihat data B; export PDF/CSV tetap jalan per klinik

## Fase 4 — Android 1 APK Dinamis [ ]
- [ ] DTO `ClinicDto` + `ApiService GET public/clinics|{slug}` (tanpa auth) — lihat `Android/docs/ANDROID_ARCH.md`
- [ ] `ClinicRepository` + DataStore keys (clinic_slug/name/color/logo/address) + `ClinicViewModel` + Screen `clinic_picker` + alur Splash→picker→onboarding→login + menu `Ganti Klinik`
- [ ] `NetworkInterceptor` tambah `X-Clinic-Slug`; `AuthRepository` kirim `clinic_slug` nullable
- [ ] Theme dinamis (`ClinicColors`, `LocalClinicColors`, hapus duplikat `0xFF2BEE6C`, ganti literal "PetHeal" → `clinic_name`); logo via Coil
- [ ] Cache dokter key by slug; ErrorState untuk 403 beda klinik
- [ ] Verifikasi: fresh install → picker → pilih B → warna/logo/alamat/dokter berubah; `assembleDebug` sukses

## Fase 5 — Testing & Demo [ ]
- [ ] Jalankan semua skenario `TESTING.md` + checklist `ACCEPTANCE.md`
- [ ] Test silang lengkap (web + API + Android) via ngrok
- [ ] Siapkan akun demo + data 3 klinik untuk sidang

## Fase 6 — Rilis Sidang [ ]
- [ ] `config:clear`, `cache:clear`, `migrate --force`, rebuild APK, catat URL ngrok final di PROGRESS.md
- [ ] Bekukan scope (tidak ada fitur baru H-1 sidang)
