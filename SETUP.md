# Chat Aura API Setup Guide

## Prerequisites

- PHP 8.2 or higher
- Composer
- Database (MySQL/PostgreSQL/SQLite)
- Agora account (for voice/video features)

## Installation

1. **Install dependencies:**
```bash
composer install
npm install
```

2. **Configure environment:**
Copy `.env.example` to `.env` (if it exists) or create a `.env` file with the following:

```env
APP_NAME="Chat Aura"
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://chataura.indsoft24.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chataura
DB_USERNAME=your_username
DB_PASSWORD=your_password

# JWT Token Configuration
ACCESS_TOKEN_EXPIRY=3600
REFRESH_TOKEN_EXPIRY=2592000

# Agora Configuration
AGORA_APP_ID=your_agora_app_id
AGORA_APP_CERTIFICATE=your_agora_app_certificate
AGORA_TOKEN_EXPIRY=3600
```

3. **Generate application key:**
```bash
php artisan key:generate
```

4. **Run migrations:**
```bash
php artisan migrate
```

5. **Seed gift types:**
```bash
php artisan db:seed --class=GiftTypeSeeder
```

## Agora Setup

1. Sign up for an Agora account at https://www.agora.io/
2. Create a new project
3. Get your App ID and App Certificate
4. Add them to your `.env` file:
   - `AGORA_APP_ID`
   - `AGORA_APP_CERTIFICATE`

**Note:** The current Agora token generation implementation is simplified. For production, consider using the official Agora PHP SDK or implementing the full token generation algorithm as per Agora's documentation.

## API Base URL

The API is accessible at:
```
https://chataura.indsoft24.com/api/v1
```

## Testing the API

You can test the API using tools like Postman or cURL. Example:

```bash
# Register a new user
curl -X POST https://chataura.indsoft24.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+1234567890",
    "password": "password123",
    "display_name": "Test User"
  }'
```

## Database Schema

The following tables are created:
- `users` - User accounts
- `rooms` - Party rooms
- `room_members` - Room membership tracking
- `seats` - Seat management
- `gift_types` - Gift catalog
- `transactions` - Gift transactions
- `refresh_tokens` - JWT refresh tokens

## Security Notes

1. **JWT Tokens:** The current implementation uses a simple JWT-like token. For production, consider using a more robust JWT library like `tymon/jwt-auth` or `laravel/sanctum`.

2. **Agora Tokens:** Never expose your Agora App Certificate to the client. Always generate tokens on the server side.

3. **Rate Limiting:** Consider adding rate limiting middleware for sensitive endpoints (login, register, send gift).

4. **HTTPS:** Always use HTTPS in production.

## Troubleshooting

### API routes not working
- Ensure `routes/api.php` is registered in `bootstrap/app.php`
- Check that your web server is configured to route requests to `public/index.php`

### Database errors
- Verify database credentials in `.env`
- Run `php artisan migrate:fresh` to reset the database (WARNING: deletes all data)

### Agora token errors
- Verify `AGORA_APP_ID` and `AGORA_APP_CERTIFICATE` are set correctly
- Check Agora dashboard for project status

## Next Steps

1. Implement rate limiting
2. Add logging for audit trails
3. Implement WebSocket/Firebase for real-time events
4. Add file upload for avatars and room covers
5. Implement game state management (Ludo, Teen Patti)
6. Add push notifications using FCM

