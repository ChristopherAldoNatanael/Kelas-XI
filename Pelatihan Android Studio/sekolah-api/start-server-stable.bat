@echo off
title Laravel Server - Auto Restart
color 0A

echo ============================================
echo    LARAVEL SERVER - AUTO RESTART MODE
echo ============================================
echo.
echo Server akan restart otomatis jika mati
echo Port: 8000
echo Host: 0.0.0.0 (accessible dari emulator)
echo.
echo Tekan Ctrl+C untuk STOP
echo ============================================
echo.

REM Pindah ke folder project Laravel
cd /d "c:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api"

:loop
echo [%date% %time%] Starting server with XAMPP PHP (Already working!)...
C:\xampp\php\php.exe artisan serve --host=0.0.0.0 --port=8000

echo.
echo [%date% %time%] Server stopped!
echo Restarting in 3 seconds...
timeout /t 3 /nobreak >nul
echo.
goto loop
