# Realtime Chat & Calling API – Endpoints & Responses

Base URL: **`/api/v1`**. All endpoints below require **Bearer token** (Laravel Sanctum) unless noted.

---

## 1. Send message (text, emoji, gift only)

**POST** `/api/v1/messages/send`

**Body (JSON):**
```json
{
  "conversation_id": 1,
  "message_type": "text",
  "message_text": "Hello"
}
```
- `message_type`: `text` | `emoji` | `gift` (required for gift: `message_text` and/or `gift_id`)
- `message_text`: optional for gift, required for text/emoji
- `gift_id`: optional, for `message_type: gift`
- Backward compat: `message` accepted as `message_text`

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 101,
    "conversation_id": 1,
    "sender_id": 5,
    "message_type": "text",
    "message_text": "Hello",
    "gift_id": null,
    "status": "sent",
    "created_at": "2026-02-21 18:30:00"
  }
}
```

**Errors:** 403 not a participant, 400 validation (e.g. missing message_text).

---

## 2. Get messages

**GET** `/api/v1/messages/{conversation_id}`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 101,
      "conversation_id": 1,
      "sender_id": 5,
      "sender_name": "John",
      "sender_avatar": "https://...",
      "message_type": "text",
      "message_text": "Hello",
      "gift_id": null,
      "status": "sent",
      "created_at": "2026-02-21T18:30:00.000000Z"
    }
  ]
}
```
Order: `created_at` ASC. Only participants can read.

---

## 3. Message status (delivered / read)

**POST** `/api/v1/messages/status`

**Body:**
```json
{
  "message_id": 101,
  "status": "read"
}
```
- `status`: `delivered` | `read`

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 101,
    "status": "read"
  }
}
```

---

## 4. Agora token

**POST** `/api/v1/agora/token`

**Body:**
```json
{
  "channel_name": "call_conv_1_1739123456",
  "user_id": 5
}
```
- `user_id` must be the authenticated user’s id.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "token": "<RTC token>",
    "channel_name": "call_conv_1_1739123456",
    "uid": 12345678
  }
}
```

---

## 5. Initiate call

**POST** `/api/v1/call/initiate`

**Body:**
```json
{
  "conversation_id": 1,
  "call_type": "video"
}
```
- `call_type`: `audio` | `video`

**Response (200):**
```json
{
  "success": true,
  "data": {
    "call_id": 42,
    "token": "<RTC token>",
    "uid": 12345678,
    "channel_name": "call_conv_1_1739123456",
    "conversation_id": 1,
    "receiver_id": 6,
    "call_type": "video"
  }
}
```
Creates `call_log` (status `initiated`), generates Agora token, sends FCM call notification to receiver.

---

## 6. Update call status

**POST** `/api/v1/call/status`

**Body:**
```json
{
  "call_id": 42,
  "status": "ended"
}
```
- `status`: `initiated` | `ringing` | `answered` | `rejected` | `missed` | `ended`

**Response (200):**
```json
{
  "success": true,
  "data": {
    "call_id": 42,
    "status": "ended",
    "ended_at": "2026-02-21T18:35:00.000000Z"
  }
}
```
Only caller or receiver can update. `ended_at` set when status is `ended`.

---

## 7. Register FCM device

**POST** `/api/v1/device/register`

**Body:**
```json
{
  "fcm_token": "<FCM device token>",
  "device_type": "android"
}
```
- `device_type`: e.g. `android` | `ios` | `web`

**Response (200):**
```json
{
  "success": true,
  "data": {
    "message": "Device registered"
  }
}
```

---

## 8. Call session APIs (WhatsApp-style)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/call/initiate` | Start call: creates `call_sessions`, sends FCM **incoming_call** (high priority) |
| POST | `/call/accept` | Accept call (receiver only): status = accepted |
| POST | `/call/reject` | Reject call: status = rejected |
| POST | `/call/end` | End call: status = ended, set ended_at |
| GET | `/call/active/{user_id}` | Get active incoming call for user (user_id must be authenticated user) |

**Initiate body:** `conversation_id` (optional), `receiver_id` (optional if conversation_id given), `call_type` (audio|video).

**FCM incoming_call payload:** `type: incoming_call`, `call_id`, `channel_name`, `caller_id`, `caller_name`, `call_type`, optional `conversation_id`, `token`, `uid`. Priority: **high**.

---

## 9. Existing chat/call endpoints (unchanged)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/call/token` | Token by `user_id` + `type` (query) |
| POST | `/call/status` | Update call_log status (legacy) |
| POST | `/call/log` | Log call (receiver_id, channel_name, call_type, status) |
| POST | `/agora/token` | Agora token by channel_name + user_id |
| GET | `/conversations` | List conversations |
| GET | `/conversations/with-user/{userId}` | Get/create 1-to-1 conversation |
| POST | `/user/device` | Same as `/device/register` (fcm_token, platform) |

---

## Firebase usage

- **New message:** `FirebaseService::sendMessageNotification($userId, $message)` for each other participant.
- **Incoming call (call_sessions):** `FirebaseService::sendIncomingCallNotification($receiverId, $payload)` from `POST /call/initiate` — type `incoming_call`, priority **high** for instant delivery.
- **Legacy call:** `FirebaseService::sendCallNotification($receiverId, $callData)` still available.

FCM data payloads include `type` (`message`, `call`, or `incoming_call`) and relevant ids/channel/token/uid for the client.
