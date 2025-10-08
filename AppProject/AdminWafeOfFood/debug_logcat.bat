@echo off
echo Monitoring logcat for AdminWafeOfFood crashes...
echo Press Ctrl+C to stop monitoring
echo.

REM Get Android SDK path from gradlew
for /f "tokens=*" %%i in ('.\gradlew properties -q ^| findstr "android.sdkDirectory"') do set SDK_LINE=%%i
for /f "tokens=2 delims=:" %%j in ("%SDK_LINE%") do set ANDROID_SDK=%%j

REM Remove leading space and set path
set ANDROID_SDK=%ANDROID_SDK:~1%
set ADB_PATH=%ANDROID_SDK%\platform-tools\adb.exe

echo Using ADB from: %ADB_PATH%
echo.

REM Clear logcat and monitor for crashes
"%ADB_PATH%" logcat -c
"%ADB_PATH%" logcat -s AndroidRuntime,FATAL,MenuFragment,MainActivity,MenuViewModel,MenuRepository,Firebase:* *:E

pause
