@echo off
echo ==========================================
echo  STARTING PETHEAL WITH HTTPS
echo ==========================================
echo.

:: Check if SSL certificate exists
if not exist ssl\server.crt (
    echo [!] SSL certificate not found!
    echo Running certificate generation...
    echo.
    call generate-ssl.bat
)

echo ==========================================
echo  HTTPS URLS:
echo ==========================================
echo.
echo  Admin Panel:  https://localhost:8443/admin/login
echo  API Base:     https://localhost:8443/api/
echo.
echo  Press Ctrl+C to stop the server
echo ==========================================
echo.

:: Start PHP server with HTTPS using built-in router
php -S localhost:8443 -t public ssl\ssl-router.php
