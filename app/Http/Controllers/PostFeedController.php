<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\MediaPost;
use App\Models\PostLike;
use App\Models\PostSave;
use App\Services\ApiCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PostFeedController extends Controller
{
    /**
     * GET /api/v1/posts/feed — newest posts, 10 per page, infinite scroll.
     * Each item includes is_liked and is_saved for the authenticated user.
     */
    public function feed(Request $request, ApiCacheService $cache): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('limit', 10)), 50);
        $viewerId = $request->user()?->id ?? 0;
        $ttl = $cache->ttl('feed');
        $cacheKey = $cache->versionedKey('feed', [
            'viewer' => $viewerId,
            'page' => $page,
            'limit' => $perPage,
        ]);

        $payload = $cache->remember($cacheKey, $ttl, function () use ($request, $perPage, $page) {
            $paginator = MediaPost::posts()
                ->select(MediaPost::FEED_SELECT)
                ->with('user:id,display_name,name,avatar_url')
                ->latest()
                ->paginate($perPage, ['*'], 'page', $page);

            $collection = $paginator->getCollection();
            $postIds = $collection->pluck('id')->all();
            [$likedIds, $savedIds] = $this->viewerLikeAndSaveIds($request, $postIds);

            $data = $collection->map(fn (MediaPost $item) => $this->feedItem($item, $likedIds, $savedIds));
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

        return $cache->applyHttpCacheHeaders($request, response()->json($payload), $ttl, $viewerId ? 'private' : 'public');
    }

    /**
     * Post IDs the current user has liked and saved (for feed items).
     */
    private function viewerLikeAndSaveIds(Request $request, array $postIds): array
    {
        if (empty($postIds)) {
            return [[], []];
        }
        $userId = $request->user()?->id;
        if (!$userId) {
            return [[], []];
        }
        $likedIds = PostLike::where('user_id', $userId)->whereIn('media_post_id', $postIds)->pluck('media_post_id')->flip()->all();
        $savedIds = PostSave::where('user_id', $userId)->whereIn('media_post_id', $postIds)->pluck('media_post_id')->flip()->all();
        return [$likedIds, $savedIds];
    }

    /**
     * Single item for feed (clean format; CDN URLs already in DB). Includes is_liked and is_saved.
     */
    private function feedItem(MediaPost $item, array $likedIds = [], array $savedIds = []): array
    {
        $row = $item->toFeedItem();

        // Backward-compatible aliases expected by some clients
        $row['media_url'] = $item->file_url;
        $row['likes_count'] = (int) $item->likes;
        $row['comments_count'] = (int) $item->comments;

        $row['isLiked'] = isset($likedIds[$item->id]);
        $row['isSaved'] = isset($savedIds[$item->id]);
        $row['is_liked'] = $row['isLiked'];
        $row['is_saved'] = $row['isSaved'];

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
