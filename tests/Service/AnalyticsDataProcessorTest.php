<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsAnalytics;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Repository\DnsAnalyticsRepository;
use CloudflareDnsBundle\Service\AnalyticsDataProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AnalyticsDataProcessor::class)]
#[RunTestsInSeparateProcesses]
final class AnalyticsDataProcessorTest extends AbstractIntegrationTestCase
{
    private AnalyticsDataProcessor $processor;

    private DnsAnalyticsRepository $analyticsRepository;

    protected function onSetUp(): void
    {
        $this->processor = self::getService(AnalyticsDataProcessor::class);
        $this->analyticsRepository = self::getService(DnsAnalyticsRepository::class);
    }

    public function testSaveAnalyticsDataWithEmptyArray(): void
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $persistedDomain = $this->persistAndFlush($domain);
        self::assertInstanceOf(DnsDomain::class, $persistedDomain);

        // 获取此域名当前的分析记录数
        $countBefore = count($this->analyticsRepository->findBy(['domain' => $persistedDomain]));

        $result = $this->processor->saveAnalyticsData($persistedDomain, []);

        self::assertSame(0, $result);
        // 验证没有新增记录
        $countAfter = count($this->analyticsRepository->findBy(['domain' => $persistedDomain]));
        self::assertSame($countBefore, $countAfter);
    }

    public function testSaveAnalyticsDataWithValidData(): void
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $persistedDomain = $this->persistAndFlush($domain);
        self::assertInstanceOf(DnsDomain::class, $persistedDomain);

        $data = [
            [
                'time' => '2024-01-01T00:00:00Z',
                'data' => [
                    [
                        'dimensions' => ['example.com', 'A'],
                        'metrics' => [100, 50.5],
                    ],
                ],
            ],
        ];

        // 获取此域名当前的分析记录数
        $countBefore = count($this->analyticsRepository->findBy(['domain' => $persistedDomain]));

        $result = $this->processor->saveAnalyticsData($persistedDomain, $data);

        self::assertSame(1, $result);

        // 验证新增了1条记录
        $analytics = $this->analyticsRepository->findBy(['domain' => $persistedDomain]);
        self::assertCount($countBefore + 1, $analytics);
        // 验证最后一条记录属于这个域名
        $lastAnalytics = end($analytics);
        self::assertInstanceOf(DnsAnalytics::class, $lastAnalytics);
        $analyticsDomain = $lastAnalytics->getDomain();
        self::assertNotNull($analyticsDomain);
        self::assertSame($persistedDomain->getId(), $analyticsDomain->getId());
    }

    public function testSaveAnalyticsDataIgnoresInvalidItems(): void
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $persistedDomain = $this->persistAndFlush($domain);
        self::assertInstanceOf(DnsDomain::class, $persistedDomain);

        $data = [
            'invalid_item',
            ['no_data_field' => 'value'],
            ['data' => 'not_an_array'],
        ];

        // 获取此域名当前的分析记录数
        $countBefore = count($this->analyticsRepository->findBy(['domain' => $persistedDomain]));

        $result = $this->processor->saveAnalyticsData($persistedDomain, $data);

        self::assertSame(0, $result);
        // 验证没有新增记录
        $countAfter = count($this->analyticsRepository->findBy(['domain' => $persistedDomain]));
        self::assertSame($countBefore, $countAfter);
    }

    public function testSaveAnalyticsDataIgnoresInvalidDataItems(): void
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $persistedDomain = $this->persistAndFlush($domain);
        self::assertInstanceOf(DnsDomain::class, $persistedDomain);

        $data = [
            [
                'time' => '2024-01-01T00:00:00Z',
                'data' => [
                    'invalid_data_item',
                    ['dimensions' => 'not_array'],
                    ['dimensions' => [], 'metrics' => []],
                ],
            ],
        ];

        // 获取此域名当前的分析记录数
        $countBefore = count($this->analyticsRepository->findBy(['domain' => $persistedDomain]));

        $result = $this->processor->saveAnalyticsData($persistedDomain, $data);

        self::assertSame(0, $result);
        // 验证没有新增记录
        $countAfter = count($this->analyticsRepository->findBy(['domain' => $persistedDomain]));
        self::assertSame($countBefore, $countAfter);
    }
}
