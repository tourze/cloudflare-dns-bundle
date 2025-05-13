<?php

namespace CloudflareDnsBundle\Service;

use Tourze\DDNSContracts\DNSProviderInterface;
use Tourze\DDNSContracts\ExpectResolveResult;

class DNSProvider implements DNSProviderInterface
{
    public function getName(): string
    {
        return 'cloudflare-dns';
    }

    public function check(ExpectResolveResult $result): bool
    {
        // TODO: Implement check() method.
    }

    public function resolve(ExpectResolveResult $result): void
    {
        // TODO: Implement resolve() method.
    }
}
