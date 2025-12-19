<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Abstract\BaseCloudflareService;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Exception\CloudflareServiceException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(BaseCloudflareService::class)]
#[RunTestsInSeparateProcesses]
final class BaseCloudflareServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Tests don't require special setup
    }

    public function testGetCloudFlareClient(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        // 使用匿名类继承 BaseCloudflareService 来测试 protected 方法
        $service = new class($logger) extends BaseCloudflareService {
            /**
             * 公开 getCloudFlareClient 方法用于测试
             */
            public function exposedGetCloudFlareClient(DnsDomain $domain): \CloudflareDnsBundle\Client\CloudflareHttpClient
            {
                return $this->getCloudFlareClient($domain);
            }
        };

        $domain = $this->createDnsDomain();

        $client = $service->exposedGetCloudFlareClient($domain);

        // 由于方法已有明确的返回类型声明，此处添加有意义的断言
        $this->assertNotNull($client);
        $this->assertInstanceOf(\CloudflareDnsBundle\Client\CloudflareHttpClient::class, $client);
    }

    public function testGetCloudFlareClientWithoutIamKey(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $service = new class($logger) extends BaseCloudflareService {
            public function exposedGetCloudFlareClient(DnsDomain $domain): \CloudflareDnsBundle\Client\CloudflareHttpClient
            {
                return $this->getCloudFlareClient($domain);
            }
        };

        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');
        // 不设置 IamKey

        $this->expectException(CloudflareServiceException::class);
        $this->expectExceptionMessage('Domain does not have an IAM key configured');
        $service->exposedGetCloudFlareClient($domain);
    }

    public function testGetCloudFlareClientWithIncompleteIamKey(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $service = new class($logger) extends BaseCloudflareService {
            public function exposedGetCloudFlareClient(DnsDomain $domain): \CloudflareDnsBundle\Client\CloudflareHttpClient
            {
                return $this->getCloudFlareClient($domain);
            }
        };

        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');

        $iamKey = new IamKey();
        $iamKey->setAccessKey('test-access-key');
        // 不设置 SecretKey
        $domain->setIamKey($iamKey);

        $this->expectException(CloudflareServiceException::class);
        $this->expectExceptionMessage('IAM key is missing access key or secret key');
        $service->exposedGetCloudFlareClient($domain);
    }

    public function testHandleResponseSuccess(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        // 使用匿名类继承 BaseCloudflareService 来测试 protected 方法
        $service = new class($logger) extends BaseCloudflareService {
            /**
             * 公开 handleResponse 方法用于测试
             *
             * @param array<string, mixed> $result
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function exposedHandleResponse(array $result, string $errorMessage, array $context = []): array
            {
                return $this->handleResponse($result, $errorMessage, $context);
            }
        };

        $successResult = [
            'success' => true,
            'result' => ['id' => 'test-id'],
        ];

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('info')
            ->with('操作成功', self::callback(function ($context) use ($successResult) {
                return is_array($context) && isset($context['result']) && $context['result'] === $successResult;
            }))
        ;

        $result = $service->exposedHandleResponse($successResult, '操作失败');
        $this->assertSame($successResult, $result);
    }

    public function testHandleResponseFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $service = new class($logger) extends BaseCloudflareService {
            /**
             * @param array<string, mixed> $result
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function exposedHandleResponse(array $result, string $errorMessage, array $context = []): array
            {
                return $this->handleResponse($result, $errorMessage, $context);
            }
        };

        $failureResult = [
            'success' => false,
            'errors' => [['code' => 1003, 'message' => 'Invalid access token']],
        ];

        // 配置 logger 的预期行为
        $logger->expects($this->once())
            ->method('error')
            ->with('操作失败', self::callback(function ($context) {
                return is_array($context) && isset($context['errors']);
            }))
        ;

        $this->expectException(CloudflareServiceException::class);
        $this->expectExceptionMessageMatches('/操作失败/');
        $service->exposedHandleResponse($failureResult, '操作失败');
    }

    public function testToArraySafely(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $service = new class($logger) extends BaseCloudflareService {
            /**
             * @return array<string, mixed>
             */
            public function exposedToArraySafely(mixed $response): array
            {
                return $this->toArraySafely($response);
            }
        };

        // 测试有效响应对象
        $mockResponse = new class {
            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return ['success' => true, 'result' => ['id' => 'test']];
            }
        };

        $result = $service->exposedToArraySafely($mockResponse);
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);

        // 测试无效响应（不是对象）
        $result = $service->exposedToArraySafely('not an object');
        $this->assertSame([], $result);

        // 测试无 toArray 方法的对象
        $result = $service->exposedToArraySafely(new \stdClass());
        $this->assertSame([], $result);
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
}
