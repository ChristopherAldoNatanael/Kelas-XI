@echo off
cd /d "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"
php artisan migrate --path=database/migrations/2025_11_17_110900_add_deleted_at_to_users_table.php
pause
