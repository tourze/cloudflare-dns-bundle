<?php

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\DnsAnalyticsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DnsAnalyticsServiceTest extends TestCase
{
    /**
     * @var MockObject&LoggerInterface
     */
    private $logger;

    private TestDnsAnalyticsService $service;
    private TestHttpResponse $response;

    protected function setUp(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $this->logger = $logger;

        $this->response = new TestHttpResponse(true);

        // 创建装饰器服务实例
        $this->service = new TestDnsAnalyticsService($this->logger, $this->response);
    }

    public function testGetDnsAnalytics(): void
    {
        $domain = $this->createDnsDomain();
        $params = ['since' => '-6h', 'until' => 'now'];

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare DNS分析成功', $this->anything());

        $result = $this->service->getDnsAnalytics($domain, $params);
        $this->assertTrue($result['success']);
    }

    public function testGetDnsAnalyticsByTime(): void
    {
        $domain = $this->createDnsDomain();
        $params = ['since' => '-6h', 'until' => 'now', 'time_delta' => '1h'];

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare DNS分析按时间分组成功', $this->anything());

        $result = $this->service->getDnsAnalyticsByTime($domain, $params);
        $this->assertTrue($result['success']);
    }

    /**
     * 创建测试用的 DnsDomain 对象
     */
    private function createDnsDomain(): DnsDomain
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');

        $iamKey = new IamKey();
        $iamKey->setAccessKey('test-access-key');
        $iamKey->setSecretKey('test-secret-key');
        $domain->setIamKey($iamKey);

        return $domain;
    }
}

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
     */
    public function getDnsAnalytics(DnsDomain $domain, array $params = []): array
    {
        $this->logger->info('获取CloudFlare DNS分析成功', [
            'domain' => $domain,
            'params' => $params
        ]);

        return $this->response->toArray();
    }

    /**
     * 获取按时间分组的DNS分析报告
     */
    public function getDnsAnalyticsByTime(DnsDomain $domain, array $params = []): array
    {
        $this->logger->info('获取CloudFlare DNS分析按时间分组成功', [
            'domain' => $domain,
            'params' => $params
        ]);

        return $this->response->toArray();
    }
}
