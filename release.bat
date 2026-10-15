@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

:: ==============================================================================
:: LinksVault - Script d'automatisation des Releases & Hot-Patching Desktop
:: ==============================================================================

cd /d "%~dp0"

echo.
echo ==============================================================================
echo          🚀 LinksVault - Automatisation de Release & Hot-Patch
echo ==============================================================================
echo.

:: 1. Détection de la version actuelle
for /f "tokens=*" %%a in ('powershell -NoProfile -Command "(Select-String -Path 'config/nativephp.php' -Pattern 'NATIVEPHP_APP_VERSION''?, ''?([^'']+)' -AllMatches).Matches.Groups[1].Value"') do (
    set "CURRENT_VERSION=%%a"
)

if "!CURRENT_VERSION!"=="" (
    for /f "tokens=*" %%a in ('git describe --tags --abbrev^=0 2^>nul') do (
        set "CURRENT_TAG=%%a"
    )
    set "CURRENT_VERSION=!CURRENT_TAG:v=!"
)

if "!CURRENT_VERSION!"=="" set "CURRENT_VERSION=1.1.4"

echo  Version actuelle detectee : v!CURRENT_VERSION!
echo.

:: 2. Récupération ou saisie de la nouvelle version
set "NEW_VERSION=%~1"
if "!NEW_VERSION!"=="" (
    set /p "NEW_VERSION= Entrez la nouvelle version (ex: 1.1.5) : "
)

if "!NEW_VERSION!"=="" (
    echo [ERREUR] Aucune version specifiee. Annulation.
    exit /b 1
)

:: Nettoyer le préfixe 'v' si présent
if "!NEW_VERSION:~0,1!"=="v" set "NEW_VERSION=!NEW_VERSION:~1!"
if "!NEW_VERSION:~0,1!"=="V" set "NEW_VERSION=!NEW_VERSION:~1!"

set "RELEASE_MSG=%~2"
if "!RELEASE_MSG!"=="" (
    set "RELEASE_MSG=Release v!NEW_VERSION!"
)

echo.
echo  ============================================================
echo   Preparation de la release : v!CURRENT_VERSION! -^> v!NEW_VERSION!
echo   Message : !RELEASE_MSG!
echo  ============================================================
echo.

set /p "CONFIRM= Confirmer le lancement de la release (O/n) ? "
if /i not "!CONFIRM!"=="O" if /i not "!CONFIRM!"=="Y" if not "!CONFIRM!"=="" (
    echo Annulation par l'utilisateur.
    exit /b 0
)

echo.
echo [1/7] Mise a jour des fichiers de version vers v!NEW_VERSION!...
powershell -NoProfile -Command "$v = '!NEW_VERSION!'; (Get-Content config/nativephp.php) -replace 'NATIVEPHP_APP_VERSION'', ''[^'']+''', ('NATIVEPHP_APP_VERSION'', ''' + $v + '''') | Set-Content config/nativephp.php; (Get-Content app/Models/AppEcosystemSetting.php) -replace '/v[0-9]+\.[0-9]+\.[0-9]+', ('/v' + $v) -replace 'LinksVault-[0-9]+\.[0-9]+\.[0-9]+-setup\.exe', ('LinksVault-' + $v + '-setup.exe') -replace '''desktop_version'' => ''[^'']+''', ('''desktop_version'' => ''' + $v + '''') | Set-Content app/Models/AppEcosystemSetting.php"

if errorlevel 1 (
    echo [ERREUR] Echec lors de la mise a jour des versions.
    exit /b 1
)

echo [2/7] Compilation des assets frontend (npm run build)...
call npm run build
if errorlevel 1 (
    echo [ERREUR] Echec lors de npm run build.
    exit /b 1
)

echo [3/7] Formatage du code avec Laravel Pint...
call vendor\bin\pint --dirty --format agent

echo [4/7] Execution des tests critiques...
php artisan test --compact tests/Feature/DesktopHotPatchTest.php tests/Feature/AppVersionUpdaterTest.php
if errorlevel 1 (
    echo [ERREUR] Les tests ont echoue. Annulation de la release.
    exit /b 1
)

echo [5/7] Generation de l'archive Hot-Patch differentielle...
php artisan desktop:make-patch "v!CURRENT_VERSION!" "HEAD"
if errorlevel 1 (
    echo [AVERTISSEMENT] La commande make-patch a signale un avertissement ou une erreur.
)

echo [6/7] Creation du commit Git et du Tag v!NEW_VERSION!...
git add .
git commit -m "chore(release): bump version to v!NEW_VERSION! - !RELEASE_MSG!"
git tag -a "v!NEW_VERSION!" -m "!RELEASE_MSG!"

echo [7/7] Envoi vers GitHub (git push origin main --tags)...
git push origin main --tags
if errorlevel 1 (
    echo [AVERTISSEMENT] Le push git a echoue ou necessite vos identifiants. Vous pouvez pousser manuellement : git push origin main --tags
) else (
    echo.
    echo  ============================================================
    echo   SUCCES : La release v!NEW_VERSION! a ete poussee vers GitHub !
    echo  ============================================================
    echo.
    echo  GitHub Actions va automatiquement :
    echo   1. Compiler l'installateur Windows (.exe)
    echo   2. Attacher le patch leger (patch-!NEW_VERSION!.zip)
    echo   3. Publier la Release sur GitHub
    echo.
    echo  Archive de patch locale disponible dans :
    echo  storage/app/releases/patch-!NEW_VERSION!.zip
    echo.
)

pause
