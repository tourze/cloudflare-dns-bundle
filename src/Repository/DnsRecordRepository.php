<?php

namespace CloudflareDnsBundle\Repository;

use CloudflareDnsBundle\Entity\DnsRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DnsRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method DnsRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method DnsRecord[] findAll()
 * @method DnsRecord[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DnsRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DnsRecord::class);
    }
}
