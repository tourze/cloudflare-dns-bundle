<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Abstract\BaseCloudflareService;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
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
        // 创建 LoggerInterface 模拟对象
        $logger = $this->createMock(LoggerInterface::class);

        // 使用测试子类
        $service = new TestBaseCloudflareService($logger);

        $domain = $this->createDnsDomain();

        $client = $service->exposedGetCloudFlareClient($domain);

        // 由于方法已有明确的返回类型声明，此处添加有意义的断言
        $this->assertNotNull($client);
    }

    public function testHandleResponseSuccess(): void
    {
        // 创建 LoggerInterface 模拟对象
        $logger = $this->createMock(LoggerInterface::class);

        // 使用测试子类
        $service = new TestBaseCloudflareService($logger);

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
        // 创建 LoggerInterface 模拟对象
        $logger = $this->createMock(LoggerInterface::class);

        // 使用测试子类
        $service = new TestBaseCloudflareService($logger);

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

        $this->expectException(\RuntimeException::class);
        $service->exposedHandleResponse($failureResult, '操作失败');
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
