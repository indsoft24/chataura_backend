# Party Room: Co-Host & Automatic Host Transfer

## Schema

- **Rooms:** `host_id` (current host), `co_host_id` (optional co-host).
- **Room members:** Roles `host`, `co_host`, `speaker`, `listener` (owner/host, co-host, member, audience).

## REST API

### Promote to Co-Host

- **POST** `/api/v1/rooms/{roomId}/co-host`
- **Body:** `{ "target_uid": 12345 }`
- **Auth:** Required (Bearer).
- **Rules:** Caller must be current host. Target must be a **seated** member.
- **Effect:** Target becomes co-host; any previous co-host is demoted to speaker. `room.co_host_id` is set.
- **Event:** `room_role_updated` so frontend can update UI (e.g. co-host can manage seats, themes, music).

### Transfer Host

- **POST** `/api/v1/rooms/{roomId}/transfer-host`
- **Body:** `{ "target_uid": 12345 }` (or `new_host_user_id` / `user_id` for backward compatibility).
- **Auth:** Required (Bearer).
- **Rules:** Caller must be current host. Target must be a **seated** member.
- **Effect:** Target becomes host; previous host becomes **speaker** (seated member). `room.co_host_id` is cleared.
- **Event:** `room_host_changed` so all clients can sync.

## Automatic Host Reassignment on Disconnect

When the **host** leaves the room (e.g. **POST** `/api/v1/rooms/{roomId}/leave`):

1. If there is a **co-host**, the co-host becomes the new host.
2. If there is no co-host but there are other **seated** members (speakers), the **oldest** seated member (by `joined_at`) becomes host.
3. **Event:** `room_host_changed` is emitted.
4. **Destroy:** The room is **only** destroyed if the host disconnects and the room is **completely empty** (no other active members). If there are only listeners and no one to promote, the room is left without a host (no auto-destroy).

This logic runs in `RoomController::leave()` and `HostTransferService::transferOnHostLeave()`.

### Socket / Presence Integration

If you use a **websocket or presence** layer (e.g. Soketi, Laravel Echo, or a separate Node service):

- On **host disconnect** (websocket disconnect or heartbeat timeout), call the same behavior as when the host leaves via REST:
  - Resolve the room and current host.
  - If host disconnected: run `HostTransferService::transferOnHostLeave($room)`; if it returns `false`, optionally call `DestroyRoomService::endRoom($room)` only when the room has **no remaining active members**.
  - Emit `room_host_changed` (and optionally push notification) so all clients update.

The REST `leave` endpoint already handles “host left”; socket servers should mirror this (e.g. when presence detects host offline, trigger the same transfer/end logic server-side and broadcast `room_host_changed`).

## Events (broadcast names)

| Event                 | Broadcast name       | When |
|-----------------------|----------------------|------|
| Role updated (co-host)| `room_role_updated`  | After promoting a seated member to co-host. |
| Host changed          | `room_host_changed`  | After manual transfer or automatic reassignment on host leave. |

## Room API responses

Room payloads (e.g. join, show, list) include:

- `host`: `{ id, display_name, avatar_url }`
- `co_host`: `{ id, display_name, avatar_url }` or `null`

Members still expose `role`: `host`, `co_host`, `speaker`, or `listener`.

---

## 4. Exclusive Co-Host Migration Constraint

- A room has **exactly one** `host_id` and **at most one** `co_host_id`.
- When the current host **assigns a new co-host**, any **previous co-host is revoked** (they become a standard speaker/seated member). Only one co-host at a time.
- **CRITICAL – Host migration (manual or automatic):** When host privileges are transferred (via **POST** `/rooms/{roomId}/transfer-host` or automatic reassignment on host disconnect), if the target receiving host is the **current co-host** (or any other member), the system **clears** the room’s `co_host_id` (sets it to `null`). The new host is assigned, and the room has **0 co-hosts** until the new host assigns one. This avoids residual co-host state and keeps exactly one host and at most one co-host at all times.
