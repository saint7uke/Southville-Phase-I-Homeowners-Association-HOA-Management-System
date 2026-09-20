@echo off
setlocal
set "PHP_EXE=%~dp0..\..\php\php.exe"
if not exist "%PHP_EXE%" exit /b 1
cd /d "%~dp0"
"%PHP_EXE%" artisan queue:work database --queue=default --tries=3 --timeout=120 --max-time=3600
