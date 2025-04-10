<?php

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class DnsDomainService extends BaseCloudflareService
{
    /**
     * 获取域名列表
     * @throws TransportExceptionInterface
     */
    public function listDomains(DnsDomain $domain): array
    {
        $client = $this->getCloudFlareClient($domain);
        $response = $client->listDomains($domain->getAccountId());
        return $this->handleResponse($response->toArray(), '获取CloudFlare域名列表失败', ['domain' => $domain]);
    }

    /**
     * 获取域名详情
     * @throws TransportExceptionInterface
     */
    public function getDomain(DnsDomain $domain, string $domainName): array
    {
        $client = $this->getCloudFlareClient($domain);
        $response = $client->getDomain($domain->getAccountId(), $domainName);
        return $this->handleResponse($response->toArray(), '获取CloudFlare域名详情失败', [
            'domain' => $domain,
            'domainName' => $domainName,
        ]);
    }
}
