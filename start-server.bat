@echo off
echo ========================================
echo   Starting Covre Backend Server
echo ========================================
echo.
echo Server will be accessible at:
echo   - http://localhost:8000
echo   - http://192.168.1.5:8000
echo.
echo Press Ctrl+C to stop the server
echo ========================================
echo.

php artisan serve --host=0.0.0.0 --port=8000
