<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\DnsAnalyticsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DnsAnalyticsService::class)]
#[RunTestsInSeparateProcesses]
final class DnsAnalyticsServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Tests don't require special setup
    }

    public function testGetDnsAnalytics(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $response = new TestHttpResponse(true);

        // 创建装饰器服务实例
        $service = new TestDnsAnalyticsService($logger, $response);

        $domain = $this->createDnsDomain();
        $params = ['since' => '-6h', 'until' => 'now'];

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare DNS分析成功', self::anything())
        ;

        $result = $service->getDnsAnalytics($domain, $params);
        $this->assertTrue($result['success']);
    }

    public function testGetDnsAnalyticsByTime(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $response = new TestHttpResponse(true);

        // 创建装饰器服务实例
        $service = new TestDnsAnalyticsService($logger, $response);

        $domain = $this->createDnsDomain();
        $params = ['since' => '-6h', 'until' => 'now', 'time_delta' => '1h'];

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare DNS分析按时间分组成功', self::anything())
        ;

        $result = $service->getDnsAnalyticsByTime($domain, $params);
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
