<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Message;

/**
 * 同步DNS记录到远程的消息
 */
final class SyncDnsRecordToRemoteMessage
{
    public function __construct(
        private readonly int $dnsRecordId,
    ) {
    }

    public function getDnsRecordId(): int
    {
        return $this->dnsRecordId;
    }
}
