# 1-1 Call Lifecycle (Agora)

Base URL: `POST/GET /api/v1/call/...` (auth required).

## Routes

| Method | Path | Description |
|--------|------|-------------|
| POST | `/call/initiate` | Start call; creates `calls` row (ringing), sends FCM to receiver |
| POST | `/call/accept` | Receiver accepts; sets accepted, started_at; returns token for receiver |
| POST | `/call/reject` | Participant rejects; notifies caller |
| POST | `/call/end` | Participant ends; expires token, runs billing if accepted |
| GET | `/call/status/{id}` | Get call status (caller or receiver only) |
| GET | `/call/active/{user_id}` | Get active incoming call for user (ringing/accepted) |

## Example JSON responses

### POST /call/initiate

**Request:** `{ "receiver_id": 2, "call_type": "video" }` (optional: `conversation_id`)

**Success 200:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "call_id": 1,
    "channel_name": "call_1_2_1739876543",
    "token": "006...",
    "uid": 12345678,
    "caller": { "id": 1, "name": "Alice", "avatar_url": "https://..." },
    "receiver": { "id": 2, "name": "Bob", "avatar_url": "https://..." }
  }
}
```

**Error 409 (duplicate active call):**
```json
{
  "success": false,
  "error": { "code": "ACTIVE_CALL_EXISTS", "message": "You or the receiver already have an active call" }
}
```

### POST /call/accept

**Request:** `{ "call_id": 1 }`

**Success 200:**
```json
{
  "success": true,
  "data": {
    "call_id": 1,
    "status": "accepted",
    "channel_name": "call_1_2_1739876543",
    "token": "006...",
    "uid": 87654321
  }
}
```

### POST /call/reject

**Request:** `{ "call_id": 1 }`

**Success 200:**
```json
{
  "success": true,
  "data": { "call_id": 1, "status": "rejected" }
}
```

### POST /call/end

**Request:** `{ "call_id": 1 }` (optional: `duration` seconds for billing)

**Success 200:**
```json
{
  "success": true,
  "data": {
    "call_id": 1,
    "status": "ended",
    "ended_at": "2026-02-23T12:00:00.000000Z",
    "current_balance": 500
  }
}
```

### GET /call/status/{id}

**Success 200:**
```json
{
  "success": true,
  "message": "Call status",
  "data": { "call_id": 1, "status": "ringing" }
}
```

**404:** `{ "success": false, "error": { "code": "NOT_FOUND", "message": "Call not found" } }`

---

## FCM integration (receiver app)

**Incoming call** (sent to receiver when call is initiated):

- **Type:** data-only message (no notification block) so Android delivers to `onMessageReceived` even when app is in background.
- **Payload keys:** `type` = `incoming_call`, `call_id`, `channel_name`, `token`, `caller_id`, `caller_name`, `call_type` (audio/video), `conversation_id`, `uid`.
- **Android:** Use high-priority FCM; from the data payload open **IncomingCallActivity** and pass `call_id`, `channel_name`, `token`, caller info, `call_type` so the activity can join the Agora channel.

**Call ended / missed** (sent to caller when call is rejected, ended, or auto-missed):

- **Payload:** `type` = `call_ended` or `call_missed`, `call_id`.
- **Android:** Stop ringing / leave channel and show “Call ended” or “Missed call” as appropriate.

---

## Auto-missed (30 seconds)

- Scheduled command `call:mark-missed` runs every minute.
- Any call with `status` = `ringing` and `created_at` older than 30 seconds is set to `missed` and a **call_missed** FCM is sent to the **caller**.
- Ensure cron runs: `* * * * * php /path/to/artisan schedule:run`.
