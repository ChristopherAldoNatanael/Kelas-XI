# TENANCY_RULES.md — Aturan Isolasi Klinik

## Peran
| Peran | `users.role` | `users.clinic_id` | Hak |
|---|---|---|---|
| super_admin | `super_admin` | NULL (bypass) | Semua klinik, CRUD klinik, approve join-request, switch via session/`?clinic_id=` |
| clinic_admin | `clinic_admin` | WAJIB 1 id | Hanya kliniknya: dashboard/users/doctors/bookings/services/payments/medical/audit/notif. TIDAK bisa ganti klinik. |
| user (pasien) | `user` | WAJIB 1 id | Hanya data miliknya (`user_id`) + katalog kliniknya. 1 akun = 1 klinik. |
| doctor | `doctor` | opsional | Hanya helper `isDoctor()`, tanpa panel khusus (tetap seperti sekarang). |
| admin (legacy) | `admin` | — | Dianggap `super_admin` setelah migrasi (promosikan semua `admin` lama). |

## Menentukan Klinik Aktif
- **Web (Blade):** `session('current_clinic_id')`. clinic_admin: dikunci = `user->clinic_id` (abaikan input). super_admin: boleh dari `POST /admin/clinics/switch` atau `?clinic_id=`, default klinik pertama aktif. Helper: `currentClinic()` / `currentClinicId()`.
- **API:** prioritas (1) header `X-Clinic-Slug` → (2) `auth()->user()->clinic_id` → (3) query `?clinic_slug=`. Slug tidak dikenal/nonaktif → `404`. User authed + slug ≠ klinik user → `403` (kecuali super_admin).
- **Midtrans webhook:** tentukan klinik dari `booking->clinic_id` (via `order_id`), bukan dari header.

## Aturan Query (wajib di SEMUA controller baru/diubah)
- Admin list/show/export: selalu `->where('clinic_id', currentClinicId())` kecuali super_admin eksplisit `?all=1` (log ke audit).
- API katalog: `Doctor::where(is_active)->where(clinic_id, $clinicId)`, `Service::active()->where(clinic_id, $clinicId)`.
- Booking store: ambil `doctor->clinic_id` + (jika ada) `service->clinic_id`, pastikan ketiganya sama dengan user clinic → set `booking->clinic_id` = itu. Double-book check scoped: `where(clinic_id, doctor_id, booking_date, booking_time)`.
- `pets` tetap milik user (`user_id`), tapi `medical_records/vaccinations/weight_records` ikut `pet->user->clinic_id` untuk laporan.
- `audit_logs`: isi `clinic_id` + `user_id` setiap aksi admin. `notifications/device_tokens`: kirim hanya dalam klinik yang sama.

## Larangan
- DILARANG mempercayai `clinic_id` dari body request mentah tanpa verifikasi milik user/klinik aktif.
- DILARANG menampilkan dropdown data klinik lain ke clinic_admin (filter di query, bukan cuma hide tombol).
- DILARANG mengubah `users.clinic_id` via API profil biasa — hanya via ganti-klinik (logout + daftar ulang) atau批准 admin.
