<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDomainsCommand;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\DomainBatchSynchronizer;
use CloudflareDnsBundle\Service\IamKeyService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(SyncDomainsCommand::class)]
#[RunTestsInSeparateProcesses]
final class SyncDomainsCommandTest extends AbstractCommandTestCase
{
    private SyncDomainsCommand $command;

    private IamKeyService&MockObject $iamKeyService;

    private DomainBatchSynchronizer&MockObject $domainBatchSynchronizer;

    private CommandTester $commandTester;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        /*
         * 使用具体类 IamKeyService 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $this->iamKeyService = $this->createMock(IamKeyService::class);
        /*
         * 使用具体类 DomainBatchSynchronizer 而不是接口的原因：
         * 1) 该类提供了测试所需的具体方法实现
         * 2) 当前架构中该类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */
        $this->domainBatchSynchronizer = $this->createMock(DomainBatchSynchronizer::class);

        // 替换容器中的服务
        self::getContainer()->set(IamKeyService::class, $this->iamKeyService);
        self::getContainer()->set(DomainBatchSynchronizer::class, $this->domainBatchSynchronizer);

        $command = self::getService(SyncDomainsCommand::class);
        $this->assertInstanceOf(SyncDomainsCommand::class, $command);
        $this->command = $command;

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteSuccessWithSpecificDomain(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123'],
            ],
        ];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->with(1)
            ->willReturn($iamKey)
        ;

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->with($iamKey)
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->with($iamKey)
            ->willReturn($domainsData)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->with($domainsData, 'example.com', self::anything())
            ->willReturn($domainsData['result'])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('showSyncPreview')
            ->willReturn([['example.com', 'zone123', 'zone123', 'active', '更新']])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('confirmSync')
            ->with(true, false, self::anything())
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('executeBatchSync')
            ->willReturn([1, 0, 0])
        ;

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
            '--domain' => 'example.com',
            '--force' => true,
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('同步完成', $this->commandTester->getDisplay());
    }

    public function testExecuteWithDryRun(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123'],
            ],
        ];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey)
        ;

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willReturn($domainsData)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->willReturn($domainsData['result'])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('showSyncPreview')
            ->willReturn([['example.com', 'zone123', 'zone123', 'active', '更新']])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('confirmSync')
            ->with(false, true, self::anything())
            ->willReturn(false)
        ;

        $this->domainBatchSynchronizer->expects($this->never())
            ->method('executeBatchSync')
        ;

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $result);
    }

    public function testExecuteIamKeyNotFound(): void
    {
        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->with(999)
            ->willReturn(null)
        ;

        $result = $this->commandTester->execute([
            'iamKeyId' => 999,
        ]);

        $this->assertEquals(1, $result);
    }

    public function testExecuteInvalidAccountId(): void
    {
        $iamKey = $this->createIamKey();

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey)
        ;

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->with($iamKey)
            ->willReturn(false)
        ;

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
        ]);

        $this->assertEquals(1, $result);
    }

    public function testExecuteNoDomainsFound(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = [
            'success' => true,
            'result' => [],
        ];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey)
        ;

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willReturn($domainsData)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->willReturn([])
        ;

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
        ]);

        $this->assertEquals(0, $result);
    }

    public function testExecuteUserCancelsOperation(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123'],
            ],
        ];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey)
        ;

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willReturn($domainsData)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->willReturn($domainsData['result'])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('showSyncPreview')
            ->willReturn([['example.com', 'zone123', 'zone123', 'active', '更新']])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('confirmSync')
            ->willReturn(false)
        ;

        $this->domainBatchSynchronizer->expects($this->never())
            ->method('executeBatchSync')
        ;

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
        ]);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithSyncErrors(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123'],
                ['name' => 'error.com', 'status' => 'active', 'id' => 'zone456'],
            ],
        ];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey)
        ;

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willReturn($domainsData)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->willReturn($domainsData['result'])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('showSyncPreview')
            ->willReturn([
                ['example.com', 'zone123', 'zone123', 'active', '更新'],
                ['error.com', 'zone456', 'zone456', 'active', '更新'],
            ])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('confirmSync')
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('executeBatchSync')
            ->willReturn([1, 1, 0]) // 1 success, 1 error, 0 skipped
        ;

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
            '--force' => true,
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('同步完成', $this->commandTester->getDisplay());
        $this->assertStringContainsString('成功: 1', $this->commandTester->getDisplay());
        $this->assertStringContainsString('失败: 1', $this->commandTester->getDisplay());
    }

    public function testExecuteWithException(): void
    {
        $iamKey = $this->createIamKey();

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey)
        ;

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willThrowException(new \Exception('API Error'))
        ;

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

    public function testArgumentIamKeyId(): void
    {
        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->with(1)
            ->willReturn(null)
        ;

        $result = $this->commandTester->execute(['iamKeyId' => 1]);
        $this->assertEquals(1, $result);
    }

    public function testOptionDomain(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = ['success' => true, 'result' => []];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->with(1)
            ->willReturn($iamKey)
        ;

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willReturn($domainsData)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->with($domainsData, 'example.com', self::anything())
            ->willReturn([])
        ;

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
            '--domain' => 'example.com',
        ]);
        $this->assertEquals(1, $result);
    }

    public function testOptionDryRun(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = ['success' => true, 'result' => [
            ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123'],
        ]];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey)
        ;

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willReturn($domainsData)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->willReturn($domainsData['result'])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('showSyncPreview')
            ->willReturn([['example.com', 'zone123', 'zone123', 'active', '更新']])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('confirmSync')
            ->with(false, true, self::anything())
            ->willReturn(false)
        ;

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
            '--dry-run' => true,
        ]);
        $this->assertEquals(0, $result);
    }

    public function testOptionForce(): void
    {
        $iamKey = $this->createIamKey();
        $domainsData = ['success' => true, 'result' => [
            ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123'],
        ]];

        $this->iamKeyService->expects($this->once())
            ->method('findAndValidateKey')
            ->willReturn($iamKey)
        ;

        $this->iamKeyService->expects($this->once())
            ->method('validateAccountId')
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('listAllDomains')
            ->willReturn($domainsData)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('filterDomains')
            ->willReturn($domainsData['result'])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('showSyncPreview')
            ->willReturn([['example.com', 'zone123', 'zone123', 'active', '更新']])
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('confirmSync')
            ->with(true, false, self::anything())
            ->willReturn(true)
        ;

        $this->domainBatchSynchronizer->expects($this->once())
            ->method('executeBatchSync')
            ->willReturn([1, 0, 0])
        ;

        $result = $this->commandTester->execute([
            'iamKeyId' => 1,
            '--force' => true,
        ]);
        $this->assertEquals(0, $result);
    }
}
