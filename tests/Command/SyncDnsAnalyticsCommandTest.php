<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDnsAnalyticsCommand;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Repository\DnsAnalyticsRepository;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Client\CloudflareHttpClient;
use CloudflareDnsBundle\Service\DnsAnalyticsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(SyncDnsAnalyticsCommand::class)]
final class SyncDnsAnalyticsCommandTest extends AbstractCommandTestCase
{
    private SyncDnsAnalyticsCommand $command;

    private CommandTester $commandTester;

    private DnsDomainRepository $domainRepository;

    private DnsAnalyticsRepository $analyticsRepository;

    private HttpClientInterface $mockHttpClient;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        // 清理数据库中的所有测试数据，避免干扰
        $em = self::getEntityManager();
        $em->createQuery('DELETE FROM CloudflareDnsBundle\Entity\DnsAnalytics')->execute();
        $em->createQuery('DELETE FROM CloudflareDnsBundle\Entity\DnsDomain')->execute();
        $em->createQuery('DELETE FROM CloudflareDnsBundle\Entity\IamKey')->execute();
        $em->flush();
        $em->clear();

        // 获取真实的 Repository 实例
        $this->domainRepository = self::getService(DnsDomainRepository::class);
        $this->analyticsRepository = self::getService(DnsAnalyticsRepository::class);

        // 创建 Mock HttpClient 用于网络请求
        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);

        // 创建 DnsAnalyticsService，注入 Mock HttpClient
        $logger = self::getService(\Psr\Log\LoggerInterface::class);
        $dnsAnalyticsService = new DnsAnalyticsService($logger, $this->mockHttpClient);

        // 替换容器中的 DnsAnalyticsService
        self::getContainer()->set(DnsAnalyticsService::class, $dnsAnalyticsService);

        $command = self::getService(SyncDnsAnalyticsCommand::class);
        $this->assertInstanceOf(SyncDnsAnalyticsCommand::class, $command);
        $this->command = $command;

        $application = new Application();
        $application->addCommand($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteSuccessWithDefaultParameters(): void
    {
        $domain = $this->createDnsDomain();

        // Mock 网络响应
        $this->setupMockHttpClient([
            // getZoneDetails 响应
            [
                'success' => true,
                'result' => [
                    'plan' => ['name' => 'Enterprise'],
                    'status' => 'active',
                ],
            ],
            // getDnsAnalytics 响应
            [
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
            ],
        ]);

        $result = $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertEquals(0, $result, 'Command failed with output: ' . $output);
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    public function testExecuteWithCustomTimeParameters(): void
    {
        $domain = $this->createDnsDomain();

        $this->setupMockHttpClient([
            [
                'success' => true,
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'active',
                ],
            ],
            ['success' => true, 'data' => []],
        ]);

        $result = $this->commandTester->execute([
            '--since' => '-48h',
            '--until' => '-24h',
            '--time-delta' => '2h',
        ]);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithCleanupDays(): void
    {
        $result = $this->commandTester->execute([
            '--cleanup-before' => '10',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertEquals(0, $result, 'Command failed with output: ' . $output);
        $this->assertStringContainsString('没有找到符合条件的域名', $output);
    }

    public function testExecuteWithNoDomains(): void
    {
        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('没有找到符合条件的域名', $output);
    }

    public function testExecuteWithApiErrorsSkipped(): void
    {
        $domain = $this->createDnsDomain();

        // 模拟 API 返回失败响应
        $this->setupMockHttpClient([
            [
                'success' => true,
                'result' => [
                    'plan' => ['name' => 'Free'],
                    'status' => 'active',
                ],
            ],
            ['success' => false, 'errors' => ['API error']],
        ]);

        // 使用 --skip-errors 选项让命令继续执行而不中断
        $result = $this->commandTester->execute(['--skip-errors' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertEquals(0, $result, 'Command failed with output: ' . $output);
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    public function testExecuteWithEmptyAnalyticsData(): void
    {
        $domain = $this->createDnsDomain();

        $this->setupMockHttpClient([
            [
                'success' => true,
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'active',
                ],
            ],
            ['success' => true, 'data' => []],
        ]);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    public function testExecuteWithInvalidTimeDelta(): void
    {
        $result = $this->commandTester->execute([
            '--time-delta' => 'invalid',
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('没有找到符合条件的域名', $this->commandTester->getDisplay());
    }

    public function testExecuteWithInvalidSinceParameter(): void
    {
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

        $this->setupMockHttpClient([
            // domain1 getZoneDetails
            [
                'success' => true,
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'active',
                ],
            ],
            // domain1 getDnsAnalytics
            ['success' => true, 'data' => []],
            // domain2 getZoneDetails
            [
                'success' => true,
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'active',
                ],
            ],
            // domain2 getDnsAnalytics
            ['success' => true, 'data' => []],
        ]);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    public function testExecuteWithInactiveZone(): void
    {
        $domain = $this->createDnsDomain();

        // 模拟非活跃状态的 zone
        $this->setupMockHttpClient([
            [
                'success' => true,
                'result' => [
                    'plan' => ['name' => 'Pro'],
                    'status' => 'pending',
                ],
            ],
            ['success' => true, 'data' => []],
        ]);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始同步DNS分析数据', $output);
    }

    private function createDnsDomain(): DnsDomain
    {
        return $this->createDnsDomainWithName('example.com');
    }

    private function createDnsDomainWithName(string $name): DnsDomain
    {
        // 创建 IamKey，使用唯一名称避免约束冲突
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key ' . $name . ' ' . uniqid());
        $iamKey->setAccessKey('test-access-key@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setValid(true);

        // 创建 Domain
        $domain = new DnsDomain();
        $domain->setName($name);
        $domain->setZoneId('test-zone-id-' . $name);
        $domain->setValid(true);
        $domain->setIamKey($iamKey);

        // 持久化到数据库
        $em = self::getEntityManager();
        $em->persist($iamKey);
        $em->persist($domain);
        $em->flush();

        return $domain;
    }

    /**
     * 设置 Mock HttpClient，为每个网络请求准备响应
     * @param array<array<string, mixed>> $responses
     */
    private function setupMockHttpClient(array $responses): void
    {
        $mockResponses = array_map(
            fn (array $data) => $this->createMockResponse($data),
            $responses
        );

        $this->mockHttpClient->expects($this->exactly(count($mockResponses)))
            ->method('request')
            ->willReturnOnConsecutiveCalls(...$mockResponses);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createMockResponse(array $data): ResponseInterface
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('toArray')->willReturn($data);
        $mockResponse->method('getStatusCode')->willReturn(200);

        return $mockResponse;
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
