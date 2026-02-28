# Chat Aura Android App Integration Guide

## Overview
This document provides complete integration instructions for building the Chat Aura Android mobile app that connects to the backend API.

## Base Configuration

### API Base URL
```
https://chataura.indsoft24.com/api/v1
```

### Authentication
All protected endpoints require Bearer token authentication:
```
Authorization: Bearer <access_token>
```

### Content Type
All requests should use:
```
Content-Type: application/json
Accept: application/json
```

---

## Authentication Flow

### 1. User Registration

**Endpoint:** `POST /auth/register`

**Request:**
```json
{
  "phone": "+1234567890",  // OR "email": "user@example.com"
  "password": "password123",
  "display_name": "John Doe",  // optional
  "invite_code": "ABC12345"  // optional
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "phone": "+1234567890",
      "email": null,
      "display_name": "John Doe",
      "avatar_url": null,
      "level": 1,
      "exp": 0,
      "coin_balance": 0,
      "invite_code": "XYZ78901",
      "created_at": "2024-01-01T00:00:00.000000Z"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "random_refresh_token_string",
    "expires_in": 3600
  }
}
```

**Error Response (400):**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "errors": {
      "phone": ["The phone has already been taken."]
    }
  }
}
```

**Implementation Notes:**
- Store `access_token` and `refresh_token` securely (use Android Keystore/EncryptedSharedPreferences)
- Token expires in 3600 seconds (1 hour)
- Use `refresh_token` to get new `access_token` before expiry

---

### 2. User Login

**Endpoint:** `POST /auth/login`

**Request:**
```json
{
  "phone": "+1234567890",  // OR "email": "user@example.com"
  "password": "password123"
}
```

**Response:** Same format as registration

**Error (401):**
```json
{
  "success": false,
  "error": {
    "code": "INVALID_CREDENTIALS",
    "message": "Invalid phone/email or password"
  }
}
```

---

### 3. Refresh Access Token

**Endpoint:** `POST /auth/refresh`

**Request:**
```json
{
  "refresh_token": "stored_refresh_token"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "access_token": "new_access_token",
    "refresh_token": "new_refresh_token",
    "expires_in": 3600
  }
}
```

**Implementation:**
- Call this automatically when `access_token` expires (401 response)
- Update stored tokens after successful refresh
- If refresh fails, redirect to login screen

---

### 4. Logout

**Endpoint:** `POST /auth/logout`

**Request:**
```json
{
  "refresh_token": "stored_refresh_token"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Logged out successfully"
  }
}
```

---

## User Profile

### Get Current User Profile

**Endpoint:** `GET /users/me`

**Headers:**
```
Authorization: Bearer <access_token>
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "phone": "+1234567890",
    "email": null,
    "display_name": "John Doe",
    "avatar_url": "https://...",
    "level": 5,
    "exp": 1250,
    "coin_balance": 5000,
    "invite_code": "XYZ78901",
    "created_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

---

### Update Profile

**Endpoint:** `PATCH /users/me`

**Request:**
```json
{
  "display_name": "New Name",  // optional
  "avatar_url": "https://..."  // optional
}
```

---

### Get Wallet/Balance

**Endpoint:** `GET /users/me/wallet`

**Response:**
```json
{
  "success": true,
  "data": {
    "coin_balance": 5000,
    "total_earned": 10000,
    "total_spent": 5000
  }
}
```

---

## Rooms (Party Rooms)

### Create Room

**Endpoint:** `POST /rooms`

**Request:**
```json
{
  "title": "My Party Room",
  "max_seats": 8,  // optional, default: 8
  "settings": {  // optional
    "allow_video": true,
    "allow_gifts": true,
    "allow_games": true
  },
  "cover_image_url": "https://...",  // optional
  "description": "Room description",  // optional
  "tags": ["music", "fun"]  // optional
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "title": "My Party Room",
    "owner_id": 1,
    "agora_channel_name": "room_550e8400-e29b-41d4-a716-446655440000",
    "max_seats": 8,
    "is_live": true,
    "cover_image_url": null,
    "description": null,
    "tags": ["music", "fun"],
    "settings": {
      "allow_video": true,
      "allow_gifts": true,
      "allow_games": true
    },
    "created_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

---

### List Rooms (Hot Parties / Discovery)

**Endpoint:** `GET /rooms?page=1&limit=20&sort=popular`

**Query Parameters:**
- `page`: integer (default: 1)
- `limit`: integer (default: 20, max: 100)
- `sort`: `popular` | `recent` (default: `recent`)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Party Room #1",
      "owner": {
        "id": 1,
        "display_name": "John",
        "avatar_url": "https://..."
      },
      "members_count": 5,
      "max_seats": 8,
      "is_live": true,
      "cover_image_url": "https://...",
      "created_at": "2024-01-01T00:00:00.000000Z"
    }
  ],
  "meta": {
    "total": 100,
    "page": 1,
    "limit": 20
  }
}
```

---

### Get Single Room

**Endpoint:** `GET /rooms/{roomId}`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "title": "Party Room #1",
    "owner_id": 1,
    "agora_channel_name": "room_550e8400...",
    "max_seats": 8,
    "is_live": true,
    "members_count": 5,
    "current_seats": [
      {
        "seat_index": 0,
        "user_id": 1,
        "display_name": "John",
        "avatar_url": "https://...",
        "is_muted": false
      },
      {
        "seat_index": 1,
        "user_id": null,
        "display_name": null,
        "avatar_url": null,
        "is_muted": false
      }
    ],
    "created_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

---

### Join Room

**Endpoint:** `POST /rooms/{roomId}/join`

**Response:**
```json
{
  "success": true,
  "data": {
    "room": { /* room object */ },
    "member": {
      "id": "member-uuid",
      "room_id": "room-uuid",
      "user_id": 1,
      "role": "member",
      "seat_index": null,
      "joined_at": "2024-01-01T00:00:00.000000Z"
    },
    "agora_token": "agora_rtc_token_string",
    "agora_uid": 12345
  }
}
```

**Implementation Notes:**
- Store `agora_token` and `agora_uid` for Agora SDK initialization
- Use `agora_channel_name` from room object for Agora channel
- Initialize Agora RTC engine with these credentials

---

### Leave Room

**Endpoint:** `POST /rooms/{roomId}/leave`

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Left room successfully"
  }
}
```

---

### Get Agora Token (Refresh)

**Endpoint:** `GET /rooms/{roomId}/token?uid=12345`

**Query Parameters:**
- `uid`: integer (optional, auto-generated if not provided)

**Response:**
```json
{
  "success": true,
  "data": {
    "agora_token": "new_agora_token",
    "agora_uid": 12345,
    "expires_in": 3600
  }
}
```

---

## Seats (Chairs)

### List Seats in Room

**Endpoint:** `GET /rooms/{roomId}/seats`

**Response:**
```json
{
  "success": true,
  "data": {
    "seats": [
      {
        "seat_index": 0,
        "user_id": 1,
        "display_name": "John",
        "avatar_url": "https://...",
        "is_muted": false
      },
      {
        "seat_index": 1,
        "user_id": null,
        "display_name": null,
        "avatar_url": null,
        "is_muted": false
      }
    ],
    "max_seats": 8
  }
}
```

---

### Take a Seat

**Endpoint:** `POST /rooms/{roomId}/seats/{seatIndex}/take`

**Response:**
```json
{
  "success": true,
  "data": {
    "seat_index": 0,
    "user_id": 1,
    "is_muted": false
  }
}
```

**Error (409 Conflict):**
```json
{
  "success": false,
  "error": {
    "code": "CONFLICT",
    "message": "Seat is already taken"
  }
}
```

---

### Leave Seat

**Endpoint:** `POST /rooms/{roomId}/seats/leave`

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Left seat successfully"
  }
}
```

---

### Mute/Unmute Seat

**Endpoint:** `PATCH /rooms/{roomId}/seats/{seatIndex}/mute`

**Request:**
```json
{
  "muted": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "seat_index": 0,
    "is_muted": true
  }
}
```

---

## Gifts

### Get Gift Catalog

**Endpoint:** `GET /gifts`

**Response:**
```json
{
  "success": true,
  "data": {
    "gifts": [
      {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "Rose",
        "coin_price": 10,
        "image_url": "https://...",
        "animation_type": "fade"
      },
      {
        "id": "550e8400-e29b-41d4-a716-446655440001",
        "name": "Heart",
        "coin_price": 20,
        "image_url": "https://...",
        "animation_type": "bounce"
      }
    ]
  }
}
```

---

### Send Gift

**Endpoint:** `POST /rooms/{roomId}/gifts/send`

**Request:**
```json
{
  "gift_id": "550e8400-e29b-41d4-a716-446655440000",
  "receiver_id": 2,
  "quantity": 1  // optional, default: 1
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "transaction_id": "transaction-uuid",
    "coin_amount": 10,
    "sender_balance_after": 4990,
    "receiver_balance_after": 5010
  }
}
```

**Error (400 - Insufficient Balance):**
```json
{
  "success": false,
  "error": {
    "code": "INSUFFICIENT_BALANCE",
    "message": "Insufficient coin balance"
  }
}
```

**Implementation:**
- Update user's coin balance in UI after successful gift send
- Show gift animation in room (use `animation_type` from gift catalog)
- Play sound/visual effect based on gift type

---

## Invite System

### Get Invite Information

**Endpoint:** `GET /users/me/invite`

**Response:**
```json
{
  "success": true,
  "data": {
    "invite_code": "ABC12345",
    "invite_link": "https://chataura.indsoft24.com/invite/ABC12345",
    "reward_rules": {
      "inviter_reward": 100,
      "referee_reward": 50
    },
    "total_invited": 5,
    "total_earned_coins": 500
  }
}
```

---

### Apply Invite Code

**Endpoint:** `POST /invite/apply`

**Request:**
```json
{
  "invite_code": "ABC12345"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Invite code applied successfully",
    "reward_received": 50
  }
}
```

---

## Error Handling

### Standard Error Response Format

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message"
  }
}
```

### Common Error Codes

| HTTP Status | Error Code | Description |
|------------|------------|-------------|
| 400 | `VALIDATION_ERROR` | Request validation failed |
| 400 | `INSUFFICIENT_BALANCE` | Not enough coins |
| 401 | `UNAUTHORIZED` | Missing or invalid token |
| 401 | `INVALID_CREDENTIALS` | Wrong login credentials |
| 403 | `FORBIDDEN` | Insufficient permissions |
| 404 | `NOT_FOUND` | Resource not found |
| 409 | `CONFLICT` | Resource conflict (e.g., seat taken) |
| 500 | `SERVER_ERROR` | Internal server error |

### Error Handling Implementation

```kotlin
// Example Kotlin error handling
when (response.code()) {
    401 -> {
        // Token expired, try refresh
        refreshToken { newToken ->
            // Retry request with new token
        }
    }
    400 -> {
        // Show validation errors
        val error = response.errorBody()?.string()
        // Parse and display errors
    }
    409 -> {
        // Show conflict message (e.g., "Seat already taken")
    }
}
```

---

## Agora Integration

### Required Agora SDK Setup

1. **Add Agora SDK to Android project:**
```gradle
dependencies {
    implementation 'io.agora.rtc:full-sdk:4.x.x'
}
```

2. **Initialize Agora Engine:**
```kotlin
val engine = RtcEngine.create(context, AGORA_APP_ID, object : IRtcEngineEventHandler() {
    override fun onJoinChannelSuccess(channel: String?, uid: Int, elapsed: Int) {
        // User joined channel successfully
    }
    
    override fun onUserJoined(uid: Int, elapsed: Int) {
        // Another user joined
    }
    
    override fun onUserOffline(uid: Int, reason: Int) {
        // User left channel
    }
    
    override fun onAudioVolumeIndication(speakers: Array<out AudioVolumeInfo>?, totalVolume: Int) {
        // Audio volume updates for UI visualization
    }
})
```

3. **Join Channel:**
```kotlin
// After getting token from /rooms/{roomId}/join
engine.joinChannel(
    agoraToken,      // From API response
    room.agoraChannelName,  // From room object
    null,           // Optional info
    agoraUid        // From API response
)
```

4. **Enable Audio/Video:**
```kotlin
engine.enableAudio()  // Enable audio
engine.enableVideo()  // Enable video
engine.muteLocalAudioStream(false)  // Unmute self
```

5. **Leave Channel:**
```kotlin
engine.leaveChannel()
```

---

## Implementation Checklist

### Authentication
- [ ] Implement registration screen
- [ ] Implement login screen
- [ ] Store tokens securely (EncryptedSharedPreferences/Keystore)
- [ ] Implement token refresh logic
- [ ] Handle token expiry (auto-refresh or redirect to login)
- [ ] Implement logout functionality

### User Profile
- [ ] Display user profile
- [ ] Edit profile (display_name, avatar_url)
- [ ] Show wallet/balance
- [ ] Display transaction history

### Rooms
- [ ] Create room screen
- [ ] Room discovery/list screen (Hot Parties)
- [ ] Room detail screen
- [ ] Join room functionality
- [ ] Leave room functionality
- [ ] Real-time room updates (if WebSocket implemented)

### Seats
- [ ] Display seats UI (chairs grid)
- [ ] Take seat functionality
- [ ] Leave seat functionality
- [ ] Mute/unmute controls
- [ ] Visual indicators for occupied/empty seats

### Agora Integration
- [ ] Initialize Agora SDK
- [ ] Join Agora channel on room join
- [ ] Display video streams
- [ ] Audio controls (mute/unmute)
- [ ] Video controls (enable/disable)
- [ ] Handle connection errors
- [ ] Leave channel on room leave

### Gifts
- [ ] Gift catalog screen
- [ ] Gift selection UI
- [ ] Send gift functionality
- [ ] Gift animation display
- [ ] Update balance after gift send

### Invite System
- [ ] Display invite code
- [ ] Share invite link
- [ ] Apply invite code during registration
- [ ] Show invite statistics

### Error Handling
- [ ] Network error handling
- [ ] Token expiry handling
- [ ] Validation error display
- [ ] Generic error messages
- [ ] Retry logic for failed requests

### UI/UX
- [ ] Loading indicators
- [ ] Pull-to-refresh
- [ ] Pagination for lists
- [ ] Empty states
- [ ] Error states
- [ ] Smooth animations

---

## Sample API Client (Kotlin)

```kotlin
class ChatAuraApiClient(private val context: Context) {
    private val baseUrl = "https://chataura.indsoft24.com/api/v1"
    private val tokenManager = TokenManager(context)
    
    private fun getHeaders(): Map<String, String> {
        val headers = mutableMapOf<String, String>()
        headers["Content-Type"] = "application/json"
        headers["Accept"] = "application/json"
        tokenManager.getAccessToken()?.let {
            headers["Authorization"] = "Bearer $it"
        }
        return headers
    }
    
    suspend fun register(phone: String?, email: String?, password: String, displayName: String?): Result<AuthResponse> {
        return try {
            val request = RegisterRequest(phone, email, password, displayName)
            val response = apiService.register(request)
            if (response.isSuccessful) {
                response.body()?.let {
                    tokenManager.saveTokens(it.data.accessToken, it.data.refreshToken)
                    Result.success(it.data)
                } ?: Result.failure(Exception("Empty response"))
            } else {
                Result.failure(parseError(response))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun login(phone: String?, email: String?, password: String): Result<AuthResponse> {
        // Similar implementation
    }
    
    suspend fun getRooms(page: Int = 1, limit: Int = 20, sort: String = "recent"): Result<RoomsResponse> {
        return try {
            val response = apiService.getRooms(page, limit, sort)
            if (response.isSuccessful) {
                Result.success(response.body()!!)
            } else {
                Result.failure(parseError(response))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun joinRoom(roomId: String): Result<JoinRoomResponse> {
        // Implementation
    }
    
    // Add other methods...
}
```

---

## Testing

### Test Credentials
Create test accounts using the registration endpoint for development/testing.

### Postman Collection
Import the API endpoints into Postman for testing:
- Base URL: `https://chataura.indsoft24.com/api/v1`
- Use environment variables for tokens
- Test all endpoints before mobile app integration

---

## Support & Documentation

- **API Documentation:** See `API_DOCUMENTATION.md`
- **Setup Guide:** See `SETUP.md`
- **Base URL:** `https://chataura.indsoft24.com/api/v1`

---

## Important Notes

1. **Token Management:** Always store tokens securely. Use Android Keystore or EncryptedSharedPreferences.

2. **Token Refresh:** Implement automatic token refresh before expiry. Refresh tokens expire after 30 days.

3. **Agora Tokens:** Agora tokens are generated server-side. Never expose Agora App Certificate to the client.

4. **Error Handling:** Always handle network errors, timeouts, and API errors gracefully.

5. **Rate Limiting:** Be aware of potential rate limits. Implement request queuing if needed.

6. **Real-time Updates:** Currently, real-time events (member join/leave, gifts) are not implemented via WebSocket. Consider polling or implementing WebSocket/Firebase in the future.

7. **Image Uploads:** Avatar and room cover images should be uploaded to a CDN/storage service first, then the URL should be sent to the API.

---

## Next Steps

1. Set up Android project with Retrofit/OkHttp for API calls
2. Integrate Agora SDK for voice/video
3. Implement authentication flow
4. Build room discovery and detail screens
5. Implement seat management UI
6. Add gift sending functionality
7. Test all features thoroughly
8. Implement error handling and edge cases
9. Add loading states and animations
10. Performance optimization and testing

Good luck with the app development! 🚀

