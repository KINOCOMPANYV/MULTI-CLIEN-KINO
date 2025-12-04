@echo off
REM backup_system.bat
REM Script para crear backup completo del sistema

echo ========================================
echo   BACKUP DEL SISTEMA KINO v1.0
echo ========================================
echo.

REM Crear carpeta de backups si no existe
if not exist "backups" mkdir backups

REM Obtener fecha y hora para el nombre del backup
for /f "tokens=2-4 delims=/ " %%a in ('date /t') do (set mydate=%%c-%%a-%%b)
for /f "tokens=1-2 delims=/:" %%a in ('time /t') do (set mytime=%%a%%b)
set datetime=%mydate%_%mytime%

echo [1/3] Creando backup del codigo...
git archive --format=zip --output=backups/code-backup-%datetime%.zip HEAD
if %errorlevel% equ 0 (
    echo ✓ Backup del codigo creado: backups/code-backup-%datetime%.zip
) else (
    echo × Error al crear backup del codigo
)

echo.
echo [2/3] Creando backup del archivo SQL...
if exist "if0_39064130_buscador (10).sql" (
    copy "if0_39064130_buscador (10).sql" "backups\sql-backup-%datetime%.sql" >nul
    echo ✓ Backup SQL creado: backups/sql-backup-%datetime%.sql
) else (
    echo × Archivo SQL no encontrado
)

echo.
echo [3/3] Guardando informacion del commit actual...
git log -1 --pretty=format:"Commit: %%H%%nAutor: %%an%%nFecha: %%ad%%nMensaje: %%s" > backups/commit-info-%datetime%.txt
echo ✓ Informacion del commit guardada: backups/commit-info-%datetime%.txt

echo.
echo ========================================
echo   BACKUP COMPLETADO
echo ========================================
echo.
echo Archivos creados en la carpeta 'backups':
dir /b backups\*%datetime%*
echo.
echo Para restaurar este backup en el futuro:
echo 1. Descomprime code-backup-%datetime%.zip
echo 2. Importa sql-backup-%datetime%.sql a la base de datos
echo 3. Revisa commit-info-%datetime%.txt para ver el estado
echo.
pause
