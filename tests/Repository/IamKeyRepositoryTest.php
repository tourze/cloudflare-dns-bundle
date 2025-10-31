<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Repository;

use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Repository\IamKeyRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(IamKeyRepository::class)]
#[RunTestsInSeparateProcesses]
final class IamKeyRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
    }

    public function testRepositoryInstance(): void
    {
        $repository = self::getService(IamKeyRepository::class);
        $this->assertInstanceOf(IamKeyRepository::class, $repository);
    }

    protected function createNewEntity(): object
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key ' . uniqid());
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        return $iamKey;
    }

    protected function getRepository(): IamKeyRepository
    {
        return self::getService(IamKeyRepository::class);
    }
}
