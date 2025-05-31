<?php

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDomainRecordToLocalCommand;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SyncDomainRecordToLocalCommandTest extends TestCase
{
    private SyncDomainRecordToLocalCommand $command;
    private EntityManagerInterface&MockObject $entityManager;
    private DnsDomainRepository&MockObject $domainRepository;
    private DnsRecordRepository&MockObject $recordRepository;
    private LoggerInterface&MockObject $logger;
    private DnsRecordService&MockObject $dnsService;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->domainRepository = $this->createMock(DnsDomainRepository::class);
        $this->recordRepository = $this->createMock(DnsRecordRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->dnsService = $this->createMock(DnsRecordService::class);

        $this->command = new SyncDomainRecordToLocalCommand(
            $this->entityManager,
            $this->domainRepository,
            $this->recordRepository,
            $this->logger,
            $this->dnsService
        );

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function test_execute_success_with_specific_domain(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->with(['id' => '123'])
            ->willReturn([$domain]);

        $apiResponse = [
            'success' => true,
            'result' => [
                [
                    'id' => 'remote-record-id-1',
                    'name' => 'test.example.com',
                    'type' => 'A',
                    'content' => '192.168.1.1',
                    'ttl' => 300,
                    'proxiable' => false
                ]
            ],
            'result_info' => [
                'total_pages' => 1
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse);

        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->commandTester->execute([
            'domainId' => '123',
        ]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);
        $this->assertStringContainsString('发现子域名：test.example.com', $output);
    }

    public function test_execute_all_domains(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain1->setName('example1.com');
        
        $domain2 = $this->createDnsDomain();
        $domain2->setName('example2.com');

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain1, $domain2]);

        $apiResponse = [
            'success' => true,
            'result' => [],
            'result_info' => [
                'total_pages' => 1
            ]
        ];

        $this->dnsService->expects($this->exactly(2))
            ->method('listRecords')
            ->willReturn($apiResponse);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example1.com', $output);
        $this->assertStringContainsString('开始处理域名：example2.com', $output);
    }

    public function test_execute_domain_not_found(): void
    {
        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->with(['id' => '999'])
            ->willReturn([]);

        $this->dnsService->expects($this->never())
            ->method('listRecords');

        $result = $this->commandTester->execute([
            'domainId' => '999',
        ]);

        $this->assertEquals(0, $result);
    }

    public function test_execute_updates_existing_record(): void
    {
        $domain = $this->createDnsDomain();
        $existingRecord = $this->createDnsRecord();
        $existingRecord->setContent('192.168.1.1');
        $existingRecord->setRecordId('remote-record-id-1');

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain]);

        $apiResponse = [
            'success' => true,
            'result' => [
                [
                    'id' => 'remote-record-id-1',
                    'name' => 'test.example.com',
                    'type' => 'A',
                    'content' => '192.168.1.2',
                    'ttl' => 300,
                    'proxiable' => false
                ]
            ],
            'result_info' => [
                'total_pages' => 1
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse);

        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'domain' => $domain,
                'recordId' => 'remote-record-id-1'
            ])
            ->willReturn($existingRecord);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);
    }

    public function test_execute_with_api_error(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain]);

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn(['success' => false]);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
    }

    public function test_execute_with_empty_remote_records(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain]);

        $apiResponse = [
            'success' => true,
            'result' => [],
            'result_info' => [
                'total_pages' => 1
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);
    }

    public function test_execute_with_multiple_record_types(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain]);

        $apiResponse = [
            'success' => true,
            'result' => [
                [
                    'id' => 'record-1',
                    'name' => 'www.example.com',
                    'type' => 'A',
                    'content' => '192.168.1.1',
                    'ttl' => 300,
                    'proxiable' => false
                ],
                [
                    'id' => 'record-2',
                    'name' => 'mail.example.com',
                    'type' => 'MX',
                    'content' => 'mail.example.com',
                    'ttl' => 300,
                    'proxiable' => false
                ],
                [
                    'id' => 'record-3',
                    'name' => 'api.example.com',
                    'type' => 'CNAME',
                    'content' => 'example.com',
                    'ttl' => 300,
                    'proxiable' => false
                ]
            ],
            'result_info' => [
                'total_pages' => 1
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse);

        $this->recordRepository->expects($this->exactly(3))
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager->expects($this->exactly(3))
            ->method('persist');

        $this->entityManager->expects($this->exactly(3))
            ->method('flush');

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);
    }

    public function test_execute_with_database_error(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain]);

        $apiResponse = [
            'success' => true,
            'result' => [
                [
                    'id' => 'record-1',
                    'name' => 'test.example.com',
                    'type' => 'A',
                    'content' => '192.168.1.1',
                    'ttl' => 300,
                    'proxiable' => false
                ]
            ],
            'result_info' => [
                'total_pages' => 1
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse);

        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException(new \Exception('Database error'));

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('同步DNS发生错误', $output);
    }

    public function test_execute_handles_invalid_record_type(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain]);

        $apiResponse = [
            'success' => true,
            'result' => [
                [
                    'id' => 'record-1',
                    'name' => 'test.example.com',
                    'type' => 'INVALID_TYPE',
                    'content' => '192.168.1.1',
                    'ttl' => 300,
                    'proxiable' => false
                ]
            ],
            'result_info' => [
                'total_pages' => 1
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn($apiResponse);

        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        // 由于记录类型无效，tryFrom会返回null，记录不会被创建
        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->never())
            ->method('flush');

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

        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');
        $domain->setIamKey($iamKey);
        $domain->setValid(true);

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

        return $record;
    }
} 