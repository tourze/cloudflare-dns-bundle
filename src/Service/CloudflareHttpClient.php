<?php

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsRecord;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Cloudflare API HTTP 客户端
 * 这是一个值对象,不应该被注册为服务
 */
#[Exclude]
final class CloudflareHttpClient
{
    private const API_BASE_URL = 'https://api.cloudflare.com/client/v4';
    private HttpClientInterface $client;

    public function __construct(
        private readonly string $accessKey,
        private readonly string $secretKey,
    ) {
        $this->client = HttpClient::create([
            'base_uri' => self::API_BASE_URL,
            'headers' => [
                'X-Auth-Email' => $this->accessKey,
                'X-Auth-Key' => $this->secretKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * 删除 DNS 记录
     */
    public function deleteDnsRecord(string $zoneId, string $recordId): ResponseInterface
    {
        return $this->client->request(
            'DELETE',
            sprintf('/client/v4/zones/%s/dns_records/%s', $zoneId, $recordId)
        );
    }

    /**
     * 创建 DNS 记录
     */
    public function createDnsRecord(string $zoneId, DnsRecord $record): ResponseInterface
    {
        return $this->client->request(
            'POST',
            sprintf('/client/v4/zones/%s/dns_records', $zoneId),
            [
                'json' => [
                    'type' => $record->getType()->value,
                    'name' => $record->getRecord(),
                    'content' => $record->getContent(),
                    'ttl' => $record->getTtl(),
                    'proxied' => $record->isProxy(),
                ],
            ]
        );
    }

    /**
     * 更新 DNS 记录
     */
    public function updateDnsRecord(string $zoneId, DnsRecord $record): ResponseInterface
    {
        return $this->client->request(
            'PUT',
            sprintf('/client/v4/zones/%s/dns_records/%s', $zoneId, $record->getRecordId()),
            [
                'json' => [
                    'type' => $record->getType()->value,
                    'name' => $record->getRecord(),
                    'content' => $record->getContent(),
                    'ttl' => $record->getTtl(),
                    'proxied' => $record->isProxy(),
                ],
            ]
        );
    }

    /**
     * 获取 DNS 记录列表
     */
    public function listDnsRecords(string $zoneId, array $params = []): ResponseInterface
    {
        return $this->client->request(
            'GET',
            sprintf('/client/v4/zones/%s/dns_records', $zoneId),
            ['query' => $params]
        );
    }

    /**
     * 获取单个 DNS 记录
     */
    public function getDnsRecord(string $zoneId, string $recordId): ResponseInterface
    {
        return $this->client->request(
            'GET',
            sprintf('/client/v4/zones/%s/dns_records/%s', $zoneId, $recordId)
        );
    }

    /**
     * 批量操作 DNS 记录
     * @param array{
     *     deletes?: array<string>,
     *     patches?: array<array{id: string, record: DnsRecord}>,
     *     posts?: array<DnsRecord>,
     *     puts?: array<array{id: string, record: DnsRecord}>
     * } $operations
     */
    public function batchDnsRecords(string $zoneId, array $operations): ResponseInterface
    {
        $data = [];
        if (!empty($operations['deletes'])) {
            $data['deletes'] = $operations['deletes'];
        }
        if (!empty($operations['patches'])) {
            $data['patches'] = array_map(fn(array $item) => [
                'id' => $item['id'],
                'record' => [
                    'type' => $item['record']->getType()->value,
                    'name' => $item['record']->getRecord(),
                    'content' => $item['record']->getContent(),
                    'ttl' => $item['record']->getTtl(),
                    'proxied' => $item['record']->isProxy(),
                ],
            ], $operations['patches']);
        }
        if (!empty($operations['posts'])) {
            $data['posts'] = array_map(fn(DnsRecord $record) => [
                'type' => $record->getType()->value,
                'name' => $record->getRecord(),
                'content' => $record->getContent(),
                'ttl' => $record->getTtl(),
                'proxied' => $record->isProxy(),
            ], $operations['posts']);
        }
        if (!empty($operations['puts'])) {
            $data['puts'] = array_map(fn(array $item) => [
                'id' => $item['id'],
                'record' => [
                    'type' => $item['record']->getType()->value,
                    'name' => $item['record']->getRecord(),
                    'content' => $item['record']->getContent(),
                    'ttl' => $item['record']->getTtl(),
                    'proxied' => $item['record']->isProxy(),
                ],
            ], $operations['puts']);
        }

        return $this->client->request(
            'POST',
            sprintf('/client/v4/zones/%s/dns_records/batch', $zoneId),
            ['json' => $data]
        );
    }

    /**
     * 导出 DNS 记录
     */
    public function exportDnsRecords(string $zoneId): ResponseInterface
    {
        return $this->client->request(
            'GET',
            sprintf('/client/v4/zones/%s/dns_records/export', $zoneId)
        );
    }

    /**
     * 导入 DNS 记录
     */
    public function importDnsRecords(string $zoneId, string $bindConfig): ResponseInterface
    {
        return $this->client->request(
            'POST',
            sprintf('/client/v4/zones/%s/dns_records/import', $zoneId),
            [
                'body' => $bindConfig,
                'headers' => [
                    'Content-Type' => 'text/plain',
                ],
            ]
        );
    }

    /**
     * 扫描 DNS 记录
     */
    public function scanDnsRecords(string $zoneId): ResponseInterface
    {
        return $this->client->request(
            'POST',
            sprintf('/client/v4/zones/%s/dns_records/scan', $zoneId)
        );
    }

    /**
     * 获取 DNS 分析报告
     * @throws TransportExceptionInterface
     */
    public function getDnsAnalytics(string $zoneId, array $params = []): ResponseInterface
    {
        return $this->client->request(
            'GET',
            sprintf('/client/v4/zones/%s/dns_analytics/report', $zoneId),
            ['query' => $params]
        );
    }

    /**
     * 获取按时间分组的 DNS 分析报告
     * @throws TransportExceptionInterface
     */
    public function getDnsAnalyticsByTime(string $zoneId, array $params = []): ResponseInterface
    {
        return $this->client->request(
            'GET',
            sprintf('/client/v4/zones/%s/dns_analytics/report/bytime', $zoneId),
            ['query' => $params]
        );
    }

    /**
     * 获取域名列表
     * @throws TransportExceptionInterface
     */
    public function listDomains(string $accountId): ResponseInterface
    {
        return $this->client->request(
            'GET',
            sprintf('/client/v4/accounts/%s/registrar/domains', $accountId)
        );
    }

    /**
     * 获取域名详情
     * @throws TransportExceptionInterface
     */
    public function getDomain(string $accountId, string $domainName): ResponseInterface
    {
        return $this->client->request(
            'GET',
            sprintf('/client/v4/accounts/%s/registrar/domains/%s', $accountId, $domainName)
        );
    }

    /**
     * 查询域名的Zone ID
     * 
     * 这个方法可以根据域名名称获取对应的Zone ID
     * 参考文档: https://api.cloudflare.com/#zone-list-zones
     */
    public function lookupZoneId(string $domainName): ResponseInterface
    {
        return $this->client->request(
            'GET',
            '/client/v4/zones',
            [
                'query' => [
                    'name' => $domainName,
                    'per_page' => 1
                ]
            ]
        );
    }
}
