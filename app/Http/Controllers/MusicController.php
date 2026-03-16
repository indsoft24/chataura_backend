<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\MusicTrack;
use App\Models\MediaPost;
use App\Services\ApiCacheService;
use Illuminate\Http\Request;

class MusicController extends Controller
{
    /**
     * GET /api/v1/music/library
     * Return full music library (cached for 1 hour).
     */
    public function library(Request $request, ApiCacheService $cache)
    {
        $ttl = $cache->ttl('catalog');
        $tracks = $cache->remember($cache->versionedKey('music', ['variant' => 'library']), $ttl, function () {
            return MusicTrack::query()
                ->orderBy('title')
                ->get(['id', 'title', 'artist', 'file_url']);
        });

        return $cache->applyHttpCacheHeaders($request, ApiResponse::success($tracks), $ttl, 'public');
    }

    /**
     * GET /api/v1/music/trending
     * Return most used tracks based on media_posts.music_url.
     */
    public function trending(Request $request, ApiCacheService $cache)
    {
        $ttl = $cache->ttl('catalog');
        $tracks = $cache->remember($cache->versionedKey('music', ['variant' => 'trending']), $ttl, function () {
            return MusicTrack::query()
                ->select('music_tracks.id', 'music_tracks.title', 'music_tracks.artist', 'music_tracks.file_url')
                ->leftJoin('media_posts', 'media_posts.music_url', '=', 'music_tracks.file_url')
                ->groupBy('music_tracks.id', 'music_tracks.title', 'music_tracks.artist', 'music_tracks.file_url')
                ->orderByRaw('COUNT(media_posts.id) DESC')
                ->limit(50)
                ->get();
        });

        return $cache->applyHttpCacheHeaders($request, ApiResponse::success($tracks), $ttl, 'public');
    }
}

