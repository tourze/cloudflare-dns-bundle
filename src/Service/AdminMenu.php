<?php

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsAnalytics;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use Knp\Menu\ItemInterface;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

/**
 * Cloudflare DNS菜单服务
 */
class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private readonly LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (!$item->getChild('Cloudflare DNS')) {
            $item->addChild('Cloudflare DNS');
        }

        $dnsMenu = $item->getChild('Cloudflare DNS');
        
        // IAM密钥菜单
        $dnsMenu->addChild('IAM密钥')
            ->setUri($this->linkGenerator->getCurdListPage(IamKey::class))
            ->setAttribute('icon', 'fas fa-key');
        
        // 域名管理菜单
        $dnsMenu->addChild('域名管理')
            ->setUri($this->linkGenerator->getCurdListPage(DnsDomain::class))
            ->setAttribute('icon', 'fas fa-globe');
        
        // DNS解析菜单
        $dnsMenu->addChild('DNS解析')
            ->setUri($this->linkGenerator->getCurdListPage(DnsRecord::class))
            ->setAttribute('icon', 'fas fa-server');
        
        // DNS分析菜单
        $dnsMenu->addChild('DNS分析')
            ->setUri($this->linkGenerator->getCurdListPage(DnsAnalytics::class))
            ->setAttribute('icon', 'fas fa-chart-line');
    }
}
