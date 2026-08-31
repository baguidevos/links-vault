<?php

declare(strict_types=1);

namespace App\Services\CloudBackup\Contracts;

interface CloudStorageConnectorInterface
{
    /**
     * Upload a backup file to the remote cloud storage.
     *
     * @param  string  $remoteFilename  Filename to use on remote
     * @param  string  $fileContent  Binary zip file content
     * @return array{success: bool, path: string, id: ?string, error: ?string}
     */
    public function upload(string $remoteFilename, string $fileContent): array;

    /**
     * Download a backup file from remote cloud storage.
     *
     * @param  string  $remotePath  Remote path or file ID
     * @return string|null Binary file content
     */
    public function download(string $remotePath): ?string;

    /**
     * Delete a backup file from remote cloud storage.
     *
     * @param  string  $remotePath  Remote path or file ID
     */
    public function delete(string $remotePath): bool;

    /**
     * Test connection to the cloud storage provider.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * Get the identifier key for this provider (e.g. 'google_drive', 's3', 'dropbox', 'local').
     */
    public function getProviderKey(): string;
}
