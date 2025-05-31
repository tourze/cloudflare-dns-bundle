<?php

namespace CloudflareDnsBundle\Tests\EventListener;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\EventListener\DnsRecordSyncListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DnsRecordSyncListenerTest extends TestCase
{
    private DnsRecordSyncListener $listener;

    protected function setUp(): void
    {
        $this->listener = new DnsRecordSyncListener();
    }

    public function test_prePersist_sets_unsynced_for_new_record(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId(null); // 新记录，没有远程ID
        $record->setSynced(true); // 初始状态为同步

        $this->listener->prePersist($record);

        $this->assertFalse($record->isSynced());
    }

    public function test_prePersist_keeps_synced_for_existing_record(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId('existing-remote-id'); // 已有远程ID
        $record->setSynced(true);

        $this->listener->prePersist($record);

        $this->assertTrue($record->isSynced());
    }

    public function test_preUpdate_sets_unsynced_when_type_changes(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['type' => [DnsRecordType::A, DnsRecordType::CNAME]];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function test_preUpdate_sets_unsynced_when_record_changes(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['record' => ['old-record', 'new-record']];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function test_preUpdate_sets_unsynced_when_content_changes(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['content' => ['192.168.1.1', '192.168.1.2']];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function test_preUpdate_sets_unsynced_when_ttl_changes(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['ttl' => [300, 600]];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function test_preUpdate_sets_unsynced_when_proxy_changes(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['proxy' => [false, true]];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function test_preUpdate_keeps_synced_when_non_sync_field_changes(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = ['updateTime' => [new \DateTime('2024-01-01'), new \DateTime('2024-01-02')]];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertTrue($record->isSynced());
    }

    public function test_preUpdate_handles_multiple_field_changes(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = [
            'content' => ['192.168.1.1', '192.168.1.2'],
            'ttl' => [300, 600],
            'updateTime' => [new \DateTime('2024-01-01'), new \DateTime('2024-01-02')]
        ];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function test_preUpdate_keeps_synced_when_no_sync_fields_changed(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = [
            'lastSyncedTime' => [null, new \DateTime()],
            'syncing' => [false, true],
            'updateTime' => [new \DateTime('2024-01-01'), new \DateTime('2024-01-02')]
        ];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertTrue($record->isSynced());
    }

    public function test_preUpdate_handles_empty_changeset(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(true);

        $changeSet = [];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertTrue($record->isSynced());
    }

    public function test_preUpdate_with_unsynced_record_stays_unsynced(): void
    {
        $record = $this->createDnsRecord();
        $record->setSynced(false);

        $changeSet = ['updateTime' => [new \DateTime('2024-01-01'), new \DateTime('2024-01-02')]];
        $args = $this->createPreUpdateEventArgs($record, $changeSet);

        $this->listener->preUpdate($record, $args);

        $this->assertFalse($record->isSynced());
    }

    public function test_preUpdate_sets_unsynced_for_all_sync_fields(): void
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

    public function test_prePersist_with_already_unsynced_record(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId(null);
        $record->setSynced(false); // 已经是未同步状态

        $this->listener->prePersist($record);

        $this->assertFalse($record->isSynced());
    }

    public function test_listener_methods_with_null_record_id(): void
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

    private function createPreUpdateEventArgs(DnsRecord $entity, array $changeSet): PreUpdateEventArgs&MockObject
    {
        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getEntityChangeSet')
            ->willReturn($changeSet);
        
        return $args;
    }
} 