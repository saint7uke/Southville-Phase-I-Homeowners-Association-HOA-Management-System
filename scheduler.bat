@echo off
setlocal
cd /d "%~dp0"

call "%~dp0scripts\resolve-php.bat" "%~dp0"
if errorlevel 1 exit /b 1

echo Starting Southville HOA scheduler. Press Ctrl+C to stop.
"%PHP_EXE%" artisan schedule:work %*
set "EXIT_CODE=%ERRORLEVEL%"
endlocal & exit /b %EXIT_CODE%
