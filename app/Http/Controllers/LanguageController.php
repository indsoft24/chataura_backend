<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Language;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /**
     * GET /languages - List supported languages (LanguageDto shape).
     */
    public function index(Request $request)
    {
        $items = Language::orderBy('sort_order')->orderBy('name')->get()->map(fn ($l) => [
            'code' => $l->code,
            'name' => $l->name,
            'native_name' => $l->native_name,
        ]);
        return ApiResponse::success($items->values()->all());
    }
}
