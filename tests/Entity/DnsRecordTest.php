<?php

namespace CloudflareDnsBundle\Tests\Entity;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use PHPUnit\Framework\TestCase;

/**
 * DNS记录实体测试
 */
class DnsRecordTest extends TestCase
{
    public function test_constructor_initializes_default_values(): void
    {
        $record = new DnsRecord();

        $this->assertEquals(0, $record->getId());
        $this->assertNull($record->getDomain());
        $this->assertEquals(DnsRecordType::A, $record->getType());
        $this->assertNull($record->getRecord());
        $this->assertNull($record->getRecordId());
        $this->assertNull($record->getContent());
        $this->assertEquals(60, $record->getTtl());
        $this->assertFalse($record->isProxy());
        $this->assertFalse($record->isSynced());
        $this->assertNull($record->getLastSyncedTime());
        $this->assertFalse($record->isSyncing());
        $this->assertNull($record->getCreatedBy());
        $this->assertNull($record->getUpdatedBy());
        $this->assertNull($record->getCreateTime());
        $this->assertNull($record->getUpdateTime());
    }

    public function test_setDomain_and_getDomain(): void
    {
        $record = new DnsRecord();
        $domain = new DnsDomain();

        $result = $record->setDomain($domain);

        $this->assertSame($record, $result);
        $this->assertSame($domain, $record->getDomain());
    }

    public function test_setDomain_with_null(): void
    {
        $record = new DnsRecord();

        $result = $record->setDomain(null);

        $this->assertSame($record, $result);
        $this->assertNull($record->getDomain());
    }

    public function test_setRecord_and_getRecord(): void
    {
        $record = new DnsRecord();
        $recordName = 'www';

        $result = $record->setRecord($recordName);

        $this->assertSame($record, $result);
        $this->assertEquals($recordName, $record->getRecord());
    }

    public function test_setRecordId_and_getRecordId(): void
    {
        $record = new DnsRecord();
        $recordId = 'record123456789';

        $result = $record->setRecordId($recordId);

        $this->assertSame($record, $result);
        $this->assertEquals($recordId, $record->getRecordId());
    }

    public function test_setRecordId_with_null(): void
    {
        $record = new DnsRecord();

        $result = $record->setRecordId(null);

        $this->assertSame($record, $result);
        $this->assertNull($record->getRecordId());
    }

    public function test_setType_and_getType(): void
    {
        $record = new DnsRecord();
        $type = DnsRecordType::CNAME;

        $result = $record->setType($type);

        $this->assertSame($record, $result);
        $this->assertSame($type, $record->getType());
    }

    public function test_setContent_and_getContent(): void
    {
        $record = new DnsRecord();
        $content = 'example.com';

        $result = $record->setContent($content);

        $this->assertSame($record, $result);
        $this->assertEquals($content, $record->getContent());
    }

    public function test_setTtl_and_getTtl(): void
    {
        $record = new DnsRecord();
        $ttl = 3600;

        $result = $record->setTtl($ttl);

        $this->assertSame($record, $result);
        $this->assertEquals($ttl, $record->getTtl());
    }

    public function test_setProxy_and_isProxy(): void
    {
        $record = new DnsRecord();

        $result = $record->setProxy(true);

        $this->assertSame($record, $result);
        $this->assertTrue($record->isProxy());

        $record->setProxy(false);
        $this->assertFalse($record->isProxy());
    }

    public function test_setSynced_and_isSynced(): void
    {
        $record = new DnsRecord();

        $result = $record->setSynced(true);

        $this->assertSame($record, $result);
        $this->assertTrue($record->isSynced());
        $this->assertInstanceOf(\DateTime::class, $record->getLastSyncedTime());

        $record->setSynced(false);
        $this->assertFalse($record->isSynced());
    }

    public function test_setSynced_with_true_sets_lastSyncedTime(): void
    {
        $record = new DnsRecord();
        $beforeTime = new \DateTime();

        $record->setSynced(true);

        $this->assertTrue($record->isSynced());
        $this->assertInstanceOf(\DateTime::class, $record->getLastSyncedTime());
        $this->assertGreaterThanOrEqual($beforeTime, $record->getLastSyncedTime());
    }

    public function test_setLastSyncedTime_and_getLastSyncedTime(): void
    {
        $record = new DnsRecord();
        $lastSyncedTime = new \DateTime('2023-01-01 12:00:00');

        $result = $record->setLastSyncedTime($lastSyncedTime);

        $this->assertSame($record, $result);
        $this->assertSame($lastSyncedTime, $record->getLastSyncedTime());
    }

    public function test_setLastSyncedTime_with_null(): void
    {
        $record = new DnsRecord();

        $result = $record->setLastSyncedTime(null);

        $this->assertSame($record, $result);
        $this->assertNull($record->getLastSyncedTime());
    }

    public function test_setSyncing_and_isSyncing(): void
    {
        $record = new DnsRecord();

        $result = $record->setSyncing(true);

        $this->assertSame($record, $result);
        $this->assertTrue($record->isSyncing());

        $record->setSyncing(false);
        $this->assertFalse($record->isSyncing());
    }

    public function test_setCreatedBy_and_getCreatedBy(): void
    {
        $record = new DnsRecord();
        $createdBy = 'admin';

        $result = $record->setCreatedBy($createdBy);

        $this->assertSame($record, $result);
        $this->assertEquals($createdBy, $record->getCreatedBy());
    }

    public function test_setCreatedBy_with_null(): void
    {
        $record = new DnsRecord();

        $result = $record->setCreatedBy(null);

        $this->assertSame($record, $result);
        $this->assertNull($record->getCreatedBy());
    }

    public function test_setUpdatedBy_and_getUpdatedBy(): void
    {
        $record = new DnsRecord();
        $updatedBy = 'admin';

        $result = $record->setUpdatedBy($updatedBy);

        $this->assertSame($record, $result);
        $this->assertEquals($updatedBy, $record->getUpdatedBy());
    }

    public function test_setUpdatedBy_with_null(): void
    {
        $record = new DnsRecord();

        $result = $record->setUpdatedBy(null);

        $this->assertSame($record, $result);
        $this->assertNull($record->getUpdatedBy());
    }

    public function test_setCreateTime_and_getCreateTime(): void
    {
        $record = new DnsRecord();
        $createTime = new \DateTimeImmutable('2023-01-01 10:00:00');

        $record->setCreateTime($createTime);

        $this->assertSame($createTime, $record->getCreateTime());
    }

    public function test_setCreateTime_with_null(): void
    {
        $record = new DnsRecord();

        $record->setCreateTime(null);

        $this->assertNull($record->getCreateTime());
    }

    public function test_setUpdateTime_and_getUpdateTime(): void
    {
        $record = new DnsRecord();
        $updateTime = new \DateTimeImmutable('2023-01-01 11:00:00');

        $record->setUpdateTime($updateTime);

        $this->assertSame($updateTime, $record->getUpdateTime());
    }

    public function test_setUpdateTime_with_null(): void
    {
        $record = new DnsRecord();

        $record->setUpdateTime(null);

        $this->assertNull($record->getUpdateTime());
    }

    public function test_toString_with_record(): void
    {
        $record = new DnsRecord();
        $record->setRecord('www');

        $this->assertEquals('(www)', (string) $record);
    }

    public function test_toString_without_record(): void
    {
        $record = new DnsRecord();

        $this->assertEquals('', (string) $record);
    }

    public function test_getFullName(): void
    {
        $record = new DnsRecord();
        $domain = new DnsDomain();
        $domain->setName('example.com');
        
        $record->setDomain($domain);
        $record->setRecord('www');

        $this->assertEquals('www.example.com', $record->getFullName());
    }

    public function test_getFullName_with_root_record(): void
    {
        $record = new DnsRecord();
        $domain = new DnsDomain();
        $domain->setName('example.com');
        
        $record->setDomain($domain);
        $record->setRecord('@');

        $this->assertEquals('@.example.com', $record->getFullName());
    }

    public function test_all_dns_record_types(): void
    {
        $record = new DnsRecord();

        foreach (DnsRecordType::cases() as $type) {
            $record->setType($type);
            $this->assertSame($type, $record->getType());
        }
    }

    public function test_complex_scenario_with_all_properties(): void
    {
        $record = new DnsRecord();
        $domain = new DnsDomain();
        $domain->setName('test.com');

        $type = DnsRecordType::MX;
        $recordName = 'mail';
        $recordId = 'record987654321';
        $content = '10 mail.test.com';
        $ttl = 7200;
        $proxy = false;
        $synced = true;
        $syncing = false;
        $createdBy = 'testuser';
        $updatedBy = 'admin';
        $createTime = new \DateTimeImmutable('2023-06-01 10:00:00');
        $updateTime = new \DateTimeImmutable('2023-06-01 15:00:00');
        $lastSyncedTime = new \DateTime('2023-06-01 14:00:00');

        $record->setDomain($domain)
            ->setType($type)
            ->setRecord($recordName)
            ->setRecordId($recordId)
            ->setContent($content)
            ->setTtl($ttl)
            ->setProxy($proxy)
            ->setSyncing($syncing)
            ->setCreatedBy($createdBy)
            ->setUpdatedBy($updatedBy);
        $record->setCreateTime($createTime);
        $record->setUpdateTime($updateTime);
        $record->setLastSyncedTime($lastSyncedTime);
        $record->setSynced($synced);

        $this->assertSame($domain, $record->getDomain());
        $this->assertSame($type, $record->getType());
        $this->assertEquals($recordName, $record->getRecord());
        $this->assertEquals($recordId, $record->getRecordId());
        $this->assertEquals($content, $record->getContent());
        $this->assertEquals($ttl, $record->getTtl());
        $this->assertEquals($proxy, $record->isProxy());
        $this->assertEquals($synced, $record->isSynced());
        $this->assertEquals($syncing, $record->isSyncing());
        $this->assertEquals($createdBy, $record->getCreatedBy());
        $this->assertEquals($updatedBy, $record->getUpdatedBy());
        $this->assertSame($createTime, $record->getCreateTime());
        $this->assertSame($updateTime, $record->getUpdateTime());
        $this->assertEquals('mail.test.com', $record->getFullName());
        $this->assertEquals('(mail)', (string) $record);
        $this->assertInstanceOf(\DateTime::class, $record->getLastSyncedTime());
    }

    public function test_edge_case_with_empty_strings(): void
    {
        $record = new DnsRecord();

        $record->setRecord('')
            ->setRecordId('')
            ->setContent('');

        $this->assertEquals('', $record->getRecord());
        $this->assertEquals('', $record->getRecordId());
        $this->assertEquals('', $record->getContent());
    }

    public function test_edge_case_with_long_strings(): void
    {
        $record = new DnsRecord();
        $longRecord = str_repeat('a', 60);
        $longRecordId = str_repeat('b', 60);
        $longContent = str_repeat('c', 1000);

        $record->setRecord($longRecord)
            ->setRecordId($longRecordId)
            ->setContent($longContent);

        $this->assertEquals($longRecord, $record->getRecord());
        $this->assertEquals($longRecordId, $record->getRecordId());
        $this->assertEquals($longContent, $record->getContent());
    }

    public function test_edge_case_with_zero_ttl(): void
    {
        $record = new DnsRecord();

        $record->setTtl(0);

        $this->assertEquals(0, $record->getTtl());
    }

    public function test_edge_case_with_negative_ttl(): void
    {
        $record = new DnsRecord();

        $record->setTtl(-100);

        $this->assertEquals(-100, $record->getTtl());
    }

    public function test_edge_case_with_maximum_ttl(): void
    {
        $record = new DnsRecord();

        $record->setTtl(PHP_INT_MAX);

        $this->assertEquals(PHP_INT_MAX, $record->getTtl());
    }

    public function test_special_record_names(): void
    {
        $record = new DnsRecord();
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $record->setDomain($domain);

        // 测试根域名记录
        $record->setRecord('@');
        $this->assertEquals('@.example.com', $record->getFullName());

        // 测试通配符记录
        $record->setRecord('*');
        $this->assertEquals('*.example.com', $record->getFullName());

        // 测试子域名记录
        $record->setRecord('sub.domain');
        $this->assertEquals('sub.domain.example.com', $record->getFullName());
    }

    public function test_sync_state_transitions(): void
    {
        $record = new DnsRecord();

        // 初始状态
        $this->assertFalse($record->isSynced());
        $this->assertFalse($record->isSyncing());
        $this->assertNull($record->getLastSyncedTime());

        // 开始同步
        $record->setSyncing(true);
        $this->assertTrue($record->isSyncing());
        $this->assertFalse($record->isSynced());

        // 同步完成
        $record->setSyncing(false);
        $record->setSynced(true);
        $this->assertFalse($record->isSyncing());
        $this->assertTrue($record->isSynced());
        $this->assertInstanceOf(\DateTime::class, $record->getLastSyncedTime());
    }

    public function test_all_dns_record_type_enum_values(): void
    {
        $expectedTypes = ['A', 'MX', 'TXT', 'CNAME', 'NS', 'URI'];
        $actualTypes = [];

        foreach (DnsRecordType::cases() as $type) {
            $actualTypes[] = $type->value;
        }

        $this->assertEquals($expectedTypes, $actualTypes);
    }

    public function test_content_with_special_characters(): void
    {
        $record = new DnsRecord();

        // 测试包含特殊字符的内容
        $specialContent = 'v=spf1 ip4:192.168.1.0/24 include:_spf.google.com ~all';
        $record->setContent($specialContent);
        $this->assertEquals($specialContent, $record->getContent());

        // 测试包含引号的内容
        $quotedContent = '"google-site-verification=abcd1234"';
        $record->setContent($quotedContent);
        $this->assertEquals($quotedContent, $record->getContent());

        // 测试包含多行的内容
        $multilineContent = "line1\nline2\nline3";
        $record->setContent($multilineContent);
        $this->assertEquals($multilineContent, $record->getContent());
    }
} 