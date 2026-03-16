<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\MediaPost;
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

