@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Repair-LinksVaultInstall.ps1"
exit /b %ERRORLEVEL%
