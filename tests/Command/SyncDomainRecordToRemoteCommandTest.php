<?php

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDomainRecordToRemoteCommand;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Message\SyncDnsRecordToRemoteMessage;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SyncDomainRecordToRemoteCommandTest extends TestCase
{
    private SyncDomainRecordToRemoteCommand $command;
    private DnsRecordRepository&MockObject $recordRepository;
    private MessageBusInterface&MockObject $messageBus;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->recordRepository = $this->createMock(DnsRecordRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->command = new SyncDomainRecordToRemoteCommand(
            $this->recordRepository,
            $this->messageBus
        );

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function test_execute_sync_single_record_success(): void
    {
        $record = $this->createDnsRecord();

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($record);

        $envelope = new Envelope(new SyncDnsRecordToRemoteMessage(1));
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SyncDnsRecordToRemoteMessage::class))
            ->willReturn($envelope);

        $result = $this->commandTester->execute([
            'dnsRecordId' => 123,
        ]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('已将DNS记录【test.example.com】加入同步队列', $output);
        $this->assertStringContainsString('类型: A', $output);
        $this->assertStringContainsString('内容: 192.168.1.1', $output);
    }

    public function test_execute_record_not_found(): void
    {
        $this->recordRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $result = $this->commandTester->execute([
            'dnsRecordId' => 999,
        ]);

        $this->assertEquals(1, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('找不到ID为999的DNS记录', $output);
    }

    public function test_execute_sync_all_records_success(): void
    {
        $record1 = $this->createDnsRecord();
        $record1->setSynced(false);
        
        $record2 = $this->createDnsRecord();
        $record2->setRecord('www');
        $record2->setSynced(false);

        $this->recordRepository->expects($this->once())
            ->method('findBy')
            ->with(['synced' => false])
            ->willReturn([$record1, $record2]);

        $envelope = new Envelope(new SyncDnsRecordToRemoteMessage(1));
        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(SyncDnsRecordToRemoteMessage::class))
            ->willReturn($envelope);

        $result = $this->commandTester->execute([
            '--all' => true,
        ]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('找到2条未同步的DNS记录', $output);
        $this->assertStringContainsString('已将2条DNS记录加入同步队列', $output);
    }

    public function test_execute_sync_all_with_no_unsynced_records(): void
    {
        $this->recordRepository->expects($this->once())
            ->method('findBy')
            ->with(['synced' => false])
            ->willReturn([]);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $result = $this->commandTester->execute([
            '--all' => true,
        ]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('没有需要同步的DNS记录', $output);
    }

    public function test_execute_with_all_flag_shows_progress(): void
    {
        $records = [];
        for ($i = 1; $i <= 5; $i++) {
            $record = $this->createDnsRecord();
            $record->setRecord("test{$i}");
            $record->setSynced(false);
            $records[] = $record;
        }

        $this->recordRepository->expects($this->once())
            ->method('findBy')
            ->willReturn($records);

        $envelope = new Envelope(new SyncDnsRecordToRemoteMessage(1));
        $this->messageBus->expects($this->exactly(5))
            ->method('dispatch')
            ->willReturn($envelope);

        $result = $this->commandTester->execute([
            '--all' => true,
        ]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('找到5条未同步的DNS记录', $output);
        $this->assertStringContainsString('已将5条DNS记录加入同步队列', $output);
    }

    public function test_message_contains_correct_record_id(): void
    {
        $record = $this->createDnsRecord();

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->willReturn($record);

        $envelope = new Envelope(new SyncDnsRecordToRemoteMessage(1));
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function($message) {
                return $message instanceof SyncDnsRecordToRemoteMessage && 
                       $message->getDnsRecordId() === 1;
            }))
            ->willReturn($envelope);

        $this->commandTester->execute([
            'dnsRecordId' => 123,
        ]);
    }

    public function test_command_name_and_description(): void
    {
        $this->assertEquals('cloudflare:sync-dns-domain-record-to-remote', $this->command->getName());
        $this->assertEquals('将DNS记录同步到远程Cloudflare', $this->command->getDescription());
    }

    public function test_command_has_correct_arguments_and_options(): void
    {
        $definition = $this->command->getDefinition();
        
        $this->assertTrue($definition->hasArgument('dnsRecordId'));
        $this->assertFalse($definition->getArgument('dnsRecordId')->isRequired());
        
        $this->assertTrue($definition->hasOption('all'));
        $this->assertFalse($definition->getOption('all')->acceptValue());
    }

    public function test_sync_all_with_mixed_sync_status(): void
    {
        $syncedRecord = $this->createDnsRecord();
        $syncedRecord->setSynced(true);
        
        $unsyncedRecord1 = $this->createDnsRecord();
        $unsyncedRecord1->setRecord('api');
        $unsyncedRecord1->setSynced(false);
        
        $unsyncedRecord2 = $this->createDnsRecord();
        $unsyncedRecord2->setRecord('cdn');
        $unsyncedRecord2->setSynced(false);

        // 应该只返回未同步的记录
        $this->recordRepository->expects($this->once())
            ->method('findBy')
            ->with(['synced' => false])
            ->willReturn([$unsyncedRecord1, $unsyncedRecord2]);

        $envelope = new Envelope(new SyncDnsRecordToRemoteMessage(1));
        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturn($envelope);

        $result = $this->commandTester->execute([
            '--all' => true,
        ]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('找到2条未同步的DNS记录', $output);
    }

    public function test_single_record_sync_with_different_record_types(): void
    {
        $cnameRecord = $this->createDnsRecord();
        $cnameRecord->setType(DnsRecordType::CNAME);
        $cnameRecord->setContent('example.com');

        $this->recordRepository->expects($this->once())
            ->method('find')
            ->willReturn($cnameRecord);

        $envelope = new Envelope(new SyncDnsRecordToRemoteMessage(1));
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $result = $this->commandTester->execute([
            'dnsRecordId' => 456,
        ]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('类型: CNAME', $output);
        $this->assertStringContainsString('内容: example.com', $output);
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
        $record->setSynced(true);

        // 使用反射设置 ID，因为这是一个私有属性且通常由 Doctrine 管理
        $reflection = new \ReflectionClass($record);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($record, 1);

        return $record;
    }
} 