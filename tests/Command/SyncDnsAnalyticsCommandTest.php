<?php

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDnsAnalyticsCommand;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Repository\DnsAnalyticsRepository;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\DnsAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SyncDnsAnalyticsCommandTest extends TestCase
{
    private SyncDnsAnalyticsCommand $command;
    private EntityManagerInterface&MockObject $entityManager;
    private DnsDomainRepository&MockObject $domainRepository;
    private DnsAnalyticsRepository&MockObject $analyticsRepository;
    private DnsAnalyticsService&MockObject $dnsService;
    private LoggerInterface&MockObject $logger;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->domainRepository = $this->createMock(DnsDomainRepository::class);
        $this->analyticsRepository = $this->createMock(DnsAnalyticsRepository::class);
        $this->dnsService = $this->createMock(DnsAnalyticsService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->command = new SyncDnsAnalyticsCommand(
            $this->entityManager,
            $this->domainRepository,
            $this->analyticsRepository,
            $this->dnsService,
            $this->logger
        );

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function test_execute_success_with_default_parameters(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->with(['valid' => true])
            ->willReturn([$domain]);

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(5);

        // Mock API response with proper structure
        $apiResponse = [
            'success' => true,
            'data' => [
                [
                    'time' => '2024-01-01T00:00:00Z',
                    'data' => [
                        [
                            'dimensions' => ['example.com', 'A'],
                            'metrics' => [100, 50]
                        ]
                    ]
                ]
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('getZoneDetails')
            ->willReturn([
                'result' => [
                    'plan' => ['name' => 'Enterprise'],
                    'status' => 'active'
                ]
            ]);

        $this->dnsService->expects($this->once())
            ->method('getDnsAnalytics')
            ->willReturn($apiResponse);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理了 5 条旧数据', $output);
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    public function test_execute_with_custom_time_parameters(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->with(['valid' => true])
            ->willReturn([$domain]);

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0);

        $this->dnsService->expects($this->once())
            ->method('getZoneDetails')
            ->willReturn([
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'active'
                ]
            ]);

        $this->dnsService->expects($this->once())
            ->method('getDnsAnalytics')
            ->with($domain, $this->callback(function ($params) {
                return $params['since'] === '-48h' &&
                    $params['until'] === '-24h' &&
                    $params['time_delta'] === '2h';
            }))
            ->willReturn(['success' => true, 'data' => []]);

        $result = $this->commandTester->execute([
            '--since' => '-48h',
            '--until' => '-24h',
            '--time-delta' => '2h',
        ]);

        $this->assertEquals(0, $result);
    }

    public function test_execute_with_cleanup_days(): void
    {
        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(10);

        $result = $this->commandTester->execute([
            '--cleanup-before' => '10',
        ]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理了 10 条旧数据', $output);
        $this->assertStringContainsString('没有找到符合条件的域名', $output);
    }

    public function test_execute_with_no_domains(): void
    {
        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理了 0 条旧数据', $output);
        $this->assertStringContainsString('没有找到符合条件的域名', $output);
    }

    public function test_execute_with_api_error(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$domain]);

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0);

        $this->dnsService->expects($this->once())
            ->method('getZoneDetails')
            ->willThrowException(new \Exception('API Error'));

        $this->dnsService->expects($this->once())
            ->method('getDnsAnalytics')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->commandTester->execute([]);

        $this->assertEquals(1, $result);
    }

    public function test_execute_with_empty_analytics_data(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$domain]);

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0);

        $this->dnsService->expects($this->once())
            ->method('getZoneDetails')
            ->willReturn([
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'active'
                ]
            ]);

        $this->dnsService->expects($this->once())
            ->method('getDnsAnalytics')
            ->willReturn(['success' => true, 'data' => []]);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理了 0 条旧数据', $output);
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    public function test_execute_with_invalid_time_delta(): void
    {
        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0);

        $result = $this->commandTester->execute([
            '--time-delta' => 'invalid',
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('没有找到符合条件的域名', $this->commandTester->getDisplay());
    }

    public function test_execute_with_invalid_since_parameter(): void
    {
        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0);

        $result = $this->commandTester->execute([
            '--since' => 'invalid-date',
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('没有找到符合条件的域名', $this->commandTester->getDisplay());
    }

    public function test_execute_with_multiple_domains(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain1->setName('example1.com');

        $domain2 = $this->createDnsDomain();
        $domain2->setName('example2.com');

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$domain1, $domain2]);

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0);

        $this->dnsService->expects($this->exactly(2))
            ->method('getZoneDetails')
            ->willReturn([
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'active'
                ]
            ]);

        $this->dnsService->expects($this->exactly(2))
            ->method('getDnsAnalytics')
            ->willReturn(['success' => true, 'data' => []]);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理了 0 条旧数据', $output);
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    public function test_execute_with_database_transaction_error(): void
    {
        $domain = $this->createDnsDomain();

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->willReturn([$domain]);

        $this->analyticsRepository->expects($this->once())
            ->method('cleanupBefore')
            ->willReturn(0);

        $this->dnsService->expects($this->once())
            ->method('getZoneDetails')
            ->willReturn([
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'active'
                ]
            ]);

        $apiResponse = [
            'success' => true,
            'data' => [
                [
                    'time' => '2024-01-01T00:00:00Z',
                    'data' => [
                        [
                            'dimensions' => ['example.com', 'A'],
                            'metrics' => [100, 50]
                        ]
                    ]
                ]
            ]
        ];

        $this->dnsService->expects($this->once())
            ->method('getDnsAnalytics')
            ->willReturn($apiResponse);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush')
            ->willThrowException(new \Exception('Database error'));

        $result = $this->commandTester->execute([]);

        $this->assertEquals(1, $result);
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
