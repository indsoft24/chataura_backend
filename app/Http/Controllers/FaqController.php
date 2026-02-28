<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\FaqItem;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * GET /faq - List FAQ items (FaqItemDto shape).
     */
    public function index(Request $request)
    {
        $items = FaqItem::orderBy('sort_order')->orderBy('id')->get()->map(fn ($f) => [
            'id' => $f->id,
            'question' => $f->question,
            'answer' => $f->answer,
        ]);
        return ApiResponse::success($items->values()->all());
    }
}
