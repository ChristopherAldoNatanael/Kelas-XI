# Testing Guide - Critical Fixes Verification

This guide will help you test and verify that both critical issues have been resolved.

---

## 🔧 Prerequisites

Before testing, ensure:
1. ✅ MySQL/MariaDB server is running
2. ✅ Database `db_sekolah` exists and is populated
3. ✅ Laravel dependencies are installed (`composer install`)
4. ✅ Android Studio is set up with the app
5. ✅ Physical device or emulator is connected

---

## 📋 Test Plan

### Test 1: Verify Backend Fixes

**Step 1: Clear all caches**
```bash
cd "Pelatihan Android Studio\sekolah-api"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize:clear
```

**Step 2: Run the automated test script**
```bash
php test-fixes.php
```

**Expected Output:**
```
✅ PASS: Cache is set to 'file' (prevents memory exhaustion)
✅ PASS: Token cleanup is optimized with limit and date filter
✅ PASS: Stateful middleware removed (prevents session conflicts)
✅ PASS: Circuit breaker limit increased to 200 req/min
✅ PASS: Database optimizations and monitoring added
✅ PASS: Token cleanup command exists
✅ PASS: Database connection successful
```

**Step 3: Start Laravel server**
```bash
php artisan serve
```

**Expected Output:**
```
INFO  Server running on [http://127.0.0.1:8000].
```

---

### Test 2: Student Login (Issue #1)

**Objective:** Verify student login completes in < 2 seconds without timeout

**Steps:**
1. Open Android app on device/emulator
2. Select role: **"Siswa"**
3. Enter student credentials:
   - Email: (use a valid student email from database)
   - Password: (corresponding password)
4. Click **Login** button
5. Observe loading time

**Expected Results:**
- ✅ Login completes in **< 2 seconds**
- ✅ No network timeout errors
- ✅ Successfully navigates to student dashboard
- ✅ Student name and class displayed correctly

**If Test Fails:**
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check token count: `SELECT COUNT(*) FROM personal_access_tokens;`
3. Run cleanup: `php artisan tokens:cleanup`
4. Verify database connection in `.env`

---

### Test 3: Server Stability During Navigation (Issue #2)

**Objective:** Verify server remains stable during multiple page navigations

**Steps:**
1. Login as any role (Siswa, Kurikulum, or Kepala Sekolah)
2. Navigate to **Schedule/Jadwal** page
   - Observe: Page loads successfully
3. Navigate to **Attendance/Kehadiran** page
   - Observe: Page loads successfully
4. Navigate back to **Schedule** page
   - Observe: Page loads successfully
5. Repeat steps 2-4 **five more times**
6. Check Laravel server terminal

**Expected Results:**
- ✅ All pages load successfully every time
- ✅ Server remains running (no crashes)
- ✅ No "Connection refused" errors
- ✅ Response times remain consistent (< 3 seconds)
- ✅ Memory usage stays stable

**Monitor Server:**
Watch the Laravel server terminal for:
```
2025-11-05 08:51:27 /api/v1/test .................. ~ 3s
2025-11-05 08:51:31 /api/auth/login ............... ~ 2s
2025-11-05 08:59:52 /api/schedules-mobile ......... ~ 2s
```

**If Test Fails:**
1. Check if server crashed (terminal shows error)
2. Check memory usage: Task Manager → php.exe process
3. Verify cache setting: `php artisan config:show cache.default`
4. Check for errors in `storage/logs/laravel.log`

---

### Test 4: High Traffic Simulation

**Objective:** Verify system handles multiple concurrent users

**Steps:**
1. Have 3-5 students login simultaneously
2. Each student navigates between pages
3. Monitor server performance

**Expected Results:**
- ✅ All logins succeed
- ✅ Server remains stable
- ✅ No circuit breaker triggers (429 errors)
- ✅ Response times < 5 seconds under load

---

### Test 5: Token Cleanup Verification

**Objective:** Verify automated token cleanup works

**Step 1: Check current token count**
```bash
php artisan tinker
```
```php
DB::table('personal_access_tokens')->count();
```

**Step 2: Run manual cleanup**
```bash
php artisan tokens:cleanup
```

**Expected Output:**
```
Cleaning up old authentication tokens...
Deleted X old tokens.
Deleted Y unused tokens.
Token cleanup completed successfully!
```

**Step 3: Verify count decreased**
```bash
php artisan tinker
```
```php
DB::table('personal_access_tokens')->count();
```

---

## 📊 Performance Benchmarks

### Before Fixes
| Metric | Value |
|--------|-------|
| Student Login Time | 30+ seconds |
| Server Uptime | Crashes after 2-3 requests |
| Memory Usage | Grows unbounded |
| Circuit Breaker False Positives | High (50 req/min limit) |

### After Fixes (Expected)
| Metric | Value |
|--------|-------|
| Student Login Time | < 2 seconds |
| Server Uptime | Stable indefinitely |
| Memory Usage | Stable at ~50MB |
| Circuit Breaker False Positives | Low (200 req/min limit) |

---

## 🐛 Troubleshooting

### Issue: Login still times out

**Solution:**
```bash
# 1. Check database connection
php artisan db:show

# 2. Check token count
php artisan tinker
DB::table('personal_access_tokens')->count();

# 3. Run cleanup
php artisan tokens:cleanup

# 4. Clear all caches
php artisan optimize:clear

# 5. Restart server
php artisan serve
```

### Issue: Server still crashes

**Solution:**
```bash
# 1. Verify cache setting
cat .env | grep CACHE_STORE
# Should show: CACHE_STORE=file

# 2. Clear cache
php artisan cache:clear

# 3. Check memory limit
php -i | grep memory_limit
# Should be at least 256M

# 4. Restart server
php artisan serve
```

### Issue: Circuit breaker triggers

**Solution:**
```bash
# 1. Check logs
tail -f storage/logs/laravel.log

# 2. Verify limit in EmergencyCircuitBreaker.php
# Should be: if ($currentCount > 200)

# 3. Clear cache
php artisan cache:clear
```

---

## ✅ Success Criteria

All tests pass when:
- ✅ Student login completes in < 2 seconds
- ✅ No timeout or network errors
- ✅ Server remains stable for 10+ page navigations
- ✅ Memory usage stays constant
- ✅ No circuit breaker false positives
- ✅ Multiple concurrent users work smoothly

---

## 📝 Test Results Template

Copy and fill this out after testing:

```
=== Test Results ===
Date: ___________
Tester: ___________

Test 1 - Backend Fixes: [ ] PASS [ ] FAIL
Test 2 - Student Login: [ ] PASS [ ] FAIL
  - Login time: _____ seconds
  - Errors: ___________
  
Test 3 - Server Stability: [ ] PASS [ ] FAIL
  - Navigations completed: _____
  - Server crashes: _____
  - Errors: ___________
  
Test 4 - High Traffic: [ ] PASS [ ] FAIL
  - Concurrent users: _____
  - Errors: ___________
  
Test 5 - Token Cleanup: [ ] PASS [ ] FAIL
  - Tokens before: _____
  - Tokens after: _____
  - Deleted: _____

Overall Status: [ ] ALL PASS [ ] SOME FAIL

Notes:
___________________________________________
___________________________________________
```

---

## 🚀 Next Steps After Testing

If all tests pass:
1. ✅ Deploy to production
2. ✅ Set up Laravel scheduler for automated token cleanup
3. ✅ Monitor logs for first 24 hours
4. ✅ Document any issues found

If any tests fail:
1. ❌ Review error logs
2. ❌ Check CRITICAL_FIXES_APPLIED.md for troubleshooting
3. ❌ Contact developer for support
4. ❌ Do NOT deploy to production

---

## 📞 Support

If you encounter issues during testing:
1. Check `CRITICAL_FIXES_APPLIED.md` for detailed fix documentation
2. Review Laravel logs: `storage/logs/laravel.log`
3. Check Android logcat for mobile app errors
4. Verify all prerequisites are met

---

**Good luck with testing! 🎉**

