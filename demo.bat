@echo off
rem CRM Cabinet : demo hors ligne (donnees fictives, aucun compte externe). Necessite PHP 8.1+ dans le PATH,
rem ou la variable PHP vers php.exe (ex. set PHP=C:\php\php.exe). Identifiant : demo / mot de passe : demo.
setlocal
cd /d "%~dp0"
if "%PHP%"=="" set "PHP=php"
if "%PORT%"=="" set "PORT=8080"
"%PHP%" -v >nul 2>nul
if errorlevel 1 (
  echo PHP introuvable. Installez PHP 8.1 ou plus recent et ajoutez-le au PATH, ou : set PHP=C:\chemin\vers\php.exe
  pause
  exit /b 1
)
set CRM_DEMO=1
echo.
echo   CRM Cabinet - demo : http://127.0.0.1:%PORT%   (identifiant demo / mot de passe demo)
echo   Donnees fictives dans data-demo\ (supprimez ce dossier pour repartir de zero). Ctrl+C pour arreter.
echo.
if not "%SANS_NAVIGATEUR%"=="1" start "" "http://127.0.0.1:%PORT%"
"%PHP%" -S 127.0.0.1:%PORT% -t public public/routeur.php
