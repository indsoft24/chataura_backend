# Chat Aura API Documentation

## Base URL
```
https://chataura.indsoft24.com/api/v1
```

## Authentication
All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer <access_token>
```

## Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... }
}
```

### Success Response (List with Pagination)
```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "total": 100,
    "page": 1,
    "limit": 20
  }
}
```

### Error Response
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message"
  }
}
```

## Endpoints

### Authentication

#### POST /auth/register
Register a new user.

**Request Body:**
```json
{
  "phone": "+1234567890",  // or "email": "user@example.com"
  "password": "password123",
  "display_name": "John Doe",  // optional
  "invite_code": "ABC12345"  // optional
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "uuid",
      "phone": "+1234567890",
      "display_name": "John Doe",
      "level": 1,
      "exp": 0,
      "coin_balance": 0,
      ...
    },
    "access_token": "jwt_token",
    "refresh_token": "refresh_token",
    "expires_in": 3600
  }
}
```

#### POST /auth/login
Login with phone/email and password.

**Request Body:**
```json
{
  "phone": "+1234567890",  // or "email": "user@example.com"
  "password": "password123"
}
```

**Response:** Same as register.

#### POST /auth/refresh
Refresh access token.

**Request Body:**
```json
{
  "refresh_token": "refresh_token"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "access_token": "new_jwt_token",
    "refresh_token": "new_refresh_token",
    "expires_in": 3600
  }
}
```

#### POST /auth/logout
Logout and invalidate refresh token.

**Request Body:**
```json
{
  "refresh_token": "refresh_token"
}
```

### User

#### GET /users/me
Get current user profile.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "phone": "+1234567890",
    "email": "user@example.com",
    "display_name": "John Doe",
    "avatar_url": "https://...",
    "level": 5,
    "exp": 1250,
    "coin_balance": 5000,
    ...
  }
}
```

#### PATCH /users/me
Update current user profile.

**Request Body:**
```json
{
  "display_name": "New Name",  // optional
  "avatar_url": "https://..."  // optional
}
```

#### GET /users/me/wallet
Get wallet/balance information.

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

#### GET /users/me/transactions
Get user transactions.

**Query Parameters:**
- `type`: `sent` | `received` | `all` (default: `all`)
- `page`: integer (default: 1)
- `limit`: integer (default: 20, max: 100)

### Rooms

#### POST /rooms
Create a new room.

**Request Body:**
```json
{
  "title": "Party Room #1",
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
    "id": "uuid",
    "title": "Party Room #1",
    "owner_id": "uuid",
    "agora_channel_name": "room_uuid",
    "max_seats": 8,
    "is_live": true,
    ...
  }
}
```

#### GET /rooms
List rooms (Hot Parties / Discovery).

**Query Parameters:**
- `page`: integer (default: 1)
- `limit`: integer (default: 20, max: 100)
- `sort`: `popular` | `recent` (default: `recent`)

#### GET /rooms/:roomId
Get a single room.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "title": "Party Room #1",
    "members_count": 5,
    "current_seats": [
      {
        "seat_index": 0,
        "user_id": "uuid",
        "display_name": "John",
        "avatar_url": "https://...",
        "is_muted": false
      },
      ...
    ],
    ...
  }
}
```

#### PATCH /rooms/:roomId
Update a room (owner only).

**Request Body:**
```json
{
  "title": "New Title",  // optional
  "max_seats": 10,  // optional
  "settings": { ... },  // optional
  "cover_image_url": "https://..."  // optional
}
```

#### DELETE /rooms/:roomId
Close/delete a room (owner only).

#### POST /rooms/:roomId/join
Join a room.

**Response:**
```json
{
  "success": true,
  "data": {
    "room": { ... },
    "member": { ... },
    "agora_token": "agora_rtc_token",
    "agora_uid": 12345
  }
}
```

#### POST /rooms/:roomId/leave
Leave a room.

#### GET /rooms/:roomId/token
Get Agora RTC token for a room.

**Query Parameters:**
- `uid`: integer (optional, auto-generated if not provided)

**Response:**
```json
{
  "success": true,
  "data": {
    "agora_token": "agora_rtc_token",
    "agora_uid": 12345,
    "expires_in": 3600
  }
}
```

### Seats

#### GET /rooms/:roomId/seats
List all seats in a room.

**Response:**
```json
{
  "success": true,
  "data": {
    "seats": [
      {
        "seat_index": 0,
        "user_id": "uuid",
        "display_name": "John",
        "avatar_url": "https://...",
        "is_muted": false
      },
      ...
    ],
    "max_seats": 8
  }
}
```

#### POST /rooms/:roomId/seats/:seatIndex/take
Take a seat.

**Response:**
```json
{
  "success": true,
  "data": {
    "seat_index": 0,
    "user_id": "uuid",
    "is_muted": false
  }
}
```

#### POST /rooms/:roomId/seats/leave
Leave current seat.

#### PATCH /rooms/:roomId/seats/:seatIndex/mute
Mute/unmute a seat.

**Request Body:**
```json
{
  "muted": true
}
```

**Note:** Can be done by seat owner (self) or room owner/host.

### Gifts

#### GET /gifts
Get gift catalog.

**Response:**
```json
{
  "success": true,
  "data": {
    "gifts": [
      {
        "id": "uuid",
        "name": "Rose",
        "coin_price": 10,
        "image_url": "https://...",
        "animation_type": "fade"
      },
      ...
    ]
  }
}
```

#### POST /rooms/:roomId/gifts/send
Send a gift.

**Request Body:**
```json
{
  "gift_id": "uuid",
  "receiver_id": "uuid",
  "quantity": 1  // optional, default: 1
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "transaction_id": "uuid",
    "coin_amount": 10,
    "sender_balance_after": 4990,
    "receiver_balance_after": 5010
  }
}
```

### Invite

#### GET /users/me/invite
Get invite information.

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

#### POST /invite/apply
Apply an invite code (after registration).

**Request Body:**
```json
{
  "invite_code": "ABC12345"
}
```

## Error Codes

- `UNAUTHORIZED` (401): Missing or invalid token
- `FORBIDDEN` (403): Insufficient permissions
- `NOT_FOUND` (404): Resource not found
- `CONFLICT` (409): Resource conflict (e.g., seat already taken)
- `VALIDATION_ERROR` (400): Validation failed
- `INVALID_CREDENTIALS` (401): Invalid login credentials
- `INSUFFICIENT_BALANCE` (400): Not enough coins
- `ROOM_CREATE_FAILED` (500): Room creation failed
- `JOIN_FAILED` (500): Failed to join room
- `SEND_GIFT_FAILED` (500): Failed to send gift

## HTTP Status Codes

- `200`: Success
- `400`: Bad Request (validation errors)
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `409`: Conflict
- `500`: Internal Server Error

