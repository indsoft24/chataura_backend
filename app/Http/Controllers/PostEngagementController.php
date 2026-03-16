<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\MediaPost;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\PostSave;
use App\Services\ApiCacheService;
use Illuminate\Http\Request;

class PostEngagementController extends Controller
{
    /**
     * Toggle like for the authenticated user. Updates media_posts.likes count.
     * POST /api/v1/posts/{id}/like
     */
    public function like(ApiCacheService $cache, string $id)
    {
        $post = MediaPost::find($id);
        if (!$post) {
            return ApiResponse::error('NOT_FOUND', 'Post not found.', 404);
        }

        $user = request()->user();
        $existing = PostLike::where('user_id', $user->id)->where('media_post_id', $post->id)->first();

        if ($existing) {
            $existing->delete();
            $post->decrement('likes');
            $liked = false;
        } else {
            PostLike::create(['user_id' => $user->id, 'media_post_id' => $post->id]);
            $post->increment('likes');
            $liked = true;
        }

        $post->refresh();
        $this->invalidateMediaCaches($cache, $post);
        return response()->json([
            'status' => 'success',
            'success' => true,
            'isLiked' => $liked,
            'liked' => $liked,
            'likes' => (int) $post->likes,
        ]);
    }

    /**
     * Add a comment. Increments media_posts.comments.
     * POST /api/v1/posts/{id}/comment
     */
    public function comment(Request $request, ApiCacheService $cache, string $id)
    {
        $request->validate([
            'comment' => 'required|string|max:2200',
        ]);

        $post = MediaPost::find($id);
        if (!$post) {
            return ApiResponse::error('NOT_FOUND', 'Post not found.', 404);
        }

        $user = $request->user();
        $comment = PostComment::create([
            'user_id' => $user->id,
            'media_post_id' => $post->id,
            'comment' => $request->input('comment'),
        ]);
        $post->increment('comments');
        $this->invalidateMediaCaches($cache, $post);

        $comment->load('user');
        return response()->json([
            'success' => true,
            'comment' => [
                'id' => $comment->id,
                'user' => [
                    'id' => $comment->user->id,
                    'display_name' => $comment->user->display_name ?? $comment->user->name,
                    'avatar_url' => $comment->user->avatar_url ?? '',
                ],
                'comment' => $comment->comment,
                'created_at' => $comment->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Paginated comments for a post.
     * GET /api/v1/posts/{id}/comments
     */
    public function getComments(Request $request, string $id)
    {
        $post = MediaPost::find($id);
        if (!$post) {
            return ApiResponse::error('NOT_FOUND', 'Post not found.', 404);
        }

        $perPage = min((int) $request->input('limit', 20), 50);
        $paginator = PostComment::where('media_post_id', $post->id)
            ->with('user:id,display_name,name,avatar_url')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = $paginator->getCollection()->map(function (PostComment $c) {
            return [
                'id' => $c->id,
                'user' => [
                    'id' => $c->user->id,
                    'display_name' => $c->user->display_name ?? $c->user->name,
                    'avatar_url' => $c->user->avatar_url ?? '',
                ],
                'comment' => $c->comment,
                'created_at' => $c->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    /**
     * Toggle save post for the authenticated user.
     * POST /api/v1/posts/{id}/save
     */
    public function save(ApiCacheService $cache, string $id)
    {
        $post = MediaPost::find($id);
        if (!$post) {
            return ApiResponse::error('NOT_FOUND', 'Post not found.', 404);
        }

        $user = request()->user();
        $existing = PostSave::where('user_id', $user->id)->where('media_post_id', $post->id)->first();

        if ($existing) {
            $existing->delete();
            $saved = false;
        } else {
            PostSave::create(['user_id' => $user->id, 'media_post_id' => $post->id]);
            $saved = true;
        }

        $this->invalidateMediaCaches($cache, $post);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'isSaved' => $saved,
            'saved' => $saved,
        ]);
    }

    /**
     * Increment share counter. Returns new shares count.
     * POST /api/v1/posts/{id}/share
     */
    public function share(ApiCacheService $cache, string $id)
    {
        $post = MediaPost::find($id);
        if (!$post) {
            return ApiResponse::error('NOT_FOUND', 'Post not found.', 404);
        }

        $post->increment('shares');
        $post->refresh();
        $this->invalidateMediaCaches($cache, $post);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'shares' => (int) $post->shares,
        ]);
    }

    private function invalidateMediaCaches(ApiCacheService $cache, MediaPost $post): void
    {
        if ($post->type === MediaPost::TYPE_REEL) {
            $cache->bumpVersion('reels');
            return;
        }

        $cache->bumpVersion('feed');
    }
}
