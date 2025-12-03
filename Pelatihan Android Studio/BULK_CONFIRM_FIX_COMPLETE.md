# Bulk Confirm Attendance Fix - COMPLETE

## Problem

The "Confirm Attendance" feature from the Kurikulum Pending screen was not working. After selecting items and clicking confirm, the status wouldn't change.

## Root Cause Analysis

1. **Android app only sent `attendance_ids`** - IDs from the `teacher_attendances` table
2. **Items with `status: "belum_lapor"` have `id: null`** - No attendance record exists yet in the database
3. **UI filtered out items with null IDs** - `classGroup.schedules.filter { it.id != null }`, meaning "belum_lapor" items couldn't be selected
4. **Backend validation required existing IDs** - `'attendance_ids.*' => 'integer|exists:teacher_attendances,id'`

## Solution Implemented

### 1. Backend Changes (`KurikulumController.php`)

Updated `bulkConfirmAttendance()` to handle both scenarios:

```php
// Now accepts:
// - attendance_ids: for items with existing attendance records (status: pending)
// - schedule_items: for items without attendance records (status: belum_lapor)

$request->validate([
    'attendance_ids' => 'nullable|array',
    'attendance_ids.*' => 'integer|exists:teacher_attendances,id',
    'schedule_items' => 'nullable|array',
    'schedule_items.*.schedule_id' => 'required_with:schedule_items|integer|exists:schedules,id',
    'schedule_items.*.date' => 'required_with:schedule_items|date',
    'status' => 'required|in:hadir,telat'
]);
```

The endpoint now:

- Updates existing attendance records when `attendance_ids` is provided
- Creates new attendance records when `schedule_items` is provided
- Returns combined results with `confirmed_count`, `created_count`, and `skipped_count`

### 2. Android Model Changes (`KurikulumModels.kt`)

Updated `BulkConfirmRequest`:

```kotlin
data class BulkConfirmRequest(
    @SerializedName("attendance_ids") val attendanceIds: List<Int>? = null,
    @SerializedName("schedule_items") val scheduleItems: List<ScheduleItem>? = null,
    @SerializedName("status") val status: String
)

data class ScheduleItem(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("date") val date: String
)
```

### 3. ViewModel Changes (`KurikulumViewModel.kt`)

Updated `bulkConfirmAttendance()` to:

- Accept `List<PendingAttendanceItem>` instead of `List<Int>`
- Separate items into those with existing attendance records and those without
- Construct the proper request with both `attendanceIds` and `scheduleItems`

```kotlin
fun bulkConfirmAttendance(items: List<PendingAttendanceItem>, status: String) {
    val itemsWithAttendance = items.filter { it.id != null }
    val itemsWithoutAttendance = items.filter { it.id == null }

    val attendanceIds = itemsWithAttendance.mapNotNull { it.id }
    val scheduleItems = itemsWithoutAttendance.map {
        ScheduleItem(scheduleId = it.scheduleId, date = it.date)
    }

    val request = BulkConfirmRequest(
        attendanceIds = attendanceIds.ifEmpty { null },
        scheduleItems = scheduleItems.ifEmpty { null },
        status = status
    )
    // ... send request
}
```

### 4. UI Changes (`KurikulumPendingScreen.kt`)

- Changed `selectedItems` from `Set<Int>` to `Set<PendingAttendanceItem>`
- All items (both "pending" and "belum_lapor") now show checkboxes and can be selected
- Items are identified by `scheduleId + date` combination instead of just `id`
- Updated all related functions (`PendingContent`, `ClassGroupCard`, `PendingItemRow`)

## Files Modified

1. `sekolah-api/app/Http/Controllers/Api/KurikulumController.php`

   - `bulkConfirmAttendance()` method completely rewritten

2. `AplikasiMonitoringKelas/.../data/KurikulumModels.kt`

   - `BulkConfirmRequest` data class updated
   - New `ScheduleItem` data class added

3. `AplikasiMonitoringKelas/.../ui/viewmodel/KurikulumViewModel.kt`

   - `bulkConfirmAttendance()` method signature and logic updated

4. `AplikasiMonitoringKelas/.../ui/screens/kurikulum/KurikulumPendingScreen.kt`
   - `selectedItems` type changed
   - Selection logic updated
   - All item types now selectable

## Testing

1. Login as Kurikulum user
2. Go to Pending screen
3. Select items (both "pending" and "belum_lapor" should be selectable now)
4. Click "Konfirmasi X Terpilih"
5. Choose "Hadir" or "Telat"
6. Verify status changes and items are removed from the pending list

## Date Completed

December 3, 2025
