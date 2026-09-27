@echo off

set "PHP_EXE=%HOA_PHP_BINARY%"
if defined PHP_EXE (
    if exist "%PHP_EXE%" goto php_found
    echo ERROR: HOA_PHP_BINARY does not point to an existing PHP executable:
    echo        "%PHP_EXE%"
    exit /b 1
)

if exist "%~1..\..\php\php.exe" (
    for %%I in ("%~1..\..\php\php.exe") do set "PHP_EXE=%%~fI"
    goto php_found
)

where php.exe >nul 2>&1
if not errorlevel 1 (
    set "PHP_EXE=php.exe"
    goto php_found
)

echo ERROR: PHP was not found.
echo Set HOA_PHP_BINARY to php.exe or install this project inside XAMPP's htdocs folder.
exit /b 1

:php_found
if not exist "%~1artisan" (
    echo ERROR: artisan was not found in %~1
    exit /b 1
)
exit /b 0
