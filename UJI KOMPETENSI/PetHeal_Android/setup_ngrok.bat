@echo off
setlocal enabledelayedexpansion

echo ==========================================
echo PETHEAL NGROK SETUP SCRIPT
echo ==========================================
echo.

REM Check if ngrok is running
tasklist | findstr /i "ngrok.exe" >nul 2>&1
if errorlevel 1 (
    echo [ERROR] ngrok is not running!
    echo.
    echo Please start ngrok first:
    echo   ngrok http 8000
    echo.
    echo Then run this script again.
    echo.
    pause
    exit /b 1
)

echo [OK] ngrok is running
echo.

REM Try to get ngrok URL from API
echo Detecting ngrok URL...
powershell -Command "(Invoke-WebRequest -Uri 'http://localhost:4040/api/tunnels' -UseBasicParsing).Content" > temp_ngrok.json 2>nul

if not exist temp_ngrok.json (
    echo [WARNING] Could not detect ngrok URL automatically
    echo.
    echo Please enter your ngrok URL manually (e.g., https://abc123.ngrok-free.app):
    set /p NGROK_URL=
) else (
    REM Extract URL from JSON
    for /f "tokens=* USEBACKQ" %%F in (`powershell -Command "(Get-Content temp_ngrok.json | ConvertFrom-Json).tunnels[0].public_url"`) do (
        set NGROK_URL=%%F
    )
    del temp_ngrok.json >nul 2>&1
)

if "%NGROK_URL%"=="" (
    echo [ERROR] No ngrok URL provided!
    pause
    exit /b 1
)

echo.
echo Using ngrok URL: %NGROK_URL%
echo.

REM Update Android local.properties
echo [1/3] Updating Android configuration...
set ANDROID_CONFIG=C:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Android\local.properties

if exist %ANDROID_CONFIG% (
    powershell -Command "(Get-Content '%ANDROID_CONFIG%') -replace 'BACKEND_BASE_URL=.*', 'BACKEND_BASE_URL=%NGROK_URL%/api/' | Set-Content '%ANDROID_CONFIG%'"
    echo [OK] Updated local.properties
) else (
    echo [WARNING] local.properties not found, creating...
    echo BACKEND_BASE_URL=%NGROK_URL%/api/ > %ANDROID_CONFIG%
    echo [OK] Created local.properties
)

echo.

REM Update Backend .env
echo [2/3] Updating Backend configuration...
set BACKEND_CONFIG=C:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Backend\.env

if exist %BACKEND_CONFIG% (
    powershell -Command "(Get-Content '%BACKEND_CONFIG%') -replace 'APP_URL=.*', 'APP_URL=%NGROK_URL%' | Set-Content '%BACKEND_CONFIG%'"
    echo [OK] Updated .env
) else (
    echo [ERROR] .env not found in PetHeal_Backend!
    echo Please copy .env.example to .env first
    pause
    exit /b 1
)

echo.

REM Clear Laravel cache
echo [3/3] Clearing Laravel cache...
cd /d C:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Backend
call php artisan config:clear >nul 2>&1
call php artisan cache:clear >nul 2>&1
echo [OK] Cache cleared

echo.
echo ==========================================
echo SETUP COMPLETE!
echo ==========================================
echo.
echo Configuration Summary:
echo   Backend APP_URL: %NGROK_URL%
echo   Android BASE_URL: %NGROK_URL%/api/
echo.
echo Next Steps:
echo   1. Rebuild Android app: gradlew.bat assembleDebug
echo   2. Run the app on emulator/device
echo   3. Test API connectivity
echo.
echo IMPORTANT: Keep ngrok running during development!
echo.
pause
