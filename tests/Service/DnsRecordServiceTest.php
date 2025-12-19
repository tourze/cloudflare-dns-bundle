<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Exception\CloudflareServiceException;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DnsRecordService::class)]
#[RunTestsInSeparateProcesses]
final class DnsRecordServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Tests don't require special setup
    }

    public function testExtractDomain(): void
    {
        $service = self::getService(DnsRecordService::class);

        // 创建测试域名数据
        $domain = $this->createDnsDomain();
        $repository = self::getService(DnsDomainRepository::class);
        $repository->save($domain, flush: true);

        $result = $service->extractDomain('sub.example.com');

        $this->assertInstanceOf(DnsDomain::class, $result);
        $this->assertEquals('example.com', $result->getName());
    }

    public function testExtractDomainNotFound(): void
    {
        $service = self::getService(DnsRecordService::class);

        $result = $service->extractDomain('sub.notfound.com');

        $this->assertNull($result);
    }

    public function testRemoveRecord(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => ['id' => 'deleted'],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);

        $domain = $this->createDnsDomain();
        $record = $this->createDnsRecord();
        $record->setDomain($domain);

        // 验证不抛出异常
        $service->removeRecord($record);
        $this->assertTrue(true); // removeRecord 返回 void，只要不抛异常就是成功
    }

    public function testCreateRecord(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => ['id' => 'test-record-id', 'name' => 'test.example.com'],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);

        $domain = $this->createDnsDomain();
        $record = $this->createDnsRecord();

        $result = $service->createRecord($domain, $record);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('result', $result);
    }

    public function testUpdateRecord(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => ['id' => 'test-record-id', 'name' => 'test.example.com'],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);

        $domain = $this->createDnsDomain();
        $record = $this->createDnsRecord();
        $record->setDomain($domain);

        $result = $service->updateRecord($record);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('result', $result);
    }

    public function testBatchRecords(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => ['timing' => []],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);

        $domain = $this->createDnsDomain();
        $operations = [
            'posts' => [$this->createDnsRecord()],
        ];

        $result = $service->batchRecords($domain, $operations);
        $this->assertTrue($result['success']);
    }

    public function testListRecords(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => [
                ['id' => 'record-1', 'name' => 'test1.example.com'],
                ['id' => 'record-2', 'name' => 'test2.example.com'],
            ],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);

        $domain = $this->createDnsDomain();
        $params = ['page' => 1, 'per_page' => 20];

        $result = $service->listRecords($domain, $params);
        $this->assertTrue($result['success']);
        $this->assertIsArray($result['result']);
    }

    /**
     * 测试当 API 返回失败时的处理
     */
    public function testApiResponseFailure(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => false,
            'errors' => [['code' => 1003, 'message' => 'Invalid access token']],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);

        $domain = $this->createDnsDomain();
        $record = $this->createDnsRecord();
        $record->setDomain($domain);

        $this->expectException(CloudflareServiceException::class);
        $this->expectExceptionMessageMatches('/创建CloudFlare域名记录失败/');
        $service->createRecord($domain, $record);
    }

    public function testExportRecords(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')
            ->willReturn('test.example.com. 300 IN A 192.0.2.1');

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')
            ->willReturn($mockResponse);

        $service = $this->createServiceWithMockClient($mockHttpClient);
        $domain = $this->createDnsDomain();

        $result = $service->exportRecords($domain);
        $this->assertIsString($result);
        $this->assertStringContainsString('test.example.com', $result);
    }

    public function testImportRecords(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => ['recs_added' => 1],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);
        $domain = $this->createDnsDomain();
        $bindConfig = 'test.example.com. 300 IN A 192.0.2.1';

        $result = $service->importRecords($domain, $bindConfig);
        $this->assertTrue($result['success']);
    }

    public function testScanRecords(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => ['recs_added' => 0],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);
        $domain = $this->createDnsDomain();

        $result = $service->scanRecords($domain);
        $this->assertTrue($result['success']);
    }

    /**
     * 创建测试用的 DnsDomain 对象
     */
    private function createDnsDomain(): DnsDomain
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');

        $iamKey = new IamKey();
        $iamKey->setName('test-iam-key');
        $iamKey->setAccessKey('test-access-key');
        $iamKey->setSecretKey('test-secret-key');
        $domain->setIamKey($iamKey);

        return $domain;
    }

    /**
     * 创建测试用的 DnsRecord 对象
     */
    private function createDnsRecord(): DnsRecord
    {
        $record = new DnsRecord();
        $record->setRecordId('test-record-id');
        $record->setRecord('test.example.com');
        $record->setContent('192.0.2.1');
        $record->setTtl(120);
        $record->setProxy(false);

        return $record;
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
            ->willReturn($responseData);
        $success = isset($responseData['success']) && true === $responseData['success'];
        $mockResponse->method('getStatusCode')
            ->willReturn($success ? 200 : 400);

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')
            ->willReturn($mockResponse);

        return $mockHttpClient;
    }

    /**
     * 创建使用 Mock HTTP 客户端的 DnsRecordService
     */
    private function createServiceWithMockClient(HttpClientInterface $mockHttpClient): DnsRecordService
    {
        $logger = self::getService(\Psr\Log\LoggerInterface::class);
        $repository = self::getService(DnsDomainRepository::class);

        // 使用反射创建带有 Mock 客户端的服务
        $service = new class($logger, $repository, $mockHttpClient) extends DnsRecordService {
            private HttpClientInterface $mockClient;

            public function __construct($logger, $repository, HttpClientInterface $mockClient)
            {
                parent::__construct($logger, $repository);
                $this->mockClient = $mockClient;
            }

            /**
             * @return \CloudflareDnsBundle\Client\CloudflareHttpClient
             */
            protected function getCloudFlareClient(\CloudflareDnsBundle\Entity\DnsDomain $domain): \CloudflareDnsBundle\Client\CloudflareHttpClient
            {
                $iamKey = $domain->getIamKey();
                if (null === $iamKey) {
                    throw new \CloudflareDnsBundle\Exception\CloudflareServiceException('Domain does not have an IAM key configured');
                }

                $accessKey = $iamKey->getAccessKey();
                $secretKey = $iamKey->getSecretKey();

                if (null === $accessKey || null === $secretKey) {
                    throw new \CloudflareDnsBundle\Exception\CloudflareServiceException('IAM key is missing access key or secret key');
                }

                return new \CloudflareDnsBundle\Client\CloudflareHttpClient(
                    $accessKey,
                    $secretKey,
                    $this->mockClient,
                    $this->logger
                );
            }
        };

        return $service;
    }
}
