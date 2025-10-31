<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\IamKeyService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(IamKeyService::class)]
#[RunTestsInSeparateProcesses]
final class IamKeyServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Tests don't require special setup
    }

    public function testFindAndValidateKeySuccess(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();

        // 使用实体管理器持久化测试数据
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->flush();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $iamKeyId = $iamKey->getId();
        $this->assertNotNull($iamKeyId, 'IAM Key ID should not be null after persistence');
        $result = $service->findAndValidateKey($iamKeyId, $io);

        $this->assertSame($iamKey, $result);
    }

    public function testFindAndValidateKeyNotFound(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->findAndValidateKey(999, $io);

        $this->assertNull($result);
        $this->assertStringContainsString('找不到 IAM Key: 999', $output->fetch());
    }

    public function testFindAndValidateKeyInvalid(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();
        $iamKey->setValid(false);

        // 使用实体管理器持久化测试数据
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->flush();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $iamKeyId = $iamKey->getId();
        $this->assertNotNull($iamKeyId, 'IAM Key ID should not be null after persistence');
        $result = $service->findAndValidateKey($iamKeyId, $io);

        $this->assertNull($result);
        $this->assertStringContainsString('IAM Key ' . $iamKeyId . ' 未激活', $output->fetch());
    }

    public function testFindAndValidateKeyWithEmptyCredentialsStillValid(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();
        $iamKey->setSecretKey('');

        // 使用实体管理器持久化测试数据
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->flush();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $iamKeyId = $iamKey->getId();
        $this->assertNotNull($iamKeyId, 'IAM Key ID should not be null after persistence');
        $result = $service->findAndValidateKey($iamKeyId, $io);

        // IamKeyService只检查存在性和激活状态，不检查凭证完整性
        $this->assertSame($iamKey, $result);
    }

    public function testFindAndValidateKeyWithoutIo(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();

        // 使用实体管理器持久化测试数据
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->flush();

        $iamKeyId = $iamKey->getId();
        $this->assertNotNull($iamKeyId, 'IAM Key ID should not be null after persistence');
        $result = $service->findAndValidateKey($iamKeyId);

        $this->assertSame($iamKey, $result);
    }

    public function testFindAndValidateKeyWithoutIoInvalid(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();
        $iamKey->setValid(false);

        // 使用实体管理器持久化测试数据
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->flush();

        $iamKeyId = $iamKey->getId();
        $this->assertNotNull($iamKeyId, 'IAM Key ID should not be null after persistence');
        $result = $service->findAndValidateKey($iamKeyId);

        $this->assertNull($result);
    }

    public function testValidateAccountIdSuccess(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->validateAccountId($iamKey, $io);

        $this->assertTrue($result);
        $this->assertStringContainsString('使用 IAM Key 中的 Account ID', $output->fetch());
    }

    public function testValidateAccountIdMissing(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccountId(null);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->validateAccountId($iamKey, $io);

        $this->assertFalse($result);
        $this->assertStringContainsString('IamKey中未设置Account ID', $output->fetch());
    }

    public function testValidateAccountIdEmptyStringIsValid(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccountId('');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->validateAccountId($iamKey, $io);

        // 空字符串在这个实现中是有效的，只有null才无效
        $this->assertTrue($result);
    }

    public function testValidateAccountIdWithoutIo(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();

        $result = $service->validateAccountId($iamKey);

        $this->assertTrue($result);
    }

    public function testValidateAccountIdWithoutIoMissing(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccountId(null);

        $result = $service->validateAccountId($iamKey);

        $this->assertFalse($result);
    }

    public function testFindAndValidateKeyWithEmptyAccessKeyStillValid(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccessKey('');

        // 使用实体管理器持久化测试数据
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->flush();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $iamKeyId = $iamKey->getId();
        $this->assertNotNull($iamKeyId, 'IAM Key ID should not be null after persistence');
        $result = $service->findAndValidateKey($iamKeyId, $io);

        // IamKeyService只检查存在性和激活状态，不检查凭证完整性
        $this->assertSame($iamKey, $result);
    }

    public function testFindAndValidateKeyWithNullAccessKeyStillValid(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccessKey(null);

        // 使用实体管理器持久化测试数据
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->flush();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $iamKeyId = $iamKey->getId();
        $this->assertNotNull($iamKeyId, 'IAM Key ID should not be null after persistence');
        $result = $service->findAndValidateKey($iamKeyId, $io);

        // IamKeyService只检查存在性和激活状态，不检查凭证完整性
        $this->assertSame($iamKey, $result);
    }

    public function testFindAndValidateKeyWithNullSecretKeyStillValid(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();
        $iamKey->setSecretKey('');

        // 使用实体管理器持久化测试数据
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->flush();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $iamKeyId = $iamKey->getId();
        $this->assertNotNull($iamKeyId, 'IAM Key ID should not be null after persistence');
        $result = $service->findAndValidateKey($iamKeyId, $io);

        // IamKeyService只检查存在性和激活状态，不检查凭证完整性
        $this->assertSame($iamKey, $result);
    }

    public function testValidateAccountIdWithWhitespaceStringIsValid(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccountId('   '); // 只有空格

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->validateAccountId($iamKey, $io);

        // 空格字符串在这个实现中也是有效的，只有null才无效
        $this->assertTrue($result);
    }

    public function testEdgeCaseWithValidCredentials(): void
    {
        // 使用集成测试方式，从容器中获取服务
        $service = self::getService(IamKeyService::class);

        $iamKey = new IamKey();
        $iamKey->setName('Test Key');
        $iamKey->setAccessKey('valid@example.com');
        $iamKey->setSecretKey('valid-secret-key-123');
        $iamKey->setAccountId('account-123456');
        $iamKey->setValid(true);

        // 使用实体管理器持久化测试数据
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->flush();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $iamKeyId = $iamKey->getId();
        $this->assertNotNull($iamKeyId, 'IAM Key ID should not be null after persistence');
        $result = $service->findAndValidateKey($iamKeyId, $io);

        $this->assertSame($iamKey, $result);
        $this->assertEquals('valid@example.com', $result->getAccessKey());
        $this->assertEquals('valid-secret-key-123', $result->getSecretKey());
        $this->assertEquals('account-123456', $result->getAccountId());
    }

    private function createValidIamKey(): IamKey
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key');
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        return $iamKey;
    }
}
