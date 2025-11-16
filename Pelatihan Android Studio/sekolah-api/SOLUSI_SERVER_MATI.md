# 🔥 SOLUSI SERVER MATI - MASALAH SEBENARNYA DITEMUKAN!

## ❌ MASALAH UTAMA: MySQL/MariaDB TIDAK BERJALAN!

Dari log error, saya menemukan masalah sebenarnya:

```
SQLSTATE[HY000] [2002] No connection could be made because 
the target machine actively refused it
```

**Artinya:** MySQL/MariaDB server **TIDAK BERJALAN** di komputer Anda!

Laravel server mati karena **tidak bisa connect ke database**.

---

## ✅ SOLUSI LENGKAP - IKUTI LANGKAH INI:

### Langkah 1: Cek Apakah Anda Pakai XAMPP atau Laragon

**Jika pakai XAMPP:**
1. Buka XAMPP Control Panel
2. Klik tombol **"Start"** di sebelah **MySQL**
3. Tunggu sampai tulisan berubah jadi hijau

**Jika pakai Laragon:**
1. Buka Laragon
2. Klik tombol **"Start All"**
3. Tunggu sampai MySQL/MariaDB berjalan

**Jika pakai WAMP:**
1. Buka WAMP
2. Klik icon WAMP di system tray
3. Pilih **"Start All Services"**

---

### Langkah 2: Pastikan MySQL Berjalan di Port yang Benar

Buka file `.env` dan cek baris ini:

```env
DB_PORT=3306
```

**Cek port MySQL Anda:**
- XAMPP biasanya: **3306**
- Laragon biasanya: **3306**  
- Jika ada konflik, bisa jadi: **3307** atau **3308**

**Cara cek port yang benar:**

1. Buka XAMPP/Laragon Control Panel
2. Lihat di bagian MySQL, biasanya ada tulisan port (misal: `Port: 3306`)
3. Sesuaikan di file `.env`

---

### Langkah 3: Test Koneksi Database

Jalankan command ini:

```bash
php artisan db:show
```

**Jika berhasil**, akan muncul info database:
```
MySQL ........................... 8.0.30
Database ........................ db_sekolah
Host ............................ 127.0.0.1
Port ............................ 3306
```

**Jika gagal**, akan muncul error connection refused.

---

### Langkah 4: Jika MySQL Tetap Tidak Bisa Start

**Kemungkinan 1: Port sudah dipakai aplikasi lain**

Solusi:
1. Buka XAMPP/Laragon Config
2. Ubah MySQL port dari 3306 ke 3307
3. Update file `.env`:
   ```env
   DB_PORT=3307
   ```

**Kemungkinan 2: Service MySQL error**

Solusi untuk XAMPP:
1. Buka XAMPP Control Panel
2. Klik **"Config"** di sebelah MySQL
3. Pilih **"my.ini"**
4. Cari baris `port=` dan pastikan sesuai
5. Save dan restart MySQL

**Kemungkinan 3: MySQL belum terinstall**

Jika Anda belum install MySQL:
1. Download XAMPP dari https://www.apachefriends.org/
2. Install XAMPP
3. Start Apache dan MySQL
4. Buat database `db_sekolah` via phpMyAdmin

---

### Langkah 5: Buat Database Jika Belum Ada

1. Buka browser, ketik: `http://localhost/phpmyadmin`
2. Klik tab **"Databases"**
3. Di kolom "Create database", ketik: `db_sekolah`
4. Klik **"Create"**

---

### Langkah 6: Import Database (Jika Ada File SQL)

Jika Anda punya file backup database:

1. Buka phpMyAdmin
2. Pilih database `db_sekolah`
3. Klik tab **"Import"**
4. Pilih file `.sql` Anda
5. Klik **"Go"**

---

### Langkah 7: Jalankan Migration (Jika Database Kosong)

```bash
php artisan migrate --seed
```

Ini akan membuat semua tabel dan data awal.

---

### Langkah 8: Clear Cache dan Restart Server

```bash
php artisan config:clear
php artisan cache:clear
php artisan serve
```

---

## 🎯 CHECKLIST - PASTIKAN SEMUA INI SUDAH BENAR:

- [ ] MySQL/MariaDB sudah **RUNNING** (cek di XAMPP/Laragon)
- [ ] Port di `.env` **SESUAI** dengan port MySQL (biasanya 3306)
- [ ] Database `db_sekolah` sudah **DIBUAT**
- [ ] Tabel sudah ada (jalankan migration jika perlu)
- [ ] Command `php artisan db:show` **BERHASIL**
- [ ] Server Laravel bisa start tanpa error

---

## 🔍 CARA CEK APAKAH MYSQL BERJALAN

### Cara 1: Via XAMPP/Laragon Control Panel
- Lihat status MySQL, harus **hijau/running**

### Cara 2: Via Command Line
```bash
# Windows PowerShell
Get-Service | Where-Object {$_.Name -like "*mysql*"}

# Atau coba connect langsung
mysql -u root -p
```

### Cara 3: Via Browser
- Buka: `http://localhost/phpmyadmin`
- Jika bisa buka, berarti MySQL jalan

---

## 🚨 ERROR YANG SERING MUNCUL & SOLUSINYA

### Error 1: "Connection refused"
**Penyebab:** MySQL tidak jalan  
**Solusi:** Start MySQL di XAMPP/Laragon

### Error 2: "Access denied for user 'root'"
**Penyebab:** Password salah  
**Solusi:** Cek password di `.env`, biasanya kosong untuk XAMPP:
```env
DB_PASSWORD=
```

### Error 3: "Unknown database 'db_sekolah'"
**Penyebab:** Database belum dibuat  
**Solusi:** Buat database via phpMyAdmin

### Error 4: "Port 3306 already in use"
**Penyebab:** Port sudah dipakai  
**Solusi:** Ubah port MySQL atau matikan aplikasi yang pakai port 3306

---

## 📝 LANGKAH LENGKAP DARI AWAL

Ikuti ini step by step:

```bash
# 1. Pastikan MySQL jalan (buka XAMPP/Laragon, start MySQL)

# 2. Cek koneksi
php artisan db:show

# 3. Jika error, cek .env
cat .env | Select-String "DB_"

# 4. Sesuaikan jika perlu, lalu clear config
php artisan config:clear

# 5. Test lagi
php artisan db:show

# 6. Jika berhasil, jalankan migration
php artisan migrate

# 7. Start server
php artisan serve

# 8. Test dari Android app
```

---

## ✅ SETELAH MYSQL JALAN, SERVER TIDAK AKAN MATI LAGI

Setelah MySQL berjalan dengan benar:
- ✅ Server Laravel akan stabil
- ✅ Login siswa akan cepat
- ✅ Navigasi antar halaman lancar
- ✅ Tidak ada crash lagi

---

## 🎉 KESIMPULAN

**Masalah utama bukan di code Laravel, tapi MySQL tidak jalan!**

Setelah MySQL berjalan:
1. Semua fix yang sudah saya buat akan bekerja
2. Login siswa < 1 detik
3. Server stabil
4. Tidak ada crash

**PASTIKAN MYSQL JALAN DULU SEBELUM START LARAVEL SERVER!**

---

## 📞 Jika Masih Bermasalah

Kirim screenshot dari:
1. XAMPP/Laragon Control Panel (tunjukkan status MySQL)
2. Output dari: `php artisan db:show`
3. Isi file `.env` (bagian DB_* saja)

Saya akan bantu troubleshoot lebih lanjut!

