<?php

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class DnsAnalyticsService extends BaseCloudflareService
{
    /**
     * 获取DNS分析报告
     * @param array{
     *     dimensions?: array<string>,
     *     metrics?: array<string>,
     *     since?: string,
     *     until?: string,
     *     filters?: string,
     *     sort?: array<string>,
     *     limit?: int
     * } $params
     * @throws TransportExceptionInterface
     */
    public function getDnsAnalytics(DnsDomain $domain, array $params = []): array
    {
        $client = $this->getCloudFlareClient($domain);
        $response = $client->getDnsAnalytics($domain->getZoneId(), $params);
        return $this->handleResponse($response->toArray(), '获取CloudFlare域名分析报告失败', [
            'domain' => $domain,
            'params' => $params,
        ]);
    }

    /**
     * 获取按时间分组的DNS分析报告
     * @param array{
     *     dimensions?: array<string>,
     *     metrics?: array<string>,
     *     since?: string,
     *     until?: string,
     *     filters?: string,
     *     sort?: array<string>,
     *     limit?: int,
     *     time_delta?: string,
     *     time_periods?: array<string>
     * } $params
     * @throws TransportExceptionInterface
     */
    public function getDnsAnalyticsByTime(DnsDomain $domain, array $params = []): array
    {
        $client = $this->getCloudFlareClient($domain);
        $response = $client->getDnsAnalyticsByTime($domain->getZoneId(), $params);
        return $this->handleResponse($response->toArray(), '获取CloudFlare域名时间分析报告失败', [
            'domain' => $domain,
            'params' => $params,
        ]);
    }

    /**
     * 获取Zone详情
     *
     * 获取域名的Zone详细信息，包括计划类型和状态
     *
     * @throws TransportExceptionInterface
     */
    public function getZoneDetails(DnsDomain $domain): array
    {
        $client = $this->getCloudFlareClient($domain);
        $response = $client->getZoneDetails($domain->getZoneId());
        return $this->handleResponse($response->toArray(), '获取CloudFlare域名详情失败', [
            'domain' => $domain->getName(),
            'zoneId' => $domain->getZoneId(),
        ]);
    }
}
