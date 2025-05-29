<?php

namespace CloudflareDnsBundle\Tests\Entity;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Enum\DomainStatus;
use PHPUnit\Framework\TestCase;

/**
 * 域名实体测试
 */
class DnsDomainTest extends TestCase
{
    public function test_constructor_initializes_default_values(): void
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
        $this->assertEmpty($domain->getRecords());
        $this->assertFalse($domain->isValid());
        $this->assertNull($domain->getCreatedBy());
        $this->assertNull($domain->getUpdatedBy());
        $this->assertNull($domain->getCreateTime());
        $this->assertNull($domain->getUpdateTime());
    }

    public function test_setName_and_getName(): void
    {
        $domain = new DnsDomain();
        $name = 'example.com';

        $result = $domain->setName($name);

        $this->assertSame($domain, $result);
        $this->assertEquals($name, $domain->getName());
    }

    public function test_setZoneId_and_getZoneId(): void
    {
        $domain = new DnsDomain();
        $zoneId = 'zone123456789';

        $result = $domain->setZoneId($zoneId);

        $this->assertSame($domain, $result);
        $this->assertEquals($zoneId, $domain->getZoneId());
    }

    public function test_setZoneId_with_null(): void
    {
        $domain = new DnsDomain();

        $result = $domain->setZoneId(null);

        $this->assertSame($domain, $result);
        $this->assertNull($domain->getZoneId());
    }

    public function test_setIamKey_and_getIamKey(): void
    {
        $domain = new DnsDomain();
        $iamKey = new IamKey();

        $result = $domain->setIamKey($iamKey);

        $this->assertSame($domain, $result);
        $this->assertSame($iamKey, $domain->getIamKey());
    }

    public function test_setIamKey_with_null(): void
    {
        $domain = new DnsDomain();

        $result = $domain->setIamKey(null);

        $this->assertSame($domain, $result);
        $this->assertNull($domain->getIamKey());
    }

    public function test_addRecord_and_getRecords(): void
    {
        $domain = new DnsDomain();
        $record = new DnsRecord();

        $result = $domain->addRecord($record);

        $this->assertSame($domain, $result);
        $this->assertCount(1, $domain->getRecords());
        $this->assertTrue($domain->getRecords()->contains($record));
        $this->assertSame($domain, $record->getDomain());
    }

    public function test_addRecord_duplicate_record(): void
    {
        $domain = new DnsDomain();
        $record = new DnsRecord();

        $domain->addRecord($record);
        $domain->addRecord($record); // 添加相同记录

        $this->assertCount(1, $domain->getRecords());
    }

    public function test_removeRecord(): void
    {
        $domain = new DnsDomain();
        $record = new DnsRecord();
        $domain->addRecord($record);

        $result = $domain->removeRecord($record);

        $this->assertSame($domain, $result);
        $this->assertCount(0, $domain->getRecords());
        $this->assertFalse($domain->getRecords()->contains($record));
        $this->assertNull($record->getDomain());
    }

    public function test_removeRecord_not_existing(): void
    {
        $domain = new DnsDomain();
        $record = new DnsRecord();

        $result = $domain->removeRecord($record);

        $this->assertSame($domain, $result);
        $this->assertCount(0, $domain->getRecords());
    }

    public function test_toString_with_name(): void
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');

        // 没有ID时返回空字符串
        $this->assertEquals('', (string) $domain);
    }

    public function test_toString_without_name(): void
    {
        $domain = new DnsDomain();

        $this->assertEquals('', (string) $domain);
    }

    public function test_getAccountId_from_iamKey(): void
    {
        $domain = new DnsDomain();
        $iamKey = new IamKey();
        $iamKey->setAccountId('account123');
        $domain->setIamKey($iamKey);

        $this->assertEquals('account123', $domain->getAccountId());
    }

    public function test_getAccountId_without_iamKey(): void
    {
        $domain = new DnsDomain();

        $this->assertNull($domain->getAccountId());
    }

    public function test_getAccountId_with_iamKey_without_accountId(): void
    {
        $domain = new DnsDomain();
        $iamKey = new IamKey();
        $domain->setIamKey($iamKey);

        $this->assertNull($domain->getAccountId());
    }

    public function test_setStatus_and_getStatus(): void
    {
        $domain = new DnsDomain();
        $status = DomainStatus::ACTIVE;

        $result = $domain->setStatus($status);

        $this->assertSame($domain, $result);
        $this->assertEquals($status, $domain->getStatus());
    }

    public function test_setStatus_with_null(): void
    {
        $domain = new DnsDomain();

        $result = $domain->setStatus(null);

        $this->assertSame($domain, $result);
        $this->assertNull($domain->getStatus());
    }

    public function test_setExpiresTime_and_getExpiresTime(): void
    {
        $domain = new DnsDomain();
        $expiresTime = new \DateTime('2024-12-31');

        $result = $domain->setExpiresTime($expiresTime);

        $this->assertSame($domain, $result);
        $this->assertSame($expiresTime, $domain->getExpiresTime());
    }

    public function test_setExpiresTime_with_null(): void
    {
        $domain = new DnsDomain();

        $result = $domain->setExpiresTime(null);

        $this->assertSame($domain, $result);
        $this->assertNull($domain->getExpiresTime());
    }

    public function test_setLockedUntilTime_and_getLockedUntilTime(): void
    {
        $domain = new DnsDomain();
        $lockedUntilTime = new \DateTime('2024-06-30');

        $result = $domain->setLockedUntilTime($lockedUntilTime);

        $this->assertSame($domain, $result);
        $this->assertSame($lockedUntilTime, $domain->getLockedUntilTime());
    }

    public function test_setLockedUntilTime_with_null(): void
    {
        $domain = new DnsDomain();

        $result = $domain->setLockedUntilTime(null);

        $this->assertSame($domain, $result);
        $this->assertNull($domain->getLockedUntilTime());
    }

    public function test_setAutoRenew_and_isAutoRenew(): void
    {
        $domain = new DnsDomain();

        $result = $domain->setAutoRenew(true);

        $this->assertSame($domain, $result);
        $this->assertTrue($domain->isAutoRenew());

        $domain->setAutoRenew(false);
        $this->assertFalse($domain->isAutoRenew());
    }

    public function test_setValid_and_isValid(): void
    {
        $domain = new DnsDomain();

        $result = $domain->setValid(true);

        $this->assertSame($domain, $result);
        $this->assertTrue($domain->isValid());

        $domain->setValid(false);
        $this->assertFalse($domain->isValid());

        $domain->setValid(null);
        $this->assertNull($domain->isValid());
    }

    public function test_setCreatedBy_and_getCreatedBy(): void
    {
        $domain = new DnsDomain();
        $createdBy = 'admin';

        $result = $domain->setCreatedBy($createdBy);

        $this->assertSame($domain, $result);
        $this->assertEquals($createdBy, $domain->getCreatedBy());
    }

    public function test_setCreatedBy_with_null(): void
    {
        $domain = new DnsDomain();

        $result = $domain->setCreatedBy(null);

        $this->assertSame($domain, $result);
        $this->assertNull($domain->getCreatedBy());
    }

    public function test_setUpdatedBy_and_getUpdatedBy(): void
    {
        $domain = new DnsDomain();
        $updatedBy = 'admin';

        $result = $domain->setUpdatedBy($updatedBy);

        $this->assertSame($domain, $result);
        $this->assertEquals($updatedBy, $domain->getUpdatedBy());
    }

    public function test_setUpdatedBy_with_null(): void
    {
        $domain = new DnsDomain();

        $result = $domain->setUpdatedBy(null);

        $this->assertSame($domain, $result);
        $this->assertNull($domain->getUpdatedBy());
    }

    public function test_setCreateTime_and_getCreateTime(): void
    {
        $domain = new DnsDomain();
        $createTime = new \DateTime('2023-01-01 10:00:00');

        $domain->setCreateTime($createTime);

        $this->assertSame($createTime, $domain->getCreateTime());
    }

    public function test_setCreateTime_with_null(): void
    {
        $domain = new DnsDomain();

        $domain->setCreateTime(null);

        $this->assertNull($domain->getCreateTime());
    }

    public function test_setUpdateTime_and_getUpdateTime(): void
    {
        $domain = new DnsDomain();
        $updateTime = new \DateTime('2023-01-01 11:00:00');

        $domain->setUpdateTime($updateTime);

        $this->assertSame($updateTime, $domain->getUpdateTime());
    }

    public function test_setUpdateTime_with_null(): void
    {
        $domain = new DnsDomain();

        $domain->setUpdateTime(null);

        $this->assertNull($domain->getUpdateTime());
    }

    public function test_complex_scenario_with_multiple_records(): void
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

    public function test_complex_scenario_with_all_properties(): void
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
        $createTime = new \DateTime('2023-06-01 10:00:00');
        $updateTime = new \DateTime('2023-06-01 15:00:00');

        $domain->setIamKey($iamKey)
            ->setName($name)
            ->setZoneId($zoneId)
            ->setStatus($status)
            ->setExpiresTime($expiresTime)
            ->setLockedUntilTime($lockedUntilTime)
            ->setAutoRenew($autoRenew)
            ->setValid($valid)
            ->setCreatedBy($createdBy)
            ->setUpdatedBy($updatedBy);
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

    public function test_edge_case_with_empty_string_name(): void
    {
        $domain = new DnsDomain();
        $domain->setName('');

        $this->assertEquals('', $domain->getName());
        $this->assertEquals('', (string) $domain);
    }

    public function test_edge_case_with_long_strings(): void
    {
        $domain = new DnsDomain();
        $longName = str_repeat('a', 100) . '.com';
        $longZoneId = str_repeat('b', 60);
        $longStatus = DomainStatus::SUSPENDED;

        $domain->setName($longName)
            ->setZoneId($longZoneId)
            ->setStatus($longStatus);

        $this->assertEquals($longName, $domain->getName());
        $this->assertEquals($longZoneId, $domain->getZoneId());
        $this->assertEquals($longStatus, $domain->getStatus());
    }

    public function test_edge_case_with_future_dates(): void
    {
        $domain = new DnsDomain();
        $futureDate = new \DateTime('+10 years');

        $domain->setExpiresTime($futureDate)
            ->setLockedUntilTime($futureDate);

        $this->assertSame($futureDate, $domain->getExpiresTime());
        $this->assertSame($futureDate, $domain->getLockedUntilTime());
    }

    public function test_edge_case_with_past_dates(): void
    {
        $domain = new DnsDomain();
        $pastDate = new \DateTime('-5 years');

        $domain->setExpiresTime($pastDate)
            ->setLockedUntilTime($pastDate);

        $this->assertSame($pastDate, $domain->getExpiresTime());
        $this->assertSame($pastDate, $domain->getLockedUntilTime());
    }
}
