<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Services\BunnyStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    public function __construct(
        private BunnyStorageService $bunny
    ) {}

    /**
     * POST /upload - General image upload (e.g. room cover).
     * Content-Type: multipart/form-data. Body: file (required).
     * Stores on BunnyCDN (same path as reels/posts) and returns the full CDN URL.
     */
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|image|max:10240', // 10MB
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $name = (string) Str::ulid() . '.' . $ext;
        $path = 'uploads/' . $name;

        try {
            $url = $this->bunny->uploadImage($file, $path);
        } catch (\Throwable $e) {
            return ApiResponse::error('UPLOAD_FAILED', 'Failed to store file on CDN: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success(['url' => $url]);
    }
}
