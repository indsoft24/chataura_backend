# Chat: 1-to-1 Call Token & Image Messages

## 1. 1-to-1 Call Token (Video / Audio)

**Endpoint:** `GET /api/v1/call/token`

**Auth:** Required (Bearer).

**Query parameters:**

| Param     | Type   | Required | Description |
|----------|--------|----------|-------------|
| `user_id` | int    | Yes      | The other user’s database ID (the user you are calling). |
| `type`    | string | Yes      | `"video"` or `"audio"`. |

**Success (200):**

```json
{
  "success": true,
  "data": {
    "agora_token": "<RTC token>",
    "channel_name": "call_5_6",
    "agora_uid": 12345678
  }
}
```

- **Channel name:** Deterministic per pair: `call_{min(userA_id, userB_id)}_{max(userA_id, userB_id)}`. Caller and callee use the same channel when they use the same `user_id` (the other user).
- **agora_uid:** Unique for this request; use it when joining the channel.
- **Errors:** 400 if calling yourself, 404 if user not found, 422 if validation fails. If Agora is not configured, token may be empty; app should show “Call not configured” / “Call failed”.

---

## 2. Image Upload for Chat (two-step flow)

### Step 1: Upload image

**Endpoint:** `POST /api/v1/messages/upload-image`

**Auth:** Required.

**Body:** Multipart form data, field name `image` (file). Max 10MB, image types only.

**Success (200):**

```json
{
  "success": true,
  "data": {
    "url": "https://your-domain.com/storage/chat-attachments/..."
  }
}
```

Use this `url` in the next step as `image_url`.

### Step 2: Send message with image

**Endpoint:** `POST /api/v1/messages/send` (existing, extended)

**Body (JSON):**

| Field             | Type   | Required | Description |
|-------------------|--------|----------|-------------|
| `conversation_id` | int    | Yes      | Conversation ID. |
| `message`         | string | No*      | Text. *Required if `image_url` is not set. |
| `image_url`       | string | No*      | URL from upload step. *Required if `message` is empty. |

Either `message` or `image_url` (or both) must be present.

**Success (200):** Same message object as before, with `image_url` included when set.

### List messages

**Endpoint:** `GET /api/v1/messages/{conversation_id}` (existing, extended)

Each message object now includes:

- `image_url` (string | null) – present when the message has an image.

Use this to render image bubbles in the chat UI.
