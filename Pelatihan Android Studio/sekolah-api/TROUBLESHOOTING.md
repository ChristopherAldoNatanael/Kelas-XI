# 🔧 TROUBLESHOOTING GUIDE - Edit Schedule

## Masalah Umum dan Solusinya

---

## ❌ Masalah 1: Data Tidak Terupdate di Database

### Gejala:

-   Update button diklik tapi data tidak berubah
-   Setelah redirect, data masih lama

### Penyebab Kemungkinan:

1. Database connection error
2. Validasi data gagal
3. Foreign key constraint
4. Cache masih aktif

### Solusi:

#### Step 1: Cek Database Connection

```bash
# Test connection
php artisan migrate:status

# Atau langsung test via SQL
mysql -u root -p -h 127.0.0.1 sekolah_api
mysql> SELECT 1;
```

**Jika error:**

-   Check credentials di `.env`
-   Pastikan MySQL running
-   Pastikan database exist

#### Step 2: Cek Logs untuk Error Messages

```bash
# Real-time log monitoring
tail -f storage/logs/laravel.log

# Search for update errors
grep -i "error.*update\|update.*error" storage/logs/laravel.log

# Search for validation errors
grep -i "validation" storage/logs/laravel.log
```

**Expected log:**

```
[2024-10-27 10:30:45] local.INFO: Schedule update completed [...]
[2024-10-27 10:30:45] local.INFO: Schedule data verified in database [...]
```

**Error log:**

```
[2024-10-27 10:30:45] local.ERROR: Error in update schedule [...]
```

#### Step 3: Verify Database Manually

```sql
-- Check schedule record exists
SELECT * FROM schedules WHERE id = <schedule_id>;

-- Check updated_at timestamp
SELECT id, day_of_week, start_time, end_time, updated_at
FROM schedules WHERE id = <schedule_id>;

-- Check if records exist for foreign keys
SELECT * FROM teachers WHERE id = <teacher_id>;
SELECT * FROM subjects WHERE id = <subject_id>;
SELECT * FROM classrooms WHERE id = <classroom_id>;
SELECT * FROM classes WHERE id = <class_id>;
```

#### Step 4: Clear Cache

```bash
# Clear all cache
php artisan cache:clear

# Clear config cache
php artisan config:clear

# Clear schedule cache specifically
php artisan cache:forget schedules

# Refresh browser (Ctrl+F5 or Cmd+Shift+R)
```

#### Step 5: Test Update via Artisan Tinker

```bash
php artisan tinker

# Inside tinker:
>>> $schedule = App\Models\Schedule::find(<schedule_id>);
>>> $schedule->update(['notes' => 'Test update ' . now()]);
>>> $schedule->refresh();
>>> echo $schedule->notes;  // Should show new notes
```

---

## ❌ Masalah 2: Validation Error Muncul

### Gejala:

-   Form dikembalikan ke edit page
-   Error message muncul

### Penyebab:

1. Start time >= end time
2. Period number tidak 1-10
3. Day tidak valid
4. Foreign key tidak ada

### Solusi:

#### Untuk Error "End time must be after start time"

```
Verifikasi:
- Start time: 08:00
- End time: 09:00 (harus lebih besar)

Debug:
  - Check form input values di browser DevTools
  - Check validation rule di controller
  - Pastikan format waktu H:i (bukan lain)
```

#### Untuk Error "Foreign Key Constraint"

```bash
# Check if record exists
mysql> SELECT id FROM teachers WHERE id = <teacher_id>;
mysql> SELECT id FROM subjects WHERE id = <subject_id>;

# If empty, data tidak ada, harus pilih yang ada
```

#### Untuk Error "Invalid Day"

```
Valid values: monday, tuesday, wednesday, thursday, friday, saturday, sunday

Debug:
  - Check form value sent
  - Verify select option values di HTML
  - Check validation rule: in:monday,tuesday,...
```

---

## ❌ Masalah 3: Redirect Tidak Terjadi

### Gejala:

-   Update berhasil (no error) tapi tidak redirect
-   Halaman masih di edit form

### Penyebab:

1. Route tidak terdaftar
2. AJAX request (tidak trigger redirect)
3. JavaScript error
4. Browser cache

### Solusi:

#### Step 1: Verify Route

```bash
# List all routes
php artisan route:list | grep web-schedules

# Should output:
# GET       /web-schedules                                  web-schedules.index
# GET       /web-schedules/create                          web-schedules.create
# POST      /web-schedules                                 web-schedules.store
# GET       /web-schedules/{web_schedule}                  web-schedules.show
# GET       /web-schedules/{web_schedule}/edit             web-schedules.edit
# PUT       /web-schedules/{web_schedule}                  web-schedules.update
# DELETE    /web-schedules/{web_schedule}                  web-schedules.destroy
```

#### Step 2: Check Browser Network Tab

```
1. Open DevTools (F12)
2. Go to Network tab
3. Click "Update Schedule"
4. Look for PUT request to /web-schedules/{id}
5. Check Response:
   - Status: 302 (Redirect)
   - Location header: should point to /web-schedules
```

#### Step 3: Check Browser Console

```
DevTools Console → Look for:
- JavaScript errors
- Network errors
- CORS issues
```

#### Step 4: Test with cURL

```bash
curl -X PUT http://localhost:8000/web-schedules/1 \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "X-CSRF-TOKEN: <token>" \
  -d "teacher_id=1&subject_id=1&classroom_id=1&class_id=1&day=tuesday&start_time=10:00&end_time=11:00&period_number=2" \
  -L  # Follow redirects

# Should output HTML dari index page
```

#### Step 5: Clear Browser Cache

```
- Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
- Clear cookies
- Clear local storage
- Incognito mode test
```

---

## ❌ Masalah 4: Data Lama Masih Tampil

### Gejala:

-   Update berhasil, redirect bekerja
-   Tapi di list schedule masih tampil data lama
-   Refresh page baru tampil yang baru

### Penyebab:

-   Cache masih aktif
-   Query menggunakan cache stale

### Solusi:

```bash
# Clear all caches
php artisan cache:clear

# Clear schedule cache specifically
php artisan cache:forget schedules

# Restart queue (jika ada background job)
php artisan queue:restart

# Clear browser cache
- Hard refresh page
```

### Test Cache Issue:

```sql
-- Direct query harus show updated data
SELECT * FROM schedules WHERE id = <id>;
```

Jika SQL menunjukkan data baru tapi UI masih lama = CACHE ISSUE
Jika SQL masih lama = DATABASE UPDATE ISSUE

---

## ❌ Masalah 5: Class_id Field Error

### Gejala:

-   Error: "class_id is required"
-   Form edit tidak bisa disubmit

### Penyebab:

-   Hidden field `class_id` tidak ada atau kosong
-   Schedule tidak punya relationship ke class

### Solusi:

#### Check Hidden Field di HTML

```javascript
// DevTools Console
document.getElementById("class_id").value; // Should have value

// Or check all form fields
new FormData(document.querySelector("form"));
```

#### Check Schedule Model

```bash
php artisan tinker
>>> $schedule = App\Models\Schedule::find(<id>);
>>> $schedule->class_id;  // Should have value
>>> $schedule->class;     // Should load relationship
```

#### Fix: Add Hidden Field

```blade
<input type="hidden" id="class_id" name="class_id" value="{{ $schedule->class->id ?? '' }}">
```

---

## ❌ Masalah 6: Teacher Dropdown Kosong

### Gejala:

-   Teacher dropdown tidak ada option
-   Error: "teacher_id is required" saat submit

### Penyebab:

-   Subject tidak punya teachers
-   JavaScript not running
-   Data loading error

### Solusi:

#### Step 1: Check Subject-Teacher Relationship

```bash
php artisan tinker
>>> $subject = App\Models\Subject::find(<subject_id>);
>>> $subject->teachers;  // Should return Collection
>>> $subject->teachers->count();  // Should > 0
```

Jika kosong:

```sql
-- Add teachers to subject
INSERT INTO subject_teacher (subject_id, teacher_id) VALUES (<subject_id>, <teacher_id>);
```

#### Step 2: Verify JavaScript Running

```javascript
// DevTools Console
// Should log when teacher select changes
console.log("Check if JavaScript loaded");

// Check if function exists
typeof loadTeachersForSubject; // Should be 'function'
```

#### Step 3: Check Console Errors

```javascript
// DevTools Console → Look for JavaScript errors
// Check network requests
```

---

## ❌ Masalah 7: CSRF Token Error

### Gejala:

-   Error: "CSRF token mismatch"
-   419 Page Expired

### Penyebab:

-   Session expired
-   CSRF token tidak valid
-   Form tidak punya @csrf

### Solusi:

#### Add CSRF Token to Form

```blade
<form method="POST" action="...">
    @csrf
    @method('PUT')
    ...
</form>
```

#### Check Session Configuration

```bash
# Check .env
CACHE_DRIVER=file  # or redis, etc
SESSION_DRIVER=file  # or redis, etc

# Clear sessions
php artisan cache:clear
```

#### Test CSRF Token

```javascript
// DevTools Console
// Check token exists
document.querySelector('input[name="_token"]').value;
```

---

## ❌ Masalah 8: Updated By User Tidak Terekam

### Gejala:

-   Update berhasil
-   Tapi updated_by masih menunjukkan user lama

### Penyebab:

-   User tidak authenticated
-   Auth::id() returning null

### Solusi:

#### Check if User Authenticated

```bash
php artisan tinker
>>> auth()->check();  // Should be true
>>> auth()->user()->id;  // Should have value
```

#### Check Controller Code

```php
// Should be in update() method
'updated_by' => Auth::id()

// Verify Auth facade imported
use Illuminate\Support\Facades\Auth;
```

#### Check logs

```bash
grep "updated_by" storage/logs/laravel.log
```

---

## ❌ Masalah 9: Activity Log Tidak Tercatat

### Gejala:

-   Update berhasil
-   Tapi activity_logs table kosong

### Penyebab:

-   Activity log table tidak ada
-   ActivityLog model error
-   Migration belum jalan

### Solusi:

#### Check Table Exists

```sql
mysql> SHOW TABLES LIKE 'activity_logs';
mysql> DESCRIBE activity_logs;
```

Jika tidak ada:

```bash
# Run migrations
php artisan migrate

# Check specific migration
php artisan migrate:status
```

#### Check ActivityLog Model

```bash
php artisan tinker
>>> App\Models\ActivityLog::count();  // Should work
>>> App\Models\ActivityLog::latest()->first();  // Check latest
```

#### Verify Controller Creates Log

```php
// In controller update() method
ActivityLog::create([
    'user_id' => Auth::id(),
    'action' => 'update',
    'model_type' => 'Schedule',
    'model_id' => $schedule->id,
    'old_values' => $oldValues,
    'new_values' => $newValues,
    ...
]);
```

---

## ❌ Masalah 10: Form Fields Not Pre-populated

### Gejala:

-   Buka edit form
-   Fields kosong, tidak ada data current

### Penyebab:

-   Schedule tidak load dengan relationships
-   Blade template syntax error
-   Data null

### Solusi:

#### Check Schedule Load

```bash
php artisan tinker
>>> $schedule = App\Models\Schedule::with(['class', 'subject', 'teacher', 'classroom'])->find(<id>);
>>> $schedule->toArray();  // Should show all data
```

#### Check Blade Syntax

```blade
<!-- Correct syntax -->
value="{{ $schedule->subject->id ?? '' }}"

<!-- Not correct -->
value="{{ $schedule->subject->id ?: '' }}"  <!-- This might not work -->
```

#### Add Debug in View

```blade
<!-- Temporarily add for debugging -->
<pre>{{ json_encode($schedule->toArray(), JSON_PRETTY_PRINT) }}</pre>
<pre>{{ json_encode($dropdownData, JSON_PRETTY_PRINT) }}</pre>
```

---

## 🔍 General Debugging Steps

### For Any Problem:

#### 1. Check Application Logs

```bash
tail -f storage/logs/laravel.log
```

#### 2. Check Database

```sql
-- Verify schedule exists
SELECT * FROM schedules WHERE id = <id>;

-- Check relationships
SELECT * FROM teachers WHERE id = (SELECT teacher_id FROM schedules WHERE id = <id>);
SELECT * FROM subjects WHERE id = (SELECT subject_id FROM schedules WHERE id = <id>);
```

#### 3. Run Tests

```bash
php artisan test tests/Feature/ScheduleUpdateTest.php
```

#### 4. Use Debugger

```php
// In controller, use dd() for debug dump
dd($validated);  // Dumps and dies
dump($schedule);  // Dumps but continues
```

#### 5. Check Network Traffic

-   Open DevTools Network tab
-   Check request/response headers
-   Check payload sent
-   Check response status code

#### 6. Test with Artisan Tinker

```bash
php artisan tinker
>>> // Test model operations
>>> // Test relationships
>>> // Test update directly
```

---

## 📞 When to Ask for Help

If after trying all troubleshooting:

1. **Collect Information:**

    - Error message (complete)
    - Steps to reproduce
    - Log file excerpt
    - Screenshots/video
    - Browser/PHP version

2. **Prepare Test Data:**

    - Export database
    - Share configuration (sanitized)
    - List of plugins/extensions

3. **Ask in Context:**
    - "Masalah apa?"
    - "Sudah coba apa?"
    - "Error message apa?"
    - "Expected behavior vs actual behavior"

---

## ✅ Quick Reference

| Problem             | Quick Fix                      |
| ------------------- | ------------------------------ |
| Data tidak update   | Clear cache, check logs        |
| Validation error    | Check form values, check rules |
| Tidak redirect      | Check routes, check logs       |
| Data lama tampil    | Hard refresh, clear cache      |
| CSRF error          | Add @csrf, clear session       |
| Activity log kosong | Run migrations                 |
| Form kosong         | Check relationships            |
| 404 error           | Check route exists             |
| 500 error           | Check logs, check DB           |
| Timeout             | Check query performance        |

---

**Last Updated:** October 27, 2025
**Version:** 1.0
