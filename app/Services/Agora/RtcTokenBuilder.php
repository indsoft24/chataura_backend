<?php

namespace App\Services\Agora;

/**
 * Builds RTC token in official Agora format (006). Ported from AgoraIO/Tools.
 * Use this so the Android/iOS SDK accepts the token and does not crash.
 */
class RtcTokenBuilder
{
    public const RoleAttendee = 0;
    public const RolePublisher = 1;
    public const RoleSubscriber = 2;
    public const RoleAdmin = 101;

    /**
     * Build RTC token with numeric uid.
     *
     * @param string $appId Agora App ID (from dashboard)
     * @param string $appCertificate Agora App Certificate
     * @param string $channelName Channel name
     * @param int $uid User ID (1 to 2^32-1)
     * @param int $role RolePublisher = 1 for host, RoleSubscriber = 2 for audience
     * @param int $privilegeExpireTs Unix timestamp when token expires
     */
    public static function buildTokenWithUid(
        string $appId,
        string $appCertificate,
        string $channelName,
        int $uid,
        int $role,
        int $privilegeExpireTs
    ): string {
        $token = AccessToken::init($appId, $appCertificate, $channelName, $uid);
        if ($token === null) {
            return '';
        }
        $token->addPrivilege(AccessToken::Privileges['kJoinChannel'], $privilegeExpireTs);
        if (in_array($role, [self::RoleAttendee, self::RolePublisher, self::RoleAdmin], true)) {
            $token->addPrivilege(AccessToken::Privileges['kPublishVideoStream'], $privilegeExpireTs);
            $token->addPrivilege(AccessToken::Privileges['kPublishAudioStream'], $privilegeExpireTs);
            $token->addPrivilege(AccessToken::Privileges['kPublishDataStream'], $privilegeExpireTs);
        }
        return $token->build();
    }
}
