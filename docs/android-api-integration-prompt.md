# Android App – Full API Integration & UI Guide (Chataura)

You are a senior Android (Kotlin) engineer integrating the Chataura backend API into the Android app. Implement a **professional, production-ready** experience: correct auth, pagination, error handling, and polished UI for all features below.

---

## 1. Base configuration

- **Base URL:** `https://chataura.indsoft24.com/api/v1` (or use `BuildConfig.BASE_URL`).
- **Auth:** Send `Authorization: Bearer <access_token>` on every request except login/register/refresh.
- **Content-Type:** `application/json` for JSON bodies; `multipart/form-data` for uploads (avatar, images).
- **Success shape:** `{ "success": true, "data": { ... } }` — use `data` for payload.
- **Pagination:** List endpoints return `meta`: `{ "total", "current_page", "last_page", "limit" }`. Always send `?page=1&limit=20` (or your page size) and use `meta` for “load more” and “no more data”.

---

## 2. Auth flow

- **Register:** `POST /auth/register` — body: email, password, name, etc. as per backend.
- **Login:** `POST /auth/login` — returns token (and optionally refresh token). Store securely (e.g. EncryptedSharedPreferences).
- **Refresh:** `POST /auth/refresh` — use refresh token when API returns 401; then retry the failed request with the new access token.
- **Logout:** `POST /auth/logout` — optional; clear local tokens and FCM registration.
- **OTP (email):** `POST /auth/send-email-otp`, `POST /auth/verify-email-otp` after registration if required.

**UI:** Login / Register screens with validation; “Forgot password” if backend supports it; auto-refresh token and retry on 401.

---

## 3. User & profile

- **Me:** `GET /users/me` — full profile; use for drawer/header and settings.
- **Update me:** `PATCH /users/me` (JSON) or `POST /user/update` (multipart for avatar).
- **Profile (other user):** `GET /users/{id}` or `GET /user/{id}` — public profile; show follow/friend/block actions.
- **Profile (app shape):** `GET /user/profile` — profile DTO for profile screen (counts, coins, etc.).
- **Search users:** `GET /users/search?q=...` — pagination not applied; limit on server.
- **Blocked list:** `GET /user/blocked-users?page=1&limit=20` — paginated; show list and unblock.

**UI:** Profile screen with avatar, name, level, counts (followers, following, friends), bio; edit profile with avatar upload; search with results list; blocked users list with unblock.

---

## 4. Follow / friend / block (user interaction)

- **Follow:** `POST /user/follow` — body: `following_id`.
- **Unfollow:** `POST /user/unfollow` — body: `following_id`.
- **Follow requests (incoming):** `GET /user/follow-requests?page=1&limit=20` — paginated; accept/reject.
- **Accept follow request:** `POST /user/accept-follow-request` — body: `follower_id`.
- **Reject follow request:** `POST /user/reject-follow-request` — body: `follower_id`.
- **Friend requests (incoming):** `GET /user/friend-requests?page=1&limit=20` — paginated.
- **Send friend request:** `POST /user/friend-request` or `POST /user/add-friend` — body: `user_id`.
- **Accept/decline friend:** `POST /user/friend-request/accept`, `POST /user/friend-request/decline`, or accept/reject by request id as per backend.
- **Block:** `POST /user/block` — body: `user_id` or `blocked_user_id`.
- **Unblock:** `POST /user/unblock` — body: `blocked_user_id` or `user_id`.
- **Followers/Following:** `GET /users/{id}/followers?page=1&limit=20`, `GET /users/{id}/following?page=1&limit=20` — paginated lists.
- **Gifts received (charm):** `GET /users/{id}/gifts?page=1&limit=20` — paginated; response includes `total_gifts_collected`, `max_gifts_available`, `gifts`.
- **Privileges:** `GET /users/{id}/privileges` — wealth/level privileges for that user.

**UI:** Buttons on profile: Follow/Unfollow, Add friend / Pending / Friends; Follow requests and Friend requests screens with accept/reject; followers/following tabs with pagination; charm/gifts tab with paginated grid; privilege list.

---

## 5. Reference data (cached on server – use as normal GET)

- **Countries:** `GET /countries` — list for signup/profile (id, name, flag_url, flag_emoji).
- **Languages:** `GET /languages` — list for app language (code, name, native_name).
- **FAQ:** `GET /faq` — list (id, question, answer); show in FAQ screen or help.
- **Feedback:** `POST /feedback` — body as required by backend.

**UI:** Country/language pickers; FAQ expandable list; feedback form.

---

## 6. Wallet & coins

- **Balance:** `GET /users/me/wallet` or `GET /users/me/balance` — wallet balance.
- **Packages (recharge):** `GET /wallet/packages` — list of coin packages (id, coin_amount, price_in_inr).
- **Initiate recharge:** `POST /wallet/recharge/initiate` — body: `package_id`; returns Razorpay order id, key_id, amount. Open Razorpay SDK with these.
- **Verify recharge:** `POST /wallet/recharge/verify` — body: razorpay_order_id, razorpay_payment_id, razorpay_signature; then refresh balance.
- **Transactions:** `GET /wallet/transactions?page=1&limit=20` — paginated; response has `data.transactions` and `meta`.
- **Withdrawals:** `GET /wallet/withdrawals?page=1&limit=20` — paginated list.
- **Withdraw:** `POST /wallet/withdraw` — body: as per backend (gems, payment details, etc.).
- **Send gift (1:1):** `POST /wallet/send-gift` — body: gift_id, receiver_id.
- **Transfer (seller/admin):** `POST /wallet/transfer` — body: receiver_id, coin_amount, note.
- **Can call:** `GET /wallet/can-call/{receiver_id}/{call_type}` — video/audio; use before starting call.
- **Gifts catalog (1:1):** `GET /gifts` — list of virtual gifts (id, name, coin_cost, image_url, animation_url).

**UI:** Wallet screen with balance; “Add coins” with package list and Razorpay flow; transaction history with load more; withdrawals list; send gift from catalog; “Can call” check before call UI.

---

## 7. Rooms (live / party)

- **List rooms:** `GET /rooms?page=1&limit=20&sort=recent|popular` — optional: country, owner_id, following=1, friends=1. Paginated; use for discovery.
- **Themes:** `GET /rooms/themes` — list of room themes (id, name, type, media_url) for create/edit.
- **Create:** `POST /rooms` — body: title, max_seats, cover_image_url, description, tags, theme_id, etc.
- **Room detail:** `GET /rooms/{roomId}` — single room; roomId can be UUID or display_id.
- **Update/Delete:** `PATCH /rooms/{roomId}`, `DELETE /rooms/{roomId}`.
- **Join/Leave:** `POST /rooms/{roomId}/join`, `POST /rooms/{roomId}/leave`.
- **Token (Agora):** `GET /rooms/{roomId}/token` — for joining room RTC.
- **Transfer host:** `POST /rooms/{roomId}/transfer-host` — body: new_host_user_id.
- **Seats:** `GET /rooms/{roomId}/seats` — list; take/leave/assign/free/mute via POST/DELETE/PATCH under `rooms/{roomId}/seats/...`.

**Gifts in room:** `GET /gift-types` — catalog; `POST /rooms/{roomId}/gifts/send` — body: gift_id, receiver_id, quantity.

**UI:** Room list with filters and sort; “Load more” using meta; room detail with join/leave and seat grid; Agora integration for audio/video; gift panel using gift-types and send; host controls and transfer host.

---

## 8. Spin (party room)

- **Prizes config:** `GET /spin/prizes` — spin_cost, prizes (label, emoji, coins, probability).
- **Play:** `POST /spin/play` — body: optional room_id; deducts coins, returns prize. Enforce server result only; animate client-side.

**UI:** Spin wheel/slot UI; show cost and prizes from `/spin/prizes`; on play show result and update balance.

---

## 9. Call (1:1 video/audio)

- **Token (1:1):** `GET /call/token?user_id=...&type=video|audio` — Agora RTC token for channel `call_{minId}_{maxId}`.
- **Agora token (by channel):** `POST /agora/token` — body: channel_name, user_id (current user).
- **Initiate:** `POST /call/initiate` — body: receiver_id, call_type (video/audio); backend may send FCM to callee.
- **Accept/Reject/End:** `POST /call/accept`, `POST /call/reject`, `POST /call/end` — body as required (call_id, etc.).
- **Heartbeat:** `POST /call/heartbeat` — during call so server doesn’t terminate.
- **Status:** `GET /call/status/{call_id}`, `POST /call/status` (update).
- **Active call:** `GET /call/active/{user_id}` — check if user is in a call.
- **History:** `GET /calls/history?page=1&limit=20` — paginated.

**UI:** Call button on profile/chat; in-call screen with Agora; heartbeat timer; end/accept/reject; call history list with load more.

---

## 10. Chat & contacts

- **Conversations:** `GET /conversations?page=1&limit=20` — paginated; each item: id, name, type, image_url, last_message, last_message_at, unread_count, other_user, members (for group).
- **Conversation with user:** `GET /conversations/with-user/{userId}` — get or create private conversation; use for “Message” from profile.
- **Mark read:** `POST /conversations/{id}/read`.
- **Delete conversation:** `DELETE /conversations/{id}` (1:1 only).
- **Messages:** `GET /messages/{conversation_id}?page=1&limit=20` — paginated; newest first in response (page 1 = latest); use meta for “load older”.
- **Send message:** `POST /messages/send` — body: conversation_id, message_type (text|emoji|gift), message_text, gift_id (optional).
- **Upload image:** `POST /messages/upload-image` — multipart `image`; use returned URL in send if needed.
- **Message status:** `POST /messages/status` — body: message_id, status (delivered|read).
- **Friends:** `GET /contacts/friends?page=1&limit=50` — paginated.
- **Add friend:** `POST /contacts/friends/add` — body: user_id.
- **Groups:** `GET /contacts/groups?page=1&limit=50` — paginated list of groups.
- **Create group:** `POST /groups/create` — body: name, image, members[].
- **Group detail / update:** `PATCH /groups/{groupId}` (e.g. image_url).
- **Members:** `GET /groups/{groupId}/members?page=1&limit=50` — paginated.
- **Leave / remove member / add members:** `POST /groups/{groupId}/leave`, `DELETE /groups/{groupId}/members`, `POST /groups/{groupId}/members` with body as required.

**UI:** Chat list with unread badges and last message; open chat with message list and “load older” using pagination; send text/image/gift; friends and groups lists with pagination; group info and member list with pagination.

---

## 11. Level & profile frames (gamification)

- **Level/XP:** `GET /profile/level` or `GET /user/level` — current level, xp, progress.
- **Details (frames):** `GET /profile/details` — level, xp, selected_frame, unlocked_frames, available_frames.
- **All frames:** `GET /profile/frames/all` — full list with unlocked/selected; use for frame picker grid.
- **Unlocked frames:** `GET /profile/frames` — unlocked + selected.
- **Select frame:** `POST /profile/select-frame` — body: frame_id.
- **Add XP:** `POST /xp/add` or `POST /user/level/add-xp` — body: amount (server may restrict).

**UI:** Level badge and XP bar; frame selector grid (locked/unlocked/selected); show selected frame on profile.

---

## 12. Device & notifications

- **Register FCM:** `POST /device/register` or `POST /update-fcm-token` — body: fcm_token, platform (android), device_type.
- **User notifications (prefs):** `POST /user/notifications` — body: message_notifications, room_notifications, gift_notifications (booleans).
- **Privacy:** `POST /user/privacy` — body: private_account, show_online_status.
- **Delete account:** `POST /user/delete` — clear local data and tokens after success.

**UI:** Register FCM on login; settings screen for notification toggles and privacy; delete account with confirmation.

---

## 13. Invite

- **My invite:** `GET /users/me/invite` — invite code / link.
- **Apply referral:** `POST /invite/apply` — body: invite_code (or as per backend).

**UI:** Invite screen with share; apply code on signup or in settings.

---

## 14. Implementation checklist

- Use a single **API client** (Retrofit + OkHttp) with interceptors: add Bearer token, handle 401 with refresh+retry, parse error body (success: false, error.code, error.message).
- **Pagination:** For every list endpoint above that mentions `page` and `limit`, use `meta.total`, `meta.current_page`, `meta.last_page`, `meta.limit` to drive “Load more” and disable when `current_page >= last_page`.
- **Errors:** Show user-friendly messages from `error.message`; for validation use `error.errors` if present.
- **Offline:** Cache reference data (countries, languages, FAQ) and optionally last conversation list; show cached data when offline and sync when online.
- **FCM:** Implement incoming call and new-message handling as in the existing FCM doc; keep token registration and high-priority handling.
- **UI:** Material Design 3; dark/light theme; loading states and empty states for all lists; pull-to-refresh and pagination for lists; clear navigation (bottom nav or drawer) for Home (rooms), Chat, Wallet, Profile; call and notification handling must not break existing Agora and chat.

---

## 15. Summary

- **Base:** `/api/v1`, Bearer auth, JSON/multipart as needed.
- **Lists:** Always pass `page` and `limit` where documented; use `meta` for pagination and “load more”.
- **Reference:** Countries, languages, FAQ, room themes, wallet packages, gifts, spin prizes are cached on server; call as normal GET.
- **Feature parity:** Auth, profile, follow/friend/block, wallet, rooms, spin, 1:1 call, chat, groups, level/frames, device/FCM, invite — all with correct endpoints and pagination.
- **Quality:** Centralized API layer, 401 refresh+retry, error handling, and professional UI for every screen listed above.

Implement the app to this spec so that all backend functionality is correctly consumed and the user experience is consistent, fast, and reliable.
