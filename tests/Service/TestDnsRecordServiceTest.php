<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
#[CoversClass(TestDnsRecordService::class)]
final class TestDnsRecordServiceTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $logger = $this->createMock(LoggerInterface::class);
        $this->logger = $logger;

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);
        $this->repository = $repository;

        $this->service = new TestDnsRecordService($this->logger, $this->repository, true);
    }

    public function testServiceInstantiation(): void
    {
        $this->assertInstanceOf(TestDnsRecordService::class, $this->service);
        $this->assertNotNull($this->service);
    }

    public function testExtractDomain(): void
    {
        $domain = $this->createDnsDomain();
        $this->repository->expects($this->any())
            ->method('findOneBy')
            ->willReturn($domain)
        ;

        $result = $this->service->extractDomain('test.example.com');
        $this->assertInstanceOf(DnsDomain::class, $result);
    }

    public function testRemoveRecord(): void
    {
        $record = $this->createDnsRecord();

        $this->logger->expects($this->once())
            ->method('info')
            ->with('删除CloudFlare域名记录成功', self::anything())
        ;

        $this->service->removeRecord($record);
    }

    public function testCreateRecord(): void
    {
        $domain = $this->createDnsDomain();
        $record = $this->createDnsRecord();

        $this->logger->expects($this->once())
            ->method('info')
            ->with('创建CloudFlare域名记录成功', self::anything())
        ;

        $result = $this->service->createRecord($domain, $record);
        $this->assertTrue($result['success']);
    }

    public function testUpdateRecord(): void
    {
        $record = $this->createDnsRecord();

        $this->logger->expects($this->once())
            ->method('info')
            ->with('更新CloudFlare域名记录成功', self::anything())
        ;

        $result = $this->service->updateRecord($record);
        $this->assertTrue($result['success']);
    }

    public function testBatchRecords(): void
    {
        $domain = $this->createDnsDomain();
        $operations = [
            'create' => $this->createDnsRecord(),
        ];

        $this->logger->expects($this->once())
            ->method('info')
            ->with('批量操作CloudFlare域名记录成功', self::anything())
        ;

        $result = $this->service->batchRecords($domain, $operations);
        $this->assertTrue($result['success']);
    }

    public function testExportRecords(): void
    {
        $domain = $this->createDnsDomain();

        $result = $this->service->exportRecords($domain);
        $this->assertNotNull($result);
    }

    public function testImportRecords(): void
    {
        $domain = $this->createDnsDomain();
        $bindConfig = 'example.com. IN A 127.0.0.1';

        $this->logger->expects($this->once())
            ->method('info')
            ->with('导入CloudFlare域名记录成功', self::anything())
        ;

        $result = $this->service->importRecords($domain, $bindConfig);
        $this->assertTrue($result['success']);
    }

    public function testScanRecords(): void
    {
        $domain = $this->createDnsDomain();

        $this->logger->expects($this->once())
            ->method('info')
            ->with('扫描CloudFlare域名记录成功', self::anything())
        ;

        $result = $this->service->scanRecords($domain);
        $this->assertTrue($result['success']);
    }

    public function testListRecords(): void
    {
        $domain = $this->createDnsDomain();
        $params = ['type' => 'A'];

        $this->logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare域名记录列表成功', self::anything())
        ;

        $result = $this->service->listRecords($domain, $params);
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
        $iamKey->setName('Test Key');
        $iamKey->setAccessKey('test-access-key');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $domain->setIamKey($iamKey);

        return $domain;
    }

    /**
     * 创建测试用的 DnsRecord 对象
     */
    private function createDnsRecord(): DnsRecord
    {
        $record = new DnsRecord();
        $record->setRecord('test');
        $record->setType(DnsRecordType::A);
        $record->setContent('127.0.0.1');
        $record->setDomain($this->createDnsDomain());

        return $record;
    }
}
