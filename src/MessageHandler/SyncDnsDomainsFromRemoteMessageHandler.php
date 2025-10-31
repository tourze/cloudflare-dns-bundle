<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\MessageHandler;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Message\SyncDnsDomainsFromRemoteMessage;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use CloudflareDnsBundle\Service\RecordSyncProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * 处理从远程同步DNS记录到本地的消息
 */
#[AsMessageHandler]
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'cloudflare_dns')]
readonly class SyncDnsDomainsFromRemoteMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DnsDomainRepository $domainRepository,
        private DnsRecordRepository $recordRepository,
        private DnsRecordService $dnsService,
        private RecordSyncProcessor $recordSyncProcessor,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncDnsDomainsFromRemoteMessage $message): void
    {
        $domain = $this->findAndValidateDomain($message->getDomainId());
        if (null === $domain) {
            return;
        }

        try {
            $this->syncDomainRecords($domain);
        } catch (\Throwable $e) {
            $this->logger->error('同步DNS记录时发生错误', [
                'domain' => $domain->getName(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    private function findAndValidateDomain(int $domainId): ?DnsDomain
    {
        $domain = $this->domainRepository->find($domainId);

        if (null === $domain) {
            $this->logger->warning('找不到要同步的域名', ['domainId' => $domainId]);

            return null;
        }

        if (true !== $domain->isValid() || null === $domain->getZoneId() || '' === $domain->getZoneId()) {
            $this->logger->warning('域名无效或缺少Zone ID，无法同步', [
                'domain' => $domain->getName(),
                'valid' => $domain->isValid(),
                'zoneId' => $domain->getZoneId(),
            ]);

            return null;
        }

        return $domain;
    }

    private function syncDomainRecords(DnsDomain $domain): void
    {
        $this->logger->info('开始从Cloudflare获取域名解析记录', [
            'domain' => $domain->getName(),
            'zoneId' => $domain->getZoneId(),
        ]);

        $remoteRecords = $this->fetchAllRemoteRecords($domain);
        if ([] === $remoteRecords) {
            $this->logger->info('域名在Cloudflare上没有任何解析记录', ['domain' => $domain->getName()]);

            return;
        }

        $this->logger->info('从Cloudflare获取到解析记录', [
            'domain' => $domain->getName(),
            'count' => count($remoteRecords),
        ]);

        $this->processRecords($domain, $remoteRecords);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllRemoteRecords(DnsDomain $domain): array
    {
        $page = 1;
        $perPage = 50;
        $allRemoteRecords = [];

        while (true) {
            $response = $this->dnsService->listRecords($domain, [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if (!$this->isValidResponse($response, $domain)) {
                break;
            }

            $pageRecords = $this->extractPageRecords($response);
            if ([] === $pageRecords) {
                break;
            }

            $allRemoteRecords = array_merge($allRemoteRecords, $pageRecords);

            if (!$this->hasMorePages($response, $page)) {
                break;
            }

            ++$page;
        }

        return $allRemoteRecords;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function isValidResponse(array $response, DnsDomain $domain): bool
    {
        $success = $response['success'] ?? false;
        if (!is_bool($success) || !$success) {
            $this->logger->warning('获取DNS记录列表失败', [
                'domain' => $domain->getName(),
                'result' => $response,
            ]);

            return false;
        }

        return ($response['result'] ?? []) !== [];
    }

    /**
     * @param array<string, mixed> $response
     * @return array<int, array<string, mixed>>
     */
    private function extractPageRecords(array $response): array
    {
        $result = $response['result'] ?? [];
        if (!is_array($result)) {
            return [];
        }

        return $this->normalizeRecordKeys($result);
    }

    /**
     * @param array<int|string, mixed> $records
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRecordKeys(array $records): array
    {
        $typedResult = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $stringKeyedRecord = [];
            foreach ($record as $key => $value) {
                $stringKeyedRecord[is_string($key) ? $key : (string) $key] = $value;
            }
            $typedResult[] = $stringKeyedRecord;
        }

        return $typedResult;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function hasMorePages(array $response, int $currentPage): bool
    {
        $resultInfo = $response['result_info'] ?? [];
        if (!is_array($resultInfo)) {
            return false;
        }

        $totalPages = $resultInfo['total_pages'] ?? 0;

        return is_int($totalPages) && $totalPages > $currentPage;
    }

    /**
     * @param array<int, array<string, mixed>> $remoteRecords
     */
    private function processRecords(DnsDomain $domain, array $remoteRecords): void
    {
        $localRecordMaps = $this->buildLocalRecordMaps($domain);
        $counters = $this->initializeCounters();

        $counters = $this->processAllRemoteRecords($domain, $remoteRecords, $localRecordMaps, $counters);

        $this->entityManager->flush();
        $this->logSyncCompletion($domain, $counters);
    }

    /**
     * @return array<string, int>
     */
    private function initializeCounters(): array
    {
        return ['create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];
    }

    /**
     * @param array<int, array<string, mixed>> $remoteRecords
     * @param array{0: array<string, DnsRecord>, 1: array<string, DnsRecord>} $localRecordMaps
     * @param array<string, int> $counters
     * @return array<string, int>
     */
    private function processAllRemoteRecords(DnsDomain $domain, array $remoteRecords, array $localRecordMaps, array $counters): array
    {
        foreach ($remoteRecords as $remoteRecord) {
            $counters = $this->processRemoteRecordSafely($domain, $remoteRecord, $localRecordMaps, $counters);
        }

        return $counters;
    }

    /**
     * @param array<string, mixed> $remoteRecord
     * @param array{0: array<string, DnsRecord>, 1: array<string, DnsRecord>} $localRecordMaps
     * @param array<string, int> $counters
     * @return array<string, int>
     */
    private function processRemoteRecordSafely(DnsDomain $domain, array $remoteRecord, array $localRecordMaps, array $counters): array
    {
        try {
            return $this->processRemoteRecord($domain, $remoteRecord, $localRecordMaps, $counters);
        } catch (\Throwable $e) {
            $this->logger->error('处理DNS记录时出错', [
                'record' => $remoteRecord['name'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            ++$counters['error'];

            return $counters;
        }
    }

    /**
     * @param array<string, int> $counters
     */
    private function logSyncCompletion(DnsDomain $domain, array $counters): void
    {
        $this->logger->info('域名DNS记录同步完成', [
            'domain' => $domain->getName(),
            'created' => $counters['create'],
            'updated' => $counters['update'],
            'skipped' => $counters['skip'],
            'errors' => $counters['error'],
        ]);
    }

    /**
     * @return array{0: array<string, DnsRecord>, 1: array<string, DnsRecord>}
     */
    private function buildLocalRecordMaps(DnsDomain $domain): array
    {
        $localRecords = $this->recordRepository->findBy(['domain' => $domain]);
        $localRecordMap = [];
        $localRecordByCloudflareId = [];

        foreach ($localRecords as $record) {
            $key = $record->getType()->value . '_' . $record->getRecord();
            $localRecordMap[$key] = $record;

            if (null !== $record->getRecordId() && '' !== $record->getRecordId()) {
                $localRecordByCloudflareId[$record->getRecordId()] = $record;
            }
        }

        return [$localRecordMap, $localRecordByCloudflareId];
    }

    /**
     * @param array<string, mixed>                                            $remoteRecord
     * @param array{0: array<string, DnsRecord>, 1: array<string, DnsRecord>} $localRecordMaps
     * @param array<string, int>                                              $counters
     *
     * @return array<string, int>
     */
    private function processRemoteRecord(DnsDomain $domain, array $remoteRecord, array $localRecordMaps, array $counters): array
    {
        return $this->recordSyncProcessor->processRemoteRecord($domain, $remoteRecord, $localRecordMaps, $counters);
    }
}
