# User Profile API – Dynamic ID Integration

Single source of truth for backend and app. All profile lookups use **database primary key (integer)** only.

---

## 1. Endpoint (preferred)

| Item | Value |
|------|--------|
| **Method** | `GET` |
| **URL** | `/api/v1/users/{id}` |
| **Auth** | Required (Bearer token) |
| **Parameter** | `{id}` = **Database primary key (integer)**. Never use Agora UID, Firebase UID, or hash. |

Legacy alias: `GET /api/v1/user/{id}` (same behavior).

---

## 2. Dynamic integration rules

- **Backend:** Look up the user by **primary key `id` only** (e.g. `User::find($id)`). Reject non‑positive or non‑integer `id` with **400 INVALID_ID**.
- **App:** Sends messages with a property **`apiUserId`** (integer). When the user taps a message or a seat, the app calls `GET /api/v1/users/{apiUserId}` with that integer. No hardcoding; the same ID is used for chat sender, seat user, and host.
- **Contract:** The `data.id` in the response **must match** the `{id}` passed in the URL (the profile’s database primary key).

If you see **400** with **INVALID_ID**, backend validation is working (e.g. Agora UID was sent by mistake). The app must never send Agora UIDs to this endpoint.

---

## 3. Request

- No body.
- Path: `id` must be a **positive integer**. Backend casts to `int` and returns 400 if `id <= 0`.

---

## 4. Success response (200)

```json
{
  "success": true,
  "data": {
    "id": 6,
    "name": "Bibhu",
    "avatar": "https://...",
    "level": 1,
    "followers_count": 0,
    "following_count": 0,
    "friends_count": 0,
    "is_following": false,
    "is_friend": false
  }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | **number** | Database primary key; must equal the `{id}` in the URL. |
| `name` | string | Display name (or name, or `"User"`). |
| `avatar` | string \| null | Avatar URL. |
| `level` | number | User level. |
| `followers_count` | number | Follower count. |
| `following_count` | number | Following count. |
| `friends_count` | number | Accepted friends count. |
| `is_following` | boolean | Whether the current user follows this user. |
| `is_friend` | boolean | Whether the current user is friends with this user. |

---

## 5. Error responses

| Status | When | Body |
|--------|------|------|
| **400** | `id` invalid (e.g. not a positive integer) | `{ "success": false, "error": { "code": "INVALID_ID", "message": "User id must be a positive integer" } }` |
| **404** | No user with that primary key | `{ "success": false, "error": { "code": "NOT_FOUND", "message": "User not found" } }` |
| **403** | Blocked (viewer blocked target, or target blocked viewer) | `{ "success": false, "error": { "code": "FORBIDDEN", "message": "..." } }` |

---

## 6. App / frontend requirements

1. **Source of `id`:** Use the **database user ID** from:
   - Room payload: `host.id`, `owner.id`
   - Chat message metadata: **`apiUserId`** (set by sender from `/me` before sending)
   - Member list / seat list: user id from backend
2. **Call:** `GET /api/v1/users/{id}` with that integer (no Agora UID).
3. **UI:** Use `data.id` for profile header, “Message” (conversation), and any follow/friend actions. Ensure `data.id` is treated as the canonical user id everywhere.

---

## 7. Verification (dynamic ID flow)

1. Join the room.
2. Send a message (e.g. “Fix verified!”). Sender’s `apiUserId` is included in message metadata.
3. Tap your own message or another user’s message.
4. Profile sheet should open and show the **correct database ID** (e.g. 5 or 6) at the top.
5. “Message” opens the correct conversation for that user.

If the profile shows the wrong user or “User not found”, the app is still passing a non‑database ID (e.g. Agora UID or hash) to the profile endpoint; fix the app to use `apiUserId` (or equivalent) only.
