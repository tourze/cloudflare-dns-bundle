<?php

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DNSProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\DDNSContracts\ExpectResolveResult;

class DNSProviderTest extends TestCase
{
    private DNSProvider $service;
    private DnsDomainRepository&MockObject $domainRepository;
    private DnsRecordRepository&MockObject $recordRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private MessageBusInterface&MockObject $messageBus;

    protected function setUp(): void
    {
        $this->domainRepository = $this->createMock(DnsDomainRepository::class);
        $this->recordRepository = $this->createMock(DnsRecordRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->service = new DNSProvider(
            $this->domainRepository,
            $this->recordRepository,
            $this->entityManager,
            $this->logger,
            $this->messageBus
        );
    }

    public function test_getName(): void
    {
        $this->assertEquals('cloudflare-dns', $this->service->getName());
    }

    public function test_check_with_exact_domain_match(): void
    {
        $result = new ExpectResolveResult('example.com', '192.168.1.1');
        
        $domain = $this->createDnsDomain();
        
        $this->domainRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'name' => 'example.com',
                'valid' => true
            ])
            ->willReturn($domain);

        $this->assertTrue($this->service->check($result));
    }

    public function test_check_with_subdomain_match(): void
    {
        $result = new ExpectResolveResult('sub.example.com', '192.168.1.1');
        
        $domain = $this->createDnsDomain();
        
        $this->domainRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnMap([
                [['name' => 'sub.example.com', 'valid' => true], null, null],
                [['name' => 'example.com', 'valid' => true], null, $domain],
            ]);

        $this->assertTrue($this->service->check($result));
    }

    public function test_check_with_no_match(): void
    {
        $result = new ExpectResolveResult('test.notmanaged.com', '192.168.1.1');
        
        $this->domainRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturn(null);

        $this->assertFalse($this->service->check($result));
    }

    public function test_resolve_creates_new_record(): void
    {
        $result = new ExpectResolveResult('test.example.com', '192.168.1.1');
        
        $domain = $this->createDnsDomain();
        
        // findRootDomain 调用
        $this->domainRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnMap([
                [['name' => 'test.example.com', 'valid' => true], null, null],
                [['name' => 'example.com', 'valid' => true], null, $domain],
            ]);

        // findOrCreateRecord 调用
        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'domain' => $domain,
                'record' => 'test',
                'type' => DnsRecordType::A
            ])
            ->willReturn(null);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $this->service->resolve($result);
    }

    public function test_resolve_updates_existing_record(): void
    {
        $result = new ExpectResolveResult('test.example.com', '192.168.1.2');
        
        $domain = $this->createDnsDomain();
        $existingRecord = $this->createDnsRecord();
        $existingRecord->setContent('192.168.1.1');
        
        $this->domainRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnMap([
                [['name' => 'test.example.com', 'valid' => true], null, null],
                [['name' => 'example.com', 'valid' => true], null, $domain],
            ]);

        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingRecord);

        // 一次更新记录，一次在 syncToRemoteIfNeeded 中
        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $this->service->resolve($result);

        $this->assertEquals('192.168.1.2', $existingRecord->getContent());
        $this->assertFalse($existingRecord->isSynced());
    }

    public function test_resolve_with_unchanged_content(): void
    {
        $result = new ExpectResolveResult('test.example.com', '192.168.1.1');
        
        $domain = $this->createDnsDomain();
        $existingRecord = $this->createDnsRecord();
        $existingRecord->setContent('192.168.1.1');
        $existingRecord->setSynced(true);
        
        $this->domainRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnMap([
                [['name' => 'test.example.com', 'valid' => true], null, null],
                [['name' => 'example.com', 'valid' => true], null, $domain],
            ]);

        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingRecord);

        // 内容未变更，不应该调用 flush
        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->service->resolve($result);
    }

    public function test_resolve_with_root_domain(): void
    {
        $result = new ExpectResolveResult('example.com', '192.168.1.1');
        
        $domain = $this->createDnsDomain();
        
        $this->domainRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'name' => 'example.com',
                'valid' => true
            ])
            ->willReturn($domain);

        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'domain' => $domain,
                'record' => '@',
                'type' => DnsRecordType::A
            ])
            ->willReturn(null);

        $this->service->resolve($result);
    }

    public function test_resolve_with_no_matching_domain(): void
    {
        $result = new ExpectResolveResult('test.notfound.com', '192.168.1.1');

        // 检查根域名查找次数 - test.notfound.com, notfound.com
        $this->domainRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('找不到匹配的根域名：test.notfound.com');

        $this->service->resolve($result);
    }

    public function test_resolve_with_database_exception(): void
    {
        $result = new ExpectResolveResult('test.example.com', '192.168.1.1');
        
        $this->domainRepository->expects($this->once())
            ->method('findOneBy')
            ->willThrowException(new \Exception('Database error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->service->resolve($result);
    }

    public function test_resolve_with_deep_subdomain(): void
    {
        $result = new ExpectResolveResult('a.b.c.example.com', '192.168.1.1');
        
        $domain = $this->createDnsDomain();
        
        $this->domainRepository->expects($this->exactly(4))
            ->method('findOneBy')
            ->willReturnMap([
                [['name' => 'a.b.c.example.com', 'valid' => true], null, null],
                [['name' => 'b.c.example.com', 'valid' => true], null, null],
                [['name' => 'c.example.com', 'valid' => true], null, null],
                [['name' => 'example.com', 'valid' => true], null, $domain],
            ]);

        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'domain' => $domain,
                'record' => 'a.b.c',
                'type' => DnsRecordType::A
            ])
            ->willReturn(null);

        $this->service->resolve($result);
    }

    public function test_resolve_dispatches_sync_message(): void
    {
        $domain = $this->createDnsDomain();
        $result = new ExpectResolveResult('test.example.com', '192.168.1.2');

        // 模拟域名查找：第一次查找test.example.com失败，第二次查找example.com成功
        $this->domainRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(null, $domain);

        // 查找记录：找不到记录，需要创建新记录
        $this->recordRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        // 模拟EntityManager的persist和flush行为，设置记录ID
        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(function($record) {
                if ($record instanceof DnsRecord && !$record->getId()) {
                    // 使用反射设置ID，模拟数据库保存后的状态
                    $reflection = new \ReflectionClass($record);
                    $idProperty = $reflection->getProperty('id');
                    $idProperty->setAccessible(true);
                    $idProperty->setValue($record, 123);
                }
            });

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        // 期望dispatch被调用一次 - 新创建的记录默认是未同步状态
        $envelope = new \Symfony\Component\Messenger\Envelope(new \CloudflareDnsBundle\Message\SyncDnsRecordToRemoteMessage(123));
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $this->service->resolve($result);
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
        $record->setType(DnsRecordType::A);
        $record->setContent('192.168.1.1');
        $record->setTtl(300);
        $record->setProxy(false);

        return $record;
    }
} 