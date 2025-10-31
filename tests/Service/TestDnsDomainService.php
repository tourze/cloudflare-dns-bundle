<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use Psr\Log\LoggerInterface;

/**
 * DnsDomainService 的测试装饰器
 */
class TestDnsDomainService
{
    private TestHttpResponse $response;

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, TestHttpResponse $response)
    {
        $this->logger = $logger;
        $this->response = $response;
    }

    /**
     * 获取域名列表
     *
     * @return array<string, mixed>
     */
    public function listDomains(DnsDomain $domain): array
    {
        $this->logger->info('获取CloudFlare域名列表成功', [
            'domain' => $domain,
        ]);

        return $this->response->toArray();
    }

    /**
     * 获取域名详情
     *
     * @return array<string, mixed>
     */
    public function getDomain(DnsDomain $domain, string $domainName): array
    {
        $this->logger->info('获取CloudFlare域名详情成功', [
            'domain' => $domain,
            'domainName' => $domainName,
        ]);

        return $this->response->toArray();
    }

    /**
     * 查询域名的Zone ID
     */
    public function lookupZoneId(DnsDomain $domain): ?string
    {
        $responseData = $this->response->toArray();
        if (isset($responseData['success']) && true === $responseData['success']) {
            $this->logger->info('查询Zone ID成功', [
                'domain' => $domain->getName(),
            ]);

            return 'test-zone-id';
        }

        $this->logger->warning('未找到域名的Zone ID', [
            'domain' => $domain->getName(),
        ]);

        return null;
    }

    /**
     * 同步域名的Zone ID
     */
    /**
     * @param array<string, mixed>|null $domainData
     */
    public function syncZoneId(DnsDomain $domain, ?array $domainData = null): ?string
    {
        $responseData = $this->response->toArray();
        if (isset($responseData['success']) && true === $responseData['success']) {
            $zoneId = $domainData['id'] ?? 'test-zone-id';

            // 确保zone ID是字符串类型
            if (is_string($zoneId)) {
                $domain->setZoneId($zoneId);

                $this->logger->info('同步Zone ID成功', [
                    'domain' => $domain->getName(),
                    'zoneId' => $zoneId,
                ]);

                return $zoneId;
            }
            $defaultZoneId = 'test-zone-id';
            $domain->setZoneId($defaultZoneId);

            return $defaultZoneId;
        }

        $this->logger->warning('同步Zone ID失败', [
            'domain' => $domain->getName(),
        ]);

        return null;
    }
}
