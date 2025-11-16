# Dokumentasi Perbaikan Edit Schedule

## Summary

Saya telah memperbaiki fitur edit schedule untuk memastikan:

1. ✅ Data berhasil diupdate di database dengan benar
2. ✅ Redirect pengguna ke halaman all schedule setelah update berhasil
3. ✅ Semua perubahan terekam di database
4. ✅ Validasi data yang ketat di sisi server
5. ✅ Logging aktivitas update untuk audit trail

---

## Perubahan yang Dilakukan

### 1. **Perbaikan File Blade (edit.blade.php)**

#### Perubahan Utama:

-   ✅ **Menambahkan hidden field `class_id`** - Field ini diperlukan oleh database dan controller tapi tidak ditampilkan di form
-   ✅ **Mengubah field `day` menjadi select dengan value yang benar** (monday-sunday) sesuai database
-   ✅ **Menambahkan validasi client-side** untuk:
    -   Memastikan kedua waktu (start_time dan end_time) terisi
    -   Memastikan end_time lebih besar dari start_time
    -   Memastikan teacher dipilih sebelum submit

#### Kode Penting:

```blade
<!-- Hidden field untuk class_id -->
<input type="hidden" id="class_id" name="class_id" value="{{ $schedule->class->id ?? '' }}">

<!-- Validasi di client-side -->
document.querySelector('form').addEventListener('submit', function(e) {
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;

    if (!startTime || !endTime) {
        e.preventDefault();
        alert('Please select both start time and end time');
        return false;
    }

    if (startTime >= endTime) {
        e.preventDefault();
        alert('End time must be after start time');
        return false;
    }

    if (!teacherSelect.value) {
        e.preventDefault();
        alert('Please select a teacher');
        return false;
    }
});
```

---

### 2. **Perbaikan Controller (WebScheduleController.php)**

#### Perubahan pada Method `update()`:

**A. Validasi Data yang Lebih Ketat:**

```php
$validated = $request->validate([
    'teacher_id' => 'required|integer|exists:teachers,id',
    'subject_id' => 'required|integer|exists:subjects,id',
    'classroom_id' => 'required|integer|exists:classrooms,id',  // Changed from nullable
    'class_id' => 'required|integer|exists:classes,id',
    'day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
    'start_time' => 'required|date_format:H:i',
    'end_time' => 'required|date_format:H:i|after:start_time',  // Validates end > start
    'period_number' => 'required|integer|between:1,10',
    'notes' => 'nullable|string|max:500',
]);
```

**B. Update Data dengan Struktur yang Jelas:**

```php
$updateData = [
    'class_id' => $validated['class_id'],
    'subject_id' => $validated['subject_id'],
    'teacher_id' => $validated['teacher_id'],
    'classroom_id' => $validated['classroom_id'],
    'day_of_week' => $validated['day'],  // Langsung pakai value dari form
    'period_number' => $validated['period_number'],
    'start_time' => $validated['start_time'],
    'end_time' => $validated['end_time'],
    'notes' => $validated['notes'] ?? null,
    'updated_by' => Auth::id()
];

$schedule->update($updateData);
```

**C. Verifikasi Update di Database:**

```php
// Verify data was saved to database
$updatedSchedule = Schedule::findOrFail($id);
Log::info('Schedule data verified in database', ['updated_schedule' => $updatedSchedule->toArray()]);
```

**D. Logging Aktivitas:**

```php
ActivityLog::create([
    'user_id' => Auth::id(),
    'action' => 'update',
    'model_type' => 'Schedule',
    'model_id' => $schedule->id,
    'old_values' => $oldValues,
    'new_values' => [
        // Semua data baru dicatat untuk audit
    ],
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
]);
```

**E. Redirect ke Index Page:**

```php
return redirect()->route('web-schedules.index')
    ->with('success', 'Schedule updated successfully and changes saved to database.');
```

**F. Better Error Handling:**

```php
} catch (\Illuminate\Validation\ValidationException $e) {
    Log::warning('Validation error in update schedule', ['id' => $id, 'errors' => $e->errors()]);
    return back()->withErrors($e->errors())->withInput();
} catch (\Exception $e) {
    Log::error('Error in update schedule', ['id' => $id, 'error' => $e->getMessage()]);
    return back()->withErrors('Failed to update schedule: ' . $e->getMessage())->withInput();
}
```

---

## Alur Kerja Update Schedule

```
1. User buka halaman edit schedule
   ↓
2. Controller load data schedule dengan relationships
   ↓
3. Form tampil dengan data ter-populate
   ↓
4. User edit data dan klik "Update Schedule"
   ↓
5. Client-side validation berjalan
   ↓
6. Form submitted ke controller update()
   ↓
7. Server-side validation dengan rule ketat
   ↓
8. Jika valid → Data diupdate di database
   ↓
9. Verifikasi update berhasil dengan query ulang
   ↓
10. Cache dihapus
   ↓
11. Activity log dicatat
   ↓
12. Redirect ke halaman index dengan success message
   ↓
13. User lihat list schedule yang sudah diupdate
```

---

## Database Fields yang Diupdate

| Field         | Type      | Notes                   |
| ------------- | --------- | ----------------------- |
| class_id      | integer   | ID kelas                |
| subject_id    | integer   | ID mata pelajaran       |
| teacher_id    | integer   | ID guru                 |
| classroom_id  | integer   | ID ruang kelas          |
| day_of_week   | string    | Hari (monday-sunday)    |
| period_number | integer   | Nomor periode (1-10)    |
| start_time    | time      | Waktu mulai             |
| end_time      | time      | Waktu selesai           |
| notes         | text      | Catatan tambahan        |
| updated_by    | integer   | ID user yang update     |
| updated_at    | timestamp | Waktu update (otomatis) |

---

## Fitur Keamanan & Validasi

### Server-Side Validation:

✅ Semua field required dicheck
✅ Foreign key validation (teacher, subject, classroom, class ada di DB)
✅ Format time validation (HH:MM)
✅ End time harus > start time
✅ Period number antara 1-10
✅ Day harus salah satu dari 7 hari

### Logging:

✅ Old values sebelum update
✅ New values setelah update
✅ IP address pengguna
✅ User agent
✅ Timestamp update

### Cache Management:

✅ Cache dihapus setelah update untuk menghindari stale data
✅ Query akan langsung hit database untuk data terbaru

---

## Testing

Untuk menguji apakah update berfungsi dengan baik:

### Via Web Interface:

1. Login ke aplikasi
2. Buka halaman Schedule
3. Klik Edit pada salah satu schedule
4. Ubah beberapa field
5. Klik "Update Schedule"
6. Verifikasi:
    - ✅ Redirect ke halaman index
    - ✅ Muncul pesan success
    - ✅ Data di list sudah berubah
    - ✅ Buka ulang form edit - data masih tetap barunya

### Via Database Direct:

```sql
SELECT * FROM schedules WHERE id = <schedule_id>;
-- Lihat bahwa field-field sudah berubah sesuai update
-- updated_at timestamp sudah ter-update
```

### Via Activity Log:

```sql
SELECT * FROM activity_logs
WHERE model_type = 'Schedule' AND action = 'update'
ORDER BY created_at DESC;
-- Lihat bahwa activity tercatat dengan old_values dan new_values
```

---

## Troubleshooting

### Jika data tidak terupdate:

1. Cek logs di `storage/logs/laravel.log`
2. Lihat error message di form
3. Pastikan user yang login punya permission (jika ada)
4. Check database connection
5. Pastikan field classroom_id tidak nullable (sudah diperbaiki)

### Jika redirect tidak bekerja:

1. Pastikan route 'web-schedules.index' terdaftar di routes/web.php
2. Check server logs untuk error redirect

### Jika data lama masih tampil:

1. Clear cache: `php artisan cache:clear`
2. Clear config: `php artisan config:clear`
3. Refresh browser (Ctrl+F5)

---

## Files yang Dimodifikasi

1. ✅ `resources/views/schedules/edit.blade.php`

    - Menambahkan hidden field class_id
    - Perbaikan form validation
    - Menambahkan client-side validation script

2. ✅ `app/Http/Controllers/Web/WebScheduleController.php`
    - Perbaikan method `update()`
    - Validasi yang lebih ketat
    - Verifikasi data di database
    - Better error handling
    - Improved logging

---

## Kesimpulan

Semua perubahan telah implementasi dengan baik untuk memastikan:

-   ✅ Data terupdate di database dengan benar
-   ✅ Redirect ke halaman index berhasil
-   ✅ User mendapat feedback yang jelas (success message)
-   ✅ Perubahan terekam di activity log
-   ✅ Validasi ketat di server dan client
-   ✅ Cache management yang tepat

Sekarang fitur edit schedule siap digunakan dengan aman dan reliable! 🎉
