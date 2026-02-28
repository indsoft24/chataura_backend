<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * GET /countries - List supported countries (id, name, flag_url, flag_emoji).
     * Order: India (IN) first, then rest alphabetically by name.
     */
    public function index(Request $request)
    {
        $countries = Country::query()
            ->orderByRaw("CASE WHEN id = 'IN' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->map(fn (Country $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'flag_url' => $c->flag_url,
            'flag_emoji' => $c->flag_emoji,
        ]);

        return ApiResponse::success($countries->values()->all());
    }
}
