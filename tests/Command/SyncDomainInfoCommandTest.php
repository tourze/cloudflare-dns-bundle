<?php

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDomainInfoCommand;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Service\DomainSynchronizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SyncDomainInfoCommandTest extends TestCase
{
    private SyncDomainInfoCommand $command;
    private DomainSynchronizer&MockObject $domainSynchronizer;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->domainSynchronizer = $this->createMock(DomainSynchronizer::class);

        $this->command = new SyncDomainInfoCommand(
            $this->domainSynchronizer
        );

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function test_execute_success_with_specific_domain(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->with('example.com')
            ->willReturn([$domain]);

        $this->domainSynchronizer->expects($this->once())
            ->method('syncDomainInfo')
            ->with($domain, $this->anything())
            ->willReturn(true);

        $result = $this->commandTester->execute([
            '--domain' => 'example.com',
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('同步完成', $this->commandTester->getDisplay());
    }

    public function test_execute_success_all_domains(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain2 = $this->createDnsDomain();
        $domain2->setName('test.com');

        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->with(null)
            ->willReturn([$domain1, $domain2]);

        $this->domainSynchronizer->expects($this->exactly(2))
            ->method('syncDomainInfo')
            ->willReturn(true);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('同步完成', $this->commandTester->getDisplay());
    }

    public function test_execute_domain_not_found(): void
    {
        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->with('notfound.com')
            ->willReturn([]);

        $result = $this->commandTester->execute([
            '--domain' => 'notfound.com',
        ]);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('未找到指定的域名', $this->commandTester->getDisplay());
    }

    public function test_execute_no_domains_found(): void
    {
        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->with(null)
            ->willReturn([]);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('没有找到任何域名', $this->commandTester->getDisplay());
    }

    public function test_execute_with_sync_failures(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain2 = $this->createDnsDomain();
        $domain2->setName('failed.com');

        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->willReturn([$domain1, $domain2]);

        $this->domainSynchronizer->expects($this->exactly(2))
            ->method('syncDomainInfo')
            ->willReturnCallback(function($domain) {
                return $domain->getName() !== 'failed.com';
            });

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('同步完成', $this->commandTester->getDisplay());
        $this->assertStringContainsString('成功: 1', $this->commandTester->getDisplay());
        $this->assertStringContainsString('失败: 1', $this->commandTester->getDisplay());
    }

    public function test_execute_all_sync_failures(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain2 = $this->createDnsDomain();
        $domain2->setName('failed.com');

        $this->domainSynchronizer->expects($this->once())
            ->method('findDomains')
            ->willReturn([$domain1, $domain2]);

        $this->domainSynchronizer->expects($this->exactly(2))
            ->method('syncDomainInfo')
            ->willReturn(false);

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
} 