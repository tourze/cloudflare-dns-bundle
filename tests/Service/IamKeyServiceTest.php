<?php

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Repository\IamKeyRepository;
use CloudflareDnsBundle\Service\IamKeyService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class IamKeyServiceTest extends TestCase
{
    private IamKeyService $service;
    private IamKeyRepository&MockObject $iamKeyRepository;

    protected function setUp(): void
    {
        $this->iamKeyRepository = $this->createMock(IamKeyRepository::class);
        $this->service = new IamKeyService($this->iamKeyRepository);
    }

    public function test_findAndValidateKey_success(): void
    {
        $iamKey = $this->createValidIamKey();

        $this->iamKeyRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($iamKey);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->findAndValidateKey(1, $io);

        $this->assertSame($iamKey, $result);
    }

    public function test_findAndValidateKey_not_found(): void
    {
        $this->iamKeyRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->findAndValidateKey(999, $io);

        $this->assertNull($result);
        $this->assertStringContainsString('找不到 IAM Key: 999', $output->fetch());
    }

    public function test_findAndValidateKey_invalid(): void
    {
        $iamKey = $this->createValidIamKey();
        $iamKey->setValid(false);

        $this->iamKeyRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($iamKey);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->findAndValidateKey(1, $io);

        $this->assertNull($result);
        $this->assertStringContainsString('IAM Key 1 未激活', $output->fetch());
    }

    public function test_findAndValidateKey_with_empty_credentials_still_valid(): void
    {
        $iamKey = $this->createValidIamKey();
        $iamKey->setSecretKey('');

        $this->iamKeyRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($iamKey);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->findAndValidateKey(1, $io);

        // IamKeyService只检查存在性和激活状态，不检查凭证完整性
        $this->assertSame($iamKey, $result);
    }

    public function test_findAndValidateKey_without_io(): void
    {
        $iamKey = $this->createValidIamKey();

        $this->iamKeyRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($iamKey);

        $result = $this->service->findAndValidateKey(1);

        $this->assertSame($iamKey, $result);
    }

    public function test_findAndValidateKey_without_io_invalid(): void
    {
        $iamKey = $this->createValidIamKey();
        $iamKey->setValid(false);

        $this->iamKeyRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($iamKey);

        $result = $this->service->findAndValidateKey(1);

        $this->assertNull($result);
    }

    public function test_validateAccountId_success(): void
    {
        $iamKey = $this->createValidIamKey();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->validateAccountId($iamKey, $io);

        $this->assertTrue($result);
        $this->assertStringContainsString('使用 IAM Key 中的 Account ID', $output->fetch());
    }

    public function test_validateAccountId_missing(): void
    {
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccountId(null);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->validateAccountId($iamKey, $io);

        $this->assertFalse($result);
        $this->assertStringContainsString('IamKey中未设置Account ID', $output->fetch());
    }

    public function test_validateAccountId_empty_string_is_valid(): void
    {
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccountId('');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->validateAccountId($iamKey, $io);

        // 空字符串在这个实现中是有效的，只有null才无效
        $this->assertTrue($result);
    }

    public function test_validateAccountId_without_io(): void
    {
        $iamKey = $this->createValidIamKey();

        $result = $this->service->validateAccountId($iamKey);

        $this->assertTrue($result);
    }

    public function test_validateAccountId_without_io_missing(): void
    {
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccountId(null);

        $result = $this->service->validateAccountId($iamKey);

        $this->assertFalse($result);
    }

    public function test_findAndValidateKey_with_empty_access_key_still_valid(): void
    {
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccessKey('');

        $this->iamKeyRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($iamKey);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->findAndValidateKey(1, $io);

        // IamKeyService只检查存在性和激活状态，不检查凭证完整性
        $this->assertSame($iamKey, $result);
    }

    public function test_findAndValidateKey_with_null_access_key_still_valid(): void
    {
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccessKey(null);

        $this->iamKeyRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($iamKey);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->findAndValidateKey(1, $io);

        // IamKeyService只检查存在性和激活状态，不检查凭证完整性
        $this->assertSame($iamKey, $result);
    }

    public function test_findAndValidateKey_with_null_secret_key_still_valid(): void
    {
        $iamKey = $this->createValidIamKey();
        $iamKey->setSecretKey('');

        $this->iamKeyRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($iamKey);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->findAndValidateKey(1, $io);

        // IamKeyService只检查存在性和激活状态，不检查凭证完整性
        $this->assertSame($iamKey, $result);
    }

    public function test_validateAccountId_with_whitespace_string_is_valid(): void
    {
        $iamKey = $this->createValidIamKey();
        $iamKey->setAccountId('   '); // 只有空格

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->validateAccountId($iamKey, $io);

        // 空格字符串在这个实现中也是有效的，只有null才无效
        $this->assertTrue($result);
    }

    public function test_edge_case_with_valid_credentials(): void
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test Key');
        $iamKey->setAccessKey('valid@example.com');
        $iamKey->setSecretKey('valid-secret-key-123');
        $iamKey->setAccountId('account-123456');
        $iamKey->setValid(true);

        $this->iamKeyRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($iamKey);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->findAndValidateKey(1, $io);

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