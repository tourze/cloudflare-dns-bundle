<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Exception\TestServiceException;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\DnsDomainService;
use CloudflareDnsBundle\Service\DomainBatchSynchronizer;
use CloudflareDnsBundle\Service\DomainSynchronizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DomainBatchSynchronizer::class)]
#[RunTestsInSeparateProcesses]
final class DomainBatchSynchronizerTest extends AbstractIntegrationTestCase
{
    private DnsDomainRepository&MockObject $dnsDomainRepository;

    private DnsDomainService&MockObject $dnsDomainService;

    private DomainSynchronizer&MockObject $domainSynchronizer;

    private LoggerInterface&MockObject $logger;

    private DomainBatchSynchronizer $service;

    protected function onSetUp(): void
    {
        $this->dnsDomainRepository = $this->createMock(DnsDomainRepository::class);
        $this->dnsDomainService = $this->createMock(DnsDomainService::class);
        $this->domainSynchronizer = $this->createMock(DomainSynchronizer::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = $this->createDomainBatchSynchronizer(
            $this->dnsDomainRepository,
            $this->dnsDomainService,
            $this->domainSynchronizer,
            $this->logger
        );
    }

    private function createDomainBatchSynchronizer(
        ?DnsDomainRepository $dnsDomainRepository = null,
        ?DnsDomainService $dnsDomainService = null,
        ?DomainSynchronizer $domainSynchronizer = null,
        ?LoggerInterface $logger = null,
    ): DomainBatchSynchronizer {
        /*
         * 使用具体类 DnsDomainRepository、DnsDomainService 和 DomainSynchronizer 的原因：
         * 1) 这些类提供了测试所需的具体方法实现
         * 2) 当前架构中这些类作为具体实现类，测试需要 mock 其具体行为
         * 3) 使用具体类能更好地验证方法调用和参数传递
         */

        // 使用反射创建实例以避免直接实例化
        $reflection = new \ReflectionClass(DomainBatchSynchronizer::class);

        return $reflection->newInstance(
            self::getEntityManager(),
            $dnsDomainRepository ?? $this->createMock(DnsDomainRepository::class),
            $dnsDomainService ?? $this->createMock(DnsDomainService::class),
            $domainSynchronizer ?? $this->createMock(DomainSynchronizer::class),
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    public function testFilterDomainsWithSpecificDomain(): void
    {
        $domains = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'test.com', 'status' => 'active'],
                ['name' => 'demo.com', 'status' => 'pending'],
            ],
        ];

        $service = $this->createDomainBatchSynchronizer();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->filterDomains($domains, 'example.com', $io);

        $this->assertCount(1, $result);
        $this->assertEquals('example.com', $result[0]['name']);
    }

    public function testFilterDomainsWithSpecificDomainNotFound(): void
    {
        $domains = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'test.com', 'status' => 'active'],
            ],
        ];

        $service = $this->createDomainBatchSynchronizer();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->filterDomains($domains, 'notfound.com', $io);

        $this->assertEmpty($result);
        $this->assertStringContainsString('未找到指定的域名', $output->fetch());
    }

    public function testFilterDomainsWithoutSpecificDomain(): void
    {
        $domains = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'test.com', 'status' => 'active'],
                ['name' => 'demo.com', 'status' => 'pending'],
            ],
        ];

        $service = $this->createDomainBatchSynchronizer();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->filterDomains($domains, null, $io);

        $this->assertCount(3, $result);
    }

    public function testFilterDomainsWithEmptyDomains(): void
    {
        $domains = ['result' => []];

        $service = $this->createDomainBatchSynchronizer();

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->filterDomains($domains, null, $io);

        $this->assertEmpty($result);
        $this->assertStringContainsString('没有找到任何域名', $output->fetch());
    }

    public function testShowSyncPreview(): void
    {
        $domainsToSync = [
            ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123'],
            ['name' => 'test.com', 'status' => 'pending', 'id' => 'zone456'],
        ];

        $iamKey = $this->createIamKey();

        $this->dnsDomainRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturn(null)
        ;

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->showSyncPreview($domainsToSync, $iamKey, $output, $io);
        $this->assertCount(2, $result);

        $outputContent = $output->fetch();
        $this->assertStringContainsString('example.com', $outputContent);
        $this->assertStringContainsString('test.com', $outputContent);
    }

    public function testConfirmSyncWithForce(): void
    {
        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->confirmSync(true, false, $io);

        $this->assertTrue($result);
    }

    public function testConfirmSyncWithDryRun(): void
    {
        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->confirmSync(false, true, $io);

        $this->assertFalse($result);
        $this->assertStringContainsString('干运行模式下未执行实际同步', $output->fetch());
    }

    public function testExecuteBatchSyncSuccess(): void
    {
        $domainsToSync = [
            ['name' => 'example.com', 'status' => 'active'],
            ['name' => 'test.com', 'status' => 'pending'],
        ];

        $iamKey = $this->createIamKey();
        $domain = $this->createDnsDomain();

        $this->domainSynchronizer->expects($this->exactly(2))
            ->method('createOrUpdateDomain')
            ->with($iamKey, self::isArray(), self::isInstanceOf(SymfonyStyle::class))
            ->willReturn($domain)
        ;

        // EntityManager persist 和 flush 操作由集成测试框架自动处理

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->executeBatchSync($domainsToSync, $iamKey, $io);

        $this->assertEquals([2, 0, 0], $result); // [syncCount, errorCount, skippedCount]
    }

    public function testExecuteBatchSyncWithErrors(): void
    {
        $domainsToSync = [
            ['name' => 'example.com', 'status' => 'active'],
            ['name' => 'invalid.com', 'status' => 'error'],
        ];

        $iamKey = $this->createIamKey();
        $domain = $this->createDnsDomain();

        $this->domainSynchronizer->expects($this->exactly(2))
            ->method('createOrUpdateDomain')
            ->willReturnCallback(function ($key, $data) use ($domain) {
                if ('invalid.com' === $data['name']) {
                    throw new TestServiceException('Sync error');
                }

                return $domain;
            })
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('同步域名失败', self::isArray())
        ;

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->executeBatchSync($domainsToSync, $iamKey, $io);

        $this->assertEquals([1, 1, 0], $result); // [syncCount, errorCount, skippedCount]
    }

    public function testCreateTempDomain(): void
    {
        $iamKey = $this->createIamKey();

        $result = $this->service->createTempDomain($iamKey);

        // 由于方法已有明确的返回类型声明，此处直接验证业务逻辑
        $this->assertEquals($iamKey, $result->getIamKey());
    }

    public function testListAllDomainsSuccess(): void
    {
        $iamKey = $this->createIamKey();
        $expectedResponse = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'test.com', 'status' => 'pending'],
            ],
        ];

        $this->dnsDomainService->expects($this->once())
            ->method('listDomains')
            ->willReturn($expectedResponse)
        ;

        $result = $this->service->listAllDomains($iamKey);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testFilterDomainsWithMalformedDomainData(): void
    {
        $domains = [
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['invalid' => 'data'], // 缺少 name 字段
                ['name' => 'test.com', 'status' => 'pending'],
            ],
        ];

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->filterDomains($domains, null, $io);

        // 应该包含所有域名数据，包括无效的
        $this->assertCount(3, $result);
    }

    public function testFilterDomainsWithEmptyResultArray(): void
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
