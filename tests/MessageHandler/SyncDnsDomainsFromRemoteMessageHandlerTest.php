<?php

namespace CloudflareDnsBundle\Tests\MessageHandler;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Message\SyncDnsDomainsFromRemoteMessage;
use CloudflareDnsBundle\MessageHandler\SyncDnsDomainsFromRemoteMessageHandler;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SyncDnsDomainsFromRemoteMessageHandlerTest extends TestCase
{
    private SyncDnsDomainsFromRemoteMessageHandler $handler;
    private EntityManagerInterface&MockObject $entityManager;
    private DnsDomainRepository&MockObject $domainRepository;
    private DnsRecordRepository&MockObject $recordRepository;
    private DnsRecordService&MockObject $dnsService;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->domainRepository = $this->createMock(DnsDomainRepository::class);
        $this->recordRepository = $this->createMock(DnsRecordRepository::class);
        $this->dnsService = $this->createMock(DnsRecordService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SyncDnsDomainsFromRemoteMessageHandler(
            $this->entityManager,
            $this->domainRepository,
            $this->recordRepository,
            $this->dnsService,
            $this->logger
        );
    }

    public function test_invoke_with_nonexistent_domain(): void
    {
        $message = new SyncDnsDomainsFromRemoteMessage(999);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('找不到要同步的域名', ['domainId' => 999]);

        $this->dnsService->expects($this->never())
            ->method('listRecords');

        $this->handler->__invoke($message);
    }

    public function test_invoke_with_invalid_domain(): void
    {
        $domain = $this->createDnsDomain();
        $domain->setValid(false);
        $message = new SyncDnsDomainsFromRemoteMessage(1);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($domain);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('域名无效或缺少Zone ID，无法同步', [
                'domain' => 'example.com',
                'valid' => false,
                'zoneId' => 'test-zone-id'
            ]);

        $this->dnsService->expects($this->never())
            ->method('listRecords');

        $this->handler->__invoke($message);
    }

    public function test_invoke_with_domain_missing_zone_id(): void
    {
        $domain = $this->createDnsDomain();
        $domain->setZoneId(null);
        $message = new SyncDnsDomainsFromRemoteMessage(1);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->willReturn($domain);

        $this->logger->expects($this->once())
            ->method('warning');

        $this->dnsService->expects($this->never())
            ->method('listRecords');

        $this->handler->__invoke($message);
    }

    public function test_invoke_with_api_failure(): void
    {
        $domain = $this->createDnsDomain();
        $message = new SyncDnsDomainsFromRemoteMessage(1);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->willReturn($domain);

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn(['success' => false, 'errors' => ['API Error']]);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('获取DNS记录列表失败', [
                'domain' => 'example.com',
                'result' => ['success' => false, 'errors' => ['API Error']]
            ]);

        $this->recordRepository->expects($this->never())
            ->method('findBy');

        $this->handler->__invoke($message);
    }

    public function test_invoke_with_empty_remote_records(): void
    {
        $domain = $this->createDnsDomain();
        $message = new SyncDnsDomainsFromRemoteMessage(1);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->willReturn($domain);

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn([
                'success' => true,
                'result' => [],
                'result_info' => ['total_pages' => 1]
            ]);

        $this->logger->expects($this->exactly(2))
            ->method('info');

        $this->handler->__invoke($message);
    }

    public function test_invoke_creates_new_records(): void
    {
        $domain = $this->createDnsDomain();
        $message = new SyncDnsDomainsFromRemoteMessage(1);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->willReturn($domain);

        $remoteRecords = [
            [
                'id' => 'remote-1',
                'type' => 'A',
                'name' => 'test.example.com',
                'content' => '192.168.1.1',
                'ttl' => 300,
                'proxied' => false
            ],
            [
                'id' => 'remote-2',
                'type' => 'CNAME',
                'name' => 'www.example.com',
                'content' => 'example.com',
                'ttl' => 300,
                'proxied' => false
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn([
                'success' => true,
                'result' => $remoteRecords,
                'result_info' => ['total_pages' => 1]
            ]);

        $this->recordRepository->expects($this->once())
            ->method('findBy')
            ->with(['domain' => $domain])
            ->willReturn([]);

        // 期望创建2个新记录
        $this->entityManager->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        // 修正logger调用次数期望：
        // 1. 开始从Cloudflare获取域名解析记录
        // 2. 从Cloudflare获取到解析记录
        // 3. 创建本地DNS记录 (第一个)
        // 4. 创建本地DNS记录 (第二个)
        // 5. 域名DNS记录同步完成
        $this->logger->expects($this->exactly(5))
            ->method('info');

        $this->handler->__invoke($message);
    }

    public function test_invoke_updates_existing_records(): void
    {
        $domain = $this->createDnsDomain();
        $existingRecord = $this->createDnsRecord($domain);
        $existingRecord->setRecordId('remote-1');
        $existingRecord->setContent('192.168.1.1');
        $existingRecord->setTtl(300);

        $message = new SyncDnsDomainsFromRemoteMessage(1);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->willReturn($domain);

        $remoteRecords = [
            [
                'id' => 'remote-1',
                'type' => 'A',
                'name' => 'test.example.com',
                'content' => '192.168.1.2', // 内容变更
                'ttl' => 600, // TTL变更
                'proxied' => true // 代理状态变更
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn([
                'success' => true,
                'result' => $remoteRecords,
                'result_info' => ['total_pages' => 1]
            ]);

        $this->recordRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$existingRecord]);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($existingRecord);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->handler->__invoke($message);

        // 验证记录被正确更新
        $this->assertEquals('192.168.1.2', $existingRecord->getContent());
        $this->assertEquals(600, $existingRecord->getTtl());
        $this->assertTrue($existingRecord->isProxy());
        $this->assertTrue($existingRecord->isSynced());
    }

    public function test_invoke_skips_unchanged_records(): void
    {
        $domain = $this->createDnsDomain();
        $existingRecord = $this->createDnsRecord($domain);
        $existingRecord->setRecordId('remote-1');
        $existingRecord->setContent('192.168.1.1');
        $existingRecord->setTtl(300);
        $existingRecord->setProxy(false);

        $message = new SyncDnsDomainsFromRemoteMessage(1);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->willReturn($domain);

        $remoteRecords = [
            [
                'id' => 'remote-1',
                'type' => 'A',
                'name' => 'test.example.com',
                'content' => '192.168.1.1', // 内容相同
                'ttl' => 300, // TTL相同
                'proxied' => false // 代理状态相同
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn([
                'success' => true,
                'result' => $remoteRecords,
                'result_info' => ['total_pages' => 1]
            ]);

        $this->recordRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$existingRecord]);

        // 记录没有变化，不应该调用persist
        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->handler->__invoke($message);
    }

    public function test_invoke_handles_paginated_results(): void
    {
        $domain = $this->createDnsDomain();
        $message = new SyncDnsDomainsFromRemoteMessage(1);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->willReturn($domain);

        // 模拟分页结果
        $this->dnsService->expects($this->exactly(3))
            ->method('listRecords')
            ->willReturnOnConsecutiveCalls(
                [
                    'success' => true,
                    'result' => [
                        ['id' => 'remote-1', 'type' => 'A', 'name' => 'test1.example.com', 'content' => '1.1.1.1', 'ttl' => 300, 'proxied' => false]
                    ],
                    'result_info' => ['total_pages' => 3]
                ],
                [
                    'success' => true,
                    'result' => [
                        ['id' => 'remote-2', 'type' => 'A', 'name' => 'test2.example.com', 'content' => '2.2.2.2', 'ttl' => 300, 'proxied' => false]
                    ],
                    'result_info' => ['total_pages' => 3]
                ],
                [
                    'success' => true,
                    'result' => [
                        ['id' => 'remote-3', 'type' => 'A', 'name' => 'test3.example.com', 'content' => '3.3.3.3', 'ttl' => 300, 'proxied' => false]
                    ],
                    'result_info' => ['total_pages' => 3]
                ]
            );

        $this->recordRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        // 期望创建3个记录
        $this->entityManager->expects($this->exactly(3))
            ->method('persist');

        $this->handler->__invoke($message);
    }

    public function test_invoke_handles_invalid_record_type(): void
    {
        $domain = $this->createDnsDomain();
        $message = new SyncDnsDomainsFromRemoteMessage(1);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->willReturn($domain);

        $remoteRecords = [
            [
                'id' => 'remote-1',
                'type' => 'INVALID_TYPE',
                'name' => 'test.example.com',
                'content' => '192.168.1.1',
                'ttl' => 300,
                'proxied' => false
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn([
                'success' => true,
                'result' => $remoteRecords,
                'result_info' => ['total_pages' => 1]
            ]);

        $this->recordRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        // 无效类型不应该创建记录
        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->handler->__invoke($message);
    }

    public function test_invoke_handles_root_domain_records(): void
    {
        $domain = $this->createDnsDomain();
        $message = new SyncDnsDomainsFromRemoteMessage(1);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->willReturn($domain);

        $remoteRecords = [
            [
                'id' => 'remote-1',
                'type' => 'A',
                'name' => 'example.com', // 根域名
                'content' => '192.168.1.1',
                'ttl' => 300,
                'proxied' => false
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn([
                'success' => true,
                'result' => $remoteRecords,
                'result_info' => ['total_pages' => 1]
            ]);

        $this->recordRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function($record) {
                return $record instanceof DnsRecord && $record->getRecord() === '@';
            }));

        $this->handler->__invoke($message);
    }

    public function test_invoke_with_exception_handling(): void
    {
        $domain = $this->createDnsDomain();
        $message = new SyncDnsDomainsFromRemoteMessage(1);

        $this->domainRepository->expects($this->once())
            ->method('find')
            ->willReturn($domain);

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willThrowException(new \Exception('API connection failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '同步DNS记录时发生错误',
                $this->callback(function($context) {
                    return isset($context['domain']) && 
                           isset($context['error']) &&
                           isset($context['exception']) &&
                           $context['domain'] === 'example.com';
                })
            );

        $this->handler->__invoke($message);
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

        // 使用反射设置ID
        $reflection = new \ReflectionClass($domain);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($domain, 1);

        return $domain;
    }

    private function createDnsRecord(DnsDomain $domain): DnsRecord
    {
        $record = new DnsRecord();
        $record->setDomain($domain);
        $record->setRecord('test');
        $record->setType(DnsRecordType::A);
        $record->setContent('192.168.1.1');
        $record->setTtl(300);
        $record->setProxy(false);

        return $record;
    }
} 