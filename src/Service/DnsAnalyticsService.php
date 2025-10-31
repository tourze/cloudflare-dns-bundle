<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Abstract\BaseCloudflareService;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Exception\DnsAnalyticsException;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'cloudflare_dns')]
class DnsAnalyticsService extends BaseCloudflareService
{
    /**
     * 获取DNS分析报告
     *
     * @param array{
     *     dimensions?: array<string>,
     *     metrics?: array<string>,
     *     since?: string,
     *     until?: string,
     *     filters?: string,
     *     sort?: array<string>,
     *     limit?: int
     * } $params
     *
     * @return array<string, mixed>
     *
     * @throws TransportExceptionInterface
     */
    public function getDnsAnalytics(DnsDomain $domain, array $params = []): array
    {
        $zoneId = $domain->getZoneId();
        if (null === $zoneId) {
            throw new DnsAnalyticsException('域名Zone ID不能为空');
        }

        $client = $this->getCloudFlareClient($domain);
        $response = $client->getDnsAnalytics($zoneId, $params);

        return $this->handleResponse($this->toArraySafely($response), '获取CloudFlare域名分析报告失败', [
            'domain' => $domain,
            'params' => $params,
        ]);
    }

    /**
     * 获取按时间分组的DNS分析报告
     *
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
     *
     * @return array<string, mixed>
     *
     * @throws TransportExceptionInterface
     */
    public function getDnsAnalyticsByTime(DnsDomain $domain, array $params = []): array
    {
        $zoneId = $domain->getZoneId();
        if (null === $zoneId) {
            throw new DnsAnalyticsException('域名Zone ID不能为空');
        }

        $client = $this->getCloudFlareClient($domain);
        $response = $client->getDnsAnalyticsByTime($zoneId, $params);

        return $this->handleResponse($this->toArraySafely($response), '获取CloudFlare域名时间分析报告失败', [
            'domain' => $domain,
            'params' => $params,
        ]);
    }

    /**
     * 获取Zone详情
     *
     * 获取域名的Zone详细信息，包括计划类型和状态
     *
     * @return array<string, mixed>
     *
     * @throws TransportExceptionInterface
     */
    public function getZoneDetails(DnsDomain $domain): array
    {
        $zoneId = $domain->getZoneId();
        if (null === $zoneId) {
            throw new DnsAnalyticsException('域名Zone ID不能为空');
        }

        $client = $this->getCloudFlareClient($domain);
        $response = $client->getZoneDetails($zoneId);

        return $this->handleResponse($this->toArraySafely($response), '获取CloudFlare域名详情失败', [
            'domain' => $domain->getName(),
            'zoneId' => $domain->getZoneId(),
        ]);
    }
}
