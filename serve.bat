@echo off
setlocal
set "PHP_EXE=%~dp0..\..\php\php.exe"
if not exist "%PHP_EXE%" (
  echo XAMPP PHP was not found at "%PHP_EXE%".
  echo Update PHP_EXE in serve.bat if XAMPP is installed elsewhere.
  exit /b 1
)
cd /d "%~dp0"
"%PHP_EXE%" artisan serve --host=127.0.0.1 --port=8000
