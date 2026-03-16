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

class PostMediaController extends Controller
{
    public function __construct(
        private BunnyStorageService $bunny,
        private VideoProcessorService $videoProcessor
    ) {}

    /**
     * POST /api/v1/posts/upload — image or video (required), caption (optional).
     * Images: 5MB max, jpg/png. Videos: 100MB max, mp4/mov; compressed then uploaded.
     */
    public function upload(Request $request, ApiCacheService $cache)
    {
        $request->validate([
            'caption' => 'nullable|string|max:2200',
        ]);

        $imageFile = $request->file('image');
        $videoFile = $request->file('video');
        $genericFile = $request->file('file');

        if ($genericFile && !$imageFile && !$videoFile) {
            $mime = $genericFile->getMimeType();
            $ext = strtolower($genericFile->getClientOriginalExtension() ?? '');
            if (str_starts_with($mime, 'image/')) {
                $imageFile = $genericFile;
            } elseif (in_array($ext, ['mp4', 'mov'], true)) {
                $videoFile = $genericFile;
            }
        }

        if (!$imageFile && !$videoFile) {
            return ApiResponse::validationError('Validation failed', [
                'image' => ['Either image, video, or file is required.'],
            ]);
        }
        if ($imageFile && $videoFile) {
            return ApiResponse::validationError('Validation failed', [
                'image' => ['Send either image or video, not both.'],
            ]);
        }

        if ($imageFile) {
            $maxImageSize = 5120; // 5MB
            if (!$imageFile->isValid()) {
                return ApiResponse::validationError('Validation failed', ['file' => ['The uploaded file is invalid.']]);
            }
            if (!str_starts_with($imageFile->getMimeType(), 'image/')) {
                return ApiResponse::validationError('Validation failed', ['file' => ['The file must be an image (jpg or png).']]);
            }
            if (!in_array(strtolower($imageFile->getClientOriginalExtension() ?? ''), ['jpg', 'jpeg', 'png'], true)) {
                return ApiResponse::validationError('Validation failed', ['file' => ['The file must be a jpg or png image.']]);
            }
            if ($imageFile->getSize() > $maxImageSize * 1024) {
                return ApiResponse::validationError('Validation failed', ['file' => ['The image must not exceed 5MB.']]);
            }
        } else {
            $maxVideoSize = 102400; // 100MB
            if (!$videoFile->isValid()) {
                return ApiResponse::validationError('Validation failed', ['file' => ['The uploaded file is invalid.']]);
            }
            if (!in_array(strtolower($videoFile->getClientOriginalExtension() ?? ''), ['mp4', 'mov'], true)) {
                return ApiResponse::validationError('Validation failed', ['file' => ['The file must be a video (mp4 or mov).']]);
            }
            if ($videoFile->getSize() > $maxVideoSize * 1024) {
                return ApiResponse::validationError('Validation failed', ['file' => ['The video must not exceed 100MB.']]);
            }
        }

        $validated = $request->only(['caption']);
        $user = $request->user();
        $uuid = Str::uuid()->toString();

        if ($imageFile) {
            $path = "posts/images/{$uuid}.jpg";
            $ext = strtolower($imageFile->getClientOriginalExtension() ?: 'jpg');
            if ($ext === 'png') {
                $path = "posts/images/{$uuid}.png";
            }
            try {
                $fileUrl = $this->bunny->uploadImage($imageFile, $path);
            } catch (\Throwable $e) {
                \Log::error('Post image upload failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                return ApiResponse::error('UPLOAD_FAILED', 'Image upload failed.', 500);
            }

            $media = MediaPost::create([
                'user_id' => $user->id,
                'type' => MediaPost::TYPE_POST,
                'media_type' => MediaPost::MEDIA_TYPE_IMAGE,
                'file_url' => $fileUrl,
                'thumbnail_url' => null,
                'caption' => $validated['caption'] ?? null,
            ]);

            $cache->bumpVersion('feed');

            return ApiResponse::success([
                'id' => $media->id,
                'file_url' => $media->file_url,
                'thumbnail_url' => $media->thumbnail_url,
                'media_type' => 'image',
                'caption' => $media->caption,
            ]);
        }

        // Video post
        \Log::info('Post video upload started', [
            'user_id' => $user->id,
            'uploaded_file_size_bytes' => $videoFile->getSize(),
            'uploaded_file_size_mb' => round($videoFile->getSize() / 1024 / 1024, 2),
        ]);

        $videoPath = "posts/videos/{$uuid}.mp4";
        $thumbPath = "posts/thumbs/{$uuid}.jpg";
        $compressedPath = null;
        $thumbFilePath = null;

        try {
            $compressedPath = $this->videoProcessor->compress($videoFile);
            $thumbFilePath = $this->videoProcessor->extractThumbnailFromFile($compressedPath);

            $fileUrl = $this->bunny->uploadVideo($compressedPath, $videoPath);
            $thumbUrl = $this->bunny->uploadImage($thumbFilePath, $thumbPath);
        } catch (\Throwable $e) {
            if ($compressedPath && file_exists($compressedPath)) {
                @unlink($compressedPath);
            }
            if ($thumbFilePath && file_exists($thumbFilePath)) {
                @unlink($thumbFilePath);
            }
            \Log::error('Post video upload failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
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
            'type' => MediaPost::TYPE_POST,
            'media_type' => MediaPost::MEDIA_TYPE_VIDEO,
            'file_url' => $fileUrl,
            'thumbnail_url' => $thumbUrl,
            'caption' => $validated['caption'] ?? null,
        ]);

        $cache->bumpVersion('feed');

        return ApiResponse::success([
            'id' => $media->id,
            'file_url' => $media->file_url,
            'thumbnail_url' => $media->thumbnail_url,
            'media_type' => 'video',
            'caption' => $media->caption,
        ]);
    }
}
