# Profile Frames API (Level & Frames)

Auth: required (`/api/v1` under `auth.api` middleware).

---

## All frames (full grid with locked placeholders)

**Route:** `GET /api/v1/profile/frames/all`

Use this to build the full frame grid: every frame is returned with `unlocked` and `selected` so the client can show locked placeholders and merge with `buildFrameModels`.

**Response (200):**

```json
{
  "success": true,
  "data": {
    "frames": [
      {
        "id": 1,
        "name": "Starter",
        "level_required": 0,
        "animation_json": { "type": "none" },
        "is_premium": false,
        "unlocked": true,
        "unlocked_at": "2026-02-25T12:00:00.000000Z",
        "selected": true
      },
      {
        "id": 2,
        "name": "Silver",
        "level_required": 3,
        "animation_json": { "type": "glow", "color": "#c0c0c0" },
        "is_premium": false,
        "unlocked": false,
        "unlocked_at": null,
        "selected": false
      }
    ],
    "selected_frame_id": 1
  }
}
```

- **frames**: Full list, ordered by `level_required` then `id`.
- **unlocked**: `true` if the user has unlocked this frame.
- **unlocked_at**: ISO8601 when unlocked, or `null` if locked.
- **selected**: `true` for the user’s current profile frame.
- **selected_frame_id**: ID of the selected frame, or `null` if none.

Client: use `frames` for the grid; for each item use `unlocked` to show locked vs unlocked and `selected` to highlight the chosen frame.
