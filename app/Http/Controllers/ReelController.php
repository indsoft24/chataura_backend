<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\MediaPost;
use App\Services\ApiCacheService;
use App\Services\BunnyStorageService;
use App\Services\VideoProcessorService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReelController extends Controller
{
    public function __construct(
        private BunnyStorageService $bunny,
        private VideoProcessorService $videoProcessor
    ) {}

    /**
     * POST /api/v1/reels/upload — video (required), caption (optional).
     * Accepts video under "video", "file", or "video_file". Compresses (30s max, 1080p, 2000k), thumbnail at 1s, uploads to Bunny CDN.
     */
    public function upload(Request $request, ApiCacheService $cache)
    {
        // Normalize: accept video under common field names (Android may send "file" or "video_file")
        $videoFile = $request->file('video') ?? $request->file('file') ?? $request->file('video_file');
        if ($videoFile) {
            $request->merge(['video' => $videoFile]);
            $request->files->set('video', $videoFile);
        }

        try {
            $validated = $request->validate([
                'video' => 'required|file|mimes:mp4,mov|max:102400', // 100MB
                'caption' => 'nullable|string|max:2200',
                'music_url' => 'nullable|string|max:500',
                'effect_name' => 'nullable|string|max:100',
                'duration' => 'nullable|integer|min:0',
                'aspect_ratio' => 'nullable|string|max:20',
                'is_camera_recorded' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $videoFile = $request->file('video');
        \Log::info('Reel video upload started', [
            'user_id' => $user->id,
            'uploaded_file_size_bytes' => $videoFile->getSize(),
            'uploaded_file_size_mb' => round($videoFile->getSize() / 1024 / 1024, 2),
        ]);

        $uuid = Str::uuid()->toString();
        $videoPath = "reels/videos/{$uuid}.mp4";
        $thumbPath = "reels/thumbs/{$uuid}.jpg";

        $compressedPath = null;
        $thumbFilePath = null;

        try {
            $compressedPath = $this->videoProcessor->compress($videoFile);
            $thumbFilePath = $this->videoProcessor->extractThumbnailFromFile($compressedPath);

            $videoUrl = $this->bunny->uploadVideo($compressedPath, $videoPath);
            $thumbUrl = $this->bunny->uploadImage($thumbFilePath, $thumbPath);
        } catch (\Throwable $e) {
            if ($compressedPath && file_exists($compressedPath)) {
                @unlink($compressedPath);
            }
            if ($thumbFilePath && file_exists($thumbFilePath)) {
                @unlink($thumbFilePath);
            }
            \Log::error('Reel upload failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return ApiResponse::error('UPLOAD_FAILED', 'Video processing or upload failed. Ensure FFmpeg is installed.', 500);
        } finally {
            if ($compressedPath && file_exists($compressedPath)) {
                @unlink($compressedPath);
            }
            if ($thumbFilePath && file_exists($thumbFilePath)) {
                @unlink($thumbFilePath);
            }
        }

        $media = MediaPost::create([
            'user_id' => $user->id,
            'type' => MediaPost::TYPE_REEL,
            'media_type' => MediaPost::MEDIA_TYPE_VIDEO,
            'file_url' => $videoUrl,
            'thumbnail_url' => $thumbUrl,
            'caption' => $validated['caption'] ?? null,
            'music_url' => $validated['music_url'] ?? null,
            'effect_name' => $validated['effect_name'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'aspect_ratio' => $validated['aspect_ratio'] ?? null,
            'is_camera_recorded' => (bool) ($validated['is_camera_recorded'] ?? false),
        ]);

        $cache->bumpVersion('reels');
        $cache->bumpVersion('music');

        return ApiResponse::success([
            'id' => $media->id,
            'file_url' => $media->file_url,
            'thumbnail_url' => $media->thumbnail_url,
            'caption' => $media->caption,
            'music_url' => $media->music_url,
            'effect_name' => $media->effect_name,
            'duration' => $media->duration,
            'aspect_ratio' => $media->aspect_ratio,
            'is_camera_recorded' => (bool) $media->is_camera_recorded,
        ]);
    }
}
