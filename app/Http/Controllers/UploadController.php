<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    /**
     * POST /upload - General image upload (e.g. room cover).
     * Content-Type: multipart/form-data. Body: file (required).
     * Saves to storage/app/public/uploads (persistent). Returns public URL for cover_image_url.
     * Ensure php artisan storage:link has been run so /storage/* is publicly accessible.
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
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $name = Str::ulid() . '.' . strtolower($ext);
        $path = $file->storeAs('uploads', $name, 'public');

        $baseUrl = rtrim(config('app.url'), '/');
        $url = $baseUrl . '/storage/' . ltrim($path, '/');

        return ApiResponse::success(['url' => $url]);
    }
}
