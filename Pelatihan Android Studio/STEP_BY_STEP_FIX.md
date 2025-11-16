# 🎯 STEP-BY-STEP FIX - Follow This EXACTLY

## ❗ CONFIRMED PROBLEM

From your Laravel log, I can see the EXACT error:

```
SQLSTATE[HY000] [2002] No connection could be made because 
the target machine actively refused it
```

**This means: MySQL is NOT running or keeps stopping!**

---

## ✅ COMPLETE FIX - DO THESE STEPS IN ORDER

### STEP 1: Check if You're Using XAMPP or Laragon

**Please tell me which one you're using:**
- [ ] XAMPP
- [ ] Laragon  
- [ ] Other (WAMP, MAMP, etc.)

---

### STEP 2: Start MySQL Properly

#### If Using XAMPP:

1. Open **XAMPP Control Panel**
2. Look at the **MySQL** row
3. **What do you see?**
   - If button says "Start" → Click it
   - If button says "Stop" and background is GREEN → MySQL is running
   - If button says "Stop" but background is WHITE/RED → MySQL crashed

4. **If MySQL won't start:**
   - Click "Config" button next to MySQL
   - Select "my.ini"
   - Find line: `port=3306`
   - Change to: `port=3307`
   - Save and close
   - Try starting MySQL again

#### If Using Laragon:

1. Open **Laragon**
2. Click **"Start All"** button
3. Wait until all services show as running
4. Check if MySQL/MariaDB shows "Running"

---

### STEP 3: Run Diagnostic Script

**Open PowerShell in your project folder and run:**

```powershell
cd "Pelatihan Android Studio\sekolah-api"
php diagnose-complete.php
```

**STOP HERE and send me the output!**

The script will tell us:
- ✅ or ❌ if MySQL is accessible
- ✅ or ❌ if database exists
- ✅ or ❌ if PHP configuration is correct
- ✅ or ❌ if Laravel configuration is correct

---

### STEP 4: Fix Based on Diagnostic Results

**If diagnostic shows "Port not accessible":**

```powershell
# Check if MySQL is running
Get-Service | Where-Object {$_.Name -like "*mysql*"}

# If not running, start XAMPP/Laragon and try again
```

**If diagnostic shows "Database not found":**

```powershell
# Create database
php artisan db:create
# OR manually via phpMyAdmin: http://localhost/phpmyadmin
```

**If diagnostic shows "Wrong port":**

1. Open `.env` file
2. Find line: `DB_PORT=3306`
3. Change to match your MySQL port (check XAMPP/Laragon)
4. Save file
5. Run: `php artisan config:clear`

---

### STEP 5: Clear All Caches

```powershell
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

---

### STEP 6: Test Server Stability

**Terminal 1 - Start Server:**
```powershell
php artisan serve
```

**Terminal 2 - Run Stress Test:**
```powershell
# Open NEW PowerShell window
cd "Pelatihan Android Studio\sekolah-api"
php test-server.php
```

**Expected output:**
```
✅ Server is responding
✅ API endpoint working
✅ Server is stable under load
✅ ALL TESTS PASSED!
```

**If you see ❌ FAIL, STOP and tell me which test failed!**

---

### STEP 7: Monitor Server in Real-Time

**Terminal 3 - Watch Logs:**
```powershell
# Open ANOTHER PowerShell window
cd "Pelatihan Android Studio\sekolah-api"
Get-Content storage/logs/laravel.log -Wait -Tail 20
```

This will show you errors in real-time as they happen.

---

## 🔍 WHAT TO SEND ME

Please run these commands and send me the output:

### Command 1: Check MySQL Status
```powershell
php cek-mysql.php
```

### Command 2: Full Diagnostic
```powershell
php diagnose-complete.php
```

### Command 3: Check .env Settings
```powershell
Get-Content .env | Select-String "DB_"
```

---

## 🚨 COMMON ISSUES & QUICK FIXES

### Issue 1: "Port 3306 already in use"

**Fix:**
1. Find what's using port 3306:
   ```powershell
   netstat -ano | findstr :3306
   ```
2. Change MySQL port in XAMPP/Laragon to 3307
3. Update `.env`: `DB_PORT=3307`
4. Restart MySQL

### Issue 2: "Access denied for user 'root'"

**Fix:**
1. Open `.env`
2. Check `DB_PASSWORD=`
3. For XAMPP default, it should be EMPTY
4. Save and run: `php artisan config:clear`

### Issue 3: "Database 'db_sekolah' does not exist"

**Fix:**
1. Open browser: `http://localhost/phpmyadmin`
2. Click "Databases" tab
3. Type: `db_sekolah`
4. Click "Create"

### Issue 4: MySQL keeps crashing

**Fix:**
1. Restart computer
2. Start XAMPP/Laragon as Administrator
3. Start MySQL
4. If still crashes, increase MySQL memory:
   - XAMPP Config → my.ini
   - Find: `innodb_buffer_pool_size`
   - Change to: `256M`
   - Save and restart MySQL

---

## ✅ SUCCESS CRITERIA

You'll know it's fixed when:

1. **Diagnostic script shows:**
   ```
   ✅ PASS: Port 3306 terbuka dan bisa diakses
   ✅ PASS: Berhasil connect ke MySQL server
   ✅ PASS: Database 'db_sekolah' ditemukan
   ✅ ALL TESTS PASSED!
   ```

2. **Server test shows:**
   ```
   ✅ Server is responding
   ✅ API endpoint working
   ✅ Server is stable under load
   ```

3. **Laravel server runs without errors:**
   ```
   INFO  Server running on [http://127.0.0.1:8000].
   ```

4. **Android app:**
   - Login completes in < 2 seconds
   - Can navigate between pages
   - No crashes or timeouts

---

## 📞 NEXT STEPS

**RIGHT NOW, please do this:**

1. Check if MySQL is running in XAMPP/Laragon
2. Run: `php cek-mysql.php`
3. Send me the output
4. Tell me if you see ✅ or ❌

**Based on your output, I will give you the EXACT fix!**

---

## 🎯 TIMELINE

- **Step 1-2:** 2 minutes (check MySQL)
- **Step 3:** 1 minute (run diagnostic)
- **Step 4:** 5 minutes (fix issues found)
- **Step 5:** 1 minute (clear caches)
- **Step 6:** 2 minutes (test server)
- **Step 7:** Ongoing (monitoring)

**Total time to fix: ~10-15 minutes**

---

## 💡 WHY THIS WILL WORK

The log shows **MySQL connection refused** errors. This means:

1. MySQL is not running, OR
2. MySQL is running on wrong port, OR
3. MySQL keeps crashing

The diagnostic script will tell us EXACTLY which one it is, then we fix that specific issue.

**This is NOT a code problem - it's a MySQL configuration problem!**

---

**Please start with STEP 1 and STEP 3, then send me the results! 🚀**

