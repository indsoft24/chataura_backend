<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\MediaPost;
use App\Services\ApiCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PostFeedController extends Controller
{
    /**
     * GET /api/v1/posts/feed — newest posts, 10 per page, infinite scroll.
     */
    public function feed(Request $request, ApiCacheService $cache): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('limit', 10)), 50);
        $ttl = $cache->ttl('feed');
        $cacheKey = $cache->versionedKey('feed', [
            'page' => $page,
            'limit' => $perPage,
        ]);

        $payload = $cache->remember($cacheKey, $ttl, function () use ($perPage, $page) {
            $paginator = MediaPost::posts()
                ->select(MediaPost::FEED_SELECT)
                ->with('user:id,display_name,name,avatar_url')
                ->latest()
                ->paginate($perPage, ['*'], 'page', $page);

            $data = $paginator->getCollection()->map(fn (MediaPost $item) => $this->feedItem($item));
            $paginator->setCollection($data);

            return [
                'data' => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'next_page_url' => $paginator->hasMorePages() ? $paginator->nextPageUrl() : null,
                'has_more' => $paginator->hasMorePages(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ];
        });

        return $cache->applyHttpCacheHeaders($request, response()->json($payload), $ttl, 'public');
    }

    /**
     * Single item for feed (clean format; CDN URLs already in DB).
     */
    private function feedItem(MediaPost $item): array
    {
        $row = $item->toFeedItem();

        // Backward-compatible aliases expected by some clients
        $row['media_url'] = $item->file_url;
        $row['likes_count'] = (int) $item->likes;
        $row['comments_count'] = (int) $item->comments;

        if ($item->relationLoaded('user') && $item->user) {
            $displayName = $item->user->display_name ?? $item->user->name ?? 'User';
            $avatarUrl = $item->user->avatar_url;

            // New unified user object used by mobile clients
            $row['user'] = [
                'id' => $item->user->id,
                'name' => $displayName,
                'avatar' => $avatarUrl,
                // Legacy fields kept for older frontend code paths
                'display_name' => $displayName,
                'avatar_url' => $avatarUrl,
            ];
        }

        return $row;
    }

    // -------------------------------------------------------------------------
    // Placeholders for future: like, comment, save, follow
    // -------------------------------------------------------------------------
    // public function like(Request $request, int $id): JsonResponse { ... }
    // public function unlike(Request $request, int $id): JsonResponse { ... }
    // public function comments(Request $request, int $id): JsonResponse { ... }
    // public function addComment(Request $request, int $id): JsonResponse { ... }
    // public function save(Request $request, int $id): JsonResponse { ... }
    // public function unsave(Request $request, int $id): JsonResponse { ... }
}
