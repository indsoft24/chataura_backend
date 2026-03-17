<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\MediaPost;
use App\Models\PostLike;
use App\Models\PostSave;
use App\Models\UserFollower;
use App\Services\ApiCacheService;
use App\Services\BunnyStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserMediaController extends Controller
{
    public function __construct(
        private BunnyStorageService $bunny
    ) {}

    /**
     * GET /api/v1/me/posts - Posts created by the authenticated user.
     */
    public function myPosts(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::unauthorized();
        }

        $limit = min(max((int) $request->query('limit', 20), 1), 50);
        $page = max(1, (int) $request->query('page', 1));

        $paginator = MediaPost::posts()
            ->where('user_id', $user->id)
            ->select(MediaPost::FEED_SELECT)
            ->latest()
            ->paginate($limit, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(function (MediaPost $item) {
            return [
                'id' => $item->id,
                'media_url' => $item->file_url,
                'thumbnail_url' => $item->thumbnail_url,
                'caption' => $item->caption ?? '',
                'likes_count' => (int) $item->likes,
                'comments_count' => (int) $item->comments,
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        })->values()->all();

        $meta = ApiResponse::paginationMeta($paginator->total(), $paginator->currentPage(), $paginator->perPage());

        return ApiResponse::success($items, $meta);
    }

    /**
     * GET /api/v1/me/reels - Reels created by the authenticated user.
     */
    public function myReels(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::unauthorized();
        }

        $limit = min(max((int) $request->query('limit', 20), 1), 50);
        $page = max(1, (int) $request->query('page', 1));

        $paginator = MediaPost::reels()
            ->where('user_id', $user->id)
            ->select(MediaPost::FEED_SELECT)
            ->latest()
            ->paginate($limit, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(function (MediaPost $item) {
            return [
                'id' => $item->id,
                'video_url' => $item->file_url,
                'thumbnail' => $item->thumbnail_url,
                'caption' => $item->caption ?? '',
                'views' => (int) ($item->views ?? 0),
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        })->values()->all();

        $meta = ApiResponse::paginationMeta($paginator->total(), $paginator->currentPage(), $paginator->perPage());

        return ApiResponse::success($items, $meta);
    }

    /**
     * GET /api/v1/me/saved - Paginated list of posts and reels saved by the current user.
     * Format: FeedResponse<MediaPost> (same as posts/feed and reels/feed). is_saved is true for all items.
     */
    public function mySaved(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::unauthorized();
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('limit', 20)), 50);

        $paginator = MediaPost::query()
            ->select('media_posts.*')
            ->join('post_saves', 'media_posts.id', '=', 'post_saves.media_post_id')
            ->where('post_saves.user_id', $user->id)
            ->orderBy('post_saves.created_at', 'desc')
            ->with('user:id,display_name,name,avatar_url')
            ->paginate($perPage, ['media_posts.*'], 'page', $page);

        $collection = $paginator->getCollection();
        $postIds = $collection->pluck('id')->all();
        $authorIds = $collection->pluck('user_id')->unique()->filter()->values()->all();
        $likedIds = $this->viewerLikedIds($user->id, $postIds);
        $followingIds = $this->viewerFollowingIds($user->id, $authorIds);

        $data = $collection->map(function (MediaPost $item) use ($likedIds, $followingIds) {
            return $this->feedItem($item, $likedIds, [], $followingIds, saved: true);
        })->values()->all();

        return response()->json([
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'next_page_url' => $paginator->hasMorePages() ? $paginator->nextPageUrl() : null,
            'has_more' => $paginator->hasMorePages(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    /**
     * GET /api/v1/me/liked - Paginated list of posts and reels liked by the current user.
     * Format: FeedResponse<MediaPost>. is_liked is true for all items.
     */
    public function myLiked(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::unauthorized();
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('limit', 20)), 50);

        $paginator = MediaPost::query()
            ->select('media_posts.*')
            ->join('post_likes', 'media_posts.id', '=', 'post_likes.media_post_id')
            ->where('post_likes.user_id', $user->id)
            ->orderBy('post_likes.created_at', 'desc')
            ->with('user:id,display_name,name,avatar_url')
            ->paginate($perPage, ['media_posts.*'], 'page', $page);

        $collection = $paginator->getCollection();
        $postIds = $collection->pluck('id')->all();
        $authorIds = $collection->pluck('user_id')->unique()->filter()->values()->all();
        $savedIds = $this->viewerSavedIds($user->id, $postIds);
        $followingIds = $this->viewerFollowingIds($user->id, $authorIds);

        $data = $collection->map(function (MediaPost $item) use ($savedIds, $followingIds) {
            return $this->feedItem($item, [], $savedIds, $followingIds, liked: true);
        })->values()->all();

        return response()->json([
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'next_page_url' => $paginator->hasMorePages() ? $paginator->nextPageUrl() : null,
            'has_more' => $paginator->hasMorePages(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    /**
     * Build a single feed item (MediaPost format) for FeedResponse. Optional overrides for saved/liked.
     */
    private function feedItem(
        MediaPost $item,
        array $likedIds,
        array $savedIds,
        array $followingIds,
        bool $saved = false,
        bool $liked = false
    ): array {
        $row = $item->toFeedItem();
        $row['isLiked'] = $liked || isset($likedIds[$item->id]);
        $row['isSaved'] = $saved || isset($savedIds[$item->id]);
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

    private function viewerLikedIds(int $userId, array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }
        return PostLike::where('user_id', $userId)->whereIn('media_post_id', $postIds)->pluck('media_post_id')->flip()->all();
    }

    private function viewerSavedIds(int $userId, array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }
        return PostSave::where('user_id', $userId)->whereIn('media_post_id', $postIds)->pluck('media_post_id')->flip()->all();
    }

    private function viewerFollowingIds(int $userId, array $authorIds): array
    {
        if (empty($authorIds)) {
            return [];
        }
        return UserFollower::where('follower_id', $userId)
            ->whereIn('following_id', $authorIds)
            ->where('status', UserFollower::STATUS_ACCEPTED)
            ->pluck('following_id')
            ->flip()
            ->all();
    }

    /**
     * PUT /api/v1/posts/{post_id} - Update caption (and future metadata) for a post owned by the user.
     */
    public function updatePost(Request $request, ApiCacheService $cache, int $postId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::unauthorized();
        }

        $validated = $request->validate([
            'caption' => 'nullable|string|max:2200',
        ]);

        $post = MediaPost::posts()->where('id', $postId)->first();
        if (!$post) {
            return ApiResponse::notFound('Post not found');
        }
        if ((int) $post->user_id !== (int) $user->id) {
            return ApiResponse::forbidden('You can only edit your own posts');
        }

        $post->caption = $validated['caption'] ?? $post->caption;
        $post->save();
        $this->invalidatePostCaches($cache);

        return ApiResponse::success([
            'id' => $post->id,
            'media_url' => $post->file_url,
            'thumbnail_url' => $post->thumbnail_url,
            'caption' => $post->caption ?? '',
            'likes_count' => (int) $post->likes,
            'comments_count' => (int) $post->comments,
            'created_at' => $post->created_at?->toIso8601String(),
        ]);
    }

    /**
     * PUT /api/v1/reels/{reel_id} - Update caption or metadata for a reel owned by the user.
     * Currently supports caption; privacy and tags are accepted but ignored (no columns yet).
     */
    public function updateReel(Request $request, ApiCacheService $cache, int $reelId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::unauthorized();
        }

        $validated = $request->validate([
            'caption' => 'nullable|string|max:2200',
            'privacy' => 'nullable|string|max:50',
            'tags' => 'nullable|array',
        ]);

        $reel = MediaPost::reels()->where('id', $reelId)->first();
        if (!$reel) {
            return ApiResponse::notFound('Reel not found');
        }
        if ((int) $reel->user_id !== (int) $user->id) {
            return ApiResponse::forbidden('You can only edit your own reels');
        }

        $reel->caption = $validated['caption'] ?? $reel->caption;
        $reel->save();
        $this->invalidateReelCaches($cache);

        return ApiResponse::success([
            'id' => $reel->id,
            'video_url' => $reel->file_url,
            'thumbnail' => $reel->thumbnail_url,
            'caption' => $reel->caption ?? '',
            'views' => (int) ($reel->views ?? 0),
            'created_at' => $reel->created_at?->toIso8601String(),
        ]);
    }

    /**
     * DELETE /api/v1/posts/{post_id} - Delete a post owned by the user (and attached media).
     */
    public function deletePost(Request $request, ApiCacheService $cache, int $postId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::unauthorized();
        }

        $post = MediaPost::posts()->where('id', $postId)->first();
        if (!$post) {
            return ApiResponse::notFound('Post not found');
        }
        if ((int) $post->user_id !== (int) $user->id) {
            return ApiResponse::forbidden('You can only delete your own posts');
        }

        $this->deleteMediaFiles($post->file_url, $post->thumbnail_url);
        $post->delete();
        $this->invalidatePostCaches($cache);

        return ApiResponse::success(['message' => 'Post deleted']);
    }

    /**
     * DELETE /api/v1/reels/{reel_id} - Delete a reel owned by the user (and attached media).
     */
    public function deleteReel(Request $request, ApiCacheService $cache, int $reelId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::unauthorized();
        }

        $reel = MediaPost::reels()->where('id', $reelId)->first();
        if (!$reel) {
            return ApiResponse::notFound('Reel not found');
        }
        if ((int) $reel->user_id !== (int) $user->id) {
            return ApiResponse::forbidden('You can only delete your own reels');
        }

        $this->deleteMediaFiles($reel->file_url, $reel->thumbnail_url);
        $reel->delete();
        $this->invalidateReelCaches($cache);

        return ApiResponse::success(['message' => 'Reel deleted']);
    }

    private function deleteMediaFiles(?string $fileUrl, ?string $thumbUrl): void
    {
        $paths = [];
        if ($fileUrl) {
            $path = $this->storagePathFromCdnUrl($fileUrl);
            if ($path) {
                $paths[] = $path;
            }
        }
        if ($thumbUrl) {
            $path = $this->storagePathFromCdnUrl($thumbUrl);
            if ($path) {
                $paths[] = $path;
            }
        }

        foreach ($paths as $path) {
            try {
                $this->bunny->deleteFile($path);
            } catch (\Throwable $e) {
                // Swallow errors; log if needed by higher-level handlers.
            }
        }
    }

    private function storagePathFromCdnUrl(string $url): ?string
    {
        $cdnBase = rtrim((string) config('bunny.cdn_url'), '/');
        if ($cdnBase === '') {
            return null;
        }
        if (str_starts_with($url, $cdnBase . '/')) {
            return ltrim(substr($url, strlen($cdnBase . '/')), '/');
        }
        return null;
    }

    private function invalidatePostCaches(ApiCacheService $cache): void
    {
        $cache->bumpVersion('feed');
    }

    private function invalidateReelCaches(ApiCacheService $cache): void
    {
        $cache->bumpVersion('reels');
        $cache->bumpVersion('music');
    }
}

