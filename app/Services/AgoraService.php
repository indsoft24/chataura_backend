<?php

namespace App\Services;

use App\Models\Room;
use App\Services\Agora\RtcTokenBuilder;

class AgoraService
{
    private string $appId;
    private string $appCertificate;
    private int $tokenExpiry; // in seconds

    public function __construct()
    {
        $this->appId = config('services.agora.app_id', '');
        $this->appCertificate = config('services.agora.app_certificate', '');
        $this->tokenExpiry = config('services.agora.token_expiry', 3600); // 1 hour
    }

    /**
     * Generate RTC token for a room using official Agora token format (006).
     * Custom token format caused SIGSEGV in Android SDK; this format is accepted by the SDK.
     */
    public function generateRtcToken(Room $room, int $uid): string
    {
        $channelName = $room->agora_channel_name;
        $privilegeExpireTs = time() + $this->tokenExpiry;

        return RtcTokenBuilder::buildTokenWithUid(
            $this->appId,
            $this->appCertificate,
            $channelName,
            $uid,
            RtcTokenBuilder::RolePublisher,
            $privilegeExpireTs
        );
    }

    /**
     * Generate RTC token for an arbitrary channel (e.g. 1-to-1 call).
     */
    public function generateRtcTokenForChannel(string $channelName, int $uid): string
    {
        $privilegeExpireTs = time() + $this->tokenExpiry;
        return RtcTokenBuilder::buildTokenWithUid(
            $this->appId,
            $this->appCertificate,
            $channelName,
            $uid,
            RtcTokenBuilder::RolePublisher,
            $privilegeExpireTs
        );
    }

    /**
     * Generate a unique UID for a user in a room or call.
     */
    public function generateUid(): int
    {
        return random_int(1, 2147483647);
    }
}

