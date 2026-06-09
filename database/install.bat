@echo off
REM Phase 0 — Install database (XAMPP)
REM Usage: double-click or run from project root

set MYSQL=C:\xampp\mysql\bin\mysql.exe

if not exist "%MYSQL%" (
    echo ERROR: MySQL not found at %MYSQL%
    echo Adjust MYSQL path in database\install.bat
    exit /b 1
)

echo Installing stock_manage schema...
"%MYSQL%" -u root < "%~dp0schema.sql"
if errorlevel 1 exit /b 1

echo Seeding default admin user...
"%MYSQL%" -u root stock_manage < "%~dp0seeds.sql"
if errorlevel 1 exit /b 1

echo Recording migrations (schema is up to date)...
php "%~dp0migrate.php" stamp
if errorlevel 1 exit /b 1

echo.
echo Done. Testing connection...
php "%~dp0test_connection.php"
exit /b %ERRORLEVEL%
