<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

class MakeDesktopPatchCommand extends Command
{
    protected $signature = 'desktop:make-patch
        {from_tag? : Base git tag or commit (e.g. v1.1.3)}
        {to_tag? : Target git tag or commit (default: HEAD)}
        {--output= : Custom destination path for the patch zip}';

    protected $description = 'Génère une archive de mise à jour différentielle (Hot-Patch) entre deux versions git';

    protected const ALLOWED_PREFIXES = [
        'app/',
        'config/',
        'database/migrations/',
        'database/seeders/',
        'public/build/',
        'resources/',
        'routes/',
        'nativephp/electron/electron-plugin/dist/',
    ];

    public function handle(): int
    {
        $fromTag = $this->argument('from_tag');
        $toTag = $this->argument('to_tag') ?? 'HEAD';

        if (! $fromTag) {
            $fromTag = $this->detectPreviousTag();
            if (! $fromTag) {
                $this->error('Impossible de déterminer automatiquement le tag précédent. Veuillez spécifier : php artisan desktop:make-patch v1.1.3');

                return self::FAILURE;
            }
        }

        $this->info("🔍 Analyse du différentiel git entre <comment>{$fromTag}</comment> et <comment>{$toTag}</comment>...");

        $diffProcess = new Process(['git', 'diff', '--name-status', "{$fromTag}..{$toTag}"]);
        $diffProcess->run();

        if (! $diffProcess->isSuccessful()) {
            $this->error("Erreur lors de l'exécution de git diff : ".$diffProcess->getErrorOutput());

            return self::FAILURE;
        }

        $output = trim($diffProcess->getOutput());
        if (empty($output)) {
            $this->warn('Aucun fichier modifié entre ces deux versions.');

            return self::SUCCESS;
        }

        $lines = explode("\n", str_replace("\r", '', $output));
        $filesToInclude = [];
        $filesToDelete = [];
        $hasMigrations = false;

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line), 2);
            if (count($parts) < 2) {
                continue;
            }

            $status = $parts[0];
            $filePath = str_replace('\\', '/', $parts[1]);

            if (! $this->isAllowedPath($filePath)) {
                continue;
            }

            if ($status === 'D') {
                $filesToDelete[] = $filePath;
            } else {
                $filesToInclude[] = $filePath;
                if (str_starts_with($filePath, 'database/migrations/')) {
                    $hasMigrations = true;
                }
            }
        }

        // Include current public/build files if any frontend file was touched
        $frontendTouched = collect($filesToInclude)->contains(fn ($p) => str_starts_with($p, 'resources/') || str_contains($p, 'Filament'));
        if ($frontendTouched && File::isDirectory(public_path('build'))) {
            $buildFiles = File::allFiles(public_path('build'));
            foreach ($buildFiles as $file) {
                $rel = str_replace('\\', '/', 'public/build/'.$file->getRelativePathname());
                if (! in_array($rel, $filesToInclude, true)) {
                    $filesToInclude[] = $rel;
                }
            }
        }

        $filesToInclude = array_values(array_unique($filesToInclude));

        if (empty($filesToInclude) && empty($filesToDelete)) {
            $this->warn('Aucun fichier éligible au hot-patch trouvé dans ce diff.');

            return self::SUCCESS;
        }

        $this->info('📦 Fichiers inclus dans le patch : '.count($filesToInclude));
        $this->info('🗑️ Fichiers supprimés dans le patch : '.count($filesToDelete));

        $fromClean = ltrim($fromTag, 'v');
        $toClean = $toTag === 'HEAD' ? ltrim((string) config('nativephp.version', '1.1.4'), 'v') : ltrim($toTag, 'v');

        $manifest = [
            'app' => config('nativephp.app_id', 'com.linksvault.desktop'),
            'from_version' => $fromClean,
            'to_version' => $toClean,
            'created_at' => now()->toIso8601String(),
            'has_migrations' => $hasMigrations,
            'files' => array_map(function ($p) {
                $full = base_path($p);

                return [
                    'path' => $p,
                    'sha256' => File::exists($full) ? hash_file('sha256', $full) : null,
                    'size' => File::exists($full) ? File::size($full) : 0,
                ];
            }, $filesToInclude),
            'deleted_files' => $filesToDelete,
        ];

        $outputDir = storage_path('app/releases');
        if (! File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $zipFileName = "patch-{$fromClean}-to-{$toClean}.zip";
        $zipPath = $this->option('output') ?: ($outputDir.'/'.$zipFileName);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Impossible de créer l'archive zip : {$zipPath}");

            return self::FAILURE;
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        foreach ($filesToInclude as $relPath) {
            $full = base_path($relPath);
            if (File::exists($full)) {
                $zip->addFile($full, $relPath);
            }
        }

        $zip->close();

        // Also duplicate as generic patch-{version}.zip for easy GitHub release asset matching
        $genericZipPath = $outputDir."/patch-{$toClean}.zip";
        File::copy($zipPath, $genericZipPath);

        $size = File::size($zipPath);
        $sizeHuman = $this->formatBytes($size);

        $this->newLine();
        $this->line('🎉 <info>Patch généré avec succès !</info>');
        $this->line("📁 Chemin du fichier : <comment>{$zipPath}</comment>");
        $this->line("📁 Version générique : <comment>{$genericZipPath}</comment>");
        $this->line("⚡ Poids de l'archive : <info>{$sizeHuman}</info> (au lieu de ~150 Mo pour l'installeur complet)");
        $this->newLine();
        $this->line('👉 <comment>Pour le publier :</comment> Attachez simplement ce fichier zip aux assets de votre Release GitHub v'.$toClean);

        return self::SUCCESS;
    }

    protected function detectPreviousTag(): ?string
    {
        $proc = new Process(['git', 'tag', '--sort=-v:refname']);
        $proc->run();

        if ($proc->isSuccessful()) {
            $tags = array_filter(explode("\n", trim($proc->getOutput())));
            if (count($tags) >= 2) {
                return $tags[1]; // Previous tag
            }
            if (count($tags) === 1) {
                return $tags[0];
            }
        }

        return null;
    }

    protected function isAllowedPath(string $path): bool
    {
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' Mo';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' Ko';
        }

        return $bytes.' octets';
    }
}
