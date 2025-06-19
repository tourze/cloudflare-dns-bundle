<?php

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DomainStatus;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\DnsDomainService;
use CloudflareDnsBundle\Service\DomainSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class DomainSynchronizerTest extends TestCase
{
    private DomainSynchronizer $service;
    private EntityManagerInterface&MockObject $entityManager;
    private DnsDomainRepository&MockObject $domainRepository;
    private DnsDomainService&MockObject $dnsDomainService;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->domainRepository = $this->createMock(DnsDomainRepository::class);
        $this->dnsDomainService = $this->createMock(DnsDomainService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new DomainSynchronizer(
            $this->entityManager,
            $this->domainRepository,
            $this->dnsDomainService,
            $this->logger
        );
    }

    public function test_syncDomainInfo_success(): void
    {
        $domain = $this->createDnsDomain();
        $listData = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'other.com', 'status' => 'pending'],
            ]
        ];
        $detailData = [
            'result' => [
                'status' => 'active',
                'expires_at' => '2025-12-31T00:00:00Z',
                'locked_until' => '2025-06-30T00:00:00Z',
                'auto_renew' => true,
            ]
        ];

        $this->dnsDomainService->expects($this->once())
            ->method('listDomains')
            ->with($domain)
            ->willReturn($listData);

        $this->dnsDomainService->expects($this->once())
            ->method('getDomain')
            ->with($domain, 'example.com')
            ->willReturn($detailData);

        $this->dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($domain);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->syncDomainInfo($domain, $io);

        $this->assertTrue($result);
        $this->assertEquals(DomainStatus::ACTIVE, $domain->getStatus());
        $this->assertTrue($domain->isAutoRenew());
    }

    public function test_syncDomainInfo_domain_not_found(): void
    {
        $domain = $this->createDnsDomain();
        $listData = [
            'result' => [
                ['name' => 'other.com', 'status' => 'active'],
            ]
        ];

        $this->dnsDomainService->expects($this->once())
            ->method('listDomains')
            ->with($domain)
            ->willReturn($listData);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('未在API返回中找到指定域名', $this->isType('array'));

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->syncDomainInfo($domain, $io);

        $this->assertFalse($result);
    }

    public function test_syncDomainInfo_exception(): void
    {
        $domain = $this->createDnsDomain();

        $this->dnsDomainService->expects($this->once())
            ->method('listDomains')
            ->willThrowException(new \Exception('Network error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('同步域名信息失败', $this->isType('array'));

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->syncDomainInfo($domain, $io);

        $this->assertFalse($result);
    }

    public function test_updateDomainDetails(): void
    {
        $domain = $this->createDnsDomain();
        $detailData = [
            'status' => 'active',
            'expires_at' => '2025-12-31T00:00:00Z',
            'locked_until' => '2025-06-30T00:00:00Z',
            'auto_renew' => true,
        ];

        $this->dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $this->service->updateDomainDetails($domain, $detailData, $io);

        $this->assertEquals(DomainStatus::ACTIVE, $domain->getStatus());
        $this->assertTrue($domain->isAutoRenew());
        $this->assertInstanceOf(\DateTimeInterface::class, $domain->getExpiresTime());
        $this->assertInstanceOf(\DateTimeInterface::class, $domain->getLockedUntilTime());
    }

    public function test_updateDomainDetails_with_invalid_date(): void
    {
        $domain = $this->createDnsDomain();
        $detailData = [
            'status' => 'active',
            'expires_at' => 'invalid-date',
            'auto_renew' => false,
        ];

        $this->dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $this->expectException(\Exception::class);

        $this->service->updateDomainDetails($domain, $detailData, $io);
    }

    public function test_createOrUpdateDomain_create_new(): void
    {
        $iamKey = $this->createIamKey();
        $domainData = [
            'name' => 'newdomain.com',
            'status' => 'active',
            'id' => 'zone123456',
        ];

        $this->domainRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'newdomain.com', 'iamKey' => $iamKey])
            ->willReturn(null);

        $this->dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123456');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->createOrUpdateDomain($iamKey, $domainData, $io);

        $this->assertInstanceOf(DnsDomain::class, $result);
        $this->assertEquals('newdomain.com', $result->getName());
        $this->assertEquals($iamKey, $result->getIamKey());
        $this->assertTrue($result->isValid());
    }

    public function test_createOrUpdateDomain_update_existing(): void
    {
        $iamKey = $this->createIamKey();
        $existingDomain = $this->createDnsDomain();
        $existingDomain->setIamKey($iamKey);
        $existingDomain->setValid(false);
        
        $domainData = [
            'name' => 'example.com',
            'status' => 'pending',
            'id' => 'new-zone-id',
        ];

        $this->domainRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'example.com', 'iamKey' => $iamKey])
            ->willReturn($existingDomain);

        $this->dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('new-zone-id');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->createOrUpdateDomain($iamKey, $domainData, $io);

        $this->assertSame($existingDomain, $result);
        $this->assertTrue($result->isValid()); // 应该被设置为有效
        $this->assertEquals(DomainStatus::PENDING, $result->getStatus());
    }

    public function test_findDomains_without_specific_domain(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain2 = $this->createDnsDomain();
        $domain2->setName('test.com');

        $this->domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain1, $domain2]);

        $result = $this->service->findDomains();

        $this->assertCount(2, $result);
        $this->assertContains($domain1, $result);
        $this->assertContains($domain2, $result);
    }

    public function test_findDomains_with_specific_domain(): void
    {
        $domain = $this->createDnsDomain();
        $domain->setName('example.com');

        $this->domainRepository->expects($this->once())
            ->method('findBy')
            ->with(['name' => 'example.com'])
            ->willReturn([$domain]);

        $result = $this->service->findDomains('example.com');

        $this->assertCount(1, $result);
        $this->assertContains($domain, $result);
    }

    public function test_updateDomainDetails_with_missing_status(): void
    {
        $domain = $this->createDnsDomain();
        $detailData = [
            'expires_at' => '2025-12-31T00:00:00Z',
            'auto_renew' => true,
        ];

        $this->dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $this->service->updateDomainDetails($domain, $detailData, $io);

        $this->assertNull($domain->getStatus()); // 状态应保持为null
        $this->assertTrue($domain->isAutoRenew());
    }

    public function test_updateDomainDetails_with_empty_data(): void
    {
        $domain = $this->createDnsDomain();
        $detailData = [];

        $this->dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn(null);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $this->service->updateDomainDetails($domain, $detailData, $io);

        // 应该不会抛出异常，域名对象保持原状
        $this->assertEquals('example.com', $domain->getName());
    }

    public function test_createOrUpdateDomain_with_malformed_data(): void
    {
        $iamKey = $this->createIamKey();
        $domainData = [
            // 缺少 name 字段
            'status' => 'active',
            'id' => 'zone123456',
        ];

        $this->domainRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->expectException(\TypeError::class);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $this->service->createOrUpdateDomain($iamKey, $domainData, $io);
    }

    public function test_syncDomainInfo_without_io(): void
    {
        $domain = $this->createDnsDomain();
        $listData = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
            ]
        ];
        $detailData = [
            'result' => [
                'status' => 'active',
                'auto_renew' => true,
            ]
        ];

        $this->dnsDomainService->expects($this->once())
            ->method('listDomains')
            ->willReturn($listData);

        $this->dnsDomainService->expects($this->once())
            ->method('getDomain')
            ->willReturn($detailData);

        $this->dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123');

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->syncDomainInfo($domain);

        $this->assertTrue($result);
    }

    public function test_updateDomainDetails_with_create_time(): void
    {
        $domain = $this->createDnsDomain();
        $detailData = [
            'created_at' => '2024-01-01T00:00:00Z',
            'status' => 'active',
        ];

        $this->dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $this->service->updateDomainDetails($domain, $detailData, $io);

        $this->assertInstanceOf(\DateTimeInterface::class, $domain->getCreateTime());
    }

    private function createIamKey(): IamKey
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key');
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        return $iamKey;
    }

    private function createDnsDomain(): DnsDomain
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');
        $domain->setValid(true);

        return $domain;
    }
} 