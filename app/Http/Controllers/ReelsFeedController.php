<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\MediaPost;
use App\Models\PostLike;
use App\Models\PostSave;
use App\Models\UserFollower;
use App\Services\ApiCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReelsFeedController extends Controller
{
    /**
     * GET /api/v1/reels/feed — newest reels, 10 per page, infinite scroll.
     * Each item includes isLiked, isSaved (for authenticated user), likes, comments, user (name, avatar).
     */
    public function feed(Request $request, ApiCacheService $cache): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('limit', 10)), 50);
        $viewerId = $request->user()?->id ?? 0;
        $ttl = $cache->ttl('reels');
        $cacheKey = $cache->versionedKey('reels', [
            'viewer' => $viewerId,
            'page' => $page,
            'limit' => $perPage,
        ]);

        $payload = $cache->remember($cacheKey, $ttl, function () use ($request, $perPage, $page) {
            $paginator = MediaPost::reels()
                ->select(MediaPost::FEED_SELECT)
                ->with('user:id,display_name,name,avatar_url')
                ->latest()
                ->paginate($perPage, ['*'], 'page', $page);

            $collection = $paginator->getCollection();
            $postIds = $collection->pluck('id')->all();
            $authorIds = $collection->pluck('user_id')->unique()->filter()->values()->all();
            [$likedIds, $savedIds] = $this->viewerLikeAndSaveIds($request, $postIds);
            $followingIds = $this->viewerFollowingIds($request, $authorIds);
            $data = $collection->map(fn (MediaPost $item) => $this->feedItem($item, $likedIds, $savedIds, $followingIds));
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

        return $cache->applyHttpCacheHeaders($request, response()->json($payload), $ttl, 'private');
    }

    /**
     * GET /api/v1/reels/trending — reels sorted by engagement (likes + comments).
     */
    public function trending(Request $request, ApiCacheService $cache): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('limit', 10)), 50);
        $viewerId = $request->user()?->id ?? 0;
        $ttl = $cache->ttl('reels');
        $cacheKey = $cache->versionedKey('reels', [
            'variant' => 'trending',
            'viewer' => $viewerId,
            'page' => $page,
            'limit' => $perPage,
        ]);

        $payload = $cache->remember($cacheKey, $ttl, function () use ($request, $perPage, $page) {
            $paginator = MediaPost::reels()
                ->select(MediaPost::FEED_SELECT)
                ->with('user:id,display_name,name,avatar_url')
                ->trending()
                ->paginate($perPage, ['*'], 'page', $page);

            $collection = $paginator->getCollection();
            $postIds = $collection->pluck('id')->all();
            $authorIds = $collection->pluck('user_id')->unique()->filter()->values()->all();
            [$likedIds, $savedIds] = $this->viewerLikeAndSaveIds($request, $postIds);
            $followingIds = $this->viewerFollowingIds($request, $authorIds);
            $data = $collection->map(fn (MediaPost $item) => $this->feedItem($item, $likedIds, $savedIds, $followingIds));
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

        return $cache->applyHttpCacheHeaders($request, response()->json($payload), $ttl, 'private');
    }

    /**
     * GET /api/v1/reels/discover — random reels for exploration (20 items).
     */
    public function discover(Request $request, ApiCacheService $cache): JsonResponse
    {
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $viewerId = $request->user()?->id ?? 0;
        $ttl = $cache->ttl('reels');
        $cacheKey = $cache->versionedKey('reels', [
            'variant' => 'discover',
            'viewer' => $viewerId,
            'limit' => $limit,
        ]);

        $payload = $cache->remember($cacheKey, $ttl, function () use ($request, $limit) {
            $items = MediaPost::reels()
                ->select(MediaPost::FEED_SELECT)
                ->with('user:id,display_name,name,avatar_url')
                ->inRandomOrder()
                ->limit($limit)
                ->get();

            $postIds = $items->pluck('id')->all();
            $authorIds = $items->pluck('user_id')->unique()->filter()->values()->all();
            [$likedIds, $savedIds] = $this->viewerLikeAndSaveIds($request, $postIds);
            $followingIds = $this->viewerFollowingIds($request, $authorIds);
            $data = $items->map(fn (MediaPost $item) => $this->feedItem($item, $likedIds, $savedIds, $followingIds))->values()->all();

            return [
                'success' => true,
                'data' => $data,
            ];
        });

        return $cache->applyHttpCacheHeaders($request, response()->json($payload), $ttl, 'private');
    }

    /**
     * Get sets of post IDs the current user has liked and saved (for feed items).
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
     * User IDs the current user follows (accepted). Used for isFollowing on feed authors.
     */
    private function viewerFollowingIds(Request $request, array $authorIds): array
    {
        if (empty($authorIds)) {
            return [];
        }
        $viewerId = $request->user()?->id;
        if (!$viewerId) {
            return [];
        }
        return UserFollower::where('follower_id', $viewerId)
            ->whereIn('following_id', $authorIds)
            ->where('status', UserFollower::STATUS_ACCEPTED)
            ->pluck('following_id')
            ->flip()
            ->all();
    }

    /**
     * Single item for feed: isLiked, isSaved, likes, comments, user (id, name, avatar, isFollowing).
     */
    private function feedItem(MediaPost $item, array $likedIds = [], array $savedIds = [], array $followingIds = []): array
    {
        $row = $item->toFeedItem();
        $row['isLiked'] = isset($likedIds[$item->id]);
        $row['isSaved'] = isset($savedIds[$item->id]);
        $row['is_liked'] = $row['isLiked'];
        $row['is_saved'] = $row['isSaved'];
        $row['likes'] = (int) $item->likes;
        $row['comments'] = (int) $item->comments;
        if ($item->relationLoaded('user') && $item->user) {
            $row['user'] = [
                'id' => $item->user->id,
                'name' => $item->user->display_name ?? $item->user->name ?? 'User',
                'avatar' => $item->user->avatar_url ?? '',
                'isFollowing' => isset($followingIds[$item->user->id]),
                'display_name' => $item->user->display_name ?? $item->user->name ?? 'User',
                'avatar_url' => $item->user->avatar_url ?? '',
            ];
        }
        return $row;
    }

}
