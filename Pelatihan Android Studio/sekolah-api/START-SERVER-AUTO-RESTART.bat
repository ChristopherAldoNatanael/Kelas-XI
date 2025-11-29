@echo off
echo ========================================
echo   LARAVEL SERVER MONITOR
echo   Auto-restart jika crash
echo ========================================
echo.

cd /d "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"

:START
echo [%DATE% %TIME%] Starting Laravel server...
php artisan serve --host=0.0.0.0 --port=8000

echo.
echo [%DATE% %TIME%] Server stopped! Restarting in 3 seconds...
timeout /t 3 /nobreak >nul
goto START
