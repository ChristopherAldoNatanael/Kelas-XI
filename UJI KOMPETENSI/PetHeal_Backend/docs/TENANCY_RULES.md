# TENANCY_RULES.md — Aturan Isolasi Klinik

## Peran
| Peran | `users.role` | `users.clinic_id` | Hak |
|---|---|---|---|
| super_admin | `super_admin` | NULL (bypass) | Semua klinik, CRUD klinik, approve join-request, switch via session/`?clinic_id=` |
| clinic_admin | `clinic_admin` | WAJIB 1 id | Hanya kliniknya: dashboard/users/doctors/bookings/services/payments/medical/audit/notif. TIDAK bisa ganti klinik. |
| user (pasien) | `user` | **NULL (akun GLOBAL)** | Hanya data miliknya (`user_id`). Boleh melihat katalog klinik mana pun dan memilih klinik berbeda pada setiap booking. |
| doctor | `doctor` | opsional | Hanya helper `isDoctor()`, tanpa panel khusus (tetap seperti sekarang). |
| admin (legacy) | `admin` | — | Dianggap `super_admin` setelah migrasi (promosikan semua `admin` lama). |

## Menentukan Klinik Aktif
- **Web (Blade):** `session('current_clinic_id')`. clinic_admin: dikunci = `user->clinic_id` (abaikan input). super_admin: boleh dari `POST /admin/clinics/switch` atau `?clinic_id=`, default klinik pertama aktif. Helper: `currentClinic()` / `currentClinicId()`.
- **API:** prioritas (1) header `X-Clinic-Slug` → (2) `auth()->user()->clinic_id` → (3) query `?clinic_slug=`. Slug tidak dikenal/nonaktif → `404`. User authed + slug ≠ klinik user → `403` (kecuali super_admin).
- **Midtrans webhook:** tentukan klinik dari `booking->clinic_id` (via `order_id`), bukan dari header.

## Pasien adalah Akun Global (revisi multi-klinik)

`users.clinic_id` untuk pasien **NULL** dan tidak pernah dipakai sebagai
otorisasi. Pasien memilih klinik per transaksi, jadi keanggotaan klinik hanya
bisa dibuktikan dari data transaksi.

Satu-satunya tempat aturan ini didefinisikan: **`App\Services\PatientScope`**.

| kebutuhan | cara yang benar |
|---|---|
| Daftar/statistik akun klinik (`/admin/users`, dashboard) | `PatientScope::scopeClinicUsers($query, $clinicId)` — staf klinik **atau** pasien yang punya booking/rekam medis di klinik itu |
| Query hewan untuk klinik | `PatientScope::scopeClinicPets($query, $clinicId)` |
| Menentukan apakah sebuah akun pasien | `PatientScope::isPatient($user)` (dipakai `ApiAuthenticate`) |
| Katalog dokter/layanan lintas klinik | `PatientScope::scopeCatalog(...)` |

**DILARANG** memfilter pasien dengan `users.clinic_id`: seluruh pasien yang
mendaftar dari aplikasi akan hilang dari daftar akun, angka "total pasien", dan
pencarian admin (pernah terjadi — lihat `docs/AUDIT_REPORT.md` F-01).

Data milik pasien tetap milik pasien: admin klinik hanya melihat transaksi
kliniknya sendiri. `Admin\UserController::show()` membatasi relasi `bookings`
ke `clinic_id` klinik tersebut, bukan seluruh riwayat pasien di klinik lain.

## Aturan Query (wajib di SEMUA controller baru/diubah)
- Admin list/show/export: selalu `->where('clinic_id', currentClinicId())` kecuali super_admin eksplisit `?all=1` (log ke audit).
- API katalog: `Doctor::where(is_active)->where(clinic_id, $clinicId)`, `Service::active()->where(clinic_id, $clinicId)`.
- Booking store: ambil `doctor->clinic_id` + (jika ada) `service->clinic_id`, pastikan ketiganya sama dengan user clinic → set `booking->clinic_id` = itu. Double-book check scoped: `where(clinic_id, doctor_id, booking_date, booking_time)`.
- `pets` tetap milik user (`user_id`); `medical_records/vaccinations/weight_records` untuk laporan mengikuti `clinic_id` barisnya sendiri, BUKAN `pet->user->clinic_id` (pasien global tidak punya klinik).
- `audit_logs`: isi `clinic_id` + `user_id` setiap aksi admin. `notifications/device_tokens`: kirim hanya dalam klinik yang sama.

## Permintaan Klinik (`clinic_join_requests`)
- `type=join`: permintaan gabung ke klinik existing; `clinic_id` wajib dan approve hanya membuat akun `clinic_admin`.
- `type=new_clinic`: pendaftaran klinik mandiri; `clinic_id` NULL sampai disetujui. Data klinik disimpan di kolom snapshot `requested_*`.
- Daftar permintaan HANYA untuk `super_admin` dan **jangan** difilter `currentClinicId()`: request `new_clinic` akan hilang bila super admin sedang berada di konteks sebuah klinik.
- Approve `new_clinic` membuat Clinic + User `clinic_admin` + audit log dalam satu transaksi dengan row lock; slug/email dicek ulang di dalam lock. Reject tidak pernah membuat Clinic/User.

## Larangan
- DILARANG mempercayai `clinic_id` dari body request mentah tanpa verifikasi milik user/klinik aktif.
- DILARANG menampilkan dropdown data klinik lain ke clinic_admin (filter di query, bukan cuma hide tombol).
- DILARANG mengubah `users.clinic_id` via API profil biasa — hanya via ganti-klinik (logout + daftar ulang) atau批准 admin.
