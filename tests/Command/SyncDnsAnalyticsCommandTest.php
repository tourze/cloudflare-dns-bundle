<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDnsAnalyticsCommand;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Repository\DnsAnalyticsRepository;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\DnsAnalyticsService;
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
#[CoversClass(SyncDnsAnalyticsCommand::class)]
final class SyncDnsAnalyticsCommandTest extends AbstractCommandTestCase
{
    private SyncDnsAnalyticsCommand $command;

    private DnsDomainRepository&MockObject $domainRepository;

    private DnsAnalyticsRepository&MockObject $analyticsRepository;

    private DnsAnalyticsService&MockObject $dnsService;

    private CommandTester $commandTester;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        $this->domainRepository = $this->createMock(DnsDomainRepository::class);
        $this->analyticsRepository = $this->createMock(DnsAnalyticsRepository::class);
        $this->dnsService = $this->createMock(DnsAnalyticsService::class);

        // 替换容器中的服务
        self::getContainer()->set(DnsDomainRepository::class, $this->domainRepository);
        self::getContainer()->set(DnsAnalyticsRepository::class, $this->analyticsRepository);
        self::getContainer()->set(DnsAnalyticsService::class, $this->dnsService);

        $command = self::getService(SyncDnsAnalyticsCommand::class);
        $this->assertInstanceOf(SyncDnsAnalyticsCommand::class, $command);
        $this->command = $command;

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteSuccessWithDefaultParameters(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->with(['valid' => true])
            ->willReturn([$domain])
        ;

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(5)
        ;

        // Mock API response with proper structure
        $apiResponse = [
            'success' => true,
            'data' => [
                [
                    'time' => '2024-01-01T00:00:00Z',
                    'data' => [
                        [
                            'dimensions' => ['example.com', 'A'],
                            'metrics' => [100, 50],
                        ],
                    ],
                ],
            ],
        ];

        $this->dnsService->expects($this->once())
            ->method('getZoneDetails')
            ->willReturn([
                'result' => [
                    'plan' => ['name' => 'Enterprise'],
                    'status' => 'active',
                ],
            ])
        ;

        $this->dnsService->expects($this->once())
            ->method('getDnsAnalytics')
            ->willReturn($apiResponse)
        ;

        // 不验证 entityManager 的调用，因为测试重点是命令执行结果
        // 而不是具体的数据库操作细节

        $result = $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertEquals(0, $result, 'Command failed with output: ' . $output);
        $this->assertStringContainsString('清理了 5 条旧数据', $output);
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    public function testExecuteWithCustomTimeParameters(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->with(['valid' => true])
            ->willReturn([$domain])
        ;

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0)
        ;

        $this->dnsService->expects($this->once())
            ->method('getZoneDetails')
            ->willReturn([
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'active',
                ],
            ])
        ;

        $this->dnsService->expects($this->once())
            ->method('getDnsAnalytics')
            ->with($domain, self::callback(function ($params) {
                return is_array($params) && '-48h' === $params['since']
                    && '-24h' === $params['until']
                    && '2h' === $params['time_delta'];
            }))
            ->willReturn(['success' => true, 'data' => []])
        ;

        $result = $this->commandTester->execute([
            '--since' => '-48h',
            '--until' => '-24h',
            '--time-delta' => '2h',
        ]);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCleanupDays(): void
    {
        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([])
        ;

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(10)
        ;

        $result = $this->commandTester->execute([
            '--cleanup-before' => '10',
        ]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理了 10 条旧数据', $output);
        $this->assertStringContainsString('没有找到符合条件的域名', $output);
    }

    public function testExecuteWithNoDomains(): void
    {
        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([])
        ;

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0)
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理了 0 条旧数据', $output);
        $this->assertStringContainsString('没有找到符合条件的域名', $output);
    }

    public function testExecuteWithApiErrorsSkipped(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$domain])
        ;

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0)
        ;

        // 模拟 API 返回空数据而不是抛出异常
        $this->dnsService->expects($this->once())
            ->method('getZoneDetails')
            ->willReturn([
                'result' => [
                    'plan' => ['name' => 'Free'],
                    'status' => 'active',
                ],
            ])
        ;

        $this->dnsService->expects($this->once())
            ->method('getDnsAnalytics')
            ->willReturn(['success' => false, 'data' => []])
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理了 0 条旧数据', $output);
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    public function testExecuteWithEmptyAnalyticsData(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$domain])
        ;

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0)
        ;

        $this->dnsService->expects($this->once())
            ->method('getZoneDetails')
            ->willReturn([
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'active',
                ],
            ])
        ;

        $this->dnsService->expects($this->once())
            ->method('getDnsAnalytics')
            ->willReturn(['success' => true, 'data' => []])
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理了 0 条旧数据', $output);
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    public function testExecuteWithInvalidTimeDelta(): void
    {
        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([])
        ;

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0)
        ;

        $result = $this->commandTester->execute([
            '--time-delta' => 'invalid',
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('没有找到符合条件的域名', $this->commandTester->getDisplay());
    }

    public function testExecuteWithInvalidSinceParameter(): void
    {
        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([])
        ;

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0)
        ;

        $result = $this->commandTester->execute([
            '--since' => 'invalid-date',
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('没有找到符合条件的域名', $this->commandTester->getDisplay());
    }

    public function testExecuteWithMultipleDomains(): void
    {
        $domain1 = $this->createDnsDomainWithName('example1.com');
        $domain2 = $this->createDnsDomainWithName('example2.com');

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$domain1, $domain2])
        ;

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0)
        ;

        $this->dnsService->expects($this->exactly(2))
            ->method('getZoneDetails')
            ->willReturn([
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'active',
                ],
            ])
        ;

        $this->dnsService->expects($this->exactly(2))
            ->method('getDnsAnalytics')
            ->willReturn(['success' => true, 'data' => []])
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理了 0 条旧数据', $output);
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    public function testExecuteWithInactiveZone(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$domain])
        ;

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0)
        ;

        // 模拟非活跃状态的 zone
        $this->dnsService->expects($this->once())
            ->method('getZoneDetails')
            ->willReturn([
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'pending',
                ],
            ])
        ;

        $this->dnsService->expects($this->once())
            ->method('getDnsAnalytics')
            ->willReturn(['success' => true, 'data' => []])
        ;

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理了 0 条旧数据', $output);
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    private function createDnsDomain(): DnsDomain
    {
        return $this->createDnsDomainWithName('example.com');
    }

    private function createDnsDomainWithName(string $name): DnsDomain
    {
        $domain = new DnsDomain();
        $domain->setName($name);
        $domain->setZoneId('test-zone-id');
        $domain->setValid(true);

        self::getEntityManager()->persist($domain);
        self::getEntityManager()->flush();

        return $domain;
    }

    public function testOptionSince(): void
    {
        $result = $this->commandTester->execute(['--since' => '-24h']);
        $this->assertEquals(0, $result);
    }

    public function testOptionUntil(): void
    {
        $result = $this->commandTester->execute(['--until' => '-1h']);
        $this->assertEquals(0, $result);
    }

    public function testOptionTimeDelta(): void
    {
        $result = $this->commandTester->execute(['--time-delta' => '2h']);
        $this->assertEquals(0, $result);
    }

    public function testOptionCleanupBefore(): void
    {
        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(5)
        ;

        $result = $this->commandTester->execute(['--cleanup-before' => '7']);
        $this->assertEquals(0, $result);
    }

    public function testOptionDomainId(): void
    {
        $result = $this->commandTester->execute(['--domain-id' => '1']);
        $this->assertEquals(0, $result);
    }

    public function testOptionSkipErrors(): void
    {
        $result = $this->commandTester->execute(['--skip-errors' => true]);
        $this->assertEquals(0, $result);
    }
}
