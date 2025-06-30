<?php

namespace CloudflareDnsBundle\Tests\Repository;

use CloudflareDnsBundle\Repository\DnsAnalyticsRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class DnsAnalyticsRepositoryTest extends TestCase
{
    private DnsAnalyticsRepository $repository;
    private ManagerRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->repository = new DnsAnalyticsRepository($this->registry);
    }

    public function test_constructor_callsParentWithCorrectParameters(): void
    {
        $this->assertInstanceOf(DnsAnalyticsRepository::class, $this->repository);
    }

    public function test_inheritsFromServiceEntityRepository(): void
    {
        $this->assertInstanceOf(ServiceEntityRepository::class, $this->repository);
    }
}
