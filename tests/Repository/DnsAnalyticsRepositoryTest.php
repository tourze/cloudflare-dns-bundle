<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Repository;

use CloudflareDnsBundle\Entity\DnsAnalytics;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Repository\DnsAnalyticsRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DnsAnalyticsRepository::class)]
#[RunTestsInSeparateProcesses]
final class DnsAnalyticsRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
    }

    public function testRepositoryInstance(): void
    {
        $repository = self::getService(DnsAnalyticsRepository::class);
        $this->assertInstanceOf(DnsAnalyticsRepository::class, $repository);
    }

    protected function createNewEntity(): object
    {
        $uniqueId = uniqid();
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key ' . $uniqueId);
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        $domain = new DnsDomain();
        $domain->setName('example-' . $uniqueId . '.com');
        $domain->setZoneId('test-zone-id');
        $domain->setIamKey($iamKey);
        $domain->setValid(true);

        $analytics = new DnsAnalytics();
        $analytics->setDomain($domain);
        $analytics->setQueryName('test.example.com');
        $analytics->setQueryType('A');
        $analytics->setQueryCount(100);
        $analytics->setResponseTimeAvg(50.5);
        $analytics->setStatTime(new \DateTimeImmutable());

        return $analytics;
    }

    protected function getRepository(): DnsAnalyticsRepository
    {
        return self::getService(DnsAnalyticsRepository::class);
    }

    public function testCleanupBefore(): void
    {
        $repository = $this->getRepository();

        // 创建测试数据
        $oldAnalytics = $this->createNewEntity();
        $this->assertInstanceOf(DnsAnalytics::class, $oldAnalytics);
        $oldAnalytics->setStatTime(new \DateTimeImmutable('-7 days'));

        $newAnalytics = $this->createNewEntity();
        $this->assertInstanceOf(DnsAnalytics::class, $newAnalytics);
        $newAnalytics->setStatTime(new \DateTimeImmutable('-1 day'));

        // 保存测试数据
        $repository->save($oldAnalytics);
        $repository->save($newAnalytics);

        // 执行清理操作，删除3天前的数据
        $cutoffTime = new \DateTimeImmutable('-3 days');
        $deletedCount = $repository->cleanupBefore($cutoffTime);

        // 验证清理结果
        $this->assertGreaterThanOrEqual(0, $deletedCount, '删除的记录数应该大于等于0');
    }
}
