<?php

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDomainsCommand;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\DomainBatchSynchronizer;
use CloudflareDnsBundle\Service\IamKeyService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SyncDomainsCommandTest extends TestCase
{
    private SyncDomainsCommand $command;
    private IamKeyService&MockObject $iamKeyService;
    private DomainBatchSynchronizer&MockObject $domainBatchSynchronizer;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->iamKeyService = $this->createMock(IamKeyService::class);
        $this->domainBatchSynchronizer = $this->createMock(DomainBatchSynchronizer::class);

        $this->command = new SyncDomainsCommand(
            $this->iamKeyService,
            $this->domainBatchSynchronizer
        );

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function test_execute_success_with_specific_domain(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123']
            ]
        ];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->with(1)
            ->willReturn($iamKey);

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->with($iamKey)
            ->willReturn(true);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->with($iamKey)
            ->willReturn($domainsData);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->with($domainsData, 'example.com', $this->anything())
            ->willReturn($domainsData['result']);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('showSyncPreview')
            ->willReturn([['example.com', 'zone123', 'zone123', 'active', '更新']]);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('confirmSync')
            ->with(true, false, $this->anything())
            ->willReturn(true);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('executeBatchSync')
            ->willReturn([1, 0, 0]);

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
            '--domain' => 'example.com',
            '--force' => true,
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('同步完成', $this->commandTester->getDisplay());
    }

    public function test_execute_with_dry_run(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123']
            ]
        ];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey);

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willReturn($domainsData);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->willReturn($domainsData['result']);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('showSyncPreview')
            ->willReturn([['example.com', 'zone123', 'zone123', 'active', '更新']]);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('confirmSync')
            ->with(false, true, $this->anything())
            ->willReturn(false);

        $this->domainBatchSynchronizer->expects($this->never())
            ->method('executeBatchSync');

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $result);
    }

    public function test_execute_iam_key_not_found(): void
    {
        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->with(999)
            ->willReturn(null);

        $result = $this->commandTester->execute([
            'iamKeyId' => 999,
        ]);

        $this->assertEquals(1, $result);
    }

    public function test_execute_invalid_account_id(): void
    {
        $iamKey = $this->createIamKey();

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey);

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->with($iamKey)
            ->willReturn(false);

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
        ]);

        $this->assertEquals(1, $result);
    }

    public function test_execute_no_domains_found(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = [
            'success' => true,
            'result' => []
        ];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey);

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willReturn($domainsData);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->willReturn([]);

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
        ]);

        $this->assertEquals(0, $result);
    }

    public function test_execute_user_cancels_operation(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123']
            ]
        ];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey);

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willReturn($domainsData);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->willReturn($domainsData['result']);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('showSyncPreview')
            ->willReturn([['example.com', 'zone123', 'zone123', 'active', '更新']]);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('confirmSync')
            ->willReturn(false);

        $this->domainBatchSynchronizer->expects($this->never())
            ->method('executeBatchSync');

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
        ]);

        $this->assertEquals(0, $result);
    }

    public function test_execute_with_sync_errors(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123'],
                ['name' => 'error.com', 'status' => 'active', 'id' => 'zone456']
            ]
        ];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey);

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willReturn($domainsData);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->willReturn($domainsData['result']);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('showSyncPreview')
            ->willReturn([
                ['example.com', 'zone123', 'zone123', 'active', '更新'],
                ['error.com', 'zone456', 'zone456', 'active', '更新']
            ]);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('confirmSync')
            ->willReturn(true);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('executeBatchSync')
            ->willReturn([1, 1, 0]); // 1 success, 1 error, 0 skipped

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
            '--force' => true,
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('同步完成', $this->commandTester->getDisplay());
        $this->assertStringContainsString('成功: 1', $this->commandTester->getDisplay());
        $this->assertStringContainsString('失败: 1', $this->commandTester->getDisplay());
    }

    public function test_execute_with_exception(): void
    {
        $iamKey = $this->createIamKey();

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey);

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true);

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
        ]);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('同步域名时发生错误', $this->commandTester->getDisplay());
    }

    private function createIamKey(): IamKey
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