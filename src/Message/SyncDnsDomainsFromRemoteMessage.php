<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Message;

/**
 * 从远程同步域名DNS记录的消息
 */
class SyncDnsDomainsFromRemoteMessage
{
    public function __construct(
        private readonly int $domainId,
    ) {
    }

    public function getDomainId(): int
    {
        return $this->domainId;
    }
}
