<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FeedbackController extends Controller
{
    /**
     * POST /feedback - Body: message, optional screenshot (URL or base64).
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'message' => 'required|string|max:5000',
                'screenshot' => 'nullable|string|max:10000',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        Feedback::create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'screenshot_url' => $validated['screenshot'] ?? null,
        ]);
        return ApiResponse::success(['message' => 'Feedback submitted']);
    }
}
