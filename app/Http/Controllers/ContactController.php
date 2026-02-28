<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Friendship;
use App\Models\ChatGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    /**
     * GET /contacts/friends - List friends with optional conversation_id.
     */
    public function friends(Request $request)
    {
        $user = $request->user();
        $friendships = Friendship::where('status', Friendship::STATUS_ACCEPTED)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('friend_id', $user->id);
            })
            ->with(['user', 'friend'])
            ->get();
        $viewerId = (int) $user->id;
        $list = $friendships->map(function ($f) use ($user, $viewerId) {
            $friend = $f->user_id === $user->id ? $f->friend : $f->user;
            $conversationId = $this->getOrCreatePrivateConversation($user->id, $friend->id);
            $online = \App\Models\User::getOnlineStatusForViewer($friend, $viewerId);
            return [
                'id' => $friend->id,
                'name' => $friend->display_name ?? $friend->name,
                'avatar_url' => $friend->avatar_url,
                'is_online' => $online['is_online'],
                'last_seen_at' => $online['last_seen_at'],
                'private_account' => (bool) $friend->private_account,
                'show_online_status' => (bool) $friend->show_online_status,
                'conversation_id' => $conversationId,
            ];
        });
        return ApiResponse::success($list->values()->all());
    }

    /**
     * GET /contacts/groups - List groups the user is a member of.
     */
    public function groups(Request $request)
    {
        $user = $request->user();
        $myConvIds = ConversationParticipant::where('user_id', $user->id)->pluck('conversation_id');
        $groupConversations = Conversation::where('type', 'group')->whereIn('id', $myConvIds)->with('group')->get();
        $groupIds = $groupConversations->pluck('group_id')->filter()->unique()->values();
        $groups = ChatGroup::whereIn('id', $groupIds)->with('conversation')->get();
        $list = $groups->map(function (ChatGroup $g) {
            $conv = $g->conversation;
            return [
                'id' => $g->id,
                'name' => $g->name,
                'image_url' => $g->image_url,
                'owner_id' => (int) $g->created_by,
                'member_count' => $g->members()->count(),
                'conversation_id' => $conv?->id,
            ];
        });
        return ApiResponse::success($list->values()->all());
    }

    /**
     * GET /conversations/with-user/{userId} - Get or create private conversation with user (for Message button).
     */
    public function conversationWithUser(Request $request, $userId)
    {
        $me = $request->user();
        $other = User::find((int) $userId);
        if (!$other || $other->id === $me->id) {
            return ApiResponse::notFound('User not found');
        }
        $convId = $this->getOrCreatePrivateConversation($me->id, $other->id);
        return ApiResponse::success([
            'conversation_id' => $convId,
            'name' => $other->display_name ?? $other->name ?? 'User',
            'image_url' => $other->avatar_url,
        ]);
    }

    /**
     * POST /contacts/friends/add - Add a friend (body: user_id).
     */
    public function addFriend(Request $request)
    {
        try {
            $validated = $request->validate(['user_id' => 'required|integer|exists:users,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $friendId = (int) $validated['user_id'];
        if ($friendId === $user->id) {
            return ApiResponse::error('INVALID_REQUEST', 'Cannot add yourself', 400);
        }
        $existing = Friendship::where(function ($q) use ($user, $friendId) {
            $q->where('user_id', $user->id)->where('friend_id', $friendId);
        })->orWhere(function ($q) use ($user, $friendId) {
            $q->where('user_id', $friendId)->where('friend_id', $user->id);
        })->first();
        if ($existing) {
            if ($existing->status === Friendship::STATUS_ACCEPTED) {
                return ApiResponse::error('ALREADY_FRIENDS', 'Already friends', 400);
            }
            return ApiResponse::error('PENDING', 'Friend request already sent', 400);
        }
        Friendship::create([
            'user_id' => $user->id,
            'friend_id' => $friendId,
            'status' => Friendship::STATUS_PENDING,
        ]);
        return ApiResponse::success(['message' => 'Friend request sent']);
    }

    private function getOrCreatePrivateConversation(int $userId1, int $userId2): ?int
    {
        $convId = ConversationParticipant::whereIn('user_id', [$userId1, $userId2])
            ->select('conversation_id')
            ->groupBy('conversation_id')
            ->havingRaw('count(distinct user_id) = 2')
            ->pluck('conversation_id')
            ->first();
        if ($convId) {
            $conv = Conversation::where('type', 'private')->find($convId);
            if ($conv) {
                return $conv->id;
            }
        }
        $conv = Conversation::create(['type' => 'private']);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $userId1]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $userId2]);
        return $conv->id;
    }
}
