<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Upload and delete files on Bunny CDN Storage.
 * Streams uploads to avoid loading large files into memory.
 */
class BunnyStorageService
{
    private string $storageZone;
    private string $apiKey;
    private string $baseUrl;
    private string $cdnUrl;
    private Client $client;

    public function __construct()
    {
        $this->storageZone = config('bunny.storage_zone');
        $this->apiKey = config('bunny.storage_api_key');
        $region = config('bunny.storage_region');
        $host = $region
            ? "{$region}.storage.bunnycdn.com"
            : 'storage.bunnycdn.com';
        $this->baseUrl = "https://{$host}/{$this->storageZone}";
        $this->cdnUrl = config('bunny.cdn_url');
        $this->client = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Upload a video file (streamed) to Bunny Storage. Returns CDN URL.
     */
    public function uploadVideo(UploadedFile|string $file, string $path): string
    {
        return $this->uploadFile($file, $path, 'video/mp4');
    }

    /**
     * Upload an image file (streamed) to Bunny Storage. Returns CDN URL.
     */
    public function uploadImage(UploadedFile|string $file, string $path): string
    {
        $mime = $file instanceof UploadedFile
            ? $file->getMimeType()
            : 'image/jpeg';
        return $this->uploadFile($file, $path, $mime);
    }

    /**
     * Upload file (stream body) to Bunny. Path is relative to storage zone (e.g. reels/videos/uuid.mp4).
     * Returns the CDN URL for the stored file (never a local/temp path).
     */
    public function uploadFile(UploadedFile|string $file, string $path, string $contentType = 'application/octet-stream'): string
    {
        $storagePath = ltrim(str_replace('\\', '/', $path), '/');
        $url = $this->baseUrl . '/' . $storagePath;

        $localPath = $file instanceof UploadedFile
            ? $file->getRealPath()
            : $file;
        $stream = fopen($localPath, 'r');
        if (!$stream || !is_resource($stream)) {
            throw new \InvalidArgumentException('Invalid file or path for upload');
        }

        $body = Utils::streamFor($stream);

        try {
            $this->client->request('PUT', $url, [
                'headers' => [
                    'AccessKey' => $this->apiKey,
                    'Content-Type' => $contentType,
                ],
                'body' => $body,
            ]);
        } catch (GuzzleException $e) {
            Log::error('Bunny upload failed', ['path' => $storagePath, 'message' => $e->getMessage()]);
            throw new \RuntimeException('Upload to storage failed: ' . $e->getMessage(), 0, $e);
        }

        return $this->cdnUrl($storagePath);
    }

    /**
     * Delete a file from Bunny Storage. Path is relative to storage zone.
     */
    public function deleteFile(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $url = $this->baseUrl . '/' . $path;

        try {
            $response = $this->client->request('DELETE', $url, [
                'headers' => [
                    'AccessKey' => $this->apiKey,
                ],
            ]);
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                return true; // already gone
            }
            Log::warning('Bunny delete failed', ['path' => $path, 'message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Build CDN URL for a storage path (without uploading).
     */
    public function cdnUrl(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        return $this->cdnUrl . '/' . $path;
    }
}
