<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DomainStatus;
use CloudflareDnsBundle\Exception\CloudflareServiceException;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Client\CloudflareHttpClient;
use CloudflareDnsBundle\Service\DnsDomainService;
use CloudflareDnsBundle\Service\DomainSynchronizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
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

    public function testSyncDomainInfoSuccess(): void
    {
        $domain = $this->createDnsDomain();
        $this->persistAndFlush($domain->getIamKey());
        $this->persistAndFlush($domain);

        $listData = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
                ['name' => 'other.com', 'status' => 'pending'],
            ],
        ];
        $detailData = [
            'success' => true,
            'result' => [
                'status' => 'active',
                'expires_at' => '2025-12-31T00:00:00Z',
                'locked_until' => '2025-06-30T00:00:00Z',
                'auto_renew' => true,
                'id' => 'zone123',
            ],
        ];

        $mockHttpClient = $this->createSequentialMockHttpClient([$listData, $detailData]);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->syncDomainInfo($domain, $io);

        $this->assertTrue($result);
        $this->assertEquals(DomainStatus::ACTIVE, $domain->getStatus());
        $this->assertTrue($domain->isAutoRenew());
        $this->assertEquals('zone123', $domain->getZoneId());
    }

    public function testSyncDomainInfoDomainNotFound(): void
    {
        $domain = $this->createDnsDomain();
        $this->persistAndFlush($domain->getIamKey());
        $this->persistAndFlush($domain);

        $listData = [
            'success' => true,
            'result' => [
                ['name' => 'other.com', 'status' => 'active'],
            ],
        ];

        $mockHttpClient = $this->createMockHttpClient($listData);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->syncDomainInfo($domain, $io);

        $this->assertFalse($result);
    }

    public function testSyncDomainInfoException(): void
    {
        $domain = $this->createDnsDomain();
        $this->persistAndFlush($domain->getIamKey());
        $this->persistAndFlush($domain);

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')
            ->willThrowException(new \Exception('Network error'))
        ;

        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->syncDomainInfo($domain, $io);

        $this->assertFalse($result);
    }

    public function testUpdateDomainDetails(): void
    {
        $domain = $this->createDnsDomain();
        $this->persistAndFlush($domain->getIamKey());
        $this->persistAndFlush($domain);

        $detailData = [
            'status' => 'active',
            'expires_at' => '2025-12-31T00:00:00Z',
            'locked_until' => '2025-06-30T00:00:00Z',
            'auto_renew' => true,
            'id' => 'zone123',
        ];

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $service->updateDomainDetails($domain, $detailData, $io);

        $this->assertEquals(DomainStatus::ACTIVE, $domain->getStatus());
        $this->assertTrue($domain->isAutoRenew());
        $this->assertInstanceOf(\DateTimeInterface::class, $domain->getExpiresTime());
        $this->assertInstanceOf(\DateTimeInterface::class, $domain->getLockedUntilTime());
        $this->assertEquals('zone123', $domain->getZoneId());
    }

    public function testUpdateDomainDetailsWithInvalidDate(): void
    {
        $domain = $this->createDnsDomain();
        $this->persistAndFlush($domain->getIamKey());
        $this->persistAndFlush($domain);

        $detailData = [
            'status' => 'active',
            'expires_at' => 'invalid-date',
            'auto_renew' => false,
            'id' => 'zone123',
        ];

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $this->expectException(\Exception::class);

        $service->updateDomainDetails($domain, $detailData, $io);
    }

    public function testCreateOrUpdateDomainCreateNew(): void
    {
        $iamKey = $this->createIamKey();
        $this->persistAndFlush($iamKey);

        $domainData = [
            'name' => 'newdomain.com',
            'status' => 'active',
            'id' => 'zone123456',
        ];

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->createOrUpdateDomain($iamKey, $domainData, $io);

        $this->assertEquals('newdomain.com', $result->getName());
        $this->assertEquals($iamKey, $result->getIamKey());
        $this->assertTrue($result->isValid());
        $this->assertEquals('zone123456', $result->getZoneId());
    }

    public function testCreateOrUpdateDomainUpdateExisting(): void
    {
        $iamKey = $this->createIamKey();
        $existingDomain = $this->createDnsDomain();
        $existingDomain->setIamKey($iamKey);
        $existingDomain->setValid(false);
        $this->persistAndFlush($iamKey);
        $this->persistAndFlush($existingDomain);

        $domainData = [
            'name' => 'example.com',
            'status' => 'pending',
            'id' => 'new-zone-id',
        ];

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $result = $service->createOrUpdateDomain($iamKey, $domainData, $io);

        $this->assertSame($existingDomain, $result);
        $this->assertTrue($result->isValid());
        $this->assertEquals(DomainStatus::PENDING, $result->getStatus());
        $this->assertEquals('new-zone-id', $result->getZoneId());
    }

    public function testFindDomainsWithoutSpecificDomain(): void
    {
        $domain1 = $this->createDnsDomain();
        $domain2 = $this->createDnsDomain();
        $domain2->setName('test.com');
        $this->persistAndFlush($domain1->getIamKey());
        $this->persistAndFlush($domain1);
        $this->persistAndFlush($domain2);

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $result = $service->findDomains();

        $this->assertGreaterThanOrEqual(2, count($result));
        $this->assertContains($domain1, $result);
        $this->assertContains($domain2, $result);
    }

    public function testFindDomainsWithSpecificDomain(): void
    {
        $domain = $this->createDnsDomain();
        $domain->setName('example.com');
        $this->persistAndFlush($domain->getIamKey());
        $this->persistAndFlush($domain);

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $result = $service->findDomains('example.com');

        $this->assertGreaterThanOrEqual(1, count($result));
        $domains = array_filter($result, fn ($d) => 'example.com' === $d->getName());
        $this->assertNotEmpty($domains);
    }

    public function testUpdateDomainDetailsWithMissingStatus(): void
    {
        $domain = $this->createDnsDomain();
        $this->persistAndFlush($domain->getIamKey());
        $this->persistAndFlush($domain);

        $detailData = [
            'expires_at' => '2025-12-31T00:00:00Z',
            'auto_renew' => true,
            'id' => 'zone123',
        ];

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $service->updateDomainDetails($domain, $detailData, $io);

        $this->assertNull($domain->getStatus());
        $this->assertTrue($domain->isAutoRenew());
    }

    public function testUpdateDomainDetailsWithEmptyData(): void
    {
        $domain = $this->createDnsDomain();
        $this->persistAndFlush($domain->getIamKey());
        $this->persistAndFlush($domain);

        $detailData = [];

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $service->updateDomainDetails($domain, $detailData, $io);

        $this->assertEquals('example.com', $domain->getName());
    }

    public function testCreateOrUpdateDomainWithMalformedData(): void
    {
        $iamKey = $this->createIamKey();
        $this->persistAndFlush($iamKey);

        $domainData = [
            'status' => 'active',
            'id' => 'zone123456',
        ];

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain data must contain a "name" field');

        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $service->createOrUpdateDomain($iamKey, $domainData, $io);
    }

    public function testSyncDomainInfoWithoutIo(): void
    {
        $domain = $this->createDnsDomain();
        $this->persistAndFlush($domain->getIamKey());
        $this->persistAndFlush($domain);

        $listData = [
            'success' => true,
            'result' => [
                ['name' => 'example.com', 'status' => 'active'],
            ],
        ];
        $detailData = [
            'success' => true,
            'result' => [
                'status' => 'active',
                'auto_renew' => true,
                'id' => 'zone123',
            ],
        ];

        $mockHttpClient = $this->createSequentialMockHttpClient([$listData, $detailData]);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

        $result = $service->syncDomainInfo($domain);

        $this->assertTrue($result);
    }

    public function testUpdateDomainDetailsWithCreateTime(): void
    {
        $domain = $this->createDnsDomain();
        $this->persistAndFlush($domain->getIamKey());
        $this->persistAndFlush($domain);

        $detailData = [
            'created_at' => '2024-01-01T00:00:00Z',
            'status' => 'active',
            'id' => 'zone123',
        ];

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $service = $this->createDomainSynchronizerWithMockClient($mockHttpClient);

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

        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');
        $domain->setIamKey($iamKey);
        $domain->setValid(true);

        return $domain;
    }

    /**
     * 创建 Mock HTTP 客户端，返回预定义的响应
     *
     * @param array<string, mixed> $responseData
     */
    private function createMockHttpClient(array $responseData): HttpClientInterface
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('toArray')
            ->willReturn($responseData)
        ;
        $success = isset($responseData['success']) && true === $responseData['success'];
        $mockResponse->method('getStatusCode')
            ->willReturn($success ? 200 : 400)
        ;

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')
            ->willReturn($mockResponse)
        ;

        return $mockHttpClient;
    }

    /**
     * 创建顺序返回多个响应的 Mock HTTP 客户端
     *
     * @param array<int, array<string, mixed>> $responseDataList
     */
    private function createSequentialMockHttpClient(array $responseDataList): HttpClientInterface
    {
        $mockResponses = [];
        foreach ($responseDataList as $responseData) {
            $mockResponse = $this->createMock(ResponseInterface::class);
            $mockResponse->method('toArray')
                ->willReturn($responseData)
            ;
            $success = isset($responseData['success']) && true === $responseData['success'];
            $mockResponse->method('getStatusCode')
                ->willReturn($success ? 200 : 400)
            ;
            $mockResponses[] = $mockResponse;
        }

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')
            ->willReturnOnConsecutiveCalls(...$mockResponses)
        ;

        return $mockHttpClient;
    }

    /**
     * 创建使用 Mock HTTP 客户端的 DomainSynchronizer
     *
     * 由于 DomainSynchronizer 和 DnsDomainService 都是 final 类，无法直接 Mock。
     * 我们采用的策略是：通过匿名类扩展 DnsDomainService 来注入 Mock 的 HttpClient。
     * 这样可以保持真实的业务逻辑，只模拟网络层的交互。
     *
     * @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass
     */
    private function createDomainSynchronizerWithMockClient(HttpClientInterface $mockHttpClient): DomainSynchronizer
    {
        $logger = self::getService(LoggerInterface::class);
        $dnsDomainRepository = self::getService(DnsDomainRepository::class);

        // 创建使用 Mock HTTP 客户端的 DnsDomainService
        $dnsDomainService = new class($logger, $mockHttpClient) extends DnsDomainService {
            private HttpClientInterface $mockClient;

            public function __construct(LoggerInterface $logger, HttpClientInterface $mockClient)
            {
                parent::__construct($logger);
                $this->mockClient = $mockClient;
            }

            protected function getCloudFlareClient(DnsDomain $domain): CloudflareHttpClient
            {
                $iamKey = $domain->getIamKey();
                if (null === $iamKey) {
                    throw new CloudflareServiceException('Domain does not have an IAM key configured');
                }

                $accessKey = $iamKey->getAccessKey();
                $secretKey = $iamKey->getSecretKey();

                if (null === $accessKey || null === $secretKey) {
                    throw new CloudflareServiceException('IAM key is missing access key or secret key');
                }

                return new CloudflareHttpClient(
                    $accessKey,
                    $secretKey,
                    $this->mockClient,
                    $this->logger
                );
            }
        };

        // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass
        return new DomainSynchronizer(
            self::getEntityManager(),
            $dnsDomainRepository,
            $dnsDomainService,
            $logger
        );
    }
}
