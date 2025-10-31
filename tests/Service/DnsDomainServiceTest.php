<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\DnsDomainService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DnsDomainService::class)]
#[RunTestsInSeparateProcesses]
final class DnsDomainServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Tests don't require special setup
    }

    public function testListDomains(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $response = new TestHttpResponse(true);

        // 创建装饰器服务实例
        $service = new TestDnsDomainService($logger, $response);

        $domain = $this->createDnsDomain();

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare域名列表成功', self::anything())
        ;

        $result = $service->listDomains($domain);
        $this->assertTrue($result['success']);
    }

    public function testGetDomain(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $response = new TestHttpResponse(true);

        // 创建装饰器服务实例
        $service = new TestDnsDomainService($logger, $response);

        $domain = $this->createDnsDomain();
        $domainName = 'example.com';

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare域名详情成功', self::anything())
        ;

        $result = $service->getDomain($domain, $domainName);
        $this->assertTrue($result['success']);
    }

    public function testLookupZoneId(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $response = new TestHttpResponse(true);
        $service = new TestDnsDomainService($logger, $response);
        $domain = $this->createDnsDomain();

        $logger->expects($this->once())
            ->method('info')
            ->with('查询Zone ID成功', self::anything())
        ;

        $result = $service->lookupZoneId($domain);
        $this->assertSame('test-zone-id', $result);
    }

    public function testSyncZoneId(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $response = new TestHttpResponse(true);
        $service = new TestDnsDomainService($logger, $response);
        $domain = $this->createDnsDomain();
        $domainData = ['id' => 'custom-zone-id'];

        $logger->expects($this->once())
            ->method('info')
            ->with('同步Zone ID成功', self::anything())
        ;

        $result = $service->syncZoneId($domain, $domainData);
        $this->assertSame('custom-zone-id', $result);
        $this->assertSame('custom-zone-id', $domain->getZoneId());
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
        $iamKey->setName('Test Key');
        $iamKey->setAccessKey('test-access-key');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $domain->setIamKey($iamKey);

        return $domain;
    }
}
