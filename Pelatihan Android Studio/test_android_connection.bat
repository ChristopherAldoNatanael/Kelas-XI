@echo off
echo ========================================
echo   TESTING ANDROID CONNECTION TO LARAVEL
echo ========================================
echo.
echo This script tests if your Android app can connect to Laravel backend
echo.
echo Current configuration:
echo - Laravel Server: http://127.0.0.1:8000 (localhost)
echo - Android connects to: http://192.168.1.10:8000 (host IP)
echo.
echo Testing server availability...
echo.

curl -X GET "http://127.0.0.1:8000/api/test" -H "Content-Type: application/json"
if %errorlevel% neq 0 (
    echo.
    echo ❌ SERVER NOT RESPONDING!
    echo Make sure Laravel server is running with:
    echo cd sekolah-api ^&^& php artisan serve --host=0.0.0.0 --port=8000
    echo.
    pause
    exit /b 1
)

echo.
echo ✅ SERVER IS RUNNING AND RESPONDING
echo.
echo Now test from Android emulator:
echo 1. Start Android emulator
echo 2. Run the app
echo 3. Try to login
echo 4. Check Android Studio Logcat for connection logs
echo.
echo Expected logs:
echo - "🔗 Base URL: http://192.168.1.10:8000/api/"
echo - "🌐 Server should be accessible at: http://192.168.1.10:8000/api/test"
echo.
echo If still failing, try:
echo 1. Restart Android emulator (Cold Boot)
echo 2. Check Windows Firewall allows port 8000
echo 3. Verify Android device is on same network as host
echo.
pause