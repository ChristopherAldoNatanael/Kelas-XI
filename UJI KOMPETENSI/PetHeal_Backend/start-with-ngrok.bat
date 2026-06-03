@echo off
echo ==========================================
echo  STARTING PETHEAL WITH NGROK (HTTPS)
echo ==========================================
echo.
echo This will:
echo 1. Start Laravel server on port 8000
echo 2. Start ngrok tunnel with HTTPS
echo.
echo URLs available:
echo - Local:   http://127.0.0.1:8000
echo - HTTPS:   https://YOUR-NGROK-URL.ngrok-free.dev
echo.
echo Admin Panel (HTTPS): https://YOUR-NGROK-URL.ngrok-free.dev/admin/login
echo.
echo Press Ctrl+C in each window to stop
echo ==========================================
echo.
echo [1/2] Starting Laravel server...
echo.

:: Start Laravel in background
start "PetHeal Laravel" cmd /k "cd /d %~dp0 && php artisan serve --host=127.0.0.1 --port=8000"

timeout /t 3 /nobreak >nul

echo [2/2] Starting ngrok tunnel...
echo.
echo NOTE: If ngrok is not installed, download from:
echo https://ngrok.com/download
echo.

:: Check if ngrok exists
where ngrok >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] ngrok not found in PATH!
    echo.
    echo Please:
    echo 1. Download ngrok from https://ngrok.com/download
    echo 2. Extract to a folder (e.g., C:\ngrok)
    echo 3. Add to PATH or run from that folder
    echo.
    pause
    exit /b 1
)

:: Start ngrok
start "Ngrok Tunnel" cmd /k "ngrok http 8000"

echo.
echo ==========================================
echo  SERVERS STARTED!
echo ==========================================
echo.
echo Check the ngrok window for your HTTPS URL
echo Then visit: https://YOUR-URL.ngrok-free.dev/admin/login
echo.
echo Login credentials:
echo   Email:    admin@petheal.com
echo   Password: admin123
echo.
pause
