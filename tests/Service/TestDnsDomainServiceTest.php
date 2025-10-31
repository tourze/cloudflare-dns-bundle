<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
#[CoversClass(TestDnsDomainService::class)]
final class TestDnsDomainServiceTest extends TestCase
{
    /**
     * @var MockObject&LoggerInterface
     */
    private $logger;

    private TestDnsDomainService $service;

    private TestHttpResponse $response;

    protected function setUp(): void
    {
        parent::setUp();
        $logger = $this->createMock(LoggerInterface::class);
        $this->logger = $logger;

        $this->response = new TestHttpResponse(true);
        $this->service = new TestDnsDomainService($this->logger, $this->response);
    }

    public function testServiceInstantiation(): void
    {
        $this->assertInstanceOf(TestDnsDomainService::class, $this->service);
        $this->assertNotNull($this->service);
    }

    public function testListDomains(): void
    {
        $domain = $this->createDnsDomain();

        $this->logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare域名列表成功', self::anything())
        ;

        $result = $this->service->listDomains($domain);
        $this->assertTrue($result['success']);
    }

    public function testGetDomain(): void
    {
        $domain = $this->createDnsDomain();

        $this->logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare域名详情成功', self::anything())
        ;

        $result = $this->service->getDomain($domain, 'example.com');
        $this->assertTrue($result['success']);
    }

    public function testLookupZoneId(): void
    {
        $domain = $this->createDnsDomain();

        $this->logger->expects($this->once())
            ->method('info')
            ->with('查询Zone ID成功', self::anything())
        ;

        $result = $this->service->lookupZoneId($domain);
        $this->assertEquals('test-zone-id', $result);
    }

    public function testSyncZoneId(): void
    {
        $domain = $this->createDnsDomain();

        $this->logger->expects($this->once())
            ->method('info')
            ->with('同步Zone ID成功', self::anything())
        ;

        $result = $this->service->syncZoneId($domain);
        $this->assertEquals('test-zone-id', $result);
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
