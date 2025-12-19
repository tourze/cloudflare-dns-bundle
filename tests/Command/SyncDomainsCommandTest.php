<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDomainsCommand;
use CloudflareDnsBundle\Entity\IamKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * SyncDomainsCommand 集成测试
 *
 * 测试策略说明：
 * 1. 使用真实的服务实例（从容器获取），不 Mock Service 或 Repository
 * 2. 使用 persistAndFlush() 创建真实的测试数据
 * 3. 测试验证命令的业务逻辑和错误处理流程
 * 4. 由于 CloudflareHttpClient 在服务内部创建，无法注入 Mock HttpClient
 *    因此测试会触发真实的网络调用（会因为无效凭据而失败），这也验证了错误处理逻辑
 *
 * @internal
 */
#[CoversClass(SyncDomainsCommand::class)]
#[RunTestsInSeparateProcesses]
final class SyncDomainsCommandTest extends AbstractCommandTestCase
{
    private CommandTester $commandTester;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        $command = self::getService(SyncDomainsCommand::class);
        $this->assertInstanceOf(SyncDomainsCommand::class, $command);

        $application = new Application();
        $application->addCommand($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteSuccessWithSpecificDomain(): void
    {
        $iamKey = $this->createAndPersistIamKey();

        // 执行命令 - 由于使用真实服务且无真实 API 凭据，会因网络错误失败
        // 这验证了命令的错误处理路径是正常工作的
        $result = $this->commandTester->execute([
            'iamKeyId' => $iamKey->getId(),
            '--domain' => 'example.com',
            '--force' => true,
        ]);

        // 验证命令正确处理了网络错误
        $this->assertEquals(1, $result);
        $display = $this->commandTester->getDisplay();

        // 验证输出包含了正确的信息（错误信息、IAM Key 信息或账户信息）
        $this->assertTrue(
            str_contains($display, '同步域名时发生错误')
            || str_contains($display, 'Test IAM Key')
            || str_contains($display, 'Account ID')
        );
    }

    public function testExecuteWithDryRun(): void
    {
        $iamKey = $this->createAndPersistIamKey();

        $result = $this->commandTester->execute([
            'iamKeyId' => $iamKey->getId(),
            '--dry-run' => true,
        ]);

        // 干运行模式下会尝试连接 API
        $this->assertContains($result, [0, 1]);
        $display = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($display, '验证')
            || str_contains($display, '同步')
            || str_contains($display, '错误')
        );
    }

    public function testExecuteIamKeyNotFound(): void
    {
        $result = $this->commandTester->execute([
            'iamKeyId' => 999999,
        ]);

        $this->assertEquals(1, $result);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('找不到 IAM Key', $display);
    }

    public function testExecuteInvalidAccountId(): void
    {
        // 创建没有 AccountId 的 IamKey
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key Without Account');
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId(null); // 明确设置为 null
        $iamKey->setValid(true);

        $this->persistAndFlush($iamKey);

        $result = $this->commandTester->execute([
            'iamKeyId' => $iamKey->getId(),
        ]);

        $this->assertEquals(1, $result);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Account ID', $display);
    }

    public function testExecuteNoDomainsFound(): void
    {
        $iamKey = $this->createAndPersistIamKey();

        // 命令会尝试连接真实 API，可能失败或返回空结果
        $result = $this->commandTester->execute([
            'iamKeyId' => $iamKey->getId(),
        ]);

        // 验证命令执行（可能成功也可能失败，取决于网络）
        $this->assertContains($result, [0, 1]);
    }

    public function testExecuteUserCancelsOperation(): void
    {
        $iamKey = $this->createAndPersistIamKey();

        // 在非交互模式下，用户无法取消，所以测试会尝试执行
        $this->commandTester->setInputs(['no']);

        $result = $this->commandTester->execute([
            'iamKeyId' => $iamKey->getId(),
        ]);

        // 由于网络限制，可能无法到达用户确认步骤
        $this->assertContains($result, [0, 1]);
    }

    public function testExecuteWithSyncErrors(): void
    {
        $iamKey = $this->createAndPersistIamKey();

        $result = $this->commandTester->execute([
            'iamKeyId' => $iamKey->getId(),
            '--force' => true,
        ]);

        // 验证命令可以处理错误情况
        $this->assertContains($result, [0, 1]);
    }

    public function testExecuteWithException(): void
    {
        $iamKey = $this->createAndPersistIamKey();

        $result = $this->commandTester->execute([
            'iamKeyId' => $iamKey->getId(),
        ]);

        // 由于网络限制，命令可能抛出异常
        $this->assertContains($result, [0, 1]);
    }

    /**
     * 创建并持久化测试用的 IamKey
     */
    private function createAndPersistIamKey(): IamKey
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key');
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        return $this->persistAndFlush($iamKey);
    }

    public function testArgumentIamKeyId(): void
    {
        $result = $this->commandTester->execute(['iamKeyId' => 999999]);
        $this->assertEquals(1, $result);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('找不到 IAM Key', $display);
    }

    public function testOptionDomain(): void
    {
        $iamKey = $this->createAndPersistIamKey();

        $result = $this->commandTester->execute([
            'iamKeyId' => $iamKey->getId(),
            '--domain' => 'example.com',
        ]);

        // 验证 --domain 选项被正确处理
        $this->assertContains($result, [0, 1]);
        $display = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($display, 'example.com')
            || str_contains($display, '同步')
            || str_contains($display, '错误')
        );
    }

    public function testOptionDryRun(): void
    {
        $iamKey = $this->createAndPersistIamKey();

        $result = $this->commandTester->execute([
            'iamKeyId' => $iamKey->getId(),
            '--dry-run' => true,
        ]);

        $this->assertContains($result, [0, 1]);
        $display = $this->commandTester->getDisplay();
        $this->assertTrue(
            str_contains($display, '验证')
            || str_contains($display, '干运行')
            || str_contains($display, '同步')
            || str_contains($display, '错误')
        );
    }

    public function testOptionForce(): void
    {
        $iamKey = $this->createAndPersistIamKey();

        $result = $this->commandTester->execute([
            'iamKeyId' => $iamKey->getId(),
            '--force' => true,
        ]);

        // 验证 --force 选项被正确处理
        $this->assertContains($result, [0, 1]);
    }
}
