# Fix Soft Deletes Error Task

## Issue
Internal Server Error: Call to undefined method App\Models\User::trashed()
- Error occurs in users/index.blade.php line 141
- User model doesn't have Soft Deletes enabled
- View template expects soft delete functionality

## Tasks
- [ ] Examine User model current state
- [ ] Add SoftDeletes trait to User model
- [ ] Ensure deleted_at column exists in database
- [ ] Test the fix
- [ ] Verify all functionality works

## Files Involved
- App\Models\User.php
- users/index.blade.php
- users table migration
