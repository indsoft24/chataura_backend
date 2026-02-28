<?php

namespace App\Services\Agora;

/**
 * Official Agora token format (006). Ported from AgoraIO/Tools DynamicKey PHP.
 * Used by RtcTokenBuilder to generate tokens the Android/iOS SDKs accept.
 */
class Message
{
    public $salt;
    public $ts;
    public $privileges = [];

    public function __construct()
    {
        $this->salt = random_int(0, 100000);
        $this->ts = time() + 24 * 3600;
    }

    public function packContent(): array
    {
        $buffer = array_values(unpack('C*', pack('V', $this->salt)));
        $buffer = array_merge($buffer, array_values(unpack('C*', pack('V', $this->ts))));
        $buffer = array_merge($buffer, array_values(unpack('C*', pack('v', count($this->privileges)))));
        foreach ($this->privileges as $key => $value) {
            $buffer = array_merge($buffer, array_values(unpack('C*', pack('v', $key))));
            $buffer = array_merge($buffer, array_values(unpack('C*', pack('V', $value))));
        }
        return $buffer;
    }
}

class AccessToken
{
    public const Privileges = [
        'kJoinChannel' => 1,
        'kPublishAudioStream' => 2,
        'kPublishVideoStream' => 3,
        'kPublishDataStream' => 4,
        'kRtmLogin' => 1000,
    ];

    public $appID;
    public $appCertificate;
    public $channelName;
    public $uid;
    /** @var Message */
    public $message;

    public function __construct()
    {
        $this->message = new Message();
    }

    public function setUid($uid): void
    {
        $this->uid = ($uid === 0 || $uid === '0') ? '' : (string) $uid;
    }

    public static function init(string $appID, string $appCertificate, string $channelName, $uid): ?self
    {
        if ($appID === '' || $appCertificate === '' || $channelName === '') {
            return null;
        }
        $t = new self();
        $t->appID = $appID;
        $t->appCertificate = $appCertificate;
        $t->channelName = $channelName;
        $t->setUid($uid);
        $t->message = new Message();
        return $t;
    }

    public function addPrivilege(int $key, int $expireTimestamp): self
    {
        $this->message->privileges[$key] = $expireTimestamp;
        return $this;
    }

    public function build(): string
    {
        $msg = $this->message->packContent();
        $appIdBytes = array_values(unpack('C*', $this->appID));
        $channelBytes = array_values(unpack('C*', $this->channelName));
        $uidBytes = array_values(unpack('C*', $this->uid));
        $val = array_merge($appIdBytes, $channelBytes, $uidBytes, $msg);
        $sig = hash_hmac('sha256', implode(array_map('chr', $val)), $this->appCertificate, true);

        $crcChannel = crc32($this->channelName) & 0xffffffff;
        $crcUid = crc32($this->uid) & 0xffffffff;
        $packedSig = self::packString($sig);
        $content = array_merge(
            array_values(unpack('C*', $packedSig)),
            array_values(unpack('C*', pack('V', $crcChannel))),
            array_values(unpack('C*', pack('V', $crcUid))),
            array_values(unpack('C*', pack('v', count($msg)))),
            $msg
        );
        return '006' . $this->appID . base64_encode(implode(array_map('chr', $content)));
    }

    private static function packString(string $value): string
    {
        return pack('v', strlen($value)) . $value;
    }
}
