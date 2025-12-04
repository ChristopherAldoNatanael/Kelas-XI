# PETUNJUK PENGGUNAAN APLIKASI BUKURINGKASAPP

## 📱 TENTANG APLIKASI

BukuRingkasApp adalah aplikasi Android untuk membantu siswa:
- Meringkas teks buku pelajaran dengan AI
- Mengajukan pertanyaan tentang materi pelajaran
- Mendapatkan penjelasan konsep dengan bahasa yang mudah dipahami
- Menyimpan dan mengelola ringkasan materi

## ✅ STATUS APLIKASI: ONLINE-ONLY

Aplikasi ini menggunakan **OpenAI API** dan **memerlukan koneksi internet** untuk semua fitur AI.

## 🔑 PETUNJUK KONFIGURASI API KEY

Sebelum menggunakan aplikasi, Anda perlu mengatur API key OpenAI:

1. Buka aplikasi dan navigasikan ke tab "Pengaturan"
2. Masukkan API key OpenAI Anda di kolom yang tersedia
3. Tekan tombol "Simpan" untuk menyimpan API key

### Cara Mendapatkan API Key OpenAI:
1. Kunjungi [platform.openai.com](https://platform.openai.com/)
2. Buat akun atau login ke akun yang sudah ada
3. Navigasikan ke menu API Keys
4. Klik "Create new secret key"
5. Salin key yang dihasilkan dan masukkan ke aplikasi

## 🔧 PETUNJUK PENGUJIAN

### 1. Test OpenAI API

Sebelum menjalankan aplikasi, pastikan OpenAI API berfungsi dengan baik:

1. Jalankan file `test_openai.bat` di folder project
2. Periksa hasilnya - semua test harus SUCCESS
3. Jika gagal, periksa koneksi internet dan status API key

### 2. Build dan Jalankan Aplikasi

Untuk menjalankan aplikasi:

1. Buka project dengan Android Studio
2. Pastikan Gradle sync berjalan lancar
3. Build dan jalankan aplikasi di emulator atau perangkat fisik
4. Jika terjadi error, periksa Logcat untuk detail

### 3. Test Fitur Utama

#### a) Fitur Ringkasan

1. Buka tab "Scan"
2. Ambil foto halaman buku atau ketik teks
3. Tekan "Ringkas" untuk mendapatkan ringkasan
4. Ringkasan akan muncul setelah proses selesai

#### b) Fitur Tanya AI

1. Buka tab "Tanya AI"
2. Ketik pertanyaan tentang materi pelajaran
3. Tekan "Tanya" untuk mendapatkan jawaban
4. Jawaban akan muncul setelah proses selesai

#### c) Fitur Penjelasan

1. Buka ringkasan atau jawaban yang ada
2. Tekan tombol "Jelaskan" pada konsep yang ingin dipahami
3. Penjelasan akan muncul setelah proses selesai

## ⚠️ TROUBLESHOOTING

### Jika Aplikasi Error:

1. **"APLIKASI MEMERLUKAN KONEKSI INTERNET!"**
   - Pastikan perangkat terhubung ke internet yang stabil
   - Periksa apakah OpenAI API tidak sedang down

2. **"API tidak mengembalikan hasil" atau "API Key belum dikonfigurasi"**
   - Pastikan OpenAI API key sudah dimasukkan di menu Pengaturan
   - Pastikan OpenAI API key masih valid
   - Jalankan `test_openai.bat` untuk verifikasi

3. **"Token tidak valid"**
   - Periksa kembali API key Anda di menu Pengaturan
   - Pastikan tidak ada spasi atau karakter tambahan saat memasukkan API key

4. **Aplikasi crash**
   - Periksa Logcat di Android Studio untuk detail error
   - Pastikan semua dependensi terpenuhi

## 📝 CATATAN PENGEMBANG

- Aplikasi dirancang untuk mode online-only sesuai permintaan
- Tidak ada fallback offline - aplikasi HARUS online
- Menggunakan model OpenAI text-davinci-003 untuk semua fitur AI
- Error handling sudah ditingkatkan dengan pesan yang lebih jelas

## 📱 SISTEM YANG DIDUKUNG

- Android 8.0 (API 24) dan yang lebih baru
- RAM minimal 2GB
- Memerlukan koneksi internet stabil
