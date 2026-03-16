# Reels & Posts Media Upload (Bunny CDN)

## Overview

- **User** → **Laravel API** → **Bunny Storage** → **CDN Pull Zone**
- Video is compressed with FFmpeg (30s max, 1080p, 2000k bitrate, mp4 h264) before upload.
- Thumbnails are generated at 1 second (JPG).
- Files are streamed to Bunny to avoid storing large files on the app server.

## Environment

```env
BUNNY_STORAGE_ZONE=chataura
BUNNY_STORAGE_API_KEY=your-storage-api-key
BUNNY_STORAGE_REGION=la
BUNNY_CDN_URL=https://chataura.b-cdn.net
```

**Server:** FFmpeg must be installed (e.g. `apt install ffmpeg`) for video compression and thumbnail generation.

## Endpoints (authenticated)

### POST /api/v1/reels/upload

- **Content-Type:** `multipart/form-data`
- **Body:** `video` (required), `caption` (optional)
- **Video:** mp4, mov; max 100MB
- **Response:** `video_url`, `thumbnail_url` (CDN URLs), `caption`, `id`

### POST /api/v1/posts/upload

- **Content-Type:** `multipart/form-data`
- **Body:** one of `image` or `video` (or `file` for either), plus optional `caption`
- **Image:** jpg, png; max 5MB
- **Video:** mp4, mov; max 100MB (compressed then uploaded)
- **Response:** `file_url`, `thumbnail_url` (for video), `media_type` (image|video), `caption`, `id`

## Storage paths (Bunny)

- `reels/videos/{uuid}.mp4`
- `reels/thumbs/{uuid}.jpg`
- `posts/images/{uuid}.jpg` or `.png`
- `posts/videos/{uuid}.mp4`
- `posts/thumbs/{uuid}.jpg`

## Database

Table `media_posts`: `user_id`, `type` (reel|post), `media_type` (video|image), `file_url`, `thumbnail_url`, `caption`, `likes`, `comments`, timestamps.

All `file_url` and `thumbnail_url` values are full CDN URLs (e.g. `https://chataura.b-cdn.net/reels/videos/...`).

## Dependencies

- **guzzlehttp/guzzle** – HTTP client for Bunny Storage API
- **FFmpeg** – system binary for video compression and thumbnail extraction
