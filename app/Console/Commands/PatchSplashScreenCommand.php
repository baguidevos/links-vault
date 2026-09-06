<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class PatchSplashScreenCommand extends Command
{
    protected $signature = 'native:patch-splash-screen
        {--path= : Path to index.js (defaults to vendor file)}';

    protected $description = 'Inject a branded splash screen into the Electron entry point so users see immediate feedback on launch';

    public function handle(): int
    {
        $path = $this->option('path')
            ?? base_path('vendor/nativephp/desktop/resources/electron/src/main/index.js');

        if (! file_exists($path)) {
            $this->warn("index.js not found at: {$path}");

            return self::SUCCESS;
        }

        $content = file_get_contents($path);

        if (str_contains($content, '__splashWindow')) {
            $this->info('Splash screen already patched — skipping.');

            return self::SUCCESS;
        }

        // 1. Add BrowserWindow to electron import
        $importPattern = "/import\s*\{\s*app\s*\}\s*from 'electron'/";

        if (! preg_match($importPattern, $content)) {
            throw new RuntimeException("Could not find electron import in {$path}");
        }

        $content = preg_replace($importPattern, "import { app, BrowserWindow } from 'electron'", $content);

        // 2. Inject splash window code before NativePHP.bootstrap()
        $needle = 'NativePHP.bootstrap(';

        if (! str_contains($content, $needle)) {
            throw new RuntimeException("Could not find 'NativePHP.bootstrap(' in {$path}");
        }

        $splashCode = $this->getSplashCode();

        $content = str_replace($needle, $splashCode."\n".$needle, $content);

        file_put_contents($path, $content);

        $this->info('Injected splash screen into Electron entry point.');

        return self::SUCCESS;
    }

    private function getSplashCode(): string
    {
        return <<<'JS'
/**
 * LinksVault Splash screen — shown immediately while PHP boots.
 * Marker: __splashWindow (used for idempotency check)
 */
let __splashWindow = null;

const splashHtml = `
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body {
    width: 100%; height: 100%;
    overflow: hidden;
    background: transparent;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    user-select: none;
    -webkit-app-region: drag;
  }
  .card {
    width: 100%; height: 100%;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 20px;
    background: #0b1120;
    border-radius: 18px;
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 25px 60px rgba(0,0,0,0.6);
  }
  .brand {
    display: flex; align-items: center; gap: 12px;
  }
  .icon-box {
    width: 44px; height: 44px;
    background: linear-gradient(135deg, #0099FF, #4F46E5);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 8px 20px rgba(0,153,255,0.3);
  }
  .icon-box svg { width: 24px; height: 24px; color: white; stroke: currentColor; fill: none; }
  .title {
    font-size: 22px; font-weight: 800; letter-spacing: -0.5px;
    background: linear-gradient(90deg, #38bdf8, #60a5fa, #818cf8);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  }
  .spinner {
    width: 28px; height: 28px;
    border: 3px solid rgba(255,255,255,0.1);
    border-top-color: #0099FF;
    border-radius: 50%;
    animation: spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  .label { color: rgba(255,255,255,0.65); font-size: 13px; font-weight: 500; letter-spacing: 0.3px; }
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="icon-box">
      <svg viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <rect width="18" height="18" x="3" y="3" rx="2"/>
        <path d="M10 10l4 4m0-4l-4 4"/>
      </svg>
    </div>
    <div class="title">LinksVault</div>
  </div>
  <div class="spinner"></div>
  <div class="label">Démarrage du coffre-fort…</div>
</div>
</body>
</html>`;

app.whenReady().then(() => {
  __splashWindow = new BrowserWindow({
    width: 420,
    height: 280,
    frame: false,
    transparent: true,
    resizable: false,
    alwaysOnTop: true,
    skipTaskbar: true,
    center: true,
    show: false,
    webPreferences: { nodeIntegration: false, contextIsolation: true },
  });

  __splashWindow.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(splashHtml));

  __splashWindow.once('ready-to-show', () => {
    if (__splashWindow && !__splashWindow.isDestroyed()) {
      __splashWindow.show();
    }
  });

  // Register AFTER splash is assigned so it never matches the splash window
  app.on('browser-window-created', (_event, win) => {
    if (win === __splashWindow) return;

    // A real NativePHP window was created — close splash when it finishes loading
    win.webContents.once('did-finish-load', () => {
      if (__splashWindow && !__splashWindow.isDestroyed()) {
        __splashWindow.close();
        __splashWindow = null;
      }
    });
  });
});
JS;
    }
}
