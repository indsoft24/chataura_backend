<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ChatGroup;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    /**
     * POST /groups/create - Create a group and its conversation.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'image' => 'nullable|string|max:500',
                'type' => 'nullable|string|max:50',
                'is_private' => 'nullable|boolean',
                'allow_visitors' => 'nullable|boolean',
                'auto_approve' => 'nullable|boolean',
                'members' => 'nullable|array',
                'members.*' => 'integer|exists:users,id',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $members = $validated['members'] ?? [];
        $memberIds = array_unique(array_merge([$user->id], $members));

        $group = ChatGroup::create([
            'name' => $validated['name'],
            'image_url' => $validated['image'] ?? null,
            'type' => $validated['type'] ?? 'general',
            'is_private' => $validated['is_private'] ?? true,
            'allow_visitors' => $validated['allow_visitors'] ?? false,
            'auto_approve' => $validated['auto_approve'] ?? true,
            'created_by' => $user->id,
        ]);
        $conv = Conversation::create([
            'type' => 'group',
            'name' => $group->name,
            'image_url' => $group->image_url,
            'group_id' => $group->id,
        ]);
        $group->update(['conversation_id' => $conv->id]);
        foreach ($memberIds as $uid) {
            ConversationParticipant::firstOrCreate(['conversation_id' => $conv->id, 'user_id' => $uid]);
            GroupMember::firstOrCreate(['chat_group_id' => $group->id, 'user_id' => $uid], ['role' => $uid === $user->id ? 'admin' : 'member']);
        }
        return ApiResponse::success([
            'id' => $group->id,
            'name' => $group->name,
            'image_url' => $group->image_url,
            'owner_id' => (int) $group->created_by,
            'member_count' => $group->members()->count(),
            'conversation_id' => $conv->id,
        ]);
    }

    /**
     * PATCH /groups/{groupId} - Update group (owner only). Body: { "image_url": "https://..." }.
     * Downloads image, resizes to max 1024px, JPEG 85%, saves to groups/{id}/avatar.jpg (overwrite).
     * Deletes previous group image file if under our storage. Syncs conversation image_url.
     */
    public function update(Request $request, int $groupId)
    {
        if ($groupId <= 0) {
            return ApiResponse::error('INVALID_GROUP_ID', 'Group id must be a positive integer', 400);
        }

        try {
            $request->validate([
                'image_url' => 'required|string|url|max:500',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $group = ChatGroup::find($groupId);

        if (!$group) {
            return ApiResponse::notFound('Group not found');
        }

        if ((int) $group->created_by !== (int) $user->id) {
            return ApiResponse::forbidden('Only the group owner can update the group');
        }

        $imageUrl = $request->input('image_url');
        $storagePath = 'groups/' . $groupId . '/avatar.jpg';

        if (!$this->downloadResizeAndSaveGroupImage($imageUrl, $storagePath)) {
            return ApiResponse::error('INVALID_IMAGE', 'Could not download or process the image', 400);
        }

        $oldImageUrl = $group->image_url;
        $this->deleteGroupImageFileIfOurs($oldImageUrl);

        $newUrl = rtrim(config('app.url'), '/') . '/storage/' . ltrim($storagePath, '/');
        $group->update(['image_url' => $newUrl]);

        $conversation = Conversation::find($group->conversation_id);
        if ($conversation) {
            $conversation->update(['image_url' => $newUrl]);
        }

        return ApiResponse::success([
            'id' => $group->id,
            'name' => $group->name,
            'image_url' => $group->image_url,
            'owner_id' => (int) $group->created_by,
            'member_count' => $group->members()->count(),
            'conversation_id' => $group->conversation_id,
        ]);
    }

    /**
     * GET /groups/{groupId}/members - List group members (as contact-like list).
     */
    public function members(Request $request, int $groupId)
    {
        $user = $request->user();
        $group = ChatGroup::find($groupId);
        if (!$group) {
            return ApiResponse::notFound('Group not found');
        }
        $isMember = GroupMember::where('chat_group_id', $groupId)->where('user_id', $user->id)->exists();
        if (!$isMember) {
            return ApiResponse::forbidden('Not a member of this group');
        }
        $ownerId = (int) $group->created_by;
        $members = GroupMember::where('chat_group_id', $groupId)->with('user')->get();
        $list = $members->map(fn ($m) => [
            'id' => $m->user->id,
            'name' => $m->user->display_name ?? $m->user->name,
            'avatar_url' => $m->user->avatar_url,
            'is_online' => false,
            'last_seen_at' => null,
            'is_owner' => (int) $m->user_id === $ownerId,
            'conversation_id' => $group->conversation_id,
        ]);
        return ApiResponse::success($list->values()->all());
    }

    /**
     * POST /groups/{groupId}/leave - Leave the group. Removes user from group members and conversation
     * participants, and inserts a system message "(display_name) left this group" for remaining members.
     */
    public function leave(Request $request, int $groupId)
    {
        if ($groupId <= 0) {
            return ApiResponse::error('INVALID_GROUP_ID', 'Group id must be a positive integer', 400);
        }

        $user = $request->user();
        $group = ChatGroup::find($groupId);

        if (!$group) {
            return ApiResponse::notFound('Group not found');
        }

        $groupMember = GroupMember::where('chat_group_id', $groupId)->where('user_id', $user->id)->first();
        if (!$groupMember) {
            return ApiResponse::notFound('You are not a member of this group');
        }

        $conversation = Conversation::find($group->conversation_id);
        if (!$conversation || $conversation->type !== 'group') {
            return ApiResponse::notFound('Group conversation not found');
        }

        $displayName = $user->display_name ?? $user->name ?? 'User';
        $systemText = $displayName . ' left this group';

        $systemUserId = (int) config('admin.system_user_id', 1);
        $systemUser = User::find($systemUserId);
        if (!$systemUser) {
            $systemUserId = User::orderBy('id')->value('id') ?? $user->id;
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $systemUserId,
            'message_type' => Message::TYPE_SYSTEM,
            'message_text' => $systemText,
            'message' => $systemText,
        ]);

        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->delete();

        $groupMember->delete();

        return ApiResponse::success(['message' => 'Left the group']);
    }

    /**
     * DELETE /groups/{groupId} - Delete the group (owner only). Removes group, conversation, participants, members.
     */
    public function destroy(Request $request, int $groupId)
    {
        if ($groupId <= 0) {
            return ApiResponse::error('INVALID_GROUP_ID', 'Group id must be a positive integer', 400);
        }

        $user = $request->user();
        $group = ChatGroup::find($groupId);

        if (!$group) {
            return ApiResponse::notFound('Group not found');
        }

        if ((int) $group->created_by !== (int) $user->id) {
            return ApiResponse::forbidden('Only the group owner can delete the group');
        }

        $conversationId = $group->conversation_id;
        if ($conversationId) {
            Conversation::where('id', $conversationId)->delete();
        }
        $group->delete();

        return ApiResponse::success(['message' => 'Group deleted']);
    }

    /**
     * DELETE /groups/{groupId}/members - Remove a member (owner only). Body: { "user_id": 123 }.
     */
    public function removeMember(Request $request, int $groupId)
    {
        if ($groupId <= 0) {
            return ApiResponse::error('INVALID_GROUP_ID', 'Group id must be a positive integer', 400);
        }

        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $targetUserId = (int) $validated['user_id'];

        $group = ChatGroup::find($groupId);
        if (!$group) {
            return ApiResponse::notFound('Group not found');
        }

        if ((int) $group->created_by !== (int) $user->id) {
            return ApiResponse::forbidden('Only the group owner can remove members');
        }

        $targetMember = GroupMember::where('chat_group_id', $groupId)->where('user_id', $targetUserId)->first();
        if (!$targetMember) {
            return ApiResponse::notFound('User is not a member of this group');
        }

        $conversation = Conversation::find($group->conversation_id);
        if ($conversation && $conversation->type === 'group') {
            $targetUser = User::find($targetUserId);
            $displayName = $targetUser ? ($targetUser->display_name ?? $targetUser->name ?? 'User') : 'User';
            $systemText = $displayName . ' was removed from the group';
            $systemUserId = (int) config('admin.system_user_id', 1);
            if (!User::find($systemUserId)) {
                $systemUserId = (int) User::orderBy('id')->value('id');
            }
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $systemUserId,
                'message_type' => Message::TYPE_SYSTEM,
                'message_text' => $systemText,
                'message' => $systemText,
            ]);
        }

        ConversationParticipant::where('conversation_id', $group->conversation_id)
            ->where('user_id', $targetUserId)
            ->delete();
        $targetMember->delete();

        return ApiResponse::success(['message' => 'Member removed']);
    }

    /**
     * POST /groups/{groupId}/members - Add members (owner only). Body: { "members": [11, 22, 33] }.
     */
    public function addMembers(Request $request, int $groupId)
    {
        if ($groupId <= 0) {
            return ApiResponse::error('INVALID_GROUP_ID', 'Group id must be a positive integer', 400);
        }

        try {
            $validated = $request->validate([
                'members' => 'required|array|min:1',
                'members.*' => 'integer|exists:users,id',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $group = ChatGroup::find($groupId);

        if (!$group) {
            return ApiResponse::notFound('Group not found');
        }

        if ((int) $group->created_by !== (int) $user->id) {
            return ApiResponse::forbidden('Only the group owner can add members');
        }

        $conversation = Conversation::find($group->conversation_id);
        if (!$conversation || $conversation->type !== 'group') {
            return ApiResponse::notFound('Group conversation not found');
        }

        $memberIds = array_unique(array_map('intval', $validated['members']));
        $existing = GroupMember::where('chat_group_id', $groupId)->whereIn('user_id', $memberIds)->pluck('user_id')->all();
        $toAdd = array_values(array_diff($memberIds, $existing));

        foreach ($toAdd as $uid) {
            ConversationParticipant::firstOrCreate(['conversation_id' => $conversation->id, 'user_id' => $uid]);
            GroupMember::firstOrCreate(
                ['chat_group_id' => $groupId, 'user_id' => $uid],
                ['role' => 'member']
            );
        }

        return ApiResponse::success([
            'message' => 'Members added',
            'added_count' => count($toAdd),
        ]);
    }

    /**
     * Download image from URL, resize so longest side <= 1024px, save as JPEG 85% to storage path.
     */
    private function downloadResizeAndSaveGroupImage(string $imageUrl, string $storagePath): bool
    {
        $response = Http::timeout(15)->get($imageUrl);
        if (!$response->successful()) {
            return false;
        }
        $body = $response->body();
        $im = @imagecreatefromstring($body);
        if ($im === false) {
            return false;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 1 || $h < 1) {
            imagedestroy($im);
            return false;
        }
        $max = 1024;
        if ($w <= $max && $h <= $max) {
            $nw = $w;
            $nh = $h;
        } else {
            if ($w >= $h) {
                $nw = $max;
                $nh = (int) round($h * ($max / $w));
            } else {
                $nh = $max;
                $nw = (int) round($w * ($max / $h));
            }
        }
        $out = imagecreatetruecolor($nw, $nh);
        if ($out === false) {
            imagedestroy($im);
            return false;
        }
        imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($im);
        ob_start();
        $ok = imagejpeg($out, null, 85);
        imagedestroy($out);
        if (!$ok) {
            ob_end_clean();
            return false;
        }
        $jpeg = ob_get_clean();
        Storage::disk('public')->put($storagePath, $jpeg);
        return true;
    }

    /**
     * If the given URL is our app's storage URL, delete that file so we don't leave orphans.
     */
    private function deleteGroupImageFileIfOurs(?string $imageUrl): void
    {
        if ($imageUrl === null || $imageUrl === '') {
            return;
        }
        $base = rtrim(config('app.url'), '/');
        if (stripos($imageUrl, $base) !== 0) {
            return;
        }
        $path = parse_url($imageUrl, PHP_URL_PATH);
        if ($path === null || $path === '') {
            return;
        }
        $prefix = '/storage/';
        if (stripos($path, $prefix) !== 0) {
            return;
        }
        $relative = ltrim(substr($path, strlen($prefix)), '/');
        if ($relative === '') {
            return;
        }
        if (Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }
}
