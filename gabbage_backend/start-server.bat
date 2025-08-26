@echo off
echo Starting Laravel Backend Server...
echo Server will be accessible at http://0.0.0.0:8000
echo Press Ctrl+C to stop the server
php artisan serve --host=0.0.0.0 --port=8000
pause