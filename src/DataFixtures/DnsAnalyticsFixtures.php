<?php

namespace CloudflareDnsBundle\DataFixtures;

use CloudflareDnsBundle\Entity\DnsAnalytics;
use CloudflareDnsBundle\Entity\DnsDomain;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * DNS分析数据填充
 */
class DnsAnalyticsFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $now = new \DateTime();
        $yesterday = new \DateTime('-1 day');
        $twoDaysAgo = new \DateTime('-2 days');

        // 为example.com创建DNS分析数据
        $this->createAnalyticsData(
            $manager,
            $this->getReference(DnsDomainFixtures::EXAMPLE_DOMAIN_REFERENCE, DnsDomain::class),
            'example.com',
            'A',
            1500,
            25.5,
            $now
        );

        $this->createAnalyticsData(
            $manager,
            $this->getReference(DnsDomainFixtures::EXAMPLE_DOMAIN_REFERENCE, DnsDomain::class),
            'www.example.com',
            'CNAME',
            850,
            18.2,
            $now
        );

        $this->createAnalyticsData(
            $manager,
            $this->getReference(DnsDomainFixtures::EXAMPLE_DOMAIN_REFERENCE, DnsDomain::class),
            'example.com',
            'MX',
            320,
            30.1,
            $now
        );

        // 昨天的数据
        $this->createAnalyticsData(
            $manager,
            $this->getReference(DnsDomainFixtures::EXAMPLE_DOMAIN_REFERENCE, DnsDomain::class),
            'example.com',
            'A',
            1200,
            28.7,
            $yesterday
        );

        // 前天的数据
        $this->createAnalyticsData(
            $manager,
            $this->getReference(DnsDomainFixtures::EXAMPLE_DOMAIN_REFERENCE, DnsDomain::class),
            'example.com',
            'A',
            980,
            32.4,
            $twoDaysAgo
        );

        // 为test.com创建DNS分析数据
        $this->createAnalyticsData(
            $manager,
            $this->getReference(DnsDomainFixtures::TEST_DOMAIN_REFERENCE, DnsDomain::class),
            'test.com',
            'A',
            750,
            21.3,
            $now
        );

        $this->createAnalyticsData(
            $manager,
            $this->getReference(DnsDomainFixtures::TEST_DOMAIN_REFERENCE, DnsDomain::class),
            'test.com',
            'TXT',
            120,
            15.8,
            $now
        );

        // 为demo.com创建DNS分析数据
        $this->createAnalyticsData(
            $manager,
            $this->getReference(DnsDomainFixtures::DEMO_DOMAIN_REFERENCE, DnsDomain::class),
            'demo.com',
            'A',
            480,
            19.7,
            $now
        );

        $this->createAnalyticsData(
            $manager,
            $this->getReference(DnsDomainFixtures::DEMO_DOMAIN_REFERENCE, DnsDomain::class),
            'api.demo.com',
            'A',
            350,
            22.5,
            $now
        );

        $manager->flush();
    }

    /**
     * 创建DNS分析数据记录
     */
    private function createAnalyticsData(
        ObjectManager $manager,
        DnsDomain $domain,
        string $queryName,
        string $queryType,
        int $queryCount,
        float $responseTimeAvg,
        \DateTime $statTime
    ): void {
        $analytics = new DnsAnalytics();
        $analytics->setDomain($domain);
        $analytics->setQueryName($queryName);
        $analytics->setQueryType($queryType);
        $analytics->setQueryCount($queryCount);
        $analytics->setResponseTimeAvg($responseTimeAvg);
        $analytics->setStatTime($statTime);

        $manager->persist($analytics);
    }

    public function getDependencies(): array
    {
        return [
            DnsDomainFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return [
            CfFixturesGroup::CLOUDFLARE_DNS,
            CfFixturesGroup::ANALYTICS,
        ];
    }
}
