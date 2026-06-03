@echo off
echo PetHeal Development Server with Asset Caching
echo ==============================================
php -S 0.0.0.0:8000 -t public router.php
