<?php

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DnsRecordServiceTest extends TestCase
{
    /**
     * @var MockObject&LoggerInterface
     */
    private $logger;

    /**
     * @var MockObject&DnsDomainRepository
     */
    private $repository;

    private TestDnsRecordService $service;
    private TestDnsRecordService $failureService;

    protected function setUp(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $this->logger = $logger;

        /** @var DnsDomainRepository&MockObject $repository */
        $repository = $this->createMock(DnsDomainRepository::class);
        $this->repository = $repository;

        // 配置 repository mock 返回域名
        $this->repository->method('findOneBy')
            ->willReturnCallback(function ($criteria) {
                if (isset($criteria['name']) && $criteria['name'] === 'example.com') {
                    return $this->createDnsDomain();
                }
                return null;
            });

        // 创建测试服务实例
        $this->service = new TestDnsRecordService($this->logger, $this->repository);
        $this->failureService = new TestDnsRecordService($this->logger, $this->repository, false);
    }

    public function testExtractDomain(): void
    {
        $domain = $this->service->extractDomain('sub.example.com');

        $this->assertInstanceOf(DnsDomain::class, $domain);
        $this->assertEquals('example.com', $domain->getName());
    }

    public function testExtractDomainNotFound(): void
    {
        $domain = $this->service->extractDomain('sub.notfound.com');

        $this->assertNull($domain);
    }

    public function testRemoveRecord(): void
    {
        $record = $this->createDnsRecord();

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with('删除CloudFlare域名记录成功', $this->anything());

        $this->service->removeRecord($record);
    }

    public function testCreateRecord(): void
    {
        $domain = $this->createDnsDomain();
        $record = $this->createDnsRecord();

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with('创建CloudFlare域名记录成功', $this->anything());

        $result = $this->service->createRecord($domain, $record);
        $this->assertTrue($result['success']);
    }

    public function testUpdateRecord(): void
    {
        $record = $this->createDnsRecord();
        $record->setDomain($this->createDnsDomain());

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with('更新CloudFlare域名记录成功', $this->anything());

        $result = $this->service->updateRecord($record);
        $this->assertTrue($result['success']);
    }

    public function testBatchRecords(): void
    {
        $domain = $this->createDnsDomain();
        $operations = [
            'posts' => [$this->createDnsRecord()],
        ];

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with('批量操作CloudFlare域名记录成功', $this->anything());

        $result = $this->service->batchRecords($domain, $operations);
        $this->assertTrue($result['success']);
    }

    public function testListRecords(): void
    {
        $domain = $this->createDnsDomain();
        $params = ['page' => 1, 'per_page' => 20];

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare域名记录列表成功', $this->anything());

        $result = $this->service->listRecords($domain, $params);
        $this->assertTrue($result['success']);
    }

    /**
     * 测试当 API 返回失败时的处理
     */
    public function testApiResponseFailure(): void
    {
        $domain = $this->createDnsDomain();
        $record = $this->createDnsRecord();
        $record->setDomain($domain);

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('error')
            ->with('创建CloudFlare域名记录失败', $this->anything());

        $this->expectException(\RuntimeException::class);
        $this->failureService->createRecord($domain, $record);
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

    /**
     * 创建测试用的 DnsRecord 对象
     */
    private function createDnsRecord(): DnsRecord
    {
        $record = new DnsRecord();
        $record->setRecordId('test-record-id');
        $record->setRecord('test.example.com');
        $record->setContent('192.0.2.1');
        $record->setTtl(120);
        $record->setProxy(false);
        return $record;
    }
}
