<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/public' . $path;

if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    $cacheExts = ['png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'webp', 'css', 'js', 'woff2', 'woff', 'ttf', 'eot'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, $cacheExts)) {
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    }
    return false;
}

require __DIR__ . '/public/index.php';
