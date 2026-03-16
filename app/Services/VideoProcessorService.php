<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * FFmpeg video compression and thumbnail extraction.
 * Requires FFmpeg on the server (e.g. apt install ffmpeg).
 */
class VideoProcessorService
{
    private const MAX_DURATION_SEC = 30;
    private const MAX_HEIGHT = 1080;
    private const VIDEO_BITRATE = '2000k';
    private const CODEC = 'libx264';
    private const THUMBNAIL_OFFSET_SEC = 1;

    /**
     * Compress video: max 30s, 1080p, 2000k bitrate, mp4 h264.
     * Returns path to the compressed temp file (caller must delete).
     */
    public function compress(UploadedFile $file): string
    {
        $input = $file->getRealPath();
        $output = $this->tempPath('mp4');

        $args = [
            '-y',
            '-i', $input,
            '-t', (string) self::MAX_DURATION_SEC,
            '-vf', 'scale=-2:' . min(self::MAX_HEIGHT, 1080) . ':force_original_aspect_ratio=decrease',
            '-c:v', self::CODEC,
            '-b:v', self::VIDEO_BITRATE,
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            $output,
        ];

        $this->runFfmpeg($args);
        return $output;
    }

    /**
     * Extract a thumbnail at 1 second as JPG. Returns path to temp file (caller must delete).
     */
    public function extractThumbnail(UploadedFile|string $inputPath): string
    {
        $input = $inputPath instanceof UploadedFile ? $inputPath->getRealPath() : $inputPath;
        $output = $this->tempPath('jpg');

        $args = [
            '-y',
            '-i', $input,
            '-ss', (string) self::THUMBNAIL_OFFSET_SEC,
            '-vframes', '1',
            '-q:v', '2',
            $output,
        ];

        $this->runFfmpeg($args);
        return $output;
    }

    /**
     * Extract thumbnail from an already compressed file (path).
     */
    public function extractThumbnailFromFile(string $videoPath): string
    {
        return $this->extractThumbnail($videoPath);
    }

    private function runFfmpeg(array $args): void
    {
        $process = new Process(array_merge(['ffmpeg'], $args));
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('FFmpeg failed', [
                'command' => $process->getCommandLine(),
                'output' => $process->getErrorOutput(),
            ]);
            throw new \RuntimeException('Video processing failed: ' . $process->getErrorOutput());
        }
    }

    private function tempPath(string $ext): string
    {
        $dir = storage_path('app/temp');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        return $dir . '/' . uniqid('vid_', true) . '.' . $ext;
    }
}
