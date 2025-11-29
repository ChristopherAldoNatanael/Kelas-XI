@echo off
cls
echo ========================================
echo   MONITORING KELAS - SERVER LAUNCHER
echo   Laravel Development Server
echo ========================================
echo.
echo [INFO] Starting Laravel server...
echo [INFO] Listening on: 0.0.0.0:8000
echo [INFO] Access from computer: http://127.0.0.1:8000
echo [INFO] Access from Android: http://192.168.30.113:8000
echo [INFO] Port: 8000
echo.
echo ========================================
echo   INSTRUCTIONS:
echo   1. DO NOT CLOSE THIS WINDOW
echo   2. Server will run until you press Ctrl+C
echo   3. Access: http://192.168.30.113:8000
echo ========================================
echo.

cd /d "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"

echo [OK] Server starting...
echo.
php artisan serve --host=0.0.0.0 --port=8000

echo.
echo ========================================
echo   SERVER STOPPED
echo ========================================
pause
