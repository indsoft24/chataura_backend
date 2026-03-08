# Backend Image Upload & Profile Sync — Verification for Android

This document confirms the exact request/response keys and server expectations so the Android app can align.

---

## 1. General Upload Endpoint

**POST** `/api/v1/upload`

| Item | Backend expectation | Android should use |
|------|--------------------|--------------------|
| **Request** | `multipart/form-data` | `multipart/form-data` |
| **Field name** | **`file`** | **`file`** ✓ |
| **Validation** | `required|image|max:10240` (10MB in KB) | Send under key `file` |
| **MIME** | Any `image/*` (Laravel `image` rule allows jpeg, png, bmp, gif, svg, webp) | `image/jpeg` is fine ✓ |

**Response (success):**

```json
{
  "success": true,
  "data": {
    "url": "https://chataura.in/storage/uploads/..."
  }
}
```

- **Key for the image URL:** **`data.url`** (not `image_url`, `avatar_url`, or a raw string).  
- The URL is **absolute** (includes `config('app.url')` + `/storage/` + path).

---

## 2. Profile Update Endpoint

**POST** `/api/v1/user/update`

| Item | Backend expectation | Android should use |
|------|--------------------|--------------------|
| **Request** | `multipart/form-data` | `multipart/form-data` ✓ |
| **Avatar field name** | **`avatar`** | **`avatar`** ✓ |
| **Validation** | `nullable|image|max:5120` (5MB) | Send profile picture under key `avatar` |

**Response (success):** Updated user object inside `data`:

```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "Display Name",
    "avatar_url": "https://chataura.in/storage/avatars/...",
    "bio": "...",
    "gender": "Male",
    "dob": "1990-01-01",
    "country": "IN",
    "wallet_balance": 0,
    "role": "user"
  }
}
```

- **Avatar URL key:** **`data.avatar_url`**.  
- The backend stores and returns an **absolute** URL for the avatar (built from `APP_URL` + `/storage/` + path). Use `data.avatar_url` in the app’s User model for the new profile picture.

---

## 3. Room Creation & Update

**POST** `/api/v1/rooms` (create)  
**PATCH** `/api/v1/rooms/{id}` (update)

| Item | Backend expectation | Android should use |
|------|--------------------|--------------------|
| **Cover image** | Not uploaded in this request; send the **URL** from upload | Send the URL from `POST /upload` → `data.url` |
| **Field name** | **`cover_image_url`** | **`cover_image_url`** ✓ |

- **Create:** JSON body with `cover_image_url` (optional). Same key for PATCH.
- **Update:** JSON body with `cover_image_url` (optional). Empty string is normalized to `null`.

So the flow is: **Upload image via POST /upload → get `data.url` → send that URL in `cover_image_url`** for room create/update.

---

## 4. MIME Type & Size Limits

| Endpoint | Max size (backend rule) | MIME |
|----------|-------------------------|------|
| **POST /upload** | 10 MB (`max:10240` in KB) | `image/*` (e.g. `image/jpeg`) |
| **POST /user/update** (avatar) | 5 MB (`max:5120` in KB) | `image/*` |

**Server configuration (PHP / Nginx):**

- To avoid **413 Payload Too Large** or **422 Unprocessable Entity**, ensure at least **10 MB** for uploads:
  - **PHP:** `upload_max_filesize` and `post_max_size` ≥ `10M` (e.g. in `php.ini` or `.user.ini`).
  - **Nginx:** `client_max_body_size 10M;` (or higher) in the server/location block for the API.
- If the app compresses images and keeps them under 5 MB, 5–10 MB server limits are sufficient.

---

## Summary: Exact keys for Android data models

| Use case | Request key | Response key for URL |
|----------|-------------|----------------------|
| General upload (avatar, room cover, etc.) | `file` | `data.url` |
| Profile update (avatar file) | `avatar` | `data.avatar_url` (in returned user object) |
| Room create/update (cover) | `cover_image_url` (URL string from upload) | Use same URL in subsequent GET room responses as `cover_image_url` |

All returned URLs are **absolute** (full `https://...`). If the app sees relative paths, check that `APP_URL` is set correctly in `.env` on the server.
