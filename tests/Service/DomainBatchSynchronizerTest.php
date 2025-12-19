<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Exception\CloudflareServiceException;
use CloudflareDnsBundle\Service\DnsDomainService;
use CloudflareDnsBundle\Service\DomainBatchSynchronizer;
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
#[CoversClass(DomainBatchSynchronizer::class)]
#[RunTestsInSeparateProcesses]
final class DomainBatchSynchronizerTest extends AbstractIntegrationTestCase
{
    private DomainBatchSynchronizer $service;

    protected function onSetUp(): void
    {
        $this->service = self::getService(DomainBatchSynchronizer::class);
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

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->filterDomains($domains, 'example.com', $io);

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

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->filterDomains($domains, 'notfound.com', $io);

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

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->filterDomains($domains, null, $io);

        $this->assertCount(3, $result);
    }

    public function testFilterDomainsWithEmptyDomains(): void
    {
        $domains = ['result' => []];

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $this->service->filterDomains($domains, null, $io);

        $this->assertEmpty($result);
        $this->assertStringContainsString('没有找到任何域名', $output->fetch());
    }

    public function testShowSyncPreview(): void
    {
        // 创建真实的 IamKey
        $iamKey = $this->createAndPersistIamKey();

        $domainsToSync = [
            ['name' => 'example.com', 'status' => 'active', 'id' => 'zone123'],
            ['name' => 'test.com', 'status' => 'pending', 'id' => 'zone456'],
        ];

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
        // 创建真实的 IamKey
        $iamKey = $this->createAndPersistIamKey();

        // 准备域名数据
        $domainsToSync = [
            ['name' => 'example-' . uniqid() . '.com', 'status' => 'active', 'id' => 'zone-' . uniqid()],
            ['name' => 'test-' . uniqid() . '.com', 'status' => 'pending', 'id' => 'zone-' . uniqid()],
        ];

        // Mock DomainSynchronizer 和 DnsDomainService
        $domainSynchronizer = $this->createMock(DomainSynchronizer::class);
        $dnsDomainService = $this->createMock(DnsDomainService::class);

        $domainSynchronizer->expects($this->exactly(2))
            ->method('createOrUpdateDomain')
            ->willReturnCallback(function ($key, $data) {
                $domain = new DnsDomain();
                $domain->setName($data['name']);
                $domain->setZoneId($data['id'] ?? null);
                $domain->setIamKey($key);
                $domain->setValid(true);

                return $domain;
            })
        ;

        // 使用反射创建带有 Mock 服务的 DomainBatchSynchronizer
        $service = $this->createDomainBatchSynchronizerWithMocks($domainSynchronizer, $dnsDomainService);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->executeBatchSync($domainsToSync, $iamKey, $io);

        $this->assertEquals([2, 0, 0], $result); // [syncCount, errorCount, skippedCount]
    }

    public function testExecuteBatchSyncWithErrors(): void
    {
        // 创建真实的 IamKey
        $iamKey = $this->createAndPersistIamKey();

        $domainsToSync = [
            ['name' => 'example.com', 'status' => 'active'],
            ['name' => 'invalid.com', 'status' => 'error'],
        ];

        // Mock DomainSynchronizer 和 DnsDomainService
        $domainSynchronizer = $this->createMock(DomainSynchronizer::class);
        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $domainSynchronizer->expects($this->exactly(2))
            ->method('createOrUpdateDomain')
            ->willReturnCallback(function ($key, $data) {
                if ('invalid.com' === $data['name']) {
                    throw new CloudflareServiceException('Sync error');
                }

                $domain = new DnsDomain();
                $domain->setName($data['name']);
                $domain->setIamKey($key);
                $domain->setValid(true);

                return $domain;
            })
        ;

        $logger->expects($this->once())
            ->method('error')
            ->with('同步域名失败', self::isArray())
        ;

        // 使用反射创建带有 Mock 服务的 DomainBatchSynchronizer
        $service = $this->createDomainBatchSynchronizerWithMocks($domainSynchronizer, $dnsDomainService, $logger);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->executeBatchSync($domainsToSync, $iamKey, $io);

        $this->assertEquals([1, 1, 0], $result); // [syncCount, errorCount, skippedCount]
    }

    public function testCreateTempDomain(): void
    {
        $iamKey = $this->createAndPersistIamKey();

        $result = $this->service->createTempDomain($iamKey);

        // 由于方法已有明确的返回类型声明，此处直接验证业务逻辑
        $this->assertEquals($iamKey, $result->getIamKey());
    }

    public function testListAllDomainsSuccess(): void
    {
        $iamKey = $this->createAndPersistIamKey();
        $expectedResponse = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'test.com', 'status' => 'pending'],
            ],
        ];

        // Mock DnsDomainService
        $dnsDomainService = $this->createMock(DnsDomainService::class);
        $dnsDomainService->expects($this->once())
            ->method('listDomains')
            ->willReturn($expectedResponse)
        ;

        $service = $this->createDomainBatchSynchronizerWithMocks(null, $dnsDomainService);

        $result = $service->listAllDomains($iamKey);

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

    /**
     * 创建并持久化 IamKey 实体
     */
    private function createAndPersistIamKey(): IamKey
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key ' . uniqid());
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        $this->persistAndFlush($iamKey);

        return $iamKey;
    }

    /**
     * 使用反射创建带有 Mock 服务的 DomainBatchSynchronizer
     */
    private function createDomainBatchSynchronizerWithMocks(
        ?DomainSynchronizer $domainSynchronizer = null,
        ?DnsDomainService $dnsDomainService = null,
        ?LoggerInterface $logger = null,
    ): DomainBatchSynchronizer {
        $reflection = new \ReflectionClass(DomainBatchSynchronizer::class);

        return $reflection->newInstance(
            self::getEntityManager(),
            self::getService('CloudflareDnsBundle\Repository\DnsDomainRepository'),
            $dnsDomainService ?? self::getService(DnsDomainService::class),
            $domainSynchronizer ?? self::getService(DomainSynchronizer::class),
            $logger ?? self::getService(LoggerInterface::class)
        );
    }
}
