@echo off
setlocal
cd /d "%~dp0"

call "%~dp0scripts\resolve-php.bat" "%~dp0"
if errorlevel 1 exit /b 1

echo Starting Southville HOA queue worker. Press Ctrl+C to stop.
"%PHP_EXE%" artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=600 %*
set "EXIT_CODE=%ERRORLEVEL%"
endlocal & exit /b %EXIT_CODE%
