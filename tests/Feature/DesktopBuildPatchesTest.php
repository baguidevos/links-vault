<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'linksvault_patch_tests_'.uniqid();
    File::makeDirectory($this->tempDir, 0755, true);
});

afterEach(function () {
    if (File::isDirectory($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

test('patch splash screen injects splash window and is idempotent', function () {
    $fakeIndex = $this->tempDir.DIRECTORY_SEPARATOR.'index.js';
    file_put_contents($fakeIndex, "import { app } from 'electron';\nNativePHP.bootstrap();\n");

    // First run: should patch
    $this->artisan('native:patch-splash-screen', ['--path' => $fakeIndex])
        ->expectsOutput('Injected splash screen into Electron entry point.')
        ->assertSuccessful();

    $content = file_get_contents($fakeIndex);
    expect($content)
        ->toContain('__splashWindow')
        ->toContain("import { app, BrowserWindow } from 'electron'")
        ->toContain('LinksVault');

    // Second run: should skip idempotently
    $this->artisan('native:patch-splash-screen', ['--path' => $fakeIndex])
        ->expectsOutput('Splash screen already patched — skipping.')
        ->assertSuccessful();
});

test('patch electron builder injects locales and compression and is idempotent', function () {
    $fakeBuilder = $this->tempDir.DIRECTORY_SEPARATOR.'electron-builder.mjs';
    file_put_contents($fakeBuilder, "export default {\n    appId: 'test',\n    npmRebuild: false,\n};\n");

    // First run
    $this->artisan('native:patch-electron-builder', ['--path' => $fakeBuilder])
        ->expectsOutput('Injected electronLanguages into electron-builder.mjs.')
        ->expectsOutput('Injected compression: "normal" into electron-builder.mjs.')
        ->assertSuccessful();

    $content = file_get_contents($fakeBuilder);
    expect($content)
        ->toContain("electronLanguages: ['fr', 'en-US']")
        ->toContain("compression: 'normal'");

    // Second run
    $this->artisan('native:patch-electron-builder', ['--path' => $fakeBuilder])
        ->expectsOutput('electronLanguages already configured — skipping.')
        ->expectsOutput('compression already configured — skipping.')
        ->assertSuccessful();
});

test('patch nsis installer injects nsis options and creates installer nsh', function () {
    $fakeBuilder = $this->tempDir.DIRECTORY_SEPARATOR.'electron-builder.mjs';
    $buildDir = $this->tempDir.DIRECTORY_SEPARATOR.'build';
    File::makeDirectory($buildDir);
    file_put_contents($fakeBuilder, "export default {\n    nsis: {\n        artifactName: 'test',\n    },\n};\n");

    // First run
    $this->artisan('native:patch-nsis-installer', [
        '--path' => $fakeBuilder,
        '--build-dir' => $buildDir,
    ])
        ->expectsOutput('Injected NSIS options into electron-builder.mjs.')
        ->expectsOutput('Created installer.nsh with branded wizard pages.')
        ->assertSuccessful();

    $builderContent = file_get_contents($fakeBuilder);
    expect($builderContent)
        ->toContain('oneClick: false')
        ->toContain('allowToChangeInstallationDirectory: true');

    $nshFile = $buildDir.DIRECTORY_SEPARATOR.'installer.nsh';
    expect(file_exists($nshFile))->toBeTrue();
    $nshContent = file_get_contents($nshFile);
    expect($nshContent)
        ->toContain('LinksVault')
        ->toContain('BringToFront')
        ->toContain('customWelcomePage');

    // Second run
    $this->artisan('native:patch-nsis-installer', [
        '--path' => $fakeBuilder,
        '--build-dir' => $buildDir,
    ])
        ->expectsOutput('NSIS options already configured — skipping electron-builder.mjs patch.')
        ->assertSuccessful();
});

test('patch auto updater injects graceful shutdown and is idempotent', function () {
    $fakePluginIndex = $this->tempDir.DIRECTORY_SEPARATOR.'index.js';
    file_put_contents($fakePluginIndex, "autoUpdater.checkForUpdatesAndNotify();\n");

    // First run
    $this->artisan('native:patch-auto-updater', ['--index-path' => $fakePluginIndex])
        ->expectsOutput('Injected autoInstallOnAppQuit = false into index.js.')
        ->expectsOutput('Injected quit-on-close update handler into index.js.')
        ->assertSuccessful();

    $content = file_get_contents($fakePluginIndex);
    expect($content)
        ->toContain('autoUpdater.autoInstallOnAppQuit = false')
        ->toContain('_updateReady')
        ->toContain('autoUpdater.quitAndInstall()');

    // Second run
    $this->artisan('native:patch-auto-updater', ['--index-path' => $fakePluginIndex])
        ->expectsOutput('autoInstallOnAppQuit already configured — skipping.')
        ->expectsOutput('quit-on-close handler already configured — skipping.')
        ->assertSuccessful();
});
