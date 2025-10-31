<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use Psr\Log\LoggerInterface;

/**
 * DnsAnalyticsService 的测试装饰器
 */
class TestDnsAnalyticsService
{
    private TestHttpResponse $response;

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, TestHttpResponse $response)
    {
        $this->logger = $logger;
        $this->response = $response;
    }

    /**
     * 获取DNS分析报告
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getDnsAnalytics(DnsDomain $domain, array $params = []): array
    {
        $this->logger->info('获取CloudFlare DNS分析成功', [
            'domain' => $domain,
            'params' => $params,
        ]);

        return $this->response->toArray();
    }

    /**
     * 获取按时间分组的DNS分析报告
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getDnsAnalyticsByTime(DnsDomain $domain, array $params = []): array
    {
        $this->logger->info('获取CloudFlare DNS分析按时间分组成功', [
            'domain' => $domain,
            'params' => $params,
        ]);

        return $this->response->toArray();
    }
}
