<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\DnsDomainService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DnsDomainService::class)]
#[RunTestsInSeparateProcesses]
final class DnsDomainServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Tests don't require special setup
    }

    public function testListDomains(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => [],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);

        $domain = $this->createDnsDomain();

        $result = $service->listDomains($domain);
        $this->assertTrue($result['success']);
    }

    public function testGetDomain(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => ['name' => 'example.com'],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);

        $domain = $this->createDnsDomain();
        $domainName = 'example.com';

        $result = $service->getDomain($domain, $domainName);
        $this->assertTrue($result['success']);
    }

    public function testLookupZoneId(): void
    {
        // 创建一个 Mock Response，返回 JSON 字符串而不是数组
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')
            ->willReturn(json_encode([
                'success' => true,
                'result' => [
                    ['id' => 'test-zone-id', 'name' => 'example.com'],
                ],
            ]));

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')
            ->willReturn($mockResponse);

        $service = $this->createServiceWithMockClient($mockHttpClient);
        $domain = $this->createDnsDomain();

        $result = $service->lookupZoneId($domain);
        $this->assertSame('test-zone-id', $result);
    }

    public function testSyncZoneId(): void
    {
        // 创建一个基本的 Mock 客户端（syncZoneId 不会调用 HTTP 客户端，因为我们提供了 domainData）
        $mockHttpClient = $this->createMock(HttpClientInterface::class);

        $service = $this->createServiceWithMockClient($mockHttpClient);
        $domain = $this->createDnsDomain();
        $domainData = ['id' => 'custom-zone-id'];

        $result = $service->syncZoneId($domain, $domainData);
        $this->assertSame('custom-zone-id', $result);
        $this->assertSame('custom-zone-id', $domain->getZoneId());
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
        $iamKey->setName('Test Key');
        $iamKey->setAccessKey('test-access-key');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $domain->setIamKey($iamKey);

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
     * 创建使用 Mock HTTP 客户端的 DnsDomainService
     */
    private function createServiceWithMockClient(HttpClientInterface $mockHttpClient): DnsDomainService
    {
        $logger = self::getService(\Psr\Log\LoggerInterface::class);

        // 使用匿名类扩展服务，注入 Mock HTTP 客户端
        $service = new class($logger, $mockHttpClient) extends DnsDomainService {
            private HttpClientInterface $mockClient;

            public function __construct($logger, HttpClientInterface $mockClient)
            {
                parent::__construct($logger);
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
