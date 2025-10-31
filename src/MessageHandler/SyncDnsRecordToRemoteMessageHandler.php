<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\MessageHandler;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Message\SyncDnsRecordToRemoteMessage;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * 处理DNS记录同步到远程的消息
 */
#[AsMessageHandler]
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'cloudflare_dns')]
readonly class SyncDnsRecordToRemoteMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DnsRecordRepository $recordRepository,
        private DnsRecordService $dnsService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncDnsRecordToRemoteMessage $message): void
    {
        $record = $this->findAndValidateRecord($message->getDnsRecordId());
        if (null === $record) {
            return;
        }

        if ($record->isSyncing()) {
            $this->logger->info('记录正在同步中,跳过处理', [
                'recordId' => $message->getDnsRecordId(),
            ]);

            return;
        }

        $this->performSync($record);
    }

    private function findAndValidateRecord(int $recordId): ?DnsRecord
    {
        $record = $this->recordRepository->find($recordId);

        if (null === $record) {
            $this->logger->warning('找不到要同步的DNS记录', [
                'recordId' => $recordId,
            ]);

            return null;
        }

        return $record;
    }

    private function performSync(DnsRecord $record): void
    {
        try {
            $record->setSyncing(true);
            $this->entityManager->flush();

            $domain = $record->getDomain();
            if (null === $domain) {
                return;
            }

            $this->ensureRecordId($record, $domain);
            $this->updateRemoteRecord($record);

            $record->setSynced(true);
        } catch (\Throwable $e) {
            $this->logger->error('同步DNS记录失败', [
                'record' => $record->getFullName(),
                'exception' => $e,
            ]);
        } finally {
            $record->setSyncing(false);
            $this->entityManager->flush();
        }
    }

    private function ensureRecordId(DnsRecord $record, DnsDomain $domain): void
    {
        if (null !== $record->getRecordId()) {
            return;
        }

        $this->searchExistingRecord($record, $domain);

        if (null === $record->getRecordId()) {
            $this->createNewRecord($record, $domain);
        }
    }

    private function searchExistingRecord(DnsRecord $record, DnsDomain $domain): void
    {
        $response = $this->dnsService->listRecords($domain, [
            'type' => $record->getType()->value,
            'name' => "{$record->getRecord()}.{$domain->getName()}",
        ]);

        $result = $response['result'] ?? [];
        if (is_array($result) && count($result) > 0 && is_array($result[0])) {
            $recordId = $result[0]['id'] ?? null;
            if (is_string($recordId)) {
                $record->setRecordId($recordId);
                $this->entityManager->flush();
            }
        }
    }

    private function createNewRecord(DnsRecord $record, DnsDomain $domain): void
    {
        $result = $this->dnsService->createRecord($domain, $record);
        $this->logger->info('DNS记录创建结果', [
            'result' => $result,
            'record' => $record->getFullName(),
        ]);

        $recordId = $result['id'] ?? null;
        if (is_string($recordId)) {
            $record->setRecordId($recordId);
        }
        $this->entityManager->flush();
    }

    private function updateRemoteRecord(DnsRecord $record): void
    {
        $result = $this->dnsService->updateRecord($record);
        $this->logger->info('DNS记录更新结果', [
            'record' => $record->getFullName(),
            'result' => $result,
        ]);
    }
}
