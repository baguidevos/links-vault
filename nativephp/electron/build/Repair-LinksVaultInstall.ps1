[CmdletBinding()]
param(
    [string]$InstallerPath
)

$ErrorActionPreference = 'Stop'
$installDirectory = Join-Path $env:LOCALAPPDATA 'Programs\linksvault'
$expectedInstallDirectory = [System.IO.Path]::GetFullPath($installDirectory)

function Stop-LinksVault {
    Get-Process -Name 'linksvault' -ErrorAction SilentlyContinue |
        Stop-Process -Force -ErrorAction Stop
}

function Get-InstallerPath {
    param([string]$Path)

    if ($Path) {
        return [System.IO.Path]::GetFullPath($Path)
    }

    $installers = Get-ChildItem -Path $PSScriptRoot -Filter '*-setup.exe' -File

    if ($installers.Count -ne 1) {
        throw 'Un seul fichier *-setup.exe doit être présent à côté du lanceur de réparation.'
    }

    return $installers[0].FullName
}

$installer = Get-InstallerPath -Path $InstallerPath

if (-not (Test-Path -LiteralPath $installer -PathType Leaf)) {
    throw "Installateur introuvable : $installer"
}

Stop-LinksVault

$uninstaller = Join-Path $expectedInstallDirectory 'Uninstall linksvault.exe'

if (Test-Path -LiteralPath $uninstaller -PathType Leaf) {
    $uninstallProcess = Start-Process -FilePath $uninstaller -ArgumentList '/S' -Wait -PassThru

    if ($uninstallProcess.ExitCode -ne 0) {
        Write-Warning "L'ancien désinstalleur a retourné le code $($uninstallProcess.ExitCode). Nettoyage du dossier d'installation orphelin."
    }
}

Stop-LinksVault

if (Test-Path -LiteralPath $expectedInstallDirectory -PathType Container) {
    Remove-Item -LiteralPath $expectedInstallDirectory -Recurse -Force
}

Start-Process -FilePath $installer -Wait
