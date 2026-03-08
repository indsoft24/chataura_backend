<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Language;
use App\Services\ApiCacheService;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /**
     * GET /languages - List supported languages. Cached.
     */
    public function index(Request $request, ApiCacheService $cache)
    {
        $data = $cache->remember('languages', $cache->ttl('static'), function () {
            return Language::orderBy('sort_order')->orderBy('name')->get()->map(fn ($l) => [
                'code' => $l->code,
                'name' => $l->name,
                'native_name' => $l->native_name,
            ])->values()->all();
        });
        return ApiResponse::success($data);
    }
}
