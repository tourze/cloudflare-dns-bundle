<?php

namespace CloudflareDnsBundle\Message;

/**
 * 同步DNS记录到远程的消息
 */
class SyncDnsRecordToRemoteMessage
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
