<?php

namespace CloudflareDnsBundle\DataFixtures;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * 域名数据填充
 */
class DnsDomainFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    // 使用常量定义引用名称
    public const EXAMPLE_DOMAIN_REFERENCE = 'domain-example-com';
    public const TEST_DOMAIN_REFERENCE = 'domain-test-com';
    public const DEMO_DOMAIN_REFERENCE = 'domain-demo-com';

    public function load(ObjectManager $manager): void
    {
        // 创建example.com域名
        $exampleDomain = new DnsDomain();
        $exampleDomain->setName('example.com');
        $exampleDomain->setIamKey($this->getReference(IamKeyFixtures::CLOUDFLARE_API_KEY_REFERENCE, IamKey::class));
        $exampleDomain->setZoneId('zone123456789');
        $exampleDomain->setStatus('active');
        $exampleDomain->setExpiresAt(new \DateTime('+1 year'));
        $exampleDomain->setLockedUntil(new \DateTime('+6 months'));
        $exampleDomain->setAutoRenew(true);
        $exampleDomain->setValid(true);

        $manager->persist($exampleDomain);

        // 创建test.com域名
        $testDomain = new DnsDomain();
        $testDomain->setName('test.com');
        $testDomain->setIamKey($this->getReference(IamKeyFixtures::CLOUDFLARE_DNS_KEY_REFERENCE, IamKey::class));
        $testDomain->setZoneId('zone987654321');
        $testDomain->setStatus('active');
        $testDomain->setExpiresAt(new \DateTime('+2 years'));
        $testDomain->setLockedUntil(new \DateTime('+3 months'));
        $testDomain->setAutoRenew(true);
        $testDomain->setValid(true);

        $manager->persist($testDomain);

        // 创建demo.com域名
        $demoDomain = new DnsDomain();
        $demoDomain->setName('demo.com');
        $demoDomain->setIamKey($this->getReference(IamKeyFixtures::CLOUDFLARE_DNS_KEY_REFERENCE, IamKey::class));
        $demoDomain->setZoneId('zone111222333');
        $demoDomain->setStatus('pending');
        $demoDomain->setExpiresAt(new \DateTime('+6 months'));
        $demoDomain->setLockedUntil(null);
        $demoDomain->setAutoRenew(false);
        $demoDomain->setValid(true);

        $manager->persist($demoDomain);
        $manager->flush();

        // 添加引用以便其他Fixture使用
        $this->addReference(self::EXAMPLE_DOMAIN_REFERENCE, $exampleDomain);
        $this->addReference(self::TEST_DOMAIN_REFERENCE, $testDomain);
        $this->addReference(self::DEMO_DOMAIN_REFERENCE, $demoDomain);
    }

    public function getDependencies(): array
    {
        return [
            IamKeyFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return [
            CfFixturesGroup::CLOUDFLARE_DNS,
            CfFixturesGroup::DOMAIN,
        ];
    }
}
