# DB_SCHEMA.md — Skema Saat Ini + Rencana Kolom Baru

## Saat Ini (36 migration, single-tenant)
- `users`: id, firebase_uid UNIQUE NULL, name, email, password NULL, role DEFAULT 'user', phone, photo. (+ password_reset_tokens, sessions)
- `device_tokens`: id, user_id FK CASCADE, token, device_type, INDEX(token)
- `pets`: id, user_id FK CASCADE, name, species, breed, age(months), weight DECIMAL(8,2) NULL, gender, date_of_birth NULL, photo, notes
- `doctors`: id, name, specialization, phone, email, photo, available_days JSON, start_time, end_time, is_active. **TANPA clinic_id**
- `bookings`: id, user_id FK, pet_id FK, doctor_id FK, service_id NULL FK NULLOnDelete, booking_date, booking_time, status [pending|confirmed|completed|cancelled], notes, service_type DEFAULT medical_checkup, payment_method NULL, payment_type [dp|full], dp_amount, total_amount, paid_amount DEFAULT 0, remaining_amount NULL, payment_status DEFAULT unpaid, payment_date NULL, cancellation_reason, confirmed_at, completed_at. **TANPA clinic_id**
- `medical_records`: id, booking_id FK, pet_id FK, doctor_id FK, diagnosis, treatment, medicine, notes, next_visit_date/time, reminder_sent, cost, treatment_cost, medicine_cost, total_medical_cost, extra_payment_amount|paid_amount|status DEFAULT not_required|order_id UNIQUE|date
- `services`: id, name, description, price DECIMAL(12,2), duration INT, category, is_active. **TANPA clinic_id**
- `payment_methods`: id, name, type [qris|bank|ewallet], description, icon, is_active (global)
- `doctor_reviews`: id, doctor_id FK, user_id FK, booking_id FK UNIQUE, rating TINYINT, review
- `notifications`: id, user_id FK, title, body, type DEFAULT general, data JSON, read_at
- `vaccinations`: id, pet_id FK, vaccine_name, batch_number, date_administered, next_due_date, veterinarian, notes, reminder_sent
- `weight_records`: id, pet_id FK, weight DECIMAL(5,2), recorded_at DATE, notes
- `audit_logs`: id, user_id NULL SET NULL, action, model_type, model_id, description, ip_address, user_agent
- `payment_events`: id, booking_id FK, medical_record_id NULL FK, order_id UNIQUE, transaction_status, payment_type, gross_amount, payload JSON
- `app_settings`: id, key UNIQUE, value (Cache). + cache, jobs, personal_access_tokens.

## Rencana Baru (Fase 1) — JANGAN eksekusi sebelum Fase 1 dimulai
```sql
CREATE TABLE clinics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  address TEXT NOT NULL,
  phone VARCHAR(50) NULL, email VARCHAR(255) NULL,
  logo_path VARCHAR(512) NULL,
  primary_color CHAR(7) NOT NULL DEFAULT '#18C964',
  description TEXT NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX(slug), INDEX(is_active)
);
CREATE TABLE clinic_join_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clinic_id BIGINT UNSIGNED NOT NULL REFERENCES clinics(id) ON DELETE CASCADE,
  name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NULL, password VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX(clinic_id,status)
);
ALTER TABLE users ADD clinic_id BIGINT UNSIGNED NULL REFERENCES clinics(id) ON DELETE SET NULL, ADD INDEX(clinic_id,role);
ALTER TABLE doctors ADD clinic_id BIGINT UNSIGNED NOT NULL REFERENCES clinics(id) ON DELETE CASCADE, ADD INDEX(clinic_id,is_active);
ALTER TABLE services ADD clinic_id BIGINT UNSIGNED NOT NULL REFERENCES clinics(id) ON DELETE CASCADE, ADD INDEX(clinic_id,is_active);
ALTER TABLE bookings ADD clinic_id BIGINT UNSIGNED NOT NULL REFERENCES clinics(id) ON DELETE CASCADE,
  ADD INDEX(clinic_id,status,booking_date),
  ADD INDEX(clinic_id,doctor_id,booking_date,booking_time);
ALTER TABLE medical_records ADD clinic_id BIGINT UNSIGNED NULL REFERENCES clinics(id) ON DELETE SET NULL;
ALTER TABLE payment_events ADD clinic_id BIGINT UNSIGNED NULL REFERENCES clinics(id) ON DELETE SET NULL;
ALTER TABLE audit_logs ADD clinic_id BIGINT UNSIGNED NULL REFERENCES clinics(id) ON DELETE SET NULL;
ALTER TABLE notifications ADD clinic_id BIGINT UNSIGNED NULL REFERENCES clinics(id) ON DELETE SET NULL;
```
- Backfill: semua `doctors/services/bookings` lama → klinik default `petheal-pusat`; `users` lama → klinik default kecuali yang dipromosikan `super_admin` (biarkan NULL + bypass filter).
- Role baru: `super_admin` (semua klinik), `clinic_admin` (1 klinik), pertahankan `user|admin|doctor` untuk kompat.
