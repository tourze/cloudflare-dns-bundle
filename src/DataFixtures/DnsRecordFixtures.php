<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\DataFixtures;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * DNS记录数据填充
 */
#[When(env: 'test')]
class DnsRecordFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        // 为example.com创建A记录
        $exampleA = new DnsRecord();
        $exampleA->setDomain($this->getReference(DnsDomainFixtures::EXAMPLE_DOMAIN_REFERENCE, DnsDomain::class));
        $exampleA->setType(DnsRecordType::A);
        $exampleA->setRecord('@');
        $exampleA->setRecordId('record123456789');
        $exampleA->setContent('192.168.1.1');
        $exampleA->setTtl(3600);
        $exampleA->setProxy(true);

        $manager->persist($exampleA);

        // 为example.com创建CNAME记录
        $exampleCname = new DnsRecord();
        $exampleCname->setDomain($this->getReference(DnsDomainFixtures::EXAMPLE_DOMAIN_REFERENCE, DnsDomain::class));
        $exampleCname->setType(DnsRecordType::CNAME);
        $exampleCname->setRecord('www');
        $exampleCname->setRecordId('record987654321');
        $exampleCname->setContent('google.com');
        $exampleCname->setTtl(3600);
        $exampleCname->setProxy(true);

        $manager->persist($exampleCname);

        // 为example.com创建MX记录
        $exampleMx = new DnsRecord();
        $exampleMx->setDomain($this->getReference(DnsDomainFixtures::EXAMPLE_DOMAIN_REFERENCE, DnsDomain::class));
        $exampleMx->setType(DnsRecordType::MX);
        $exampleMx->setRecord('@');
        $exampleMx->setRecordId('record555666777');
        $exampleMx->setContent('10 mail.google.com');
        $exampleMx->setTtl(3600);
        $exampleMx->setProxy(false);

        $manager->persist($exampleMx);

        // 为test.com创建A记录
        $testA = new DnsRecord();
        $testA->setDomain($this->getReference(DnsDomainFixtures::TEST_DOMAIN_REFERENCE, DnsDomain::class));
        $testA->setType(DnsRecordType::A);
        $testA->setRecord('@');
        $testA->setRecordId('record111222333');
        $testA->setContent('192.168.2.2');
        $testA->setTtl(1800);
        $testA->setProxy(true);

        $manager->persist($testA);

        // 为test.com创建TXT记录
        $testTxt = new DnsRecord();
        $testTxt->setDomain($this->getReference(DnsDomainFixtures::TEST_DOMAIN_REFERENCE, DnsDomain::class));
        $testTxt->setType(DnsRecordType::TXT);
        $testTxt->setRecord('@');
        $testTxt->setRecordId('record444555666');
        $testTxt->setContent('v=spf1 ip4:192.168.2.0/24 ~all');
        $testTxt->setTtl(3600);
        $testTxt->setProxy(false);

        $manager->persist($testTxt);

        // 为demo.com创建A记录
        $demoA = new DnsRecord();
        $demoA->setDomain($this->getReference(DnsDomainFixtures::DEMO_DOMAIN_REFERENCE, DnsDomain::class));
        $demoA->setType(DnsRecordType::A);
        $demoA->setRecord('@');
        $demoA->setRecordId('record999888777');
        $demoA->setContent('192.168.3.3');
        $demoA->setTtl(1800);
        $demoA->setProxy(true);

        $manager->persist($demoA);

        // 为demo.com创建子域名A记录
        $demoSubA = new DnsRecord();
        $demoSubA->setDomain($this->getReference(DnsDomainFixtures::DEMO_DOMAIN_REFERENCE, DnsDomain::class));
        $demoSubA->setType(DnsRecordType::A);
        $demoSubA->setRecord('api');
        $demoSubA->setRecordId('record777888999');
        $demoSubA->setContent('192.168.3.4');
        $demoSubA->setTtl(1800);
        $demoSubA->setProxy(true);

        $manager->persist($demoSubA);

        $manager->flush();
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
            CfFixturesGroup::RECORD,
        ];
    }
}
