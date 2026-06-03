@echo off
echo Starting PetHeal Queue Worker...
php artisan queue:work --sleep=3 --tries=3 --timeout=90
pause
