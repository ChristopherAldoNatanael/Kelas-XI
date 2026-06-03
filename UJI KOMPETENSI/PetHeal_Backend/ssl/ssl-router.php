<?php
/**
 * SSL Router for PHP built-in server
 * Note: PHP built-in server doesn't support HTTPS natively.
 * This router works with HTTP, but we'll use a proxy approach.
 * 
 * For actual HTTPS, we recommend using:
 * 1. ngrok (easiest) - already working
 * 2. Laravel Valet (Mac/Linux)
 * 3. Docker with SSL
 * 4. XAMPP/WAMP with SSL configured
 */

// Forward all requests to Laravel's public/index.php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

require_once __DIR__ . '/index.php';
