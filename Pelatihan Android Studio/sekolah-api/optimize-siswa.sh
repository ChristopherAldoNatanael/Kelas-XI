#!/bin/bash

echo "🚀 MULAI OPTIMISASI SERVER LARAVEL UNTUK ROLE SISWA"
echo "================================================="

# 1. Jalankan migrasi index baru
echo "1️⃣ Menjalankan migrasi database indexes..."
php artisan migrate --force

# 2. Clear semua cache
echo "2️⃣ Membersihkan semua cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. Optimize aplikasi
echo "3️⃣ Mengoptimasi aplikasi..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Restart queue workers (jika ada)
echo "4️⃣ Restart queue workers..."
php artisan queue:restart

echo ""
echo "✅ OPTIMISASI SELESAI!"
echo "======================================"
echo "Endpoint baru yang tersedia:"
echo "- GET /api/siswa/jadwal-hari-ini (Ultra lightweight)"
echo "- GET /api/siswa/riwayat-kehadiran?page=1&limit=10 (Paginated)"
echo "- GET /api/siswa/my-schedule (Optimized dengan timeout protection)"
echo ""
echo "🔥 SERVER SEKARANG SUDAH DIOPTIMASI UNTUK ROLE SISWA!"
echo "Timeout protection: 5-10 detik per request"
echo "Cache duration: 60-300 detik"
echo "Max items per page: 10-20"
echo ""
echo "📊 Monitor performa dengan:"
echo "tail -f storage/logs/laravel.log"
