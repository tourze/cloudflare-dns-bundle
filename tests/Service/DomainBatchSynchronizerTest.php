<?php

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\DnsDomainService;
use CloudflareDnsBundle\Service\DomainBatchSynchronizer;
use CloudflareDnsBundle\Service\DomainSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class DomainBatchSynchronizerTest extends TestCase
{
    private DomainBatchSynchronizer $service;
    private EntityManagerInterface&MockObject $entityManager;
    private DnsDomainRepository&MockObject $dnsDomainRepository;
    private DnsDomainService&MockObject $dnsDomainService;
    private DomainSynchronizer&MockObject $domainSynchronizer;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->dnsDomainRepository = $this->createMock(DnsDomainRepository::class);
        $this->dnsDomainService = $this->createMock(DnsDomainService::class);
        $this->domainSynchronizer = $this->createMock(DomainSynchronizer::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new DomainBatchSynchronizer(
            $this->entityManager,
            $this->dnsDomainRepository,
            $this->dnsDomainService,
            $this->domainSynchronizer,
            $this->logger
        );
    }

    public function test_filterDomains_with_specific_domain(): void
    {
        $domains = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'test.com', 'status' => 'active'],
                ['name' => 'demo.com', 'status' => 'pending'],
            ]
        ];

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->filterDomains($domains, 'example.com', $io);

        $this->assertCount(1, $result);
        $this->assertEquals('example.com', $result[0]['name']);
    }

    public function test_filterDomains_with_specific_domain_not_found(): void
    {
        $domains = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'test.com', 'status' => 'active'],
            ]
        ];

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->filterDomains($domains, 'notfound.com', $io);

        $this->assertEmpty($result);
        $this->assertStringContainsString('未找到指定的域名', $output->fetch());
    }

    public function test_filterDomains_without_specific_domain(): void
    {
        $domains = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'test.com', 'status' => 'active'],
                ['name' => 'demo.com', 'status' => 'pending'],
            ]
        ];

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->filterDomains($domains, null, $io);

        $this->assertCount(3, $result);
    }

    public function test_filterDomains_with_empty_domains(): void
    {
        $domains = ['result' => []];

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->filterDomains($domains, null, $io);

        $this->assertEmpty($result);
        $this->assertStringContainsString('没有找到任何域名', $output->fetch());
    }

    public function test_showSyncPreview(): void
    {
        $domainsToSync = [
            ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123'],
            ['name' => 'test.com', 'status' => 'pending', 'id' => 'zone456'],
        ];

        $iamKey = $this->createIamKey();
        
        $this->dnsDomainRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturn(null);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->showSyncPreview($domainsToSync, $iamKey, $output, $io);
        $this->assertCount(2, $result);
        
        $outputContent = $output->fetch();
        $this->assertStringContainsString('example.com', $outputContent);
        $this->assertStringContainsString('test.com', $outputContent);
    }

    public function test_confirmSync_with_force(): void
    {
        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->confirmSync(true, false, $io);

        $this->assertTrue($result);
    }

    public function test_confirmSync_with_dry_run(): void
    {
        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->confirmSync(false, true, $io);

        $this->assertFalse($result);
        $this->assertStringContainsString('干运行模式下未执行实际同步', $output->fetch());
    }

    public function test_executeBatchSync_success(): void
    {
        $domainsToSync = [
            ['name' => 'example.com', 'status' => 'active'],
            ['name' => 'test.com', 'status' => 'pending'],
        ];

        $iamKey = $this->createIamKey();
        $domain = $this->createDnsDomain();

        $this->domainSynchronizer->expects($this->exactly(2))
            ->method('createOrUpdateDomain')
            ->with($iamKey, $this->isType('array'), $this->isInstanceOf(SymfonyStyle::class))
            ->willReturn($domain);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->with($domain);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->executeBatchSync($domainsToSync, $iamKey, $io);

        $this->assertEquals([2, 0, 0], $result); // [syncCount, errorCount, skippedCount]
    }

    public function test_executeBatchSync_with_errors(): void
    {
        $domainsToSync = [
            ['name' => 'example.com', 'status' => 'active'],
            ['name' => 'invalid.com', 'status' => 'error'],
        ];

        $iamKey = $this->createIamKey();
        $domain = $this->createDnsDomain();

        $this->domainSynchronizer->expects($this->exactly(2))
            ->method('createOrUpdateDomain')
            ->willReturnCallback(function($key, $data) use ($domain) {
                if ($data['name'] === 'invalid.com') {
                    throw new \Exception('Sync error');
                }
                return $domain;
            });

        $this->logger->expects($this->once())
            ->method('error')
            ->with('同步域名失败', $this->isType('array'));

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->executeBatchSync($domainsToSync, $iamKey, $io);

        $this->assertEquals([1, 1, 0], $result); // [syncCount, errorCount, skippedCount]
    }

    public function test_createTempDomain(): void
    {
        $iamKey = $this->createIamKey();

        $result = $this->service->createTempDomain($iamKey);

        $this->assertInstanceOf(DnsDomain::class, $result);
        $this->assertEquals($iamKey, $result->getIamKey());
    }

    public function test_listAllDomains_success(): void
    {
        $iamKey = $this->createIamKey();
        $expectedResponse = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'test.com', 'status' => 'pending'],
            ]
        ];

        $this->dnsDomainService->expects($this->once())
            ->method('listDomains')
            ->willReturn($expectedResponse);

        $result = $this->service->listAllDomains($iamKey);

        $this->assertEquals($expectedResponse, $result);
    }

    public function test_filterDomains_with_malformed_domain_data(): void
    {
        $domains = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['invalid' => 'data'], // 缺少 name 字段
                ['name' => 'test.com', 'status' => 'pending'],
            ]
        ];

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->filterDomains($domains, null, $io);

        // 应该包含所有域名数据，包括无效的
        $this->assertCount(3, $result);
    }

    public function test_filterDomains_with_empty_result_array(): void
    {
        $domains = [];

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->filterDomains($domains, null, $io);

        $this->assertEmpty($result);
        $this->assertStringContainsString('没有找到任何域名', $output->fetch());
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