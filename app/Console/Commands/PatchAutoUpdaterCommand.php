<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class PatchAutoUpdaterCommand extends Command
{
    protected $signature = 'native:patch-auto-updater
        {--index-path= : Path to electron-plugin dist/index.js}';

    protected $description = 'Patch auto-updater: disable autoInstallOnAppQuit and install pending updates gracefully on app quit';

    private const ELECTRON_PLUGIN_DIR = 'vendor/nativephp/desktop/resources/electron/electron-plugin/dist';

    public function handle(): int
    {
        $this->patchIndexJs();

        return self::SUCCESS;
    }

    private function patchIndexJs(): void
    {
        $explicitPath = $this->option('index-path');

        if ($explicitPath !== null) {
            $this->patchFile($explicitPath);

            return;
        }

        $candidates = array_unique([
            base_path('nativephp/electron/electron-plugin/dist/index.js'),
            base_path(self::ELECTRON_PLUGIN_DIR.'/index.js'),
        ]);

        $found = false;
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $this->patchFile($path);
                $found = true;
            }
        }

        if (! $found) {
            $this->warn('index.js not found in any candidate path.');
        }
    }

    private function patchFile(string $path): void
    {
        if (! file_exists($path)) {
            $this->warn("index.js not found at: {$path}");

            return;
        }

        $content = file_get_contents($path);
        $changed = false;

        $content = $this->disableAutoInstall($content, $changed);
        $content = $this->injectQuitOnClose($content, $changed);

        if ($changed) {
            file_put_contents($path, $content);
        }
    }

    private function disableAutoInstall(string $content, bool &$changed): string
    {
        if (str_contains($content, 'autoInstallOnAppQuit')) {
            $this->info('autoInstallOnAppQuit already configured — skipping.');

            return $content;
        }

        $needle = 'autoUpdater.checkForUpdatesAndNotify();';

        if (! str_contains($content, $needle)) {
            throw new RuntimeException("Could not find '{$needle}' in index.js");
        }

        $changed = true;
        $this->info('Injected autoInstallOnAppQuit = false into index.js.');

        return str_replace(
            $needle,
            "autoUpdater.autoInstallOnAppQuit = false;\n            {$needle}",
            $content,
        );
    }

    private function injectQuitOnClose(string $content, bool &$changed): string
    {
        if (str_contains($content, '_updateReady')) {
            $this->info('quit-on-close handler already configured — skipping.');

            return $content;
        }

        $needle = 'autoUpdater.checkForUpdatesAndNotify();';

        if (! str_contains($content, $needle)) {
            throw new RuntimeException("Could not find '{$needle}' in index.js");
        }

        $handler = <<<'JS'

            let _updateReady = false;
            autoUpdater.on('update-downloaded', () => { _updateReady = true; });
            const _origQAI = autoUpdater.quitAndInstall.bind(autoUpdater);
            autoUpdater.quitAndInstall = (...args) => { _updateReady = false; _origQAI(...args); };
            app.on('before-quit', (e) => {
                if (_updateReady && !global.__isRelaunching) {
                    _updateReady = false;
                    e.preventDefault();
                    autoUpdater.quitAndInstall();
                }
            });
JS;

        $changed = true;
        $this->info('Injected quit-on-close update handler into index.js.');

        return str_replace(
            $needle,
            $needle.$handler,
            $content,
        );
    }
}
