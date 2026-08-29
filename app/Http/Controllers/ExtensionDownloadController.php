<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ExtensionDownloadController extends Controller
{
    /**
     * Package and download the Chrome extension as a zip file.
     */
    public function __invoke(Request $request): BinaryFileResponse
    {
        $extensionDir = base_path('extension');

        if (! is_dir($extensionDir)) {
            abort(404, "Le dossier de l'extension est introuvable.");
        }

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir.'/links-vault-clipper.zip';

        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, "Impossible de créer l'archive zip.");
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extensionDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($extensionDir) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);

                // Ignore temporary or source-control files
                if (str_starts_with($relativePath, '.') || str_contains($relativePath, '/.')) {
                    continue;
                }

                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        return response()->download($zipPath, 'links-vault-clipper.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
