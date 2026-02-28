<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class JwtService
{
    private string $secret;
    private int $accessTokenExpiry; // in seconds
    private int $refreshTokenExpiry; // in seconds

    public function __construct()
    {
        $this->secret = config('app.key');
        $this->accessTokenExpiry = config('auth.access_token_expiry', 3600); // 1 hour
        $this->refreshTokenExpiry = config('auth.refresh_token_expiry', 2592000); // 30 days
    }

    /**
     * Generate JWT token for a user.
     */
    public function generateAccessToken(User $user): string
    {
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = base64_encode(json_encode([
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + $this->accessTokenExpiry,
            'type' => 'access',
        ]));
        $signature = hash_hmac('sha256', "$header.$payload", $this->secret, true);
        $signature = base64_encode($signature);

        return "$header.$payload.$signature";
    }

    /**
     * Generate refresh token and store it.
     */
    public function generateRefreshToken(User $user): string
    {
        $token = Str::random(64);
        
        $user->refreshTokens()->create([
            'token' => hash('sha256', $token),
            'expires_at' => now()->addSeconds($this->refreshTokenExpiry),
        ]);

        return $token;
    }

    /**
     * Verify and decode JWT token.
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        $expectedSignature = hash_hmac('sha256', "$header.$payload", $this->secret, true);
        $expectedSignature = base64_encode($expectedSignature);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $decoded = json_decode(base64_decode($payload), true);
        
        if (!$decoded || isset($decoded['exp']) && $decoded['exp'] < time()) {
            return null;
        }

        return $decoded;
    }

    /**
     * Get user from access token.
     */
    public function getUserFromToken(string $token): ?User
    {
        $decoded = $this->verifyToken($token);
        
        if (!$decoded || $decoded['type'] !== 'access') {
            return null;
        }

        return User::find($decoded['sub']);
    }

    /**
     * Verify refresh token and get user.
     */
    public function verifyRefreshToken(string $token): ?User
    {
        $hashedToken = hash('sha256', $token);
        
        $refreshToken = \App\Models\RefreshToken::where('token', $hashedToken)
            ->where('expires_at', '>', now())
            ->first();

        if (!$refreshToken) {
            return null;
        }

        return $refreshToken->user;
    }

    /**
     * Revoke refresh token.
     */
    public function revokeRefreshToken(string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        
        return \App\Models\RefreshToken::where('token', $hashedToken)->delete() > 0;
    }
}

