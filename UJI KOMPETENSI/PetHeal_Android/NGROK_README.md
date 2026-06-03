# 🔗 NGROK Configuration for PetHeal

## ⚡ Quick Start (Automated)

### Option 1: Use Setup Script (Recommended)

1. **Start Laravel Backend:**
   ```bash
   cd C:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Backend
   php artisan serve --port=8000
   ```

2. **Start ngrok:**
   ```bash
   ngrok http 8000
   ```

3. **Run Setup Script:**
   ```bash
   cd C:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Android
   setup_ngrok.bat
   ```

4. **Rebuild Android App:**
   ```bash
   gradlew.bat assembleDebug
   ```

**That's it!** The script automatically:
- ✅ Detects your ngrok URL
- ✅ Updates Android `local.properties`
- ✅ Updates Backend `.env`
- ✅ Clears Laravel cache

---

## 📝 Manual Setup

If you prefer manual configuration:

### Step 1: Get Your ngrok URL

```bash
ngrok http 8000
```

Copy the URL shown, e.g.: `https://abc123.ngrok-free.app`

### Step 2: Update Android Config

**File:** `PetHeal_Android/local.properties`

```properties
BACKEND_BASE_URL=https://abc123.ngrok-free.app/api/
```

**⚠️ Important:**
- Must use `https://` (not `http://`)
- Must include `/api/` at the end
- Must have trailing slash

### Step 3: Update Backend Config

**File:** `PetHeal_Backend/.env`

```env
APP_URL=https://abc123.ngrok-free.app
```

**⚠️ Important:**
- Must use `https://` (not `http://`)
- Must NOT include `/api/`
- Must NOT have trailing slash

### Step 4: Clear Cache & Rebuild

```bash
# Clear Laravel cache
cd PetHeal_Backend
php artisan config:clear
php artisan cache:clear

# Rebuild Android
cd ../PetHeal_Android
gradlew.bat assembleDebug
```

---

## 🎯 Configuration Rules

### ✅ CORRECT Format:

| Component | File | Variable | Value |
|-----------|------|----------|-------|
| Backend | `.env` | `APP_URL` | `https://abc123.ngrok-free.app` |
| Android | `local.properties` | `BACKEND_BASE_URL` | `https://abc123.ngrok-free.app/api/` |

### ❌ WRONG Examples:

```properties
# WRONG - Using HTTP
BACKEND_BASE_URL=http://abc123.ngrok-free.app/api/

# WRONG - Missing /api/
BACKEND_BASE_URL=https://abc123.ngrok-free.app

# WRONG - Has /api/ in backend
APP_URL=https://abc123.ngrok-free.app/api/

# WRONG - Trailing slash in backend
APP_URL=https://abc123.ngrok-free.app/
```

---

## 🔍 Verification

### Test Backend:
```bash
curl https://YOUR-NGROK-URL.ngrok-free.app/api/health
```

**Expected Response:**
```json
{
  "status": "ok",
  "message": "API is running"
}
```

### Test Android:
1. Check Logcat for API calls
2. Look for URLs containing your ngrok URL
3. Should NOT see `10.0.2.2` or `localhost`

---

## 🚨 Common Errors & Solutions

### Error: "Unable to resolve host"
**Cause:** ngrok URL mismatch or ngrok not running

**Solution:**
1. Ensure ngrok is running
2. Run `setup_ngrok.bat` again
3. Rebuild Android app

### Error: "ERR_CLEARTEXT_NOT_PERMITTED"
**Cause:** Using HTTP instead of HTTPS

**Solution:**
- Change `http://` to `https://` in both configs

### Error: "404 Not Found"
**Cause:** Missing or extra `/api/` suffix

**Solution:**
- Backend: `https://xxx.ngrok-free.app` (NO /api/)
- Android: `https://xxx.ngrok-free.app/api/` (WITH /api/)

---

## 📋 Files Modified

The setup script modifies these files:

1. **`PetHeal_Android/local.properties`**
   - Updates `BACKEND_BASE_URL` with ngrok URL + `/api/`

2. **`PetHeal_Backend/.env`**
   - Updates `APP_URL` with ngrok URL

**These files are in `.gitignore` and should NOT be committed!**

---

## 🔄 Daily Workflow

### Starting Work:
```bash
# Terminal 1 - Start Laravel
cd PetHeal_Backend
php artisan serve --port=8000

# Terminal 2 - Start ngrok
ngrok http 8000

# Terminal 3 - Run setup (if ngrok URL changed)
cd PetHeal_Android
setup_ngrok.bat

# Rebuild if needed
gradlew.bat assembleDebug
```

### Stopping Work:
```bash
# Stop ngrok (Ctrl+C in ngrok terminal)
# Stop Laravel (Ctrl+C in Laravel terminal)
# Configs can stay as-is for next session
```

---

## 💡 Pro Tips

1. **Keep ngrok running:** Don't close the ngrok terminal during development
2. **Same URL everywhere:** Backend and Android must use the same ngrok URL
3. **HTTPS only:** Never use HTTP with ngrok
4. **Rebuild after changes:** Always rebuild Android after changing URLs
5. **Use the script:** `setup_ngrok.bat` automates everything

---

## 📚 Full Documentation

For complete setup instructions, see: **`NGROK_SETUP_GUIDE.md`** in the root directory.

---

**Status:** ✅ Ready to use  
**Last Updated:** April 6, 2026
