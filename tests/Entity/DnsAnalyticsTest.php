<?php

namespace CloudflareDnsBundle\Tests\Entity;

use CloudflareDnsBundle\Entity\DnsAnalytics;
use CloudflareDnsBundle\Entity\DnsDomain;
use PHPUnit\Framework\TestCase;

/**
 * DNS分析数据实体测试
 */
class DnsAnalyticsTest extends TestCase
{
    public function test_constructor_initializes_default_values(): void
    {
        $analytics = new DnsAnalytics();

        $this->assertEquals(0, $analytics->getId());
        $this->assertNull($analytics->getDomain());
        $this->assertNull($analytics->getQueryName());
        $this->assertNull($analytics->getQueryType());
        $this->assertEquals(0, $analytics->getQueryCount());
        $this->assertEquals(0.0, $analytics->getResponseTimeAvg());
        $this->assertNull($analytics->getStatTime());
        $this->assertNull($analytics->getCreateTime());
        $this->assertNull($analytics->getUpdateTime());
    }

    public function test_setDomain_and_getDomain(): void
    {
        $analytics = new DnsAnalytics();
        $domain = new DnsDomain();

        $result = $analytics->setDomain($domain);

        $this->assertSame($analytics, $result);
        $this->assertSame($domain, $analytics->getDomain());
    }

    public function test_setDomain_with_null(): void
    {
        $analytics = new DnsAnalytics();

        $result = $analytics->setDomain(null);

        $this->assertSame($analytics, $result);
        $this->assertNull($analytics->getDomain());
    }

    public function test_setQueryName_and_getQueryName(): void
    {
        $analytics = new DnsAnalytics();
        $queryName = 'example.com';

        $result = $analytics->setQueryName($queryName);

        $this->assertSame($analytics, $result);
        $this->assertEquals($queryName, $analytics->getQueryName());
    }

    public function test_setQueryName_with_null(): void
    {
        $analytics = new DnsAnalytics();

        $result = $analytics->setQueryName(null);

        $this->assertSame($analytics, $result);
        $this->assertNull($analytics->getQueryName());
    }

    public function test_setQueryType_and_getQueryType(): void
    {
        $analytics = new DnsAnalytics();
        $queryType = 'A';

        $result = $analytics->setQueryType($queryType);

        $this->assertSame($analytics, $result);
        $this->assertEquals($queryType, $analytics->getQueryType());
    }

    public function test_setQueryType_with_null(): void
    {
        $analytics = new DnsAnalytics();

        $result = $analytics->setQueryType(null);

        $this->assertSame($analytics, $result);
        $this->assertNull($analytics->getQueryType());
    }

    public function test_setQueryCount_and_getQueryCount(): void
    {
        $analytics = new DnsAnalytics();
        $queryCount = 1500;

        $result = $analytics->setQueryCount($queryCount);

        $this->assertSame($analytics, $result);
        $this->assertEquals($queryCount, $analytics->getQueryCount());
    }

    public function test_setQueryCount_with_null(): void
    {
        $analytics = new DnsAnalytics();

        $result = $analytics->setQueryCount(null);

        $this->assertSame($analytics, $result);
        $this->assertNull($analytics->getQueryCount());
    }

    public function test_setQueryCount_with_zero(): void
    {
        $analytics = new DnsAnalytics();

        $result = $analytics->setQueryCount(0);

        $this->assertSame($analytics, $result);
        $this->assertEquals(0, $analytics->getQueryCount());
    }

    public function test_setQueryCount_with_negative_value(): void
    {
        $analytics = new DnsAnalytics();

        $result = $analytics->setQueryCount(-100);

        $this->assertSame($analytics, $result);
        $this->assertEquals(-100, $analytics->getQueryCount());
    }

    public function test_setResponseTimeAvg_and_getResponseTimeAvg(): void
    {
        $analytics = new DnsAnalytics();
        $responseTime = 25.5;

        $result = $analytics->setResponseTimeAvg($responseTime);

        $this->assertSame($analytics, $result);
        $this->assertEquals($responseTime, $analytics->getResponseTimeAvg());
    }

    public function test_setResponseTimeAvg_with_null(): void
    {
        $analytics = new DnsAnalytics();

        $result = $analytics->setResponseTimeAvg(null);

        $this->assertSame($analytics, $result);
        $this->assertNull($analytics->getResponseTimeAvg());
    }

    public function test_setResponseTimeAvg_with_zero(): void
    {
        $analytics = new DnsAnalytics();

        $result = $analytics->setResponseTimeAvg(0.0);

        $this->assertSame($analytics, $result);
        $this->assertEquals(0.0, $analytics->getResponseTimeAvg());
    }

    public function test_setResponseTimeAvg_with_negative_value(): void
    {
        $analytics = new DnsAnalytics();

        $result = $analytics->setResponseTimeAvg(-10.5);

        $this->assertSame($analytics, $result);
        $this->assertEquals(-10.5, $analytics->getResponseTimeAvg());
    }

    public function test_setStatTime_and_getStatTime(): void
    {
        $analytics = new DnsAnalytics();
        $statTime = new \DateTime('2023-01-01 12:00:00');

        $result = $analytics->setStatTime($statTime);

        $this->assertSame($analytics, $result);
        $this->assertSame($statTime, $analytics->getStatTime());
    }

    public function test_setStatTime_with_null(): void
    {
        $analytics = new DnsAnalytics();

        $result = $analytics->setStatTime(null);

        $this->assertSame($analytics, $result);
        $this->assertNull($analytics->getStatTime());
    }

    public function test_setCreateTime_and_getCreateTime(): void
    {
        $analytics = new DnsAnalytics();
        $createTime = new \DateTimeImmutable('2023-01-01 10:00:00');

        $analytics->setCreateTime($createTime);

        $this->assertSame($createTime, $analytics->getCreateTime());
    }

    public function test_setCreateTime_with_null(): void
    {
        $analytics = new DnsAnalytics();

        $analytics->setCreateTime(null);

        $this->assertNull($analytics->getCreateTime());
    }

    public function test_setUpdateTime_and_getUpdateTime(): void
    {
        $analytics = new DnsAnalytics();
        $updateTime = new \DateTimeImmutable('2023-01-01 11:00:00');

        $analytics->setUpdateTime($updateTime);

        $this->assertSame($updateTime, $analytics->getUpdateTime());
    }

    public function test_setUpdateTime_with_null(): void
    {
        $analytics = new DnsAnalytics();

        $analytics->setUpdateTime(null);

        $this->assertNull($analytics->getUpdateTime());
    }

    public function test_complex_scenario_with_all_properties(): void
    {
        $analytics = new DnsAnalytics();
        $domain = new DnsDomain();
        $domain->setName('example.com');
        
        $queryName = 'www.example.com';
        $queryType = 'CNAME';
        $queryCount = 2500;
        $responseTimeAvg = 18.75;
        $statTime = new \DateTime('2023-06-15 14:30:00');
        $createTime = new \DateTimeImmutable('2023-06-15 14:00:00');
        $updateTime = new \DateTimeImmutable('2023-06-15 15:00:00');

        $analytics->setDomain($domain)
            ->setQueryName($queryName)
            ->setQueryType($queryType)
            ->setQueryCount($queryCount)
            ->setResponseTimeAvg($responseTimeAvg)
            ->setStatTime($statTime);
        $analytics->setCreateTime($createTime);
        $analytics->setUpdateTime($updateTime);

        $this->assertSame($domain, $analytics->getDomain());
        $this->assertEquals($queryName, $analytics->getQueryName());
        $this->assertEquals($queryType, $analytics->getQueryType());
        $this->assertEquals($queryCount, $analytics->getQueryCount());
        $this->assertEquals($responseTimeAvg, $analytics->getResponseTimeAvg());
        $this->assertSame($statTime, $analytics->getStatTime());
        $this->assertSame($createTime, $analytics->getCreateTime());
        $this->assertSame($updateTime, $analytics->getUpdateTime());
    }

    public function test_edge_case_with_empty_strings(): void
    {
        $analytics = new DnsAnalytics();

        $analytics->setQueryName('')
            ->setQueryType('');

        $this->assertEquals('', $analytics->getQueryName());
        $this->assertEquals('', $analytics->getQueryType());
    }

    public function test_edge_case_with_long_strings(): void
    {
        $analytics = new DnsAnalytics();
        $longQueryName = str_repeat('a', 100) . '.com';
        $longQueryType = str_repeat('B', 50);

        $analytics->setQueryName($longQueryName)
            ->setQueryType($longQueryType);

        $this->assertEquals($longQueryName, $analytics->getQueryName());
        $this->assertEquals($longQueryType, $analytics->getQueryType());
    }

    public function test_edge_case_with_maximum_values(): void
    {
        $analytics = new DnsAnalytics();

        $analytics->setQueryCount(PHP_INT_MAX)
            ->setResponseTimeAvg(PHP_FLOAT_MAX);

        $this->assertEquals(PHP_INT_MAX, $analytics->getQueryCount());
        $this->assertEquals(PHP_FLOAT_MAX, $analytics->getResponseTimeAvg());
    }

    public function test_edge_case_with_minimum_values(): void
    {
        $analytics = new DnsAnalytics();

        $analytics->setQueryCount(PHP_INT_MIN)
            ->setResponseTimeAvg(-PHP_FLOAT_MAX);

        $this->assertEquals(PHP_INT_MIN, $analytics->getQueryCount());
        $this->assertEquals(-PHP_FLOAT_MAX, $analytics->getResponseTimeAvg());
    }
} 