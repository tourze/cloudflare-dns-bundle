<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Abstract\BaseCloudflareService;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Exception\DnsRecordException;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'cloudflare_dns')]
class DnsRecordService extends BaseCloudflareService
{
    public function __construct(
        LoggerInterface $logger,
        private readonly DnsDomainRepository $domainRepository,
    ) {
        parent::__construct($logger);
    }

    public function extractDomain(string $name): ?DnsDomain
    {
        $tmp = explode('.', $name, 2);
        $domain = $this->domainRepository->findOneBy(['name' => $tmp[1]]);
        if (null === $domain) {
            $this->logger->error('发现一个未托管的域名', [
                'domain' => $name,
            ]);

            return null;
        }

        return $domain;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function removeRecord(DnsRecord $record): void
    {
        $domain = $record->getDomain();
        if (null === $domain) {
            throw new DnsRecordException('DNS记录关联的域名不能为空');
        }

        $client = $this->getCloudFlareClient($domain);
        $zoneId = $domain->getZoneId();
        $recordId = $record->getRecordId();

        if (null === $zoneId) {
            throw new DnsRecordException('域名Zone ID不能为空');
        }
        if (null === $recordId) {
            throw new DnsRecordException('DNS记录ID不能为空');
        }

        $response = $client->deleteDnsRecord($zoneId, $recordId);
        $this->handleResponse($this->toArraySafely($response), '删除CloudFlare域名记录失败', ['record' => $record]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws TransportExceptionInterface
     */
    public function createRecord(DnsDomain $domain, DnsRecord $record): array
    {
        $client = $this->getCloudFlareClient($domain);
        $zoneId = $domain->getZoneId();

        if (null === $zoneId) {
            throw new DnsRecordException('域名Zone ID不能为空');
        }

        $response = $client->createDnsRecord($zoneId, $record);

        return $this->handleResponse($this->toArraySafely($response), '创建CloudFlare域名记录失败', [
            'domain' => $domain,
            'record' => $record,
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws TransportExceptionInterface
     */
    public function updateRecord(DnsRecord $record): array
    {
        $domain = $record->getDomain();
        if (null === $domain) {
            throw new DnsRecordException('DNS记录关联的域名不能为空');
        }

        $client = $this->getCloudFlareClient($domain);
        $zoneId = $domain->getZoneId();

        if (null === $zoneId) {
            throw new DnsRecordException('域名Zone ID不能为空');
        }

        $response = $client->updateDnsRecord($zoneId, $record);

        return $this->handleResponse($this->toArraySafely($response), '更新CloudFlare域名记录失败', ['record' => $record]);
    }

    /**
     * @param array{
     *     deletes?: array<string>,
     *     patches?: array<array{id: string, record: DnsRecord}>,
     *     posts?: array<DnsRecord>,
     *     puts?: array<array{id: string, record: DnsRecord}>
     * } $operations
     *
     * @return array<string, mixed>
     *
     * @throws TransportExceptionInterface
     */
    public function batchRecords(DnsDomain $domain, array $operations): array
    {
        $client = $this->getCloudFlareClient($domain);
        $zoneId = $domain->getZoneId();

        if (null === $zoneId) {
            throw new DnsRecordException('域名Zone ID不能为空');
        }

        $response = $client->batchDnsRecords($zoneId, $operations);

        return $this->handleResponse($this->toArraySafely($response), '批量操作CloudFlare域名记录失败', [
            'domain' => $domain,
            'operations' => $operations,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function exportRecords(DnsDomain $domain): string
    {
        $client = $this->getCloudFlareClient($domain);
        $zoneId = $domain->getZoneId();

        if (null === $zoneId) {
            throw new DnsRecordException('域名Zone ID不能为空');
        }

        return $client->exportDnsRecords($zoneId)->getContent();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws TransportExceptionInterface
     */
    public function importRecords(DnsDomain $domain, string $bindConfig): array
    {
        $client = $this->getCloudFlareClient($domain);
        $zoneId = $domain->getZoneId();

        if (null === $zoneId) {
            throw new DnsRecordException('域名Zone ID不能为空');
        }

        $response = $client->importDnsRecords($zoneId, $bindConfig);

        return $this->handleResponse($this->toArraySafely($response), '导入CloudFlare域名记录失败', ['domain' => $domain]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws TransportExceptionInterface
     */
    public function scanRecords(DnsDomain $domain): array
    {
        $client = $this->getCloudFlareClient($domain);
        $zoneId = $domain->getZoneId();

        if (null === $zoneId) {
            throw new DnsRecordException('域名Zone ID不能为空');
        }

        $response = $client->scanDnsRecords($zoneId);

        return $this->handleResponse($this->toArraySafely($response), '扫描CloudFlare域名记录失败', ['domain' => $domain]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws TransportExceptionInterface
     */
    public function listRecords(DnsDomain $domain, array $params = []): array
    {
        $client = $this->getCloudFlareClient($domain);
        $zoneId = $domain->getZoneId();

        if (null === $zoneId) {
            throw new DnsRecordException('域名Zone ID不能为空');
        }

        $response = $client->listDnsRecords($zoneId, $params);

        return $this->handleResponse($this->toArraySafely($response), '获取CloudFlare域名记录列表失败', [
            'domain' => $domain,
            'params' => $params,
        ]);
    }
}
