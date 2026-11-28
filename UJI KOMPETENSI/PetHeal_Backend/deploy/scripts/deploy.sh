#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/petheal/current}"
PHP_BIN="${PHP_BIN:-php}"
cd "$APP_DIR"

echo "==> Maintenance mode"
$PHP_BIN artisan down --retry=60 || true

cleanup() {
    echo "==> Keluar dari maintenance mode"
    $PHP_BIN artisan up || true
}
trap cleanup EXIT

echo "==> Dependency PHP"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "==> Build frontend"
npm ci
npm run build

echo "==> Migration"
$PHP_BIN artisan migrate --force

echo "==> Cache produksi"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache

echo "==> Thumbnail gambar"
$PHP_BIN artisan media:thumbnails --width=96,192,320

echo "==> Restart worker"
$PHP_BIN artisan queue:restart || true

echo "==> Selesai"
