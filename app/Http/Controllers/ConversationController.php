<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * GET /conversations - List conversations for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $participantIds = ConversationParticipant::where('user_id', $user->id)->pluck('conversation_id');
        $conversations = Conversation::whereIn('id', $participantIds)
            ->with(['participants.user', 'group'])
            ->get()
            ->sortByDesc(function (Conversation $c) {
                $last = Message::where('conversation_id', $c->id)->latest('created_at')->first();
                return $last ? $last->created_at->timestamp : 0;
            })
            ->values();

        $list = $conversations->map(function (Conversation $c) use ($user) {
            $lastMessage = Message::where('conversation_id', $c->id)->latest('created_at')->first();
            $otherParticipant = $c->participants->where('user_id', '!=', $user->id)->first()?->user;
            $name = $c->type === 'group' ? ($c->name ?? $c->group?->name ?? 'Group') : ($otherParticipant?->display_name ?? $otherParticipant?->name ?? 'User');
            $imageUrl = $c->type === 'group' ? ($c->image_url ?? $c->group?->image_url) : $otherParticipant?->avatar_url;
            // Unread: messages after participant's last_read_at
            $participant = $c->participants->where('user_id', $user->id)->first();
            $unreadCount = 0;
            if ($participant && $participant->last_read_at) {
                $unreadCount = Message::where('conversation_id', $c->id)->where('created_at', '>', $participant->last_read_at)->where('sender_id', '!=', $user->id)->count();
            } else {
                $unreadCount = Message::where('conversation_id', $c->id)->where('sender_id', '!=', $user->id)->count();
            }
            $members = $c->participants->map(fn ($p) => [
                'id' => $p->user->id,
                'name' => $p->user->display_name ?? $p->user->name,
                'avatar_url' => $p->user->avatar_url,
            ])->values()->all();
            $viewerId = (int) $user->id;
            $otherUser = $otherParticipant ? (function () use ($otherParticipant, $viewerId) {
                $online = \App\Models\User::getOnlineStatusForViewer($otherParticipant, $viewerId);
                return [
                    'id' => $otherParticipant->id,
                    'name' => $otherParticipant->display_name ?? $otherParticipant->name,
                    'avatar_url' => $otherParticipant->avatar_url,
                    'is_online' => $online['is_online'],
                    'last_seen_at' => $online['last_seen_at'],
                    'private_account' => (bool) $otherParticipant->private_account,
                    'show_online_status' => (bool) $otherParticipant->show_online_status,
                ];
            })() : null;
            return [
                'id' => (int) $c->id,
                'name' => $name,
                'type' => $c->type,
                'image_url' => $imageUrl,
                'last_message' => $lastMessage?->message,
                'last_message_at' => $lastMessage?->created_at?->toIso8601String(),
                'unread_count' => (int) $unreadCount,
                'other_user' => $otherUser,
                'members' => $c->type === 'group' ? $members : null,
            ];
        });

        return ApiResponse::success($list->values()->all());
    }

    /**
     * POST /conversations/{id}/read - Mark conversation as read for the current user.
     * Sets last_read_at to now() for the current user in that conversation.
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $id = (int) $id;
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'Conversation id must be a positive integer', 400);
        }
        $participant = ConversationParticipant::where('conversation_id', $id)
            ->where('user_id', $user->id)
            ->first();
        if (!$participant) {
            return ApiResponse::notFound('Conversation not found or you are not a participant');
        }
        $participant->last_read_at = now();
        $participant->save();
        return ApiResponse::success([
            'conversation_id' => $id,
            'last_read_at' => $participant->last_read_at->toIso8601String(),
        ]);
    }

    /**
     * DELETE /conversations/{id} - Remove conversation from the current user's chat list (1-1 only).
     * Deletes the participant record so GET /conversations no longer returns this conversation.
     * Idempotent: if already removed, returns success.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $id = (int) $id;
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'Conversation id must be a positive integer', 400);
        }

        $participant = ConversationParticipant::where('conversation_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$participant) {
            // Idempotent: already removed or never participant → success
            return ApiResponse::success(['message' => 'Conversation removed']);
        }

        $conversation = Conversation::find($id);
        if (!$conversation) {
            return ApiResponse::notFound('Conversation not found');
        }

        // Only allow delete for 1-1 (private/user); groups return 403
        if ($conversation->type === 'group') {
            return ApiResponse::forbidden('Only 1-1 conversations can be deleted');
        }

        $participant->delete();

        return ApiResponse::success(['message' => 'Conversation removed']);
    }
}
