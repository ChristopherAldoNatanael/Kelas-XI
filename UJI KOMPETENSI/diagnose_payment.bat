@echo off
REM ============================================
REM Midtrans Payment Integration Diagnostic Tool
REM ============================================

echo.
echo ============================================
echo  Midtrans Payment Integration Diagnostic
echo ============================================
echo.

REM Check if backend exists
if not exist "C:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Backend" (
    echo [ERROR] PetHeal_Backend directory not found!
    pause
    exit /b 1
)

echo [1/6] Checking Laravel backend routes...
cd "C:\Kelas XI RPL\UJI KOMPETENSI\PetHeal_Backend"
php artisan route:list --path=api/payment --no-interaction 2>&1 | findstr "payment/snap-token"
if errorlevel 1 (
    echo [FAIL] Payment route NOT found!
    echo.
    echo Attempting to fix...
    php artisan route:clear
    php artisan config:clear
    php artisan cache:clear
    echo.
    echo Please restart your Laravel server:
    echo   php artisan serve --port=8000
) else (
    echo [OK] Payment route exists
)

echo.
echo [2/6] Testing local payment endpoint...
curl -s -o nul -w "HTTP Status: %%{http_code}" -X POST "http://localhost:8000/api/payment/snap-token" ^
  -H "Content-Type: application/json" ^
  -d "{\"transaction_details\":{\"order_id\":\"test-001\",\"gross_amount\":100000}}"
echo.

echo.
echo [3/6] Testing ngrok payment endpoint...
curl -s -o nul -w "HTTP Status: %%{http_code}" -X POST "https://radia-unswaggering-lisandra.ngrok-free.dev/api/payment/snap-token" ^
  -H "Content-Type: application/json" ^
  -H "Authorization: Bearer test" ^
  -d "{\"transaction_details\":{\"order_id\":\"test-001\",\"gross_amount\":100000}}"
echo.
echo.
echo Note: 401 = endpoint exists (auth required), 404 = endpoint missing
echo.

echo [4/6] Checking Midtrans configuration in PaymentController...
findstr "SERVER_KEY" "app\Http\Controllers\Api\PaymentController.php"
echo.

echo [5/6] Checking Android BASE_URL...
findstr "BASE_URL" "..\PetHeal_Android\app\src\main\java\com\christopheraldoo\petheal\di\AppModule.kt"
echo.

echo [6/6] Checking Android Midtrans configuration...
findstr "MIDTRANS_CLIENT_KEY" "..\PetHeal_Android\app\build.gradle.kts"
echo.

echo.
echo ============================================
echo  Diagnostic Complete
echo ============================================
echo.
echo Next Steps:
echo 1. If routes missing: Run 'php artisan route:clear' and restart server
echo 2. If ngrok returns 404: Check if ngrok is running and URL matches
echo 3. If local returns 000: Start Laravel server (php artisan serve)
echo 4. If all OK: Check Android app logs for authentication issues
echo.
pause
