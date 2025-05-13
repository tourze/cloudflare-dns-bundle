<?php

namespace CloudflareDnsBundle\EventListener;

use CloudflareDnsBundle\Entity\DnsRecord;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * 监听DNS记录的创建和更新事件，自动设置同步状态
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: DnsRecord::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: DnsRecord::class)]
class DnsRecordSyncListener
{
    /**
     * 创建记录前
     */
    public function prePersist(DnsRecord $entity): void
    {
        // 对于新创建的记录，设置为未同步状态
        if ($entity->getRecordId() === null) {
            $entity->setSynced(false);
        }
    }

    /**
     * 更新记录前
     */
    public function preUpdate(DnsRecord $entity, PreUpdateEventArgs $args): void
    {
        // 如果关键字段有变更，则设置为未同步状态
        $changeSet = $args->getEntityChangeSet();
        $syncFields = ['type', 'record', 'content', 'ttl', 'proxy'];

        foreach ($syncFields as $field) {
            if (isset($changeSet[$field])) {
                $entity->setSynced(false);
                break;
            }
        }
    }
}
