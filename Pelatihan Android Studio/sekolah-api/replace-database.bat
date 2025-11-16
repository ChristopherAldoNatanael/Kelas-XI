@echo off
echo ================================================
echo     DATABASE STRUCTURE REPLACEMENT TOOL
echo ================================================
echo.

echo Checking PHP installation...
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: PHP is not installed or not in PATH
    echo Please install PHP and add it to PATH
    pause
    exit /b 1
)

echo ✓ PHP found
echo.

echo Checking project files...
if not exist "vendor\autoload.php" (
    echo ERROR: vendor\autoload.php not found
    echo Please run: composer install
    pause
    exit /b 1
)

if not exist "database_schema_new.sql" (
    echo ERROR: database_schema_new.sql not found
    echo Please ensure the database schema file exists
    pause
    exit /b 1
)

if not exist ".env" (
    echo ERROR: .env file not found
    echo Please ensure .env file exists with database configuration
    pause
    exit /b 1
)

echo ✓ All required files found
echo.

echo IMPORTANT NOTES:
echo - This will replace your entire database structure
echo - A backup will be created automatically
echo - Please ensure MySQL is running before starting
echo - Type 'yes' to confirm when prompted
echo.

pause
echo.

echo Starting database replacement process...
echo.

php replace-database-structure.php

echo.
echo Process completed. Press any key to exit...
pause
