@echo off
REM Run database migrations via PHP CLI
REM Usage: database\migrate.bat [command]
REM   migrate.bat          run pending migrations
REM   migrate.bat status   show migration status
REM   migrate.bat install  create schema + seeds
REM   migrate.bat fresh    drop DB and reinstall (add --force to skip prompt)
REM   migrate.bat stamp    mark all migrations applied
REM   migrate.bat help     show help

php "%~dp0migrate.php" %*
