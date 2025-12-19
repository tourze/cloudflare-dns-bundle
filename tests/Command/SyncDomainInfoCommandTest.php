<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDomainInfoCommand;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Service\DomainSynchronizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(SyncDomainInfoCommand::class)]
final class SyncDomainInfoCommandTest extends AbstractCommandTestCase
{
    private SyncDomainInfoCommand $command;

    private DomainSynchronizer&MockObject $domainSynchronizer;

    private CommandTester $commandTester;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        $this->domainSynchronizer = $this->createMock(DomainSynchronizer::class);

        // 替换容器中的服务
        self::getContainer()->set(DomainSynchronizer::class, $this->domainSynchronizer);

        $command = self::getService(SyncDomainInfoCommand::class);
        $this->assertInstanceOf(SyncDomainInfoCommand::class, $command);
        $this->command = $command;

        $application = new Application();
        $application->addCommand($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteSuccessWithSpecificDomain(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->with('example.com')
            ->willReturn([$domain])
        ;

        $this->domainSynchronizer->expects($this->once())
            ->method('syncDomainInfo')
            ->with($domain, self::anything())
            ->willReturn(true)
        ;

        $result = $this->commandTester->execute([
            '--domain' => 'example.com',
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('同步完成', $this->commandTester->getDisplay());
    }

    public function testExecuteSuccessAllDomains(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain2 = $this->createDnsDomain();
        $domain2->setName('test.com');

        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->with(null)
            ->willReturn([$domain1, $domain2])
        ;

        $this->domainSynchronizer->expects($this->exactly(2))
            ->method('syncDomainInfo')
            ->willReturn(true)
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('同步完成', $this->commandTester->getDisplay());
    }

    public function testExecuteDomainNotFound(): void
    {
        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->with('notfound.com')
            ->willReturn([])
        ;

        $result = $this->commandTester->execute([
            '--domain' => 'notfound.com',
        ]);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('未找到指定的域名', $this->commandTester->getDisplay());
    }

    public function testExecuteNoDomainsFound(): void
    {
        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->with(null)
            ->willReturn([])
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('没有找到任何域名', $this->commandTester->getDisplay());
    }

    public function testExecuteWithSyncFailures(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain2 = $this->createDnsDomain();
        $domain2->setName('failed.com');

        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->willReturn([$domain1, $domain2])
        ;

        $this->domainSynchronizer->expects($this->exactly(2))
            ->method('syncDomainInfo')
            ->willReturnCallback(function ($domain) {
                return $domain instanceof DnsDomain && 'failed.com' !== $domain->getName();
            })
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('同步完成', $this->commandTester->getDisplay());
        $this->assertStringContainsString('成功: 1', $this->commandTester->getDisplay());
        $this->assertStringContainsString('失败: 1', $this->commandTester->getDisplay());
    }

    public function testExecuteAllSyncFailures(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain2 = $this->createDnsDomain();
        $domain2->setName('failed.com');

        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->willReturn([$domain1, $domain2])
        ;

        $this->domainSynchronizer->expects($this->exactly(2))
            ->method('syncDomainInfo')
            ->willReturn(false)
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('没有同步任何域名', $this->commandTester->getDisplay());
        $this->assertStringContainsString('失败: 2', $this->commandTester->getDisplay());
    }

    private function createDnsDomain(): DnsDomain
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');
        $domain->setValid(true);

        return $domain;
    }

    public function testOptionDomain(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->with('example.com')
            ->willReturn([$domain])
        ;

        $this->domainSynchronizer->expects($this->once())
            ->method('syncDomainInfo')
            ->willReturn(true)
        ;

        $result = $this->commandTester->execute(['--domain' => 'example.com']);
        $this->assertEquals(0, $result);
    }
}
