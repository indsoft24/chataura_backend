# Cursor Prompt: Update Android App API Base URL to New Server

Use this prompt in your **Android app project** (in Cursor) to switch from the old API server to the new one.

---

## Copy-paste prompt for Cursor

```
Update the Android app to use the new API server.

- **Old base URL:** https://chataura.indsoft24.com/api/v1
- **New base URL:** https://chataura.in/api/v1

Tasks:
1. Find every place where the API base URL or host is defined (e.g. BuildConfig.BASE_URL, BuildConfig.API_URL, constants, Retrofit/OkHttp baseUrl, environment config, .env or gradle config that injects the URL).
2. Replace chataura.indsoft24.com with chataura.in (and keep path /api/v1 where applicable). Ensure the full base URL is https://chataura.in/api/v1 for API calls.
3. If the app uses multiple environments (debug/release/staging), update the production/default URL to chataura.in; adjust other envs only if they should also point to the new server.
4. Search for any hardcoded strings containing "chataura.indsoft24.com" or "indsoft24" in the codebase (including docs, README, or placeholder URLs) and update to chataura.in where they refer to the API or app backend.
5. Do not change deep links, package names, or unrelated third-party URLs unless they explicitly reference the old API host.

Return a short summary of files changed and the new base URL in use.
```

---

## Optional: if you use BuildConfig or build flavors

If your project uses `BuildConfig.BASE_URL` or similar, you may have the URL in:

- `build.gradle` / `build.gradle.kts` (e.g. `buildConfigField "String", "BASE_URL", "\"https://...\""`)
- `gradle.properties` or a `config.gradle`-style file
- `.env` or local config read at build time

Include in your prompt: *"Also update build.gradle (and any gradle files that set BASE_URL or API_URL) so the release build uses https://chataura.in/api/v1."*

---

## After applying the change

1. Rebuild the app (e.g. `./gradlew assembleDebug` or build from Android Studio).
2. Test login, register, and forgot-password against `https://chataura.in/api/v1`.
3. If the device still gets `UnknownHostException` for chataura.in, the issue is DNS/network on that device or network (try mobile data or different Wi‑Fi; see previous backend doc).
