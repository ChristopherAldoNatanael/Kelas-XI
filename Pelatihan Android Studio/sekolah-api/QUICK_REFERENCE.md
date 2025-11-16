# ⚡ QUICK REFERENCE - Edit Schedule Perbaikan

## 🔗 URLs yang Relevant

```
Index Schedules:    http://localhost:8000/web-schedules
Show Schedule:      http://localhost:8000/web-schedules/{id}
Edit Schedule:      http://localhost:8000/web-schedules/{id}/edit
Create Schedule:    http://localhost:8000/web-schedules/create
```

---

## 📁 Files Modified

### 1. Controller

**File:** `app/Http/Controllers/Web/WebScheduleController.php`

**Methods:**

-   `index()` - Line 45-113 (Fixed N/A display)
-   `update()` - Line 250-340 (Fixed update logic)

**Key Functions:**

```php
// Transform to array (line ~85)
$schedules = $scheduleModels->map(function ($schedule) { ... });

// Validate (line ~260)
$validated = $request->validate([...]);

// Update (line ~290)
$schedule->update($updateData);

// Verify (line ~300)
$updatedSchedule = Schedule::findOrFail($id);
```

---

### 2. Service

**File:** `app/Services/ScheduleOptimizationService.php`

**Methods:**

-   `getCachedSchedules()` - Line 14-75 (Include all relationships)

**Key Change:**

```php
// Include teacher_code & code fields (line ~30)
'teacher:id,user_id,teacher_code',
'subject:id,name,code',
'classroom:id,name,code'
```

---

### 3. View - Edit

**File:** `resources/views/schedules/edit.blade.php`

**Key Changes:**

-   Line 20: Hidden `class_id` input
-   Line 155-180: Form validation script

---

### 4. View - Index

**File:** `resources/views/schedules/index.blade.php`

**Key Changes:**

-   Line 95-160: Fixed array checking dengan `isset()`
-   Line 100: `isset($schedule['teacher']['nama'])` check

---

## 🧪 Quick Testing

### Test Update Success

```bash
cd /path/to/sekolah-api

# 1. Open browser
http://localhost:8000/web-schedules

# 2. Click Edit on any schedule

# 3. Change a field (e.g., Day: Monday → Tuesday)

# 4. Submit form

# 5. Check:
# - Redirected to index? ✓
# - Success message? ✓
# - Data updated? ✓

# 6. Verify database
php artisan tinker
>>> Schedule::find([SCHEDULE_ID])->day_of_week
=> "tuesday"
```

### Test Validation

```bash
# 1. Go to edit page
# 2. Submit with invalid time (end < start)
# 3. Should show error and not update database

# 4. Check database not changed
>>> Schedule::find([SCHEDULE_ID])->updated_at
=> [SHOULD BE OLD TIMESTAMP]
```

### Fix N/A Display

```bash
# If still seeing N/A:
php artisan cache:clear

# Then hard refresh browser:
# Ctrl+Shift+R (Windows/Linux)
# Cmd+Shift+R (Mac)
```

---

## 🔧 Common Commands

```bash
# Clear all cache
php artisan cache:clear

# View Laravel logs
tail -f storage/logs/laravel.log

# Connect to database
php artisan tinker

# Check a specific schedule
>>> Schedule::with(['teacher.user', 'subject', 'classroom'])->find(1);

# Check activity logs
>>> ActivityLog::where('action', 'update')->latest(5);

# Run tests (if exists)
php artisan test tests/Feature/ScheduleUpdateTest.php
```

---

## 📋 Validation Rules

**Client-side (JavaScript):**

```javascript
✓ Start time & end time required
✓ End time must be after start time
✓ Teacher must be selected
```

**Server-side (PHP):**

```php
✓ All fields required except notes
✓ end_time must be date_format:H:i and after:start_time
✓ period_number must be between 1-10
✓ Foreign keys must exist in database
✓ day must be: monday|tuesday|wednesday|thursday|friday|saturday|sunday
```

---

## 🚨 Troubleshooting

| Problem                | Solution                                               |
| ---------------------- | ------------------------------------------------------ |
| Still seeing N/A       | Run: `php artisan cache:clear`                         |
| Update doesn't save    | Check browser console for JS errors                    |
| Validation error loops | Verify all required fields have values                 |
| Teacher dropdown empty | Ensure subject is selected first                       |
| Redirect not working   | Check Laravel logs: `tail -f storage/logs/laravel.log` |
| Browser cache issue    | Hard refresh: `Ctrl+Shift+R`                           |

---

## 📊 Database Fields to Check

```sql
-- After update, these fields should change:
- day_of_week: 'monday' → 'tuesday'
- period_number: 1 → 2 (etc)
- start_time: '09:00' → '10:00'
- end_time: '10:30' → '11:00'
- notes: 'old note' → 'new note'
- updated_at: [OLD_TIME] → [NEW_TIME]
- updated_by: [USER_ID] (unchanged or same user)

-- These should NOT change:
- id: [ID] (primary key)
- class_id, subject_id, teacher_id, classroom_id: (unless changed)
- created_at: [CREATION_TIME] (never changes)
- created_by: [CREATOR_ID] (never changes)
```

---

## 🔄 Redirect Flow

```
Edit Form Submit
    ↓
POST /web-schedules/{id} with PUT method
    ↓
WebScheduleController@update
    ↓
Validate request
    ↓
Update database
    ↓
Clear cache
    ↓
Create activity log
    ↓
Redirect to GET /web-schedules with flash message
    ↓
Display index page with updated data
```

---

## 💾 Data Structure (Array Format)

```php
$schedule = [
    'id' => 1,
    'subject' => [
        'id' => 5,
        'name' => 'Bahasa Indonesia',
        'code' => 'BI'
    ],
    'teacher' => [
        'id' => 2,
        'nama' => 'Budi Hartono',
        'teacher_code' => 'TCH001'
    ],
    'classroom' => [
        'id' => 3,
        'name' => 'Ruang Kelas X RPL A',
        'code' => 'KRXRPLA'
    ],
    'day' => 'monday',
    'start_time' => '09:00',
    'end_time' => '10:30',
    'period_number' => 1,
    'notes' => 'Some notes'
];
```

---

## 📱 Browser Developer Tools

```javascript
// Open Console (F12)

// Check if form is being submitted
console.log("Form submitted");

// Check validation errors
console.log(formData);

// View all form data
new FormData(document.querySelector("form"));
```

---

## ✅ Sign-off Checklist

Before considering complete:

-   [ ] Edit form loads correctly
-   [ ] Form pre-populated with current data
-   [ ] Form validation works (both client & server)
-   [ ] Data saves to database
-   [ ] Redirect to index works
-   [ ] Success message displays
-   [ ] Updated data visible in table
-   [ ] No N/A values in table
-   [ ] Activity log recorded
-   [ ] Can edit multiple times
-   [ ] Cache works correctly

**When all ✓ → PRODUCTION READY** 🚀

---

## 📞 Quick Support

**Something not working?**

1. Check: `COMPLETE_TESTING_GUIDE.md` (detailed test cases)
2. Check: `RINGKASAN_PERBAIKAN.md` (complete overview)
3. Check: `PERBAIKAN_DATA_NA.md` (N/A fix details)
4. Run: `php artisan cache:clear`
5. Run: `php artisan tinker` → debug commands

**Still stuck?**

-   Check logs: `tail -f storage/logs/laravel.log`
-   Check database: phpMyAdmin
-   Check browser console: F12 → Console tab
-   Check network: F12 → Network tab → see request/response
