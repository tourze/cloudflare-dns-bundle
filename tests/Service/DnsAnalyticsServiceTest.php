<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\DnsAnalyticsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DnsAnalyticsService::class)]
#[RunTestsInSeparateProcesses]
final class DnsAnalyticsServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Tests don't require special setup
    }

    public function testGetDnsAnalytics(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => ['data' => []],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);

        $domain = $this->createDnsDomain();
        $params = ['since' => '-6h', 'until' => 'now'];

        $result = $service->getDnsAnalytics($domain, $params);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('result', $result);
    }

    public function testGetDnsAnalyticsByTime(): void
    {
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => ['data' => []],
        ]);

        $service = $this->createServiceWithMockClient($mockHttpClient);

        $domain = $this->createDnsDomain();
        $params = ['since' => '-6h', 'until' => 'now', 'time_delta' => '1h'];

        $result = $service->getDnsAnalyticsByTime($domain, $params);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('result', $result);
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
     * 创建使用 Mock HTTP 客户端的 DnsAnalyticsService
     */
    private function createServiceWithMockClient(HttpClientInterface $mockHttpClient): DnsAnalyticsService
    {
        $logger = self::getService(\Psr\Log\LoggerInterface::class);

        // 使用匿名类扩展服务，注入 Mock HTTP 客户端
        $service = new class($logger, $mockHttpClient) extends DnsAnalyticsService {
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
