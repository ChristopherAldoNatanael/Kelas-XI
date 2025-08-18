# 🚀 Panduan Upload Project ke GitHub

## ✅ Checklist Sebelum Upload

### 1. Verifikasi File yang Sudah Dilindungi

File-file berikut sudah masuk ke `.gitignore` dan TIDAK akan ter-upload:

#### 🔒 Data Sensitif (AMAN):

- ✅ `google-services.json` - Firebase configuration
- ✅ `orders_dummy.json` - Data dummy dengan info pribadi
- ✅ `local.properties` - SDK path local
- ✅ `*.md` file dokumentasi pribadi (DOKUMENTASI_LENGKAP.md, PRESENTASI_RINGKAS.md, dll)

#### 🔒 File Pribadi (AMAN):

- ✅ API keys dan secrets
- ✅ Keystore files (_.jks, _.keystore)
- ✅ Database files pribadi
- ✅ Screenshot/demo data pribadi

#### 🔒 File Development (AMAN):

- ✅ Build files (`/build/`, `.gradle/`)
- ✅ IDE files (`.idea/`, `*.iml`)
- ✅ Log files dan temporary files

## 📋 Langkah-langkah Upload ke GitHub

### Step 1: Persiapan Local Repository

```bash
# Masuk ke folder project
cd "c:\Kelas XI RPL\AppProject\AdminWafeOfFood"

# Initialize git (jika belum)
git init

# Add gitignore
git add .gitignore

# Check files yang akan di-commit (pastikan tidak ada file sensitif)
git status
```

### Step 2: First Commit

```bash
# Add all safe files
git add .

# Create initial commit
git commit -m "Initial commit: AdminWafeOfFood - Restaurant Admin Dashboard

- Complete order management system
- Dashboard with real-time analytics
- Menu management features
- Firebase authentication integration
- Material Design UI
- MVVM architecture implementation"
```

### Step 3: Connect to GitHub

```bash
# Add remote repository (ganti dengan URL repo GitHub Anda)
git remote add origin https://github.com/USERNAME/AdminWafeOfFood.git

# Push to GitHub
git push -u origin main
```

## 🛡️ Panduan Keamanan GitHub

### ✅ Yang AMAN untuk Di-upload:

- Source code (.kt files)
- Layout files (.xml)
- Resource files (strings, colors, styles)
- Build configuration (build.gradle.kts)
- README untuk GitHub
- Project structure dan architecture

### ❌ Yang TIDAK BOLEH Di-upload:

- File `google-services.json`
- API keys atau credentials
- Data dummy dengan info pribadi
- Dokumentasi yang berisi info sekolah/pribadi
- Screenshot yang mungkin ada info sensitif
- Database files dengan data real

## 📁 Struktur yang Akan Ter-upload

```
AdminWafeOfFood/
├── .gitignore                    ✅ Upload
├── README_GITHUB.md             ✅ Upload (rename to README.md)
├── build.gradle.kts             ✅ Upload
├── settings.gradle.kts          ✅ Upload
├── app/
│   ├── build.gradle.kts         ✅ Upload
│   ├── proguard-rules.pro       ✅ Upload
│   └── src/
│       └── main/
│           ├── java/            ✅ Upload (all .kt files)
│           ├── res/             ✅ Upload (layouts, values, etc)
│           └── AndroidManifest.xml ✅ Upload
├── gradle/                      ✅ Upload
└── gradle files                 ✅ Upload
```

## 🔍 Verifikasi Sebelum Public

### 1. Check GitHub Repository

Setelah upload, cek di GitHub web interface:

- ✅ Tidak ada file `google-services.json`
- ✅ Tidak ada file `orders_dummy.json`
- ✅ Tidak ada dokumentasi pribadi (DOKUMENTASI_LENGKAP.md, dll)
- ✅ Tidak ada info sekolah/nama asli dalam code comments

### 2. Test Clone

Test dengan clone repository ke folder baru:

```bash
git clone https://github.com/USERNAME/AdminWafeOfFood.git test-clone
cd test-clone
# Pastikan project bisa dibuka di Android Studio
# Pastikan tidak ada file sensitif
```

## 📝 Tips Repository GitHub yang Baik

### 1. Repository Description

```
🍽️ Android restaurant management system for administrators. Built with Kotlin, Firebase, and Material Design. Features order management, menu control, and real-time dashboard analytics.
```

### 2. Topics/Tags yang Cocok

```
android kotlin firebase material-design mvvm restaurant-management
order-management mobile-app android-studio educational-project
```

### 3. Repository Settings

- ✅ Public repository (jika mau dipublic)
- ✅ Add README
- ✅ Add .gitignore (Android template)
- ✅ Add license (jika diperlukan)

## ⚠️ PERINGATAN PENTING

1. **Jangan pernah commit file `google-services.json`**
2. **Jangan commit data dummy dengan info pribadi**
3. **Jangan commit dokumentasi yang ada info sekolah/nama asli**
4. **Selalu cek `git status` sebelum commit**
5. **Gunakan `.gitignore` yang sudah disediakan**

## 🆘 Jika Terlanjur Upload File Sensitif

```bash
# Remove file from git tracking
git rm --cached path/to/sensitive/file

# Add to gitignore
echo "path/to/sensitive/file" >> .gitignore

# Commit the removal
git commit -m "Remove sensitive file and add to gitignore"

# Push changes
git push
```

## ✅ Final Checklist

Sebelum mengumumkan repository sebagai public:

- [ ] File sensitif sudah masuk `.gitignore`
- [ ] Test clone berhasil tanpa file pribadi
- [ ] README.md informatif dan profesional
- [ ] Tidak ada hardcoded personal data dalam source code
- [ ] Repository description dan tags sudah diset
- [ ] Project bisa di-build setelah clone (dengan setup Firebase sendiri)

---

**💡 Tips**: Setelah upload, repository ini bisa dijadikan portfolio untuk menunjukkan kemampuan Android development dengan Firebase!
