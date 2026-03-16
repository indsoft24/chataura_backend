<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\FaqItem;
use App\Services\ApiCacheService;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * GET /faq - List FAQ items. Cached.
     */
    public function index(Request $request, ApiCacheService $cache)
    {
        $ttl = $cache->ttl('static');
        $data = $cache->remember($cache->versionedKey('faq'), $ttl, function () {
            return FaqItem::orderBy('sort_order')->orderBy('id')->get()->map(fn ($f) => [
                'id' => $f->id,
                'question' => $f->question,
                'answer' => $f->answer,
            ])->values()->all();
        });
        return $cache->applyHttpCacheHeaders($request, ApiResponse::success($data), $ttl, 'public');
    }
}
