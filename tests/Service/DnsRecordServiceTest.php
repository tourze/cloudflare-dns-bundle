<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Exception\TestServiceException;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DnsRecordService::class)]
#[RunTestsInSeparateProcesses]
final class DnsRecordServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Tests don't require special setup
    }

    public function testExtractDomain(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);

        // 配置 repository mock 返回域名
        $repository->method('findOneBy')
            ->willReturnCallback(function ($criteria) {
                if (isset($criteria['name']) && 'example.com' === $criteria['name']) {
                    return $this->createDnsDomain();
                }

                return null;
            })
        ;

        // 创建测试服务实例
        $service = new TestDnsRecordService($logger, $repository);

        $domain = $service->extractDomain('sub.example.com');

        $this->assertInstanceOf(DnsDomain::class, $domain);
        $this->assertEquals('example.com', $domain->getName());
    }

    public function testExtractDomainNotFound(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);

        // 配置 repository mock 返回域名
        $repository->method('findOneBy')
            ->willReturnCallback(function ($criteria) {
                if (isset($criteria['name']) && 'example.com' === $criteria['name']) {
                    return $this->createDnsDomain();
                }

                return null;
            })
        ;

        // 创建测试服务实例
        $service = new TestDnsRecordService($logger, $repository);

        $domain = $service->extractDomain('sub.notfound.com');

        $this->assertNull($domain);
    }

    public function testRemoveRecord(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);

        // 创建测试服务实例
        $service = new TestDnsRecordService($logger, $repository);

        $record = $this->createDnsRecord();

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('info')
            ->with('删除CloudFlare域名记录成功', self::anything())
        ;

        $service->removeRecord($record);
    }

    public function testCreateRecord(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);

        // 创建测试服务实例
        $service = new TestDnsRecordService($logger, $repository);

        $domain = $this->createDnsDomain();
        $record = $this->createDnsRecord();

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('info')
            ->with('创建CloudFlare域名记录成功', self::anything())
        ;

        $result = $service->createRecord($domain, $record);
        $this->assertTrue($result['success']);
    }

    public function testUpdateRecord(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);

        // 创建测试服务实例
        $service = new TestDnsRecordService($logger, $repository);

        $record = $this->createDnsRecord();
        $record->setDomain($this->createDnsDomain());

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('info')
            ->with('更新CloudFlare域名记录成功', self::anything())
        ;

        $result = $service->updateRecord($record);
        $this->assertTrue($result['success']);
    }

    public function testBatchRecords(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);

        // 创建测试服务实例
        $service = new TestDnsRecordService($logger, $repository);

        $domain = $this->createDnsDomain();
        $operations = [
            'posts' => [$this->createDnsRecord()],
        ];

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('info')
            ->with('批量操作CloudFlare域名记录成功', self::anything())
        ;

        $result = $service->batchRecords($domain, $operations);
        $this->assertTrue($result['success']);
    }

    public function testListRecords(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);

        // 创建测试服务实例
        $service = new TestDnsRecordService($logger, $repository);

        $domain = $this->createDnsDomain();
        $params = ['page' => 1, 'per_page' => 20];

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('info')
            ->with('获取CloudFlare域名记录列表成功', self::anything())
        ;

        $result = $service->listRecords($domain, $params);
        $this->assertTrue($result['success']);
    }

    /**
     * 测试当 API 返回失败时的处理
     */
    public function testApiResponseFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);

        // 创建失败测试服务实例
        $failureService = new TestDnsRecordService($logger, $repository, false);

        $domain = $this->createDnsDomain();
        $record = $this->createDnsRecord();
        $record->setDomain($domain);

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('error')
            ->with('创建CloudFlare域名记录失败', self::anything())
        ;

        $this->expectException(TestServiceException::class);
        $failureService->createRecord($domain, $record);
    }

    public function testExportRecords(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);

        $service = new TestDnsRecordService($logger, $repository);
        $domain = $this->createDnsDomain();

        $result = $service->exportRecords($domain);
        $this->assertIsString($result);
        $this->assertStringContainsString('test.example.com', $result);
    }

    public function testImportRecords(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);

        $service = new TestDnsRecordService($logger, $repository);
        $domain = $this->createDnsDomain();
        $bindConfig = 'test.example.com. 300 IN A 192.0.2.1';

        $logger->expects($this->once())
            ->method('info')
            ->with('导入CloudFlare域名记录成功', self::anything())
        ;

        $result = $service->importRecords($domain, $bindConfig);
        $this->assertTrue($result['success']);
    }

    public function testScanRecords(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $repository = $this->createMock(DnsDomainRepository::class);

        $service = new TestDnsRecordService($logger, $repository);
        $domain = $this->createDnsDomain();

        $logger->expects($this->once())
            ->method('info')
            ->with('扫描CloudFlare域名记录成功', self::anything())
        ;

        $result = $service->scanRecords($domain);
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
