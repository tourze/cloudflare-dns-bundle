<?php

namespace CloudflareDnsBundle\Repository;

use CloudflareDnsBundle\Entity\IamKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method IamKey|null find($id, $lockMode = null, $lockVersion = null)
 * @method IamKey|null findOneBy(array $criteria, array $orderBy = null)
 * @method IamKey[] findAll()
 * @method IamKey[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IamKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IamKey::class);
    }
}
