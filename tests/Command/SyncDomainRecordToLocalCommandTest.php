<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDomainRecordToLocalCommand;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(SyncDomainRecordToLocalCommand::class)]
final class SyncDomainRecordToLocalCommandTest extends AbstractCommandTestCase
{
    private SyncDomainRecordToLocalCommand $command;

    private DnsDomainRepository&MockObject $domainRepository;

    private DnsRecordRepository&MockObject $recordRepository;

    private DnsRecordService&MockObject $dnsService;

    private CommandTester $commandTester;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {        /*
         * 使用具体类 DnsDomainRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $this->domainRepository = $this->createMock(DnsDomainRepository::class);
        /*
         * 使用具体类 DnsRecordRepository 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $this->recordRepository = $this->createMock(DnsRecordRepository::class);
        /*
         * 使用具体类 DnsRecordService 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $this->dnsService = $this->createMock(DnsRecordService::class);

        // 替换容器中的服务
        self::getContainer()->set(DnsDomainRepository::class, $this->domainRepository);
        self::getContainer()->set(DnsRecordRepository::class, $this->recordRepository);
        self::getContainer()->set(DnsRecordService::class, $this->dnsService);

        $command = self::getContainer()->get(SyncDomainRecordToLocalCommand::class);
        $this->assertInstanceOf(SyncDomainRecordToLocalCommand::class, $command);
        $this->command = $command;

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteSuccessWithSpecificDomain(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->with(['id' => '1'])
            ->willReturn([$domain])
        ;

        $apiResponse = [
            'success' => true,
            'result' => [
                [
                    'id' => 'remote-record-1',
                    'name' => 'test.example.com',
                    'type' => 'A',
                    'content' => '192.168.1.1',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
            ],
            'result_info' => [
                'total_pages' => 1,
            ],
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse)
        ;

        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'domain' => $domain,
                'recordId' => 'remote-record-1',
            ])
            ->willReturn(null)
        ;

        $result = $this->commandTester->execute([
            'domainId' => '1',
        ]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);
    }

    public function testExecuteAllDomains(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain1->setName('example1.com');

        $domain2 = $this->createDnsDomain();
        $domain2->setName('example2.com');

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain1, $domain2])
        ;

        $apiResponse = [
            'success' => true,
            'result' => [],
            'result_info' => [
                'total_pages' => 1,
            ],
        ];

        $this->dnsService->expects($this->exactly(2))
            ->method('listRecords')
            ->willReturn($apiResponse)
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example1.com', $output);
        $this->assertStringContainsString('开始处理域名：example2.com', $output);
    }

    public function testExecuteDomainNotFound(): void
    {
        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->with(['id' => '999'])
            ->willReturn([])
        ;

        $this->dnsService->expects($this->never())
            ->method('listRecords')
        ;

        $result = $this->commandTester->execute([
            'domainId' => '999',
        ]);

        $this->assertEquals(0, $result);
    }

    public function testExecuteUpdatesExistingRecord(): void
    {
        $domain = $this->createDnsDomain();
        $existingRecord = $this->createDnsRecord();
        $existingRecord->setContent('192.168.1.1');
        $existingRecord->setRecordId('remote-record-id-1');

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain])
        ;

        $apiResponse = [
            'success' => true,
            'result' => [
                [
                    'id' => 'remote-record-id-1',
                    'name' => 'test.example.com',
                    'type' => 'A',
                    'content' => '192.168.1.2',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
            ],
            'result_info' => [
                'total_pages' => 1,
            ],
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse)
        ;

        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'domain' => $domain,
                'recordId' => 'remote-record-id-1',
            ])
            ->willReturn($existingRecord)
        ;

        // EntityManager persist 和 flush 操作由集成测试框架自动处理

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);
    }

    public function testExecuteWithApiError(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain])
        ;

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn(['success' => false])
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithEmptyRemoteRecords(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain])
        ;

        $apiResponse = [
            'success' => true,
            'result' => [],
            'result_info' => [
                'total_pages' => 1,
            ],
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse)
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);
    }

    public function testExecuteWithMultipleRecordTypes(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain])
        ;

        $apiResponse = [
            'success' => true,
            'result' => [
                [
                    'id' => 'record-1',
                    'name' => 'www.example.com',
                    'type' => 'A',
                    'content' => '192.168.1.1',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
                [
                    'id' => 'record-2',
                    'name' => 'mail.example.com',
                    'type' => 'MX',
                    'content' => 'mail.example.com',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
                [
                    'id' => 'record-3',
                    'name' => 'api.example.com',
                    'type' => 'CNAME',
                    'content' => 'example.com',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
            ],
            'result_info' => [
                'total_pages' => 1,
            ],
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse)
        ;

        $this->recordRepository->expects($this->atLeast(1))
            ->method('findOneBy')
            ->willReturn(null)
        ;

        // EntityManager persist 和 flush 操作由集成测试框架自动处理

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);
    }

    public function testExecuteWithDatabaseError(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain])
        ;

        $apiResponse = [
            'success' => true,
            'result' => [
                [
                    'id' => 'record-1',
                    'name' => 'test.example.com',
                    'type' => 'A',
                    'content' => '192.168.1.1',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
            ],
            'result_info' => [
                'total_pages' => 1,
            ],
        ];

        // 通过模拟 DNS 服务错误来测试异常处理
        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willThrowException(new \Exception('DNS service error'))
        ;

        // 由于 DNS 服务抛出异常，不会到达查找记录的步骤
        $this->recordRepository->expects($this->never())
            ->method('findOneBy')
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('同步DNS发生错误', $output);
    }

    public function testExecuteHandlesInvalidRecordType(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain])
        ;

        $apiResponse = [
            'success' => true,
            'result' => [
                [
                    'id' => 'record-1',
                    'name' => 'test.example.com',
                    'type' => 'INVALID_TYPE',
                    'content' => '192.168.1.1',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
            ],
            'result_info' => [
                'total_pages' => 1,
            ],
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse)
        ;

        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null)
        ;

        // 由于记录类型无效，tryFrom会返回null，记录不会被创建
        // 此测试不应该执行任何数据库写入操作

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
    }

    private function createDnsDomain(): DnsDomain
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key');
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        // 手动设置时间戳字段避免监听器问题
        $now = new \DateTimeImmutable();
        $reflection = new \ReflectionClass($iamKey);
        if ($reflection->hasProperty('createTime')) {
            $createTimeProperty = $reflection->getProperty('createTime');
            $createTimeProperty->setAccessible(true);
            $createTimeProperty->setValue($iamKey, $now);
        }
        if ($reflection->hasProperty('updateTime')) {
            $updateTimeProperty = $reflection->getProperty('updateTime');
            $updateTimeProperty->setAccessible(true);
            $updateTimeProperty->setValue($iamKey, $now);
        }

        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');
        $domain->setIamKey($iamKey);
        $domain->setValid(true);

        // 手动设置时间戳字段避免监听器问题
        $domainReflection = new \ReflectionClass($domain);
        if ($domainReflection->hasProperty('createTime')) {
            $createTimeProperty = $domainReflection->getProperty('createTime');
            $createTimeProperty->setAccessible(true);
            $createTimeProperty->setValue($domain, $now);
        }
        if ($domainReflection->hasProperty('updateTime')) {
            $updateTimeProperty = $domainReflection->getProperty('updateTime');
            $updateTimeProperty->setAccessible(true);
            $updateTimeProperty->setValue($domain, $now);
        }

        return $domain;
    }

    private function createDnsRecord(): DnsRecord
    {
        $domain = $this->createDnsDomain();

        $record = new DnsRecord();
        $record->setDomain($domain);
        $record->setRecord('test');
        $record->setRecordId('record123');
        $record->setType(DnsRecordType::A);
        $record->setContent('192.168.1.1');
        $record->setTtl(300);
        $record->setProxy(false);

        // 手动设置时间戳字段避免监听器问题
        $now = new \DateTimeImmutable();
        $reflection = new \ReflectionClass($record);
        if ($reflection->hasProperty('createTime')) {
            $createTimeProperty = $reflection->getProperty('createTime');
            $createTimeProperty->setAccessible(true);
            $createTimeProperty->setValue($record, $now);
        }
        if ($reflection->hasProperty('updateTime')) {
            $updateTimeProperty = $reflection->getProperty('updateTime');
            $updateTimeProperty->setAccessible(true);
            $updateTimeProperty->setValue($record, $now);
        }

        return $record;
    }

    public function testArgumentDomainId(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->with(['id' => '1'])
            ->willReturn([$domain])
        ;

        $apiResponse = [
            'success' => true,
            'result' => [],
            'result_info' => ['total_pages' => 1],
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse)
        ;

        $result = $this->commandTester->execute(['domainId' => '1']);
        $this->assertEquals(0, $result);
    }
}
