# Chat Aura Android App - Builder Prompt

## Project Overview
Build a social audio/party rooms Android mobile app called "Chat Aura" that allows users to create or join rooms, voice/video chat, send gifts, play games, and manage seats (limited speaking slots).

## Backend API
- **Base URL:** `https://chataura.indsoft24.com/api/v1`
- **Documentation:** See `ANDROID_APP_INTEGRATION_GUIDE.md` for complete API details
- **Postman Collection:** Import `POSTMAN_COLLECTION.json` for testing

## Core Features to Implement

### 1. Authentication
- Registration screen (phone/email + password)
- Login screen
- Secure token storage (use Android Keystore or EncryptedSharedPreferences)
- Auto token refresh before expiry
- Logout functionality

### 2. User Profile
- Display user profile (name, avatar, level, exp, coins)
- Edit profile (display name, avatar URL)
- Wallet/balance screen
- Transaction history

### 3. Room Discovery (Hot Parties)
- List of active rooms with:
  - Room title
  - Owner name and avatar
  - Member count
  - Cover image
- Pull-to-refresh
- Pagination (infinite scroll)
- Sort by: Popular / Recent
- Tap to view room details

### 4. Room Creation
- Create room screen with:
  - Title input
  - Max seats selector (1-20)
  - Settings toggles (allow video, gifts, games)
  - Optional cover image URL
  - Optional description
  - Optional tags
- On create: automatically join the room

### 5. Room Detail & Management
- Room detail screen showing:
  - Room info (title, description, owner)
  - Member list
  - Seat grid (chairs)
  - Settings (if owner)
- Owner can:
  - Edit room
  - Close room
  - Mute any seat

### 6. Room Join & Agora Integration
- Join room functionality
- Initialize Agora RTC SDK with token from API
- Join Agora channel for voice/video
- Display video streams
- Audio controls (mute/unmute)
- Video controls (enable/disable)
- Leave room functionality

### 7. Seat Management
- Visual seat grid (chairs) showing:
  - Occupied seats (with user avatar/name)
  - Empty seats
  - Muted indicators
- Tap empty seat to take it
- Tap own seat to leave it
- Mute/unmute controls (self or owner can mute)

### 8. Gifts System
- Gift catalog screen (grid/list)
- Gift details (name, price, image)
- Send gift screen:
  - Select receiver from room members
  - Select gift
  - Select quantity
  - Confirm and send
- Gift animation display in room
- Update balance after sending

### 9. Invite System
- Display invite code
- Share invite link (native share)
- Show invite statistics (total invited, coins earned)
- Apply invite code during registration

## Technical Requirements

### API Integration
- Use Retrofit or OkHttp for API calls
- Handle all error responses (400, 401, 403, 404, 409, 500)
- Implement retry logic for network failures
- Show loading states
- Handle token expiry and auto-refresh

### Agora SDK
- Integrate Agora RTC SDK for voice/video
- Initialize with App ID (get from backend team)
- Use tokens from API (never hardcode)
- Handle connection errors
- Display video streams in UI
- Audio/video controls

### UI/UX Requirements
- Modern, clean design
- Smooth animations
- Loading indicators
- Empty states
- Error states with retry
- Pull-to-refresh where applicable
- Pagination for lists
- Responsive layout

### Security
- Store tokens securely (EncryptedSharedPreferences/Keystore)
- Never log sensitive data
- Validate all inputs
- Handle SSL pinning (optional but recommended)

## API Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Error message"
  }
}
```

## Key Endpoints

### Authentication
- `POST /auth/register` - Register new user
- `POST /auth/login` - Login
- `POST /auth/refresh` - Refresh token
- `POST /auth/logout` - Logout

### Rooms
- `GET /rooms` - List rooms (Hot Parties)
- `POST /rooms` - Create room
- `GET /rooms/{id}` - Get room details
- `POST /rooms/{id}/join` - Join room (returns Agora token)
- `POST /rooms/{id}/leave` - Leave room

### Seats
- `GET /rooms/{id}/seats` - List seats
- `POST /rooms/{id}/seats/{index}/take` - Take seat
- `POST /rooms/{id}/seats/leave` - Leave seat
- `PATCH /rooms/{id}/seats/{index}/mute` - Mute/unmute

### Gifts
- `GET /gifts` - Get gift catalog
- `POST /rooms/{id}/gifts/send` - Send gift

## Error Handling

Handle these error codes:
- `401 UNAUTHORIZED` - Token expired/invalid → Refresh or redirect to login
- `400 VALIDATION_ERROR` - Show validation errors
- `409 CONFLICT` - Show conflict message (e.g., "Seat already taken")
- `404 NOT_FOUND` - Show "Not found" message
- `500 SERVER_ERROR` - Show generic error with retry option

## Testing Checklist

- [ ] Registration flow
- [ ] Login flow
- [ ] Token refresh
- [ ] Room discovery (list, pagination, sorting)
- [ ] Room creation
- [ ] Room join with Agora
- [ ] Seat management (take, leave, mute)
- [ ] Gift sending
- [ ] Profile management
- [ ] Invite system
- [ ] Error handling (network errors, API errors)
- [ ] Token expiry handling
- [ ] Offline handling

## Deliverables

1. **Android App** - Fully functional APK/AAB
2. **Source Code** - Well-documented, clean code
3. **Testing** - Tested on multiple devices/Android versions
4. **Documentation** - Code comments and README

## Additional Notes

- Use Kotlin (preferred) or Java
- Minimum SDK: Android 7.0 (API 24)
- Target SDK: Latest
- Follow Material Design guidelines
- Optimize for performance
- Handle edge cases gracefully

## Support

- **API Documentation:** `ANDROID_APP_INTEGRATION_GUIDE.md`
- **Postman Collection:** `POSTMAN_COLLECTION.json`
- **API Base URL:** `https://chataura.indsoft24.com/api/v1`

---

**Start by:**
1. Setting up Android project
2. Integrating Retrofit/OkHttp
3. Implementing authentication flow
4. Testing API endpoints with Postman collection
5. Building UI screens one by one
6. Integrating Agora SDK
7. Testing thoroughly

Good luck! 🚀

