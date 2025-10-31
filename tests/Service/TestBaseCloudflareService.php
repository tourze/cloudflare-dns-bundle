<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Abstract\BaseCloudflareService;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Service\CloudflareHttpClient;

/**
 * 可测试的 BaseCloudflareService 子类，公开受保护的方法
 */
class TestBaseCloudflareService extends BaseCloudflareService
{
    /**
     * 公开获取 CloudflareHttpClient 的方法
     */
    public function exposedGetCloudFlareClient(DnsDomain $domain): CloudflareHttpClient
    {
        return $this->getCloudFlareClient($domain);
    }

    /**
     * 公开处理响应的方法
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function exposedHandleResponse(array $result, string $errorMessage, array $context = []): array
    {
        return $this->handleResponse($result, $errorMessage, $context);
    }
}
