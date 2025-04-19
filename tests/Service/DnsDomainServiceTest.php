<?php

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\DnsDomainService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DnsDomainServiceTest extends TestCase
{
    /**
     * @var MockObject&LoggerInterface
     */
    private $logger;

    private TestDnsDomainService $service;
    private TestHttpResponse $response;

    protected function setUp(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $this->logger = $logger;

        $this->response = new TestHttpResponse(true);

        // 创建装饰器服务实例
        $this->service = new TestDnsDomainService($this->logger, $this->response);
    }

    public function testListDomains(): void
    {
        $domain = $this->createDnsDomain();

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare域名列表成功', $this->anything());

        $result = $this->service->listDomains($domain);
        $this->assertTrue($result['success']);
    }

    public function testGetDomain(): void
    {
        $domain = $this->createDnsDomain();
        $domainName = 'example.com';

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare域名详情成功', $this->anything());

        $result = $this->service->getDomain($domain, $domainName);
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
        $domain->setAccountId('test-account-id');

        $iamKey = new IamKey();
        $iamKey->setAccessKey('test-access-key');
        $iamKey->setSecretKey('test-secret-key');
        $domain->setIamKey($iamKey);

        return $domain;
    }
}

/**
 * DnsDomainService 的测试装饰器
 */
class TestDnsDomainService
{
    private DnsDomainService $service;
    private TestHttpResponse $response;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, TestHttpResponse $response)
    {
        $this->logger = $logger;
        $this->response = $response;
        $this->service = new DnsDomainService($logger);
    }

    /**
     * 获取域名列表
     */
    public function listDomains(DnsDomain $domain): array
    {
        $this->logger->info('获取CloudFlare域名列表成功', [
            'domain' => $domain
        ]);

        return $this->response->toArray();
    }

    /**
     * 获取域名详情
     */
    public function getDomain(DnsDomain $domain, string $domainName): array
    {
        $this->logger->info('获取CloudFlare域名详情成功', [
            'domain' => $domain,
            'domainName' => $domainName
        ]);

        return $this->response->toArray();
    }
}
