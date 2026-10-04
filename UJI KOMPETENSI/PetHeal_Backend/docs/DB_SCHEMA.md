# DB_SCHEMA.md — Skema Saat Ini + Rencana Kolom Baru

## Saat Ini (43 migration, multi-klinik)
- `clinics`: id, name, slug UNIQUE, address TEXT, phone NULL, email NULL, logo_path NULL, primary_color DEFAULT '#18C964', description NULL, is_active DEFAULT true, timestamps. INDEX(is_active)
- `users`: id, firebase_uid UNIQUE NULL, name, email, password NULL, role DEFAULT 'user', phone, photo, **clinic_id NULL FK clinics NULLOnDelete**. INDEX(clinic_id,role). Role: user|admin|doctor|super_admin|clinic_admin. (+ password_reset_tokens, sessions)
- `device_tokens`: id, user_id FK CASCADE, token, device_type, INDEX(token)
- `pets`: id, user_id FK CASCADE, name, species, breed, age(months), weight DECIMAL(8,2) NULL, gender, date_of_birth NULL, photo, notes
- `doctors`: id, name, specialization, phone, email, photo, available_days JSON, start_time, end_time, is_active, **clinic_id NOT NULL FK clinics CASCADE**. INDEX(clinic_id, is_active)
- `bookings`: id, user_id FK, pet_id FK, doctor_id FK, service_id NULL FK NULLOnDelete, booking_date, booking_time, status, notes, service_type, payment_method NULL, payment_type, dp_amount, total_amount, paid_amount DEFAULT 0, remaining_amount NULL, payment_status DEFAULT unpaid, payment_date NULL, cancellation_reason, confirmed_at, completed_at, **clinic_id NOT NULL FK clinics CASCADE**. INDEX(clinic_id,status,booking_date), INDEX(clinic_id,doctor_id,booking_date,booking_time)
- `medical_records`: id, booking_id FK, pet_id FK, doctor_id FK, diagnosis, treatment, medicine, notes, next_visit_date/time, reminder_sent, cost, treatment_cost, medicine_cost, total_medical_cost, extra_payment_amount|paid_amount|status|order_id UNIQUE|date, **clinic_id NULL FK clinics NULLOnDelete**
- `services`: id, name, description, price DECIMAL(12,2), duration INT, category, is_active, **clinic_id NOT NULL FK clinics CASCADE**. INDEX(clinic_id, is_active)
- `payment_methods`: id, name, type [qris|bank|ewallet], description, icon, is_active (global)
- `doctor_reviews`: id, doctor_id FK, user_id FK, booking_id FK UNIQUE, rating TINYINT, review
- `notifications`: id, user_id FK, title, body, type DEFAULT general, data JSON, read_at, **clinic_id NULL FK clinics NULLOnDelete**
- `vaccinations`: id, pet_id FK, vaccine_name, batch_number, date_administered, next_due_date, veterinarian, notes, reminder_sent
- `weight_records`: id, pet_id FK, weight DECIMAL(5,2), recorded_at DATE, notes
- `audit_logs`: id, user_id NULL SET NULL, action, model_type, model_id, description, ip_address, user_agent, **clinic_id NULL FK clinics NULLOnDelete**
- `payment_events`: id, booking_id FK, medical_record_id NULL FK, order_id UNIQUE, transaction_status, payment_type, gross_amount, payload JSON, **clinic_id NULL FK clinics NULLOnDelete**
- `app_settings`: id, key UNIQUE, value (Cache). + cache, jobs, personal_access_tokens.

## Rencana Fase 2
- `clinic_join_requests`: id, clinic_id FK CASCADE, name, email, phone, password_hash, status [pending|approved|rejected], reviewed_by, reviewed_at, timestamps. INDEX(clinic_id,status)
