<?php

namespace CloudflareDnsBundle\MessageHandler;

use CloudflareDnsBundle\Message\SyncDnsRecordToRemoteMessage;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * 处理DNS记录同步到远程的消息
 */
#[AsMessageHandler]
class SyncDnsRecordToRemoteMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DnsRecordRepository $recordRepository,
        private readonly DnsRecordService $dnsService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncDnsRecordToRemoteMessage $message): void
    {
        $recordId = $message->getDnsRecordId();
        $record = $this->recordRepository->find($recordId);

        if (!$record) {
            $this->logger->warning('找不到要同步的DNS记录', [
                'recordId' => $recordId,
            ]);
            return;
        }

        if ($record->isSyncing()) {
            $this->logger->info('记录正在同步中,跳过处理', [
                'recordId' => $recordId,
            ]);
            return;
        }

        try {
            $record->setSyncing(true);
            $this->entityManager->flush();

            $domain = $record->getDomain();

            // 如果没记录ID，那么我们试试搜索
            if (!$record->getRecordId()) {
                $response = $this->dnsService->listRecords($domain, [
                    'type' => $record->getType()->value,
                    'name' => "{$record->getRecord()}.{$domain->getName()}",
                ]);

                if (!empty($response['result'])) {
                    $record->setRecordId($response['result'][0]['id']);
                    $this->entityManager->flush();
                }
            }

            // 还是没有，我们尝试创建
            if (!$record->getRecordId()) {
                $result = $this->dnsService->createRecord($domain, $record);
                $this->logger->info('DNS记录创建结果', [
                    'result' => $result,
                    'record' => $record->getFullName(),
                ]);

                $record->setRecordId($result['id']);
                $this->entityManager->flush();
            }

            // 更新最终本地结果到远程
            $result = $this->dnsService->updateRecord($record);
            $this->logger->info('DNS记录更新结果', [
                'record' => $record->getFullName(),
                'result' => $result,
            ]);

            // 更新同步状态为已同步
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
}
