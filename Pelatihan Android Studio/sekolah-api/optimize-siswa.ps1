# OPTIMISASI SERVER LARAVEL UNTUK ROLE SISWA - Windows PowerShell
Write-Host "🚀 MULAI OPTIMISASI SERVER LARAVEL UNTUK ROLE SISWA" -ForegroundColor Green
Write-Host "=================================================" -ForegroundColor Green

# 1. Jalankan migrasi index baru
Write-Host "1️⃣ Menjalankan migrasi database indexes..." -ForegroundColor Yellow
php artisan migrate --force

# 2. Clear semua cache
Write-Host "2️⃣ Membersihkan semua cache..." -ForegroundColor Yellow
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. Optimize aplikasi
Write-Host "3️⃣ Mengoptimasi aplikasi..." -ForegroundColor Yellow
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Restart queue workers (jika ada)
Write-Host "4️⃣ Restart queue workers..." -ForegroundColor Yellow
php artisan queue:restart

Write-Host ""
Write-Host "✅ OPTIMISASI SELESAI!" -ForegroundColor Green
Write-Host "======================================" -ForegroundColor Green
Write-Host "Endpoint baru yang tersedia:" -ForegroundColor Cyan
Write-Host "- GET /api/siswa/jadwal-hari-ini (Ultra lightweight)" -ForegroundColor White
Write-Host "- GET /api/siswa/riwayat-kehadiran?page=1&limit=10 (Paginated)" -ForegroundColor White
Write-Host "- GET /api/siswa/my-schedule (Optimized dengan timeout protection)" -ForegroundColor White
Write-Host ""
Write-Host "🔥 SERVER SEKARANG SUDAH DIOPTIMASI UNTUK ROLE SISWA!" -ForegroundColor Green
Write-Host "Timeout protection: 5-10 detik per request" -ForegroundColor Yellow
Write-Host "Cache duration: 60-300 detik" -ForegroundColor Yellow
Write-Host "Max items per page: 10-20" -ForegroundColor Yellow
Write-Host ""
Write-Host "📊 Monitor performa dengan:" -ForegroundColor Cyan
Write-Host "Get-Content storage/logs/laravel.log -Wait -Tail 50" -ForegroundColor White
