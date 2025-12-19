<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Repository;

use CloudflareDnsBundle\Entity\DnsAnalytics;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DnsAnalytics>
 */
#[AsRepository(entityClass: DnsAnalytics::class)]
final class DnsAnalyticsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DnsAnalytics::class);
    }

    public function save(DnsAnalytics $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DnsAnalytics $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 清理指定时间之前的数据
     */
    public function cleanupBefore(\DateTimeInterface $time): int
    {
        $result = $this->createQueryBuilder('a')
            ->delete()
            ->where('a.statTime < :time')
            ->setParameter('time', $time)
            ->getQuery()
            ->execute()
        ;

        return is_int($result) ? $result : 0;
    }
}
