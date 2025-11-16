# Panduan Testing Edit Schedule Feature

## 📋 Overview

Dokumentasi ini menjelaskan bagaimana cara menjalankan dan memverifikasi bahwa fitur edit schedule bekerja dengan benar.

---

## 🧪 Jenis Testing

### 1. **Unit Testing** (Automated)

Menguji logika update secara langsung di database

### 2. **Feature Testing** (Automated)

Menguji seluruh workflow HTTP dari request hingga response

### 3. **Manual Testing** (Dari Web Interface)

Testing langsung melalui UI

---

## ⚙️ Setup Testing Environment

### Prerequisites:

-   Laravel installed
-   PHPUnit installed (sudah include di Laravel)
-   Database test environment siap
-   Dependencies sudah diinstall

### Setup:

1. **Copy `.env.example` ke `.env.testing`:**

```bash
cp .env .env.testing
```

2. **Edit `.env.testing` - Gunakan database terpisah untuk testing:**

```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

3. **Atau jika menggunakan MySQL:**

```env
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sekolah_test
DB_USERNAME=root
DB_PASSWORD=
```

---

## 🚀 Menjalankan Tests

### **Run All Tests:**

```bash
php artisan test
```

### **Run Specific Test Class:**

```bash
php artisan test tests/Feature/ScheduleUpdateTest.php
```

### **Run Specific Test Method:**

```bash
php artisan test tests/Feature/ScheduleUpdateTest.php::test_schedule_update_success
```

### **Run dengan Verbose Output:**

```bash
php artisan test --verbose
```

### **Run dengan Detailed Info:**

```bash
php artisan test --verbose --debug
```

---

## 📝 Daftar Test Cases

### **1. test_schedule_update_success**

-   ✅ Verifikasi bahwa update berhasil dilakukan
-   ✅ Memeriksa redirect ke halaman index
-   ✅ Memeriksa data terupdate di database
-   **Expected Result:** PASS

```bash
php artisan test --filter=test_schedule_update_success
```

---

### **2. test_schedule_update_end_time_must_be_after_start_time**

-   ✅ Verifikasi validasi end_time > start_time
-   ✅ Memastikan data tidak berubah jika validasi gagal
-   **Expected Result:** PASS (Validation error)

```bash
php artisan test --filter=test_schedule_update_end_time_must_be_after_start_time
```

---

### **3. test_schedule_update_missing_required_fields**

-   ✅ Verifikasi required field validation
-   ✅ Test ketika field penting hilang
-   **Expected Result:** PASS (Validation error untuk subject_id)

```bash
php artisan test --filter=test_schedule_update_missing_required_fields
```

---

### **4. test_schedule_update_invalid_day**

-   ✅ Verifikasi hanya hari valid yang diterima
-   **Expected Result:** PASS (Validation error)

```bash
php artisan test --filter=test_schedule_update_invalid_day
```

---

### **5. test_schedule_update_invalid_period_number**

-   ✅ Verifikasi period_number antara 1-10
-   **Expected Result:** PASS (Validation error)

```bash
php artisan test --filter=test_schedule_update_invalid_period_number
```

---

### **6. test_schedule_update_invalid_time_format**

-   ✅ Verifikasi format waktu HH:MM
-   **Expected Result:** PASS (Validation error)

```bash
php artisan test --filter=test_schedule_update_invalid_time_format
```

---

### **7. test_schedule_update_not_found**

-   ✅ Verifikasi error ketika schedule tidak ada
-   **Expected Result:** PASS (404 Not Found)

```bash
php artisan test --filter=test_schedule_update_not_found
```

---

### **8. test_schedule_update_only_notes**

-   ✅ Verifikasi update hanya field notes
-   ✅ Memastikan field lain tetap sama
-   **Expected Result:** PASS

```bash
php artisan test --filter=test_schedule_update_only_notes
```

---

### **9. test_schedule_update_timestamp_updated**

-   ✅ Verifikasi updated_at timestamp ter-update
-   **Expected Result:** PASS

```bash
php artisan test --filter=test_schedule_update_timestamp_updated
```

---

### **10. test_schedule_edit_form_loads**

-   ✅ Verifikasi form edit bisa diakses
-   ✅ Memeriksa view dan data yang dikirim
-   **Expected Result:** PASS

```bash
php artisan test --filter=test_schedule_edit_form_loads
```

---

### **11. test_schedule_edit_form_contains_current_data**

-   ✅ Verifikasi form sudah ter-populate dengan data current
-   **Expected Result:** PASS

```bash
php artisan test --filter=test_schedule_edit_form_contains_current_data
```

---

### **12. test_updated_by_user_is_recorded**

-   ✅ Verifikasi bahwa user yang update terekam
-   **Expected Result:** PASS

```bash
php artisan test --filter=test_updated_by_user_is_recorded
```

---

## 🧑‍💻 Manual Testing via Web Interface

### Step-by-Step Testing:

#### **1. Access Edit Schedule Page**

```
1. Login ke aplikasi
2. Navigate ke Schedule → All Schedules
3. Klik tombol "Edit" pada salah satu schedule
4. Verifikasi: Form tampil dengan data current
```

#### **2. Test Update with Valid Data**

```
1. Buka form edit schedule
2. Ubah beberapa field:
   - Ubah Day
   - Ubah Start Time
   - Ubah End Time
   - Ubah Period Number
   - Ubah Notes
3. Klik "Update Schedule"
4. Verifikasi:
   ✓ Redirect ke halaman All Schedules
   ✓ Muncul pesan "Schedule updated successfully"
   ✓ Data di list sudah berubah
   ✓ Buka ulang edit form - data baru tetap tersimpan
```

#### **3. Test Validation - Invalid Time**

```
1. Buka form edit schedule
2. Set:
   - Start Time: 11:00
   - End Time: 10:00 (lebih kecil dari start time)
3. Klik "Update Schedule"
4. Verifikasi: Error message muncul
```

#### **4. Test Validation - Invalid Period**

```
1. Buka form edit schedule
2. Set Period Number: 15 (lebih besar dari 10)
3. Klik "Update Schedule"
4. Verifikasi: Error message muncul
```

#### **5. Test Validation - Missing Teacher**

```
1. Buka form edit schedule
2. Clear/kosongkan Teacher field
3. Klik "Update Schedule"
4. Verifikasi: Error message muncul (client-side atau server-side)
```

---

## 🔍 Verification Methods

### **Method 1: Via Database Query**

```sql
-- Cek data schedule yang sudah diupdate
SELECT id, day_of_week, start_time, end_time, period_number,
       notes, updated_at, updated_by
FROM schedules
WHERE id = <schedule_id>;

-- Verifikasi updated_at dan updated_by sudah berubah
SELECT * FROM schedules
WHERE id = <schedule_id>
ORDER BY updated_at DESC;
```

### **Method 2: Via Activity Log**

```sql
-- Cek history update
SELECT * FROM activity_logs
WHERE model_type = 'Schedule'
  AND model_id = <schedule_id>
  AND action = 'update'
ORDER BY created_at DESC;

-- Lihat old_values dan new_values
SELECT id, user_id, action, old_values, new_values, created_at
FROM activity_logs
WHERE model_type = 'Schedule' AND action = 'update'
LIMIT 10;
```

### **Method 3: Via Application Logs**

```bash
# Tail log file
tail -f storage/logs/laravel.log

# Lihat log saat update terjadi
grep "Schedule update completed" storage/logs/laravel.log
```

### **Method 4: Via Cache**

```bash
# Clear cache jika ada
php artisan cache:clear

# Verify cache berhasil dihapus
php artisan cache:forget schedules
```

---

## ✅ Expected Test Results

### All Tests Should Pass:

```
✓ test_schedule_update_success ..................... PASS
✓ test_schedule_update_end_time_must_be_after_start_time ... PASS
✓ test_schedule_update_missing_required_fields ..... PASS
✓ test_schedule_update_invalid_day ................ PASS
✓ test_schedule_update_invalid_period_number ...... PASS
✓ test_schedule_update_invalid_time_format ........ PASS
✓ test_schedule_update_not_found ................. PASS
✓ test_schedule_update_only_notes ................ PASS
✓ test_schedule_update_timestamp_updated ......... PASS
✓ test_schedule_edit_form_loads .................. PASS
✓ test_schedule_edit_form_contains_current_data .. PASS
✓ test_updated_by_user_is_recorded ............... PASS

Tests: 12 passed ✓
```

---

## 🐛 Troubleshooting

### **Test Fails with "No such table"**

```bash
# Solution: Run migrations for test database
php artisan migrate --env=testing
```

### **Test Fails with "Connection refused"**

```bash
# Solution: Check database connection in .env.testing
# Make sure MySQL is running
# Or switch to SQLite in-memory
```

### **Test Fails with "Class not found"**

```bash
# Solution: Regenerate autoloader
composer dump-autoload
```

### **Tests Timeout**

```bash
# Solution: Increase timeout di phpunit.xml
<php>
    <ini name="default_socket_timeout" value="30" />
</php>
```

---

## 📊 Coverage Report

### Generate Coverage Report:

```bash
php artisan test --coverage
```

### Generate HTML Coverage Report:

```bash
php artisan test --coverage --coverage-html=coverage-report
```

---

## 🔐 Security Testing

### Test Points:

1. ✅ **Authentication**: User harus login
2. ✅ **Authorization**: User hanya bisa update schedule milik mereka (jika ada)
3. ✅ **Input Validation**: Semua input divalidasi
4. ✅ **SQL Injection**: Tidak bisa inject SQL (menggunakan Laravel ORM)
5. ✅ **CSRF Protection**: Form protected dengan @csrf token

### Run Security Tests:

```bash
php artisan test tests/Feature/ScheduleUpdateTest.php --filter="test_"
```

---

## 📈 Performance Testing

### Check Performance:

```bash
# Run test dengan timing
php artisan test --verbose --debug

# Log execution time
```

### Expected Performance:

-   Update query: < 100ms
-   Redirect: < 50ms
-   Total response: < 500ms

---

## 📚 Additional Resources

-   [Laravel Testing Documentation](https://laravel.com/docs/testing)
-   [PHPUnit Documentation](https://phpunit.de/)
-   [Laravel Database Testing](https://laravel.com/docs/database-testing)

---

## 🎯 Summary

Fitur Edit Schedule sudah diperbaiki dengan:

-   ✅ Validasi data ketat
-   ✅ Database update yang terjamin
-   ✅ Redirect yang benar
-   ✅ Error handling yang baik
-   ✅ Comprehensive test coverage
-   ✅ Activity logging

**Status: Ready for Production** ✅
