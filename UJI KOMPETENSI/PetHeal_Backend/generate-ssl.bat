@echo off
echo ==========================================
echo  GENERATING SELF-SIGNED SSL CERTIFICATE
echo ==========================================
echo.

:: Check if OpenSSL is available
where openssl >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] OpenSSL not found!
    echo Please install OpenSSL from: https://slproweb.com/products/Win32OpenSSL.html
    echo Or use XAMPP's OpenSSL (if installed)
    echo.
    pause
    exit /b 1
)

:: Create ssl directory
if not exist ssl mkdir ssl

:: Generate private key
echo [1/3] Generating private key...
openssl genrsa -out ssl/server.key 2048 >nul 2>&1

:: Generate CSR (Certificate Signing Request)
echo [2/3] Generating certificate signing request...
openssl req -new -key ssl/server.key -out ssl/server.csr -subj "/C=ID/ST=Jakarta/L=Jakarta/O=PetHeal/CN=localhost" >nul 2>&1

:: Generate self-signed certificate (valid for 365 days)
echo [3/3] Generating self-signed certificate...
openssl x509 -req -days 365 -in ssl/server.csr -signkey ssl/server.key -out ssl/server.crt >nul 2>&1

echo.
echo ==========================================
echo  SSL CERTIFICATE GENERATED!
echo ==========================================
echo.
echo Files created:
echo   - ssl/server.key  (Private key)
echo   - ssl/server.crt  (Certificate)
echo.
echo Valid for: 365 days
echo Common Name: localhost
echo.
echo Next step: Run "start-https.bat" to start Laravel with HTTPS
echo.
pause
