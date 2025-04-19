<?php

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\BaseCloudflareService;
use CloudflareDnsBundle\Service\CloudflareHttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BaseCloudflareServiceTest extends TestCase
{
    /**
     * @var MockObject&LoggerInterface
     */
    private $logger;
    private TestBaseCloudflareService $service;

    protected function setUp(): void
    {
        // 创建 LoggerInterface 模拟对象
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $this->logger = $logger;

        // 使用测试子类
        $this->service = new TestBaseCloudflareService($this->logger);
    }

    public function testGetCloudFlareClient(): void
    {
        $domain = $this->createDnsDomain();

        $client = $this->service->exposedGetCloudFlareClient($domain);

        $this->assertInstanceOf(CloudflareHttpClient::class, $client);
    }

    public function testHandleResponseSuccess(): void
    {
        $successResult = [
            'success' => true,
            'result' => ['id' => 'test-id'],
        ];

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with('操作成功', $this->callback(function ($context) use ($successResult) {
                return isset($context['result']) && $context['result'] === $successResult;
            }));

        $result = $this->service->exposedHandleResponse($successResult, '操作失败');
        $this->assertSame($successResult, $result);
    }

    public function testHandleResponseFailure(): void
    {
        $failureResult = [
            'success' => false,
            'errors' => [['code' => 1003, 'message' => 'Invalid access token']],
        ];

        // 配置 logger 的预期行为
        $this->logger->expects($this->once())
            ->method('error')
            ->with('操作失败', $this->callback(function ($context) {
                return isset($context['errors']);
            }));

        $this->expectException(\RuntimeException::class);
        $this->service->exposedHandleResponse($failureResult, '操作失败');
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
        $iamKey->setAccessKey('test-access-key');
        $iamKey->setSecretKey('test-secret-key');
        $domain->setIamKey($iamKey);

        return $domain;
    }
}

/**
 * 可测试的 BaseCloudflareService 子类，公开受保护的方法
 */
class TestBaseCloudflareService extends BaseCloudflareService
{
    /**
     * 公开获取 CloudflareHttpClient 的方法
     */
    public function exposedGetCloudFlareClient(DnsDomain $domain): CloudflareHttpClient
    {
        return $this->getCloudFlareClient($domain);
    }

    /**
     * 公开处理响应的方法
     */
    public function exposedHandleResponse(array $result, string $errorMessage, array $context = []): array
    {
        return $this->handleResponse($result, $errorMessage, $context);
    }
}
