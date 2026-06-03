@echo off
echo ==========================================
echo  LARAVEL PERFORMANCE OPTIMIZATION
echo ==========================================
echo.
echo [1/6] Clearing old caches...
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo.
echo [2/6] Caching Configuration...
php artisan config:cache

echo.
echo [3/6] Caching Routes...
php artisan route:cache

echo.
echo [4/6] Caching Views...
php artisan view:cache

echo.
echo [5/6] Optimizing Class Loader...
php artisan optimize

echo.
echo [6/6] Clearing compiled files...
php artisan clear-compiled

echo.
echo ==========================================
echo  ✅ OPTIMIZATION COMPLETE!
echo ==========================================
echo.
echo Your Laravel app is now optimized for:
echo   - Faster config loading (cached)
echo   - Faster route matching (cached)
echo   - Faster view rendering (cached)
echo   - Optimized class autoloading
echo.
echo Response time should be 30-50%% faster!
echo.
pause
