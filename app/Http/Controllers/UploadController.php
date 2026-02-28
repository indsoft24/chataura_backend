<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    /**
     * POST /upload - General image upload (e.g. room cover).
     * Content-Type: multipart/form-data. Body: file (required).
     * Returns { "url": "https://..." } for use in cover_image_url etc.
     */
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|image|max:10240', // 10MB for room covers
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $path = $request->file('file')->store('uploads', 'public');
        $url = rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/');

        return ApiResponse::success(['url' => $url]);
    }
}
