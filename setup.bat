@echo off
setlocal enabledelayedexpansion

REM Change to repository root
cd /d "%~dp0"

echo ============================================
echo   Harba Appointment Booking Setup Script
echo ============================================

REM Ensure base .env exists
if not exist ".env" (
    echo [.env] not found. Copying from .env.example ...
    copy ".env.example" ".env" >nul
)

REM Ensure backend/.env exists (Symfony needs it)
if not exist "backend\.env" (
    echo [backend\.env] not found. Copying from backend/.env.example ...
    copy "backend\.env.example" "backend\.env" >nul
)

echo.
echo [1/7] Stopping any running containers...
docker compose down --remove-orphans
if errorlevel 1 goto :error

echo.
echo [2/7] Building and starting containers in the background...
docker compose up -d --build
if errorlevel 1 goto :error

echo.
echo [3/7] Installing backend Composer dependencies...
docker compose run --rm php composer install --no-interaction
if errorlevel 1 goto :error

echo.
echo [4/7] Generating JWT keys (skips if already present)...
docker compose run --rm php php bin/console lexik:jwt:generate-keypair --skip-if-exists
if errorlevel 1 goto :error

echo.
echo [5/7] Running database migrations...
docker compose run --rm php php bin/console doctrine:migrations:migrate --no-interaction
if errorlevel 1 goto :error

echo.
echo [6/7] Loading demo fixtures...
docker compose run --rm php php bin/console doctrine:fixtures:load --no-interaction
if errorlevel 1 goto :error

echo.
echo [7/7] Installing frontend npm dependencies...
docker compose run --rm frontend npm install
if errorlevel 1 goto :error

echo.
echo OK Setup complete!
echo - Containers are running (docker compose up -d)
echo - Backend ready at http://localhost:8080
echo - Frontend dev server still runs via docker compose (port 5173)
echo.
pause
goto :eof

:error
echo.
echo ! Setup aborted due to an error. Check the output above.
pause
exit /b 1


