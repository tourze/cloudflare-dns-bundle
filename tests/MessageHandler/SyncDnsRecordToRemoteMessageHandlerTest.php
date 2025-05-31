<?php

namespace CloudflareDnsBundle\Tests\MessageHandler;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Message\SyncDnsRecordToRemoteMessage;
use CloudflareDnsBundle\MessageHandler\SyncDnsRecordToRemoteMessageHandler;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SyncDnsRecordToRemoteMessageHandlerTest extends TestCase
{
    private SyncDnsRecordToRemoteMessageHandler $handler;
    private EntityManagerInterface&MockObject $entityManager;
    private DnsRecordRepository&MockObject $recordRepository;
    private DnsRecordService&MockObject $dnsService;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->recordRepository = $this->createMock(DnsRecordRepository::class);
        $this->dnsService = $this->createMock(DnsRecordService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SyncDnsRecordToRemoteMessageHandler(
            $this->entityManager,
            $this->recordRepository,
            $this->dnsService,
            $this->logger
        );
    }

    public function test_invoke_with_nonexistent_record(): void
    {
        $message = new SyncDnsRecordToRemoteMessage(999);

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('找不到要同步的DNS记录', ['recordId' => 999]);

        $this->dnsService->expects($this->never())
            ->method('listRecords');

        $this->handler->__invoke($message);
    }

    public function test_invoke_with_record_already_syncing(): void
    {
        $record = $this->createDnsRecord();
        $record->setSyncing(true);
        $message = new SyncDnsRecordToRemoteMessage(1);

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($record);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('记录正在同步中,跳过处理', ['recordId' => 1]);

        $this->dnsService->expects($this->never())
            ->method('listRecords');

        $this->handler->__invoke($message);
    }

    public function test_invoke_creates_new_record_success(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId(null); // 没有远程ID
        $message = new SyncDnsRecordToRemoteMessage(1);

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->willReturn($record);

        // 第一次flush设置syncing状态
        // 第二次flush设置recordId
        // 第三次flush完成同步
        $this->entityManager->expects($this->exactly(3))
            ->method('flush');

        // 尝试搜索现有记录（返回空）
        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn(['result' => []]);

        // 创建新记录
        $this->dnsService->expects($this->once())
            ->method('createRecord')
            ->willReturn(['id' => 'new-record-id']);

        // 更新记录
        $this->dnsService->expects($this->once())
            ->method('updateRecord')
            ->willReturn(['success' => true]);

        $this->logger->expects($this->exactly(2))
            ->method('info');

        $this->handler->__invoke($message);

        $this->assertEquals('new-record-id', $record->getRecordId());
        $this->assertTrue($record->isSynced());
        $this->assertFalse($record->isSyncing());
    }

    public function test_invoke_finds_existing_record_by_search(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId(null); // 没有远程ID
        $message = new SyncDnsRecordToRemoteMessage(1);

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->willReturn($record);

        $this->entityManager->expects($this->exactly(3))
            ->method('flush');

        // 搜索现有记录（找到）
        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn(['result' => [['id' => 'found-record-id']]]);

        // 不需要创建，直接更新
        $this->dnsService->expects($this->never())
            ->method('createRecord');

        $this->dnsService->expects($this->once())
            ->method('updateRecord')
            ->willReturn(['success' => true]);

        $this->handler->__invoke($message);

        $this->assertEquals('found-record-id', $record->getRecordId());
        $this->assertTrue($record->isSynced());
        $this->assertFalse($record->isSyncing());
    }

    public function test_invoke_updates_existing_record(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId('existing-record-id'); // 已有远程ID
        $message = new SyncDnsRecordToRemoteMessage(1);

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->willReturn($record);

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        // 有recordId，跳过搜索和创建
        $this->dnsService->expects($this->never())
            ->method('listRecords');

        $this->dnsService->expects($this->never())
            ->method('createRecord');

        // 直接更新
        $this->dnsService->expects($this->once())
            ->method('updateRecord')
            ->willReturn(['success' => true]);

        $this->logger->expects($this->once())
            ->method('info');

        $this->handler->__invoke($message);

        $this->assertTrue($record->isSynced());
        $this->assertFalse($record->isSyncing());
    }

    public function test_invoke_handles_create_record_exception(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId(null);
        $message = new SyncDnsRecordToRemoteMessage(1);

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->willReturn($record);

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willReturn(['result' => []]);

        $this->dnsService->expects($this->once())
            ->method('createRecord')
            ->willThrowException(new \Exception('Create failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('同步DNS记录失败', $this->callback(function($context) {
                return isset($context['record']) && isset($context['exception']);
            }));

        $this->handler->__invoke($message);

        // 异常情况下应该重置syncing状态
        $this->assertFalse($record->isSyncing());
        $this->assertFalse($record->isSynced());
    }

    public function test_invoke_handles_update_record_exception(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId('existing-record-id');
        $message = new SyncDnsRecordToRemoteMessage(1);

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->willReturn($record);

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $this->dnsService->expects($this->once())
            ->method('updateRecord')
            ->willThrowException(new \Exception('Update failed'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->handler->__invoke($message);

        $this->assertFalse($record->isSyncing());
        $this->assertFalse($record->isSynced());
    }

    public function test_invoke_handles_search_exception(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId(null);
        $message = new SyncDnsRecordToRemoteMessage(1);

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->willReturn($record);

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->willThrowException(new \Exception('Search failed'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->handler->__invoke($message);

        $this->assertFalse($record->isSyncing());
    }

    public function test_invoke_complete_flow_with_search_create_update(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId(null);
        $message = new SyncDnsRecordToRemoteMessage(1);

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->willReturn($record);

        // 验证flush调用顺序和次数
        $this->entityManager->expects($this->exactly(3))
            ->method('flush');

        // 完整流程：搜索 -> 创建 -> 更新
        $this->dnsService->expects($this->once())
            ->method('listRecords')
            ->with($record->getDomain(), [
                'type' => 'A',
                'name' => 'test.example.com'
            ])
            ->willReturn(['result' => []]);

        $this->dnsService->expects($this->once())
            ->method('createRecord')
            ->with($record->getDomain(), $record)
            ->willReturn(['id' => 'created-record-id']);

        $this->dnsService->expects($this->once())
            ->method('updateRecord')
            ->with($record)
            ->willReturn(['success' => true]);

        $this->logger->expects($this->exactly(2))
            ->method('info');

        $this->handler->__invoke($message);

        $this->assertEquals('created-record-id', $record->getRecordId());
        $this->assertTrue($record->isSynced());
        $this->assertFalse($record->isSyncing());
    }

    public function test_invoke_entity_manager_flush_error_handling(): void
    {
        $record = $this->createDnsRecord();
        $message = new SyncDnsRecordToRemoteMessage(1);

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->willReturn($record);

        // 第一次flush失败，但finally块中还会再调用一次flush
        $this->entityManager->expects($this->exactly(2))
            ->method('flush')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \Exception('Database error')),
                null // finally块中的flush成功
            );

        $this->logger->expects($this->once())
            ->method('error');

        $this->handler->__invoke($message);
    }

    public function test_invoke_verifies_syncing_state_management(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId('existing-id');
        $message = new SyncDnsRecordToRemoteMessage(1);

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->willReturn($record);

        // 验证syncing状态的设置和重置
        $this->assertFalse($record->isSyncing());

        $this->entityManager->expects($this->exactly(2))
            ->method('flush')
            ->willReturnCallback(function() use ($record) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 1) {
                    // 第一次flush时应该已经设置为syncing
                    $this->assertTrue($record->isSyncing());
                }
            });

        $this->dnsService->expects($this->once())
            ->method('updateRecord')
            ->willReturn(['success' => true]);

        $this->handler->__invoke($message);

        // 最终应该重置syncing状态
        $this->assertFalse($record->isSyncing());
        $this->assertTrue($record->isSynced());
    }

    private function createDnsRecord(): DnsRecord
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

        $record = new DnsRecord();
        $record->setDomain($domain);
        $record->setRecord('test');
        $record->setType(DnsRecordType::A);
        $record->setContent('192.168.1.1');
        $record->setTtl(300);
        $record->setProxy(false);
        $record->setSynced(false);
        $record->setSyncing(false);

        // 使用反射设置ID
        $reflection = new \ReflectionClass($record);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($record, 1);

        return $record;
    }
} 