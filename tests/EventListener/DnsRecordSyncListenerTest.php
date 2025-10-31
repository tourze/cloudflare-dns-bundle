<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\EventListener;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\EventListener\DnsRecordSyncListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DnsRecordSyncListener::class)]
#[RunTestsInSeparateProcesses]
final class DnsRecordSyncListenerTest extends AbstractIntegrationTestCase
{
    private DnsRecordSyncListener $listener;

    protected function onSetUp(): void
    {
        $this->listener = self::getService(DnsRecordSyncListener::class);
    }

    public function testPrePersistSetsUnsyncedForNewRecord(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId(null); // 新记录，没有远程ID
        $record->setSynced(true); // 初始状态为同步

        $this->listener->prePersist($record);

        $this->assertFalse($record->isSynced());
    }

    public function testPrePersistKeepsSyncedForExistingRecord(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId('existing-remote-id'); // 已有远程ID
        $record->setSynced(true);

        $this->listener->prePersist($record);

        $this->assertTrue($record->isSynced());
    }

    public function testPreUpdateSetsUnsyncedWhenTypeChanges(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['type' => [DnsRecordType::A, DnsRecordType::CNAME]];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function testPreUpdateSetsUnsyncedWhenRecordChanges(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['record' => ['old-record', 'new-record']];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function testPreUpdateSetsUnsyncedWhenContentChanges(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['content' => ['192.168.1.1', '192.168.1.2']];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function testPreUpdateSetsUnsyncedWhenTtlChanges(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['ttl' => [300, 600]];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function testPreUpdateSetsUnsyncedWhenProxyChanges(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['proxy' => [false, true]];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function testPreUpdateKeepsSyncedWhenNonSyncFieldChanges(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['updateTime' => [new \DateTime('2024-01-01'), new \DateTime('2024-01-02')]];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertTrue($record->isSynced());
    }

    public function testPreUpdateHandlesMultipleFieldChanges(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = [
            'content' => ['192.168.1.1', '192.168.1.2'],
            'ttl' => [300, 600],
            'updateTime' => [new \DateTime('2024-01-01'), new \DateTime('2024-01-02')],
        ];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function testPreUpdateKeepsSyncedWhenNoSyncFieldsChanged(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = [
            'lastSyncedTime' => [null, new \DateTime()],
            'syncing' => [false, true],
            'updateTime' => [new \DateTime('2024-01-01'), new \DateTime('2024-01-02')],
        ];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertTrue($record->isSynced());
    }

    public function testPreUpdateHandlesEmptyChangeset(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = [];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertTrue($record->isSynced());
    }

    public function testPreUpdateWithUnsyncedRecordStaysUnsynced(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(false);

        $changeSet = ['updateTime' => [new \DateTime('2024-01-01'), new \DateTime('2024-01-02')]];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function testPreUpdateSetsUnsyncedForAllSyncFields(): void
    {
        $syncFields = ['type', 'record', 'content', 'ttl', 'proxy'];

        foreach ($syncFields as $field) {
            $record = $this->createDnsRecord();
            $record->setSynced(true);

            $changeSet = [$field => ['old_value', 'new_value']];
            $args = $this->createPreUpdateEventArgs($record, $changeSet);

            $this->listener->preUpdate($record, $args);

            $this->assertFalse($record->isSynced(), "Field {$field} should trigger unsync");
        }
    }

    public function testPrePersistWithAlreadyUnsyncedRecord(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId(null);
        $record->setSynced(false); // 已经是未同步状态

        $this->listener->prePersist($record);

        $this->assertFalse($record->isSynced());
    }

    public function testListenerMethodsWithNullRecordId(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId(null);
        $record->setSynced(true);

        // Test prePersist
        $this->listener->prePersist($record);
        $this->assertFalse($record->isSynced());

        // Reset and test preUpdate
        $record->setSynced(true);
        $changeSet = ['content' => ['old', 'new']];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);
        $this->assertFalse($record->isSynced());
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

        return $record;
    }

    /**
     * @param array<string, mixed> $changeSet
     */
    private function createPreUpdateEventArgs(DnsRecord $entity, array $changeSet): PreUpdateEventArgs&MockObject
    {
        /*
         * 使用具体类 PreUpdateEventArgs 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getEntityChangeSet')
            ->willReturn($changeSet)
        ;

        return $args;
    }
}
