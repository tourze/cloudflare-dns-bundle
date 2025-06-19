<?php

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

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
        if ($domain === null) {
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
        $client = $this->getCloudFlareClient($domain);
        $response = $client->deleteDnsRecord($domain->getZoneId(), $record->getRecordId());
        $this->handleResponse($response->toArray(), '删除CloudFlare域名记录失败', ['record' => $record]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function createRecord(DnsDomain $domain, DnsRecord $record): array
    {
        $client = $this->getCloudFlareClient($domain);
        $response = $client->createDnsRecord($domain->getZoneId(), $record);
        return $this->handleResponse($response->toArray(), '创建CloudFlare域名记录失败', [
            'domain' => $domain,
            'record' => $record,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function updateRecord(DnsRecord $record): array
    {
        $domain = $record->getDomain();
        $client = $this->getCloudFlareClient($domain);
        $response = $client->updateDnsRecord($domain->getZoneId(), $record);
        return $this->handleResponse($response->toArray(), '更新CloudFlare域名记录失败', ['record' => $record]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function batchRecords(DnsDomain $domain, array $operations): array
    {
        $client = $this->getCloudFlareClient($domain);
        $response = $client->batchDnsRecords($domain->getZoneId(), $operations);
        return $this->handleResponse($response->toArray(), '批量操作CloudFlare域名记录失败', [
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
        return $client->exportDnsRecords($domain->getZoneId())->getContent();
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function importRecords(DnsDomain $domain, string $bindConfig): array
    {
        $client = $this->getCloudFlareClient($domain);
        $response = $client->importDnsRecords($domain->getZoneId(), $bindConfig);
        return $this->handleResponse($response->toArray(), '导入CloudFlare域名记录失败', ['domain' => $domain]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function scanRecords(DnsDomain $domain): array
    {
        $client = $this->getCloudFlareClient($domain);
        $response = $client->scanDnsRecords($domain->getZoneId());
        return $this->handleResponse($response->toArray(), '扫描CloudFlare域名记录失败', ['domain' => $domain]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function listRecords(DnsDomain $domain, array $params = []): array
    {
        $client = $this->getCloudFlareClient($domain);
        $response = $client->listDnsRecords($domain->getZoneId(), $params);
        return $this->handleResponse($response->toArray(), '获取CloudFlare域名记录列表失败', [
            'domain' => $domain,
            'params' => $params,
        ]);
    }
}
