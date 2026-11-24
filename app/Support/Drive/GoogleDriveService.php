<?php

declare(strict_types=1);

namespace App\Support\Drive;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleDriveService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const FILES_URL = 'https://www.googleapis.com/drive/v3/files';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';

    private const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/drive';

    public function isConfigured(): bool
    {
        return $this->resolveCredentials() !== null && $this->rootFolderId() !== null;
    }

    public function serviceAccountEmail(): ?string
    {
        $credentials = $this->resolveCredentials();

        return is_string($credentials['client_email'] ?? null)
            ? $credentials['client_email']
            : null;
    }

    public function rootFolderId(): ?string
    {
        $folderId = config('services.google_drive.root_folder_id');

        return is_string($folderId) && trim($folderId) !== '' ? trim($folderId) : null;
    }

    /**
     * Mengunggah berkas pekerjaan harian ke folder terstruktur di Google Drive PT.
     *
     * @param  list<string>  $folderSegments
     * @return array{id: string, name: string, webViewLink: string, folderPath: string, mimeType: string, size: int}
     */
    public function uploadWorkFile(UploadedFile $file, array $folderSegments, string $customName): array
    {
        $credentials = $this->resolveCredentials();
        $rootFolderId = $this->rootFolderId();

        if ($credentials === null || $rootFolderId === null) {
            throw new RuntimeException(
                'Integrasi Google Drive PT belum dikonfigurasi. Lengkapi GOOGLE_DRIVE_ROOT_FOLDER_ID dan kredensial Service Account di .env terlebih dahulu.'
            );
        }

        $accessToken = $this->fetchAccessToken($credentials);
        $parentId = $rootFolderId;
        $cleanSegments = array_values(array_filter(array_map('trim', $folderSegments), static fn (string $s): bool => $s !== ''));

        foreach ($cleanSegments as $segment) {
            $parentId = $this->findOrCreateFolder($accessToken, $parentId, $segment);
        }

        $extension = $file->getClientOriginalExtension();
        $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '-', $customName) ?: 'laporan-kerja';
        $fileName = trim($safeBase, '-').($extension !== '' ? ".{$extension}" : '');
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $binaryContent = file_get_contents($file->getRealPath());

        if ($binaryContent === false) {
            throw new RuntimeException('Gagal membaca konten berkas yang diunggah.');
        }

        $metadataJson = json_encode([
            'name' => $fileName,
            'parents' => [$parentId],
        ], JSON_THROW_ON_ERROR);

        $boundary = 'veloce-drive-'.bin2hex(random_bytes(8));
        $multipartBody = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$metadataJson."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: {$mimeType}\r\n\r\n"
            .$binaryContent."\r\n"
            ."--{$boundary}--";

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->withBody($multipartBody, "multipart/related; boundary={$boundary}")
            ->post(self::UPLOAD_URL.'?uploadType=multipart&supportsAllDrives=true&fields=id,name,webViewLink,mimeType,size');

        if (! $response->successful()) {
            throw new RuntimeException('Gagal mengunggah berkas ke Google Drive PT: '.$response->body());
        }

        /** @var array<string, mixed> $uploaded */
        $uploaded = $response->json() ?? [];
        $fileId = (string) ($uploaded['id'] ?? '');

        if ($fileId === '') {
            throw new RuntimeException('Respons Google Drive tidak menyertakan ID berkas.');
        }

        return [
            'id' => $fileId,
            'name' => (string) ($uploaded['name'] ?? $fileName),
            'webViewLink' => (string) ($uploaded['webViewLink'] ?? "https://drive.google.com/file/d/{$fileId}/view"),
            'folderPath' => implode(' / ', $cleanSegments),
            'mimeType' => (string) ($uploaded['mimeType'] ?? $mimeType),
            'size' => (int) ($uploaded['size'] ?? $file->getSize()),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function fetchAccessToken(array $credentials): string
    {
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claimSet = $this->base64UrlEncode(json_encode([
            'iss' => (string) $credentials['client_email'],
            'scope' => self::DRIVE_SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $signingInput = "{$header}.{$claimSet}";
        $signature = '';
        $signed = openssl_sign($signingInput, $signature, (string) $credentials['private_key'], OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Gagal menandatangani token autentikasi Google Service Account.');
        }

        $jwt = $signingInput.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->timeout(15)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw new RuntimeException('Autentikasi ke Google Drive API gagal: '.$response->body());
        }

        return (string) $response->json('access_token');
    }

    private function findOrCreateFolder(string $accessToken, string $parentId, string $folderName): string
    {
        $escapedName = str_replace("'", "\\'", $folderName);
        $query = "name = '{$escapedName}' and '{$parentId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";

        $listResponse = Http::withToken($accessToken)
            ->timeout(15)
            ->get(self::FILES_URL, [
                'q' => $query,
                'fields' => 'files(id,name)',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'pageSize' => 1,
            ]);

        if ($listResponse->successful()) {
            $existingId = $listResponse->json('files.0.id');
            if (is_string($existingId) && $existingId !== '') {
                return $existingId;
            }
        }

        $createResponse = Http::withToken($accessToken)
            ->timeout(15)
            ->post(self::FILES_URL.'?supportsAllDrives=true&fields=id', [
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentId],
            ]);

        if (! $createResponse->successful() || ! is_string($createResponse->json('id'))) {
            throw new RuntimeException("Gagal menyiapkan folder '{$folderName}' di Google Drive PT.");
        }

        return (string) $createResponse->json('id');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCredentials(): ?array
    {
        $rawJson = config('services.google_drive.credentials_json');
        $jsonString = is_string($rawJson) && trim($rawJson) !== '' ? $rawJson : null;

        if ($jsonString === null) {
            $path = config('services.google_drive.credentials_path');
            if (is_string($path) && $path !== '') {
                $fullPath = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
                    ? $path
                    : base_path($path);

                if (is_file($fullPath)) {
                    $fileContents = file_get_contents($fullPath);
                    if (is_string($fileContents) && trim($fileContents) !== '') {
                        $jsonString = $fileContents;
                    }
                }
            }
        }

        if ($jsonString === null) {
            return null;
        }

        $decoded = json_decode($jsonString, true);
        if (! is_array($decoded)
            || empty($decoded['client_email'])
            || empty($decoded['private_key'])) {
            return null;
        }

        return $decoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
