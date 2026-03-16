# Video Upload Limit Configuration (100MB)

This document describes the server and application configuration for allowing video uploads up to **100MB** for reels and posts (to avoid **413 Request Entity Too Large**).

## Summary

- **App limit:** 100MB (validation `max:102400` in KB).
- **Server limit:** 120MB (slightly above app limit to avoid boundary issues).

## NGINX

**File:** `/etc/nginx/sites-available/chataura`

Inside the `server` block (HTTPS):

```nginx
client_max_body_size 120M;
```

After changes:

```bash
sudo nginx -t
sudo systemctl restart nginx
```

## PHP (PHP-FPM)

**File:** `/etc/php/8.3/fpm/php.ini`

| Directive            | Value   | Purpose                          |
|----------------------|---------|----------------------------------|
| `upload_max_filesize`| 120M    | Max size of an uploaded file    |
| `post_max_size`      | 120M    | Max size of POST body           |
| `memory_limit`       | 512M    | Script memory (video processing)|
| `max_execution_time` | 300     | Script timeout (encoding)       |

After changes:

```bash
sudo systemctl restart php8.3-fpm
```

(Use `php8.2-fpm` or your version if different.)

## Laravel Validation

- **Reels:** `ReelController` — `'video' => 'required|file|mimes:mp4,mov|max:102400'` (100MB in KB).
- **Posts:** `PostMediaController` — manual check `$videoFile->getSize() <= 102400 * 1024` (100MB in bytes).

## Request Size Logging

Uploaded video size is logged before processing:

- **Reels:** `ReelController::upload()` — `Reel video upload started` with `uploaded_file_size_bytes` and `uploaded_file_size_mb`.
- **Posts:** `PostMediaController::upload()` — `Post video upload started` with the same fields.

Check `storage/logs/laravel.log` to confirm large uploads reach Laravel.

## Expected Result

Videos up to 100MB should upload without 413 errors. NGINX accepts the body, PHP accepts the file, and Laravel validates and processes the upload.
