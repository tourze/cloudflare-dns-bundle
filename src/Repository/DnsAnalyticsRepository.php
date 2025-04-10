<?php

namespace CloudflareDnsBundle\Repository;

use CloudflareDnsBundle\Entity\DnsAnalytics;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DnsAnalytics|null find($id, $lockMode = null, $lockVersion = null)
 * @method DnsAnalytics|null findOneBy(array $criteria, array $orderBy = null)
 * @method DnsAnalytics[] findAll()
 * @method DnsAnalytics[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DnsAnalyticsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DnsAnalytics::class);
    }

    /**
     * 清理指定时间之前的数据
     */
    public function cleanupBefore(\DateTimeInterface $time): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.statTime < :time')
            ->setParameter('time', $time)
            ->getQuery()
            ->execute();
    }
}
