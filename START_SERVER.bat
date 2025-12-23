@echo off
echo ========================================
echo    COVRE BACKEND SERVER LAUNCHER
echo ========================================
echo.

REM Check if we're in the backend folder
if not exist "artisan" (
    echo ERROR: artisan file not found!
    echo Please run this script from the backend folder.
    pause
    exit /b 1
)

echo [1/3] Checking Laravel installation...
if not exist "vendor" (
    echo Installing dependencies...
    call composer install
    if errorlevel 1 (
        echo ERROR: Composer install failed!
        pause
        exit /b 1
    )
)

echo [2/3] Checking .env file...
if not exist ".env" (
    echo Creating .env file from .env.example...
    copy .env.example .env
    echo.
    echo Please configure your .env file and run this script again.
    pause
    exit /b 1
)

echo [3/3] Starting Laravel server...
echo.
echo ========================================
echo SERVER INFORMATION:
echo ========================================

REM Get local IP address
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do (
    set IP=%%a
    goto :found
)
:found
echo Local IP Address:%IP%
echo.
echo Backend will be accessible at:
echo   - Localhost: http://127.0.0.1:8000
echo   - Network:   http:%IP%:8000
echo   - API:       http:%IP%:8000/api
echo.
echo ========================================
echo.
echo IMPORTANT: Update mobile/src/config/api.config.js
echo Change IP to: http:%IP%:8000/api
echo.
echo Press Ctrl+C to stop the server
echo ========================================
echo.

REM Start server accessible from network
php artisan serve --host=0.0.0.0 --port=8000

pause
