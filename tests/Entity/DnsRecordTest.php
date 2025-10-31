<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Entity;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * DNS记录实体测试
 *
 * @internal
 */
#[CoversClass(DnsRecord::class)]
final class DnsRecordTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new DnsRecord();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');

        yield 'domain' => ['domain', $domain];
        yield 'domain with null' => ['domain', null];
        yield 'type' => ['type', DnsRecordType::CNAME];
        yield 'record' => ['record', 'www'];
        yield 'recordId' => ['recordId', 'test-record-id'];
        yield 'recordId with null' => ['recordId', null];
        yield 'content' => ['content', 'example.com'];
        yield 'ttl' => ['ttl', 3600];
        yield 'proxy' => ['proxy', true];
        yield 'synced' => ['synced', true];
        yield 'syncing' => ['syncing', true];
    }

    public function testConstructorInitializesDefaultValues(): void
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

    public function testSetDomainAndGetDomain(): void
    {
        $record = new DnsRecord();
        $domain = new DnsDomain();

        $record->setDomain($domain);

        $this->assertSame($domain, $record->getDomain());
    }

    public function testSetDomainWithNull(): void
    {
        $record = new DnsRecord();

        $record->setDomain(null);

        $this->assertNull($record->getDomain());
    }

    public function testSetRecordAndGetRecord(): void
    {
        $record = new DnsRecord();
        $recordName = 'www';

        $record->setRecord($recordName);

        $this->assertEquals($recordName, $record->getRecord());
    }

    public function testSetRecordIdAndGetRecordId(): void
    {
        $record = new DnsRecord();
        $recordId = 'record123456789';

        $record->setRecordId($recordId);

        $this->assertEquals($recordId, $record->getRecordId());
    }

    public function testSetRecordIdWithNull(): void
    {
        $record = new DnsRecord();

        $record->setRecordId(null);

        $this->assertNull($record->getRecordId());
    }

    public function testSetTypeAndGetType(): void
    {
        $record = new DnsRecord();
        $type = DnsRecordType::CNAME;

        $record->setType($type);

        $this->assertSame($type, $record->getType());
    }

    public function testSetContentAndGetContent(): void
    {
        $record = new DnsRecord();
        $content = 'example.com';

        $record->setContent($content);

        $this->assertEquals($content, $record->getContent());
    }

    public function testSetTtlAndGetTtl(): void
    {
        $record = new DnsRecord();
        $ttl = 3600;

        $record->setTtl($ttl);

        $this->assertEquals($ttl, $record->getTtl());
    }

    public function testSetProxyAndIsProxy(): void
    {
        $record = new DnsRecord();

        $record->setProxy(true);

        $this->assertTrue($record->isProxy());

        $record->setProxy(false);
        $this->assertFalse($record->isProxy());
    }

    public function testSetSyncedAndIsSynced(): void
    {
        $record = new DnsRecord();

        $record->setSynced(true);

        $this->assertTrue($record->isSynced());
        $this->assertInstanceOf(\DateTimeImmutable::class, $record->getLastSyncedTime());

        $record->setSynced(false);
        $this->assertFalse($record->isSynced());
    }

    public function testSetSyncedWithTrueSetsLastSyncedTime(): void
    {
        $record = new DnsRecord();
        $beforeTime = new \DateTime();

        $record->setSynced(true);

        $this->assertTrue($record->isSynced());
        $this->assertInstanceOf(\DateTimeImmutable::class, $record->getLastSyncedTime());
        $this->assertGreaterThanOrEqual($beforeTime, $record->getLastSyncedTime());
    }

    public function testSetLastSyncedTimeAndGetLastSyncedTime(): void
    {
        $record = new DnsRecord();
        $lastSyncedTime = new \DateTime('2023-01-01 12:00:00');

        $record->setLastSyncedTime($lastSyncedTime);

        $this->assertSame($lastSyncedTime, $record->getLastSyncedTime());
    }

    public function testSetLastSyncedTimeWithNull(): void
    {
        $record = new DnsRecord();

        $record->setLastSyncedTime(null);

        $this->assertNull($record->getLastSyncedTime());
    }

    public function testSetSyncingAndIsSyncing(): void
    {
        $record = new DnsRecord();

        $record->setSyncing(true);

        $this->assertTrue($record->isSyncing());

        $record->setSyncing(false);
        $this->assertFalse($record->isSyncing());
    }

    public function testSetCreatedByAndGetCreatedBy(): void
    {
        $record = new DnsRecord();
        $createdBy = 'admin';

        $record->setCreatedBy($createdBy);

        $this->assertEquals($createdBy, $record->getCreatedBy());
    }

    public function testSetCreatedByWithNull(): void
    {
        $record = new DnsRecord();

        $record->setCreatedBy(null);

        $this->assertNull($record->getCreatedBy());
    }

    public function testSetUpdatedByAndGetUpdatedBy(): void
    {
        $record = new DnsRecord();
        $updatedBy = 'admin';

        $record->setUpdatedBy($updatedBy);

        $this->assertEquals($updatedBy, $record->getUpdatedBy());
    }

    public function testSetUpdatedByWithNull(): void
    {
        $record = new DnsRecord();

        $record->setUpdatedBy(null);

        $this->assertNull($record->getUpdatedBy());
    }

    public function testSetCreateTimeAndGetCreateTime(): void
    {
        $record = new DnsRecord();
        $createTime = new \DateTimeImmutable('2023-01-01 10:00:00');

        $record->setCreateTime($createTime);

        $this->assertSame($createTime, $record->getCreateTime());
    }

    public function testSetCreateTimeWithNull(): void
    {
        $record = new DnsRecord();

        $record->setCreateTime(null);

        $this->assertNull($record->getCreateTime());
    }

    public function testSetUpdateTimeAndGetUpdateTime(): void
    {
        $record = new DnsRecord();
        $updateTime = new \DateTimeImmutable('2023-01-01 11:00:00');

        $record->setUpdateTime($updateTime);

        $this->assertSame($updateTime, $record->getUpdateTime());
    }

    public function testSetUpdateTimeWithNull(): void
    {
        $record = new DnsRecord();

        $record->setUpdateTime(null);

        $this->assertNull($record->getUpdateTime());
    }

    public function testToStringWithRecord(): void
    {
        $record = new DnsRecord();
        $record->setRecord('www');

        $this->assertEquals('(www)', (string) $record);
    }

    public function testToStringWithoutRecord(): void
    {
        $record = new DnsRecord();

        $this->assertEquals('', (string) $record);
    }

    public function testGetFullName(): void
    {
        $record = new DnsRecord();
        $domain = new DnsDomain();
        $domain->setName('example.com');

        $record->setDomain($domain);
        $record->setRecord('www');

        $this->assertEquals('www.example.com', $record->getFullName());
    }

    public function testGetFullNameWithRootRecord(): void
    {
        $record = new DnsRecord();
        $domain = new DnsDomain();
        $domain->setName('example.com');

        $record->setDomain($domain);
        $record->setRecord('@');

        $this->assertEquals('@.example.com', $record->getFullName());
    }

    public function testAllDnsRecordTypes(): void
    {
        $record = new DnsRecord();

        foreach (DnsRecordType::cases() as $type) {
            $record->setType($type);
            $this->assertSame($type, $record->getType());
        }
    }

    public function testComplexScenarioWithAllProperties(): void
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

        $record->setDomain($domain);
        $record->setType($type);
        $record->setRecord($recordName);
        $record->setRecordId($recordId);
        $record->setContent($content);
        $record->setTtl($ttl);
        $record->setProxy($proxy);
        $record->setSyncing($syncing);
        $record->setCreatedBy($createdBy);
        $record->setUpdatedBy($updatedBy);
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
        $this->assertInstanceOf(\DateTimeImmutable::class, $record->getLastSyncedTime());
    }

    public function testEdgeCaseWithEmptyStrings(): void
    {
        $record = new DnsRecord();

        $record->setRecord('');
        $record->setRecordId('');
        $record->setContent('');

        $this->assertEquals('', $record->getRecord());
        $this->assertEquals('', $record->getRecordId());
        $this->assertEquals('', $record->getContent());
    }

    public function testEdgeCaseWithLongStrings(): void
    {
        $record = new DnsRecord();
        $longRecord = str_repeat('a', 60);
        $longRecordId = str_repeat('b', 60);
        $longContent = str_repeat('c', 1000);

        $record->setRecord($longRecord);
        $record->setRecordId($longRecordId);
        $record->setContent($longContent);

        $this->assertEquals($longRecord, $record->getRecord());
        $this->assertEquals($longRecordId, $record->getRecordId());
        $this->assertEquals($longContent, $record->getContent());
    }

    public function testEdgeCaseWithZeroTtl(): void
    {
        $record = new DnsRecord();

        $record->setTtl(0);

        $this->assertEquals(0, $record->getTtl());
    }

    public function testEdgeCaseWithNegativeTtl(): void
    {
        $record = new DnsRecord();

        $record->setTtl(-100);

        $this->assertEquals(-100, $record->getTtl());
    }

    public function testEdgeCaseWithMaximumTtl(): void
    {
        $record = new DnsRecord();

        $record->setTtl(PHP_INT_MAX);

        $this->assertEquals(PHP_INT_MAX, $record->getTtl());
    }

    public function testSpecialRecordNames(): void
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

    public function testSyncStateTransitions(): void
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
        $this->assertNotNull($record->getLastSyncedTime());
    }

    public function testAllDnsRecordTypeEnumValues(): void
    {
        $expectedTypes = ['A', 'MX', 'TXT', 'CNAME', 'NS', 'URI'];
        $actualTypes = [];

        foreach (DnsRecordType::cases() as $type) {
            $actualTypes[] = $type->value;
        }

        $this->assertEquals($expectedTypes, $actualTypes);
    }

    public function testContentWithSpecialCharacters(): void
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
