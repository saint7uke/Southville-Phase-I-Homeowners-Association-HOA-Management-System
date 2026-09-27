@echo off
setlocal
cd /d "%~dp0"

call "%~dp0scripts\resolve-php.bat" "%~dp0"
if errorlevel 1 exit /b 1

echo Starting Southville HOA at http://127.0.0.1:8000 ...
"%PHP_EXE%" artisan serve --host=127.0.0.1 --port=8000 %*
set "EXIT_CODE=%ERRORLEVEL%"
endlocal & exit /b %EXIT_CODE%
