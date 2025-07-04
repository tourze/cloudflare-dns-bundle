<?php

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Exception\DnsDomainException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class DnsDomainService extends BaseCloudflareService
{
    /**
     * 获取域名列表
     */
    public function listDomains(DnsDomain $domain): array
    {
        $client = $this->getCloudFlareClient($domain);
        $accountId = $domain->getAccountId();

        if ($accountId === null) {
            throw new DnsDomainException("没有设置Account ID，请确保IAM Key中已设置Account ID");
        }

        $response = $client->listDomains($accountId);
        return $this->handleResponse($response->toArray(), '获取CloudFlare域名列表失败', ['domain' => $domain]);
    }

    /**
     * 获取域名详情
     * @throws TransportExceptionInterface
     */
    public function getDomain(DnsDomain $domain, string $domainName): array
    {
        $client = $this->getCloudFlareClient($domain);
        $accountId = $domain->getAccountId();

        if ($accountId === null) {
            throw new DnsDomainException("没有设置Account ID，请确保IAM Key中已设置Account ID");
        }

        $response = $client->getDomain($accountId, $domainName);
        return $this->handleResponse($response->toArray(), '获取CloudFlare域名详情失败', [
            'domain' => $domain,
            'domainName' => $domainName,
        ]);
    }

    /**
     * 查询域名的Zone ID
     * 当API返回的域名数据中不包含Zone ID时，可以使用此方法查询
     */
    public function lookupZoneId(DnsDomain $domain): ?string
    {
        try {
            $client = $this->getCloudFlareClient($domain);
            $response = $client->lookupZoneId($domain->getName());
            $result = \json_decode($response->getContent(), true);
            //dump($result);

            if (!empty($result['result'])) {
                foreach ($result['result'] as $zone) {
                    if ($zone['name'] === $domain->getName()) {
                        return $zone['id'];
                    }
                }
            }

            $this->logger->warning('未找到域名的Zone ID', [
                'domain' => $domain->getName(),
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->logger->error('查询Zone ID失败', [
                'domain' => $domain->getName(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 同步域名的Zone ID
     * 如果API返回数据中包含id字段，则直接使用
     * 否则尝试通过lookupZoneId方法查询
     *
     * @param DnsDomain $domain 需要同步Zone ID的域名实体
     * @param array|null $domainData API返回的域名数据（如果有）
     * @return string|null 同步后的Zone ID，如果同步失败则返回null
     */
    public function syncZoneId(DnsDomain $domain, ?array $domainData = null): ?string
    {
        // 如果提供了domainData并且包含id字段，直接使用
        if ($domainData !== null && isset($domainData['id'])) {
            $domain->setZoneId($domainData['id']);
            $this->logger->info('从API返回数据中设置Zone ID', [
                'domain' => $domain->getName(),
                'zoneId' => $domainData['id']
            ]);
            return $domainData['id'];
        }

        // 如果域名已经有zoneId，且没有提供domainData或domainData中没有id，则不处理
        if ($domain->getZoneId() !== null && ($domainData === null || !isset($domainData['id']))) {
            return $domain->getZoneId();
        }

        // 尝试查询Zone ID
        $zoneId = $this->lookupZoneId($domain);
        if ($zoneId !== null) {
            $domain->setZoneId($zoneId);
            $this->logger->info('成功查找到Zone ID', [
                'domain' => $domain->getName(),
                'zoneId' => $zoneId
            ]);
            return $zoneId;
        }

        $this->logger->warning('未能查找到域名的Zone ID', [
            'domain' => $domain->getName()
        ]);

        return null;
    }
}
