<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DomainStatus;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\DnsDomainService;
use CloudflareDnsBundle\Service\DomainSynchronizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DomainSynchronizer::class)]
#[RunTestsInSeparateProcesses]
final class DomainSynchronizerTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Tests don't require special setup
    }

    private function createDomainSynchronizer(
        ?DnsDomainRepository $domainRepository = null,
        ?DnsDomainService $dnsDomainService = null,
        ?LoggerInterface $logger = null,
    ): DomainSynchronizer {
        /*
         * 使用具体类 DnsDomainRepository 和 DnsDomainService 而不是接口的原因：
         * 1) 这些类提供了测试所需的具体方法实现
         * 2) 当前架构中这些类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */

        // 使用反射创建实例以避免直接实例化
        $reflection = new \ReflectionClass(DomainSynchronizer::class);

        return $reflection->newInstance(
            self::getEntityManager(),
            $domainRepository ?? $this->createMock(DnsDomainRepository::class),
            $dnsDomainService ?? $this->createMock(DnsDomainService::class),
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    public function testSyncDomainInfoSuccess(): void
    {
        $domain = $this->createDnsDomain();
        $listData = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'other.com', 'status' => 'pending'],
            ],
        ];
        $detailData = [
            'result' => [
                'status' => 'active',
                'expires_at' => '2025-12-31T00:00:00Z',
                'locked_until' => '2025-06-30T00:00:00Z',
                'auto_renew' => true,
            ],
        ];

        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('listDomains')
            ->with($domain)
            ->willReturn($listData)
        ;

        $dnsDomainService->expects($this->once())
            ->method('getDomain')
            ->with($domain, 'example.com')
            ->willReturn($detailData)
        ;

        $dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123')
        ;

        $service = $this->createDomainSynchronizer(dnsDomainService: $dnsDomainService);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->syncDomainInfo($domain, $io);

        $this->assertTrue($result);
        $this->assertEquals(DomainStatus::ACTIVE, $domain->getStatus());
        $this->assertTrue($domain->isAutoRenew());
    }

    public function testSyncDomainInfoDomainNotFound(): void
    {
        $domain = $this->createDnsDomain();
        $listData = [
            'result' => [
                ['name' => 'other.com', 'status' => 'active'],
            ],
        ];

        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('listDomains')
            ->with($domain)
            ->willReturn($listData)
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('未在API返回中找到指定域名', self::isArray())
        ;

        $service = $this->createDomainSynchronizer(
            dnsDomainService: $dnsDomainService,
            logger: $logger
        );

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->syncDomainInfo($domain, $io);

        $this->assertFalse($result);
    }

    public function testSyncDomainInfoException(): void
    {
        $domain = $this->createDnsDomain();

        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('listDomains')
            ->willThrowException(new \Exception('Network error'))
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('同步域名信息失败', self::isArray())
        ;

        $service = $this->createDomainSynchronizer(
            dnsDomainService: $dnsDomainService,
            logger: $logger
        );

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->syncDomainInfo($domain, $io);

        $this->assertFalse($result);
    }

    public function testUpdateDomainDetails(): void
    {
        $domain = $this->createDnsDomain();
        $detailData = [
            'status' => 'active',
            'expires_at' => '2025-12-31T00:00:00Z',
            'locked_until' => '2025-06-30T00:00:00Z',
            'auto_renew' => true,
        ];

        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123')
        ;

        $service = $this->createDomainSynchronizer(dnsDomainService: $dnsDomainService);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $service->updateDomainDetails($domain, $detailData, $io);

        $this->assertEquals(DomainStatus::ACTIVE, $domain->getStatus());
        $this->assertTrue($domain->isAutoRenew());
        $this->assertInstanceOf(\DateTimeInterface::class, $domain->getExpiresTime());
        $this->assertInstanceOf(\DateTimeInterface::class, $domain->getLockedUntilTime());
    }

    public function testUpdateDomainDetailsWithInvalidDate(): void
    {
        $domain = $this->createDnsDomain();
        $detailData = [
            'status' => 'active',
            'expires_at' => 'invalid-date',
            'auto_renew' => false,
        ];

        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123')
        ;

        $service = $this->createDomainSynchronizer(dnsDomainService: $dnsDomainService);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $this->expectException(\Exception::class);

        $service->updateDomainDetails($domain, $detailData, $io);
    }

    public function testCreateOrUpdateDomainCreateNew(): void
    {
        $iamKey = $this->createIamKey();
        $domainData = [
            'name' => 'newdomain.com',
            'status' => 'active',
            'id' => 'zone123456',
        ];

        $domainRepository = $this->createMock(DnsDomainRepository::class);
        $domainRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'newdomain.com', 'iamKey' => $iamKey])
            ->willReturn(null)
        ;

        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123456')
        ;

        $service = $this->createDomainSynchronizer(
            domainRepository: $domainRepository,
            dnsDomainService: $dnsDomainService
        );

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->createOrUpdateDomain($iamKey, $domainData, $io);

        // 由于方法已有明确的返回类型声明，此处直接验证业务逻辑
        $this->assertEquals('newdomain.com', $result->getName());
        $this->assertEquals($iamKey, $result->getIamKey());
        $this->assertTrue($result->isValid());
    }

    public function testCreateOrUpdateDomainUpdateExisting(): void
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

        $domainRepository = $this->createMock(DnsDomainRepository::class);
        $domainRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'example.com', 'iamKey' => $iamKey])
            ->willReturn($existingDomain)
        ;

        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('new-zone-id')
        ;

        $service = $this->createDomainSynchronizer(
            domainRepository: $domainRepository,
            dnsDomainService: $dnsDomainService
        );

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->createOrUpdateDomain($iamKey, $domainData, $io);

        $this->assertSame($existingDomain, $result);
        $this->assertTrue($result->isValid()); // 应该被设置为有效
        $this->assertEquals(DomainStatus::PENDING, $result->getStatus());
    }

    public function testFindDomainsWithoutSpecificDomain(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain2 = $this->createDnsDomain();
        $domain2->setName('test.com');

        $domainRepository = $this->createMock(DnsDomainRepository::class);
        $domainRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$domain1, $domain2])
        ;

        $service = $this->createDomainSynchronizer(domainRepository: $domainRepository);

        $result = $service->findDomains();

        $this->assertCount(2, $result);
        $this->assertContains($domain1, $result);
        $this->assertContains($domain2, $result);
    }

    public function testFindDomainsWithSpecificDomain(): void
    {
        $domain = $this->createDnsDomain();
        $domain->setName('example.com');

        $domainRepository = $this->createMock(DnsDomainRepository::class);
        $domainRepository->expects($this->once())
            ->method('findBy')
            ->with(['name' => 'example.com'])
            ->willReturn([$domain])
        ;

        $service = $this->createDomainSynchronizer(domainRepository: $domainRepository);

        $result = $service->findDomains('example.com');

        $this->assertCount(1, $result);
        $this->assertContains($domain, $result);
    }

    public function testUpdateDomainDetailsWithMissingStatus(): void
    {
        $domain = $this->createDnsDomain();
        $detailData = [
            'expires_at' => '2025-12-31T00:00:00Z',
            'auto_renew' => true,
        ];

        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123')
        ;

        $service = $this->createDomainSynchronizer(dnsDomainService: $dnsDomainService);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $service->updateDomainDetails($domain, $detailData, $io);

        $this->assertNull($domain->getStatus()); // 状态应保持为null
        $this->assertTrue($domain->isAutoRenew());
    }

    public function testUpdateDomainDetailsWithEmptyData(): void
    {
        $domain = $this->createDnsDomain();
        $detailData = [];

        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn(null)
        ;

        $service = $this->createDomainSynchronizer(dnsDomainService: $dnsDomainService);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $service->updateDomainDetails($domain, $detailData, $io);

        // 应该不会抛出异常，域名对象保持原状
        $this->assertEquals('example.com', $domain->getName());
    }

    public function testCreateOrUpdateDomainWithMalformedData(): void
    {
        $iamKey = $this->createIamKey();
        $domainData = [
            // 缺少 name 字段
            'status' => 'active',
            'id' => 'zone123456',
        ];

        $domainRepository = $this->createMock(DnsDomainRepository::class);
        $domainRepository->expects($this->never())
            ->method('findOneBy')
        ;

        $service = $this->createDomainSynchronizer(domainRepository: $domainRepository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain data must contain a "name" field');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $service->createOrUpdateDomain($iamKey, $domainData, $io);
    }

    public function testSyncDomainInfoWithoutIo(): void
    {
        $domain = $this->createDnsDomain();
        $listData = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
            ],
        ];
        $detailData = [
            'result' => [
                'status' => 'active',
                'auto_renew' => true,
            ],
        ];

        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('listDomains')
            ->willReturn($listData)
        ;

        $dnsDomainService->expects($this->once())
            ->method('getDomain')
            ->willReturn($detailData)
        ;

        $dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123')
        ;

        $service = $this->createDomainSynchronizer(dnsDomainService: $dnsDomainService);

        // EntityManager persist 和 flush 操作由集成测试框架自动处理

        $result = $service->syncDomainInfo($domain);

        $this->assertTrue($result);
    }

    public function testUpdateDomainDetailsWithCreateTime(): void
    {
        $domain = $this->createDnsDomain();
        $detailData = [
            'created_at' => '2024-01-01T00:00:00Z',
            'status' => 'active',
        ];

        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('syncZoneId')
            ->willReturn('zone123')
        ;

        $service = $this->createDomainSynchronizer(dnsDomainService: $dnsDomainService);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $service->updateDomainDetails($domain, $detailData, $io);

        $this->assertInstanceOf(\DateTimeInterface::class, $domain->getCreateTime());
    }

    private function createIamKey(): IamKey
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key ' . uniqid());
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        return $iamKey;
    }

    private function createDnsDomain(): DnsDomain
    {
        $iamKey = $this->createIamKey();
        // 持久化 IamKey 以避免级联持久化错误
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->flush();

        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');
        $domain->setIamKey($iamKey);
        $domain->setValid(true);

        return $domain;
    }
}
