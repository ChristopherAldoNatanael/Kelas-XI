@echo off
echo Starting AdminWafeOfFood Debug Session...
echo ==================================================

echo 1. Building and Installing App...
call gradlew clean assembleDebug installDebug

if %errorlevel% neq 0 (
    echo Build failed!
    pause
    exit /b 1
)

echo 2. App installed successfully!
echo 3. Please manually open the app and navigate to Menu tab
echo 4. Watch the console output in Android Studio for crash logs
echo 5. Or use: .\gradlew :app:connectedCheck to run tests
echo.
echo ==================================================
echo Debug Tips:
echo - Check Android Studio Logcat for detailed crash logs
echo - Look for tags: MenuFragment, MenuViewModel, MainActivity
echo - Filter by package: com.christopheraldoo.adminwafeoffood
echo ==================================================

pause
