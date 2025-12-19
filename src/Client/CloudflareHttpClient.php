<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Client;

use CloudflareDnsBundle\Entity\DnsRecord;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Cloudflare API HTTP 客户端
 * 这是一个值对象,不应该被注册为服务
 * 使用时需手动实例化并传入 accessKey, secretKey 和可选的 httpClient/logger
 */
#[Exclude]
final class CloudflareHttpClient
{
    private const API_BASE_URL = 'https://api.cloudflare.com/client/v4';

    private HttpClientInterface $client;

    private LoggerInterface $logger;

    public function __construct(
        private readonly string $accessKey,
        private readonly string $secretKey,
        ?HttpClientInterface $client = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->client = $client ?? HttpClient::create([
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
        $startTime = microtime(true);
        $url = sprintf('/client/v4/zones/%s/dns_records/%s', $zoneId, $recordId);

        $this->logger->info('Cloudflare API Request', [
            'method' => 'DELETE',
            'url' => $url,
            'zone_id' => $zoneId,
            'record_id' => $recordId,
        ]);

        try {
            // @audit-logged
            $response = $this->client->request('DELETE', $url);

            $this->logger->info('Cloudflare API Response', [
                'method' => 'DELETE',
                'url' => $url,
                'status_code' => $response->getStatusCode(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Cloudflare API Error', [
                'method' => 'DELETE',
                'url' => $url,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $e;
        }
    }

    /**
     * 创建 DNS 记录
     */
    public function createDnsRecord(string $zoneId, DnsRecord $record): ResponseInterface
    {
        $url = sprintf('/client/v4/zones/%s/dns_records', $zoneId);
        $options = [
            'json' => [
                'type' => $record->getType()->value,
                'name' => $record->getRecord(),
                'content' => $record->getContent(),
                'ttl' => $record->getTtl(),
                'proxied' => $record->isProxy(),
            ],
        ];

        return $this->executeRequest('POST', $url, $options, [
            'zone_id' => $zoneId,
            'record_type' => $record->getType()->value,
            'record_name' => $record->getRecord(),
        ]);
    }

    /**
     * 更新 DNS 记录
     */
    public function updateDnsRecord(string $zoneId, DnsRecord $record): ResponseInterface
    {
        $url = sprintf('/client/v4/zones/%s/dns_records/%s', $zoneId, $record->getRecordId());
        $options = [
            'json' => [
                'type' => $record->getType()->value,
                'name' => $record->getRecord(),
                'content' => $record->getContent(),
                'ttl' => $record->getTtl(),
                'proxied' => $record->isProxy(),
            ],
        ];

        return $this->executeRequest('PUT', $url, $options, [
            'zone_id' => $zoneId,
            'record_id' => $record->getRecordId(),
            'record_type' => $record->getType()->value,
            'record_name' => $record->getRecord(),
        ]);
    }

    /**
     * 获取 DNS 记录列表
     *
     * @param array<string, mixed> $params
     */
    public function listDnsRecords(string $zoneId, array $params = []): ResponseInterface
    {
        $url = sprintf('/client/v4/zones/%s/dns_records', $zoneId);

        return $this->executeRequest('GET', $url, ['query' => $params], ['zone_id' => $zoneId]);
    }

    /**
     * 获取单个 DNS 记录
     */
    public function getDnsRecord(string $zoneId, string $recordId): ResponseInterface
    {
        $url = sprintf('/client/v4/zones/%s/dns_records/%s', $zoneId, $recordId);

        return $this->executeRequest('GET', $url, [], ['zone_id' => $zoneId, 'record_id' => $recordId]);
    }

    /**
     * 批量操作 DNS 记录
     *
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
        if (($operations['deletes'] ?? []) !== []) {
            $data['deletes'] = $operations['deletes'];
        }
        if (($operations['patches'] ?? []) !== []) {
            $data['patches'] = array_map(fn (array $item) => [
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
        if (($operations['posts'] ?? []) !== []) {
            $data['posts'] = array_map(fn (DnsRecord $record) => [
                'type' => $record->getType()->value,
                'name' => $record->getRecord(),
                'content' => $record->getContent(),
                'ttl' => $record->getTtl(),
                'proxied' => $record->isProxy(),
            ], $operations['posts']);
        }
        if (($operations['puts'] ?? []) !== []) {
            $data['puts'] = array_map(fn (array $item) => [
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

        $url = sprintf('/client/v4/zones/%s/dns_records/batch', $zoneId);

        return $this->executeRequest('POST', $url, ['json' => $data], ['zone_id' => $zoneId]);
    }

    /**
     * 导出 DNS 记录
     */
    public function exportDnsRecords(string $zoneId): ResponseInterface
    {
        $url = sprintf('/client/v4/zones/%s/dns_records/export', $zoneId);

        return $this->executeRequest('GET', $url, [], ['zone_id' => $zoneId]);
    }

    /**
     * 导入 DNS 记录
     */
    public function importDnsRecords(string $zoneId, string $bindConfig): ResponseInterface
    {
        $url = sprintf('/client/v4/zones/%s/dns_records/import', $zoneId);
        $options = [
            'body' => $bindConfig,
            'headers' => [
                'Content-Type' => 'text/plain',
            ],
        ];

        return $this->executeRequest('POST', $url, $options, ['zone_id' => $zoneId]);
    }

    /**
     * 扫描 DNS 记录
     */
    public function scanDnsRecords(string $zoneId): ResponseInterface
    {
        $url = sprintf('/client/v4/zones/%s/dns_records/scan', $zoneId);

        return $this->executeRequest('POST', $url, [], ['zone_id' => $zoneId]);
    }

    /**
     * 获取 DNS 分析报告
     *
     * @param array<string, mixed> $params
     *
     * @throws TransportExceptionInterface
     */
    public function getDnsAnalytics(string $zoneId, array $params = []): ResponseInterface
    {
        $url = sprintf('/client/v4/zones/%s/dns_analytics/report', $zoneId);

        return $this->executeRequest('GET', $url, ['query' => $params], ['zone_id' => $zoneId]);
    }

    /**
     * 获取按时间分组的 DNS 分析报告
     *
     * @param array<string, mixed> $params
     *
     * @throws TransportExceptionInterface
     */
    public function getDnsAnalyticsByTime(string $zoneId, array $params = []): ResponseInterface
    {
        $url = sprintf('/client/v4/zones/%s/dns_analytics/report/bytime', $zoneId);

        return $this->executeRequest('GET', $url, ['query' => $params], ['zone_id' => $zoneId]);
    }

    /**
     * 获取域名列表
     *
     * @throws TransportExceptionInterface
     */
    public function listDomains(string $accountId): ResponseInterface
    {
        $url = sprintf('/client/v4/accounts/%s/registrar/domains', $accountId);

        return $this->executeRequest('GET', $url, [], ['account_id' => $accountId]);
    }

    /**
     * 获取域名详情
     *
     * @throws TransportExceptionInterface
     */
    public function getDomain(string $accountId, string $domainName): ResponseInterface
    {
        $url = sprintf('/client/v4/accounts/%s/registrar/domains/%s', $accountId, $domainName);

        return $this->executeRequest('GET', $url, [], ['account_id' => $accountId, 'domain_name' => $domainName]);
    }

    /**
     * 查询域名的Zone ID
     *
     * 这个方法可以根据域名名称获取对应的Zone ID
     * 参考文档: https://api.cloudflare.com/#zone-list-zones
     */
    public function lookupZoneId(string $domainName): ResponseInterface
    {
        $url = '/client/v4/zones';
        $options = [
            'query' => [
                'name' => $domainName,
                'per_page' => 1,
            ],
        ];

        return $this->executeRequest('GET', $url, $options, ['domain_name' => $domainName]);
    }

    /**
     * 获取域名详情信息
     */
    public function getZoneDetails(string $zoneId): ResponseInterface
    {
        $startTime = microtime(true);
        $url = sprintf('/client/v4/zones/%s', $zoneId);

        $this->logger->info('Cloudflare API Request', [
            'method' => 'GET',
            'url' => $url,
            'zone_id' => $zoneId,
        ]);

        try {
            // @audit-logged
            $response = $this->client->request('GET', $url);

            $this->logger->info('Cloudflare API Response', [
                'method' => 'GET',
                'url' => $url,
                'status_code' => $response->getStatusCode(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Cloudflare API Error', [
                'method' => 'GET',
                'url' => $url,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $e;
        }
    }

    /**
     * 执行 HTTP 请求并记录日志
     *
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     */
    private function executeRequest(string $method, string $url, array $options = [], array $context = []): ResponseInterface
    {
        $startTime = microtime(true);

        $this->logger->info('Cloudflare API Request', array_merge([
            'method' => $method,
            'url' => $url,
        ], $context));

        try {
            // @audit-logged
            $response = $this->client->request($method, $url, $options);

            $this->logger->info('Cloudflare API Response', array_merge([
                'method' => $method,
                'url' => $url,
                'status_code' => $response->getStatusCode(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ], $context));

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Cloudflare API Error', array_merge([
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ], $context));

            throw $e;
        }
    }
}
