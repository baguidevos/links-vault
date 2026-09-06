<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class PatchNsisInstallerCommand extends Command
{
    protected $signature = 'native:patch-nsis-installer
        {--path= : Path to electron-builder.mjs (defaults to vendor file)}
        {--build-dir= : Path to the build directory for installer.nsh (defaults to vendor file)}';

    protected $description = 'Switch the Windows NSIS installer from one-click to assisted wizard with branded pages';

    public function handle(): int
    {
        $this->patchElectronBuilder();
        $this->createInstallerNsh();

        return self::SUCCESS;
    }

    private function patchElectronBuilder(): void
    {
        $path = $this->option('path')
            ?? base_path('vendor/nativephp/desktop/resources/electron/electron-builder.mjs');

        if (! file_exists($path)) {
            $this->warn("electron-builder.mjs not found at: {$path}");

            return;
        }

        $content = file_get_contents($path);

        if (str_contains($content, 'oneClick') && str_contains($content, 'allowToChangeInstallationDirectory')) {
            $this->info('NSIS options already configured — skipping electron-builder.mjs patch.');

            return;
        }

        $needle = 'nsis: {';

        if (! str_contains($content, $needle)) {
            throw new RuntimeException("Could not find '{$needle}' in {$path}");
        }

        $injection = implode("\n", [
            'oneClick: false,',
            '        allowToChangeInstallationDirectory: true,',
        ]);

        $content = str_replace(
            $needle,
            $needle."\n        ".$injection,
            $content,
        );

        file_put_contents($path, $content);

        $this->info('Injected NSIS options into electron-builder.mjs.');
    }

    private function createInstallerNsh(): void
    {
        $buildDir = $this->option('build-dir')
            ?? base_path('vendor/nativephp/desktop/resources/electron/build');

        if (! is_dir($buildDir)) {
            $this->warn("Build directory not found at: {$buildDir}");

            return;
        }

        $path = $buildDir.'/installer.nsh';

        file_put_contents($path, $this->getInstallerNsh());

        $this->info('Created installer.nsh with branded wizard pages.');
    }

    private function getInstallerNsh(): string
    {
        return <<<'NSH'
; Custom NSIS installer pages for LinksVault
; Defines customInit, customHeader, customWelcomePage, customInstall, and customFinishPage
; macros consumed by installer.nsi / assistedInstaller.nsh via !ifmacrodef.

; Automatically delete %APPDATA%\links-vault on uninstall (skipped during updates).
!ifndef DELETE_APP_DATA_ON_UNINSTALL
    !define DELETE_APP_DATA_ON_UNINSTALL
!endif

; Bring the installer window to the foreground on launch.
; Without this the NSIS wizard spawns behind other windows during auto-updates.
!macro customInit
    BringToFront
!macroend

!macro customHeader
    !define MUI_ABORTWARNING
!macroend

; Enable Cancel button on instfiles page and ensure window is in foreground
!macro customPageAfterChangeDir
    Function InstFilesShow
        GetDlgItem $0 $HWNDPARENT 2 ; 2 = Cancel button
        EnableWindow $0 1
        BringToFront
    FunctionEnd
    !define MUI_PAGE_CUSTOMFUNCTION_SHOW InstFilesShow
!macroend

; During updates (${isUpdated}), skip the welcome page so the user goes
; straight to the progress bar. Fresh installs still show the full wizard.
!macro customWelcomePage
    Function WelcomePagePre
        ${if} ${isUpdated}
            Abort
        ${endif}
    FunctionEnd
    !define MUI_PAGE_CUSTOMFUNCTION_PRE WelcomePagePre
    !define MUI_WELCOMEPAGE_TITLE "Bienvenue dans l'installation de LinksVault"
    !define MUI_WELCOMEPAGE_TEXT "Cet assistant va installer LinksVault sur votre ordinateur.$\r$\n$\r$\nLinksVault est votre coffre-fort universel de liens, favoris et connaissances avec synchronisation intelligente et IA locale.$\r$\n$\r$\nCliquez sur Suivant pour continuer."
    !insertmacro MUI_PAGE_WELCOME
!macroend

!macro customInstall
    ; no-op
!macroend

; During updates, auto-launch the app and skip the finish page entirely so
; the user never has to click "Finish" manually. Fresh installs keep the
; standard finish page with the "Launch LinksVault" checkbox.
!macro customFinishPage
    !ifndef HIDE_RUN_AFTER_FINISH
        Function LaunchLinksVault
            ${if} ${isUpdated}
                StrCpy $1 "--updated"
            ${else}
                StrCpy $1 ""
            ${endif}
            ${StdUtils.ExecShellAsUser} $0 "$launchLink" "open" "$1"
        FunctionEnd

        Function FinishPagePre
            ${if} ${isUpdated}
                Call LaunchLinksVault
                Quit
            ${endif}
        FunctionEnd

        !define MUI_PAGE_CUSTOMFUNCTION_PRE FinishPagePre
        !define MUI_FINISHPAGE_RUN
        !define MUI_FINISHPAGE_RUN_TEXT "Lancer LinksVault"
        !define MUI_FINISHPAGE_RUN_FUNCTION "LaunchLinksVault"
    !endif

    !define MUI_FINISHPAGE_TITLE "LinksVault a été installé avec succès"
    !define MUI_FINISHPAGE_TEXT "L'installation de LinksVault sur votre ordinateur est terminée.$\r$\n$\r$\nCliquez sur Terminer pour fermer l'assistant."
    !insertmacro MUI_PAGE_FINISH
!macroend

; Clean up leftover data from %LOCALAPPDATA% on uninstall (skipped during updates).
!macro customUnInstall
    ${ifNot} ${isUpdated}
        RMDir /r "$LOCALAPPDATA\links-vault-updater"
        RMDir /r "$LOCALAPPDATA\links-vault"
    ${endif}
!macroend
NSH;
    }
}
