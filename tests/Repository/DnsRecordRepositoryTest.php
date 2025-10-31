<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Repository;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DnsRecordRepository::class)]
#[RunTestsInSeparateProcesses]
final class DnsRecordRepositoryTest extends AbstractRepositoryTestCase
{
    private DnsRecordRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(DnsRecordRepository::class);
    }

    public function testRepositoryInstance(): void
    {
        $this->assertInstanceOf(DnsRecordRepository::class, $this->repository);
    }

    protected function createNewEntity(): object
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key ' . uniqid());
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        $domain = new DnsDomain();
        $domain->setName('test-' . uniqid() . '.com');
        $domain->setZoneId('test-zone-id-' . uniqid());
        $domain->setIamKey($iamKey);
        $domain->setValid(true);

        $record = new DnsRecord();
        $record->setDomain($domain);
        $record->setRecord('test');
        $record->setRecordId('record123-' . uniqid());
        $record->setType(DnsRecordType::A);
        $record->setContent('192.168.1.1');
        $record->setTtl(300);
        $record->setProxy(false);

        return $record;
    }

    protected function getRepository(): DnsRecordRepository
    {
        return $this->repository;
    }
}
