@echo off
title UniMICE Frontend
cd /d "%~dp0"

rem Prefer PHP from PATH; fall back to the author's local install path.
set "PHP_CMD=php"
where php >nul 2>nul
if errorlevel 1 (
    if exist "E:\Programs\PHP\php.exe" (
        set "PHP_CMD=E:\Programs\PHP\php.exe"
    ) else (
        echo PHP not found. Install PHP 8.3+ and add it to PATH.
        pause
        exit /b 1
    )
)

"%PHP_CMD%" artisan serve --port=8001
pause
