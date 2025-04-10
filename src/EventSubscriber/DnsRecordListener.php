<?php

namespace CloudflareDnsBundle\EventSubscriber;

use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * 创建和删除需要马上执行，编辑的话就靠异步同步算了？
 */
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: DnsRecord::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: DnsRecord::class)]
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: DnsRecord::class)]
final class DnsRecordListener
{
    public function __construct(
        private readonly DnsRecordService $dnsService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postPersist(DnsRecord $record, PostPersistEventArgs $args): void
    {
        if ($record->isSyncing()) {
            return;
        }

        try {
            $record->setSyncing(true);
            $result = $this->dnsService->createRecord($record->getDomain(), $record);
            $record->setRecordId($result['id']);
            $args->getObjectManager()->flush();
        } catch (\Throwable $e) {
            $this->logger->error('同步DNS记录到CloudFlare失败', [
                'record' => $record,
                'exception' => $e,
            ]);
            throw $e;
        } finally {
            $record->setSyncing(false);
        }
    }

    public function postUpdate(DnsRecord $record, PostUpdateEventArgs $args): void
    {
        if ($record->isSyncing()) {
            return;
        }

        try {
            $record->setSyncing(true);
            if (!$record->getRecordId()) {
                // 如果没有记录ID,尝试搜索或创建
                $response = $this->dnsService->listRecords($record->getDomain(), [
                    'type' => $record->getType()->value,
                    'name' => "{$record->getRecord()}.{$record->getDomain()->getName()}",
                ]);

                if (!empty($response['result'])) {
                    $record->setRecordId($response['result'][0]['id']);
                    $args->getObjectManager()->flush();
                } else {
                    $result = $this->dnsService->createRecord($record->getDomain(), $record);
                    $record->setRecordId($result['id']);
                    $args->getObjectManager()->flush();
                    return;
                }
            }

            $this->dnsService->updateRecord($record);
        } catch (\Throwable $e) {
            $this->logger->error('更新DNS记录到CloudFlare失败', [
                'record' => $record,
                'exception' => $e,
            ]);
            throw $e;
        } finally {
            $record->setSyncing(false);
        }
    }

    public function postRemove(DnsRecord $record, PostRemoveEventArgs $args): void
    {
        if ($record->isSyncing() || !$record->getRecordId()) {
            return;
        }

        try {
            $record->setSyncing(true);
            $this->dnsService->removeRecord($record);
        } catch (\Throwable $e) {
            $this->logger->error('删除CloudFlare DNS记录失败', [
                'record' => $record,
                'exception' => $e,
            ]);
            throw $e;
        } finally {
            $record->setSyncing(false);
        }
    }
}
