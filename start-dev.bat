@echo off
echo ============================================
echo   YoPrint CSV Upload System - Dev Startup
echo ============================================
echo.

echo [1/3] Starting Laravel Application Server...
start "Laravel Server" cmd /k "php artisan serve"

echo [2/3] Starting Queue Worker...
start "Queue Worker" cmd /k "php artisan queue:work --verbose"

echo [3/3] Starting Reverb WebSocket Server...
start "Reverb WebSocket" cmd /k "php artisan reverb:start --verbose"

echo.
echo All services started!
echo.
echo   App:       http://localhost:8000
echo   WebSocket: ws://localhost:8080
echo.
echo Press any key to exit...
pause > nul
