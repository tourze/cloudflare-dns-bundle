<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Repository;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DnsDomainRepository::class)]
#[RunTestsInSeparateProcesses]
final class DnsDomainRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
    }

    public function testRepositoryInstance(): void
    {
        $repository = self::getService(DnsDomainRepository::class);
        $this->assertInstanceOf(DnsDomainRepository::class, $repository);
    }

    protected function createNewEntity(): object
    {
        $uniqueId = uniqid();
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key ' . $uniqueId);
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        $domain = new DnsDomain();
        $domain->setName('example-' . $uniqueId . '.com');
        $domain->setZoneId('test-zone-id');
        $domain->setIamKey($iamKey);
        $domain->setValid(true);

        return $domain;
    }

    protected function getRepository(): DnsDomainRepository
    {
        return self::getService(DnsDomainRepository::class);
    }
}
