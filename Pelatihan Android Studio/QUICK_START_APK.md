# 🚀 QUICK START: Install & Test APK Siswa

## 📱 Cara Install APK ke HP Android

### 1. Build APK

```powershell
cd "c:\Kelas XI RPL\Pelatihan Android Studio\AplikasiMonitoringKelas"
.\gradlew assembleDebug
```

**Output APK:**

```
app\build\outputs\apk\debug\app-debug.apk
```

### 2. Transfer ke HP

**Pilihan A: USB Cable**

1. Sambungkan HP ke PC via USB
2. Copy file `app-debug.apk` ke folder Download HP
3. Buka File Manager di HP
4. Tap `app-debug.apk` → Install

**Pilihan B: Share via WhatsApp/Telegram**

1. Kirim file `app-debug.apk` ke diri sendiri
2. Download di HP
3. Tap file → Install

**Pilihan C: Google Drive/Dropbox**

1. Upload `app-debug.apk` ke cloud
2. Download di HP
3. Install

### 3. Enable Unknown Sources (jika diminta)

```
Settings → Security → Unknown Sources → Enable
```

---

## 🧪 Cara Test Aplikasi

### A. Test Role Siswa

#### 1. Login sebagai Siswa

```
Email: siswa.test@example.com
Password: password123
```

#### 2. Halaman Jadwal (Tab Pertama)

- ✅ Muncul nama siswa di top: "Halo, [Nama Siswa]"
- ✅ Ada tombol Refresh (kanan atas)
- ✅ Ada tombol Logout (kanan atas)
- ✅ Jadwal muncul **sesuai kelas siswa** (contoh: XI RPL)
- ✅ Jadwal di-group by hari (Senin, Selasa, dst)
- ✅ Setiap jadwal tampil:
  - Jam ke-X: Nama Mata Pelajaran
  - Guru: Nama Guru
  - Ruang: Nama Ruangan
  - Waktu: 07:00 - 08:00

**Test Refresh:**

- Tap tombol Refresh → Jadwal reload dari server

**Test Logout:**

- Tap tombol Logout → Kembali ke halaman Login

#### 3. Halaman Entri (Tab Kedua)

- ❌ Menampilkan pesan "Akses Dibatasi"
- Info: Siswa tidak bisa tambah jadwal (hanya lihat)

#### 4. Halaman List (Tab Ketiga)

- Menampilkan list user/data lain (sesuai kebutuhan)

---

### B. Test Error Handling

#### Test 1: Siswa Belum Punya Kelas

1. Login dengan user siswa yang **belum di-assign ke kelas**
2. **Expected:** Muncul error message:
   ```
   "Anda belum di-assign ke kelas. Hubungi admin."
   ```

#### Test 2: Server Offline

1. Matikan Laravel server
2. Login atau refresh jadwal
3. **Expected:** Muncul error connection

#### Test 3: Token Expired

1. Login
2. Tunggu beberapa jam (token expire)
3. Refresh jadwal
4. **Expected:** Diminta login ulang

---

## 🔧 Troubleshooting

### Problem: APK tidak bisa install

**Solusi:**

1. Enable Unknown Sources di Settings
2. Hapus versi lama aplikasi (jika ada)
3. Pastikan storage cukup

### Problem: Jadwal tidak muncul

**Check:**

1. Apakah siswa sudah punya `class_id` di database?
2. Apakah server Laravel running?
3. Apakah koneksi internet aktif?
4. Check logs: `adb logcat | grep "SiswaActivity"`

**Cara Assign Class ke Siswa:**

```sql
-- Di database sekolah_db
UPDATE users
SET class_id = 22  -- ID kelas XI RPL
WHERE email = 'siswa.test@example.com';
```

### Problem: Server crash saat load jadwal

**Solusi:**

- ✅ Sudah diperbaiki! Sekarang hanya load jadwal 1 kelas (~40-50 record)
- Jika masih crash, cek server resources (RAM, CPU)

---

## 📊 Monitoring Performance

### Android Studio Logcat

```bash
# Filter logs untuk SiswaActivity
adb logcat | grep "SiswaActivity"
```

**Expected Logs:**

```
Loading weekly schedule using optimized endpoint
✅ Loaded 45 schedules (class: XI RPL)
```

### Network Traffic (Chrome DevTools)

1. Enable USB debugging
2. Connect HP ke PC
3. Chrome → `chrome://inspect`
4. Inspect aplikasi
5. Network tab → Lihat API calls

**Expected:**

- API: `GET /api/schedules?class_id=22`
- Response size: < 50 KB (bukan MB!)
- Response time: < 500ms

---

## 🎯 Checklist Testing

### ✅ Functionality

- [ ] Login berhasil
- [ ] Nama siswa muncul di top
- [ ] Jadwal sesuai kelas siswa
- [ ] Jadwal di-group by hari
- [ ] Tombol Refresh berfungsi
- [ ] Tombol Logout berfungsi
- [ ] Error handling OK

### ✅ Performance

- [ ] Loading < 2 detik
- [ ] Scroll smooth (tidak lag)
- [ ] Server tidak crash
- [ ] Memory usage normal

### ✅ UI/UX

- [ ] Tampilan rapi
- [ ] Text readable
- [ ] Tombol mudah diklik
- [ ] Bottom navigation berfungsi

---

## 📞 Support

**Jika ada masalah:**

1. Check dokumentasi: `SOLUSI_JADWAL_SISWA_FIXED.md`
2. Check logs Android: `adb logcat`
3. Check logs Laravel: `storage/logs/laravel.log`
4. Test backend: `php test-siswa-weekly-schedule.php`

---

_Quick Start Guide - 4 November 2025_
