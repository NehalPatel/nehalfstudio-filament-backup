<?php

namespace NehalfStudio\FilamentBackup\Services;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use RuntimeException;

class GoogleDriveDestination
{
    public function isEnabled(): bool
    {
        return (bool) config('filament-backup.google_drive.enabled', false);
    }

    public function store(string $sourcePath, string $fileName): void
    {
        $service = $this->driveService();
        $folderId = (string) config('filament-backup.google_drive.folder_id', '');
        if ($folderId === '') {
            throw new RuntimeException('Google Drive folder_id is not configured.');
        }

        $dateFolderId = $this->getOrCreateDateFolder($service, $folderId, date('Ymd'));

        $metadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$dateFolderId],
        ]);

        $content = file_get_contents($sourcePath);
        if ($content === false) {
            throw new RuntimeException('Could not read file for Google Drive upload: '.$sourcePath);
        }

        $mime = 'application/octet-stream';

        $service->files->create($metadata, [
            'data' => $content,
            'mimeType' => $mime,
            'uploadType' => 'multipart',
            'fields' => 'id',
        ]);
    }

    /**
     * @return array<int, array{id: string, name: string, createdTime: string}>
     */
    public function listBackups(string $prefix): array
    {
        $service = $this->driveService();
        $folderId = (string) config('filament-backup.google_drive.folder_id', '');

        $matched = $this->listBackupFilesInFolder($service, $folderId, $prefix);

        foreach ($this->listDateSubfolders($service, $folderId) as $subId) {
            $matched = array_merge($matched, $this->listBackupFilesInFolder($service, $subId, $prefix));
        }

        usort($matched, function (array $a, array $b): int {
            return strcmp($b['createdTime'], $a['createdTime']);
        });

        return $matched;
    }

    /**
     * @return array<int, array{id: string, name: string, createdTime: string}>
     */
    protected function listBackupFilesInFolder(Drive $service, string $parentId, string $prefix): array
    {
        $escaped = str_replace("'", "\\'", $parentId);
        $q = "'{$escaped}' in parents and trashed = false and mimeType != 'application/vnd.google-apps.folder'";

        $matched = [];
        $pageToken = null;

        do {
            $params = [
                'q' => $q,
                'fields' => 'nextPageToken, files(id, name, createdTime)',
                'pageSize' => 100,
            ];
            if ($pageToken !== null) {
                $params['pageToken'] = $pageToken;
            }

            $response = $service->files->listFiles($params);
            $files = $response->getFiles() ?? [];

            foreach ($files as $file) {
                $name = $file->getName();
                if (! str_starts_with((string) $name, $prefix.'-')) {
                    continue;
                }
                $matched[] = [
                    'id' => (string) $file->getId(),
                    'name' => (string) $name,
                    'createdTime' => (string) ($file->getCreatedTime() ?? ''),
                ];
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken !== null);

        return $matched;
    }

    /**
     * @return array<int, string> Folder IDs named YYYYMMDD under parent.
     */
    protected function listDateSubfolders(Drive $service, string $parentFolderId): array
    {
        $escaped = str_replace("'", "\\'", $parentFolderId);
        $q = "'{$escaped}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";

        $ids = [];
        $pageToken = null;

        do {
            $params = [
                'q' => $q,
                'fields' => 'nextPageToken, files(id, name)',
                'pageSize' => 100,
            ];
            if ($pageToken !== null) {
                $params['pageToken'] = $pageToken;
            }

            $response = $service->files->listFiles($params);
            $files = $response->getFiles() ?? [];

            foreach ($files as $file) {
                $name = (string) $file->getName();
                if (preg_match('/^\d{8}$/', $name) === 1) {
                    $ids[] = (string) $file->getId();
                }
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken !== null);

        return $ids;
    }

    protected function getOrCreateDateFolder(Drive $service, string $parentFolderId, string $dateYmd): string
    {
        if (preg_match('/^\d{8}$/', $dateYmd) !== 1) {
            throw new RuntimeException('Invalid backup date folder name.');
        }

        $escapedParent = str_replace("'", "\\'", $parentFolderId);
        $escapedName = str_replace("'", "\\'", $dateYmd);
        $q = "'{$escapedParent}' in parents and mimeType = 'application/vnd.google-apps.folder' and name = '{$escapedName}' and trashed = false";

        $response = $service->files->listFiles([
            'q' => $q,
            'fields' => 'files(id, name)',
            'pageSize' => 5,
        ]);

        $files = $response->getFiles() ?? [];
        if (count($files) > 0) {
            return (string) $files[0]->getId();
        }

        $folder = new DriveFile([
            'name' => $dateYmd,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentFolderId],
        ]);
        $created = $service->files->create($folder, ['fields' => 'id']);
        $id = $created->getId();
        if ($id === null || $id === '') {
            throw new RuntimeException('Could not create Google Drive date folder.');
        }

        return (string) $id;
    }

    public function delete(string $fileId): void
    {
        $this->driveService()->files->delete($fileId);
    }

    /**
     * Keep the newest $keepCount remote files for this prefix.
     */
    public function prune(string $prefix, int $keepCount): void
    {
        if ($keepCount < 1) {
            return;
        }

        $items = $this->listBackups($prefix);
        foreach (array_slice($items, $keepCount) as $row) {
            $this->delete($row['id']);
        }
    }

    protected function driveService(): Drive
    {
        $credentialsPath = (string) config('filament-backup.google_drive.credentials_json', '');
        if ($credentialsPath === '' || ! is_readable($credentialsPath)) {
            throw new RuntimeException('Google Drive credentials JSON path is missing or not readable.');
        }

        $client = new GoogleClient;
        $client->setAuthConfig($credentialsPath);
        $client->addScope(Drive::DRIVE_FILE);

        return new Drive($client);
    }
}
