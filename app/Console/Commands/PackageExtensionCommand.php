<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class PackageExtensionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clipper:package {--output= : Chemin personnalisé du fichier ZIP de sortie}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Construit le package ZIP de l\'extension Web Clipper pour Chrome Web Store et Firefox Add-ons';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('📦 Construction du package de l\'extension Web Clipper...');

        $extensionDir = base_path('extension');

        if (! is_dir($extensionDir)) {
            $this->error('Dossier extension/ introuvable.');

            return self::FAILURE;
        }

        // Valider la présence du manifest.json
        $manifestPath = $extensionDir.'/manifest.json';
        if (! file_exists($manifestPath)) {
            $this->error('Fichier manifest.json manquant dans extension/.');

            return self::FAILURE;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $version = $manifest['version'] ?? '1.0.0';
        $name = $manifest['name'] ?? 'LinksVault Web Clipper';

        $this->line("Nom : <comment>{$name}</comment>");
        $this->line("Version : <comment>v{$version}</comment>");

        $distDir = base_path('dist');
        if (! is_dir($distDir)) {
            mkdir($distDir, 0755, true);
        }

        $outputPath = $this->option('output') ?: $distDir."/links-vault-clipper-v{$version}.zip";

        if (file_exists($outputPath)) {
            @unlink($outputPath);
        }

        $zip = new ZipArchive;
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Impossible de créer l'archive zip à {$outputPath}.");

            return self::FAILURE;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extensionDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $fileCount = 0;
        foreach ($files as $name => $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($extensionDir) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);

                if (str_starts_with($relativePath, '.') || str_contains($relativePath, '/.')) {
                    continue;
                }

                $zip->addFile($filePath, $relativePath);
                $fileCount++;
            }
        }

        $zip->close();

        $size = round(filesize($outputPath) / 1024, 2);

        $this->info("✅ Archive ZIP créée avec succès ({$fileCount} fichiers, {$size} Ko) :");
        $this->line("👉 <info>{$outputPath}</info>");

        return self::SUCCESS;
    }
}
