<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\DataFixtures;

use CloudflareDnsBundle\Entity\IamKey;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * IAM密钥数据填充
 */
#[When(env: 'test')]
class IamKeyFixtures extends Fixture implements FixtureGroupInterface
{
    // 使用常量定义引用名称
    public const CLOUDFLARE_API_KEY_REFERENCE = 'cloudflare-api-key';
    public const CLOUDFLARE_DNS_KEY_REFERENCE = 'cloudflare-dns-key';

    public function load(ObjectManager $manager): void
    {
        // 创建Cloudflare API密钥
        $apiKey = new IamKey();
        $apiKey->setName('Cloudflare API');
        $apiKey->setAccessKey('cloudflare-user@gmail.com');
        $apiKey->setSecretKey('api-key-token-value-123456789');
        $apiKey->setAccountId('account123456789');
        $apiKey->setNote('用于访问Cloudflare API的主密钥');
        $apiKey->setValid(true);

        $manager->persist($apiKey);

        // 创建Cloudflare DNS密钥
        $dnsKey = new IamKey();
        $dnsKey->setName('Cloudflare DNS API');
        $dnsKey->setAccessKey('dns-api@gmail.com');
        $dnsKey->setSecretKey('dns-api-token-value-987654321');
        $dnsKey->setAccountId('account987654321');
        $dnsKey->setNote('专用于DNS管理的密钥');
        $dnsKey->setValid(true);

        $manager->persist($dnsKey);
        $manager->flush();

        // 添加引用以便其他Fixture使用
        $this->addReference(self::CLOUDFLARE_API_KEY_REFERENCE, $apiKey);
        $this->addReference(self::CLOUDFLARE_DNS_KEY_REFERENCE, $dnsKey);
    }

    public static function getGroups(): array
    {
        return [
            CfFixturesGroup::CLOUDFLARE_DNS,
            CfFixturesGroup::IAM_KEY,
        ];
    }
}
