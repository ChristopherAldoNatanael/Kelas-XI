# 📋 COMPLETE TESTING GUIDE - Edit & Update Schedule

## ✅ Checklist Lengkap Perbaikan

Panduan lengkap untuk memastikan fitur **Edit Schedule** berfungsi dengan sempurna:

### 1. Database Update ✓

-   [x] Data benar-benar tersimpan di database setelah submit form
-   [x] Field yang diupdate: subject, teacher, classroom, day, time, period, notes
-   [x] Timestamp `updated_at` otomatis ter-update
-   [x] Field `updated_by` ter-set dengan user ID yang sedang login

**Testing:**

```sql
-- Sebelum edit
SELECT * FROM schedules WHERE id = [SCHEDULE_ID];

-- Lakukan edit schedule
-- Ubah hari dari 'monday' menjadi 'tuesday'

-- Setelah edit
SELECT * FROM schedules WHERE id = [SCHEDULE_ID];
-- Verifikasi: day_of_week = 'tuesday', updated_at = [NEW_TIMESTAMP]
```

### 2. Form Validation ✓

-   [x] Semua required fields harus diisi
-   [x] Time validation: end_time harus after start_time
-   [x] Period number: 1-10 saja
-   [x] Subject, Teacher, Classroom harus dipilih
-   [x] Error message ditampilkan di form jika validasi gagal

**Testing:**

```
A. Submit dengan start_time = end_time
   → Error: "End time must be after start time"

B. Submit tanpa pilih teacher
   → Error: "Please select a teacher"

C. Submit dengan period_number = 0
   → Error: "period_number must be between 1 and 10"

D. Submit dengan valid data
   → Success: redirect ke index dengan message "Schedule updated successfully"
```

### 3. Redirect Behavior ✓

-   [x] Setelah update berhasil: redirect ke `/web-schedules` (index page)
-   [x] Flash message ditampilkan: "Schedule updated successfully and changes saved to database"
-   [x] Data yang diupdate langsung tampil di index table

**Testing:**

```
1. Edit schedule
2. Ubah beberapa field
3. Klik "Update Schedule"
4. Verifikasi:
   - Redirect ke All Schedules page
   - Green success message tampil di atas
   - Data yang diubah langsung visible di table
```

### 4. Data Tetap Terjaga ✓

-   [x] Data lama tidak hilang jika ada error
-   [x] Form ter-populate dengan data lama jika ada validation error
-   [x] Relationships (teacher, subject, dll) tetap intact

**Testing:**

```
1. Edit schedule
2. Ubah beberapa field dengan data invalid (misal: invalid time)
3. Submit
4. Verifikasi:
   - Form masih terbuka dengan old values
   - Error message ditampilkan
   - Data yang sudah diisi tetap ada (withInput())
```

### 5. Activity Log ✓

-   [x] Setiap update ter-log di activity_logs table
-   [x] Old values vs new values ter-record
-   [x] User, IP address, user agent ter-save
-   [x] Timestamp otomatis ter-record

**Testing:**

```php
// Di tinker:
>>> ActivityLog::where('action', 'update')->latest()->first()->toArray();

// Verifikasi:
// - user_id = current user
// - action = 'update'
// - model_type = 'Schedule'
// - old_values berisi data sebelumnya
// - new_values berisi data sesudahnya
```

### 6. UI/UX Perbaikan ✓

-   [x] Form inputs ter-populate dengan data current schedule
-   [x] Teacher dropdown di-load dynamically berdasarkan subject
-   [x] All dropdowns pre-selected dengan current values
-   [x] Cancel button membawa ke index page
-   [x] Success message user-friendly

**Testing:**

```
1. Buka edit page untuk existing schedule
2. Verifikasi:
   - Subject sudah ter-select
   - Teacher sudah ter-select (based on subject)
   - Classroom sudah ter-select
   - Day sudah ter-select
   - Time inputs sudah filled
   - Period number sudah filled
   - Notes sudah filled (jika ada)
3. Click subject dropdown → teacher otomatis update
```

### 7. N/A Display Fixed ✓

-   [x] Teacher name muncul di index (bukan N/A)
-   [x] Day muncul di index (bukan N/A)
-   [x] Subject muncul di index
-   [x] Classroom muncul di index
-   [x] All times muncul di index

**Testing:**

```
1. Go to All Schedules page
2. Verifikasi:
   - Teacher column menampilkan nama guru (e.g., "Budi Hartono")
   - Day column menampilkan hari (e.g., "Monday")
   - Subject column menampilkan nama (e.g., "Bahasa Indonesia")
   - Classroom column menampilkan nama (e.g., "Ruang Kelas X RPL A")
   - Time column menampilkan time range (e.g., "09:00 - 10:30")
3. Jika masih ada N/A → Run: php artisan cache:clear
```

---

## 🧪 STEP-BY-STEP TESTING

### Test Case 1: Successful Update

```
1. Navigate ke: http://localhost:8000/web-schedules
2. Cari schedule dengan subject "Bahasa Indonesia"
3. Click "Edit" button
4. Verify:
   - Form populated dengan current data
   - Teacher dropdown pre-selected
5. Change:
   - Subject: Keep same
   - Day: Monday → Tuesday
   - Start Time: 09:00 → 10:00
   - Notes: "Updated via test" (jika kosong sebelumnya)
6. Click "Update Schedule"
7. Verify:
   - Redirect ke All Schedules
   - Green message: "Schedule updated successfully"
   - Table shows updated day as "Tuesday"
   - Table shows updated time as "10:00 - ..."
8. Check database:
   - php artisan tinker
   - >>> Schedule::find([SCHEDULE_ID])->day_of_week;
   - Output harus: "tuesday"
```

### Test Case 2: Validation Error

```
1. Navigate ke edit page
2. Clear start_time field (biarkan kosong)
3. Click "Update Schedule"
4. Verify:
   - Form tetap terbuka
   - Error message: "start_time field is required"
   - Old values masih ada di form
   - No update di database
```

### Test Case 3: Time Validation

```
1. Navigate ke edit page
2. Set:
   - Start Time: 10:00
   - End Time: 10:00 (sama dengan start time)
3. Click "Update Schedule"
4. Verify:
   - Alert: "End time must be after start time"
   - Form tetap terbuka
   - No database update
```

### Test Case 4: Teacher Pre-selection

```
1. Navigate ke edit page
2. Change subject to different subject
3. Verify:
   - Teacher dropdown updated dengan teachers dari subject baru
   - Jika schedule berisi teacher dari subject lama, teacher harus dikosongkan
4. Select new teacher
5. Submit
6. Verify database updated
```

---

## 🔍 DEBUG COMMANDS

Jika ada issues, gunakan commands ini:

```bash
# 1. Clear all cache
php artisan cache:clear

# 2. Check database connection
php artisan tinker
>>> DB::connection()->getPdo();

# 3. Check schedule data
>>> Schedule::with(['teacher.user', 'subject', 'classroom'])->find(1)->toArray();

# 4. Check activity logs
>>> ActivityLog::where('action', 'update')->latest()->first()->toArray();

# 5. Check pending database changes
>>> DB::select("SHOW PROCESSLIST;");

# 6. View Laravel logs
tail -f storage/logs/laravel.log
```

---

## 📝 Expected Results Summary

### ✅ All Passing

-   [x] Edit form loads with current data
-   [x] Form validation works correctly
-   [x] Data updates in database
-   [x] Redirect to index page
-   [x] Updated data visible in table
-   [x] Activity log recorded
-   [x] No N/A values in table
-   [x] Teacher dynamically loaded based on subject
-   [x] Cancel button works
-   [x] Success message displays

### ⚠️ If Not Passing

1. **Clear cache**: `php artisan cache:clear`
2. **Check logs**: `tail -f storage/logs/laravel.log`
3. **Database check**: Verify data in phpMyAdmin
4. **Browser cache**: Ctrl+Shift+R (hard refresh)
5. **Check relationships**: Verify teacher, subject, classroom have data
6. **Check .env**: Ensure DB connection correct

---

## 📊 Performance Check

Sebaiknya check juga performance:

```bash
# Run performance test
php artisan tinker
>>> time(function() { return Schedule::with(['teacher.user', 'subject', 'classroom'])->get(); });

# Should execute in < 100ms untuk 100+ records
```

---

## ✨ FINAL VERIFICATION CHECKLIST

-   [ ] Database data ter-update dengan benar
-   [ ] Redirect ke index setelah update
-   [ ] Flash message ditampilkan
-   [ ] Updated data visible di table
-   [ ] No N/A values di table
-   [ ] Form validation bekerja
-   [ ] Activity log ter-record
-   [ ] Teacher dropdown dinamis
-   [ ] All required fields filled
-   [ ] Time validation bekerja
-   [ ] Can edit multiple times
-   [ ] Old data tidak hilang jika error
-   [ ] Cancel button bekerja
-   [ ] Performance acceptable

**Jika semua ✓, perbaikan BERHASIL! 🎉**
