<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Entity;

use CloudflareDnsBundle\Entity\DnsAnalytics;
use CloudflareDnsBundle\Entity\DnsDomain;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * DNS分析数据实体测试
 *
 * @internal
 */
#[CoversClass(DnsAnalytics::class)]
final class DnsAnalyticsTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new DnsAnalytics();
    }

    public function testDomainSetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(DnsAnalytics::class, $entity);
        $domain = new DnsDomain();
        $domain->setName('example.com');

        $entity->setDomain($domain);
        $this->assertEquals($domain, $entity->getDomain(), 'Getter should return the set value');
    }

    public function testQueryNameSetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(DnsAnalytics::class, $entity);
        $entity->setQueryName('example.com');
        $this->assertEquals('example.com', $entity->getQueryName(), 'Getter should return the set value');
    }

    public function testQueryTypeSetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(DnsAnalytics::class, $entity);
        $entity->setQueryType('A');
        $this->assertEquals('A', $entity->getQueryType(), 'Getter should return the set value');
    }

    public function testQueryCountSetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(DnsAnalytics::class, $entity);
        $entity->setQueryCount(1500);
        $this->assertEquals(1500, $entity->getQueryCount(), 'Getter should return the set value');
    }

    public function testResponseTimeAvgSetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(DnsAnalytics::class, $entity);
        $entity->setResponseTimeAvg(25.5);
        $this->assertEquals(25.5, $entity->getResponseTimeAvg(), 'Getter should return the set value');
    }

    public function testStatTimeSetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(DnsAnalytics::class, $entity);
        $time = new \DateTime('2023-01-01 12:00:00');
        $entity->setStatTime($time);
        $this->assertEquals($time, $entity->getStatTime(), 'Getter should return the set value');
    }

    public function testToStringMethod(): void
    {
        $analytics = new DnsAnalytics();
        $analytics->setQueryName('example.com');
        $analytics->setQueryType('A');

        // 没有ID时返回空字符串
        $this->assertEquals('', (string) $analytics);

        $analytics->setQueryName('');
        $this->assertEquals('', (string) $analytics);
    }

    public function testEdgeCaseWithLongStrings(): void
    {
        $analytics = new DnsAnalytics();
        $longQueryName = str_repeat('a', 100) . '.com';
        $longQueryType = str_repeat('B', 50);

        $analytics->setQueryName($longQueryName);
        $analytics->setQueryType($longQueryType);

        $this->assertEquals($longQueryName, $analytics->getQueryName());
        $this->assertEquals($longQueryType, $analytics->getQueryType());
    }

    public function testEdgeCaseWithMaximumValues(): void
    {
        $analytics = new DnsAnalytics();

        $analytics->setQueryCount(PHP_INT_MAX);
        $analytics->setResponseTimeAvg(PHP_FLOAT_MAX);

        $this->assertEquals(PHP_INT_MAX, $analytics->getQueryCount());
        $this->assertEquals(PHP_FLOAT_MAX, $analytics->getResponseTimeAvg());
    }

    public function testEdgeCaseWithMinimumValues(): void
    {
        $analytics = new DnsAnalytics();

        $analytics->setQueryCount(PHP_INT_MIN);
        $analytics->setResponseTimeAvg(-PHP_FLOAT_MAX);

        $this->assertEquals(PHP_INT_MIN, $analytics->getQueryCount());
        $this->assertEquals(-PHP_FLOAT_MAX, $analytics->getResponseTimeAvg());
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'domain' => ['domain', new DnsDomain()];
        yield 'queryName' => ['queryName', 'example.com'];
        yield 'queryType' => ['queryType', 'A'];
        yield 'queryCount' => ['queryCount', 1500];
        yield 'responseTimeAvg' => ['responseTimeAvg', 25.5];
        yield 'statTime' => ['statTime', new \DateTime('2023-01-01 12:00:00')];
    }
}
