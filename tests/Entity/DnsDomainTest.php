<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Entity;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Enum\DomainStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * 域名实体测试
 *
 * @internal
 */
#[CoversClass(DnsDomain::class)]
final class DnsDomainTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new DnsDomain();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test Key');
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');

        yield 'name' => ['name', 'example.com'];
        yield 'name empty' => ['name', ''];
        yield 'zoneId' => ['zoneId', 'test-zone-id'];
        yield 'zoneId with null' => ['zoneId', null];
        yield 'zoneId empty' => ['zoneId', ''];
        yield 'iamKey' => ['iamKey', $iamKey];
        yield 'iamKey with null' => ['iamKey', null];
        yield 'status' => ['status', DomainStatus::ACTIVE];
        yield 'status with null' => ['status', null];
    }

    public function testConstructorInitializesDefaultValues(): void
    {
        $domain = new DnsDomain();

        $this->assertNull($domain->getId());
        $this->assertNull($domain->getIamKey());
        $this->assertNull($domain->getName());
        $this->assertNull($domain->getZoneId());
        $this->assertNull($domain->getStatus());
        $this->assertNull($domain->getExpiresTime());
        $this->assertNull($domain->getLockedUntilTime());
        $this->assertFalse($domain->isAutoRenew());
        $this->assertCount(0, $domain->getRecords());
        $this->assertFalse($domain->isValid());
        $this->assertNull($domain->getCreatedBy());
        $this->assertNull($domain->getUpdatedBy());
        $this->assertNull($domain->getCreateTime());
        $this->assertNull($domain->getUpdateTime());
    }

    public function testSetNameAndGetName(): void
    {
        $domain = new DnsDomain();
        $name = 'example.com';

        $domain->setName($name);

        $this->assertEquals($name, $domain->getName());
    }

    public function testSetZoneIdAndGetZoneId(): void
    {
        $domain = new DnsDomain();
        $zoneId = 'zone123456789';

        $domain->setZoneId($zoneId);

        $this->assertEquals($zoneId, $domain->getZoneId());
    }

    public function testSetZoneIdWithNull(): void
    {
        $domain = new DnsDomain();

        $domain->setZoneId(null);

        $this->assertNull($domain->getZoneId());
    }

    public function testSetIamKeyAndGetIamKey(): void
    {
        $domain = new DnsDomain();
        $iamKey = new IamKey();

        $domain->setIamKey($iamKey);

        $this->assertSame($iamKey, $domain->getIamKey());
    }

    public function testSetIamKeyWithNull(): void
    {
        $domain = new DnsDomain();

        $domain->setIamKey(null);

        $this->assertNull($domain->getIamKey());
    }

    public function testAddRecordAndGetRecords(): void
    {
        $domain = new DnsDomain();
        $record = new DnsRecord();

        $domain->addRecord($record);

        $this->assertCount(1, $domain->getRecords());
        $this->assertTrue($domain->getRecords()->contains($record));
        $this->assertSame($domain, $record->getDomain());
    }

    public function testAddRecordDuplicateRecord(): void
    {
        $domain = new DnsDomain();
        $record = new DnsRecord();

        $domain->addRecord($record);
        $domain->addRecord($record); // 添加相同记录

        $this->assertCount(1, $domain->getRecords());
    }

    public function testRemoveRecord(): void
    {
        $domain = new DnsDomain();
        $record = new DnsRecord();
        $domain->addRecord($record);

        $domain->removeRecord($record);

        $this->assertCount(0, $domain->getRecords());
        $this->assertFalse($domain->getRecords()->contains($record));
        $this->assertNull($record->getDomain());
    }

    public function testRemoveRecordNotExisting(): void
    {
        $domain = new DnsDomain();
        $record = new DnsRecord();

        $domain->removeRecord($record);

        $this->assertCount(0, $domain->getRecords());
    }

    public function testToStringWithName(): void
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');

        // 没有ID时返回空字符串
        $this->assertEquals('', (string) $domain);
    }

    public function testToStringWithoutName(): void
    {
        $domain = new DnsDomain();

        $this->assertEquals('', (string) $domain);
    }

    public function testGetAccountIdFromIamKey(): void
    {
        $domain = new DnsDomain();
        $iamKey = new IamKey();
        $iamKey->setAccountId('account123');
        $domain->setIamKey($iamKey);

        $this->assertEquals('account123', $domain->getAccountId());
    }

    public function testGetAccountIdWithoutIamKey(): void
    {
        $domain = new DnsDomain();

        $this->assertNull($domain->getAccountId());
    }

    public function testGetAccountIdWithIamKeyWithoutAccountId(): void
    {
        $domain = new DnsDomain();
        $iamKey = new IamKey();
        $domain->setIamKey($iamKey);

        $this->assertNull($domain->getAccountId());
    }

    public function testSetStatusAndGetStatus(): void
    {
        $domain = new DnsDomain();
        $status = DomainStatus::ACTIVE;

        $domain->setStatus($status);

        $this->assertEquals($status, $domain->getStatus());
    }

    public function testSetStatusWithNull(): void
    {
        $domain = new DnsDomain();

        $domain->setStatus(null);

        $this->assertNull($domain->getStatus());
    }

    public function testSetExpiresTimeAndGetExpiresTime(): void
    {
        $domain = new DnsDomain();
        $expiresTime = new \DateTime('2024-12-31');

        $domain->setExpiresTime($expiresTime);

        $this->assertSame($expiresTime, $domain->getExpiresTime());
    }

    public function testSetExpiresTimeWithNull(): void
    {
        $domain = new DnsDomain();

        $domain->setExpiresTime(null);

        $this->assertNull($domain->getExpiresTime());
    }

    public function testSetLockedUntilTimeAndGetLockedUntilTime(): void
    {
        $domain = new DnsDomain();
        $lockedUntilTime = new \DateTime('2024-06-30');

        $domain->setLockedUntilTime($lockedUntilTime);

        $this->assertSame($lockedUntilTime, $domain->getLockedUntilTime());
    }

    public function testSetLockedUntilTimeWithNull(): void
    {
        $domain = new DnsDomain();

        $domain->setLockedUntilTime(null);

        $this->assertNull($domain->getLockedUntilTime());
    }

    public function testSetAutoRenewAndIsAutoRenew(): void
    {
        $domain = new DnsDomain();

        $domain->setAutoRenew(true);

        $this->assertTrue($domain->isAutoRenew());

        $domain->setAutoRenew(false);
        $this->assertFalse($domain->isAutoRenew());
    }

    public function testSetValidAndIsValid(): void
    {
        $domain = new DnsDomain();

        $domain->setValid(true);

        $this->assertTrue($domain->isValid());

        $domain->setValid(false);
        $this->assertFalse($domain->isValid());

        $domain->setValid(null);
        $this->assertNull($domain->isValid());
    }

    public function testSetCreatedByAndGetCreatedBy(): void
    {
        $domain = new DnsDomain();
        $createdBy = 'admin';

        $domain->setCreatedBy($createdBy);

        $this->assertEquals($createdBy, $domain->getCreatedBy());
    }

    public function testSetCreatedByWithNull(): void
    {
        $domain = new DnsDomain();

        $domain->setCreatedBy(null);

        $this->assertNull($domain->getCreatedBy());
    }

    public function testSetUpdatedByAndGetUpdatedBy(): void
    {
        $domain = new DnsDomain();
        $updatedBy = 'admin';

        $domain->setUpdatedBy($updatedBy);

        $this->assertEquals($updatedBy, $domain->getUpdatedBy());
    }

    public function testSetUpdatedByWithNull(): void
    {
        $domain = new DnsDomain();

        $domain->setUpdatedBy(null);

        $this->assertNull($domain->getUpdatedBy());
    }

    public function testSetCreateTimeAndGetCreateTime(): void
    {
        $domain = new DnsDomain();
        $createTime = new \DateTimeImmutable('2023-01-01 10:00:00');

        $domain->setCreateTime($createTime);

        $this->assertSame($createTime, $domain->getCreateTime());
    }

    public function testSetCreateTimeWithNull(): void
    {
        $domain = new DnsDomain();

        $domain->setCreateTime(null);

        $this->assertNull($domain->getCreateTime());
    }

    public function testSetUpdateTimeAndGetUpdateTime(): void
    {
        $domain = new DnsDomain();
        $updateTime = new \DateTimeImmutable('2023-01-01 11:00:00');

        $domain->setUpdateTime($updateTime);

        $this->assertSame($updateTime, $domain->getUpdateTime());
    }

    public function testSetUpdateTimeWithNull(): void
    {
        $domain = new DnsDomain();

        $domain->setUpdateTime(null);

        $this->assertNull($domain->getUpdateTime());
    }

    public function testComplexScenarioWithMultipleRecords(): void
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');

        $recordA = new DnsRecord();
        $recordA->setType(DnsRecordType::A);
        $recordA->setRecord('@');

        $recordCname = new DnsRecord();
        $recordCname->setType(DnsRecordType::CNAME);
        $recordCname->setRecord('www');

        $domain->addRecord($recordA);
        $domain->addRecord($recordCname);

        $this->assertCount(2, $domain->getRecords());
        $this->assertTrue($domain->getRecords()->contains($recordA));
        $this->assertTrue($domain->getRecords()->contains($recordCname));
        $this->assertSame($domain, $recordA->getDomain());
        $this->assertSame($domain, $recordCname->getDomain());
    }

    public function testComplexScenarioWithAllProperties(): void
    {
        $domain = new DnsDomain();
        $iamKey = new IamKey();
        $iamKey->setName('Test Key');
        $iamKey->setAccountId('account456');

        $name = 'test.com';
        $zoneId = 'zone987654321';
        $status = DomainStatus::PENDING;
        $expiresTime = new \DateTime('2025-01-01');
        $lockedUntilTime = new \DateTime('2024-07-01');
        $autoRenew = true;
        $valid = true;
        $createdBy = 'testuser';
        $updatedBy = 'admin';
        $createTime = new \DateTimeImmutable('2023-06-01 10:00:00');
        $updateTime = new \DateTimeImmutable('2023-06-01 15:00:00');

        $domain->setIamKey($iamKey);
        $domain->setName($name);
        $domain->setZoneId($zoneId);
        $domain->setStatus($status);
        $domain->setExpiresTime($expiresTime);
        $domain->setLockedUntilTime($lockedUntilTime);
        $domain->setAutoRenew($autoRenew);
        $domain->setValid($valid);
        $domain->setCreatedBy($createdBy);
        $domain->setUpdatedBy($updatedBy);
        $domain->setCreateTime($createTime);
        $domain->setUpdateTime($updateTime);

        $this->assertSame($iamKey, $domain->getIamKey());
        $this->assertEquals($name, $domain->getName());
        $this->assertEquals($zoneId, $domain->getZoneId());
        $this->assertEquals($status, $domain->getStatus());
        $this->assertSame($expiresTime, $domain->getExpiresTime());
        $this->assertSame($lockedUntilTime, $domain->getLockedUntilTime());
        $this->assertTrue($domain->isAutoRenew());
        $this->assertTrue($domain->isValid());
        $this->assertEquals($createdBy, $domain->getCreatedBy());
        $this->assertEquals($updatedBy, $domain->getUpdatedBy());
        $this->assertSame($createTime, $domain->getCreateTime());
        $this->assertSame($updateTime, $domain->getUpdateTime());
        $this->assertEquals('account456', $domain->getAccountId());
        $this->assertEquals('', (string) $domain);
    }

    public function testEdgeCaseWithEmptyStringName(): void
    {
        $domain = new DnsDomain();
        $domain->setName('');

        $this->assertEquals('', $domain->getName());
        $this->assertEquals('', (string) $domain);
    }

    public function testEdgeCaseWithLongStrings(): void
    {
        $domain = new DnsDomain();
        $longName = str_repeat('a', 100) . '.com';
        $longZoneId = str_repeat('b', 60);
        $longStatus = DomainStatus::SUSPENDED;

        $domain->setName($longName);
        $domain->setZoneId($longZoneId);
        $domain->setStatus($longStatus);

        $this->assertEquals($longName, $domain->getName());
        $this->assertEquals($longZoneId, $domain->getZoneId());
        $this->assertEquals($longStatus, $domain->getStatus());
    }

    public function testEdgeCaseWithFutureDates(): void
    {
        $domain = new DnsDomain();
        $futureDate = new \DateTime('+10 years');

        $domain->setExpiresTime($futureDate);
        $domain->setLockedUntilTime($futureDate);

        $this->assertSame($futureDate, $domain->getExpiresTime());
        $this->assertSame($futureDate, $domain->getLockedUntilTime());
    }

    public function testEdgeCaseWithPastDates(): void
    {
        $domain = new DnsDomain();
        $pastDate = new \DateTime('-5 years');

        $domain->setExpiresTime($pastDate);
        $domain->setLockedUntilTime($pastDate);

        $this->assertSame($pastDate, $domain->getExpiresTime());
        $this->assertSame($pastDate, $domain->getLockedUntilTime());
    }
}
