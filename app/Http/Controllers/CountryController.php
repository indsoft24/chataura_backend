<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Country;
use App\Services\ApiCacheService;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * GET /countries - List supported countries (id, name, flag_url, flag_emoji). Cached.
     */
    public function index(Request $request, ApiCacheService $cache)
    {
        $data = $cache->remember('countries', $cache->ttl('static'), function () {
            return Country::query()
                ->orderByRaw("CASE WHEN id = 'IN' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get()
                ->map(fn (Country $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'flag_url' => $c->flag_url,
                    'flag_emoji' => $c->flag_emoji,
                ])
                ->values()
                ->all();
        });

        return ApiResponse::success($data);
    }
}
