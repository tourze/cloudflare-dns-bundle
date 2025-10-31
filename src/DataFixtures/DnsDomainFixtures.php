<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\DataFixtures;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DomainStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * 域名数据填充
 */
#[When(env: 'test')]
class DnsDomainFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    // 使用常量定义引用名称
    public const EXAMPLE_DOMAIN_REFERENCE = 'example-domain';
    public const TEST_DOMAIN_REFERENCE = 'test-domain';
    public const DEMO_DOMAIN_REFERENCE = 'demo-domain';

    public function load(ObjectManager $manager): void
    {
        // 创建google.com域名
        $exampleDomain = new DnsDomain();
        $exampleDomain->setName('google.com');
        $exampleDomain->setIamKey($this->getReference(IamKeyFixtures::CLOUDFLARE_API_KEY_REFERENCE, IamKey::class));
        $exampleDomain->setZoneId('zone123456789');
        $exampleDomain->setStatus(DomainStatus::ACTIVE);
        $exampleDomain->setExpiresTime(new \DateTimeImmutable('+1 year'));
        $exampleDomain->setLockedUntilTime(new \DateTimeImmutable('+6 months'));
        $exampleDomain->setAutoRenew(true);
        $exampleDomain->setValid(true);

        $manager->persist($exampleDomain);

        // 创建github.com域名
        $testDomain = new DnsDomain();
        $testDomain->setName('github.com');
        $testDomain->setIamKey($this->getReference(IamKeyFixtures::CLOUDFLARE_DNS_KEY_REFERENCE, IamKey::class));
        $testDomain->setZoneId('zone987654321');
        $testDomain->setStatus(DomainStatus::ACTIVE);
        $testDomain->setExpiresTime(new \DateTimeImmutable('+2 years'));
        $testDomain->setLockedUntilTime(new \DateTimeImmutable('+3 months'));
        $testDomain->setAutoRenew(true);
        $testDomain->setValid(true);

        $manager->persist($testDomain);

        // 创建demo.com域名
        $demoDomain = new DnsDomain();
        $demoDomain->setName('demo.com');
        $demoDomain->setIamKey($this->getReference(IamKeyFixtures::CLOUDFLARE_DNS_KEY_REFERENCE, IamKey::class));
        $demoDomain->setZoneId('zone111222333');
        $demoDomain->setStatus(DomainStatus::PENDING);
        $demoDomain->setExpiresTime(new \DateTimeImmutable('+6 months'));
        $demoDomain->setLockedUntilTime(null);
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
