# API Endpoints Reference

Base URL: **`/api/v1`** (e.g. `https://chataura.indsoft24.com/api/v1`)

All protected routes require **Bearer token** in `Authorization` header unless noted.

---

## Auth (public)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/auth/register` | Register |
| POST | `/auth/login` | Login |
| POST | `/auth/refresh` | Refresh token |
| POST | `/auth/logout` | Logout |

---

## Users (current user)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/users/me` | Current user profile (full) |
| PATCH | `/users/me` | Update current user (display_name, avatar_url) |
| GET | `/users/me/wallet` | Wallet / balance |
| GET | `/users/me/balance` | Alias for wallet |
| GET | `/users/me/transactions` | Transaction history |
| GET | `/users/{id}` | Public profile by DB id (id, name, avatar, level, followers_count, following_count, friends_count, is_following, is_friend) |

---

## User profile & settings (legacy paths)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/user/profile` | Own profile (ProfileDto: id, name, avatar, level, friends_count, followers_count, following_count, coins, gems, language) |
| POST | `/user/update` | Update profile (multipart: name, avatar file) |
| POST | `/user/update-language` | Set language (body: language) |
| GET | `/user/blocked-users` | List blocked users |
| POST | `/user/unblock` | Unblock (body: user_id) |
| POST | `/user/device` | Register FCM token (body: fcm_token, platform) |
| POST | `/user/delete` | Delete account |
| POST | `/user/privacy` | Privacy settings (body: private_account, show_online_status) |
| POST | `/user/notifications` | Notification toggles (body: message_notifications, room_notifications, gift_notifications) |

---

## User interaction (follow, friend, block)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/user/follow` | Follow user (body: following_id) |
| POST | `/user/unfollow` | Unfollow (body: following_id) |
| GET | `/user/friend-requests` | Pending friend requests to me |
| POST | `/user/add-friend` | Send friend request (body: friend_id) |
| POST | `/user/accept-friend` | Accept request (body: friend_id) |
| POST | `/user/reject-friend` | Reject request (body: friend_id) |
| POST | `/user/block` | Block user (body: blocked_user_id) |
| GET | `/user/{id}` | Same as GET /users/{id} (public profile) |

---

## Rooms (party / live)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/rooms` | List rooms (query: page, limit, sort) |
| POST | `/rooms` | Create room (body: title, max_seats, settings, etc.) |
| GET | `/rooms/{roomId}` | Room detail (with host, seats) |
| PATCH | `/rooms/{roomId}` | Update room (owner only) |
| DELETE | `/rooms/{roomId}` | Close room (owner only) |
| POST | `/rooms/{roomId}/join` | Join room (returns room, member, agora_token, agora_uid) |
| POST | `/rooms/{roomId}/leave` | Leave room |
| POST | `/rooms/{roomId}/transfer-host` | Transfer host (body: user_id) |
| GET | `/rooms/{roomId}/token` | Get Agora token (query: uid optional) |

---

## Seats (in room)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/rooms/{roomId}/seats` | List seats |
| POST | `/rooms/{roomId}/seats/leave` | Leave current seat |
| POST | `/rooms/{roomId}/seats/{seatIndex}/take` | Take seat (owner/host) |
| POST | `/rooms/{roomId}/seats/{seatIndex}/assign` | Assign seat to user (body: user_id) |
| DELETE | `/rooms/{roomId}/seats/{seatIndex}` | Free seat (host only) |
| PATCH | `/rooms/{roomId}/seats/{seatIndex}/mute` | Mute/unmute (body: muted) |

---

## Gifts

| Method | Path | Description |
|--------|------|-------------|
| GET | `/gifts` | List gifts |
| POST | `/rooms/{roomId}/gifts/send` | Send gift (body: gift_type_id, receiver_id) |

---

## Call (1-to-1 video/audio)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/call/token` | RTC token (query: user_id, type=video\|audio) |

---

## Chat

| Method | Path | Description |
|--------|------|-------------|
| GET | `/conversations` | List conversations |
| GET | `/conversations/with-user/{userId}` | Get or create 1-to-1 conversation |
| GET | `/messages/{conversation_id}` | List messages (includes image_url) |
| POST | `/messages/upload-image` | Upload image (multipart: image) → returns url |
| POST | `/messages/send` | Send message (body: conversation_id, message?, image_url?) |

---

## Contacts & groups

| Method | Path | Description |
|--------|------|-------------|
| GET | `/contacts/friends` | List friends |
| POST | `/contacts/friends/add` | Add friend (alias) |
| GET | `/contacts/groups` | List groups |
| POST | `/groups/create` | Create group |
| GET | `/groups/{groupId}/members` | Group members |

---

## Invite

| Method | Path | Description |
|--------|------|-------------|
| GET | `/users/me/invite` | Invite info |
| POST | `/invite/apply` | Apply invite code |

---

## Other

| Method | Path | Description |
|--------|------|-------------|
| GET | `/languages` | List languages |
| GET | `/faq` | FAQ list |
| POST | `/feedback` | Submit feedback |

---

## Environment (for FCM)

- `FCM_SERVER_KEY` – Legacy FCM server key for push (new follow, friend request, new message). Optional; if missing, notifications are skipped.
