<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class PatchElectronBuilderCommand extends Command
{
    protected $signature = 'native:patch-electron-builder
        {--path= : Path to electron-builder.mjs (defaults to vendor file)}';

    protected $description = 'Patch electron-builder.mjs: strip unused Chromium locales, use faster NSIS compression, and complete Azure signing options';

    public function handle(): int
    {
        $path = $this->option('path')
            ?? base_path('vendor/nativephp/desktop/resources/electron/electron-builder.mjs');

        if (! file_exists($path)) {
            $this->warn("electron-builder.mjs not found at: {$path}");

            return self::SUCCESS;
        }

        $content = file_get_contents($path);
        $changed = false;

        $content = $this->injectElectronLanguages($content, $changed);
        $content = $this->injectCompression($content, $changed);
        $content = $this->injectAzurePublisherName($content, $changed);

        if ($changed) {
            file_put_contents($path, $content);
        }

        return self::SUCCESS;
    }

    /**
     * Add the required publisherName to azureSignOptions if Azure signing is present.
     */
    private function injectAzurePublisherName(string $content, bool &$changed): string
    {
        if (! str_contains($content, 'azureSignOptions:')) {
            return $content;
        }

        if (str_contains($content, 'publisherName')) {
            $this->info('azureSignOptions publisherName already configured — skipping.');

            return $content;
        }

        $needle = 'azureSignOptions: {';

        $changed = true;
        $this->info('Injected publisherName into azureSignOptions in electron-builder.mjs.');

        return str_replace(
            $needle,
            $needle."\n                      publisherName: process.env.NATIVEPHP_AZURE_PUBLISHER_NAME,",
            $content,
        );
    }

    private function injectElectronLanguages(string $content, bool &$changed): string
    {
        if (str_contains($content, 'electronLanguages')) {
            $this->info('electronLanguages already configured — skipping.');

            return $content;
        }

        $needle = 'export default {';

        if (! str_contains($content, $needle)) {
            throw new RuntimeException("Could not find '{$needle}' in electron-builder.mjs");
        }

        $changed = true;
        $this->info('Injected electronLanguages into electron-builder.mjs.');

        return str_replace(
            $needle,
            $needle."\n    electronLanguages: ['fr', 'en-US'],",
            $content,
        );
    }

    private function injectCompression(string $content, bool &$changed): string
    {
        if (str_contains($content, "'compression'") || str_contains($content, 'compression:')) {
            $this->info('compression already configured — skipping.');

            return $content;
        }

        $needle = 'npmRebuild:';

        if (! str_contains($content, $needle)) {
            throw new RuntimeException("Could not find '{$needle}' in electron-builder.mjs");
        }

        $changed = true;
        $this->info('Injected compression: "normal" into electron-builder.mjs.');

        return str_replace(
            $needle,
            "compression: 'normal',\n    ".$needle,
            $content,
        );
    }
}
